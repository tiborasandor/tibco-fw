<?php
declare(strict_types=1);

namespace system\helpers;

class ArrayHelper {

    /**
     * Turns a stdClass into an array
     */
    public function stdToArray($stdclass):array {
        return $stdclass ? json_decode(json_encode($stdclass), true) : [];
    }

    /**
     * Uses the first row of an array as keys for the rest of the array's elements
     */
    public function firstRowToKeys($array):array {
        return $this->rowToKeys($array, 1);
    }

    /**
     * Uses one row of an array as keys for the rest of the array's elements
     */
    public function rowToKeys($array, $row_number):array {
        $row = array_slice($array, $row_number, 1, true);

        $keys = array_map('trim', array_slice($array, $row_number-1, null, true));
        return array_map(function($value) use ($keys) {
            return array_combine($keys, $value);
        }, $array);
    }

    /**
     * Recursively filters (empties) empty elements in an array
     * $exceptions can list values that should be kept even though they'd otherwise count as empty (e.g. 0, '0', false)
     * If no exceptions are given, empty() is applied to every element
     */
    public function filterRecursive(array $array, ?array $exceptions = null) {
        $array = array_map(function($value) use ($exceptions) {
            if (is_array($value)) {
                return $this->filterRecursive($value, $exceptions);
            } else {
                return trim($value);
            }
        }, $array);

        return array_filter($array, function($v, $k) use ($exceptions) {
            if (!empty($exceptions) && in_array($v, $exceptions, true)) {
                return true;
            } else {
                return !empty($v);
            }
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Removes keys that are empty across every row
     */
    public function removeEmptySameRows(array $rows):array {
        // remove empty keys, remember the non-empty ones ($keys)
        $keys = [];
        array_walk($rows, function(&$item) use (&$keys) {
            $item = array_filter($item, function($v, $k) use (&$keys) {
                if (empty($v) && $v !== 0 && $v !== '0') {
                    return false;
                } else {
                    $keys[$k] = null;
                    return true;
                }
            }, ARRAY_FILTER_USE_BOTH);
        });

        // put the non-empty keys back into the rows where they were missing
        array_walk($rows, function(&$item) use ($keys) {
            $item += $keys;
            ksort($item);
        });

        return $rows;
    }

    /**
     * Search in a two-dimensional array by key
     */
    public function multiSearch(array $array, string $key, $searchValue):array {
        $searchValues = is_array($searchValue) ? $searchValue : [$searchValue];

        $result = [];
        foreach ($searchValues as $value) {
            // extract all keys and values
            $keys = array_keys($array);
            $values = array_column($array, $key);
            $valueToKey = array_combine($keys, $values);

            // select keys based on the value
            $matchingKeys = array_keys($valueToKey, $value, true);

            // select the result based on the keys
            $matchingItems = array_intersect_key($array, array_flip($matchingKeys));

            // add the results to the final array
            $result += $matchingItems;
        }

        return $result;
    }

    public function diff(array $array1, array $array2):array {
        return [
            'normal' => array_filter(array_diff($array1, $array2)),
            'reverse' => array_filter(array_diff($array2, $array1))
        ];
    }

    public function diffKeys(array $array1, array $array2):array {
        return [
            'normal' => array_keys(array_filter(array_diff_key($array1, $array2))),
            'reverse' => array_keys(array_filter(array_diff_key($array2, $array1)))
        ];
    }

}

?>
