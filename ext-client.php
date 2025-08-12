<?php
/**
 * External Client View: /ext/client/{client_id}/tasks
 * 
 * Public, read-only view that lists work for the last two calendar months
 * for a given client. Designed to be anonymous-accessible for clients.
 *
 * URL example: /ext/client/123/tasks
 *
 * Security: This page exposes only titles, statuses, and durations for tasks
 * scoped to a single client. Future Phase 2 hardening will add Turnstile/Recaptcha.
 */

// Block direct access
if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Register rewrite rule for external client tasks view
 */
function ptt_ext_client_rewrite_rule() {
    add_rewrite_rule(
        '^ext/client/([0-9]+)/tasks/?$',
        'index.php?ptt_ext_client_tasks=1&ptt_client_id=$matches[1]',
        'top'
    );
}
add_action( 'init', 'ptt_ext_client_rewrite_rule' );

/**
 * Add query vars
 */
function ptt_ext_client_query_vars( $vars ) {
    $vars[] = 'ptt_ext_client_tasks';
    $vars[] = 'ptt_client_id';
    return $vars;
}
add_filter( 'query_vars', 'ptt_ext_client_query_vars' );

/**
 * Template redirect handler for external client tasks page
 */
function ptt_ext_client_template_redirect() {
    if ( get_query_var( 'ptt_ext_client_tasks' ) ) {
        $client_id = absint( get_query_var( 'ptt_client_id' ) );
        if ( $client_id <= 0 ) {
            status_header( 404 );
            echo 'Client not found.';
            exit;
        }
        $term = get_term( $client_id, 'client' );
        if ( ! $term || is_wp_error( $term ) ) {
            status_header( 404 );
            echo 'Client not found.';
            exit;
        }

        // Enqueue plugin styles for frontend
        do_action( 'wp_enqueue_scripts' );
        wp_head();
        echo '<div class="ptt-ext-container" style="max-width:1000px;margin:30px auto;padding:0 15px;">';
        echo '<h1>Time Summary for Client: ' . esc_html( $term->name ) . '</h1>';
        echo '<p class="description">Showing the two most recent calendar months.</p>';

        echo ptt_ext_render_client_tasks_page( $client_id );

        echo '</div>';
        wp_footer();
        exit;
    }
}
add_action( 'template_redirect', 'ptt_ext_client_template_redirect' );

/**
 * Render the external client tasks page for the last two calendar months
 *
 * @param int $client_id
 * @return string HTML
 */
function ptt_ext_render_client_tasks_page( $client_id ) {
    $out = '';

    $now = current_time( 'timestamp' );

    // Determine current month and previous month boundaries in site timezone
    $current_month_start = strtotime( date( 'Y-m-01 00:00:00', $now ) );
    $current_month_end   = strtotime( date( 'Y-m-t 23:59:59', $now ) );

    $prev_month_ts       = strtotime( '-1 month', $current_month_start );
    $prev_month_start    = strtotime( date( 'Y-m-01 00:00:00', $prev_month_ts ) );
    $prev_month_end      = strtotime( date( 'Y-m-t 23:59:59', $prev_month_ts ) );

    // Render current month then previous month
    $out .= ptt_ext_render_client_month_section( $client_id, $current_month_start, $current_month_end );
    $out .= ptt_ext_render_client_month_section( $client_id, $prev_month_start, $prev_month_end );

    return $out;
}

/**
 * Build and render a month section block
 *
 * @param int $client_id
 * @param int $start_ts
 * @param int $end_ts
 * @return string
 */
