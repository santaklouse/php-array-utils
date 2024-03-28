<?php

namespace PhpArrayUtils;

use BadMethodCallException;
use Closure;
use Countable;
use InvalidArgumentException;
use ReflectionParameter;
use Traversable;
use function array_fill_keys;
use function array_map;
use function array_values;
use function array_walk;
use function is_array;
use function is_null;
use function is_string;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use function strpos;

/**
 * Mix of illuminate/Support/Arr and Kohana's Arr helper
 *
 * @class Arr
 */
class Arr
{

    private static string $delimiter = '.';

    /**
     * Creates a new chain.
     *
     * @param mixed $input (optional) Initial input value of the chain
     * @return ArrChains A new chain
     *
     * @example
    Arr::chain([1, 2, 3])
    ->filter(function ($n) { return $n < 3; })
    ->map(function ($n) { return $n * 2; })
    ->value();
    // === [2, 4]
     */
    public static function chain($input = null)
    {
        return ArrChains::chain($input);
    }

    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName()
    {
        return 'arr';
    }

    /**
     * Return the default value of the given value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected static function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }

    /**
     * Determine whether the given value is array accessible.
     *
     * @param  mixed $value
     * @return bool
     */
    public static function accessible($value)
    {
        return self::isArray($value);
    }

    /**
     * Add an element to an array using "dot" notation if it doesn't exist.
     *
     * @param  array $array
     * @param  string|array $key
     * @param  mixed $value
     * @return array
     */
    public static function add(&$array, $key, $value = NULL)
    {
        if (self::isArray($array) && is_null($value)) {
            if (self::isArray($key)) {
                array_walk($key, function($value) use (&$array){
                    array_push($array, $value);
                });
            }
            elseif(is_string($key)) {
                array_push($array, $key);
            }
        }
        elseif (is_null(self::get($array, $key))) {
            self::set($array, $key, $value);
        }

        return $array;
    }

    /**
     * Add an element to an array using "dot" notation if it doesn't exist.
     *
     * @param  array $array
     * @param  string|array $key
     * @param  mixed $value
     * @return array
     */
    public static function push(&$array, $key, $value = NULL)
    {
        if (!is_string($key))
        {
            if (is_null($value))
            {
                array_push($array, $key);
            }
        }

        if (self::hasDelimiter($key))
        {
            $arrayPointer = &$array;
            foreach (explode('.', $key) as $segment)
            {
                if (!self::accessible($arrayPointer) || !self::exists($arrayPointer, $segment))
                    $arrayPointer[$segment] = [];

                $arrayPointer = &$arrayPointer[$segment];
            }
            array_push($arrayPointer, $value);
        }
        else
        {
            if (!self::has($array, $key))
            {
                $array[$key] = [];
            }
            array_push($array[$key], $value);
        }
        return $array;
    }

    /**
     * Collapse an array of arrays into a single array.
     *
     * @param  iterable $array
     * @return array
     */
    public static function collapse($array)
    {
        $results = [];

        foreach ($array as $values) {
            if (!is_array($values)) {
                continue;
            }

            $results[] = $values;
        }

        return array_merge([], ...$results);
    }

    /**
     * Cross join the given arrays, returning all possible permutations.
     *
     * @param  iterable ...$arrays
     * @return array
     */
    public static function crossJoin(...$arrays)
    {
        $results = [[]];

        foreach ($arrays as $index => $array) {
            $append = [];

            foreach ($results as $product) {
                foreach ($array as $item) {
                    $product[$index] = $item;

                    $append[] = $product;
                }
            }

            $results = $append;
        }

        return $results;
    }

