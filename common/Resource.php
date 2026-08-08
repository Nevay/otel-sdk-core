<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

use Closure;
use Composer\InstalledVersions;
use InvalidArgumentException;
use function array_diff_key;
use function array_intersect_key;
use function array_key_first;
use function array_keys;
use function array_map;
use function array_unshift;
use function array_values;
use function assert;
use function bin2hex;
use function count;
use function in_array;
use function random_bytes;
use function sprintf;
use function strval;
use function substr;

/**
 * An immutable representation of the entity producing telemetry.
 *
 * @see https://opentelemetry.io/docs/specs/otel/resource/sdk/
 *
 * @psalm-import-type AttributeValue from Attributes
 */
final class Resource {

    public readonly Attributes $attributes;
    /** @var non-empty-string|null */
    public readonly ?string $schemaUrl;
    /** @var list<EntityRef> */
    public readonly array $entities;

    /**
     * @param non-empty-string|null $schemaUrl
     * @param list<EntityRef> $entities
     *
     * @internal
     */
    public function __construct(Attributes $attributes, ?string $schemaUrl = null, array $entities = []) {
        $this->attributes = $attributes;
        $this->schemaUrl = $schemaUrl;
        $this->entities = $entities;

        assert((function(): bool {
            self::ensureResourceSchemaUrlMatchesEntities($this->schemaUrl, $this->entities);
            self::ensureResourceAttributesContainEntityAttributes($this->attributes, $this->entities);
            return true;
        })());
    }

    /**
     * Returns the default resource.
     *
     * @return Resource default resource
     *
     * @see https://opentelemetry.io/docs/specs/semconv/resource/#semantic-attributes-with-sdk-provided-default-value
     */
    public static function default(): Resource {
        static $default;
        return $default ??= new Resource(
            attributes: new Attributes([
                'telemetry.sdk.language' => 'php',
                'telemetry.sdk.name' => 'tbachert/otel-sdk',
                'telemetry.sdk.version' => self::packageVersion('tbachert/otel-sdk-core') ?? 'unknown',
                'service.name' => 'unknown_service:php',
                'service.instance.id' => self::uuid4(),
            ]),
            schemaUrl: 'https://opentelemetry.io/schemas/1.43.0',
            entities: [
                new EntityRef(
                    type: 'telemetry.sdk',
                    identity: ['telemetry.sdk.language', 'telemetry.sdk.name'],
                    description: ['telemetry.sdk.version'],
                    schemaUrl: 'https://opentelemetry.io/schemas/1.43.0',
                ),
                new EntityRef(
                    type: 'service',
                    identity: ['service.name'],
                    schemaUrl: 'https://opentelemetry.io/schemas/1.43.0',
                ),
                new EntityRef(
                    type: 'service.instance',
                    identity: ['service.instance.id'],
                    schemaUrl: 'https://opentelemetry.io/schemas/1.43.0',
                ),
            ],
        );
    }

    /**
     * Creates a resource from the given attributes.
     *
     * @param iterable<non-empty-string, AttributeValue> $attributes resource attributes
     * @param non-empty-string|null $schemaUrl schema url
     * @return Resource created resource
     *
     * @see https://opentelemetry.io/docs/specs/otel/resource/sdk/#create
     */
    public static function create(iterable $attributes = [], ?string $schemaUrl = null): Resource {
        return new Resource(
            (new AttributesLimitingBuilder())
                ->addAll($attributes)
                ->build(),
            $schemaUrl,
        );
    }

    /**
     * Filters resource attributes using the given filter.
     *
     * @param Closure(string): bool $filter filter to apply
     *
     * @experimental
     */
    public function filterAttributes(Closure $filter): Resource {
        $removed = [];
        foreach ($this->attributes as $key => $_) {
            if (!$filter($key)) {
                $removed[$key] = '__RESOURCE_FILTERED_ATTRIBUTE__';
            }
        }

        if (!$removed) {
            return $this;
        }

        // merge with conflicting values to drop entities with conflicting identity
        $resource = self::mergeAll(
            new Resource(new Attributes($removed)),
            $this,
        );

        return new Resource(
            attributes: new Attributes(array_diff_key($resource->attributes->toArray(), $removed)),
            schemaUrl: $resource->schemaUrl,
            entities: $resource->entities,
        );
    }

