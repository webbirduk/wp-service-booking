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
 * Sends a modern, responsive HTML email
 */
function wsb_send_modern_email($to, $subject, $title, $intro, $content_html) {
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $blog_name = get_bloginfo('name');
    
    $body = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap");
            
            body { 
                font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
                line-height: 1.6; 
                color: #334155; 
                margin: 0; 
                padding: 0; 
                background-color: #f8fafc; 
            }
            
            .email-container { 
                max-width: 600px; 
                margin: 40px auto; 
                background: #ffffff; 
                border-radius: 32px; 
                overflow: hidden; 
                box-shadow: 0 20px 50px rgba(15, 23, 42, 0.05); 
                border: 1px solid #e2e8f0; 
            }
            
            .email-header { 
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
                padding: 50px 40px; 
                text-align: center; 
                color: #ffffff;
                position: relative;
            }
            
            .email-header::after {
                content: "";
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, #6366f1, #a855f7);
            }
            
            .email-header h1 { 
                margin: 0; 
                font-size: 32px; 
                font-weight: 800; 
                letter-spacing: -0.04em; 
                line-height: 1.1;
            }
            
            .email-body { 
                padding: 45px 40px; 
            }
            
            .email-intro { 
                font-size: 20px; 
                color: #0f172a; 
                margin-bottom: 30px; 
                font-weight: 700; 
                letter-spacing: -0.02em;
                line-height: 1.4;
            }
            
            .email-content-box {
                margin: 25px 0;
            }
            
            .email-footer { 
                padding: 40px; 
                background: #f1f5f9; 
                text-align: center; 
                font-size: 14px; 
                color: #64748b; 
                border-top: 1px solid #e2e8f0; 
            }
            
            .email-brand-logo {
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.2em;
                font-weight: 800;
                color: rgba(255, 255, 255, 0.4);
                margin-bottom: 15px;
                display: block;
            }

            .info-card {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 20px;
                padding: 25px;
                margin: 20px 0;
            }

            .btn-primary {
                display: inline-block;
                padding: 16px 35px;
                background: #6366f1;
                color: #ffffff !important;
                text-decoration: none;
                border-radius: 16px;
                font-weight: 700;
                font-size: 15px;
                box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <span class="email-brand-logo">' . esc_html($blog_name) . '</span>
                <h1>' . esc_html($title) . '</h1>
            </div>
            <div class="email-body">
                <div class="email-intro">' . esc_html($intro) . '</div>
                <div class="email-content-box">
                    ' . $content_html . '
                </div>
                <p style="margin-top:40px; font-size:14px; color:#94a3b8; font-weight:500;">Sent via the ' . esc_html($blog_name) . ' secure scheduling engine.</p>
            </div>
            <div class="email-footer">
                &copy; ' . date('Y') . ' ' . esc_html($blog_name) . '. All rights reserved.
                <div style="margin-top:10px;">Security Verified &bullet; GDPR Compliant</div>
            </div>
        </div>
    </body>
    </html>';

    wp_mail($to, $subject, $body, $headers);
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
