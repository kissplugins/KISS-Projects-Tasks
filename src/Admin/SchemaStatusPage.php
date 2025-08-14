<?php
namespace KISS\PTT\Admin;

use KISS\PTT\Integration\ACF\Diagnostics as ACFDiagnostics;

class SchemaStatusPage {
    public static function register() {
        add_action('admin_menu', [__CLASS__, 'menu'], 65);
    }

    public static function menu() {
        add_submenu_page(
            'edit.php?post_type=project_task',
            'ACF Schema Status',
            'ACF Schema Status',
            'manage_options',
            'ptt-acf-schema',
            [__CLASS__, 'render']
        );
    }

    public static function render() {
        if ( ! current_user_can('manage_options') ) { return; }
        echo '<div class="wrap">';
        echo '<h1>ACF Schema Status</h1>';
        echo '<p>This page summarizes the health of the plugin\'s ACF schema (keys, names, types). It is read-only and does not modify your database.</p>';

        if ( ! function_exists('acf_get_field_groups') ) {
            echo '<div class="notice notice-error"><p>ACF Pro is not active. Please install/activate ACF Pro.</p></div>';
            echo '</div>';
            return;
        }

        $issues = ACFDiagnostics::collectIssues();
        if ( empty($issues) ) {
            echo '<div class="notice notice-success"><p>No issues detected. Your ACF schema matches the expected mapping.</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p><strong>Warnings:</strong></p><ul style="margin-left:1em;">';
            foreach ($issues as $msg) {
                echo '<li>' . esc_html($msg) . '</li>';
            }
            echo '</ul></div>';
        }

        echo '<h2>Authoritative Mapping</h2>';
        echo '<p>See ROADMAP.md → "ACF Field Schema (Authoritative Mapping)" for the canonical list of keys, names, and types. Migration notes are included there.</p>';
        echo '<p><em>Tip:</em> To enable on-screen timer UI debugging, append <code>?ptt_debug=1</code> to the edit URL or run <code>localStorage.setItem(\'PTT_DEBUG\',\'1\'); location.reload();</code>.</p>';
        echo '</div>';
    }
}

