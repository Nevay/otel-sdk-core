<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Logs;

use Amp\Cancellation;
use Closure;
use Nevay\OTelSDK\Common\AttributesLimitingFactory;
use Nevay\OTelSDK\Common\Clock;
use Nevay\OTelSDK\Common\Configurator;
use Nevay\OTelSDK\Common\Resource;
use Nevay\OTelSDK\Common\SystemClock;
use Nevay\OTelSDK\Common\UnlimitedAttributesFactory;
use Nevay\OTelSDK\Logs\Internal\LogDiscardedLogRecordProcessor;
use Nevay\OTelSDK\Logs\Internal\SelfDiagnosticsLogRecordProcessor;
use Nevay\OTelSDK\Logs\LogRecordProcessor\MultiLogRecordProcessor;
use Nevay\OTelSDK\Logs\LogRecordProcessor\NoopLogRecordProcessor;
use OpenTelemetry\API\Configuration\Context;
use OpenTelemetry\API\Logs\LoggerInterface;
use Psr\Log\NullLogger;
use function array_values;

final class LoggerProvider implements LoggerProviderInterface {

    private readonly Internal\LoggerProvider $loggerProvider;

    /** @var array<LogRecordProcessor> */
    private array $logRecordProcessors = [];
    /** @var array<LogRecordProcessor> */
    private array $diagnosticLogRecordProcessors = [];

    public function __construct(?Clock $clock = null) {
        $this->loggerProvider = new Internal\LoggerProvider(
            null,
            Resource::default(),
            UnlimitedAttributesFactory::create(),
            new Configurator\NoopConfigurator(),
            $clock ?? SystemClock::create(),
            new NoopLogRecordProcessor(),
            AttributesLimitingFactory::create(),
            new NullLogger(),
        );
    }

    public function update(Closure $update): void {
        $state = new LoggerState(
            configurator: $this->loggerProvider->configurator,
            logRecordProcessors: $this->logRecordProcessors,
            resource: $this->loggerProvider->loggerState->resource,
            logRecordAttributesFactory: $this->loggerProvider->loggerState->logRecordAttributesFactory,
        );

        $update($state);

        $this->logRecordProcessors = $state->logRecordProcessors;

        $this->loggerProvider->loggerState->resource = $state->resource;
        $this->loggerProvider->loggerState->logRecordProcessor = MultiLogRecordProcessor::composite(...array_values($this->logRecordProcessors), ...$this->diagnosticLogRecordProcessors);
        $this->loggerProvider->loggerState->logRecordAttributesFactory = $state->logRecordAttributesFactory;

        $this->loggerProvider->configurator = $state->configurator;

        $this->loggerProvider->reload();
    }

    /**
     * @internal
     */
    public function initSelfDiagnostics(Context $selfDiagnostics): void {
        $logDiscardedLogRecordProcessor = new LogDiscardedLogRecordProcessor($selfDiagnostics->logger);
        $selfDiagnosticsLogRecordProcessor = new SelfDiagnosticsLogRecordProcessor($selfDiagnostics->meterProvider);

        $this->diagnosticLogRecordProcessors = [
            $logDiscardedLogRecordProcessor,
            $selfDiagnosticsLogRecordProcessor,
        ];

        $this->loggerProvider->loggerState->logRecordProcessor = MultiLogRecordProcessor::composite(...array_values($this->logRecordProcessors), ...$this->diagnosticLogRecordProcessors);
        $this->loggerProvider->loggerState->logger = $selfDiagnostics->logger;
    }

    public function getLogger(string $name, ?string $version = null, ?string $schemaUrl = null, iterable $attributes = []): LoggerInterface {
        return $this->loggerProvider->getLogger($name, $version, $schemaUrl, $attributes);
    }

    public function shutdown(?Cancellation $cancellation = null): bool {
        return $this->loggerProvider->shutdown($cancellation);
    }

    public function forceFlush(?Cancellation $cancellation = null): bool {
        return $this->loggerProvider->forceFlush($cancellation);
    }
}
