<?php
/**
 * Debug Post Save Issues
 * Temporary diagnostic tool to understand why posts aren't appearing in All Tasks list
 *
 * USAGE: Add this line to your theme's functions.php:
 * require_once '/path/to/debug-post-save.php';
 *
 * Then go to Tasks > Debug Post Issues in WordPress admin
 */

// Add this to wp-config.php temporarily: define('WP_DEBUG_LOG', true);

add_action('save_post', 'ptt_debug_post_save', 10, 3);
function ptt_debug_post_save($post_id, $post, $update) {
    if ($post->post_type !== 'project_task') {
        return;
    }
    
    error_log("PTT DEBUG: Post save triggered");
    error_log("PTT DEBUG: Post ID: " . $post_id);
    error_log("PTT DEBUG: Post Title: " . $post->post_title);
    error_log("PTT DEBUG: Post Status: " . $post->post_status);
    error_log("PTT DEBUG: Post Type: " . $post->post_type);
    error_log("PTT DEBUG: Is Update: " . ($update ? 'Yes' : 'No'));
    error_log("PTT DEBUG: Post Date: " . $post->post_date);
    error_log("PTT DEBUG: Post Modified: " . $post->post_modified);
    
    // Check if post appears in queries
    $query_args = [
        'post_type' => 'project_task',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ];
    
    $all_published_tasks = get_posts($query_args);
    error_log("PTT DEBUG: Total published tasks found: " . count($all_published_tasks));
    error_log("PTT DEBUG: This post in published list: " . (in_array($post_id, $all_published_tasks) ? 'Yes' : 'No'));
    
    // Check draft status
    $draft_args = [
        'post_type' => 'project_task',
        'post_status' => 'draft',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ];
    
    $all_draft_tasks = get_posts($draft_args);
    error_log("PTT DEBUG: Total draft tasks found: " . count($all_draft_tasks));
    error_log("PTT DEBUG: This post in draft list: " . (in_array($post_id, $all_draft_tasks) ? 'Yes' : 'No'));
    
    // Check auto-draft status
    $autodraft_args = [
        'post_type' => 'project_task',
        'post_status' => 'auto-draft',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ];
    
    $all_autodraft_tasks = get_posts($autodraft_args);
    error_log("PTT DEBUG: Total auto-draft tasks found: " . count($all_autodraft_tasks));
    error_log("PTT DEBUG: This post in auto-draft list: " . (in_array($post_id, $all_autodraft_tasks) ? 'Yes' : 'No'));
}

add_action('acf/save_post', 'ptt_debug_acf_save', 20);
function ptt_debug_acf_save($post_id) {
    if (get_post_type($post_id) !== 'project_task') {
        return;
    }
    
    error_log("PTT DEBUG: ACF save_post triggered for post ID: " . $post_id);
    
    $post = get_post($post_id);
    if ($post) {
        error_log("PTT DEBUG: Post status after ACF save: " . $post->post_status);
        error_log("PTT DEBUG: Post title after ACF save: " . $post->post_title);
    }
}

// Add admin page for debugging
add_action('admin_menu', 'ptt_debug_admin_menu');
function ptt_debug_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=project_task',
        'Debug Post Issues',
        'Debug Post Issues',
        'manage_options',
        'ptt-debug-posts',
        'ptt_debug_admin_page'
    );
}

function ptt_debug_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    echo '<div class="wrap">';
    echo '<h1>PTT Debug: Post Save Issues</h1>';

    // Test creating a post programmatically
    if (isset($_POST['test_create_post']) && wp_verify_nonce($_POST['_wpnonce'], 'ptt_debug_test')) {
        $test_post_id = wp_insert_post([
            'post_type' => 'project_task',
            'post_title' => 'Debug Test Post ' . date('Y-m-d H:i:s'),
            'post_content' => 'This is a test post created by the debug tool.',
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);

        if ($test_post_id && !is_wp_error($test_post_id)) {
            echo '<div class="notice notice-success"><p>✅ Successfully created test post ID: ' . $test_post_id . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>❌ Failed to create test post: ' . (is_wp_error($test_post_id) ? $test_post_id->get_error_message() : 'Unknown error') . '</p></div>';
        }
    }

    // Show current post counts
    $published_count = wp_count_posts('project_task')->publish ?? 0;
    $draft_count = wp_count_posts('project_task')->draft ?? 0;
    $autodraft_count = wp_count_posts('project_task')->{'auto-draft'} ?? 0;

    echo '<h2>Current Post Counts</h2>';
    echo '<ul>';
    echo '<li><strong>Published:</strong> ' . $published_count . '</li>';
    echo '<li><strong>Drafts:</strong> ' . $draft_count . '</li>';
    echo '<li><strong>Auto-drafts:</strong> ' . $autodraft_count . '</li>';
    echo '</ul>';

    // Show recent posts
    echo '<h2>Recent Posts (All Statuses)</h2>';
    $recent_posts = get_posts([
        'post_type' => 'project_task',
        'post_status' => ['publish', 'draft', 'auto-draft', 'private', 'pending'],
        'posts_per_page' => 10,
        'orderby' => 'modified',
        'order' => 'DESC'
    ]);

    if (empty($recent_posts)) {
        echo '<p>No project_task posts found.</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Title</th><th>Status</th><th>Author</th><th>Modified</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($recent_posts as $post) {
            echo '<tr>';
            echo '<td>' . $post->ID . '</td>';
            echo '<td>' . esc_html($post->post_title ?: '(no title)') . '</td>';
            echo '<td>' . $post->post_status . '</td>';
            echo '<td>' . get_userdata($post->post_author)->display_name ?? 'Unknown' . '</td>';
            echo '<td>' . $post->post_modified . '</td>';
            echo '<td><a href="' . get_edit_post_link($post->ID) . '">Edit</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    // Test form
    echo '<h2>Test Post Creation</h2>';
    echo '<form method="post">';
    wp_nonce_field('ptt_debug_test');
    echo '<p><input type="submit" name="test_create_post" class="button button-primary" value="Create Test Post" /></p>';
    echo '</form>';

    // User capabilities check
    echo '<h2>Current User Capabilities</h2>';
    $current_user = wp_get_current_user();
    echo '<ul>';
    echo '<li><strong>User ID:</strong> ' . $current_user->ID . '</li>';
    echo '<li><strong>Username:</strong> ' . $current_user->user_login . '</li>';
    echo '<li><strong>Can edit posts:</strong> ' . (current_user_can('edit_posts') ? 'Yes' : 'No') . '</li>';
    echo '<li><strong>Can publish posts:</strong> ' . (current_user_can('publish_posts') ? 'Yes' : 'No') . '</li>';
    echo '<li><strong>Can edit project_task:</strong> ' . (current_user_can('edit_post', 1) ? 'Yes' : 'No') . '</li>';
    echo '</ul>';

    echo '</div>';
}
