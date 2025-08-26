<?php
namespace KISS\PTT\Reports;

class Preferences
{
    /**
     * Handle sort-by-status cookie updates on admin_init for the Reports page.
     * Mirrors legacy ptt_handle_sort_status_cookie() logic.
     */
    public static function handleSortStatusCookie(): void
    {
        if ( ! isset($_GET['page']) || $_GET['page'] !== 'ptt-reports' ) {
            return;
        }
        if ( ! current_user_can('edit_posts') ) {
            return;
        }
        if ( isset($_GET['run_report']) ) {
            $sortPref = isset($_GET['sort_status']) ? sanitize_text_field( (string) $_GET['sort_status'] ) : 'default';
            if ( isset($_GET['remember_sort']) && $_GET['remember_sort'] === '1' ) {
                setcookie('ptt_sort_status', $sortPref, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            } else {
                setcookie('ptt_sort_status', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            }
        }
    }

    /**
     * Compute saved sort preference from request or cookie (fallback to 'default').
     * Used by reports page header and results rendering.
     */
    public static function getSavedSort(): string
    {
        if ( isset($_REQUEST['sort_status']) && $_REQUEST['sort_status'] !== '' ) {
            return sanitize_text_field( (string) $_REQUEST['sort_status'] );
        }
        if ( isset($_COOKIE['ptt_sort_status']) && $_COOKIE['ptt_sort_status'] !== '' ) {
            return sanitize_text_field( (string) $_COOKIE['ptt_sort_status'] );
        }
        return 'default';
    }
}

