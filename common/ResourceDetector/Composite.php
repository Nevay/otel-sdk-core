<?php declare(strict_types=1);

namespace Nevay\OTelSDK\Common\ResourceDetector;

use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\ResourceDetector;
use function Amp\async;

final class Composite implements ResourceDetector {

    private readonly array $detectors;

    public function __construct(ResourceDetector ...$detectors) {
        $this->detectors = $detectors;
    }

    public function getResource(): Resource {
        $resources = [];
        foreach ($this->detectors as $key => $detector) {
            $resources[$key] = async($detector->getResource(...));
        }
        foreach ($resources as &$resource) {
            $resource = $resource->await();
        }
        unset($resource);

        return Resource::mergeAll(...$resources);
    }
}
