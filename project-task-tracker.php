<?php
/**
 * Plugin Name:       KISS - Project & Task Time Tracker
 * Plugin URI:        https://kissplugins.com
 * Description:       A robust system for WordPress users to track time spent on client projects and individual tasks. Requires ACF Pro.
 * Version:           2.2.13
 * Author:            KISS Plugins
 * Author URI:        https://kissplugins.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ptt
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'PTT_VERSION', '2.2.13' );
define( 'PTT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PTT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the Plugin Update Checker
require_once PTT_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/kissplugins/KISS-Projects-Tasks',
    __FILE__,
    'ptt'
);
// Optional: Set the branch that contains the stable release.
$update_checker->setBranch( 'main' );

require __DIR__ . '/vendor/autoload.php';

KISS\PTT\Plugin::init();
