<?php
namespace KISS\PTT\Presentation\Today;

use KISS\PTT\Plugin;

/**
 * Minimal Today service to centralize data-building helpers under PSR-4.
 * This is a non-breaking wrapper over existing static helpers.
 */
class TodayService
{
    /** Calculate total duration from Today entries. */
    public static function calculateTotalDuration(array $entries): array
    {
        $total = 0;
        foreach ($entries as $e) {
            if (isset($e['duration_seconds'])) { $total += (int)$e['duration_seconds']; }
        }
        return [ 'seconds' => $total, 'formatted' => gmdate('H:i:s', $total) ];
    }
}

