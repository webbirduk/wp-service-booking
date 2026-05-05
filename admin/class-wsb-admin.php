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
        require_once $dir . 'class-wsb-admin-integrations.php';
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

        // --- DEVELOPER SCALABILITY ENGINE ---
        // Allow developers to register custom modules or override existing ones
        $allowed_tabs = apply_filters('wsb_admin_tabs', array(
            'dashboard' => array('class' => 'Wsb_Admin_Dashboard'),
            'bookings'  => array('class' => 'Wsb_Admin_Bookings'),
            'finance'   => array('class' => 'Wsb_Admin_Finance'),
            'services'  => array('class' => 'Wsb_Admin_Services'),
            'staff'     => array('class' => 'Wsb_Admin_Staff'),
            'customers' => array('class' => 'Wsb_Admin_Customers'),
            'design'    => array('class' => 'Wsb_Admin_Design'),
            'settings'  => array('class' => 'Wsb_Admin_Settings'),
            'integrations' => array('class' => 'Wsb_Admin_Integrations')
        ));

        ob_start();
        if (isset($allowed_tabs[$tab])) {
            $class = $allowed_tabs[$tab]['class'];
            if (class_exists($class)) {
                $module = new $class($this);
                $module->display();
            } else {
                do_action('wsb_admin_tab_render_' . $tab, $this);
            }
        } else {
            // Default Fallback
            $module = new Wsb_Admin_Dashboard($this);
            $module->display();
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
        $this->wsb_notify_booking_update($booking_id, array('status' => $new_status));
    }

    public function wsb_notify_booking_update($booking_id, $changes = array())
    {
        global $wpdb;
        $booking_table = $wpdb->prefix . 'wsb_bookings';
        $customer_table = $wpdb->prefix . 'wsb_customers';
        $staff_table = $wpdb->prefix . 'wsb_staff';
        
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, c.email, c.first_name, c.last_name 
             FROM $booking_table b 
             JOIN $customer_table c ON b.customer_id = c.id 
             WHERE b.id = %d", 
             $booking_id
        ));
        
        if ($booking && !empty($booking->email)) {
            $change_details = "";
            $subject_parts = array();

            if (isset($changes['booking_date'])) {
                $change_details .= '<div style="margin-bottom:10px;">📅 <strong>Date Changed:</strong> ' . esc_html($changes['booking_date']) . '</div>';
                $subject_parts[] = "Date";
            }
            if (isset($changes['start_time'])) {
                $change_details .= '<div style="margin-bottom:10px;">⏰ <strong>Time Changed:</strong> ' . esc_html($changes['start_time']) . '</div>';
                $subject_parts[] = "Time";
            }
            if (isset($changes['staff_id'])) {
                $staff_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $staff_table WHERE id = %d", $changes['staff_id']));
                $change_details .= '<div style="margin-bottom:10px;">👤 <strong>Professional Reassigned:</strong> ' . esc_html($staff_name) . '</div>';
                $subject_parts[] = "Professional";
            }
            if (isset($changes['status'])) {
                $change_details .= '<div style="margin-bottom:10px;">🔄 <strong>Status Updated:</strong> ' . strtoupper(esc_html($changes['status'])) . '</div>';
                $subject_parts[] = "Status";
            }

            if (empty($change_details)) return; // No relevant changes

            $mail_subject = "Update: Your Booking #" . $booking_id . " has been updated (" . implode(", ", $subject_parts) . ")";
            
            $content_html = '
                <div style="background:#f8fafc; padding:30px; border-radius:16px; text-align:left;">
                    <div style="font-size:12px; text-transform:uppercase; color:#94a3b8; font-weight:800; margin-bottom:20px;">Update Details</div>
                    <div style="color:#1e293b; font-size:15px; line-height:1.6;">
                        ' . $change_details . '
                    </div>
                    <p style="margin-top:20px; color:#475569; font-size:14px; border-top:1px solid #e2e8f0; padding-top:20px;">
                        The details of your appointment have been updated in our system. If you have any questions regarding these changes, please contact our support team.
                    </p>
                    <div style="text-align:center; margin-top:25px;">
                        <a href="' . home_url('/booking-dashboard') . '" style="display:inline-block; padding:14px 30px; background:#6366f1; color:#fff; text-decoration:none; border-radius:12px; font-weight:700; box-shadow:0 4px 12px rgba(99, 102, 241, 0.2);">View Full Details</a>
                    </div>
                </div>';

            wsb_send_modern_email($booking->email, $mail_subject, 'Booking Updated', "Hello " . $booking->first_name . ", some details of your booking #" . $booking_id . " have been updated.", $content_html);
        }
    }
}
