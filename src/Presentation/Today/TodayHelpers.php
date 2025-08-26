<?php
namespace KISS\PTT\Presentation\Today;

/**
 * PSR-4 replacement for today-helpers.php
 *
 * Provides a namespace container and API placeholder. The actual Today helper
 * classes (EntryRenderer, DataProvider, PageManager) now live in separate
 * PSR-4 files to align with Composer autoloading.
 */
class TodayHelpers
{
    /**
     * Register procedural class wrappers for backward compatibility.
     * Classes are declared in the compatibility file.
     */
    public static function registerProceduralWrappers(): void
    {
        // No-op: compatibility wrappers are defined in today-helpers-compat.php
    }
}