    /**
     * @param Entity $entity entity to add
     * @param Entity ...$entities additional entities to add in descending priority
     * @return Resource resulting resource
     *
     * @experimental
     */
    public function withEntity(Entity $entity, Entity ...$entities): Resource {
        $attributesFactory = UnlimitedAttributesFactory::create();
        $entityResources = [];

        array_unshift($entities, $entity);
        foreach ($entities as $entity) {
            $attributes = $attributesFactory
                ->builder()
                ->addAll($entity->identity)
                ->addAll($entity->description)
                ->build();

            if (count($attributes) !== count($entity->identity) + count($entity->description)) {
                throw new InvalidArgumentException(sprintf('Entity "%s" cannot have attribute "%s" as both identity and description attribute', $entity->type, array_key_first(array_intersect_key($entity->identity, $entity->description))));
            }

            $identity = array_map(strval(...), array_keys($entity->identity));
            $description = array_map(strval(...), array_keys($entity->description));

            $entityResources[] = new Resource(
                attributes: $attributes,
                schemaUrl: $entity->schemaUrl,
                entities: [
                    new EntityRef(
                        type: $entity->type,
                        identity: $identity,
                        description: $description,
                        schemaUrl: $entity->schemaUrl,
                    ),
                ],
            );
        }

        return self::mergeAll($this, ...$entityResources);
    }

    /**
     * Merges the given resource with this resource.
     *
     * @param Resource $updating the updating resource, has to have matching
     *        schema url
     * @return Resource merged resource
     *
     * @see https://opentelemetry.io/docs/specs/otel/resource/sdk/#merge
     */
    public function merge(Resource $updating): Resource {
        return self::mergeAll($updating, $this);
    }

    /**
     * Merges multiple resources into a single resource.
     *
     * ---
     *
     * Deviates from spec:
     * - uses the highest-priority entity identity instead of the lowest-priority entity identity
     *   to allow using fallback entity detectors
     *     (e.g. `service.name` set by `service` > `composer` > `unknown_service`)
     * - drops conflicting description attributes instead of dropping the entire entity
     * - preserves `null` schema URLs as indicator for explicitly unset/missing schema URLs
     *
     * @param Resource ...$resources resources in descending priority, have to
     *        have matching schema urls
     * @return Resource merged resource
     *
     * @see https://opentelemetry.io/docs/specs/otel/resource/sdk/#merge
     */
    public static function mergeAll(Resource ...$resources): Resource {
        if (count($resources) === 1) {
            return $resources[array_key_first($resources)];
        }

        $schemaUrls = [];
        $attributes = [];
        $entities = [];
        foreach ($resources as $resource) {
            $lockedAttributes = $attributes;
            $markedForRemoval = [];
            $resourceAttributes = $resource->attributes->toArray();
            $attributes += $resourceAttributes;

            if ($lockedAttributes !== $attributes) {
                $schemaUrls[$resource->schemaUrl ?? ''] = true;
            }

            foreach ($resource->entities as $entity) {
                if (!self::attributesMatch($attributes, $resourceAttributes, $entity->identity)) {
                    $markedForRemoval[] = $entity;
                    continue;
                }

                if (!isset($entities[$entity->type])) {
                    $entities[$entity->type] = new EntityRef(
                        type: $entity->type,
                        identity: $entity->identity,
                        schemaUrl: $entity->schemaUrl,
                    );
                    $schemaUrls[$entity->schemaUrl ?? ''] = true;
                    foreach ($entity->identity as $key) {
                        $lockedAttributes[$key] = true;
                    }
                }

                $e = $entities[$entity->type];

                if (!self::canMergeEntities($e, $entity)) {
                    $markedForRemoval[] = $entity;
                    continue;
                }
                if ($e->description === $entity->description) {
                    continue;
                }

                $description = $e->description;
                foreach ($entity->description as $key) {
                    if ($attributes[$key] === $resourceAttributes[$key] && !in_array($key, $description, true)) {
                        $description[] = $key;
                        $lockedAttributes[$key] = true;
                    }
                }

                if ($e->description !== $description) {
                    $entities[$e->type] = new EntityRef($e->type, $e->identity, $description, $e->schemaUrl);
                }
            }

            foreach ($markedForRemoval as $entity) {
                foreach (self::entityAttributes($entity) as $key) {
                    if (!isset($lockedAttributes[$key])) {
                        unset($attributes[$key]);
                    }
                }
            }
        }

        $schemaUrl = null;
        if (count($schemaUrls) === 1) {
            unset($schemaUrls['']);
            $schemaUrl = array_key_first($schemaUrls);
        }

        return new Resource(
            attributes: new Attributes($attributes),
            schemaUrl: $schemaUrl,
            entities: array_values($entities),
        );
    }