function ptt_ext_render_client_month_section( $client_id, $start_ts, $end_ts ) {
    $month_title = date( 'F Y', $start_ts );

    // Query all tasks under this client
    $args = [
        'post_type'      => 'project_task',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'tax_query'      => [
            [
                'taxonomy' => 'client',
                'field'    => 'term_id',
                'terms'    => $client_id,
            ],
        ],
        'orderby'        => 'date',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ];
    $q = new WP_Query( $args );

    $tasks_summary   = []; // [post_id => [title, status, month_duration]]
    $session_entries = []; // flat list of [date, task_title, duration]
    $month_total     = 0.0;

    if ( $q->have_posts() ) {
        foreach ( $q->posts as $post_id ) {
            $title = get_the_title( $post_id );

            // Get status (first term name)
            $status_terms = get_the_terms( $post_id, 'task_status' );
            $status_name  = ( ! is_wp_error( $status_terms ) && ! empty( $status_terms ) ) ? $status_terms[0]->name : '';

            $task_total = 0.0;

            // Sessions-based time
            if ( function_exists( 'get_field' ) ) {
                $sessions = get_field( 'sessions', $post_id );
                if ( ! empty( $sessions ) && is_array( $sessions ) ) {
                    foreach ( $sessions as $session ) {
                        $start_str  = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
                        $stop_str   = isset( $session['session_stop_time'] )  ? $session['session_stop_time']  : '';
                        $is_manual  = ! empty( $session['session_manual_override'] );
                        $man_hours  = isset( $session['session_manual_duration'] ) ? floatval( $session['session_manual_duration'] ) : 0.0;

                        $include = false;
                        $entry_ts = null;

                        if ( $start_str ) {
                            $ts = strtotime( $start_str );
                            if ( $ts >= $start_ts && $ts <= $end_ts ) {
                                $include = true;
                                $entry_ts = $ts;
                            }
                        } elseif ( $is_manual && $man_hours > 0 ) {
                            // Heuristic: include manual sessions without timestamps if task was created in the month
                            $creation_ts = get_the_date( 'U', $post_id );
                            if ( $creation_ts >= $start_ts && $creation_ts <= $end_ts ) {
                                $include = true;
                                $entry_ts = $creation_ts;
                            }
                        }

                        if ( $include ) {
                            $duration = 0.0;
                            if ( $is_manual ) {
                                $duration = $man_hours;
                            } else {
                                if ( $start_str && $stop_str ) {
                                    try {
                                        $start_dt = new DateTime( $start_str, new DateTimeZone( 'UTC' ) );
                                        $stop_dt  = new DateTime( $stop_str,  new DateTimeZone( 'UTC' ) );
                                        if ( $stop_dt > $start_dt ) {
                                            $diff_seconds = $stop_dt->getTimestamp() - $start_dt->getTimestamp();
                                            $duration     = ceil( ( $diff_seconds / 3600 ) * 100 ) / 100; // round up to 2 decimals
                                        }
                                    } catch ( Exception $e ) {
                                        $duration = 0.0;
                                    }
                                }
                            }

                            if ( $duration > 0 ) {
                                $task_total   += $duration;
                                $month_total  += $duration;
                                $session_entries[] = [
                                    'date'       => date( 'Y-m-d', $entry_ts ),
                                    'task_title' => $title,
                                    'duration'   => $duration,
                                ];
                            }
                        }
                    }
                }
            }

            if ( $task_total > 0 ) {
                $tasks_summary[ $post_id ] = [
                    'title'   => $title,
                    'status'  => $status_name,
                    'hours'   => $task_total,
                ];
            }
        }
        wp_reset_postdata();
    }

    ob_start();
    echo '<section class="ptt-ext-month">';
    echo '<h2>' . esc_html( $month_title ) . '</h2>';

    // Tasks + Current Status
    echo '<h3>Tasks + Current Status</h3>';
    if ( empty( $tasks_summary ) ) {
        echo '<p>No time entries recorded for this month.</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Task</th><th>Status</th><th style="text-align:right">Hours</th></tr></thead><tbody>';
        foreach ( $tasks_summary as $row ) {
            echo '<tr>';
            echo '<td>' . esc_html( $row['title'] ) . '</td>';
            echo '<td>' . esc_html( $row['status'] ) . '</td>';
            echo '<td style="text-align:right"><span class="ptt-time-display">' . number_format( $row['hours'], 2 ) . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    // Session Entries + Entered Time
    echo '<h3 style="margin-top:20px;">Session Entries + Entered Time</h3>';
    if ( empty( $session_entries ) ) {
        echo '<p>No session entries this month.</p>';
    } else {
        // Sort entries chronologically
        usort( $session_entries, function ( $a, $b ) { return strcmp( $a['date'], $b['date'] ); } );
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th style="width:20%">Date</th><th>Task</th><th style="text-align:right;width:15%">Hours</th></tr></thead><tbody>';
        foreach ( $session_entries as $e ) {
            echo '<tr>';
            echo '<td>' . esc_html( date( 'M j, Y', strtotime( $e['date'] ) ) ) . '</td>';
            echo '<td>' . esc_html( $e['task_title'] ) . '</td>';
            echo '<td style="text-align:right"><span class="ptt-time-display">' . number_format( $e['duration'], 2 ) . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '<p class="grand-total"><strong>Total for ' . esc_html( $month_title ) . ': <span class="ptt-time-display">' . number_format( $month_total, 2 ) . '</span> hours</strong></p>';
    echo '</section>';

    return ob_get_clean();
}

