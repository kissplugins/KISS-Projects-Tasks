<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =================================================================
// 10.0 ADMIN PAGES & LINKS
// =================================================================

/**
 * Helper function to process and format task notes for display in reports.
 * 
 * @param string $content The post content to process.
 * @param int $max_length Maximum length before truncation (default: 200).
 * @return string Formatted content with URLs converted to links and truncation.
 */
function ptt_format_task_notes( $content, $max_length = 200 ) {
    // Strip all HTML tags first
    $content = wp_strip_all_tags( $content );
    
    // Trim whitespace
    $content = trim( $content );
    
    if ( empty( $content ) ) {
        return '';
    }
    
    // First, truncate if needed
    $truncated = false;
    if ( strlen( $content ) > $max_length ) {
        $content = substr( $content, 0, $max_length - 3 );
        $truncated = true;
    }
    
    // Escape HTML entities
    $content = esc_html( $content );
    
    // Pattern to match URLs anywhere in the text
    // This pattern matches http://, https://, and common URL formats
    $url_pattern = '/(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/i';
    
    // Replace URLs with clickable links
    $formatted_content = preg_replace_callback( $url_pattern, function( $matches ) {
        $url = $matches[1];
        // For display, truncate very long URLs in the link text
        $display_url = strlen( $url ) > 50 ? substr( $url, 0, 47 ) . '...' : $url;
        return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $display_url . '</a>';
    }, $content );
    
    // Add ellipses if content was truncated
    if ( $truncated ) {
        $formatted_content .= '...';
    }
    
    return $formatted_content;
}

/**
 * Adds the "Reports" page under the "Tasks" menu.
 */
function ptt_add_reports_page() {
    add_submenu_page(
        'edit.php?post_type=project_task', // Parent slug
        'Time Reports',                    // Page title
        'Reports',                         // Menu title
        'manage_options',                  // Capability
        'ptt-reports',                     // Menu slug
        'ptt_reports_page_html'            // Function
    );
}
add_action( 'admin_menu', 'ptt_add_reports_page' );

/**
 * Renders the HTML for the reports page.
 */