    /**
     * @return iterable<string>
     */
    private static function entityAttributes(EntityRef $entity): iterable {
        yield from $entity->identity;
        yield from $entity->description;
    }

    private static function canMergeEntities(EntityRef $left, EntityRef $right): bool {
        return assert($left->type === $right->type)
            && $left->schemaUrl === $right->schemaUrl
            && ($left->identity === $right->identity || count($left->identity) === count($right->identity) && !array_diff($left->identity, $right->identity));
    }

    private static function attributesMatch(array $left, array $right, iterable $keys): bool {
        foreach ($keys as $key) {
            if ($left[$key] !== $right[$key]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<EntityRef> $entities entities
     */
    private static function ensureResourceSchemaUrlMatchesEntities(?string $schemaUrl, array $entities): void {
        if ($schemaUrl === null) {
            return;
        }

        foreach ($entities as $entity) {
            if ($entity->schemaUrl !== $schemaUrl) {
                throw new InvalidArgumentException(sprintf('Resource schema url must match entity schema url, cannot specify resource schema url "%s" as entity "%s" uses different schema url "%s"', $schemaUrl, $entity->type, $entity->schemaUrl));
            }
        }
    }

    /**
     * @param list<EntityRef> $entities entities
     */
    private static function ensureResourceAttributesContainEntityAttributes(Attributes $attributes, array $entities): void {
        foreach ($entities as $entity) {
            foreach (self::entityAttributes($entity) as $key) {
                if (!$attributes->has($key)) {
                    throw new InvalidArgumentException(sprintf('Resource does not contain attribute "%s" referenced by entity "%s"', $key, $entity->type));
                }
            }
        }
    }

    private static function packageVersion(string $package): ?string {
        return InstalledVersions::isInstalled($package)
            ? InstalledVersions::getVersion($package)
            : null;
    }

    private static function uuid4(): string {
        /*
        https://datatracker.ietf.org/doc/html/rfc4122#section-4.4
        https://datatracker.ietf.org/doc/html/rfc4122#section-4.1.2

        4.4.  Algorithms for Creating a UUID from Truly Random or
              Pseudo-Random Numbers
           o  Set the two most significant bits (bits 6 and 7) of the
              clock_seq_hi_and_reserved to zero and one, respectively.
           o  Set the four most significant bits (bits 12 through 15) of the
              time_hi_and_version field to the 4-bit version number from
              Section 4.1.3.
           o  Set all the other bits to randomly (or pseudo-randomly) chosen
              values.
         */
        $b = random_bytes(16);
        $b[8] = $b[8] & "\x3f" | "\x80";
        $b[6] = $b[6] & "\x0f" | "\x40";
        $h = bin2hex($b);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($h, 0, 8),
            substr($h, 8, 4),
            substr($h, 12, 4),
            substr($h, 16, 4),
            substr($h, 20, 12),
        );
    }
}
