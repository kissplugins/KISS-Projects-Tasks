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
