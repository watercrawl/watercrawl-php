<?php

namespace WaterCrawl;

if (!function_exists('str_contains')) {
    /**
     * Polyfill for str_contains() function added in PHP 8.0
     *
     * @param string $haystack The string to search in
     * @param string $needle The substring to search for
     * @return bool Returns true if needle is in haystack, false otherwise
     */
    function str_contains(string $haystack, string $needle): bool {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    /**
     * Polyfill for str_starts_with() function added in PHP 8.0
     *
     * @param string $haystack The string to search in
     * @param string $needle The substring to search for
     * @return bool Returns true if haystack starts with needle, false otherwise
     */
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle !== '' && mb_strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    /**
     * Polyfill for str_ends_with() function added in PHP 8.0
     *
     * @param string $haystack The string to search in
     * @param string $needle The substring to search for
     * @return bool Returns true if haystack ends with needle, false otherwise
     */
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle !== '' && mb_substr($haystack, -mb_strlen($needle)) === $needle;
    }
} 