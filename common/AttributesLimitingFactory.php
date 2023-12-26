<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Common;

use Closure;
use function str_starts_with;
use function strlen;

/**
 * An {@link AttributesFactory} that applies attributes limits.
 *
 * @see https://opentelemetry.io/docs/specs/otel/common/#attribute-limits
 *
 * @psalm-type AttributeKeyFilter = Closure(string): bool
 * @psalm-type AttributeValueFilter = Closure(mixed, string): bool
 */
final class AttributesLimitingFactory implements AttributesFactory {

    /**
     * @param AttributeKeyFilter|null $attributeKeyFilter
     * @param AttributeValueFilter|null $attributeValueFilter
     */
    private function __construct(
        private readonly ?int $attributeCountLimit,
        private readonly ?int $attributeValueLengthLimit,
        private readonly ?Closure $attributeKeyFilter,
        private readonly ?Closure $attributeValueFilter,
    ) {}

    /**
     * Creates a new attributes factory that applies the given limits.
     *
     * @param int|null $attributeCountLimit maximum number of attributes,
     *        attributes exceeding this limit will be dropped
     * @param int|null $attributeValueLengthLimit maximum length of string
     *        valued attributes, values exceeding this limit will be truncated
     * @param AttributeKeyFilter|null $attributeKeyFilter filter callback,
     *        attribute keys that do not pass this filter will be dropped
     * @param AttributeValueFilter|null $attributeValueFilter filter callback,
     *        attribute values that do not pass this filter will be dropped
     */
    public static function create(
        ?int $attributeCountLimit = 128,
        ?int $attributeValueLengthLimit = null,
        ?Closure $attributeKeyFilter = null,
        ?Closure $attributeValueFilter = null,
    ): AttributesFactory {
        return new self($attributeCountLimit, $attributeValueLengthLimit, $attributeKeyFilter, $attributeValueFilter);
    }

    /**
     * Rejects attributes with the given name or namespace.
     *
     * @param string|array $rejected attribute names and namespaces to reject
     * @return AttributeKeyFilter filter callback
     */
    public static function rejectKeyFilter(string|array $rejected): Closure {
        return static function(string $key) use ($rejected): bool {
            foreach ((array) $rejected as $rejectedKey) {
                if (str_starts_with($key, $rejectedKey) && ($key[strlen($rejectedKey)] ?? '.') === '.') {
                    return false;
                }
            }

            return true;
        };
    }

    public function builder(): AttributesBuilder {
        return new AttributesLimitingBuilder(
            $this->attributeCountLimit,
            $this->attributeValueLengthLimit,
            $this->attributeKeyFilter,
            $this->attributeValueFilter,
        );
    }
}
