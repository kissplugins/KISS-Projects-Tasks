<?php
namespace KISS\PTT\Helpers;

class TaskHelper {
    public static function get_tasks_for_user( $user_id ) {
        global $wpdb;

        if ( ! $user_id ) {
            return [];
        }

        $assigned_posts_query = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'ptt_assignee' AND meta_value = %d",
            $user_id
        );
        $assigned_posts = $wpdb->get_col( $assigned_posts_query );

        $task_ids = array_map( 'intval', array_unique( $assigned_posts ) );

        return $task_ids;
    }
}
