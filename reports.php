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
 * @return string Formatted and truncated content.
 */
function ptt_format_task_notes( $content, $max_length = 200 ) {
    // Escape HTML entities
    $content = esc_html( $content );
    
    // Pattern to match URLs anywhere in the text
    // This pattern matches http://, https://, and common URL formats
    $url_pattern = '/(https?:\/\/[^\s<>"{}|\^`\[\]]+)/i';
    
    // Replace URLs with clickable links
    $formatted_content = preg_replace_callback( $url_pattern, function( $matches ) {
        $url = $matches[1];
        // For display, truncate very long URLs in the link text
        $display_url = strlen( $url ) > 50 ? substr( $url, 0, 47 ) . '...' : $url;
        return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $display_url . '</a>';
    }, $content );
    
    // Truncate if longer than max length
    if ( strlen( strip_tags( $formatted_content ) ) > $max_length ) {
        $truncated = substr( strip_tags( $formatted_content ), 0, $max_length - 3 ) . '...';
        return esc_html( $truncated );
    }
    
    return $formatted_content;
}

/**
 * Add Reports page to admin menu.
 */
function ptt_add_reports_page() {
    add_menu_page(
        'Reports',                         // Page title
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
    ?>
    <div class="wrap">
        <h1>Project &amp; Task Time Reports</h1>

        <form method="get">
            <input type="hidden" name="page" value="ptt-reports" />
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="user">User</label></th>
                    <td>
                        <?php
                        wp_dropdown_users( [
                            'show_option_all' => 'All Users',
                            'name'            => 'user',
                            'selected'        => isset( $_GET['user'] ) ? intval( $_GET['user'] ) : 0,
                        ] );
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="client">Client</label></th>
                    <td>
                        <?php
                        $clients = get_terms( [
                            'taxonomy'   => 'client',
                            'hide_empty' => false,
                        ] );
                        echo '<select name="client"><option value="0">All Clients</option>';
                        foreach ( $clients as $client ) {
                            $selected = ( isset( $_GET['client'] ) && intval( $_GET['client'] ) === $client->term_id ) ? 'selected' : '';
                            echo '<option value="' . esc_attr( $client->term_id ) . '" ' . $selected . '>' . esc_html( $client->name ) . '</option>';
                        }
                        echo '</select>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="project">Project</label></th>
                    <td>
                        <?php
                        $projects = get_terms( [
                            'taxonomy'   => 'project',
                            'hide_empty' => false,
                        ] );
                        echo '<select name="project"><option value="0">All Projects</option>';
                        foreach ( $projects as $project ) {
                            $selected = ( isset( $_GET['project'] ) && intval( $_GET['project'] ) === $project->term_id ) ? 'selected' : '';
                            echo '<option value="' . esc_attr( $project->term_id ) . '" ' . $selected . '>' . esc_html( $project->name ) . '</option>';
                        }
                        echo '</select>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="start_date">Start Date</label></th>
                    <td><input type="date" name="start_date" value="<?php echo esc_attr( isset( $_GET['start_date'] ) ? $_GET['start_date'] : '' ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="end_date">End Date</label></th>
                    <td><input type="date" name="end_date" value="<?php echo esc_attr( isset( $_GET['end_date'] ) ? $_GET['end_date'] : '' ); ?>" /></td>
                </tr>
            </table>
            <?php submit_button( 'Run Report' ); ?>
        </form>
    <?php

    // Build query args based on filters
    $args = [
        'post_type'      => 'task',
        'posts_per_page' => -1,
        'date_query'     => [],
        'tax_query'      => [],
    ];

    $user_id   = isset( $_GET['user'] ) ? intval( $_GET['user'] ) : 0;
    $client_id = isset( $_GET['client'] ) ? intval( $_GET['client'] ) : 0;
    $project_id = isset( $_GET['project'] ) ? intval( $_GET['project'] ) : 0;

    if ( $user_id > 0 ) {
        $args['author'] = $user_id;
    }

    if ( ! empty( $_GET['start_date'] ) ) {
        $args['date_query'][] = [
            'after'     => sanitize_text_field( $_GET['start_date'] ),
            'inclusive' => true,
        ];
    }

    if ( ! empty( $_GET['end_date'] ) ) {
        $args['date_query'][] = [
            'before'    => sanitize_text_field( $_GET['end_date'] ),
            'inclusive' => true,
        ];
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

    // Fetch tasks
    $query = new WP_Query( $args );
    if ( ! $query->have_posts() ) {
        echo '<p>No tasks found for the selected criteria.</p>';
        return;
    }

    // Process and group results
    $report_data = [];
    $grand_total = 0.00;

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id      = get_the_ID();
        $author_id    = get_the_author_meta( 'ID' );
        $author_name  = get_the_author_meta( 'display_name' );

        $clients       = get_the_terms( $post_id, 'client' );
        $client_name   = ! is_wp_error( $clients ) && ! empty( $clients ) ? $clients[0]->name : 'Uncategorized';
        $client_id_term = ! is_wp_error( $clients ) && ! empty( $clients ) ? $clients[0]->term_id : 0;

        $projects       = get_the_terms( $post_id, 'project' );
        $project_name   = ! is_wp_error( $projects ) && ! empty( $projects ) ? $projects[0]->name : 'Uncategorized';
        $project_id_term = ! is_wp_error( $projects ) && ! empty( $projects ) ? $projects[0]->term_id : 0;

        // Determine duration, using manual override if enabled
        $manual_override = get_field( 'manual_override', $post_id );
        if ( $manual_override ) {
            $duration = (float) get_field( 'manual_duration', $post_id );
        } else {
            $duration = (float) get_field( 'calculated_duration', $post_id );
        }

        // Get task budget
        $task_budget = get_field( 'task_max_budget', $post_id );

        // Get project budget (from taxonomy)
        $project_budget = 0;
        if ( $project_id_term ) {
            $project_budget = get_field( 'project_max_budget', 'project_' . $project_id_term );
        }

        if ( ! isset( $report_data[ $author_id ] ) ) {
            $report_data[ $author_id ] = [
                'name'    => $author_name,
                'clients' => [],
                'total'   => 0,
            ];
        }

        if ( ! isset( $report_data[ $author_id ]['clients'][ $client_id_term ] ) ) {
            $report_data[ $author_id ]['clients'][ $client_id_term ] = [
                'name'     => $client_name,
                'projects' => [],
                'total'    => 0,
            ];
        }

        if ( ! isset( $report_data[ $author_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ] ) ) {
            $report_data[ $author_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ] = [
                'name'  => $project_name,
                'tasks' => [],
                'total' => 0,
            ];
        }

        $grand_total += $duration;
        $report_data[ $author_id ]['total'] += $duration;
        $report_data[ $author_id ]['clients'][ $client_id_term ]['total'] += $duration;
        $report_data[ $author_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ]['total'] += $duration;
        $report_data[ $author_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ]['tasks'][] = [
            'id'              => $post_id,
            'title'           => get_the_title(),
            'date'            => get_field( 'start_time', $post_id ),
            'duration'        => $duration,
            'task_budget'     => $task_budget,
            'project_budget'  => $project_budget,
            'content'         => get_the_content(),
        ];
    }
    wp_reset_postdata();

    // Output report HTML
    echo '<div class="ptt-report-results">';
    foreach ( $report_data as $author ) {
        echo '<h3>User: ' . esc_html( $author['name'] ) . ' <span class="total">(' . number_format( $author['total'], 2 ) . ' hrs)</span></h3>';

        foreach ( $author['clients'] as $client ) {
            echo '<div class="client-group">';
            echo '<h4>Client: ' . esc_html( $client['name'] ) . ' <span class="total">(' . number_format( $client['total'], 2 ) . ' hrs)</span></h4>';

            foreach ( $client['projects'] as $project ) {
                echo '<div class="project-group">';
                echo '<h5>Project: ' . esc_html( $project['name'] ) . ' <span class="total">(' . number_format( $project['total'], 2 ) . ' hrs)</span></h5>';
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>Task Name</th><th>Date</th><th>Duration (Hours)</th><th>Orig. Budget</th><th>Notes</th></tr></thead>';
                echo '<tbody>';

                foreach ( $project['tasks'] as $task ) {
                    // Format budget display
                    $budget_display = '';
                    if ( ! empty( $task['task_budget'] ) && $task['task_budget'] > 0 ) {
                        $budget_display = number_format( (float) $task['task_budget'], 2 ) . ' (Task)';
                    } elseif ( ! empty( $task['project_budget'] ) && $task['project_budget'] > 0 ) {
                        $budget_display = number_format( (float) $task['project_budget'], 2 ) . ' (Project)';
                    } else {
                        $budget_display = '-';
                    }

                    echo '<tr>';
                    echo '<td><a href="' . get_edit_post_link( $task['id'] ) . '">' . esc_html( $task['title'] ) . '</a></td>';
                    echo '<td>' . esc_html( date( 'Y-m-d', strtotime( $task['date'] ) ) ) . '</td>';
                    echo '<td>' . number_format( $task['duration'], 2 ) . '</td>';
                    echo '<td>' . $budget_display . '</td>';
                    echo '<td>' . ptt_format_task_notes( $task['content'] ) . '</td>';
                    echo '</tr>';
                }

                echo '</tbody></table>';
                echo '</div>'; // .project-group
            }

            echo '</div>'; // .client-group
        }
    }

    echo '<div class="grand-total"><strong>Grand Total: ' . number_format( $grand_total, 2 ) . ' hours</strong></div>';
    echo '</div>'; // .ptt-report-results
}