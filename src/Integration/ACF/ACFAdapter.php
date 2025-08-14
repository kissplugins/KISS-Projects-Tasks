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
}

