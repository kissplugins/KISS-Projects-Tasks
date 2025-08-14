<?php
namespace KISS\PTT;

use KISS\PTT\Time\Calculator;
use KISS\PTT\Integration\ACF\ACFAdapter;
use KISS\PTT\Domain\Session\SessionRepository;
use KISS\PTT\Domain\Timer\TimerService;
use KISS\PTT\Admin\Assets as AdminAssets;
use KISS\PTT\Admin\SelfTestController;

class Plugin {
    // Simple, low-risk: register services directly on Plugin
    public static ACFAdapter $acf;
    public static SessionRepository $sessions;
    public static TimerService $timer;


    public static function init() {
        // Instantiate services (low risk)
        self::$acf      = new ACFAdapter();
        self::$sessions = new SessionRepository( self::$acf );
        self::$timer    = new TimerService( self::$acf, self::$sessions );

        // Register admin assets and controllers
        AdminAssets::register();
        SelfTestController::register();

        self::register_hooks();
        // Load remaining procedural modules
        require_once PTT_PLUGIN_DIR . 'helpers.php';
        require_once PTT_PLUGIN_DIR . 'time-functions.php';
        // Register local ACF groups if ACF is active
        require_once PTT_PLUGIN_DIR . 'src/Integration/ACF/FieldGroups.php';
        require_once PTT_PLUGIN_DIR . 'shortcodes.php';
        require_once PTT_PLUGIN_DIR . 'self-test.php';
        require_once PTT_PLUGIN_DIR . 'reports.php';
        require_once PTT_PLUGIN_DIR . 'kanban.php';
        require_once PTT_PLUGIN_DIR . 'today.php';
        require_once PTT_PLUGIN_DIR . 'legacy-core.php';
    }

