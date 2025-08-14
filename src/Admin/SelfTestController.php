<?php
namespace KISS\PTT\Admin;

class SelfTestController {
    public static function register() {
        add_action('admin_menu', [__CLASS__, 'menu'], 60);
        add_action('wp_ajax_ptt_run_self_tests', [__CLASS__, 'ajaxRunSelfTests']);
        add_action('wp_ajax_ptt_sync_authors_assignee', [__CLASS__, 'ajaxSyncAuthors']);
    }

    public static function menu() {
        add_submenu_page(
            'edit.php?post_type=project_task',
            'Tracker Settings',
            'Settings',
            'manage_options',
            'ptt-self-test',
            [__CLASS__, 'renderSelfTestPage']
        );
        add_submenu_page(
            'edit.php?post_type=project_task',
            'Plugin Changelog',
            'Changelog – v' . PTT_VERSION,
            'manage_options',
            'ptt-changelog',
            [__CLASS__, 'renderChangelogPage']
        );
    }

    public static function renderChangelogPage() {
        // Delegate to existing function to keep output identical
        if ( function_exists('ptt_changelog_page_html') ) {
            return \ptt_changelog_page_html();
        }
        echo '<div class="wrap"><h1>Plugin Changelog</h1><p>Changelog renderer not available.</p></div>';
    }

    public static function renderSelfTestPage() {
        if ( function_exists('ptt_self_test_page_html') ) {
            return \ptt_self_test_page_html();
        }
        echo '<div class="wrap"><h1>Plugin Settings & Self Test</h1><p>Renderer not available.</p></div>';
    }

    public static function ajaxRunSelfTests() {
        if ( function_exists('ptt_run_self_tests_callback') ) {
            return \ptt_run_self_tests_callback();
        }
        wp_send_json_error(['message' => 'Self-test handler not available.']);
    }

    public static function ajaxSyncAuthors() {
        if ( function_exists('ptt_sync_authors_assignee_callback') ) {
            return \ptt_sync_authors_assignee_callback();
        }
        wp_send_json_error(['message' => 'Sync handler not available.']);
    }
}

