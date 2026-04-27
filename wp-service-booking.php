<?php
/**
 * Plugin Name:       WordPress Service Booking (Advanced)
 * Plugin URI:        https://example.com/wp-service-booking
 * Description:       A complete appointment and service booking system with staff management, payments, and analytics.
 * Version:           1.0.0
 * Author:            Antigravity
 * Text Domain:       wp-service-booking
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'WSB_VERSION', '1.0.0' );
define( 'WSB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wsb-activator.php
 */
function activate_wp_service_booking() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wsb-activator.php';
	Wsb_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wsb-deactivator.php
 */
function deactivate_wp_service_booking() {
	// require_once plugin_dir_path( __FILE__ ) . 'includes/class-wsb-deactivator.php';
	// Wsb_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wp_service_booking' );
register_deactivation_hook( __FILE__, 'deactivate_wp_service_booking' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wp-service-booking.php';

/**
 * Begins execution of the plugin.
 */
function run_wp_service_booking() {
	$plugin = new Wp_Service_Booking();
	$plugin->run();
}
run_wp_service_booking();
