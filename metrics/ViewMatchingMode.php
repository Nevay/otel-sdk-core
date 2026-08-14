<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Metrics;

/**
 * Controls how multiple matching {@link View}s are applied to an {@link Instrument}.
 *
 * @experimental
 */
enum ViewMatchingMode {

    /**
     * Each matching View creates a separate metric stream independently of any other Views
     * registered for the same matching Instrument.
     */
    case Independent;
    /**
     * Matching Views are combined (merged) to modify metrics streams.
     */
    case Composable;
}
