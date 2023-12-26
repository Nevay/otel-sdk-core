<?php declare(strict_types=1);
namespace Nevay\OtelSDK\Common;

use Throwable;
use function basename;
use function count;
use function is_iterable;
use function method_exists;
use function sprintf;
use function str_repeat;
use function strtr;

/**
 * @psalm-type Frame = array{
 *     function: string,
 *     class: ?class-string,
 *     type: ?string,
 *     file: ?string,
 *     line: ?int,
 * }
 * @psalm-type Frames = non-empty-list<Frame>
 */
final class StackTrace {

    /**
     * Enables dot separated names.
     * ```
     * Namespace\Class::method(File.php:1)
     * Namespace.Class.method(File.php:1)
     *
     * Namespace\function(file.php:1)
     * Namespace.function(file.php:1)
     * ```
     */
    public final const DOT_SEPARATOR = 0x01;

    /**
     * Formats an exception in a java-like format.
     *
     * @param Throwable $e exception to format
     * @return string formatted exception
     *
     * @see https://docs.oracle.com/en/java/javase/17/docs/api/java.base/java/lang/Throwable.html#printStackTrace()
     */
    public static function format(Throwable $e, int $flags = 0): string {
        $s = '';
        self::write($s, $flags, $e);

        return $s;
    }

    /**
     * @param Frames|null $enclosing
     * @param array<int, Throwable> $seen
     */
    private static function write(string &$s, int $flags, Throwable $e, ?array $enclosing = null, array &$seen = [], int $indent = 0): void {
        $kind = 'Suppressed';
        do {
            if ($enclosing) {
                self::writeNewline($s, $indent);
                $s .= $kind;
                $s .= ': ';
            }
            if (isset($seen[spl_object_id($e)])) {
                $s .= '[CIRCULAR REFERENCE: ';
                self::writeInlineHeader($s, $flags, $e);
                $s .= ']';
                break;
            }
            $seen[spl_object_id($e)] = $e;

            $frames = self::frames($e);
            self::writeInlineHeader($s, $flags, $e);
            self::writeFrames($s, $flags, $frames, $enclosing, $indent + 1);

            foreach (self::getSuppressedExceptions($e) as $suppressedException) {
                self::write($s, $flags, $suppressedException, $frames, $seen, $indent + 1);
            }

            $enclosing = $frames;
            $kind = 'Caused by';
        } while ($e = $e->getPrevious());
    }

    /**
     * @param Frames $frames
     * @param Frames|null $enclosing
     */
    private static function writeFrames(string &$s, int $flags, array $frames, ?array $enclosing, int $indent): void {
        $n = count($frames);
        if ($enclosing) {
            for ($m = count($enclosing);
                 $n && $m && $frames[$n - 1] === $enclosing[$m - 1];
                 $n--, $m--) {}
        }
        for ($i = 0; $i < $n; $i++) {
            $frame = $frames[$i];
            self::writeNewline($s, $indent);
            $s .= 'at ';
            if ($frame['class'] !== null && $frame['type'] !== null) {
                $s .= self::formatName($frame['class'], $flags);
                $s .= self::formatType($frame['type'], $flags);
            }
            $s .= self::formatName($frame['function'], $flags);
            $s .= '(';
            if ($frame['file'] !== null) {
                $s .= basename($frame['file']);
                if ($frame['line']) {
                    $s .= ':';
                    $s .= $frame['line'];
                }
            } else {
                $s .= 'Unknown Source';
            }
            $s .= ')';
        }
        if ($n !== count($frames)) {
            self::writeNewline($s, $indent);
            $s .= sprintf('... %d more', count($frames) - $n);
        }
    }

    private static function writeInlineHeader(string &$s, int $flags, Throwable $e): void {
        $s .= self::formatName($e::class, $flags);
        if ($e->getMessage() !== '') {
            $s .= ': ';
            $s .= $e->getMessage();
        }
    }

    private static function writeNewline(string &$s, int $indent): void {
        $s .= "\n";
        $s .= str_repeat("\t", $indent);
    }

    /**
     * @return iterable<int, Throwable>
     */
    private static function getSuppressedExceptions(Throwable $e): iterable {
        if (!method_exists($e, 'getSuppressed')) {
            return;
        }
        try {
            $suppressed = $e->getSuppressed();
            if (is_iterable($suppressed)) {
                foreach ($suppressed as $value) {
                    if ($value instanceof Throwable) {
                        yield $value;
                    }
                }
            }
        } catch (Throwable) {}
    }

    /**
     * @return Frames
     */
    private static function frames(Throwable $e): array {
        $frames = [];
        $trace = $e->getTrace();
        for ($i = 0; $i < count($trace) + 1; $i++) {
            /** @psalm-suppress InvalidArrayOffset */
            $frames[] = [
                'function' => $trace[$i]['function'] ?? '{main}',
                'class' => $trace[$i]['class'] ?? null,
                'type' => $trace[$i]['type'] ?? null,
                'file' => $trace[$i - 1]['file'] ?? null,
                'line' => $trace[$i - 1]['line'] ?? null,
            ];
        }
        $frames[0]['file'] = $e->getFile();
        $frames[0]['line'] = $e->getLine();

        return $frames;
    }

    private static function formatType(string $type, int $flags): string {
        if ($flags & self::DOT_SEPARATOR) {
            return '.';
        }

        return $type;
    }

    private static function formatName(string $name, int $flags): string {
        if ($flags & self::DOT_SEPARATOR) {
            return strtr($name, ['\\' => '.']);
        }

        return $name;
    }
}
