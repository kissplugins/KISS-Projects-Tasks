<?php

namespace KISS\PTT\Integration\ACF;

use DateTime;
use DateTimeZone;

/**
 * Thin wrapper around ACF functions to centralize access and UTC normalization.
 * NOTE: This adapter assumes ACF functions exist; callers should guard in environments
 * where ACF is not loaded.
 */
class ACFAdapter
{
    /** Get field value by name for a post. */
    public function getField(string $name, int $postId)
    {
        return function_exists('get_field') ? get_field($name, $postId) : null;
    }

    /** Update field value by name for a post. */
    public function updateField(string $name, $value, int $postId): bool
    {
        return function_exists('update_field') ? (bool) update_field($name, $value, $postId) : false;
    }

    /** Add a row to a repeater field. */
    public function addRow(string $repeaterName, array $row, int $postId): bool
    {
        return function_exists('add_row') ? (bool) add_row($repeaterName, $row, $postId) : false;
    }

    /** Update a sub field within a repeater field. Index is 1-based for ACF. */
    public function updateSubField(array $selector, $value, int $postId): bool
    {
        return function_exists('update_sub_field') ? (bool) update_sub_field($selector, $value, $postId) : false;
    }

    /** Delete a row from a repeater field by 1-based index. */
    public function deleteRow(string $repeaterName, int $index1Based, int $postId): bool
    {
        return function_exists('delete_row') ? (bool) delete_row($repeaterName, $index1Based, $postId) : false;
    }

    /**
     * Normalize a MySQL datetime string to UTC (Y-m-d H:i:s) if possible.
     * Accepts values already in UTC; returns original if parsing fails.
     */
    public function normalizeUtc(?string $mysqlDateTime): ?string
    {
        if (!$mysqlDateTime) { return $mysqlDateTime; }
        try {
            $dt = new DateTime($mysqlDateTime, new DateTimeZone('UTC'));
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return $mysqlDateTime;
        }
    }

    /** Current UTC now in MySQL format. */
    public function nowUtc(): string
    {
        return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    /** WordPress site timezone (falls back to UTC). */
    public function wpTimezone(): DateTimeZone
    {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }
        $tz = get_option('timezone_string');
        if (!$tz || !@timezone_open($tz)) { $tz = 'UTC'; }
        return new DateTimeZone($tz);
    }

    /**
     * Parse a MySQL datetime string as UTC and return a Unix timestamp.
     * Returns null on failure or empty input.
     */
    public function toUtcTimestamp(?string $mysqlDateTime): ?int
    {
        if (!$mysqlDateTime) { return null; }
        try {
            $dt = new DateTime($mysqlDateTime, new DateTimeZone('UTC'));
            return $dt->getTimestamp();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Treat the given MySQL datetime (stored as UTC) and convert to a local timestamp.
     */
    public function utcStringToLocalTimestamp(?string $mysqlDateTime): ?int
    {
        if (!$mysqlDateTime) { return null; }
        try {
            $utc = new DateTime($mysqlDateTime, new DateTimeZone('UTC'));
            $utc->setTimezone($this->wpTimezone());
            return $utc->getTimestamp();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Convert a local date range (Y-m-d) to UTC boundary timestamps corresponding to
     * 00:00:00 local on $startDate through 23:59:59 local on $endDate.
     * If $endDate is null, uses PHP_INT_MAX for the upper bound.
     */
    public function localDateRangeToUtcBounds(?string $startDate, ?string $endDate): array
    {
        $tz = $this->wpTimezone();
        $min = 0; $max = PHP_INT_MAX;
        try {
            if ($startDate) {
                $start = new DateTime($startDate . ' 00:00:00', $tz);
                $min = (int) $start->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
            }
            if ($endDate) {
                $end = new DateTime($endDate . ' 23:59:59', $tz);
                $max = (int) $end->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
            }
        } catch (\Throwable $e) {}
        return [ 'start_ts_utc' => $min, 'end_ts_utc' => $max ];
    }

    /**
     * Check if a UTC datetime string lies within a local date range (Y-m-d).
     * This converts the local date range to UTC bounds and compares in UTC space.
     */
    public function isUtcWithinLocalRange(?string $utcDateTime, ?string $startDate, ?string $endDate): bool
    {
        $ts = $this->toUtcTimestamp($utcDateTime);
        if ($ts === null) { return false; }
        $bounds = $this->localDateRangeToUtcBounds($startDate, $endDate);
        return ($ts >= $bounds['start_ts_utc'] && $ts <= $bounds['end_ts_utc']);
    }
}

