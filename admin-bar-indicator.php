<?php
// Guard
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Mark the current user's timer status (used by admin bar indicator)
 */
function ptt_set_user_timer_status( $user_id, $is_running, $post_id = 0 ) {
    if ( ! $user_id ) return;
    update_user_meta( $user_id, 'ptt_active_timer', $is_running ? '1' : '0' );
    if ( $is_running && $post_id ) {
        update_user_meta( $user_id, 'ptt_active_timer_post_id', intval( $post_id ) );
    }
}

/**
 * Admin Bar: Active Timer Indicator
 */
function ptt_admin_bar_active_timer_indicator( $wp_admin_bar ) {
    if ( ! is_user_logged_in() ) return;
    if ( ! is_admin_bar_showing() ) return;

    // Check if current user has active session
    $user_id = get_current_user_id();
    $active = false;

    if ( function_exists( 'ptt_get_active_session_index_for_user' ) ) {
        $active_session = ptt_get_active_session_index_for_user( $user_id );
        if ( $active_session ) {
            $active = true;
        }
    }

    // Also check parent-level running timer (task-level start/stop)
    if ( ! $active && function_exists( 'ptt_get_tasks_for_user' ) ) {
        $task_ids = ptt_get_tasks_for_user( $user_id );
        if ( ! empty( $task_ids ) ) {
            $args = [
                'post_type'      => 'project_task',
                'post__in'       => $task_ids,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => [
                    [ 'key' => 'start_time', 'compare' => 'EXISTS' ],
                    [ 'key' => 'stop_time',  'compare' => 'NOT EXISTS' ],
                ],
            ];
            $q = new WP_Query( $args );
            if ( $q->have_posts() ) {
                $active = true;
            }
        }
    }

    if ( $active ) {
        $wp_admin_bar->add_node( [
            'id'    => 'ptt-active-timer',
            'title' => '<span class="ptt-timer-dot"></span> Timer Running',
            'href'  => admin_url( 'admin.php?page=ptt-today' ),
            'meta'  => [ 'title' => 'A timer is running. Click to open Today page.' ]
        ] );
        add_action( 'admin_head', 'ptt_admin_bar_timer_indicator_css' );
        add_action( 'wp_head', 'ptt_admin_bar_timer_indicator_css' );
    }
}
add_action( 'admin_bar_menu', 'ptt_admin_bar_active_timer_indicator', 100 );

function ptt_admin_bar_timer_indicator_css() {
    echo '<style>
    #wpadminbar #wp-admin-bar-ptt-active-timer .ptt-timer-dot { display:inline-block; width:10px; height:10px; border-radius:50%; background:#d63638; margin-right:6px; box-shadow:0 0 0 0 rgba(214,54,56, 0.7); animation: ptt-pulse 1.5s infinite; vertical-align:middle; }
    @keyframes ptt-pulse { 0% { box-shadow:0 0 0 0 rgba(214,54,56, 0.7);} 70% { box-shadow:0 0 0 8px rgba(214,54,56, 0);} 100% { box-shadow:0 0 0 0 rgba(214,54,56, 0);} }
    </style>';
}