    /**
     * Divide an array into two arrays. One with keys and the other with values.
     *
     * @param  array $array
     * @return array
     */
    public static function divide($array)
    {
        return [array_keys($array), array_values($array)];
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param  iterable $array
     * @param  string $prepend
     * @return array
     */
    public static function dot($array, $prepend = '')
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $results = array_merge($results, self::dot($value, $prepend . $key . self::$delimiter));
            } else {
                $results[$prepend . $key] = $value;
            }
        }

        return $results;
    }

    /**
     * Get all of the given array except for a specified array of keys.
     *
     * @param  array $array
     * @param  array|string $keys
     * @param bool $byValue - use keys as array of values that should be missed in result array
     * @return array
     */
    public static function except($array, $keys, $byValue = FALSE)
    {
        if ($byValue)
            return array_diff($array, $keys);

        self::forget($array, $keys);

        return $array;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param  array $array
     * @param  string|int $key
     * @return bool
     */
    public static function exists($array, $key): bool
    {
        return array_key_exists($key, self::wrap($array));
    }

    /**
     * @param mixed $array
     * @param string|int|null $key
     * @param string|int|null $compareTo
     * @return bool
     */
    public static function existsAndEqual($array, $key, $compareTo): bool
    {
        if (!self::isArray($array)) {
            return FALSE;
        }
        return self::get($array, $key) == $compareTo;
    }

    /**
     * Return the first element in an array passing a given truth test.
     *
     * @param  iterable $array
     * @param  callable|null $callback
     * @param  mixed $default
     * @return mixed
     */
    public static function first($array, callable $callback = null, $default = null)
    {
        if (is_null($callback)) {
            if (empty($array)) {
                return self::value($default);
            }

            foreach ($array as $item) {
                return $item;
            }
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return self::value($default);
    }

    /**
     * Analog of array_map but with key of element passed as second parameter
     *
     * @param array $array
     * @param string|callable $callback
     * @param bool $removeEmpty
     * @return mixed
     */
    public static function map(array $array, $callback, $removeEmpty = FALSE): array
    {
        if (empty($array))
            return [];

        if (is_null($callback))
            return $array;

        $result = array_map($callback, $array, array_keys($array));
        if (!$removeEmpty)
            return $result;

        return Arr::filterEmpty($result);
    }

    /**
     * Return the last element in an array passing a given truth test.
     *
     * @param  array $array
     * @param  callable|null $callback
     * @param  mixed $default
     * @return mixed
     */
    public static function last($array, callable $callback = null, $default = null)
    {
        if (is_null($callback))
            return empty($array)
                ? self::value($default)
                : end($array);

        return self::first(array_reverse($array, true), $callback, $default);
    }



    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param iterable $array
     * @param int $depth = INF
     * @return array
     */
    public static function flatten(iterable $array, int $depth = INF): array
    {
        function obj2Arr($item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            return $item;
        }

        $result = [];

        foreach ($array as $item) {
            $item = obj2Arr($item);

            if (!is_array($item)) {
                $result[] = $item;
                continue;
            }
            $values = $depth === 1
                ? array_values($item)
                : self::flatten($item, $depth - 1);

            foreach ($values as $value) {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * just php array_unique wrapper
     *
     * @param array $array
     * @param int $flags
     * @return mixed
     */
    public static function uniq(array $array, int $flags = SORT_STRING):array
    {
        if (empty($array))
            return [];

        return array_unique($array, $flags);
    }

    /**
     * Gets the number of items in `$value`.
     *
     * For iterables, this is the number of elements.
     * For strings, this is number of characters.
     *
     * @param iterable|string $value
     * @param string $encoding (optional) The character encoding of `$value` if it is a string;
     *                         see `mb_list_encodings()` for the list of supported encodings
     * @return integer Size of `$value` or zero if `$value` is neither iterable nor a string
     *
     * @alias Arr::count
     *
     * @example
    * Arr::size([1, 2, 3]);
    * // === 3
 *
* Arr::size('Beyoncé');
    * // === 7
     */
    public static function size(mixed $value, string $encoding = 'UTF-8'): int
    {
        if (is_array($value) || $value instanceof Countable)
            return count($value);

        if (Utils::isType($value, ['iterable', 'stdClass']))
        {
            $size = 0;
            foreach ($value as &$value) {
                $size++;
            }
            return $size;
        }

        if (is_string($value))
            return function_exists('mb_strlen')
                ? mb_strlen($value, $encoding)
                : strlen($value);

        return 0;
    }

    /**
     * @alias Arr::size
     * @return mixed
     */
    public static function count(): mixed
    {
        return call_user_func_array([Arr::class, 'size'], func_get_args());
    }

    /**
     * Remove one or many array items from a given array using "dot" notation.
     *
     * @param  array &$array
     * @param  array|string $keys
     * @return array
     */
    public static function forget(&$array, $keys)
    {
        $original = &$array;

        $keys = self::wrap($keys);

        if (!count($keys)) {
            return $array;
        }

        foreach ($keys as $key) {
            // if the exact key exists in the top-level, remove it
            if (self::exists($array, $key)) {
                unset($array[$key]);

                continue;
            }

            $parts = explode(self::$delimiter, $key);

            // clean up before each pass
            $array = &$original;

            while (count($parts) > 1) {
                $part = array_shift($parts);

                if (isset($array[$part]) && is_array($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }

            unset($array[array_shift($parts)]);
        }
        return $array;
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param  array $array
     * @param  string|int|null $key
     * @param  mixed $default
     * @return mixed
     */
    public static function get($array, $key, $default = null)
    {
        if (!self::accessible($array)) {
            return self::value($default);
        }

        if (is_null($key)) {
            return $array;
        }

        if (self::exists($array, $key)) {
            return $array[$key];
        }

        if (!self::hasDelimiter($key)) {
            return $array[$key] ?? self::value($default);
        }

        foreach (explode(self::$delimiter, $key) as $segment) {
            if (self::accessible($array) && self::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return self::value($default);
            }
        }

        return $array;
    }

    /**
     * Check if an item or items exist in an array using "dot" notation.
     *
     * @param  array $array
     * @param  string|array $keys
     * @return bool
     */
    public static function has($array, $keys)
    {
        $keys = self::wrap($keys);

        if (!$array || empty($keys)) {
            return FALSE;
        }

        foreach ($keys as $key) {
            $subKeyArray = $array;

            if (self::exists($array, $key)) {
                continue;
            }
            foreach (explode(self::$delimiter, $key) as $segment) {
                if (self::accessible($subKeyArray) && self::exists($subKeyArray, $segment)) {
                    $subKeyArray = $subKeyArray[$segment];
                } else {
                    return FALSE;
                }
            }
        }

        return TRUE;
    }

    /**
     * Determines if an array is associative.
     *
     * An array is "associative" if it doesn't have sequential numerical keys beginning with zero.
     *
     * @param  array $array
     * @return bool
     */
    public static function isAssoc(array $array)
    {
        $keys = array_keys($array);

        return array_keys($keys) !== $keys;
    }

    /**
     * Get a subset of the items from the given array.
     *
     * @param  array $array
     * @param  array|string $keys
     * @return array
     */
    public static function only(array $array, $keys)
    {
        return array_intersect_key($array, array_flip(self::wrap($keys)));
    }

    /**
     * Get a subset of the items from the given array.
     *
     * @param array $array
     * @param callable $callback
     * @return array
     */
    public static function filter($array, callable $callback)
    {
        return array_filter($array, $callback);
    }

    /**
     * Get a subset of the items from the given array.
     *
     * @param  array $array
     * @return array
     */
    public static function filterEmpty($array)
    {
        return array_filter($array, function($value) {
            return !empty($value);
        });
    }

    /**
     * @param  array $array
     * @see Arr::filterEmpty
     * @alias Arr::filterEmpty
     * @return array
     */
    public static function clear($array)
    {
        return self::filterEmpty($array);
    }

    /**
     * Get a subset of the items from the given array.
     *
     * @param  array $array
     * @param $key
     * @param $val
     * @param bool $contrariwise
     * @return array
     */
    public static function filterBy($array, $key, $val, $contrariwise = FALSE)
    {
        if (!self::isArray($array))
            return [];
        return array_filter($array, function($data) use ($key, $val, $contrariwise) {
            $result = !!Arr::existsAndEqual($data, $key, $val);
            return $contrariwise ? !$result : $result;
        });
    }

    /**
     * Pluck an array of values from an array.
     *
     * @param  iterable $array
     * @param  string|array $value
     * @param  string|array|null $key
     * @return array
     */
    public static function pluck($array, $value, $key = null)
    {
        if (empty($array) || !Arr::isArray($array))
            return [];

        $array = self::wrap($array);

        [$value, $key] = self::explodePluckParameters($value, $key);

        $results = [];
        foreach ($array as $item)
        {
            $itemValue = self::path($item, $value);

            // If the key is "null", we will just append the value to the array and keep
            // looping. Otherwise we will key the array using the value of the key we
            // received from the developer. Then we'll return the final array form.
            if (is_null($key)) {
                $results[] = $itemValue;
                continue;
            }
            $itemKey = self::path($item, $key);

            if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                $itemKey = (string)$itemKey;
            }

            $results[$itemKey] = $itemValue;
        }

        return $results;
    }

    /**
     * Explode the "value" and "key" arguments passed to "pluck".
     *
     * @param  string|array $value
     * @param  string|array|null $key
     * @return array
     */
    protected static function explodePluckParameters($value, $key)
    {
        $value = is_string($value) ? explode(self::$delimiter, $value) : $value;

        $key = is_null($key) || is_array($key) ? $key : explode(self::$delimiter, $key);

        return [$value, $key];
    }

    /**
     * Push an item onto the beginning of an array.
     *
     * @param  array $array
     * @param  mixed $value
     * @param  mixed $key
     * @return array
     */
    public static function prepend($array, $value, $key = null)
    {
        if (is_null($key)) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }

        return $array;
    }

    /**
     * Get a value from the array, and remove it.
     *
     * @param  array $array
     * @param  string $key
     * @param  mixed $default
     * @return mixed
     */
    public static function pull(&$array, $key, $default = null)
    {
        $value = self::get($array, $key, $default);

        self::forget($array, $key);

        return $value;
    }

    /**
     * Get one or a specified number of random values from an array.
     *
     * @param  array $array
     * @param  int|null $number
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public static function random($array, $number = null)
    {
        $requested = is_null($number) ? 1 : $number;

        $count = count($array);

        if ($requested > $count) {
            throw new InvalidArgumentException(
                "You requested {$requested} items, but there are only {$count} items available."
            );
        }

        if (is_null($number)) {
            return $array[array_rand($array)];
        }

        if ((int)$number === 0) {
            return [];
        }

        $keys = array_rand($array, $number);

        $results = [];

        foreach ((array)$keys as $key) {
            $results[] = $array[$key];
        }

        return $results;
    }

    protected static function hasDelimiter($str)
    {
        return strpos($str, self::$delimiter) > -1;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * If no key is given to the method, the entire array will be replaced.
     *
     * @param  array $array
     * @param  string|array $key
     * @param  mixed $value
     * @return array
     */
    public static function set(&$array, $key, $value = null)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        //batch mode
        if (self::isArray($key) && is_null($value) && self::isArray($array)) {
            $batch = $key;
            foreach ($batch as $i => $value)
                self::set($array, $i, $value);

            return $array;
        }

        if (!self::hasDelimiter($key)) {
            $array[$key] = $value;
            return $array;
        }

        $keys = explode(self::$delimiter, $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * Shuffle the given array and return the result.
     *
     * @param  array $array
     * @param  int|null $seed
     * @return array
     */
    public static function shuffle($array, $seed = null)
    {
        if (is_null($seed)) {
            shuffle($array);
        } else {
            mt_srand($seed);
            shuffle($array);
            mt_srand();
        }

        return $array;
    }

    /**
     * Recursively sort an array by keys and values.
     *
     * @param  array $array
     * @return array
     */
    public static function sortRecursive($array)
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::sortRecursive($value);
            }
        }

        if (self::isAssoc($array)) {
            ksort($array);
        } else {
            sort($array);
        }

        return $array;
    }

    /**
     * Convert the array into a query string.
     *
     * @param  array $array
     * @return string
     */
    public static function query($array)
    {
        return http_build_query($array, null, '&', PHP_QUERY_RFC3986);
    }

    /**
     * Filter the array using the given criteria array
     *
     * //TODO: function requires refactoring
     *
     * @param  array $array
     * @param array $filterItems
     * @param bool $contrariwise
     * @return array
     */
    public static function where($array, array $filterItems, bool $contrariwise = FALSE)
    {
        $result = [];
        if (empty($filterItems)) {
            return $array;
        }
        foreach($array as $_key => $element)
            foreach ($filterItems as $key => $value)
            {
                $tmpVal = self::get($element, $key);

                if ($contrariwise) {
                    if (self::isArray($value) && (is_null($tmpVal) || !in_array($tmpVal, $value))) {
                        $result[$_key] = $element;
                    } elseif ($tmpVal !== $value) {
                        $result[$_key] = $element;
                    }
                    continue;
                }
                if ($tmpVal === $value || (!is_null($tmpVal) && self::isArray($value) && in_array($tmpVal, $value))) {
                    $result[$_key] = $element;
                }
            }
        return $result;
    }

    /**
     * Check
     *
     * @param  array $array
     * @param $path
     * @param $value
     * @return bool
     */
    public static function inArray(array $array, $path, $value): bool
    {
        if (self::isArray($value)) {
            $array = self::get($array, $path, []);
            foreach($array as $item)
            {
                if (Arr::isEqual($item, $value))
                {
                    return TRUE;
                }
            }
            return FALSE;
        }
        if (!self::hasDelimiter($path)) {
            return in_array($value, $array[$path] ?? []);
        }
        return in_array($value, self::get($array, $path, []));
    }

    /**
     * Find first element of collection where field $fieldName value equals $value
     *
     * @param  array $array
     * @param $fieldName
     * @param $value
     * @return array
     */
    public static function findBy(array $array, $fieldName, $value)
    {
        return self::first(self::where($array, [
            $fieldName => $value
        ]));
    }

    /**
     * Filter the array using the given criteria array
     *
     * @param  array $array
     * @param $fieldName
     * @param $value
     * @return string|integer
     */
    public static function findKeyBy($array, $fieldName, $value)
    {
        return self::first(array_keys(self::where($array, [
            $fieldName => $value
        ])));
    }

    /**
     * If the given value is not an array and not null, wrap it in one.
     *
     * @param  mixed $value
     * @return array
     */
    public static function wrap($value): array
    {
        if (is_null($value)) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Determine whether the given value is array
     *
     * @param mixed $value
     * @return bool
     */
    public static function isArray($value)
    {
        if (is_array($value)) {
            // Definitely an array
            return TRUE;
        } else {
            // Possibly a Traversable object, functionally the same as an array
            return (is_object($value) AND $value instanceof Traversable);
        }
    }

    /**
     * Determine whether the given value is associative array
     *
     * @param array $array
     * @return bool
     */
    public static function is_assoc(array $array)
    {
        // Keys of the array
        $keys = array_keys($array);

        // If the array keys of the keys match the keys, then the array must
        // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
        return array_keys($keys) !== $keys;
    }

    /**
     * Gets a value from an array using a dot separated path.
     *
     * @param array $array required - Array to search
     * @param mixed $path required - Key path string (delimiter separated) or array of keys
     * @param mixed $default = NULL - Default value if the path is not set
     * @param string $delimiter = NULL - Key path delimiter
     * @return array|null
     */
    public static function path($array, $path, $default = NULL, $delimiter = NULL)
    {
        if (!self::isArray($array)) {
            // This is not an array!
            return $default;
        }

        if (is_array($path)) {
            // The path has already been separated into keys
            $keys = $path;
        } else {
            if (array_key_exists($path, $array)) {
                // No need to do extra processing
                return $array[$path];
            }

            if ($delimiter === NULL) {
                // Use the default delimiter
                $delimiter = self::$delimiter;
            }

            // Remove starting delimiters and spaces
            $path = ltrim($path, "{$delimiter} ");

            // Remove ending delimiters, spaces, and wildcards
            $path = rtrim($path, "{$delimiter} *");

            // Split the keys by delimiter
            $keys = explode($delimiter, $path);
        }

        do {
            $key = array_shift($keys);

            if (ctype_digit($key)) {
                // Make the key an integer
                $key = (int)$key;
            }

            if (isset($array[$key])) {
                if (!$keys) {
                    // Found the path requested
                    return $array[$key];
                }
                if (!self::isArray($array[$key])) {
                    // Unable to dig deeper
                    break;
                }
                // Dig down into the next part of the path
                $array = $array[$key];
            } elseif ($key === '*') {
                // Handle wildcards

                $values = array();
                foreach ($array as $arr) {
                    if ($value = self::path($arr, implode(self::$delimiter, $keys))) {
                        $values[] = $value;
                    }
                }

                if (!$values) {
                    // Unable to dig deeper
                    break;
                }

                // Found the values requested
                return $values;
            } else {
                // Unable to dig deeper
                break;
            }
        } while ($keys);

        // Unable to find the value requested
        return $default;
    }

    /**
     * Safe wrapper of array_values
     *
     * @param  mixed $array
     * @return array
     */
    public static function values($array): array
    {
        if (empty($array)) {
            return [];
        }

        if (!Arr::isArray($array)) {
            $array = Utils::toArray($array);
        }

        return  array_values(Arr::wrap($array));
    }

    /**
     *
     *
     * @param $array
     * @param array $paths
     * @param null $default
     * @param null $delimiter
     * @return array|null
     */
    public static function pick($array, $paths = [], $default = NULL, $delimiter = NULL)
    {
        if (!self::isArray($array)) {
            // This is not an array!
            return $default;
        }
        if (is_string($paths)) {
            return Arr::pluck($array, $paths);
        }
        if (empty($delimiter)) {
            // Use the default delimiter
            $delimiter = self::$delimiter;
        }

        $array = array_values($array);
        $result = [];
        foreach($array as $key => $item) {
            $result[$key] = [];
            foreach($paths as $path)
                $result[$key][$path] = self::path($item, $path, $default, $delimiter);
        }

        return $result;
    }

    /**
     * @param array $array
     * @param array $keys
     * @return array
     */
    public static function getPart(array $array, $keys = []): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::get($array, $key);
        }
        return $result;
    }

    /**
     * @param array $collection
     * @param array $keys
     * @return array
     */
    public static function getParts(array $collection, array $keys): array
    {
        if (self::isAssoc($keys))
            return $collection;

        foreach ($collection as &$item)
            if (is_array($item))
                $item = self::getPart($item, $keys);

        return $collection;
    }

    /**
     * Function that groups an array of associative arrays by some key.
     *
     * @param {String} $key Property to sort by.
     * @param {Array} $data Array that stores multiple associative arrays.
     * @return array
     */
    public static function groupBy($data, $key) {
        $result = [];

        // todo: add "." (dot) path key format like in self::path
        foreach($data as $index => $val) {
            if(!self::has($val, $key)) {
                if (is_string($index)) {
                    $result[''][$index] = $val;
                } else {
                    $result[''][] = $val;
                }
                continue;
            }
            $result[$val[$key]][] = $val;
        }
        return $result;
    }

    public static function diffRecursive($firstArray, $secondArray, $reverseKey = false)
    {
        $oldKey = 'old';
        $newKey = 'new';
        if ($reverseKey) {
            $oldKey = 'new';
            $newKey = 'old';
        }
        $difference = [];
        foreach ($firstArray as $firstKey => $firstValue) {
            $secondArrayFirstKeyVal = self::get($secondArray, $firstKey);
            if (self::isArray($firstValue)) {
                if (!key_exists($firstKey, $secondArray) || !self::isArray($secondArrayFirstKeyVal)) {
                    self::set($difference, "$oldKey.$firstKey", $firstValue);
                    self::set($difference, "$newKey.$firstKey", '');
                } else {
                    $newDiff = self::diffRecursive($firstValue, self::get($secondArray, $firstKey), $reverseKey);
                    if (!empty($newDiff)) {
                        self::set($difference, "$oldKey.$firstKey", self::get($newDiff, $oldKey));
                        self::set($difference, "$newKey.$firstKey", self::get($newDiff, $newKey));
                    }
                }
            } else if (!key_exists($firstKey, $secondArray) || $secondArrayFirstKeyVal != $firstValue) {
                self::set($difference, "$oldKey.$firstKey", $firstValue);
                self::set($difference, "$newKey.$firstKey", $secondArrayFirstKeyVal);
            }
        }
        return $difference;
    }

    /**
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public static function compare(array $array1, array $array2): array
    {
        return array_replace_recursive(
            self::diffRecursive($array1, $array2),
            self::diffRecursive($array2, $array1, true)
        );
    }

    /**
     * @param array $array1
     * @param array $array2
     * @return bool
     */
    public static function isEqual(array $array1, array $array2): bool
    {
        return !(bool)count(self::compare($array1, $array2));
    }

    /**
     * @param array $array1
     * @param array $array2
     * @return bool
     */
    public static function isNull(array $array, $key): bool
    {
        return (bool)key_exists($key, $array) && is_null($array[$key]);
    }

    /**
     * @param array $source
     * @param array $keysMap
     * @return array
     */
    public static function extract($source, $keysMap): array
    {
        /*
            $keysMap format:
            [
                'search key name' => 'new key name',
                ...
            ]

            this function will extract values for `search key name` keys from
            $source array and change it key names to new key names
         */

        $source = Arr::wrap($source);
        $keysMap = Arr::wrap($keysMap);
        if (!Arr::is_assoc($source) || !Arr::is_assoc($keysMap))
            return [];

        return Arr::collapse(Arr::map($keysMap, function($val, $key) use ($source) {
            return [$val => Arr::get($source, $key)];
        }));
    }

    /**
     *
     *
     * @param array $source
     * @return array
     */
    public static function buildPaths(array $source): array
    {
        /*
            this function convenient to use with `Arr::get` or `Arr::path` function

        it builds flatten array of each non array node of $source array with path to its value

        ```
            $source =
            [
                'key1' => 'some 1value',
                'key2' => 'some 2value',
                'key3' => [
                    'key13' => '13value',
                    'key23' => [
                        'key123' => '123value'
                    ]
                ],

                'some_key_name' => 'some value',
                ...
            ]

            returns:

            [
                'key1'              =>  'some 1value',
                'key2'              =>  'some 2value',
                'key3.key13'        =>  '13value',
                'key3.key23.key123' =>  '123value',
                'some_key_name'     =>  'some value'
            ]


          ```
         */

        $source = Arr::wrap($source);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveArrayIterator($source),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $result = [];
        foreach ($iterator as $key => $val) {
            if (!is_array($val)) {
                // add the current key
                $keys = array($key);
                // loop up the recursive chain
                $i = $iterator->getDepth() - 1;
                for(; $i >= 0; ) {
                    // add each parent key
                    array_unshift($keys, $iterator->getSubIterator($i--)->key());
                }
                $result[implode('.', $keys)] = $val;
            }

        }
        return $result;
    }

    /**
     *  Prepares $config to $container->setParameter function
     *
     *
     * @param array $config
     * @param array $skip
     * @return array
     */
    public static function config2parameters(array $config, $skip = []): array
    {
        $paths = Arr::buildPaths($config);

        $skip = array_fill_keys($skip, []);
        $items = [];
        $result = array_filter($paths, function($v, $k) use ($skip, &$items) {
            foreach ($skip as $key => $value) {
                if (str_contains($k, $key)) {
                    Arr::set($items, $k, $v);
                    return FALSE;
                }
            }
            return TRUE;
        }, ARRAY_FILTER_USE_BOTH);

        foreach($skip as $key => &$value)
            $value = Arr::get($items, $key, []);

        return array_merge([], $result, $skip);
    }

}