function ptt_reports_page_html() {
    // Check if we should use URL parameters or POST data
    $use_get = false;
    $params = [];
    
    // If we have GET parameters, use them
    if ( isset($_GET['user_id']) || isset($_GET['client_id']) || isset($_GET['project_id']) || isset($_GET['start_date']) || isset($_GET['end_date']) ) {
        $use_get = true;
        $params['user_id'] = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        $params['client_id'] = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
        $params['project_id'] = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
        $params['start_date'] = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $params['end_date'] = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    } elseif ( isset($_POST['run_report']) ) {
        // Use POST data if form was submitted
        $params['user_id'] = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $params['client_id'] = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $params['project_id'] = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $params['start_date'] = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $params['end_date'] = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
    } else {
        // Default empty params
        $params = [
            'user_id' => 0,
            'client_id' => 0,
            'project_id' => 0,
            'start_date' => '',
            'end_date' => ''
        ];
    }
    
    ?>
    <div class="wrap">
        <h1>Project & Task Time Reports</h1>
        
        <form method="post" action="" id="ptt-reports-form">
            <?php wp_nonce_field( 'ptt_run_report_nonce' ); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="user_id">Select User</label></th>
                        <td>
                            <?php wp_dropdown_users( [
                                'name' => 'user_id',
                                'show_option_all' => 'All Users',
                                'selected' => $params['user_id']
                            ] ); ?>
                        </td>
                    </tr>
                     <tr>
                        <th scope="row"><label for="client_id">Select Client</label></th>
                        <td>
                            <?php wp_dropdown_categories( [
                                'taxonomy'        => 'client',
                                'name'            => 'client_id',
                                'show_option_all' => 'All Clients',
                                'hide_empty'      => false,
                                'selected'        => $params['client_id'],
                                'hierarchical'    => true,
                                'class'           => '' // Reset class to avoid conflict
                            ] ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="project_id">Select Project</label></th>
                        <td>
                             <?php wp_dropdown_categories( [
                                'taxonomy'        => 'project',
                                'name'            => 'project_id',
                                'show_option_all' => 'All Projects',
                                'hide_empty'      => false,
                                'selected'        => $params['project_id'],
                                'hierarchical'    => true,
                                'class'           => '' // Reset class to avoid conflict
                            ] ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="start_date">Date Range</label></th>
                        <td>
                            <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($params['start_date']); ?>">
                            to
                            <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($params['end_date']); ?>">
                             <button type="button" id="set-this-week" class="button">This Week (Sun-Sat)</button>
                             <button type="button" id="set-last-week" class="button">Last Week</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="run_report" class="button button-primary" value="Run Report">
                <?php if ($use_get || isset($_POST['run_report'])) : ?>
                    <button type="button" id="ptt-copy-report-url" class="button">Copy Report URL</button>
                    <span id="ptt-url-copied" style="display: none; color: green; margin-left: 10px;">URL copied to clipboard!</span>
                <?php endif; ?>
            </p>
        </form>

        <?php
        // Display report if we have parameters from GET or POST
        if ( $use_get || isset($_POST['run_report']) ) {
            if ( isset($_POST['run_report']) ) {
                check_admin_referer( 'ptt_run_report_nonce' );
            }
            // Store params temporarily for the display function
            $_POST = array_merge($_POST, $params);
            ptt_display_report_results();
        }
        ?>
    </div>
    <?php
}

/**
 * Queries and displays the report results.
 */
function ptt_display_report_results() {
    $user_id    = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
    $client_id  = isset( $_POST['client_id'] ) ? intval( $_POST['client_id'] ) : 0;
    $project_id = isset( $_POST['project_id'] ) ? intval( $_POST['project_id'] ) : 0;
    $start_date = isset( $_POST['start_date'] ) && $_POST['start_date'] ? sanitize_text_field( $_POST['start_date'] ) : null;
    $end_date   = isset( $_POST['end_date'] ) && $_POST['end_date'] ? sanitize_text_field( $_POST['end_date'] ) : null;

    $args = [
        'post_type'      => 'project_task',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'author',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'AND',
            [ // Only include tasks that have been completed
                'key'     => 'stop_time',
                'compare' => 'EXISTS'
            ],
            [
                'key'     => 'stop_time',
                'value'   => '',
                'compare' => '!='
            ]
        ]
    ];
    
    if ( $user_id ) {
        $args['author'] = $user_id;
    }

    // Add taxonomy query
    $tax_query = ['relation' => 'AND'];
    if ( $client_id > 0 ) {
        $tax_query[] = [
            'taxonomy' => 'client',
            'field'    => 'term_id',
            'terms'    => $client_id,
        ];
    }
    if ( $project_id > 0 ) {
        $tax_query[] = [
            'taxonomy' => 'project',
            'field'    => 'term_id',
            'terms'    => $project_id,
        ];
    }
    if ( count( $tax_query ) > 1 ) {
        $args['tax_query'] = $tax_query;
    }

    if ( $start_date && $end_date ) {
        $args['meta_query'][] = [
            'key'     => 'start_time',
            'value'   => [ $start_date . ' 00:00:00', $end_date . ' 23:59:59' ],
            'compare' => 'BETWEEN',
            'type'    => 'DATETIME'
        ];
    }

    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        echo '<p>No completed tasks found for the selected criteria.</p>';
        return;
    }

    // Process and group results
    $report_data = [];
    $grand_total = 0.00;

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id      = get_the_ID();
        $author_id    = get_the_author_meta('ID');
        $author_name  = get_the_author_meta('display_name');

        $clients      = get_the_terms($post_id, 'client');
        $client_name  = !is_wp_error($clients) && !empty($clients) ? $clients[0]->name : 'Uncategorized';
        $client_id_term = !is_wp_error($clients) && !empty($clients) ? $clients[0]->term_id : 0;
        
        $projects     = get_the_terms($post_id, 'project');
        $project_name = !is_wp_error($projects) && !empty($projects) ? $projects[0]->name : 'Uncategorized';
        $project_id_term = !is_wp_error($projects) && !empty($projects) ? $projects[0]->term_id : 0;
        
        $duration = (float) get_field('calculated_duration', $post_id);
        
        // Get task budget
        $task_budget = get_field('task_max_budget', $post_id);
        
        // Get project budget (from taxonomy)
        $project_budget = 0;
        if ($project_id_term) {
            $project_budget = get_field('project_max_budget', 'project_' . $project_id_term);
        }
        
        if (!isset($report_data[$author_id])) {
            $report_data[$author_id] = ['name' => $author_name, 'clients' => [], 'total' => 0];
        }

        if (!isset($report_data[$author_id]['clients'][$client_id_term])) {
            $report_data[$author_id]['clients'][$client_id_term] = ['name' => $client_name, 'projects' => [], 'total' => 0];
        }

        if (!isset($report_data[$author_id]['clients'][$client_id_term]['projects'][$project_id_term])) {
            $report_data[$author_id]['clients'][$client_id_term]['projects'][$project_id_term] = ['name' => $project_name, 'tasks' => [], 'total' => 0];
        }
        
        $grand_total += $duration;
        $report_data[$author_id]['total'] += $duration;
        $report_data[$author_id]['clients'][$client_id_term]['total'] += $duration;
        $report_data[$author_id]['clients'][$client_id_term]['projects'][$project_id_term]['total'] += $duration;
        $report_data[$author_id]['clients'][$client_id_term]['projects'][$project_id_term]['tasks'][] = [
            'id'       => $post_id,
            'title'    => get_the_title(),
            'date'     => get_field('start_time', $post_id),
            'duration' => $duration,
            'content'  => get_the_content(),
            'task_budget' => $task_budget,
            'project_budget' => $project_budget
        ];
    }
    wp_reset_postdata();

    // Display results
    echo '<h2>Report Results</h2>';
    echo '<div class="ptt-report-results">';
    
    foreach ($report_data as $author) {
        echo '<h3>User: ' . esc_html($author['name']) . ' <span class="subtotal">(User Total: ' . number_format($author['total'], 2) . ' hrs)</span></h3>';
       
        foreach ($author['clients'] as $client) {
            echo '<div class="client-group">';
            echo '<h4>Client: ' . esc_html($client['name']) . ' <span class="subtotal">(Client Total: ' . number_format($client['total'], 2) . ' hrs)</span></h4>';
            foreach ($client['projects'] as $project) {
                echo '<div class="project-group">';
                echo '<h5>Project: ' . esc_html($project['name']) . ' <span class="subtotal">(Project Total: ' . number_format($project['total'], 2) . ' hrs)</span></h5>';
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>Task Name</th><th>Date</th><th>Duration (Hours)</th><th>Orig. Budget</th><th>Notes</th></tr></thead>';
                echo '<tbody>';
                foreach ($project['tasks'] as $task) {
                    // Format budget display
                    $budget_display = '';
                    if (!empty($task['task_budget']) && $task['task_budget'] > 0) {
                        $budget_display = number_format((float)$task['task_budget'], 2) . ' (Task)';
                    } elseif (!empty($task['project_budget']) && $task['project_budget'] > 0) {
                        $budget_display = number_format((float)$task['project_budget'], 2) . ' (Project)';
                    } else {
                        $budget_display = '-';
                    }
                    
                    echo '<tr>';
                    echo '<td><a href="' . get_edit_post_link($task['id']) . '">' . esc_html($task['title']) . '</a></td>';
                    echo '<td>' . esc_html(date('Y-m-d', strtotime($task['date']))) . '</td>';
                    echo '<td>' . number_format($task['duration'], 2) . '</td>';
                    echo '<td>' . $budget_display . '</td>';
                    echo '<td>' . ptt_format_task_notes($task['content']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>'; // .project-group
            }
             echo '</div>'; // .client-group
        }
    }
    
    echo '<div class="grand-total"><strong>Grand Total: ' . number_format($grand_total, 2) . ' hours</strong></div>';
    echo '</div>'; // .ptt-report-results
}