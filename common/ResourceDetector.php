<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Common;

/**
 * Detects resource information from the environment.
 *
 * @see https://opentelemetry.io/docs/specs/otel/resource/sdk/#detecting-resource-information-from-the-environment
 */
interface ResourceDetector {

    /**
     * Detects resource information.
     *
     * @return Resource detected resource information
     */
    public function getResource(): Resource;
}
