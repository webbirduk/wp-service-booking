<?php
class Wsb_Admin
{
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        $this->load_dependencies();

        // Register AJAX tab loader
        add_action('wp_ajax_wsb_load_admin_tab', array($this, 'ajax_load_tab'));
    }

    private function load_dependencies()
    {
        $dir = plugin_dir_path(__FILE__) . 'includes/';
        require_once $dir . 'class-wsb-admin-bookings.php';
        require_once $dir . 'class-wsb-admin-services.php';
        require_once $dir . 'class-wsb-admin-staff.php';
        require_once $dir . 'class-wsb-admin-customers.php';
        require_once $dir . 'class-wsb-admin-finance.php';
        require_once $dir . 'class-wsb-admin-settings.php';
        require_once $dir . 'class-wsb-admin-design.php';
        require_once $dir . 'class-wsb-admin-dashboard.php';
    }

    public function ajax_load_tab()
    {
        check_ajax_referer('wsb_admin_nonce', 'nonce');
        $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'dashboard';

        // Merge POST data into GET/REQUEST so that filter logic works during AJAX SPA updates
        $_GET = array_merge($_GET, $_POST);
        $_REQUEST = array_merge($_REQUEST, $_POST);

        if (!empty($_POST['params'])) {
            parse_str(ltrim($_POST['params'], '&'), $extra_params);
            $_GET = array_merge($_GET, $extra_params);
            $_REQUEST = array_merge($_REQUEST, $extra_params);
        }

        ob_start();
        switch ($tab) {
            case 'bookings':
                $module = new Wsb_Admin_Bookings($this);
                $module->display();
                break;
            case 'finance':
                $module = new Wsb_Admin_Finance($this);
                $module->display();
                break;
            case 'services':
                $module = new Wsb_Admin_Services($this);
                $module->display();
                break;
            case 'staff':
                $module = new Wsb_Admin_Staff($this);
                $module->display();
                break;
            case 'customers':
                $module = new Wsb_Admin_Customers($this);
                $module->display();
                break;
            case 'design':
                $module = new Wsb_Admin_Design($this);
                $module->display();
                break;
            case 'settings':
                $module = new Wsb_Admin_Settings($this);
                $module->display();
                break;
            default:
                $module = new Wsb_Admin_Dashboard($this);
                $module->display();
                break;
        }
        $content = ob_get_clean();
        wp_send_json_success(array('content' => $content));
    }

    public function enqueue_styles($hook)
    {
        if ('toplevel_page_wsb_main' !== $hook) {
            return;
        }
        wp_enqueue_style($this->plugin_name, plugin_dir_url(dirname(__FILE__)) . 'assets/admin/css/wsb-admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts($hook)
    {
        if ('toplevel_page_wsb_main' !== $hook) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script($this->plugin_name, plugin_dir_url(dirname(__FILE__)) . 'assets/admin/js/wsb-admin.js', array('jquery'), $this->version, false);
        wp_localize_script($this->plugin_name, 'wsb_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsb_admin_nonce')
        ));
    }

    public function add_plugin_admin_menu()
    {
        add_menu_page(
            'Service Booking',
            'Service Booking',
            'manage_options',
            'wsb_main',
            array($this, 'display_master_dashboard'),
            'dashicons-calendar-alt',
            26
        );
    }

    public function display_master_dashboard()
    {
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/wsb-admin-master-display.php';
    }

    public function wsb_notify_status_change($booking_id, $new_status)
    {
        global $wpdb;
        $booking_table = $wpdb->prefix . 'wsb_bookings';
        $customer_table = $wpdb->prefix . 'wsb_customers';
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, c.email, c.first_name, c.last_name 
             FROM $booking_table b 
             JOIN $customer_table c ON b.customer_id = c.id 
             WHERE b.id = %d", 
            $booking_id
        ));
        
        if ($booking && !empty($booking->email)) {
            $mail_subject = "Update: Booking #$booking_id Status Changed to " . ucfirst($new_status);
            $mail_body = "Hello " . esc_html($booking->first_name) . ",\n\n";
            $mail_body .= "We're writing to inform you that your booking status has been updated to: " . strtoupper($new_status) . ".\n\n";
            $mail_body .= "Review schedule timelines directly inside dashboards:\n";
            $mail_body .= esc_url(home_url('/booking-dashboard')) . "\n\n";
            $mail_body .= "Best Regards.";
            
            wp_mail($booking->email, $mail_subject, $mail_body);
        }
    }
}
