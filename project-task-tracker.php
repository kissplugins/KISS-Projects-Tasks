<?php
/**
 * Plugin Name:       KISS - Project & Task Time Tracker
 * Plugin URI:        https://kissplugins.com
 * Description:       A robust Finite State Machine (FSM) centric system for WordPress users to track time spent on client projects and individual tasks. Requires ACF Pro.
 * Version:           2.2.47
 * Author:            KISS Plugins
 * Author URI:        https://kissplugins.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ptt
 * Domain Path:       /languages
 */

/**
 * ========================================================================
 * AI / LLM AGENT INSTRUCTIONS
 * ========================================================================
 * Architectural Pattern: Finite State Machine (FSM)
 *
 * This plugin uses an FSM to manage all core business logic and ensure
 * data integrity. Do not bypass the FSM.
 *
 * -   To change an object's state, apply a transition.
 * -   To perform a CRUD action, tie it to a transition's side-effect.
 *
 * Your primary task is to read, understand, and extend the existing
 * FSM definition when adding or modifying functionality.
 * ========================================================================
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'PTT_VERSION', '2.2.41' );
define( 'PTT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PTT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require __DIR__ . '/vendor/autoload.php';

KISS\PTT\Plugin::init();
