<?php
namespace KISS\PTT\Presentation\Today;

use KISS\PTT\Plugin;

/**
 * Small PSR-4 helper for Today page date checks.
 * Centralizes logic for determining whether a UTC datetime string
 * should appear on a given local (site timezone) Y-m-d date.
 */
class DateHelper
{
    /**
     * Returns true if the given UTC MySQL datetime (Y-m-d H:i:s)
     * falls on the provided local date (Y-m-d) according to the
     * WordPress site timezone.
     */
    public static function isUtcOnLocalDate(?string $utcDateTime, string $localYmd): bool
    {
        if (!$utcDateTime) { return false; }
        $ts = Plugin::$acf->toUtcTimestamp($utcDateTime);
        if ($ts === null) { return false; }
        // Compare using wp_date which respects site timezone.
        return wp_date('Y-m-d', $ts) === $localYmd;
    }
}

