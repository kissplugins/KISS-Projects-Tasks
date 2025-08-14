<?php
use KISS\PTT\Time\Calculator;

// Block direct access.
if ( ! defined( 'WPINC' ) ) {
    die;
}

function ptt_calculate_and_save_duration( $post_id ) {
    return Calculator::calculate_and_save_duration( $post_id );
}

function ptt_get_active_session_index( $post_id ) {
    return Calculator::get_active_session_index( $post_id );
}

function ptt_calculate_session_duration( $post_id, $index ) {
    return Calculator::calculate_session_duration( $post_id, $index );
}

function ptt_ensure_manual_session_timestamps( $post_id ) {
    Calculator::ensure_manual_session_timestamps( $post_id );
}

// Back-compat: expose total sessions duration function for self-tests
if ( ! function_exists( 'ptt_get_total_sessions_duration' ) ) {
    function ptt_get_total_sessions_duration( $post_id ) {
        // Delegate to Calculator's internal method by recalculating
        // and reading the individual session totals if needed.
        $sessions = get_field( 'sessions', $post_id );
        if ( empty( $sessions ) ) {
            return 0.0;
        }
        $total = 0.0;
        foreach ( $sessions as $idx => $s ) {
            $dur = \KISS\PTT\Time\Calculator::calculate_session_duration( $post_id, $idx );
            $total += (float) $dur;
        }
        return (float) number_format( ceil( $total * 100 ) / 100, 2, '.', '' );
    }
}

