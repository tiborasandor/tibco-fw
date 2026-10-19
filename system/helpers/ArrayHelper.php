<?php
declare(strict_types=1);

namespace system\helpers;

class ArrayHelper {

    /** stdClass to array */
    public function stdToArray($stdclass):array {
        return $stdclass ? json_decode(json_encode($stdclass), true) : [];
    }

    /** Uses the first row as keys for the other rows */
    public function firstRowToKeys($array):array {
        return $this->rowToKeys($array, 1);
    }

    /** Uses one row as keys for the other rows */
    public function rowToKeys($array, $row_number):array {
        $row = array_slice($array, $row_number - 1, 1, true);
        $keys = array_map('trim', reset($row) ?: []);

        return array_map(function($value) use ($keys) {
            return array_combine($keys, $value);
        }, $array);
    }

    /** Recursively removes empty elements, except the values in $exceptions (e.g. 0, '0') */
    public function filterRecursive(array $array, ?array $exceptions = null) {
        $array = array_map(function($value) use ($exceptions) {
            if (is_array($value)) {
                return $this->filterRecursive($value, $exceptions);
            } elseif (is_string($value)) {
                return trim($value);
            } else {
                return $value;
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

    /** Removes keys that are empty in every row */
    public function removeEmptySameRows(array $rows):array {
        
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

        
        array_walk($rows, function(&$item) use ($keys) {
            $item += $keys;
            ksort($item);
        });

        return $rows;
    }

    /** Search in a two-dimensional array by key */
    public function multiSearch(array $array, string $key, $searchValue):array {
        $searchValues = is_array($searchValue) ? $searchValue : [$searchValue];

        $result = [];
        foreach ($searchValues as $value) {
            $matchingItems = array_filter($array, function($item) use ($key, $value) {
                return is_array($item) && array_key_exists($key, $item) && $item[$key] === $value;
            });

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
