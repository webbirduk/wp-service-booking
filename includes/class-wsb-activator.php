<?php
/**
 * Fired during plugin activation
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Wp_Service_Booking
 * @subpackage Wp_Service_Booking/includes
 */

class Wsb_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Custom tables
		$table_bookings = $wpdb->prefix . 'wsb_bookings';
		$table_services = $wpdb->prefix . 'wsb_services';
		$table_staff = $wpdb->prefix . 'wsb_staff';
		$table_payments = $wpdb->prefix . 'wsb_payments';
		$table_customers = $wpdb->prefix . 'wsb_customers';

		$sql_customers = "CREATE TABLE IF NOT EXISTS $table_customers (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) DEFAULT NULL,
			first_name varchar(100) NOT NULL,
			last_name varchar(100) NOT NULL,
			email varchar(100) NOT NULL,
			phone varchar(50) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql_services = "CREATE TABLE IF NOT EXISTS $table_services (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			description text,
			category varchar(100) DEFAULT NULL,
			price decimal(10,2) NOT NULL DEFAULT '0.00',
			duration int(11) NOT NULL DEFAULT '30', -- in minutes
			buffer_time int(11) NOT NULL DEFAULT '0', -- in minutes
			capacity int(11) NOT NULL DEFAULT '1',
			status varchar(50) NOT NULL DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql_staff = "CREATE TABLE IF NOT EXISTS $table_staff (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) DEFAULT NULL,
			name varchar(255) NOT NULL,
			email varchar(100) NOT NULL,
			phone varchar(50) DEFAULT NULL,
			description text,
			status varchar(50) NOT NULL DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$table_staff_services = $wpdb->prefix . 'wsb_staff_services';
		$sql_staff_services = "CREATE TABLE IF NOT EXISTS $table_staff_services (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			staff_id bigint(20) NOT NULL,
			service_id bigint(20) NOT NULL,
			custom_price decimal(10,2) DEFAULT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql_bookings = "CREATE TABLE IF NOT EXISTS $table_bookings (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) NOT NULL,
			service_id bigint(20) NOT NULL,
			staff_id bigint(20) NOT NULL,
			booking_date date NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending', -- pending, confirmed, cancelled, completed
			total_amount decimal(10,2) NOT NULL DEFAULT '0.00',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";

		$sql_payments = "CREATE TABLE IF NOT EXISTS $table_payments (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) NOT NULL,
			amount decimal(10,2) NOT NULL,
			gateway varchar(50) NOT NULL, -- stripe, paypal, woocommerce, manual
			transaction_id varchar(255) DEFAULT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending', -- pending, completed, failed, refunded
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_customers );
		dbDelta( $sql_services );
		dbDelta( $sql_staff );
		dbDelta( $sql_staff_services );
		dbDelta( $sql_bookings );
		dbDelta( $sql_payments );
	}

}
