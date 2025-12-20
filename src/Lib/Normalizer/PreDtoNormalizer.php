<?php

namespace LiquidRazor\DtoApiBundle\Lib\Normalizer;

use BackedEnum;
use DateTimeInterface;
use ReflectionObject;
use ReflectionProperty;
use SplObjectStorage;
use stdClass;
use Throwable;
use Traversable;
use UnitEnum;

/**
 * Framework-agnostic pre-DTO normalizer.
 * No vendor checks. Pure PHP. Optional pluggable handlers.
 */
final class PreDtoNormalizer
{
    /** @var array<class-string, ReflectionProperty[]> */
    private static array $refCache = [];

    /** @var callable[] list of handlers: fn(mixed $v, callable $recurse): mixed|PreDtoNoMatch */
    private array $handlers = [];

    public function __construct(
        private readonly array $opts = [
            'deep' => true,
            'maxDepth' => 8,
            'includeNull' => true,
            'asStdClass' => true,
            'dateFormat' => DATE_ATOM,
            'maxTraverse' => 10_000, // safety valve
            'propertyFilter' => null, // fn(string $name, mixed $value, object $obj): bool
            'nameTransform' => null,  // fn(string $name): string
        ]
    )
    {
        // built-ins first, very inexpensive checks
        $this->registerHandler(function ($v, $recurse) {
            // Scalars/null
            if ($v === null || is_scalar($v)) return $v;

            // Date/Time
            if ($v instanceof DateTimeInterface) {
                return $v->format($this->opts['dateFormat']);
            }

            // Enums
            if ($v instanceof UnitEnum) {
                return $v instanceof BackedEnum ? $v->value : $v->name;
            }

            // Stringable
            if (is_object($v) && method_exists($v, '__toString')) {
                try {
                    return (string)$v;
                } catch (Throwable) {
                }
            }

            return PreDtoNoMatch::instance();
        });

        // Arrays
        $this->registerHandler(function ($v, $recurse) {
            if (!is_array($v)) return PreDtoNoMatch::instance();

            $out = [];
            $i = 0;
            foreach ($v as $k => $item) {
                if (++$i > $this->opts['maxTraverse']) break;
                $nv = $recurse($item);
                if ($nv !== null || $this->opts['includeNull']) {
                    $out[$k] = $nv;
                }
            }
            return $out;
        });

        // Traversable (no vendor checks, just the interface)
        $this->registerHandler(function ($v, $recurse) {
            if (!$v instanceof Traversable) return PreDtoNoMatch::instance();

            $out = [];
            $i = 0;
            foreach ($v as $item) {
                if (++$i > $this->opts['maxTraverse']) break;
                $out[] = $recurse($item);
            }
            return $out;
        });

        // Objects via reflection (private/protected)
        $this->registerHandler(function ($v, $recurse) {
            if (!is_object($v)) return PreDtoNoMatch::instance();

            // depth/cycle guard handled in main normalize()
            $class = get_class($v);
            $props = self::$refCache[$class] ??= (function () use ($v) {
                $ro = new ReflectionObject($v);
                $all = [];
                foreach ($ro->getProperties() as $p) {
                    $all[] = $p;
                }
                return $all;
            })();

            $out = [];
            foreach ($props as $rp) {
                $name = $rp->getName();
                try {
                    $value = $rp->getValue($v);
                } catch (Throwable) {
                    continue; // unreadable
                }

                if (is_callable($this->opts['propertyFilter'])) {
                    if (!($this->opts['propertyFilter'])($name, $value, $v)) {
                        continue;
                    }
                }

                $k = is_callable($this->opts['nameTransform'])
                    ? ($this->opts['nameTransform'])($name)
                    : $name;

                $nv = $recurse($value);
                if ($nv !== null || $this->opts['includeNull']) {
                    $out[$k] = $nv;
                }
            }
            return $out;
        });
    }

    /**
     * Register a handler: fn(mixed $value, callable $recurse): mixed|PreDtoNoMatch
     * Order matters; first match wins.
     */
    public function registerHandler(callable $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Normalize any value to array/stdClass/scalar.
     */
    public function normalize(mixed $value): mixed
    {
        $visited = new SplObjectStorage();

        $recurse = function (mixed $v, int $depth = 0) use (&$recurse, $visited): mixed {
            if ($v === null || is_scalar($v)) return $v;

            if (!$this->opts['deep'] || $depth >= $this->opts['maxDepth']) {
                // graceful edge: try stringable, else minimal descriptor
                if (is_object($v) && method_exists($v, '__toString')) {
                    try {
                        return (string)$v;
                    } catch (Throwable) {
                    }
                }
                return is_object($v) ? ['__object' => get_debug_type($v)] : $v;
            }

            if (is_object($v)) {
                if ($visited->contains($v)) {
                    return ['__circular_ref' => spl_object_id($v)];
                }
                $visited->attach($v);
            }

            foreach ($this->handlers as $h) {
                $res = $h($v, function ($x) use (&$recurse, $depth) {
                    return $recurse($x, $depth + 1);
                });
                if (!$res instanceof PreDtoNoMatch) {
                    return $res;
                }
            }

            // Fallback: leave as-is (rare)
            return $v;
        };

        $data = $recurse($value, 0);

        if ($this->opts['asStdClass']) {
            $toStd = function ($x) use (&$toStd) {
                if (is_array($x)) {
                    $keys = array_keys($x);
                    $isSeq = $keys === range(0, count($x) - 1);
                    if ($isSeq) {
                        return array_map($toStd, $x);
                    }
                    $o = new stdClass();
                    foreach ($x as $k => $v) {
                        $o->{$k} = $toStd($v);
                    }
                    return $o;
                }
                return $x;
            };
            $data = $toStd($data);
        }

        return $data;
    }
}