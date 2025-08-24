<?php
/**
 * Programmatic ACF field groups to satisfy self-tests on clean installs.
 * This defines the minimal required groups/fields/keys used across the plugin.
 */

namespace KISS\PTT\Integration\ACF;

if ( ! defined( 'WPINC' ) ) { die; }

/**
 * Register local ACF field groups if ACF is active.
 */
function ptt_register_local_acf_groups() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    // ------------------------------------------------------------------
    // Group: Task Fields (attached to project_task post type)
    // Key must be 'group_ptt_task_fields' for tests
    // ------------------------------------------------------------------
    acf_add_local_field_group( [
        'key' => 'group_ptt_task_fields',
        'title' => 'PTT – Task Fields',
        'location' => [ [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'project_task' ] ] ],
        'fields' => [
            [ 'key' => 'field_ptt_task_max_budget', 'label' => 'Max Budget (hrs)', 'name' => 'task_max_budget', 'type' => 'number', 'default_value' => '', 'min' => 0, 'step' => '0.01' ],
            [ 'key' => 'field_ptt_task_deadline', 'label' => 'Task Deadline', 'name' => 'task_deadline', 'type' => 'date_time_picker', 'display_format' => 'Y-m-d H:i:s', 'return_format' => 'Y-m-d H:i:s' ],
            [ 'key' => 'field_ptt_start_time', 'label' => 'Start Time', 'name' => 'start_time', 'type' => 'date_time_picker', 'display_format' => 'Y-m-d H:i:s', 'return_format' => 'Y-m-d H:i:s' ],
            [ 'key' => 'field_ptt_stop_time', 'label' => 'Stop Time', 'name' => 'stop_time', 'type' => 'date_time_picker', 'display_format' => 'Y-m-d H:i:s', 'return_format' => 'Y-m-d H:i:s' ],
            [ 'key' => 'field_ptt_calculated_duration', 'label' => 'Calculated Duration (hrs)', 'name' => 'calculated_duration', 'type' => 'text', 'readonly' => 1 ],
            [ 'key' => 'field_ptt_manual_override', 'label' => 'Manual Override', 'name' => 'manual_override', 'type' => 'true_false', 'ui' => 1 ],
            [ 'key' => 'field_ptt_manual_duration', 'label' => 'Manual Duration (hrs)', 'name' => 'manual_duration', 'type' => 'number', 'min' => 0, 'step' => '0.01' ],
            [
                'key' => 'field_ptt_sessions',
                'label' => 'Sessions',
                'name' => 'sessions',
                'type' => 'repeater',
                'layout' => 'row',
                'collapsed' => 'field_ptt_session_title',
                'min' => 0,
                'button_label' => 'Add Session',
                'sub_fields' => [
                    [ 'key' => 'field_ptt_session_title', 'label' => 'Title', 'name' => 'session_title', 'type' => 'text' ],
                    [ 'key' => 'field_ptt_session_notes', 'label' => 'Notes', 'name' => 'session_notes', 'type' => 'textarea' ],
                    [ 'key' => 'field_ptt_session_start_time', 'label' => 'Start', 'name' => 'session_start_time', 'type' => 'date_time_picker', 'display_format' => 'Y-m-d H:i:s', 'return_format' => 'Y-m-d H:i:s' ],
                    [ 'key' => 'field_ptt_session_stop_time', 'label' => 'Stop', 'name' => 'session_stop_time', 'type' => 'date_time_picker', 'display_format' => 'Y-m-d H:i:s', 'return_format' => 'Y-m-d H:i:s' ],
                    [ 'key' => 'field_ptt_session_manual_override', 'label' => 'Manual Override', 'name' => 'session_manual_override', 'type' => 'true_false', 'ui' => 1 ],
                    [ 'key' => 'field_ptt_session_manual_duration', 'label' => 'Manual Duration (hrs)', 'name' => 'session_manual_duration', 'type' => 'number', 'min' => 0, 'step' => '0.01' ],
                    [ 'key' => 'field_ptt_session_calculated_duration', 'label' => 'Calculated (hrs)', 'name' => 'session_calculated_duration', 'type' => 'text', 'readonly' => 1 ],
                    [ 'key' => 'field_ptt_session_timer_controls', 'label' => 'Timer Controls', 'name' => 'session_timer_controls', 'type' => 'message', 'message' => 'Timer UI loads here (via JS)', 'esc_html' => 0 ],
                ],
            ],
        ],
    ] );

    // ------------------------------------------------------------------
    // Group: Project Fields (attached to Project taxonomy terms)
    // Key must be 'group_ptt_project_fields' for tests
    // ------------------------------------------------------------------
    acf_add_local_field_group( [
        'key' => 'group_ptt_project_fields',
        'title' => 'PTT – Project Fields',
        'location' => [ [ [ 'param' => 'taxonomy', 'operator' => '==', 'value' => 'project' ] ] ],
        'fields' => [
            [ 'key' => 'field_ptt_project_max_budget', 'label' => 'Project Max Budget (hrs)', 'name' => 'project_max_budget', 'type' => 'number', 'min' => 0, 'step' => '0.01' ],
            [ 'key' => 'field_ptt_project_deadline', 'label' => 'Project Deadline', 'name' => 'project_deadline', 'type' => 'date_time_picker', 'display_format' => 'Y-m-d H:i:s', 'return_format' => 'Y-m-d H:i:s' ],
        ],
    ] );
}

add_action( 'acf/init', __NAMESPACE__ . '\\ptt_register_local_acf_groups' );

