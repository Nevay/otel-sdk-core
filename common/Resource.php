<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

use Composer\InstalledVersions;
use InvalidArgumentException;
use Nevay\SPI\ServiceLoader;
use function array_key_first;
use function assert;
use function class_exists;
use function count;
use function sprintf;

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
            'telemetry.sdk.version' => self::packageVersion('tbachert/otel-sdk') ?? 'unknown',
            'service.name' => 'unknown_service:php',
        ]));
    }

    /**
     * Detects resource information using all registered resource detectors.
     *
     * @return Resource detected resource
     *
     * @see ResourceDetector
     * @see ServiceLoader::register()
     */
    public static function detect(): Resource {
        $resources = [];
        foreach (ServiceLoader::load(ResourceDetector::class) as $detector) {
            $resources[] = $detector->getResource();
        }
        $resources[] = Resource::default();

        return Resource::mergeAll(...$resources);
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
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package)) {
            return InstalledVersions::getPrettyVersion($package);
        }

        return null;
    }
}
