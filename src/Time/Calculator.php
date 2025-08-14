<?php
namespace KISS\PTT\Time;

use DateTime;
use DateTimeZone;
use Exception;

class Calculator {
    public static function calculate_and_save_duration( $post_id ) {
        $sessions = get_field( 'sessions', $post_id );
        $duration = 0.00;

        if ( ! empty( $sessions ) ) {
            $duration = self::get_total_sessions_duration( $post_id );
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
                        $start_time = new DateTime( $start_time_str, new DateTimeZone( 'UTC' ) );
                        $stop_time  = new DateTime( $stop_time_str, new DateTimeZone( 'UTC' ) );

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

    public static function get_active_session_index( $post_id ) {
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

    public static function calculate_session_duration( $post_id, $index ) {
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
                    $start_time = new DateTime( $start_time_str, new DateTimeZone( 'UTC' ) );
                    $stop_time  = new DateTime( $stop_time_str, new DateTimeZone( 'UTC' ) );

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
        update_sub_field( [ 'sessions', $index + 1, 'session_calculated_duration' ], $formatted, $post_id );
        return $formatted;
    }

    protected static function get_total_sessions_duration( $post_id ) {
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
                            $start_time = new DateTime( $start, new DateTimeZone( 'UTC' ) );
                            $stop_time  = new DateTime( $stop, new DateTimeZone( 'UTC' ) );
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

    public static function ensure_manual_session_timestamps( $post_id ) {
        if ( get_post_type( $post_id ) !== 'project_task' ) {
            return;
        }

        $sessions = get_field( 'sessions', $post_id );
        if ( empty( $sessions ) || ! is_array( $sessions ) ) {
            return;
        }

        $now = null;
        $did_update = false;

        foreach ( $sessions as $i => $session ) {
            $is_manual = ! empty( $session['session_manual_override'] );
            $has_start = ! empty( $session['session_start_time'] );

            if ( $is_manual && ! $has_start ) {
                if ( null === $now ) {
                    $now = current_time( 'mysql', 1 );
                }
                $row_index = $i + 1;
                update_sub_field( [ 'field_ptt_sessions', $row_index, 'field_ptt_session_start_time' ], $now, $post_id );
                update_sub_field( [ 'field_ptt_sessions', $row_index, 'field_ptt_session_stop_time' ], $now, $post_id );
                $sessions[ $i ]['session_start_time'] = $now;
                $sessions[ $i ]['session_stop_time']  = $now;
                $did_update = true;
            }
        }

        if ( $did_update ) {
            update_field( 'sessions', $sessions, $post_id );
        }
    }

    public static function recalculate_on_save( $post_id ) {
        if ( get_post_type( $post_id ) === 'project_task' ) {
            self::ensure_manual_session_timestamps( $post_id );
            $sessions = get_field( 'sessions', $post_id );
            if ( ! empty( $sessions ) ) {
                foreach ( array_keys( $sessions ) as $index ) {
                    self::calculate_session_duration( $post_id, $index );
                }
            }
            self::calculate_and_save_duration( $post_id );
        }
    }
}
