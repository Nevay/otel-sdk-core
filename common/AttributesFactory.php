<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Common;

/**
 * An {@link AttributesBuilder} factory.
 */
interface AttributesFactory {

    /**
     * Returns a new attribute builder.
     *
     * @return AttributesBuilder attribute builder
     */
    public function builder(): AttributesBuilder;
}
