<?php
namespace KISS\PTT\Admin;

class AdminBar {
    public static function register() {
        add_action('admin_bar_menu', [__CLASS__, 'addIndicator'], 100);
    }

    public static function addIndicator($wp_admin_bar) {
        if ( ! is_admin() || ! current_user_can('edit_posts') ) { return; }
        $version = defined('PTT_VERSION') ? PTT_VERSION : '0.0.0';
        $summary = get_option('ptt_tests_last_summary'); // ['pass'=>int,'fail'=>int,...]
        $status  = 'unknown';
        if (is_array($summary)) {
            $status = ($summary['fail'] ?? 0) > 0 || ($summary['error'] ?? 0) > 0 ? 'fail' : 'pass';
        }
        $color = ($status === 'pass') ? '#2ecc71' : (($status === 'fail') ? '#e74c3c' : '#bdc3c7');
        $dot   = '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' . esc_attr($color) . ';margin-right:6px;vertical-align:middle"></span>';
        $title = $dot . 'KISS Tasks – v' . esc_html($version);
        $args = [
            'id'    => 'ptt-indicator',
            'title' => $title,
            'href'  => admin_url('edit.php?post_type=project_task&page=ptt-self-test&auto=1'),
            'meta'  => [ 'title' => 'Open Self Test' ]
        ];
        $wp_admin_bar->add_node($args);
        // Auto-run flag handled by JS on the self-test page
    }
}

