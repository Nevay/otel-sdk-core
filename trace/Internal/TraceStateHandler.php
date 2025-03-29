<?php declare(strict_types=1);
namespace Nevay\OTelSDK\Trace\Internal;

use Psr\Log\LoggerInterface;
use function strlen;
use function strpos;
use function substr_compare;
use function substr_replace;

/**
 * @internal
 */
final class TraceStateHandler {

    /**
     * @param array{int, int}|null $seek
     */
    public static function set(string $ot, ?array $seek, string $key, string $value, bool $unsetIfLengthExceeded = false, ?LoggerInterface $logger = null): string {
        if ([$offset, $length] = $seek) {
            if (strlen($ot) - $length + strlen($value) > 256) {
                $logger?->warning('Setting TraceState.ot {key}:{value} would exceed maximum length', ['key' => $key, 'value' => $value, 'unset' => $unsetIfLengthExceeded]);

                if ($unsetIfLengthExceeded) {
                    $ot = self::unset($ot, $seek, $key);
                }

                return $ot;
            }

            $ot = substr_replace($ot, $value, $offset, $length);
        } else {
            if (strlen($ot) + ($ot !== '') + strlen($key) + 1 + strlen($value) > 256) {
                $logger?->warning('Setting TraceState.ot {key}:{value} would exceed maximum length', ['key' => $key, 'value' => $value, 'unset' => $unsetIfLengthExceeded]);
                return $ot;
            }

            if ($ot !== '') {
                $ot .= ';';
            }
            $ot .= $key;
            $ot .= ':';
            $ot .= $value;
        }

        return $ot;
    }

    /**
     * @param array{int, int}|null $seek
     */
    public static function unset(string $ot, ?array $seek, string $key): string {
        if ([$offset, $length] = $seek) {
            $ot = substr_replace($ot, '', $offset - strlen($key) - 1, $length + strlen($key) + 1);
        }

        return $ot;
    }

    /**
     * @return array{int, int}|null
     */
    public static function seek(string $ot, string $key): ?array {
        for ($i = 0, $n = strlen($ot), $l = strlen($key); $i < $n; $i = $d + 1) {
            $d = strpos($ot, ';', $i);
            if ($d === false) {
                $d = $n;
            }
            if (($ot[$i + $l] ?? '') !== ':' || substr_compare($ot, $key, $i, $l)) {
                continue;
            }

            $i += $l + 1;

            return [$i, $d - $i];
        }

        return null;
    }
}