    protected static function register_hooks() {
        register_activation_hook( PTT_PLUGIN_DIR . 'project-task-tracker.php', [ __CLASS__, 'activate' ] );
        register_deactivation_hook( PTT_PLUGIN_DIR . 'project-task-tracker.php', [ __CLASS__, 'deactivate' ] );
        add_action( 'admin_notices', [ __CLASS__, 'check_dependencies' ] );
        add_action( 'init', [ __CLASS__, 'register_post_type' ] );
        add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );
        add_filter( 'post_type_link', [ __CLASS__, 'task_id_permalink' ], 10, 2 );
        add_action( 'acf/save_post', [ Calculator::class, 'recalculate_on_save' ], 20 );
    }

    public static function activate() {
        // Register CPT and Taxonomies to make them available.
        self::register_post_type();
        self::register_taxonomies();

        // Ensure default Task Status terms exist
        $default_statuses = [ 'Not Started', 'In Progress', 'Completed', 'Paused' ];
        foreach ( $default_statuses as $status ) {
            if ( ! term_exists( $status, 'task_status' ) ) {
                wp_insert_term( $status, 'task_status' );
            }
        }

        // Create a baseline test post.
        if ( ! get_page_by_title( 'Test First Task', OBJECT, 'project_task' ) ) {
            $post_data = [
                'post_title'   => 'Test First Task',
                'post_content' => 'This is a sample task created on plugin activation for testing purposes.',
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
                'post_type'    => 'project_task',
            ];
            wp_insert_post( $post_data );
        }

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function check_dependencies() {
        if ( ! class_exists( 'ACF' ) || ! function_exists( 'acf_get_field_groups' ) ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>Project & Task Time Tracker:</strong> This plugin requires Advanced Custom Fields (ACF) Pro to be installed and activated. Please install ACF Pro.</p>
            </div>
            <?php
        }
    }

    public static function register_post_type() {
        $labels = [
            'name'               => _x( 'Tasks', 'post type general name', 'ptt' ),
            'singular_name'      => _x( 'Task', 'post type singular name', 'ptt' ),
            'menu_name'          => _x( 'Tasks', 'admin menu', 'ptt' ),
            'name_admin_bar'     => _x( 'Task', 'add new on admin bar', 'ptt' ),
            'add_new'            => _x( 'Add New Task', 'book', 'ptt' ),
            'add_new_item'       => __( 'Add New Task', 'ptt' ),
            'new_item'           => __( 'New Task', 'ptt' ),
            'edit_item'          => __( 'Edit Task', 'ptt' ),
            'view_item'          => __( 'View Task', 'ptt' ),
            'all_items'          => __( 'All Tasks', 'ptt' ),
            'search_items'       => __( 'Search Tasks', 'ptt' ),
            'parent_item_colon'  => __( 'Parent Tasks:', 'ptt' ),
            'not_found'          => __( 'No tasks found.', 'ptt' ),
            'not_found_in_trash' => __( 'No tasks found in Trash.', 'ptt' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'project_task' ],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'menu_icon'          => 'dashicons-clock',
            'supports'           => [ 'title', 'editor', 'author', 'revisions' ],
            'taxonomies'         => [ 'client', 'project', 'task_status', 'post_tag' ],
        ];

        register_post_type( 'project_task', $args );
    }

    public static function register_taxonomies() {
        // Clients taxonomy
        $client_labels = [
            'name'              => _x( 'Clients', 'taxonomy general name', 'ptt' ),
            'singular_name'     => _x( 'Client', 'taxonomy singular name', 'ptt' ),
            'search_items'      => __( 'Search Clients', 'ptt' ),
            'all_items'         => __( 'All Clients', 'ptt' ),
            'parent_item'       => __( 'Parent Client', 'ptt' ),
            'parent_item_colon' => __( 'Parent Client:', 'ptt' ),
            'edit_item'         => __( 'Edit Client', 'ptt' ),
            'update_item'       => __( 'Update Client', 'ptt' ),
            'add_new_item'      => __( 'Add New Client', 'ptt' ),
            'new_item_name'     => __( 'New Client Name', 'ptt' ),
            'menu_name'         => __( 'Clients', 'ptt' ),
        ];
        $client_args = [
            'hierarchical'      => true,
            'labels'            => $client_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'client' ],
        ];
        register_taxonomy( 'client', [ 'project_task' ], $client_args );

        // Projects taxonomy
        $project_labels = [
            'name'              => _x( 'Projects', 'taxonomy general name', 'ptt' ),
            'singular_name'     => _x( 'Project', 'taxonomy singular name', 'ptt' ),
            'search_items'      => __( 'Search Projects', 'ptt' ),
            'all_items'         => __( 'All Projects', 'ptt' ),
            'parent_item'       => __( 'Parent Project', 'ptt' ),
            'parent_item_colon' => __( 'Parent Project:', 'ptt' ),
            'edit_item'         => __( 'Edit Project', 'ptt' ),
            'update_item'       => __( 'Update Project', 'ptt' ),
            'add_new_item'      => __( 'Add New Project', 'ptt' ),
            'new_item_name'     => __( 'New Project Name', 'ptt' ),
            'menu_name'         => __( 'Projects', 'ptt' ),
        ];
        $project_args = [
            'hierarchical'      => true,
            'labels'            => $project_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'project' ],
        ];
        register_taxonomy( 'project', [ 'project_task' ], $project_args );

        // Task Status taxonomy
        $status_labels = [
            'name'              => _x( 'Task Statuses', 'taxonomy general name', 'ptt' ),
            'singular_name'     => _x( 'Task Status', 'taxonomy singular name', 'ptt' ),
            'search_items'      => __( 'Search Task Statuses', 'ptt' ),
            'all_items'         => __( 'All Task Statuses', 'ptt' ),
            'parent_item'       => __( 'Parent Task Status', 'ptt' ),
            'parent_item_colon' => __( 'Parent Task Status:', 'ptt' ),
            'edit_item'         => __( 'Edit Task Status', 'ptt' ),
            'update_item'       => __( 'Update Task Status', 'ptt' ),
            'add_new_item'      => __( 'Add New Task Status', 'ptt' ),
            'new_item_name'     => __( 'New Task Status Name', 'ptt' ),
            'menu_name'         => __( 'Task Statuses', 'ptt' ),
        ];
        $status_args = [
            'hierarchical'      => true,
            'labels'            => $status_labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'task_status' ],
        ];
        register_taxonomy( 'task_status', [ 'project_task' ], $status_args );
    }

    public static function task_id_permalink( $post_link, $post ) {
        if ( 'project_task' === get_post_type( $post ) ) {
            return home_url( 'project_task/' . $post->post_name . '-' . $post->ID . '/' );
        }
        return $post_link;
    }
}
