<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common\Schema;

use Nevay\OTelSDK\Common\Resource;

/**
 * @experimental
 */
interface ResourceTransformer {

    /**
     * @throws TransformationException
     */
    public function transformResource(Resource $resource, string $schemaUrl): Resource;
}
