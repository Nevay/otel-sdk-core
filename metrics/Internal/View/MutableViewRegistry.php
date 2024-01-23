<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics\Internal\View;

use Generator;
use Nevay\OTelSDK\Common\InstrumentationScope;
use Nevay\OTelSDK\Metrics\Instrument;
use Nevay\OTelSDK\Metrics\InstrumentType;
use Nevay\OTelSDK\Metrics\View;

final class MutableViewRegistry implements ViewRegistry {

    /** @var list<Selector> */
    private array $selectors = [];
    /** @var list<View> */
    private array $views = [];

    public function register(
        View $view,
        ?InstrumentType $type = null,
        ?string $name = null,
        ?string $unit = null,
        ?string $meterName = null,
        ?string $meterVersion = null,
        ?string $meterSchemaUrl = null,
    ): self {
        $this->selectors[] = new Selector($type, $name, $unit, $meterName, $meterVersion, $meterSchemaUrl);
        $this->views[] = $view;

        return $this;
    }

    public function find(Instrument $instrument, InstrumentationScope $instrumentationScope): ?iterable {
        $views = (function() use ($instrument, $instrumentationScope): Generator {
            foreach ($this->selectors as $index => $selector) {
                if ($selector->accepts($instrument, $instrumentationScope)) {
                    yield $this->views[$index];
                }
            }
        })();

        return $views->valid() ? $views : null;
    }
}
