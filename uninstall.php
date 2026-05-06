<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://webbird.co.uk
 * @since      1.0.0
 *
 * @package    Boocommerce
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// List of tables to drop
$tables = [
    'bc_bookings',
    'bc_services',
    'bc_staff',
    'bc_staff_services',
    'bc_payments',
    'bc_customers'
];

foreach ( $tables as $table ) {
    $table_name = $wpdb->prefix . $table;
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
}

// Delete all bc_ options
$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE 'bc_%'" );
