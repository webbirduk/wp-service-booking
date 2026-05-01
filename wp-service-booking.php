<?php
/**
 * Plugin Name:       WordPress Service Booking (Advanced)
 * Plugin URI:        https://example.com/wp-service-booking
 * Description:       A complete appointment and service booking system with staff management, payments, and analytics.
 * Version:           1.0.0
 * Author:            Antigravity
 * Text Domain:       wp-service-booking
 * Domain Path:       /languages
 * 
 * --- DEVELOPER SCALABILITY ENGINE ---
 * This plugin is designed to be fully extensible via WordPress Actions & Filters.
 * 
 * CORE FILTERS:
 * - wsb_admin_tabs: Add or override administrative modules.
 * - wsb_admin_nav_items: Customize the sidebar navigation menu.
 * - wsb_admin_bookings_query: Modify the booking list database query.
 * - wsb_admin_bookings_results: Post-process booking data objects.
 * 
 * CORE ACTIONS:
 * - wsb_admin_tab_render_{tab}: Render custom tab content.
 * - wsb_admin_settings_payment_gateways: Inject custom payment gateway settings.
 * - wsb_before_save_settings: Hook into the start of the settings save cycle.
 * - wsb_after_save_settings: Hook into the completion of the settings save cycle.
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

function wsb_get_currency_symbol($currency = 'USD') {
    $symbols = array(
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'INR' => '₹'
    );
    return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
}

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
