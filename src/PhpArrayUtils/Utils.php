<?php

namespace PhpArrayUtils;

use Traversable;

class Utils {
    /**
     * Gets a plain object representation of `$value`.
     *
     * @param mixed $value
     * @return object Empty object if `$value` is not iterable
     *
     * @example
    Utils::toObject(['a' => 1, 'b' => 2]);
    // === (object) ['a' => 1, 'b' => 2]

    Utils::toObject(new ArrayObject(['a' => 1, 'b' => 2]));
    // === (object) ['a' => 1, 'b' => 2]
     */
    public static function toObject($value): object
    {
        return (object) self::toArray($value);
    }

    /**
     * Gets an array representation of `$value`.
     *
     * @param mixed $value
     * @return array Empty array if `$value` is not iterable
     *
     * @example
    Utils::toArray((object) ['a' => 1, 'b' => 2]);
    // === ['a' => 1, 'b' => 2]
    Utils::toArray(new FilesystemIterator(__DIR__));
    // === [ SplFileInfo, SplFileInfo, ... ]
     */
    public static function toArray($value): array
    {
        if (self::isType($value, ['Traversable', 'stdClass'])) {
            $array = [];
            foreach ($value as $key => $val) {
                $array[$key] = is_object($val) ? clone $val : $val;
            }
            return $array;
        }

        return (array) $value;
    }

    public static function isType($value, $type)
    {
        if (empty($type))
            return TRUE;

        $types = Arr::wrap($type);

        foreach ($types as $type)
        {
            if ($type === 'iterable' && !function_exists("is_$type"))
                // Polyfills is_iterable() since it's only in PHP 7.1+
                return is_array($value) || $value instanceof Traversable;

            if (function_exists("is_$type"))
                return call_user_func("is_$type", $value);

            // Class type
            if ($value instanceof $type)
                return TRUE;
        }

        return FALSE;
    }
}
