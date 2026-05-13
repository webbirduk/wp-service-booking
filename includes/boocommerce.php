<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Boocommerce {

	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->plugin_name = 'boocommerce';
		$this->version = '1.0.0';
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
        $this->define_ajax_hooks();
	}

	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/bc-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/bc-public.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/bc-ajax.php';
	}

	private function define_admin_hooks() {
		$plugin_admin = new Bc_Admin( $this->get_plugin_name(), $this->get_version() );
		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );
	}

	private function define_public_hooks() {
		$plugin_public = new Bc_Public( $this->get_plugin_name(), $this->get_version() );
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_scripts' ) );
        add_shortcode( 'bc_booking_widget', array( $plugin_public, 'render_booking_widget' ) );
        add_shortcode( 'bc_services', array( $plugin_public, 'render_services_widget' ) );
        add_shortcode( 'bc_client_dashboard', array( $plugin_public, 'render_client_dashboard' ) );
        add_shortcode( 'bc_basket', array( $plugin_public, 'render_basket_shortcode' ) );
        add_shortcode( 'bc_booking_page', array( $plugin_public, 'render_booking_page_shortcode' ) );
        add_shortcode( 'bc_dashboard_page', array( $plugin_public, 'render_dashboard_page_shortcode' ) );
        add_shortcode( 'bc_login_page', array( $plugin_public, 'render_login_page_shortcode' ) );
        add_action( 'init', array( $plugin_public, 'handle_stripe_return' ) );
        add_filter( 'login_redirect', array( $plugin_public, 'bc_login_redirect' ), 10, 3 );
        add_filter( 'logout_redirect', array( $plugin_public, 'bc_logout_redirect' ), 10, 3 );
        add_action( 'admin_init', array( $plugin_public, 'bc_restrict_admin_access' ) );
        add_filter( 'wp_nav_menu_items', array( $plugin_public, 'add_basket_to_menu' ), 10, 2 );
        add_action( 'wp_footer', array( $plugin_public, 'render_floating_booking_btn' ) );
        add_filter( 'template_include', array( $plugin_public, 'bc_override_login_template' ) );
        add_action( 'wp_login_failed', array( $plugin_public, 'bc_handle_login_failed' ) );
        add_filter( 'show_admin_bar', array( $plugin_public, 'bc_hide_admin_bar_for_subscribers' ) );
    }

    private function define_ajax_hooks() {
        $plugin_ajax = new Bc_Ajax();
        add_action('wp_ajax_bc_get_slots', array($plugin_ajax, 'get_time_slots'));
        add_action('wp_ajax_nopriv_bc_get_slots', array($plugin_ajax, 'get_time_slots'));
        
        add_action('wp_ajax_bc_create_booking', array($plugin_ajax, 'create_booking'));
        add_action('wp_ajax_nopriv_bc_create_booking', array($plugin_ajax, 'create_booking'));
        
        add_action('wp_ajax_bc_client_reschedule', array($plugin_ajax, 'client_reschedule'));
        add_action('wp_ajax_bc_client_cancel_request', array($plugin_ajax, 'client_cancel_request'));
        add_action('wp_ajax_bc_update_client_profile', array($plugin_ajax, 'update_client_profile'));
        
        add_action('wp_ajax_bc_test_stripe_connection', array($plugin_ajax, 'test_stripe_connection'));
        
        add_action('wp_ajax_bc_create_stripe_intent', array($plugin_ajax, 'create_stripe_intent'));
        add_action('wp_ajax_nopriv_bc_create_stripe_intent', array($plugin_ajax, 'create_stripe_intent'));
        
        add_action('wp_ajax_bc_create_checkout_session', array($plugin_ajax, 'create_checkout_session'));
        add_action('wp_ajax_nopriv_bc_create_checkout_session', array($plugin_ajax, 'create_checkout_session'));
    }

	public function run() {
		// Hook system is initialized via constructor, no further execution needed here right now
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_version() {
		return $this->version;
	}

}
