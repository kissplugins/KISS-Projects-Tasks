<?php
namespace KISS\PTT\Admin;

class Assets {
    public static function register() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
    }

    public static function enqueue_admin($hook) {
        // Self-Test + Changelog pages
        $selftest_hooks = [ 'project_task_page_ptt-self-test', 'project_task_page_ptt-changelog' ];
        if ( in_array($hook, $selftest_hooks, true) ) {
            self::enqueueCore($hook);
            return;
        }


        // Reports page
        if ( $hook === 'project_task_page_ptt-reports' ) {
            self::enqueueCore($hook);
            return;
        }

        // Project Task post editor (post.php / post-new.php)
        if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ( $screen && $screen->post_type === 'project_task' ) {
                self::enqueueCore($hook);
                return;
            }
        }
    }

    private static function enqueueCore($hook) {
        wp_enqueue_style( 'ptt-styles', PTT_PLUGIN_URL . 'styles.css', [], PTT_VERSION );
        wp_enqueue_script( 'ptt-scripts', PTT_PLUGIN_URL . 'scripts.js', [ 'jquery' ], PTT_VERSION, true );
        wp_localize_script( 'ptt-scripts', 'ptt_ajax_object', [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('ptt_ajax_nonce'),
            'pluginUrl' => PTT_PLUGIN_URL,
        ] );

        // Editor unified FSM core and controller
        wp_enqueue_script( 'ptt-fsm-editor-core', PTT_PLUGIN_URL . 'assets/js/fsm/editor/EditorFSM.js', [ 'jquery' ], PTT_VERSION, true );
        wp_enqueue_script( 'ptt-fsm-timer-editor', PTT_PLUGIN_URL . 'assets/js/fsm/timer/EditorEffects.js', [ 'ptt-fsm-editor-core', 'jquery' ], PTT_VERSION, true );
        wp_enqueue_script( 'ptt-fsm-timer-editor-controller', PTT_PLUGIN_URL . 'assets/js/fsm/timer/EditorTimerController.js', [ 'ptt-fsm-timer-editor' ], PTT_VERSION, true );
        // FSM flags from settings helper (defaults ON). Applies to all users (internal testers).
        $flags = Settings::getFlags();
        wp_localize_script( 'ptt-fsm-timer-editor-controller', 'PTT_FSM_FLAGS', [
            'PTT_FSM_ENABLED' => $flags['enabled'],
            'PTT_FSM_EDITOR_ENABLED' => $flags['enabled'] && $flags['editor'],
        ] );
    }
}

