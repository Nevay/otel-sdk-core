<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

use Composer\InstalledVersions;
use InvalidArgumentException;
use function array_key_first;
use function assert;
use function bin2hex;
use function count;
use function random_bytes;
use function sprintf;
use function substr;

/**
 * An immutable representation of the entity producing telemetry.
 *
 * @see https://opentelemetry.io/docs/specs/otel/resource/sdk/
 *
 * @psalm-import-type AttributeValue from Attributes
 */
final class Resource {

    public function __construct(
        public readonly Attributes $attributes,
        public readonly ?string $schemaUrl = null,
    ) {}

    /**
     * Returns the default resource.
     *
     * @return Resource default resource
     *
     * @see https://opentelemetry.io/docs/specs/semconv/resource/#semantic-attributes-with-sdk-provided-default-value
     */
    public static function default(): Resource {
        static $default;
        return $default ??= new Resource(new Attributes([
            'telemetry.sdk.language' => 'php',
            'telemetry.sdk.name' => 'tbachert/otel-sdk',
            'telemetry.sdk.version' => self::packageVersion('tbachert/otel-sdk-core') ?? 'unknown',
            'service.name' => 'unknown_service:php',
            'service.instance.id' => self::uuid4(),
        ]));
    }

    /**
     * Creates a resource from the given attributes.
     *
     * @param iterable<non-empty-string, AttributeValue> $attributes resource attributes
     * @param string|null $schemaUrl schema url
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
     * Merges the given resource with this resource.
     *
     * @param Resource $updating the updating resource, has to have matching
     *        schema url
     * @return Resource merged resource
     * @throws InvalidArgumentException if schema urls do not match
     *
     * @see https://opentelemetry.io/docs/specs/otel/resource/sdk/#merge
     */
    public function merge(Resource $updating): Resource {
        return self::mergeAll(updating: $updating, old: $this);
    }

    /**
     * Merges multiple resources into a single resource.
     *
     * @param Resource ...$resources resources in descending priority, have to
     *        have matching schema urls
     * @return Resource merged resource
     * @throws InvalidArgumentException if schema urls do not match
     *
     * @see https://opentelemetry.io/docs/specs/otel/resource/sdk/#merge
     */
    public static function mergeAll(Resource ...$resources): Resource {
        if (count($resources) === 1) {
            return $resources[array_key_first($resources)];
        }

        $schemaUrl = null;
        $attributes = [];
        $attributesDropped = 0;
        foreach ($resources as $key => $resource) {
            $schemaUrl ??= $resource->schemaUrl;
            if ($schemaUrl !== $resource->schemaUrl && $resource->schemaUrl !== null) {
                assert($schemaUrl !== null);
                throw new InvalidArgumentException(sprintf(
                    'Resource schema url mismatch, cannot merge "%s" ("%s") with "%s"',
                    $resource->schemaUrl, $key, $schemaUrl,
                ));
            }

            $attributes += $resource->attributes->toArray();
            $attributesDropped += $resource->attributes->getDroppedAttributesCount();
        }

        return new Resource(
            new Attributes($attributes, $attributesDropped),
            $schemaUrl,
        );
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
