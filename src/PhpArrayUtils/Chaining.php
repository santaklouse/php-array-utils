<?php

namespace PhpArrayUtils;

use BadMethodCallException;

class Chaining extends Utils
{
    /**
     * Executes a standalone operation.
     *
     * @param string $method Method name
     * @param array $args Method arguments
     * @return mixed Return value of the method
     * @throws BadMethodCallException if an invalid operation is called
     */
    public static function __callStatic($method, $args)
    {
        $callable = self::toCallable($method);
        return call_user_func_array($callable, $args);
    }

    /**
     * Sets the input value of a chain.
     *
     * Can be called multiple times on the same chain.
     *
     * @param mixed $input
     * @return \PhpArrayUtils\Chaining
     *
     * @example With an array
     * Arr::chain()->with([1, 2, 3])->value();
     * // === [1, 2, 3]
     *
     * @example With an stdClass
     * Arr::chain()->with((object) ['a' => 1, 'b' => 2, 'c' => 3])->value();
     * // === (object) ['a' => 1, 'b' => 2, 'c' => 3]
     *
     * @example With a number
     * Arr::chain()->with(3.14)->value();
     * // === 3.14
     */
    public function with($input = null)
    {
        $this->input = $input;
        $this->output = NULL;
        return $this;
    }

    /**
     * Executes all chained operations with the latest input.
     *
     * The result is cached, and multiple calls to value() will returned the cached value
     * until the input value is changed or more operations are added to the chain.
     *
     * @return mixed The result of all chained operations on the input
     *
     * @example
    $chain = Arr::chain([1, 2, 3])->map(function ($n) { return $n * 2; });
    // map() is not called yet
    $chain->value();
    // Only now is map() called
    // === [2, 4, 6]
     */
    public function value()
    {
        if (!isset($this->output)) {
            $this->output = $this->getOutput($this->input);
        }

        return $this->output;
    }

    /**
     * Calls value() and returns the result as an array using toArray().
     *
     * @see toArray()
     *
     * @return array
     */
    public function arrayValue()
    {
        return Utils::toArray($this->value());
    }

    /**
     * Calls value() and returns the result as an object using toObject().
     *
     * @see toObject()
     *
     * @return object
     */
    public function objectValue()
    {
        return Utils::toObject($this->value());
    }

    /**
     * Alias for value(). Useful for chains whose output is not needed.
     *
     * @see value()
     * @return mixed
     *
     * @example
    Arr::chain([1, 2, 3])
    ->each(function ($n) { echo $n; })
    ->run();
    // Prints: 123
     */
    public function run()
    {
        return $this->value();
    }

    /**
     * Returns a new, independent copy of this chain.
     *
     * Future changes to this copy will not affect this original chain.
     * This method is the same as cloning the chain.
     *
     * @return Chaining
     *
     * @example
    $original = Arr::chain()->map(function ($n) { return $n * 2; });
    $original->with([1, 2, 3]);
    $original->value();
    // === [2, 4, 6]
    $copy = $original->copy();
    $copy->map(function ($n) { return $n + 1; });
    $copy->with([4, 5, 6]);
    $copy->value();
    // === [9, 11, 13]
    $original->with([1, 2, 3]);
    $original->value();
    // === [2, 4, 6]
     */
    public function copy()
    {
        return clone $this;
    }

    /**
     * Executes a chained operation on this chain.
     *
     * @param string $method Method name
     * @param array $args Method args
     * @return $this The chain
     *
     * @example
    Arr::chain([1, 2, 3])
    ->filter(function($n) { return $n < 3; })
    ->map(function($n) { return $n * 2; })
    ->value();
    // === [2, 4]
     */
    public function __call($method, $args)
    {
        $this->operations[] = $this->toOperation($method, $args);
        $this->output = null;
        return $this;
    }

    /**
     * Custom operation functions.
     *
     * @var array of operation name => callable
     */
    protected static $customFunctions = [];

    /**
     * The function operations to execute on this chain.
     *
     * @var array of callable
     */
    protected $operations = [];

    /**
     * The current input value of this chain.
     *
     * @var mixed
     */
    protected $input = null;

    /**
     * The cached output value (if any) of this chain.
     *
     * @var mixed
     */
    protected $output = null;

    /**
     * Returns a callable function for the specified operation.
     *
     * @param string $method Operation name (built-in or custom)
     * @return callable Callable function for `$method`
     * @throws BadMethodCallException if `$method` is not callable
     */
    protected static function toCallable($method)
    {
        if (is_callable([Arr::class, $method])) {
            return [Arr::class, $method];
        }
        elseif (isset(self::$customFunctions[$method])) {
            return self::$customFunctions[$method];
        }
        else {
            throw new BadMethodCallException("No operation named '$method' found");
        }
    }

    /**
     * Constructs a new chain.
     *
     * @param mixed $input (optional) Input value of the chain
     * @return void
     */
    protected function __construct($input = null)
    {
        $this->with($input);
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

        $operation = function ($input) use ($callable, $args) {
            array_unshift($args, $input);
            return call_user_func_array($callable, $args);
        };

        return $operation;
    }

    /**
     * Gets the result of all chained operations using `$input` as the initial input value.
     *
     * @param mixed $input
     * @return array|mixed Result of all operations on `$input`
     */
    protected function getOutput($input)
    {
        $output = $input;

        foreach ($this->operations as $operation) {
            $output = call_user_func_array($operation, [&$output]);
        }

        return $output;
    }
}
