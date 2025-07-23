<?php
// If this file is called directly, abort.
if ( ! defined( "WPINC" ) ) {
    die;
}

// 6.0 CORE TIME CALCULATION LOGIC
// =================================================================

/**
 * Calculates duration between start and stop time and saves it to a custom field.
 *
 * @param int $post_id The ID of the post to calculate.
 * @return float The calculated duration in decimal hours.
 */
function ptt_calculate_and_save_duration( $post_id ) {
    $sessions = get_field( 'sessions', $post_id );
    $duration = 0.00;

    if ( ! empty( $sessions ) ) {
        $duration = ptt_get_total_sessions_duration( $post_id );
    } else {
        $manual_override = get_field( 'manual_override', $post_id );

        if ( $manual_override ) {
            $manual_duration = get_field( 'manual_duration', $post_id );
            $duration        = $manual_duration ? (float) $manual_duration : 0.00;
        } else {
            $start_time_str = get_field( 'start_time', $post_id );
            $stop_time_str  = get_field( 'stop_time', $post_id );

            if ( $start_time_str && $stop_time_str ) {
                try {
                    // Always use UTC for calculations to avoid timezone issues.
                    $start_time = new DateTime( $start_time_str, new DateTimeZone('UTC') );
                    $stop_time  = new DateTime( $stop_time_str, new DateTimeZone('UTC') );

                    if ( $stop_time > $start_time ) {
                        $diff_seconds   = $stop_time->getTimestamp() - $start_time->getTimestamp();
                        $duration_hours = $diff_seconds / 3600;
                        $duration       = ceil( $duration_hours * 100 ) / 100;
                    }
                } catch ( Exception $e ) {
                    $duration = 0.00;
                }
            }
        }
    }

    $formatted_duration = number_format( (float) $duration, 2, '.', '' );
    update_field( 'calculated_duration', $formatted_duration, $post_id );

    return $formatted_duration;
}

/**
 * Returns the index of any active session for a task.
 *
 * @param int $post_id The task ID.
 * @return int|false The active session index or false if none.
 */
function ptt_get_active_session_index( $post_id ) {
    $sessions = get_field( 'sessions', $post_id );
    if ( ! empty( $sessions ) && is_array( $sessions ) ) {
        foreach ( $sessions as $index => $session ) {
            if ( ! empty( $session['session_start_time'] ) && empty( $session['session_stop_time'] ) ) {
                return $index;
            }
        }
    }
    return false;
}

/**
 * Calculates and saves duration for a specific session row.
 *
 * @param int $post_id The task ID.
 * @param int $index   Session index (0 based).
 * @return string      Formatted duration hours.
 */
function ptt_calculate_session_duration( $post_id, $index ) {
    $sessions = get_field( 'sessions', $post_id );
    if ( empty( $sessions ) || ! isset( $sessions[ $index ] ) ) {
        return '0.00';
    }

    $session = $sessions[ $index ];

    if ( ! empty( $session['session_manual_override'] ) ) {
        $duration = isset( $session['session_manual_duration'] ) ? floatval( $session['session_manual_duration'] ) : 0.00;
    } else {
        $start_time_str = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
        $stop_time_str  = isset( $session['session_stop_time'] ) ? $session['session_stop_time'] : '';
        $duration       = 0.00;

        if ( $start_time_str && $stop_time_str ) {
            try {
                // Always use UTC for calculations
                $start_time = new DateTime( $start_time_str, new DateTimeZone('UTC') );
                $stop_time  = new DateTime( $stop_time_str, new DateTimeZone('UTC') );

                if ( $stop_time > $start_time ) {
                    $diff_seconds   = $stop_time->getTimestamp() - $start_time->getTimestamp();
                    $duration_hours = $diff_seconds / 3600;
                    $duration       = ceil( $duration_hours * 100 ) / 100;
                }
            } catch ( Exception $e ) {
                $duration = 0.00;
            }
        }
    }

    $formatted = number_format( (float) $duration, 2, '.', '' );
    update_sub_field( array( 'sessions', $index + 1, 'session_calculated_duration' ), $formatted, $post_id );
    return $formatted;
}

/**
 * Calculates the total duration of all sessions for a task.
 *
 * @param int $post_id Task ID.
 * @return float Total hours rounded to two decimals.
 */
function ptt_get_total_sessions_duration( $post_id ) {
    $sessions = get_field( 'sessions', $post_id );
    $total    = 0.0;

    if ( ! empty( $sessions ) && is_array( $sessions ) ) {
        foreach ( $sessions as $session ) {
            if ( ! empty( $session['session_manual_override'] ) ) {
                $dur = isset( $session['session_manual_duration'] ) ? floatval( $session['session_manual_duration'] ) : 0.0;
            } else {
                $start = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
                $stop  = isset( $session['session_stop_time'] ) ? $session['session_stop_time'] : '';
                $dur   = 0.0;

                if ( $start && $stop ) {
                    try {
                        $start_time = new DateTime( $start, new DateTimeZone('UTC') );
                        $stop_time  = new DateTime( $stop, new DateTimeZone('UTC') );
                        if ( $stop_time > $start_time ) {
                            $diff_seconds = $stop_time->getTimestamp() - $start_time->getTimestamp();
                            $dur          = $diff_seconds / 3600;
                        }
                    } catch ( Exception $e ) {
                        $dur = 0.0;
                    }
                }
            }

            $total += $dur;
        }
    }

    $total = ceil( $total * 100 ) / 100;
    return $total;
}


/**
 * Recalculates duration whenever a task post is saved.
 * Hooks into ACF's save_post action for reliability.
 *
 * @param int $post_id The post ID.
 */
function ptt_recalculate_on_save( $post_id ) {
    if ( get_post_type( $post_id ) === 'project_task' ) {
        $sessions = get_field( 'sessions', $post_id );
        if ( ! empty( $sessions ) ) {
            foreach ( array_keys( $sessions ) as $index ) {
                ptt_calculate_session_duration( $post_id, $index );
            }
        }
        ptt_calculate_and_save_duration( $post_id );
    }
}
add_action( 'acf/save_post', 'ptt_recalculate_on_save', 20 );

