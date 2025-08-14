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
        $hasIssues = !empty($issues);
        if ( !$hasIssues ) {
            echo '<div class="notice notice-success"><p>No issues detected. Your ACF schema matches the expected mapping.</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p><strong>Warnings:</strong></p><ul style="margin-left:1em;">';
            foreach ($issues as $msg) {
                echo '<li>' . esc_html($msg) . '</li>';
            }
            echo '</ul>';
            echo '<p><button id="ptt-copy-diagnostics" class="button">Copy diagnostics (text)</button> ';
            echo '<button id="ptt-copy-diagnostics-json" class="button">Copy diagnostics (JSON)</button></p>';
            echo '</div>';
            // Inline script for copy buttons (no extra asset required)
            echo '<script>(function(){\n'
                . 'function copy(txt){navigator.clipboard.writeText(txt).then(function(){alert("Diagnostics copied to clipboard.");}).catch(function(){prompt("Copy diagnostics:", txt);});}\n'
                . 'document.addEventListener("DOMContentLoaded",function(){\n'
                . 'var btn1=document.getElementById("ptt-copy-diagnostics"); if(btn1){btn1.addEventListener("click",function(){copy(' . json_encode(implode("\n", $issues)) . ');});}\n'
                . 'var btn2=document.getElementById("ptt-copy-diagnostics-json"); if(btn2){btn2.addEventListener("click",function(){copy(' . json_encode(json_encode($issues)) . ');});}\n'
                . '});})();</script>';
        }

        echo '<h2>Authoritative Mapping</h2>';
        echo '<p>See ROADMAP.md → "ACF Field Schema (Authoritative Mapping)" for the canonical list of keys, names, and types. Migration notes are included there.</p>';
        echo '<p><em>Tip:</em> To enable on-screen timer UI debugging, append <code>?ptt_debug=1</code> to the edit URL or run <code>localStorage.setItem(\'PTT_DEBUG\',\'1\'); location.reload();</code>.</p>';
        echo '</div>';
    }
}

