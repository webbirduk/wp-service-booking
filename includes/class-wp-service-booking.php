<?php

class Wp_Service_Booking {

	protected $plugin_name;
	protected $version;

	public function __construct() {
		$this->plugin_name = 'wp-service-booking';
		$this->version = '1.0.0';
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
        $this->define_ajax_hooks();
	}

	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wsb-admin.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wsb-public.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wsb-ajax.php';
	}

	private function define_admin_hooks() {
		$plugin_admin = new Wsb_Admin( $this->get_plugin_name(), $this->get_version() );
		add_action( 'admin_menu', array( $plugin_admin, 'add_plugin_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );
	}

	private function define_public_hooks() {
		$plugin_public = new Wsb_Public( $this->get_plugin_name(), $this->get_version() );
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $plugin_public, 'enqueue_scripts' ) );
        add_shortcode( 'wsb_booking_widget', array( $plugin_public, 'render_booking_widget' ) );
        add_shortcode( 'wsb_services', array( $plugin_public, 'render_services_widget' ) );
        add_shortcode( 'wsb_client_dashboard', array( $plugin_public, 'render_client_dashboard' ) );
        add_action( 'template_redirect', array( $plugin_public, 'virtual_booking_route' ) );
        add_action( 'init', array( $plugin_public, 'handle_stripe_return' ) );
        add_filter( 'login_redirect', array( $plugin_public, 'wsb_login_redirect' ), 10, 3 );
        add_filter( 'logout_redirect', array( $plugin_public, 'wsb_logout_redirect' ), 10, 3 );
        add_action( 'admin_init', array( $plugin_public, 'wsb_restrict_admin_access' ) );
	}

    private function define_ajax_hooks() {
        $plugin_ajax = new Wsb_Ajax();
        add_action('wp_ajax_wsb_get_slots', array($plugin_ajax, 'get_time_slots'));
        add_action('wp_ajax_nopriv_wsb_get_slots', array($plugin_ajax, 'get_time_slots'));
        
        add_action('wp_ajax_wsb_create_booking', array($plugin_ajax, 'create_booking'));
        add_action('wp_ajax_nopriv_wsb_create_booking', array($plugin_ajax, 'create_booking'));
        
        add_action('wp_ajax_wsb_client_booking_action', array($plugin_ajax, 'wsb_client_booking_action'));
        add_action('wp_ajax_wsb_update_account_details', array($plugin_ajax, 'wsb_update_account_details'));
        add_action('wp_ajax_wsb_test_stripe_connection', array($plugin_ajax, 'test_stripe_connection'));
        
        add_action('wp_ajax_wsb_create_stripe_intent', array($plugin_ajax, 'create_stripe_intent'));
        add_action('wp_ajax_nopriv_wsb_create_stripe_intent', array($plugin_ajax, 'create_stripe_intent'));
        
        add_action('wp_ajax_wsb_create_checkout_session', array($plugin_ajax, 'create_checkout_session'));
        add_action('wp_ajax_nopriv_wsb_create_checkout_session', array($plugin_ajax, 'create_checkout_session'));
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
