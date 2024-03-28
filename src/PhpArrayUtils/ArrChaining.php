<?php

namespace PhpArrayUtils;

use BadMethodCallException;
use ReflectionParameter;

class ArrChains extends Chaining
{
    /**
     * Creates a new chain.
     *
     * @param mixed|null $input (optional) Initial input value of the chain
     * @return \PhpArrayUtils\ArrChains A new chain
     *
     * @example
     * Arr::chain([1, 2, 3])
     * ->filter(function ($n) { return $n < 3; })
     * ->map(function ($n) { return $n * 2; })
     * ->value();
     * // === [2, 4]
     */
    public static function chain(mixed $input = null)
    {
        return new self($input);
    }

    /**
     * Returns a callable function for the specified operation.
     *
     * @param string $method Operation name (built-in or custom)
     * @return array|callable Callable function for `$method`
     * @throws BadMethodCallException if `$method` is not callable
     */
    protected static function toCallable($method): array|callable
    {
        $callable = [Arr::class, $method];
        if (is_callable($callable)) {
            return $callable;
        }
        throw new BadMethodCallException("No method Arr::'$method' found");
    }

    /**
     * Executes a standalone operation.
     *
     * @param string $method Method name
     * @param array $args Method arguments
     * @return mixed Return value of the method
     * @throws BadMethodCallException if an invalid operation is called
     *
     */
    public static function __callStatic($method, $args)
    {
        $callable = ArrChains::toCallable($method);
        return call_user_func_array($callable, $args);
    }

    /**
     * Creates a new callable function that will invoke `$method` with `$args`.
     *
     * @param string $method Method name
     * @param array $args Method arguments
     * @return callable Callable function that will invoke `$method` with `$args` when called
     */
    protected function toOperation($method, $args)
    {
        $callable = self::toCallable($method);

        return function (&$input) use ($callable, $args, $method) {
            if ((new ReflectionParameter([Arr::class, $method], 0))->isPassedByReference()) {
                $a = [&$input];
                $args = array_push($a, ...$args);
            } else {
                array_unshift($args, $input);
            }
            return call_user_func_array($callable, $args);
        };
    }
}
