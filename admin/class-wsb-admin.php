<?php
class Wsb_Admin
{
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Register AJAX tab loader
        add_action('wp_ajax_wsb_load_admin_tab', array($this, 'ajax_load_tab'));
    }

    public function ajax_load_tab()
    {
        check_ajax_referer('wsb_admin_nonce', 'nonce');
        $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'dashboard';

        // Merge POST data into GET/REQUEST so that filter logic (which usually looks at $_GET) works during AJAX SPA updates
        $_GET = array_merge($_GET, $_POST);
        $_REQUEST = array_merge($_REQUEST, $_POST);

        // Handle extra params string (e.g. view=calendar, action=edit, etc)
        if (!empty($_POST['params'])) {
            parse_str(ltrim($_POST['params'], '&'), $extra_params);
            $_GET = array_merge($_GET, $extra_params);
            $_REQUEST = array_merge($_REQUEST, $extra_params);
        }

        ob_start();
        switch ($tab) {
            case 'bookings':
                $this->display_bookings_page();
                break;
            case 'finance':
                $this->display_finance_page();
                break;
            case 'services':
                $this->display_services_page();
                break;
            case 'staff':
                $this->display_staff_page();
                break;
            case 'customers':
                $this->display_customers_page();
                break;
            case 'design':
                $this->display_design_page();
                break;
            case 'settings':
                $this->display_settings_page();
                break;
            default:
                $this->display_plugin_setup_page();
                break;
        }
        $content = ob_get_clean();
        wp_send_json_success(array('content' => $content));
    }

    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(dirname(__FILE__)) . 'assets/admin/css/wsb-admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts()
    {
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
        // Enforce full-width by adding a script to modify body classes if needed, 
        // but we'll mainly do it via CSS in the master view.
        include_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/wsb-admin-master-display.php';
    }

    public function display_plugin_setup_page()
    {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'wsb_bookings';

        // Self-Healing Schema Patching (Compatible with all MySQL/MariaDB versions)
        $columns = $wpdb->get_col("DESCRIBE {$table_bookings}");
        if (!in_array('request_type', $columns)) {
            $wpdb->query("ALTER TABLE {$table_bookings} ADD COLUMN request_type VARCHAR(50) DEFAULT NULL");
            $wpdb->query("ALTER TABLE {$table_bookings} ADD COLUMN requested_date DATE DEFAULT NULL");
            $wpdb->query("ALTER TABLE {$table_bookings} ADD COLUMN requested_time TIME DEFAULT NULL");
            $wpdb->query("ALTER TABLE {$table_bookings} ADD COLUMN requested_staff_id BIGINT(20) DEFAULT NULL");
        }

        // Metric Queries
        $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_bookings");
        $total_revenue = $wpdb->get_var("SELECT SUM(total_amount) FROM {$wpdb->prefix}wsb_bookings WHERE status = 'confirmed' OR status = 'completed'");
        $total_customers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_customers");
        $total_services = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_services");
        
        // New Actionable Metrics
        $today_bookings = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_bookings WHERE booking_date = %s", date('Y-m-d')));
        $pending_approvals = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_bookings WHERE status = 'pending' AND (request_type IS NULL OR request_type = '')");
        $client_requests = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_bookings WHERE status = 'pending' AND request_type IN ('cancel', 'reschedule')");

        // Revenue Trajectory Data (Last 7 Days)
        $revenue_chart_labels = [];
        $revenue_chart_values = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $day_name = date('D', strtotime($date));
            $daily_rev = $wpdb->get_var($wpdb->prepare("SELECT SUM(total_amount) FROM {$wpdb->prefix}wsb_bookings WHERE booking_date = %s AND (status = 'confirmed' OR status = 'completed')", $date));
            $revenue_chart_labels[] = $day_name;
            $revenue_chart_values[] = floatval($daily_rev);
        }

        // Top Performing Services
        $top_services = $wpdb->get_results("
            SELECT s.name, COUNT(b.id) as booking_count, SUM(b.total_amount) as total_revenue
            FROM {$wpdb->prefix}wsb_bookings b
            JOIN {$wpdb->prefix}wsb_services s ON b.service_id = s.id
            WHERE b.status = 'confirmed' OR b.status = 'completed'
            GROUP BY b.service_id
            ORDER BY total_revenue DESC
            LIMIT 5
        ");

        // Recent Bookings Query
        $recent_bookings = $wpdb->get_results("
            SELECT b.*, c.first_name, c.last_name, s.name as service_name 
            FROM {$wpdb->prefix}wsb_bookings b
            LEFT JOIN {$wpdb->prefix}wsb_customers c ON b.customer_id = c.id
            LEFT JOIN {$wpdb->prefix}wsb_services s ON b.service_id = s.id
            ORDER BY b.created_at DESC LIMIT 15
        ");

        ?>
        <style>
            .wsb-clickable-card {
                text-decoration: none;
                color: inherit;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                display: block;
                border-left: 4px solid transparent;
            }

            .wsb-clickable-card:hover {
                transform: translateY(-3px);
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
                border-left-color: var(--wsb-primary);
            }

            .wsb-clickable-card h3 {
                transition: color 0.2s ease;
            }

            .wsb-clickable-card:hover h3 {
                color: var(--wsb-primary);
            }
        </style>
        <div class="wrap wsb-admin-wrap">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h1 style="margin:0;">Dashboard Overview</h1>
                <div>
                    <a href="?page=wsb_main&tab=bookings&view=calendar" class="wsb-btn-primary">View Calendar</a>
                    <a href="?page=wsb_main&tab=services&action=add" class="wsb-btn-primary"
                        style="margin-left:5px; background:var(--wsb-success);">+ New Service</a>
                </div>
            </div>
            <hr class="wp-header-end" style="margin-bottom:20px;">

            <!-- Quick Actions / High Priority Row -->
            <div class="wsb-dashboard-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom:30px;">
                <a href="?page=wsb_main&tab=bookings&filter_date_start=<?php echo date('Y-m-d'); ?>&filter_date_end=<?php echo date('Y-m-d'); ?>" class="wsb-stat-card wsb-clickable-card" style="border-left-color: #3b82f6;">
                    <h3 style="margin-top:0; font-size:16px; color:#3b82f6;">Today's Schedule</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;"><?php echo intval($today_bookings); ?></p>
                    <span style="display:inline-block; margin-top:10px; color:var(--wsb-text-muted); font-size:12px;">Appointments for today</span>
                </a>

                <a href="?page=wsb_main&tab=bookings&filter_status=pending" class="wsb-stat-card wsb-clickable-card" style="border-left-color: #f59e0b;">
                    <h3 style="margin-top:0; font-size:16px; color:#f59e0b;">Pending Approvals</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;"><?php echo intval($pending_approvals); ?></p>
                    <span style="display:inline-block; margin-top:10px; color:var(--wsb-text-muted); font-size:12px;">New booking requests</span>
                </a>

                <a href="?page=wsb_main&tab=bookings&filter_status=pending_requests" class="wsb-stat-card wsb-clickable-card" style="border-left-color: #ef4444;">
                    <h3 style="margin-top:0; font-size:16px; color:#ef4444;">Client Requests</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;"><?php echo intval($client_requests); ?></p>
                    <span style="display:inline-block; margin-top:10px; color:var(--wsb-text-muted); font-size:12px;">Reschedules & Cancellations</span>
                </a>
            </div>

            <!-- Global Metrics Row -->
            <div class="wsb-dashboard-grid"
                style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom:30px;">
                <a href="?page=wsb_main&tab=bookings" class="wsb-stat-card wsb-clickable-card">
                    <h3 style="margin-top:0; font-size:16px;">Total Bookings</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;">
                        <?php echo intval($total_bookings); ?></p>
                    <span style="display:inline-block; margin-top:10px; color:var(--wsb-text-muted); font-size:12px;">Lifetime Volume</span>
                </a>

                <a href="?page=wsb_main&tab=bookings" class="wsb-stat-card wsb-clickable-card">
                    <h3 style="margin-top:0; font-size:16px;">Total Revenue</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--wsb-success);">
                        <?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo number_format((float) $total_revenue, 2); ?></p>
                    <span style="display:inline-block; margin-top:10px; color:var(--wsb-text-muted); font-size:12px;">Confirmed Earnings</span>
                </a>

                <a href="?page=wsb_main&tab=customers" class="wsb-stat-card wsb-clickable-card">
                    <h3 style="margin-top:0; font-size:16px;">Total Customers</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;">
                        <?php echo intval($total_customers); ?></p>
                    <span style="display:inline-block; margin-top:10px; color:var(--wsb-text-muted); font-size:12px;">Registered Users</span>
                </a>

                <a href="?page=wsb_main&tab=services" class="wsb-stat-card wsb-clickable-card">
                    <h3 style="margin-top:0; font-size:16px;">Active Services</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;">
                        <?php echo intval($total_services); ?></p>
                    <span style="display:inline-block; margin-top:10px; color:var(--wsb-text-muted); font-size:12px;">Catalog Size</span>
                </a>
            </div>

            <!-- Insights Section: Chart & Performance -->
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom:30px;">
                <!-- Revenue Chart -->
                <div style="background: var(--wsb-panel-dark); border: 1px solid var(--wsb-border); border-radius:12px; padding:20px;">
                    <h3 style="margin-top:0; margin-bottom:20px; color:#fff; font-size:16px;">Revenue Trajectory (Last 7 Days)</h3>
                    <div style="height:250px; width:100%;">
                        <canvas id="wsb-revenue-chart"></canvas>
                    </div>
                </div>

                <!-- Top Services -->
                <div style="background: var(--wsb-panel-dark); border: 1px solid var(--wsb-border); border-radius:12px; padding:20px;">
                    <h3 style="margin-top:0; margin-bottom:20px; color:#fff; font-size:16px;">Top Performing Services</h3>
                    <div class="wsb-top-services-list">
                        <?php if (!empty($top_services)): 
                            foreach($top_services as $ts): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding-bottom:15px; border-bottom:1px solid rgba(255,255,255,0.05);">
                                <div>
                                    <div style="font-weight:600; color:#fff; font-size:14px;"><?php echo esc_html($ts->name); ?></div>
                                    <div style="font-size:11px; color:var(--wsb-text-muted);"><?php echo intval($ts->booking_count); ?> Bookings</div>
                                </div>
                                <div style="font-weight:bold; color:var(--wsb-success); font-size:14px;">
                                    <?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo number_format($ts->total_revenue, 2); ?>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <p style="color:var(--wsb-text-muted); font-size:13px; text-align:center; padding:20px;">No performance data available yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>
                (function() {
                    var initDashboardCharts = function() {
                        if (typeof Chart === 'undefined') {
                            console.warn('WSB: Chart.js not loaded yet, retrying...');
                            setTimeout(initDashboardCharts, 200);
                            return;
                        }
                        var ctx = document.getElementById('wsb-revenue-chart');
                        if (!ctx) return;
                        
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: <?php echo json_encode($revenue_chart_labels); ?>,
                                datasets: [{
                                    label: 'Daily Revenue',
                                    data: <?php echo json_encode($revenue_chart_values); ?>,
                                    borderColor: '#6366f1',
                                    backgroundColor: (function() {
                                        var gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
                                        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.25)');
                                        gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
                                        return gradient;
                                    })(),
                                    fill: true,
                                    tension: 0.4,
                                    borderWidth: 3,
                                    pointBackgroundColor: '#6366f1',
                                    pointRadius: 4,
                                    pointHoverRadius: 6
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { 
                                    legend: { display: false },
                                    tooltip: {
                                        backgroundColor: '#1e293b',
                                        titleColor: '#94a3b8',
                                        bodyColor: '#fff',
                                        padding: 12,
                                        borderColor: '#334155',
                                        borderWidth: 1
                                    }
                                },
                                scales: {
                                    y: { 
                                        beginAtZero: true,
                                        grid: { color: 'rgba(255,255,255,0.05)' },
                                        ticks: { color: '#94a3b8', font: { size: 10 } }
                                    },
                                    x: { 
                                        grid: { display: false },
                                        ticks: { color: '#94a3b8', font: { size: 10 } }
                                    }
                                }
                            }
                        });
                    };

                    initDashboardCharts();
                    jQuery(document).off('wsb-tab-loaded.wsb_dashboard').on('wsb-tab-loaded.wsb_dashboard', function(e, tab) {
                        if (tab === 'dashboard') initDashboardCharts();
                    });
                })();
            </script>

            <div
                style="background: var(--wsb-panel-dark); border-radius:12px; border:1px solid var(--wsb-border); overflow:hidden;">
                <div
                    style="padding: 20px; border-bottom: 1px solid var(--wsb-border); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; color: #fff;">Recent Activity</h3>
                    <a href="?page=wsb_main&tab=bookings"
                        style="color:var(--wsb-primary); text-decoration:none; font-weight:500;">View All</a>
                </div>
                <!-- Scrollable Table Container -->
                <div style="max-height: 400px; overflow-y: auto;">
                    <table style="width:100%; border-collapse:collapse; text-align:left;">
                    <thead style="background:rgba(0,0,0,0.2);">
                        <tr>
                            <th style="padding:15px 20px; color:var(--wsb-text-muted); font-weight:500; font-size:13px;">
                                Customer</th>
                            <th style="padding:15px 20px; color:var(--wsb-text-muted); font-weight:500; font-size:13px;">Service
                            </th>
                            <th style="padding:15px 20px; color:var(--wsb-text-muted); font-weight:500; font-size:13px;">Date
                            </th>
                            <th style="padding:15px 20px; color:var(--wsb-text-muted); font-weight:500; font-size:13px;">Status
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_bookings)):
                            foreach ($recent_bookings as $rb): ?>
                                <tr class="wsb-clickable-row"
                                    data-href="?page=wsb_main&tab=bookings&action=edit&id=<?php echo $rb->id; ?>"
                                    style="border-bottom:1px solid var(--wsb-border);">
                                    <td style="padding:15px 20px; font-weight:500;">
                                        <?php echo esc_html($rb->first_name . ' ' . $rb->last_name); ?></td>
                                    <td style="padding:15px 20px; color:var(--wsb-text-muted);">
                                        <?php echo esc_html($rb->service_name); ?></td>
                                    <td style="padding:15px 20px;"><?php echo esc_html(date('M d, Y', strtotime($rb->booking_date))); ?>
                                    </td>
                                    <td style="padding:15px 20px;"><span
                                            class="wsb-status wsb-status-<?php echo esc_attr($rb->status); ?>"
                                            style="font-size:11px;"><?php echo esc_html(ucfirst($rb->status)); ?></span></td>
                                </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="4" style="padding:30px; text-align:center; color:var(--wsb-text-muted);">No recent
                                    activity.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <?php
    }

    private function wsb_notify_status_change($booking_id, $new_status) {
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

    public function display_bookings_page()
    {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'wsb_bookings';

        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        // Quick Status Updates (Approve / Reject)
        // Quick Action Decisions for Client Requests (Reschedule / Cancel)
        if ($action === 'request_action' && $booking_id) {
            $decision = isset($_GET['decision']) ? sanitize_text_field($_GET['decision']) : '';
            $booking_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", $booking_id));
            
            if ($booking_record) {
                if ($decision === 'approve') {
                    if ($booking_record->request_type === 'reschedule') {
                        $wpdb->update($table_bookings, array(
                            'status' => 'confirmed',
                            'booking_date' => $booking_record->requested_date,
                            'start_time' => $booking_record->requested_time,
                            'staff_id' => $booking_record->requested_staff_id,
                            'request_type' => NULL,
                            'requested_date' => NULL,
                            'requested_time' => NULL,
                            'requested_staff_id' => NULL
                        ), array('id' => $booking_id));
                        echo '<div class="notice notice-success is-dismissible"><p>Reschedule request approved and applied successfully.</p></div>';
                    } elseif ($booking_record->request_type === 'cancel') {
                        $wpdb->update($table_bookings, array(
                            'status' => 'cancelled',
                            'request_type' => NULL
                        ), array('id' => $booking_id));
                        echo '<div class="notice notice-success is-dismissible"><p>Cancellation request approved successfully.</p></div>';
                    }
                    $this->wsb_notify_status_change($booking_id, 'confirmed');
                } elseif ($decision === 'reject') {
                    $wpdb->update($table_bookings, array(
                        'status' => 'confirmed',
                        'request_type' => NULL,
                        'requested_date' => NULL,
                        'requested_time' => NULL,
                        'requested_staff_id' => NULL
                    ), array('id' => $booking_id));
                    echo '<div class="notice notice-warning is-dismissible"><p>Client request declined. Booking remains active.</p></div>';
                    $this->wsb_notify_status_change($booking_id, 'confirmed');
                }
            }
            $action = 'list';
        }

        // Standard quick status overrides
        if ($action === 'status' && $booking_id) {
            $new_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
            if (in_array($new_status, ['confirmed', 'cancelled', 'pending', 'completed'])) {
                $wpdb->update($table_bookings, array('status' => $new_status), array('id' => $booking_id));
                $this->wsb_notify_status_change($booking_id, $new_status);
                echo '<div class="notice notice-success is-dismissible"><p>Status updated cleanly.</p></div>';
            }
            $action = 'list';
        }

        // Full Edit Submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wsb_edit_booking_nonce']) && wp_verify_nonce($_POST['wsb_edit_booking_nonce'], 'wsb_edit_booking')) {
            $wpdb->update($table_bookings, array(
                'booking_date' => sanitize_text_field($_POST['booking_date']),
                'start_time' => sanitize_text_field($_POST['start_time']),
                'end_time' => sanitize_text_field($_POST['end_time']),
                'total_amount' => floatval($_POST['total_amount']),
                'status' => sanitize_text_field($_POST['status'])
            ), array('id' => $booking_id));
            $this->wsb_notify_status_change($booking_id, sanitize_text_field($_POST['status']));
            echo '<div class="notice notice-success is-dismissible"><p>Booking information updated successfully.</p></div>';
            $action = 'list';
        }

        if ($action === 'edit' && $booking_id) {
            $booking = $wpdb->get_row($wpdb->prepare("
                SELECT b.*, c.first_name, c.last_name, c.email as customer_email, c.phone as customer_phone, s.name as service_name, st.name as staff_name 
                FROM {$table_bookings} b
                LEFT JOIN {$wpdb->prefix}wsb_customers c ON b.customer_id = c.id
                LEFT JOIN {$wpdb->prefix}wsb_services s ON b.service_id = s.id
                LEFT JOIN {$wpdb->prefix}wsb_staff st ON b.staff_id = st.id
                WHERE b.id = %d
            ", $booking_id));
            $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsb_payments WHERE booking_id = %d", $booking_id));

            if ($booking) {
                ?>
                <div class="wrap wsb-admin-wrap">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                        <h1 style="margin:0; font-size:24px; color:#fff;">Manage Booking #<?php echo esc_html(str_pad($booking->id, 5, '0', STR_PAD_LEFT)); ?></h1>
                        <a href="?page=wsb_main&tab=bookings" class="wsb-btn-primary"
                            style="background:var(--wsb-border);">Back to Bookings</a>
                    </div>

                    <form method="post" action="">
                        <?php wp_nonce_field('wsb_edit_booking', 'wsb_edit_booking_nonce'); ?>
                        
                        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:25px;">
                            
                            <!-- Left Column: Core Booking Details -->
                            <div style="display:flex; flex-direction:column; gap:25px;">
                                
                                <!-- Customer Identity Card -->
                                <div style="background:var(--wsb-panel-dark); padding:25px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid var(--wsb-primary);">
                                    <h3 style="margin:0 0 20px 0; color:var(--wsb-primary); display:flex; align-items:center; gap:10px;">
                                        <span class="dashicons dashicons-admin-users"></span> Customer Information
                                    </h3>
                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                        <div>
                                            <label style="display:block; margin-bottom:6px; color:var(--wsb-text-muted); font-size:13px;">Full Name</label>
                                            <div style="padding:12px; background:#0f172a; border:1px solid var(--wsb-border); border-radius:8px; color:#fff; font-weight:600;">
                                                <?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <label style="display:block; margin-bottom:6px; color:var(--wsb-text-muted); font-size:13px;">Contact Email</label>
                                            <div style="padding:12px; background:#0f172a; border:1px solid var(--wsb-border); border-radius:8px; color:#fff;">
                                                <?php echo esc_html($booking->customer_email); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <label style="display:block; margin-bottom:6px; color:var(--wsb-text-muted); font-size:13px;">Phone Number</label>
                                            <div style="padding:12px; background:#0f172a; border:1px solid var(--wsb-border); border-radius:8px; color:#fff;">
                                                <?php echo esc_html($booking->customer_phone ?: 'No phone provided'); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <label style="display:block; margin-bottom:6px; color:var(--wsb-text-muted); font-size:13px;">Assigned Professional</label>
                                            <div style="padding:12px; background:#0f172a; border:1px solid var(--wsb-border); border-radius:8px; color:var(--wsb-success); font-weight:700;">
                                                <?php echo esc_html($booking->staff_name); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Appointment Configuration -->
                                <div style="background:var(--wsb-panel-dark); padding:25px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid var(--wsb-warning);">
                                    <h3 style="margin:0 0 20px 0; color:var(--wsb-warning); display:flex; align-items:center; gap:10px;">
                                        <span class="dashicons dashicons-calendar-alt"></span> Schedule & Service
                                    </h3>
                                    
                                    <div style="margin-bottom:20px;">
                                        <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted);">Selected Service</label>
                                        <input type="text" value="<?php echo esc_attr($booking->service_name); ?>" readonly 
                                            style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:12px; border-radius:8px; font-weight:600; cursor:not-allowed; opacity:0.8;">
                                    </div>

                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted);">Booking Date</label>
                                            <input name="booking_date" type="date" value="<?php echo esc_attr($booking->booking_date); ?>"
                                                style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:12px; border-radius:8px;"
                                                required>
                                        </div>
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted);">Start Time</label>
                                            <input name="start_time" type="time" value="<?php echo esc_attr($booking->start_time); ?>"
                                                style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:12px; border-radius:8px;"
                                                required>
                                        </div>
                                    </div>

                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted);">End Time</label>
                                            <input name="end_time" type="time" value="<?php echo esc_attr($booking->end_time); ?>"
                                                style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:12px; border-radius:8px;"
                                                required>
                                        </div>
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted);">Total Duration (Auto)</label>
                                            <div style="padding:12px; background:rgba(255,255,255,0.03); border:1px dashed var(--wsb-border); border-radius:8px; color:var(--wsb-text-muted);">
                                                Calculated from start/end
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Status & Financials -->
                            <div style="display:flex; flex-direction:column; gap:25px;">
                                
                                <!-- Status Card -->
                                <div style="background:var(--wsb-panel-dark); padding:25px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid #fff;">
                                    <h3 style="margin:0 0 20px 0; color:#fff; display:flex; align-items:center; gap:10px;">
                                        <span class="dashicons dashicons-marker"></span> Booking Status
                                    </h3>
                                    <div style="margin-bottom:20px;">
                                        <select name="status"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:14px; border-radius:8px; font-weight:700; font-size:15px; border-left:4px solid <?php echo $booking->status === 'confirmed' ? 'var(--wsb-success)' : ($booking->status === 'pending' ? 'var(--wsb-warning)' : '#ef4444'); ?>;">
                                            <option value="pending" <?php selected($booking->status, 'pending'); ?>>Pending Approval</option>
                                            <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>>Confirmed</option>
                                            <option value="completed" <?php selected($booking->status, 'completed'); ?>>Completed</option>
                                            <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <p style="font-size:12px; color:var(--wsb-text-muted); line-height:1.5;">
                                        Updating the status will automatically trigger a notification email to the customer.
                                    </p>
                                </div>

                                <!-- Financial Insights Card -->
                                <div style="background:var(--wsb-panel-dark); padding:25px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid var(--wsb-success);">
                                    <h3 style="margin:0 0 20px 0; color:var(--wsb-success); display:flex; align-items:center; gap:10px;">
                                        <span class="dashicons dashicons-money-alt"></span> Financial Details
                                    </h3>
                                    <div style="margin-bottom:20px;">
                                        <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted);">Amount Receivable</label>
                                        <div style="position:relative;">
                                            <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--wsb-text-muted); font-weight:bold;"><?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?></span>
                                            <input name="total_amount" type="number" step="0.01" value="<?php echo esc_attr($booking->total_amount); ?>"
                                                style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:12px 12px 12px 30px; border-radius:8px; font-size:18px; font-weight:800;"
                                                required>
                                        </div>
                                    </div>
                                    
                                    <div style="background:rgba(16, 185, 129, 0.05); padding:15px; border-radius:8px; border:1px solid rgba(16, 185, 129, 0.1);">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                            <span style="font-size:13px; color:var(--wsb-text-muted);">Payment Strategy</span>
                                            <span style="font-size:11px; font-weight:800; text-transform:uppercase; color:var(--wsb-success);">Secured</span>
                                        </div>
                                        <?php if ($payment): ?>
                                            <div style="border-top:1px solid rgba(16, 185, 129, 0.1); padding-top:10px; margin-top:5px;">
                                                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                                                    <span style="font-size:12px; color:var(--wsb-text-muted);">Gateway</span>
                                                    <span style="font-size:12px; color:#fff; font-weight:600;"><?php echo strtoupper(esc_html($payment->gateway)); ?></span>
                                                </div>
                                                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                                                    <span style="font-size:12px; color:var(--wsb-text-muted);">Transaction ID</span>
                                                    <span style="font-size:12px; color:#fff; font-family:monospace;"><?php echo esc_html($payment->transaction_id ?: 'N/A'); ?></span>
                                                </div>
                                                <div style="display:flex; justify-content:space-between;">
                                                    <span style="font-size:12px; color:var(--wsb-text-muted);">Payment Status</span>
                                                    <span style="font-size:11px; padding:2px 6px; border-radius:4px; background:<?php echo $payment->status === 'completed' ? 'var(--wsb-success)' : '#f59e0b'; ?>; color:#fff; font-weight:bold;"><?php echo strtoupper(esc_html($payment->status)); ?></span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div style="border-top:1px solid rgba(16, 185, 129, 0.1); padding-top:10px; margin-top:5px; text-align:center; color:var(--wsb-text-muted); font-size:11px; font-style:italic;">
                                                No payment transaction linked to this booking yet.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Action Container -->
                                <div style="display:flex; flex-direction:column; gap:10px;">
                                    <button type="submit" class="wsb-btn-primary" style="width:100%; padding:15px; font-size:16px; background:var(--wsb-primary); border:none; box-shadow:0 4px 15px rgba(99, 102, 241, 0.3);">
                                        Update Booking Details
                                    </button>
                                    <a href="?page=wsb_main&tab=bookings" style="text-align:center; padding:10px; color:var(--wsb-text-muted); text-decoration:none; font-size:14px;">Discard Changes</a>
                                </div>

                            </div>
                        </div>
                    </form>
                </div>
                <?php
            }
            return;
        }

        // Advanced Filter Engine
        $filter_status   = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_staff    = isset($_GET['filter_staff']) ? intval($_GET['filter_staff']) : 0;
        $filter_service  = isset($_GET['filter_service']) ? intval($_GET['filter_service']) : 0;
        $filter_search   = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
        $filter_date_start = isset($_GET['filter_date_start']) ? sanitize_text_field($_GET['filter_date_start']) : '';
        $filter_date_end   = isset($_GET['filter_date_end']) ? sanitize_text_field($_GET['filter_date_end']) : '';

        $where_clause = "WHERE 1=1";
        
        if (in_array($filter_status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
            $where_clause .= " AND b.status = '{$filter_status}'";
        } elseif ($filter_status === 'pending_requests') {
            $where_clause .= " AND b.status = 'pending' AND b.request_type IN ('cancel', 'reschedule')";
        }

        if ($filter_staff > 0) {
            $where_clause .= $wpdb->prepare(" AND b.staff_id = %d", $filter_staff);
        }

        if ($filter_service > 0) {
            $where_clause .= $wpdb->prepare(" AND b.service_id = %d", $filter_service);
        }

        if (!empty($filter_search)) {
            $search_wildcard = '%' . $wpdb->esc_like($filter_search) . '%';
            $where_clause .= $wpdb->prepare(" AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR b.id = %d)", $search_wildcard, $search_wildcard, $search_wildcard, intval($filter_search));
        }

        if (!empty($filter_date_start)) {
            $where_clause .= $wpdb->prepare(" AND b.booking_date >= %s", $filter_date_start);
        }

        if (!empty($filter_date_end)) {
            $where_clause .= $wpdb->prepare(" AND b.booking_date <= %s", $filter_date_end);
        }

        $wpdb->query("ALTER TABLE {$table_bookings} ADD COLUMN IF NOT EXISTS request_type VARCHAR(50) DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table_bookings} ADD COLUMN IF NOT EXISTS requested_date DATE DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table_bookings} ADD COLUMN IF NOT EXISTS requested_time TIME DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table_bookings} ADD COLUMN IF NOT EXISTS requested_staff_id BIGINT(20) DEFAULT NULL");

        // Metrics for Top Cards
        $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$table_bookings}");
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_bookings} WHERE status='pending'");
        $confirmed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_bookings} WHERE status='confirmed' OR status='completed'");
        $client_requests_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_bookings} WHERE status='pending' AND request_type IN ('cancel', 'reschedule')");

        // Joined query to get names instead of raw IDs for a professional look
        $query = "SELECT b.*, 
                 c.first_name, c.last_name, c.email as customer_email, c.phone as customer_phone,
                 s.name as service_name, 
                 st.name as staff_name,
                 req_staff.name as requested_staff_name
          FROM {$wpdb->prefix}wsb_bookings b
          LEFT JOIN {$wpdb->prefix}wsb_customers c ON b.customer_id = c.id
          LEFT JOIN {$wpdb->prefix}wsb_services s ON b.service_id = s.id
          LEFT JOIN {$wpdb->prefix}wsb_staff st ON b.staff_id = st.id
          LEFT JOIN {$wpdb->prefix}wsb_staff req_staff ON b.requested_staff_id = req_staff.id
          {$where_clause}
          ORDER BY b.booking_date DESC, b.start_time DESC";

        $bookings = $wpdb->get_results($query);
        
        $all_staff = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}wsb_staff ORDER BY name ASC");
        $all_services = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}wsb_services ORDER BY name ASC");
        
        $view = isset($_GET['view']) ? $_GET['view'] : 'list';
        $page_url = "?page=wsb_main&tab=bookings";
        ?>
        <div class="wrap wsb-admin-wrap">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h1 style="margin:0;">Manage Bookings</h1>
                <div>
                    <a href="<?php echo $page_url; ?>&view=list&filter_status=<?php echo esc_attr($filter_status); ?>"
                        class="wsb-btn-primary"
                        style="background: <?php echo $view === 'list' ? 'var(--wsb-primary)' : 'rgba(255,255,255,0.05)'; ?>;">List
                        View</a>
                    <a href="<?php echo $page_url; ?>&view=calendar&filter_status=<?php echo esc_attr($filter_status); ?>"
                        class="wsb-btn-primary"
                        style="margin-left:5px; background: <?php echo $view === 'calendar' ? 'var(--wsb-primary)' : 'rgba(255,255,255,0.05)'; ?>;">Calendar
                        View</a>
                </div>
            </div>

            <!-- Advanced Filter Toolbar -->
            <div style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--wsb-border); margin-top:20px;">
                <form method="get" action="" style="display:flex; flex-wrap:wrap; gap:15px; align-items:flex-end;">
                    <input type="hidden" name="page" value="wsb_main">
                    <input type="hidden" name="tab" value="bookings">
                    <input type="hidden" name="view" value="<?php echo esc_attr($view); ?>">
                    <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">

                    <div style="flex:1; min-width:200px;">
                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted); font-size:12px;">Search Bookings</label>
                        <input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" placeholder="Name, Email, or ID..." style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:8px; border-radius:6px;">
                    </div>

                    <div style="width:180px;">
                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted); font-size:12px;">Professional</label>
                        <select name="filter_staff" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:8px; border-radius:6px;">
                            <option value="0">All Professionals</option>
                            <?php foreach($all_staff as $st): ?>
                                <option value="<?php echo $st->id; ?>" <?php selected($filter_staff, $st->id); ?>><?php echo esc_html($st->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="width:180px;">
                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted); font-size:12px;">Service</label>
                        <select name="filter_service" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:8px; border-radius:6px;">
                            <option value="0">All Services</option>
                            <?php foreach($all_services as $srv): ?>
                                <option value="<?php echo $srv->id; ?>" <?php selected($filter_service, $srv->id); ?>><?php echo esc_html($srv->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="width:150px;">
                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted); font-size:12px;">From Date</label>
                        <input type="date" name="filter_date_start" value="<?php echo esc_attr($filter_date_start); ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:8px; border-radius:6px;">
                    </div>

                    <div style="width:150px;">
                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted); font-size:12px;">To Date</label>
                        <input type="date" name="filter_date_end" value="<?php echo esc_attr($filter_date_end); ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:8px; border-radius:6px;">
                    </div>

                    <div style="display:flex; gap:5px;">
                        <button type="submit" class="wsb-btn-primary">Apply</button>
                        <a href="?page=wsb_main&tab=bookings&view=<?php echo esc_attr($view); ?>" class="wsb-btn-primary" style="background:var(--wsb-border);">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Clickable Meta Cards -->
            <style>
                .booking-filter-card {
                    border-left: 4px solid transparent;
                    text-decoration: none;
                    color: inherit;
                    display: block;
                    border: 1px solid var(--wsb-border);
                    border-radius: 12px;
                    background: var(--wsb-panel-dark);
                    padding: 20px;
                    transition: transform 0.2s;
                }

                .booking-filter-card:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
                }

                .card-active {
                    background: rgba(59, 130, 246, 0.1) !important;
                    border-left: 4px solid var(--wsb-primary) !important;
                    border-color: var(--wsb-primary) !important;
                }
            </style>
            <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top:20px; margin-bottom:20px;">
                <a href="<?php echo $page_url; ?>&view=<?php echo esc_attr($view); ?>&filter_status=all"
                    class="booking-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--wsb-text-muted);">Total Bookings</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                        <?php echo intval($total_bookings); ?></p>
                </a>
                <a href="<?php echo $page_url; ?>&view=<?php echo esc_attr($view); ?>&filter_status=pending"
                    class="booking-filter-card <?php echo $filter_status === 'pending' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:#f59e0b;">Pending Approvals</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                        <?php echo intval($pending_count); ?></p>
                </a>
                <a href="<?php echo $page_url; ?>&view=<?php echo esc_attr($view); ?>&filter_status=confirmed"
                    class="booking-filter-card <?php echo $filter_status === 'confirmed' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--wsb-success);">Confirmed / Completed</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                        <?php echo intval($confirmed_count); ?></p>
                </a>
                <a href="<?php echo $page_url; ?>&view=<?php echo esc_attr($view); ?>&filter_status=pending_requests"
                    class="booking-filter-card <?php echo $filter_status === 'pending_requests' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:#ef4444;">Client Requests</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                        <?php echo intval($client_requests_count); ?></p>
                </a>
            </div>

            <?php if ($view === 'calendar'): ?>
                <!-- Calendar View powered by FullCalendar -->
                <div id="wsb-calendar"
                    style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; margin-top:20px; border: 1px solid var(--wsb-border); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                </div>
                <script>
                    (function () {
                        var initCalendar = function () {
                            var calendarEl = document.getElementById('wsb-calendar');
                            if (!calendarEl || calendarEl.classList.contains('fc')) return;

                            var events = [
                                <?php foreach ($bookings as $b): ?>
                                                {
                                        title: '<?php echo esc_js($b->first_name . " - " . $b->service_name); ?>',
                                        start: '<?php echo esc_js($b->booking_date); ?>T<?php echo esc_js($b->start_time); ?>',
                                        end: '<?php echo esc_js($b->booking_date); ?>T<?php echo esc_js($b->end_time); ?>',
                                        color: '<?php echo $b->status === 'confirmed' ? '#10b981' : ($b->status === 'pending' ? '#f59e0b' : '#3b82f6'); ?>',
                                        url: '<?php echo "?page=wsb_main&tab=bookings&action=edit&id=" . $b->id; ?>',
                                        extendedProps: {
                                            customer: '<?php echo esc_js($b->first_name . " " . $b->last_name); ?>',
                                            email: '<?php echo esc_js($b->customer_email); ?>',
                                            phone: '<?php echo esc_js($b->customer_phone); ?>',
                                            amount: '<?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo esc_js($b->total_amount); ?>',
                                            status: '<?php echo esc_js(ucfirst($b->status)); ?>'
                                        }
                                    },
                                <?php endforeach; ?>
                            ];
                            var calendar = new FullCalendar.Calendar(calendarEl, {
                                initialView: 'timeGridWeek',
                                headerToolbar: {
                                    left: 'prev,next today',
                                    center: 'title',
                                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                                },
                                events: events,
                                slotMinTime: '08:00:00',
                                slotMaxTime: '19:00:00',
                                allDaySlot: false,
                                height: 700,
                                eventDidMount: function (info) {
                                    var prop = info.event.extendedProps;
                                    // Fallback to native title with phone number
                                    info.el.setAttribute('title', prop.customer + ' | ' + prop.phone + ' | ' + prop.amount + ' | ' + prop.status);
                                }
                            });
                            calendar.render();
                        };

                        initCalendar();
                        // Also listen for our custom AJAX load event
                        jQuery(document).off('wsb-tab-loaded.wsb_bookings').on('wsb-tab-loaded.wsb_bookings', function (e, tab) {
                            if (tab === 'bookings') initCalendar();
                        });
                    })();
                </script>
                <style>
                    /* FullCalendar overrides for Dark Mode */
                    .fc-theme-standard td,
                    .fc-theme-standard th {
                        border-color: var(--wsb-border);
                        color: #fff;
                    }

                    .fc-col-header-cell {
                        background: rgba(0, 0, 0, 0.2) !important;
                        padding: 10px 0;
                    }

                    .fc-timegrid-slot-label {
                        color: var(--wsb-text-muted);
                        font-size: 12px;
                    }

                    .fc-button-primary {
                        background-color: var(--wsb-primary) !important;
                        border-color: var(--wsb-primary) !important;
                        text-transform: capitalize;
                        border-radius: 6px !important;
                    }

                    .fc .fc-toolbar-title {
                        font-weight: 600;
                        font-family: 'Inter', sans-serif;
                        font-size: 20px;
                        color: var(--wsb-text-main);
                    }

                    .fc-event {
                        border: none !important;
                        border-radius: 4px;
                        padding: 2px 4px;
                        font-size: 11px;
                    }
                </style>
            <?php else: ?>
                <!-- Modern SaaS List View -->
                <table class="wsb-modern-table">
                    <thead>
                        <tr>
                            <th>Booking REF</th>
                            <th>Customer Info</th>
                            <th>Service & Staff</th>
                            <th>Date & Time</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($bookings)): ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr class="wsb-clickable-row" data-href="?page=wsb_main&tab=bookings&action=edit&id=<?php echo $b->id; ?>">
                                    <td><strong
                                            style="color:var(--wsb-primary);">#<?php echo esc_html(str_pad($b->id, 5, '0', STR_PAD_LEFT)); ?></strong>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span
                                                class="wsb-customer-name"><?php echo esc_html($b->first_name . ' ' . $b->last_name); ?></span>
                                            <span class="wsb-customer-meta"><?php echo esc_html($b->customer_email); ?> |
                                                <?php echo esc_html($b->customer_phone); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span class="wsb-customer-name"><?php echo esc_html($b->service_name); ?></span>
                                            <span class="wsb-customer-meta">
                                                <?php if ($b->request_type === 'reschedule' && !empty($b->requested_staff_id)): ?>
                                                    <span style="text-decoration:line-through; color:var(--wsb-text-muted);"><?php echo esc_html($b->staff_name); ?></span> 
                                                    <span style="color:var(--wsb-warning); font-weight:bold;">➔ <?php echo esc_html($b->requested_staff_name); ?></span>
                                                <?php else: ?>
                                                    with <?php echo esc_html($b->staff_name); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span class="wsb-customer-name">
                                                <?php if ($b->request_type === 'reschedule' && !empty($b->requested_date)): ?>
                                                    <span style="text-decoration:line-through; color:var(--wsb-text-muted);"><?php echo esc_html(date('M d, Y', strtotime($b->booking_date))); ?></span> 
                                                    <span style="color:var(--wsb-warning); font-weight:bold;">➔ <?php echo esc_html(date('M d, Y', strtotime($b->requested_date))); ?></span>
                                                <?php else: ?>
                                                    <?php echo esc_html(date('M d, Y', strtotime($b->booking_date))); ?>
                                                <?php endif; ?>
                                            </span>
                                            <span class="wsb-customer-meta">
                                                <?php if ($b->request_type === 'reschedule' && !empty($b->requested_time)): ?>
                                                    <span style="text-decoration:line-through; color:var(--wsb-text-muted);"><?php echo esc_html(date('g:i A', strtotime($b->start_time))); ?></span> 
                                                    <span style="color:var(--wsb-warning); font-weight:bold;">➔ <?php echo esc_html(date('g:i A', strtotime($b->requested_time))); ?></span>
                                                <?php else: ?>
                                                    <?php echo esc_html(date('g:i A', strtotime($b->start_time))); ?> - <?php echo esc_html(date('g:i A', strtotime($b->end_time))); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td><strong><?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo esc_html($b->total_amount); ?></strong></td>
                                    <td>
                                        <?php if ($b->status === 'pending' && !empty($b->request_type)): ?>
                                            <span style="background:rgba(245, 158, 11, 0.15); color:var(--wsb-warning); padding:4px 10px; border-radius:12px; font-size:12px; font-weight:bold; white-space:nowrap; display:block; text-align:center; margin-bottom:5px;">
                                                <?php echo esc_html(ucfirst($b->request_type)); ?> Request
                                            </span>
                                            <div style="display:flex; gap:5px;">
                                                <a href="?page=wsb_main&tab=bookings&action=request_action&decision=approve&id=<?php echo $b->id; ?>" class="button" style="flex:1; text-align:center; background:var(--wsb-success); color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:bold; padding:4px 0; text-decoration:none;">Accept</a>
                                                <a href="?page=wsb_main&tab=bookings&action=request_action&decision=reject&id=<?php echo $b->id; ?>" class="button" style="flex:1; text-align:center; background:#ef4444; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:bold; padding:4px 0; text-decoration:none;">Reject</a>
                                            </div>
                                        <?php else: ?>
                                            <span class="wsb-status wsb-status-<?php echo esc_attr($b->status); ?>"><?php echo esc_html(ucfirst($b->status)); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 40px; color: var(--wsb-text-muted);">No bookings
                                    found. Generate some dummy data!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function display_services_page()
    {
        global $wpdb;
        $table_services = $wpdb->prefix . 'wsb_services';
        $table_staff = $wpdb->prefix . 'wsb_staff';

        // Auto-patch schema if image columns don't exist
        $wpdb->query("ALTER TABLE {$table_services} ADD COLUMN IF NOT EXISTS image_url varchar(255) DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table_services} ADD COLUMN IF NOT EXISTS gallery_urls text DEFAULT NULL");

        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($action === 'delete' && $service_id) {
            $wpdb->delete($table_services, array('id' => $service_id));
            echo '<div class="notice notice-success is-dismissible"><p>Service permanently deleted.</p></div>';
            $action = 'list';
        }

        // Seed Dummy Data Handler
        if ($action === 'seed' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'seed_services')) {
            $dummy_services = array(
                array(
                    'name' => 'Signature Haircut',
                    'description' => 'Precision cut tailored to your face shape by our top stylists.',
                    'duration' => 45,
                    'price' => 50.00,
                    'buffer_time' => 15,
                    'category' => 'Hair',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Balayage Color Treatment',
                    'description' => 'Beautiful, natural-looking hand-painted highlights.',
                    'duration' => 120,
                    'price' => 180.00,
                    'buffer_time' => 30,
                    'category' => 'Color',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1512496015851-a1dc8f411906?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Deep Tissue Massage',
                    'description' => 'Intense pressure therapy to release knots and muscle tension.',
                    'duration' => 60,
                    'price' => 90.00,
                    'buffer_time' => 15,
                    'category' => 'Spa & Relax',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Bridal Makeup Session',
                    'description' => 'Full makeup session for the big day, including consultations.',
                    'duration' => 90,
                    'price' => 120.00,
                    'buffer_time' => 30,
                    'category' => 'Makeup',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Classic Manicure',
                    'description' => 'Nail shaping, cuticle care, and standard professional polish.',
                    'duration' => 30,
                    'price' => 35.00,
                    'buffer_time' => 10,
                    'category' => 'Nails',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490c81ac36?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Deluxe Pedicure',
                    'description' => 'Exfoliating scrub, massage, and perfect nail lacquer.',
                    'duration' => 45,
                    'price' => 45.00,
                    'buffer_time' => 15,
                    'category' => 'Nails',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1610992015732-2449b7de358c?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Hatha Yoga Session',
                    'description' => 'Gentle physical postures and breathing techniques.',
                    'duration' => 60,
                    'price' => 25.00,
                    'buffer_time' => 15,
                    'category' => 'Wellness',
                    'capacity' => 10,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Aromatherapy Facial',
                    'description' => 'Soothing essential oils matched with deep skin cleansing.',
                    'duration' => 60,
                    'price' => 75.00,
                    'buffer_time' => 15,
                    'category' => 'Spa & Relax',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1600334089648-b0d9d3028eb2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Men\'s Beard Grooming',
                    'description' => 'Hot towel shave, beard trim, and premium oils.',
                    'duration' => 30,
                    'price' => 25.00,
                    'buffer_time' => 10,
                    'category' => 'Hair',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1620331311520-246422fd82f9?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Keratin Hair Treatment',
                    'description' => 'Smooth and de-frizz your hair for up to 12 weeks.',
                    'duration' => 150,
                    'price' => 250.00,
                    'buffer_time' => 30,
                    'category' => 'Hair',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1560869713-7d0a29430873?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Highlights & Lowlights',
                    'description' => 'Dimensional coloring for a rich, natural shine.',
                    'duration' => 90,
                    'price' => 140.00,
                    'buffer_time' => 15,
                    'category' => 'Color',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1595476108010-b4d1f102b1b1?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Hot Stone Massage',
                    'description' => 'Heated basalt stones melt away muscle tightness.',
                    'duration' => 75,
                    'price' => 110.00,
                    'buffer_time' => 15,
                    'category' => 'Spa & Relax',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Evening Glam Makeup',
                    'description' => 'Bold, contour-heavy aesthetic for evening events.',
                    'duration' => 60,
                    'price' => 80.00,
                    'buffer_time' => 15,
                    'category' => 'Makeup',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Gel Nail Extensions',
                    'description' => 'Full set of durable sculpted gel nails.',
                    'duration' => 90,
                    'price' => 65.00,
                    'buffer_time' => 15,
                    'category' => 'Nails',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1632345031435-8727f6897d53?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Guided Meditation Hour',
                    'description' => 'Mindfulness and deep relaxation training.',
                    'duration' => 60,
                    'price' => 20.00,
                    'buffer_time' => 10,
                    'category' => 'Wellness',
                    'capacity' => 15,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1599447421416-3414500d18e5?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Full Body Scrub & Glow',
                    'description' => 'Sea salt exfoliation followed by deep moisturizing.',
                    'duration' => 45,
                    'price' => 70.00,
                    'buffer_time' => 15,
                    'category' => 'Spa & Relax',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Eyebrow Shaping & Tinting',
                    'description' => 'Precision mapping and semi-permanent brow tint.',
                    'duration' => 30,
                    'price' => 30.00,
                    'buffer_time' => 10,
                    'category' => 'Makeup',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1516979187457-637abb4f9353?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Scalp Revitalization',
                    'description' => 'Exfoliating treatment to enhance natural hair growth.',
                    'duration' => 45,
                    'price' => 55.00,
                    'buffer_time' => 10,
                    'category' => 'Hair',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1582095133179-bf108e2fc6b9?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Thai Massage Therapy',
                    'description' => 'Dynamic stretching and joint pressure application.',
                    'duration' => 90,
                    'price' => 120.00,
                    'buffer_time' => 15,
                    'category' => 'Spa & Relax',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Holistic Wellness Consultation',
                    'description' => 'Comprehensive analysis of nutrition and routines.',
                    'duration' => 60,
                    'price' => 95.00,
                    'buffer_time' => 15,
                    'category' => 'Wellness',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                )
            );
            foreach ($dummy_services as $srv) {
                $wpdb->insert($table_services, $srv);
            }
            echo '<div class="notice notice-success is-dismissible"><p>Fully loaded dummy services seamlessly injected.</p></div>';
            $action = 'list';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wsb_service_nonce']) && wp_verify_nonce($_POST['wsb_service_nonce'], 'wsb_add_service')) {
            $data = array(
                'name' => sanitize_text_field($_POST['service_name']),
                'description' => wp_kses_post($_POST['service_description']),
                'duration' => intval($_POST['service_duration']),
                'price' => floatval($_POST['service_price']),
                'buffer_time' => intval($_POST['service_buffer_time']),
                'category' => sanitize_text_field($_POST['service_category']),
                'capacity' => intval($_POST['service_capacity']),
                'image_url' => esc_url_raw($_POST['service_image_url']),
                'gallery_urls' => sanitize_text_field($_POST['service_gallery_urls']),
                'status' => 'active'
            );

            if ($service_id) {
                // Edit existing
                $wpdb->update($table_services, $data, array('id' => $service_id));
                echo '<div class="notice notice-success is-dismissible"><p>Service updated successfully!</p></div>';
            } else {
                // Add new
                $wpdb->insert($table_services, $data);
                $service_id = $wpdb->insert_id;
                echo '<div class="notice notice-success is-dismissible"><p>Service created successfully!</p></div>';
            }

            // Sync staff
            $assigned_staff = isset($_POST['assigned_staff']) ? array_map('intval', $_POST['assigned_staff']) : [];
            $table_staff_services = $wpdb->prefix . 'wsb_staff_services';
            $wpdb->delete($table_staff_services, array('service_id' => $service_id));
            foreach ($assigned_staff as $staff_id) {
                $wpdb->insert($table_staff_services, array('staff_id' => $staff_id, 'service_id' => $service_id, 'custom_price' => $data['price']));
            }

            $action = 'list';
        }

        if ($action === 'add' || $action === 'edit') {
            $staff_members = $wpdb->get_results("SELECT id, name FROM $table_staff");
            $service = null;
            $assigned_staff_ids = [];

            if ($action === 'edit' && $service_id) {
                $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_services WHERE id = %d", $service_id));
                $staff_relations = $wpdb->get_results($wpdb->prepare("SELECT staff_id FROM {$wpdb->prefix}wsb_staff_services WHERE service_id = %d", $service_id));
                foreach ($staff_relations as $sr)
                    $assigned_staff_ids[] = $sr->staff_id;
            }

            ?>
            <div class="wrap wsb-admin-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h1 style="margin:0;"><?php echo $action === 'edit' ? 'Manage Service' : 'Add New Service'; ?></h1>
                    <a href="?page=wsb_main&tab=services" class="wsb-btn-primary" style="background:var(--wsb-border);">Back to
                        Services</a>
                </div>
                <hr class="wp-header-end" style="margin-bottom:20px;">

                <form method="post" action="">
                    <?php wp_nonce_field('wsb_add_service', 'wsb_service_nonce'); ?>
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px;">

                        <!-- Main Panel -->
                        <div
                            style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--wsb-border); border-top: 4px solid var(--wsb-primary);">
                            <h3
                                style="margin-top:0; color:var(--wsb-primary); font-size:18px; display:flex; align-items:center; gap:8px;">
                                <span class="dashicons dashicons-edit"></span> Basic Information</h3>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Service Name</label>
                                <input name="service_name" type="text"
                                    value="<?php echo $service ? esc_attr($service->name) : ''; ?>"
                                    style="width:100%; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; background:#0f172a; color:white; font-size:16px;"
                                    required>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Category</label>
                                <input name="service_category" type="text"
                                    value="<?php echo $service ? esc_attr($service->category) : ''; ?>"
                                    style="width:100%; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; background:#0f172a; color:white;">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Description</label>
                                <textarea name="service_description" rows="4"
                                    style="width:100%; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; background:#0f172a; color:white;"><?php echo $service ? esc_textarea($service->description) : ''; ?></textarea>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Featured Image</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <div id="wsb-featured-preview"
                                        style="width:60px; height:60px; border-radius:6px; border:1px dashed var(--wsb-border); background:#0f172a <?php echo $service && $service->image_url ? 'url(' . esc_url($service->image_url) . ') center/cover' : ''; ?>;">
                                    </div>
                                    <input type="hidden" name="service_image_url" id="service_image_url"
                                        value="<?php echo $service ? esc_url($service->image_url) : ''; ?>">
                                    <button type="button" class="wsb-btn-primary wsb-select-image" data-target="#service_image_url"
                                        data-preview="#wsb-featured-preview"
                                        style="background:var(--wsb-border); color:white;">Select Image</button>
                                </div>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Service Gallery
                                    (Multiple Images)</label>
                                <input type="hidden" name="service_gallery_urls" id="service_gallery_urls"
                                    value="<?php echo $service && isset($service->gallery_urls) ? esc_attr($service->gallery_urls) : ''; ?>">
                                <div id="wsb-gallery-preview" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                                    <?php
                                    if ($service && !empty($service->gallery_urls)) {
                                        $urls = explode(',', $service->gallery_urls);
                                        foreach ($urls as $url) {
                                            echo '<div style="width:50px; height:50px; border-radius:4px; background:url(' . esc_url($url) . ') center/cover; border:1px solid #334155;"></div>';
                                        }
                                    }
                                    ?>
                                </div>
                                <button type="button" class="wsb-btn-primary wsb-select-gallery"
                                    style="background:var(--wsb-border); color:white;">Select Gallery Images</button>
                            </div>
                        </div>

                        <!-- Side Panel -->
                        <div>
                            <div
                                style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--wsb-border); margin-bottom: 20px; border-top: 4px solid var(--wsb-success);">
                                <h3
                                    style="margin-top:0; color:var(--wsb-success); font-size:18px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-money-alt"></span> Pricing & Duration</h3>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom: 15px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Price
                                            ($)</label>
                                        <input name="service_price" type="number" step="0.01"
                                            value="<?php echo $service ? esc_attr($service->price) : '0.00'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; font-weight:bold;"
                                            required>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Duration
                                            (m)</label>
                                        <input name="service_duration" type="number"
                                            value="<?php echo $service ? esc_attr($service->duration) : '30'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px;"
                                            required>
                                    </div>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Buffer
                                            (m)</label>
                                        <input name="service_buffer_time" type="number"
                                            value="<?php echo $service ? esc_attr($service->buffer_time) : '0'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px;">
                                    </div>
                                    <div>
                                        <label
                                            style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Capacity</label>
                                        <input name="service_capacity" type="number"
                                            value="<?php echo $service ? esc_attr($service->capacity) : '1'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px;"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <div
                                style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--wsb-border); border-top: 4px solid var(--wsb-warning);">
                                <h3
                                    style="margin-top:0; color:var(--wsb-warning); font-size:18px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-groups"></span> Assign Staff</h3>
                                <?php if (!empty($staff_members)): ?>
                                    <div style="max-height:150px; overflow-y:auto; padding-right:10px;">
                                        <?php foreach ($staff_members as $staff): ?>
                                            <label style="display:block; margin-bottom:8px;">
                                                <input type="checkbox" name="assigned_staff[]" value="<?php echo esc_attr($staff->id); ?>"
                                                    <?php echo in_array($staff->id, $assigned_staff_ids) ? 'checked' : ''; ?>>
                                                <?php echo esc_html($staff->name); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="description" style="color:var(--wsb-warning);">No staff members found.</p>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="wsb-btn-primary"
                                style="width:100%; margin-top:20px; padding:15px; font-size:16px;">
                                <?php echo $action === 'edit' ? 'Update Service' : 'Publish Service'; ?>
                            </button>
                        </div>

                    </div>
                </form>
            </div>
            <?php
        } else {
            $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
            $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : (isset($_POST['s']) ? $_POST['s'] : '');
            $filter = isset($_GET['cat']) ? sanitize_text_field($_GET['cat']) : (isset($_POST['cat']) ? $_POST['cat'] : '');

            $query = "SELECT * FROM $table_services WHERE 1=1";
            if ($search)
                $query .= $wpdb->prepare(" AND name LIKE %s", '%' . $wpdb->esc_like($search) . '%');
            if ($filter)
                $query .= $wpdb->prepare(" AND category = %s", $filter);
            if ($filter_status === 'active')
                $query .= " AND status = 'active'";
            if ($filter_status === 'inactive')
                $query .= " AND status = 'inactive'";
            $query .= " ORDER BY created_at DESC";

            $services = $wpdb->get_results($query);
            $categories = $wpdb->get_col("SELECT DISTINCT category FROM $table_services WHERE category != ''");

            // Meta Card Metrics
            $total_services = $wpdb->get_var("SELECT COUNT(*) FROM {$table_services}");
            $active_services = $wpdb->get_var("SELECT COUNT(*) FROM {$table_services} WHERE status='active'");
            $inactive_services = $wpdb->get_var("SELECT COUNT(*) FROM {$table_services} WHERE status='inactive'");
            $page_url = "?page=" . esc_attr($this->plugin_name . '-services');
            ?>
            <div class="wrap wsb-admin-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h1 style="margin:0;">Services Repository</h1>
                    <div>
                        <a href="<?php echo wp_nonce_url("?page=" . $this->plugin_name . "-services&action=seed", 'seed_services'); ?>"
                            class="wsb-btn-primary" style="background:var(--wsb-warning); margin-right:10px;">⚡ Inject Dummy
                            Services</a>
                        <a href="?page=wsb_main&tab=services&action=add" class="wsb-btn-primary">+ Add New Service</a>
                    </div>
                </div>

                <style>
                    .service-filter-card {
                        border-left: 4px solid transparent;
                        text-decoration: none;
                        color: inherit;
                        display: block;
                        border: 1px solid var(--wsb-border);
                        border-radius: 12px;
                        background: var(--wsb-panel-dark);
                        padding: 20px;
                        transition: transform 0.2s;
                    }

                    .service-filter-card:hover {
                        transform: translateY(-3px);
                        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
                    }

                    .card-active {
                        background: rgba(59, 130, 246, 0.1) !important;
                        border-left: 4px solid var(--wsb-primary) !important;
                        border-color: var(--wsb-primary) !important;
                    }
                </style>
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top:20px; margin-bottom:20px;">
                    <a href="<?php echo $page_url; ?>&filter_status=all"
                        class="service-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-text-muted);">Total Services</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                            <?php echo intval($total_services); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=active"
                        class="service-filter-card <?php echo $filter_status === 'active' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-success);">Live Offerings</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                            <?php echo intval($active_services); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=inactive"
                        class="service-filter-card <?php echo $filter_status === 'inactive' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-warning);">Draft / Inactive</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                            <?php echo intval($inactive_services); ?></p>
                    </a>
                </div>

                <form method="get" action="" style="display:flex; gap:10px; margin-bottom:20px;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                    <?php if ($filter_status !== 'all'): ?>
                        <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">
                    <?php endif; ?>
                    <input type="text" name="s" placeholder="Search services..." value="<?php echo esc_attr($search); ?>"
                        style="background:#0f172a; border:1px solid var(--wsb-border); color:white; padding:8px 12px; border-radius:6px; flex-grow:1;">
                    <select name="cat"
                        style="background:#0f172a; border:1px solid var(--wsb-border); color:white; padding:8px 12px; border-radius:6px;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat); ?>" <?php selected($filter, $cat); ?>>
                                <?php echo esc_html($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="wsb-btn-primary" style="padding:8px 20px;">Filter</button>
                    <?php if ($search || $filter): ?>
                        <a href="?page=wsb_main&tab=services" class="wsb-btn-primary" style="background:var(--wsb-danger);">Clear</a>
                    <?php endif; ?>
                </form>

                <table class="wsb-modern-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">Image</th>
                            <th>Service Name</th>
                            <th>Pricing & Duration</th>
                            <th>Category</th>
                            <th align="right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($services)): ?>
                            <?php foreach ($services as $service): ?>
                                <tr class="wsb-clickable-row"
                                    data-href="?page=wsb_main&tab=services&action=edit&id=<?php echo $service->id; ?>">
                                    <td>
                                        <?php if (!empty($service->image_url)): ?>
                                            <div
                                                style="width:40px; height:40px; border-radius:6px; background:url('<?php echo esc_url($service->image_url); ?>') center/cover; border:1px solid var(--wsb-border);">
                                            </div>
                                        <?php else: ?>
                                            <div
                                                style="width:40px; height:40px; border-radius:6px; background:var(--wsb-border); display:flex; align-items:center; justify-content:center; color:var(--wsb-text-muted); font-size:20px;">
                                                ✂️</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span class="wsb-customer-name"
                                                style="font-size:15px;"><?php echo esc_html($service->name); ?></span>
                                            <span class="wsb-customer-meta">Cap: <?php echo esc_html($service->capacity); ?> | Buffer:
                                                <?php echo esc_html($service->buffer_time); ?>m</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span class="wsb-customer-name"><?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo esc_html($service->price); ?></span>
                                            <span class="wsb-customer-meta"><?php echo esc_html($service->duration); ?> minutes</span>
                                        </div>
                                    </td>
                                    <td><span
                                            style="background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:12px;"><?php echo esc_html($service->category ?: 'Uncategorized'); ?></span>
                                    </td>
                                    <td align="right">
                                        <div class="wsb-row-actions">
                                            <a href="?page=wsb_main&tab=services&action=edit&id=<?php echo $service->id; ?>"
                                                class="wsb-row-action wsb-action-edit" title="Edit Service">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </a>
                                            <a href="?page=wsb_main&tab=services&action=delete&id=<?php echo $service->id; ?>"
                                                class="wsb-row-action wsb-action-delete" title="Delete Service"
                                                onclick="return confirm('Are you sure you want to completely delete this service?');">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path
                                                        d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                                    </path>
                                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 40px; color: var(--wsb-text-muted);">No services
                                    match your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    }

    public function display_staff_page()
    {
        global $wpdb;
        $table_staff = $wpdb->prefix . 'wsb_staff';

        // Auto-patch schema for advanced fields
        $wpdb->hide_errors();
        $wpdb->query("ALTER TABLE {$table_staff} ADD COLUMN schedule_config text AFTER description");
        $wpdb->query("ALTER TABLE {$table_staff} ADD COLUMN holidays text AFTER schedule_config");
        $wpdb->query("ALTER TABLE {$table_staff} ADD COLUMN image_url text AFTER holidays");
        $wpdb->query("ALTER TABLE {$table_staff} ADD COLUMN qualification text AFTER image_url");
        $wpdb->query("ALTER TABLE {$table_staff} ADD COLUMN address text AFTER qualification");
        $wpdb->show_errors();

        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        // Delete Handler
        if ($action === 'delete' && $staff_id && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_staff_' . $staff_id)) {
            $wpdb->delete($table_staff, array('id' => $staff_id));
            echo '<div class="notice notice-success is-dismissible"><p>Staff record purged from the system.</p></div>';
            $action = 'list';
        }

        // Seed Dummy Data Handler
        if ($action === 'seed' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'seed_staff')) {
            $dummy_staff = array(
                array(
                    'name' => 'Alexander Pierce',
                    'email' => 'alex@example.com',
                    'phone' => '555-0102',
                    'status' => 'active',
                    'description' => 'Master barber with 10 years of experience in classic cuts and hot towel shaves.',
                    'qualification' => 'Master Barber',
                    'address' => '123 Main St, Suite 100',
                    'image_url' => 'https://ui-avatars.com/api/?name=Alexander+Pierce&background=0D8ABC&color=fff&size=200',
                    'schedule_config' => '{"mon":{"active":"1","start":"09:00","end":"17:00"},"tue":{"active":"1","start":"09:00","end":"17:00"},"wed":{"active":"1","start":"09:00","end":"17:00"},"thu":{"active":"1","start":"09:00","end":"17:00"},"fri":{"active":"1","start":"09:00","end":"17:00"}}'
                ),
                array(
                    'name' => 'Sophia Lauren',
                    'email' => 'sophia@example.com',
                    'phone' => '555-0199',
                    'status' => 'active',
                    'description' => 'Expert colorist specializing in balayage and creative lifting.',
                    'qualification' => 'Senior Colorist',
                    'address' => '456 Styling Ave',
                    'image_url' => 'https://ui-avatars.com/api/?name=Sophia+Lauren&background=D81B60&color=fff&size=200',
                    'schedule_config' => '{"mon":{"active":"1","start":"10:00","end":"18:00"},"tue":{"active":"1","start":"10:00","end":"18:00"},"thu":{"active":"1","start":"10:00","end":"18:00"},"sat":{"active":"1","start":"08:00","end":"14:00"}}'
                ),
                array(
                    'name' => 'Marcus Reed',
                    'email' => 'marcus@example.com',
                    'phone' => '555-0211',
                    'status' => 'inactive',
                    'description' => 'Specializes in therapeutic massages and deep tissue recovery.',
                    'qualification' => 'Licensed Massage Therapist',
                    'address' => '789 Recovery Blvd',
                    'image_url' => 'https://ui-avatars.com/api/?name=Marcus+Reed&background=43A047&color=fff&size=200',
                    'schedule_config' => '{"mon":{"active":"1","start":"08:00","end":"14:00"},"wed":{"active":"1","start":"12:00","end":"20:00"},"fri":{"active":"1","start":"08:00","end":"16:00"}}'
                )
            );
            foreach ($dummy_staff as $st) {
                $wpdb->insert($table_staff, $st);
            }
            echo '<div class="notice notice-success is-dismissible"><p>Fully loaded dummy staff successfully injected into roster.</p></div>';
            $action = 'list';
        }

        // Form Submit Handler
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wsb_staff_nonce']) && wp_verify_nonce($_POST['wsb_staff_nonce'], 'wsb_staff_save')) {
            $schedule_data = isset($_POST['schedule']) ? $_POST['schedule'] : array();
            $data = array(
                'name' => sanitize_text_field($_POST['staff_name']),
                'email' => sanitize_email($_POST['staff_email']),
                'phone' => sanitize_text_field($_POST['staff_phone']),
                'status' => sanitize_text_field($_POST['status']),
                'description' => sanitize_textarea_field($_POST['description']),
                'schedule_config' => wp_json_encode($schedule_data),
                'holidays' => sanitize_textarea_field($_POST['holidays']),
                'image_url' => esc_url_raw($_POST['staff_image_url']),
                'qualification' => sanitize_text_field($_POST['staff_qualification']),
                'address' => sanitize_textarea_field($_POST['staff_address'])
            );

            if ($staff_id) {
                $wpdb->update($table_staff, $data, array('id' => $staff_id));
                echo '<div class="notice notice-success is-dismissible"><p>Staff profile successfully updated.</p></div>';
            } else {
                $wpdb->insert($table_staff, $data);
                echo '<div class="notice notice-success is-dismissible"><p>New Staff member securely created.</p></div>';
            }
            $action = 'list';
        }

        if (in_array($action, ['add', 'edit'])) {
            $s = null;
            $schedule = array();
            if ($action === 'edit' && $staff_id) {
                $s = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_staff} WHERE id = %d", $staff_id));
                $schedule = json_decode($s->schedule_config, true) ?: array();

                // Fetch Performance Data for this provider
                $perf_bookings = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_bookings WHERE staff_id = %d AND (status = 'confirmed' OR status = 'completed')", $staff_id));
                $perf_revenue = $wpdb->get_var($wpdb->prepare("SELECT SUM(total_amount) FROM {$wpdb->prefix}wsb_bookings WHERE staff_id = %d AND (status = 'confirmed' OR status = 'completed')", $staff_id)) ?: 0;
            }
            $days = array('mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday');
            ?>
            <div class="wrap wsb-admin-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
                    <h1 style="margin:0; font-size:24px; color:#fff;">Manage Staff Profile</h1>
                    <a href="?page=wsb_main&tab=staff" class="wsb-btn-primary" style="background:var(--wsb-border);">Back to
                        Roster</a>
                </div>
                <hr class="wp-header-end" style="margin-bottom:20px;">

                <form method="post"
                    action="?page=wsb_main&tab=staff&action=<?php echo $action; ?><?php echo $staff_id ? '&id=' . $staff_id : ''; ?>">
                    <?php wp_nonce_field('wsb_staff_save', 'wsb_staff_nonce'); ?>

                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
                        <div>
                            <!-- Core Identity -->
                            <div
                                style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid var(--wsb-primary); margin-bottom:20px;">
                                <h3 style="margin-top:0; color:var(--wsb-primary); display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-admin-users"></span> Personal Information
                                </h3>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Full
                                            Name</label>
                                        <input name="staff_name" type="text" value="<?php echo $s ? esc_attr($s->name) : ''; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;"
                                            required>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Profession /
                                            Qualification</label>
                                        <input name="staff_qualification" type="text"
                                            placeholder="e.g. Senior Hairstylist, Master Technician"
                                            value="<?php echo $s && isset($s->qualification) ? esc_attr($s->qualification) : ''; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;">
                                    </div>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Email
                                            Address</label>
                                        <input name="staff_email" type="email" value="<?php echo $s ? esc_attr($s->email) : ''; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;"
                                            required>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Phone
                                            Number</label>
                                        <input name="staff_phone" type="text" value="<?php echo $s ? esc_attr($s->phone) : ''; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;">
                                    </div>
                                </div>
                                <div style="margin-bottom:15px;">
                                    <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Physical
                                        Address</label>
                                    <textarea name="staff_address" rows="2"
                                        style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;"><?php echo $s && isset($s->address) ? esc_textarea($s->address) : ''; ?></textarea>
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Public Biography /
                                        Description</label>
                                    <textarea name="description" rows="3"
                                        style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;"><?php echo $s ? esc_textarea($s->description) : ''; ?></textarea>
                                </div>
                            </div>

                            <!-- Schedule Settings -->
                            <div
                                style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid var(--wsb-warning);">
                                <h3 style="margin-top:0; color:var(--wsb-warning); display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-calendar-alt"></span> Weekly Schedule
                                </h3>
                                <p style="color:var(--wsb-text-muted); font-size:13px; margin-bottom:15px;">Configure the working
                                    hours for this provider. If not working on a day, leave times blank or uncheck.</p>

                                <?php foreach ($days as $key => $label):
                                    $is_working = isset($schedule[$key]['active']) && $schedule[$key]['active'] == '1';
                                    $start = isset($schedule[$key]['start']) ? $schedule[$key]['start'] : '09:00';
                                    $end = isset($schedule[$key]['end']) ? $schedule[$key]['end'] : '17:00';
                                    ?>
                                    <div
                                        style="display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);">
                                        <label style="display:flex; align-items:center; gap:10px; width:150px; cursor:pointer;">
                                            <input type="checkbox" name="schedule[<?php echo $key; ?>][active]" value="1" <?php checked($is_working); ?>
                                                style="background:#0f172a; border:1px solid var(--wsb-primary);">
                                            <strong
                                                style="color:<?php echo $is_working ? 'white' : 'var(--wsb-text-muted)'; ?>"><?php echo $label; ?></strong>
                                        </label>
                                        <div
                                            style="display:flex; align-items:center; gap:10px; opacity:<?php echo $is_working ? '1' : '0.4'; ?>;">
                                            <input type="time" name="schedule[<?php echo $key; ?>][start]"
                                                value="<?php echo esc_attr($start); ?>"
                                                style="background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:4px 8px; border-radius:4px;">
                                            <span style="color:var(--wsb-text-muted);">to</span>
                                            <input type="time" name="schedule[<?php echo $key; ?>][end]"
                                                value="<?php echo esc_attr($end); ?>"
                                                style="background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:4px 8px; border-radius:4px;">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <!-- Performance Metrics Card -->
                            <?php if ($s): ?>
                                <div
                                    style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid var(--wsb-success); margin-bottom:20px;">
                                    <h3 style="margin-top:0; color:var(--wsb-success); font-size:16px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-chart-line"></span> Performance Insights
                                </h3>
                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                        <div style="background:rgba(16, 185, 129, 0.05); padding:15px; border-radius:8px; border:1px solid rgba(16, 185, 129, 0.1);">
                                            <span style="display:block; font-size:12px; color:var(--wsb-text-muted); margin-bottom:5px;">Total Revenue</span>
                                            <strong style="font-size:20px; color:#fff;"><?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo number_format($perf_revenue, 2); ?></strong>
                                        </div>
                                        <div style="background:rgba(59, 130, 246, 0.05); padding:15px; border-radius:8px; border:1px solid rgba(59, 130, 246, 0.1);">
                                            <span style="display:block; font-size:12px; color:var(--wsb-text-muted); margin-bottom:5px;">Sessions</span>
                                            <strong style="font-size:20px; color:#fff;"><?php echo intval($perf_bookings); ?></strong>
                                        </div>
                                    </div>
                                    <div style="margin-top:15px;">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                                            <span style="font-size:12px; color:var(--wsb-text-muted);">Productivity Score</span>
                                            <span style="font-size:12px; font-weight:bold; color:var(--wsb-primary);"><?php echo min(100, round(($perf_revenue / 1000) * 100)); ?>%</span>
                                        </div>
                                        <div style="width:100%; height:6px; background:rgba(255,255,255,0.05); border-radius:10px; overflow:hidden;">
                                            <div style="width:<?php echo min(100, ($perf_revenue / 1000) * 100); ?>%; height:100%; background:var(--wsb-primary); border-radius:10px;"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Side Panel - Image/Status -->
                            <div
                                style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); margin-bottom:20px;">
                                <h3 style="margin-top:0; color:#fff; font-size:16px;">Profile Image</h3>
                                <div style="display:flex; flex-direction:column; gap:10px; align-items:center; margin-bottom:15px;">
                                    <div id="wsb-staff-preview"
                                        style="width:140px; height:140px; border-radius:50%; border:4px solid #fff; box-shadow:0 10px 25px rgba(0,0,0,0.5); background:#fff <?php echo $s && isset($s->image_url) && $s->image_url ? 'url(' . esc_url($s->image_url) . ') center/cover' : ''; ?>;">
                                    </div>
                                    <input type="hidden" name="staff_image_url" id="staff_image_url"
                                        value="<?php echo $s && isset($s->image_url) ? esc_url($s->image_url) : ''; ?>">
                                    <button type="button" class="wsb-btn-primary wsb-select-image" data-target="#staff_image_url"
                                        data-preview="#wsb-staff-preview"
                                        style="background:var(--wsb-border); color:white; width:100%;">Select Avatar</button>
                                </div>
                                <hr style="border:0; border-top:1px solid var(--wsb-border); margin:15px 0;">
                                <div style="margin-bottom:15px;">
                                    <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">System
                                        Status</label>
                                    <select name="status"
                                        style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;">
                                        <option value="active" <?php selected($s ? $s->status : 'active', 'active'); ?>>Active
                                            (Accepting Bookings)</option>
                                        <option value="inactive" <?php selected($s ? $s->status : '', 'inactive'); ?>>Inactive
                                            (Hidden)</option>
                                    </select>
                                </div>
                                <button type="submit" class="wsb-btn-primary"
                                    style="width:100%; padding:12px; font-size:16px; background:var(--wsb-success);">Save Staff
                                    Configuration</button>
                            </div>

                            <div
                                style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid #ef4444;">
                                <h3 style="margin-top:0; color:#ef4444; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-palmtree"></span> Time off & Holidays
                                </h3>
                                <p style="color:var(--wsb-text-muted); font-size:13px;">Enter exact dates where this staff member is
                                    unavailable. Use YYYY-MM-DD format on a new line for each date.</p>
                                <textarea name="holidays" rows="5"
                                    style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;"
                                    placeholder="2026-12-25&#10;2026-11-28"><?php echo $s ? esc_textarea($s->holidays) : ''; ?></textarea>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personalized Booking Calendar -->
                    <?php if ($s): ?>
                        <div style="margin-top:30px;">
                            <div style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid var(--wsb-primary);">
                                <h3 style="margin:0 0 20px 0; color:#fff; display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-calendar"></span> <?php echo esc_html($s->name); ?>'s Booking Schedule
                                </h3>
                                <div id="wsb-staff-calendar" style="min-height:500px;"></div>
                            </div>

                            <script>
                                (function() {
                                    var initStaffCalendar = function() {
                                        if (typeof FullCalendar === 'undefined') {
                                            console.warn('FullCalendar not loaded yet, retrying...');
                                            setTimeout(initStaffCalendar, 200);
                                            return;
                                        }

                                        var calendarEl = document.getElementById('wsb-staff-calendar');
                                        if (!calendarEl || calendarEl.classList.contains('fc')) return;

                                        <?php
                                        $staff_bookings = $wpdb->get_results($wpdb->prepare("
                                            SELECT b.*, c.first_name, c.last_name, s.name as service_name
                                            FROM {$wpdb->prefix}wsb_bookings b
                                            LEFT JOIN {$wpdb->prefix}wsb_customers c ON b.customer_id = c.id
                                            LEFT JOIN {$wpdb->prefix}wsb_services s ON b.service_id = s.id
                                            WHERE b.staff_id = %d AND b.status != 'cancelled'
                                        ", $s->id));
                                        ?>

                                        var events = [
                                            <?php foreach ($staff_bookings as $sb): ?>
                                            {
                                                title: '<?php echo esc_js($sb->first_name . " - " . $sb->service_name); ?>',
                                                start: '<?php echo esc_js($sb->booking_date); ?>T<?php echo esc_js($sb->start_time); ?>',
                                                end: '<?php echo esc_js($sb->booking_date); ?>T<?php echo esc_js($sb->end_time); ?>',
                                                color: '<?php echo $sb->status === 'confirmed' ? '#10b981' : '#f59e0b'; ?>',
                                                url: '<?php echo "?page=wsb_main&tab=bookings&action=edit&id=" . $sb->id; ?>'
                                            },
                                            <?php endforeach; ?>
                                        ];

                                        var calendar = new FullCalendar.Calendar(calendarEl, {
                                            initialView: 'timeGridWeek',
                                            headerToolbar: {
                                                left: 'prev,next today',
                                                center: 'title',
                                                right: 'dayGridMonth,timeGridWeek,timeGridDay'
                                            },
                                            events: events,
                                            slotMinTime: '07:00:00',
                                            slotMaxTime: '21:00:00',
                                            allDaySlot: false,
                                            height: 'auto'
                                        });
                                        
                                        setTimeout(function() {
                                            calendar.render();
                                            calendar.updateSize();
                                        }, 100);
                                    };

                                    // Initial load
                                    setTimeout(initStaffCalendar, 150);

                                    // Re-init on AJAX navigation
                                    jQuery(document).off('wsb-tab-loaded.staff_cal').on('wsb-tab-loaded.staff_cal', function(e, tab) {
                                        if (tab === 'staff') {
                                            setTimeout(initStaffCalendar, 150);
                                        }
                                    });
                                })();
                            </script>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            <?php
        } else {
            // View: List Filter Logic
            $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
            $where_clause = "WHERE 1=1";
            if (in_array($filter_status, ['active', 'inactive'])) {
                $where_clause .= " AND s.status = '{$filter_status}'";
            }

            $staff = $wpdb->get_results("
                SELECT s.*, 
                       COUNT(b.id) as booking_count,
                       IFNULL(SUM(b.total_amount), 0) as total_revenue
                FROM {$table_staff} s
                LEFT JOIN {$wpdb->prefix}wsb_bookings b ON s.id = b.staff_id AND (b.status = 'confirmed' OR b.status = 'completed')
                {$where_clause}
                GROUP BY s.id
                ORDER BY total_revenue DESC, s.created_at DESC
            ");
            $total_staff = $wpdb->get_var("SELECT COUNT(*) FROM {$table_staff}");
            $active_staff = $wpdb->get_var("SELECT COUNT(*) FROM {$table_staff} WHERE status='active'");
            $inactive_staff = $wpdb->get_var("SELECT COUNT(*) FROM {$table_staff} WHERE status='inactive'");

            $page_url = "?page=" . esc_attr($this->plugin_name . '-staff');
            ?>
            <div class="wrap wsb-admin-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h1 style="margin:0;">Staff Roster</h1>
                    <div>
                        <a href="<?php echo wp_nonce_url("?page=" . $this->plugin_name . "-staff&action=seed", 'seed_staff'); ?>"
                            class="wsb-btn-primary" style="background:var(--wsb-warning); margin-right:10px;">⚡ Inject Dummy
                            Staff</a>
                        <a href="?page=wsb_main&tab=staff&action=add" class="wsb-btn-primary">+ Onboard Staff</a>
                    </div>
                </div>

                <style>
                    .staff-filter-card {
                        border-left: 4px solid transparent;
                        text-decoration: none;
                        color: inherit;
                        display: block;
                        border: 1px solid var(--wsb-border);
                        border-radius: 12px;
                        background: var(--wsb-panel-dark);
                        padding: 20px;
                        transition: transform 0.2s;
                    }

                    .staff-filter-card:hover {
                        transform: translateY(-3px);
                        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
                    }

                    .card-active {
                        background: rgba(59, 130, 246, 0.1) !important;
                        border-left: 4px solid var(--wsb-primary) !important;
                        border-color: var(--wsb-primary) !important;
                    }
                </style>
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top:20px; margin-bottom:20px;">
                    <a href="<?php echo $page_url; ?>&filter_status=all"
                        class="staff-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-text-muted);">Total Staff</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                            <?php echo intval($total_staff); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=active"
                        class="staff-filter-card <?php echo $filter_status === 'active' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-success);">Active Providers</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                            <?php echo intval($active_staff); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=inactive"
                        class="staff-filter-card <?php echo $filter_status === 'inactive' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-warning);">Inactive / On Leave</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                            <?php echo intval($inactive_staff); ?></p>
                    </a>
                </div>

                <div
                    style="background: var(--wsb-panel-dark); border-radius: 12px; border: 1px solid var(--wsb-border); overflow: hidden;">
                    <table class="wsb-modern-table" style="margin:0; width:100%;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact details</th>
                                <th>Status</th>
                                <th style="text-align:center;">Performance Index</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($staff)):
                                foreach ($staff as $s): ?>
                                    <tr class="wsb-clickable-row" data-href="?page=wsb_main&tab=staff&action=edit&id=<?php echo $s->id; ?>">
                                        <td>
                                            <div style="display:flex; align-items:center; gap:15px;">
                                                <?php if (!empty($s->image_url)): ?>
                                                    <div
                                                        style="width:40px; height:40px; border-radius:50%; background:url('<?php echo esc_url($s->image_url); ?>') center/cover; border:2px solid var(--wsb-border);">
                                                    </div>
                                                <?php else: ?>
                                                    <div
                                                        style="width:40px; height:40px; border-radius:50%; background:var(--wsb-border); display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:16px;">
                                                        <?php echo esc_html(strtoupper(substr($s->name, 0, 1))); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong
                                                        style="color:white; font-size:15px; display:block;"><?php echo esc_html($s->name); ?></strong>
                                                    <?php if (!empty($s->qualification)): ?>
                                                        <span
                                                            style="color:var(--wsb-primary); font-size:12px;"><?php echo esc_html($s->qualification); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="wsb-customer-info">
                                                <span style="color:var(--wsb-text-muted); font-size:13px;">✉️
                                                    <?php echo esc_html($s->email); ?></span>
                                                <span style="color:var(--wsb-text-muted); font-size:13px; margin-top:3px;">📞
                                                    <?php echo esc_html($s->phone ?: 'N/A'); ?></span>
                                            </div>
                                        </td>
                                        <td><span
                                                class="wsb-status wsb-status-<?php echo $s->status === 'active' ? 'completed' : 'cancelled'; ?>"><?php echo esc_html(ucfirst($s->status)); ?></span>
                                        </td>
                                        <td align="center">
                                            <div style="display:inline-flex; flex-direction:column; align-items:center; gap:5px;">
                                                <div style="display:flex; align-items:center; gap:8px;">
                                                    <span style="color:var(--wsb-success); font-weight:bold; font-size:14px;">
                                                        <?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo number_format($s->total_revenue, 2); ?>
                                                    </span>
                                                    <span style="color:var(--wsb-text-muted); font-size:11px;">(<?php echo intval($s->booking_count); ?> sessions)</span>
                                                </div>
                                                <!-- Performance Bar -->
                                                <?php 
                                                $max_rev = 1000; // Benchmark for 100%
                                                $perc = min(100, ($s->total_revenue / $max_rev) * 100);
                                                ?>
                                                <div style="width:120px; height:6px; background:rgba(255,255,255,0.05); border-radius:10px; overflow:hidden;">
                                                    <div style="width:<?php echo $perc; ?>%; height:100%; background:linear-gradient(90deg, #6366f1, #10b981); border-radius:10px;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td align="right">
                                            <div class="wsb-row-actions">
                                                <a href="?page=wsb_main&tab=staff&action=edit&id=<?php echo $s->id; ?>"
                                                    class="wsb-row-action wsb-action-edit" title="Edit Staff Member">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                    </svg>
                                                </a>
                                                <a href="<?php echo wp_nonce_url("?page=" . $this->plugin_name . "-staff&action=delete&id=" . $s->id, 'delete_staff_' . $s->id); ?>"
                                                    class="wsb-row-action wsb-action-delete" title="Remove Staff Member"
                                                    onclick="return confirm('Are you sure you want to fire this staff member? This deletes their record entirely.');">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path
                                                            d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2">
                                                        </path>
                                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="4" style="padding:40px; text-align:center; color:var(--wsb-text-muted);">Roster is
                                        empty.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
        }
    }

    public function display_customers_page()
    {
        global $wpdb;
        $table_customers = $wpdb->prefix . 'wsb_customers';
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';

        // Seed Dummy Data Handler
        if ($action === 'seed' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'seed_customers')) {
            $dummies = array(
                array('first_name' => 'Emily', 'last_name' => 'Blunt', 'email' => 'emily@example.com', 'phone' => '(555) 123-4567'),
                array('first_name' => 'John', 'last_name' => 'Krasinski', 'email' => 'john@example.com', 'phone' => '(555) 987-6543'),
                array('first_name' => 'Margot', 'last_name' => 'Robbie', 'email' => 'margot@example.com', 'phone' => '(555) 222-3333'),
                array('first_name' => 'Ryan', 'last_name' => 'Gosling', 'email' => 'ryan@example.com', 'phone' => '(555) 444-5555')
            );
            foreach ($dummies as $d) {
                // To make them realistic, we'll randomize their joined date a bit within the last 40 days
                $random_days = rand(1, 40);
                $d['created_at'] = gmdate('Y-m-d H:i:s', strtotime("-{$random_days} days"));
                $wpdb->insert($table_customers, $d);
            }
            echo '<div class="notice notice-success is-dismissible"><p>Fully loaded dummy customers successfully injected into CRM.</p></div>';
            $action = 'list';
        }

        // Filter Matrix Engine
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $where_clause = "WHERE 1=1";
        if ($filter_status === 'recent') {
            $where_clause .= " AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        $having_clause = "";
        if ($filter_status === 'vip') {
            $having_clause = "HAVING total_spent >= 10 OR booking_count >= 1";
        }

        // Advanced Joined Query for Lifetime Value (LTV) Mapping
        $order_clause = $having_clause ? "total_spent DESC" : "c.created_at DESC";
        $query = "SELECT c.*, 
                         COUNT(b.id) as booking_count,
                         IFNULL(SUM(b.total_amount), 0) as total_spent
                  FROM {$table_customers} c
                  LEFT JOIN {$wpdb->prefix}wsb_bookings b ON c.id = b.customer_id
                  {$where_clause}
                  GROUP BY c.id
                  {$having_clause}
                  ORDER BY {$order_clause}";

        $customers = $wpdb->get_results($query);

        // Core Dashboard Metrics
        $total_customers = $wpdb->get_var("SELECT COUNT(*) FROM {$table_customers}");
        $recent_customers = $wpdb->get_var("SELECT COUNT(*) FROM {$table_customers} WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");

        $vip_customers = $wpdb->get_var("
            SELECT COUNT(*) FROM (
                SELECT c.id FROM {$table_customers} c
                LEFT JOIN {$wpdb->prefix}wsb_bookings b ON c.id = b.customer_id
                GROUP BY c.id
                HAVING SUM(b.total_amount) >= 10 OR COUNT(b.id) >= 1
            ) as vips
        ");

        $page_url = "?page=" . esc_attr($this->plugin_name . '-customers');
        ?>
        <div class="wrap wsb-admin-wrap">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h1 style="margin:0;">Client CRM & Directory</h1>
                <div>
                    <a href="<?php echo wp_nonce_url("?page=" . $this->plugin_name . "-customers&action=seed", 'seed_customers'); ?>"
                        class="wsb-btn-primary" style="background:var(--wsb-warning);">⚡ Inject Dummy Customers</a>
                </div>
            </div>

            <!-- Dashboard Interactive Filters -->
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top:20px; margin-bottom:20px;">
                <a href="<?php echo $page_url; ?>&filter_status=all"
                    class="customer-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--wsb-text-muted);">Total Client Base</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                        <?php echo intval($total_customers); ?></p>
                </a>
                <a href="<?php echo $page_url; ?>&filter_status=recent"
                    class="customer-filter-card <?php echo $filter_status === 'recent' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--wsb-success);">Recent Signups (30d)</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                        <?php echo intval($recent_customers); ?></p>
                </a>
                <a href="<?php echo $page_url; ?>&filter_status=vip"
                    class="customer-filter-card <?php echo $filter_status === 'vip' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--wsb-warning);">VIP Clients (LTV)</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                        <?php echo intval($vip_customers); ?></p>
                </a>
            </div>

            <div
                style="background: var(--wsb-panel-dark); border-radius: 12px; border: 1px solid var(--wsb-border); overflow: hidden;">
                <table class="wsb-modern-table" style="margin:0; width:100%;">
                    <thead>
                        <tr>
                            <th>Client Identity</th>
                            <th>Contact Information</th>
                            <th>Platform History</th>
                            <th style="text-align:right;">Lifetime Value (LTV)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customers)):
                            foreach ($customers as $c): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:15px;">
                                            <div
                                                style="width:40px; height:40px; border-radius:50%; background:var(--wsb-border); display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:16px;">
                                                <?php echo esc_html(strtoupper(substr($c->first_name, 0, 1) . substr($c->last_name, 0, 1))); ?>
                                            </div>
                                            <div>
                                                <strong
                                                    style="color:white; font-size:15px; display:block;"><?php echo esc_html($c->first_name . ' ' . $c->last_name); ?></strong>
                                                <span style="color:var(--wsb-text-muted); font-size:12px; font-family:monospace;">ID:
                                                    #<?php echo esc_html(str_pad($c->id, 5, '0', STR_PAD_LEFT)); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span style="color:var(--wsb-text-muted); font-size:13px;">✉️
                                                <?php echo esc_html($c->email); ?></span>
                                            <span style="color:var(--wsb-text-muted); font-size:13px; margin-top:3px;">📞
                                                <?php echo esc_html($c->phone ?: 'N/A'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color:var(--wsb-text-muted); font-size:13px; display:block;">Joined:
                                            <?php echo esc_html(date('M d, Y', strtotime($c->created_at))); ?></span>
                                        <span
                                            style="color:var(--wsb-primary); font-size:12px; font-weight:bold; margin-top:3px; display:block;">Bookings:
                                            <?php echo intval($c->booking_count); ?></span>
                                    </td>
                                    <td align="right">
                                        <strong
                                            style="color:var(--wsb-success); font-size:16px;"><?php echo $c->total_spent > 0 ? '$' . number_format($c->total_spent, 2) : '-'; ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 40px; color: var(--wsb-text-muted);">No client
                                    records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    public function display_finance_page()
    {
        global $wpdb;

        // Global Filter Logic
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'all';
        $where_date = "";
        $where_book_date = "";
        if ($period === 'today') {
            $where_date = " AND DATE(p.created_at) = CURDATE()";
            $where_book_date = " AND DATE(created_at) = CURDATE()";
        } elseif ($period === '7days') {
            $where_date = " AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $where_book_date = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        } elseif ($period === '30days') {
            $where_date = " AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            $where_book_date = " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($period === 'year') {
            $where_date = " AND YEAR(p.created_at) = YEAR(CURDATE())";
            $where_book_date = " AND YEAR(created_at) = YEAR(CURDATE())";
        }

        // Financial Metrics globally respecting filter
        $total_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}wsb_payments p WHERE status = 'completed' {$where_date}");
        $total_transactions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_payments p WHERE status = 'completed' {$where_date}");
        $avg_transaction = $total_transactions > 0 ? $total_revenue / $total_transactions : 0;
        $pending_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}wsb_payments p WHERE status = 'pending' {$where_date}");

        // Dynamic Chart Query respecting filter
        if (in_array($period, ['30days', '7days', 'today'])) {
            $chart_query = "SELECT DATE_FORMAT(created_at, '%b %d') as label, SUM(amount) as val FROM {$wpdb->prefix}wsb_payments p WHERE status='completed' {$where_date} GROUP BY label ORDER BY DATE(p.created_at) ASC";
        } else {
            $chart_query = "SELECT DATE_FORMAT(created_at, '%Y %b') as label, SUM(amount) as val FROM {$wpdb->prefix}wsb_payments p WHERE status='completed' {$where_date} GROUP BY label ORDER BY YEAR(p.created_at) ASC, MONTH(p.created_at) ASC";
        }
        $chart_data = $wpdb->get_results($chart_query);
        $chart_json = json_encode($chart_data);

        // Grid of Payments respecting filter
        $query = "SELECT p.*, b.total_amount, c.first_name, c.last_name, s.name as service_name
                  FROM {$wpdb->prefix}wsb_payments p
                  JOIN {$wpdb->prefix}wsb_bookings b ON p.booking_id = b.id
                  LEFT JOIN {$wpdb->prefix}wsb_customers c ON b.customer_id = c.id
                  LEFT JOIN {$wpdb->prefix}wsb_services s ON b.service_id = s.id
                  WHERE 1=1 {$where_date}
                  ORDER BY p.created_at DESC";
        $payments = $wpdb->get_results($query);

        ?>
        <div class="wrap wsb-admin-wrap">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h1 style="margin:0;">Financial Ledger & Revenue</h1>

                <!-- Master Dashboard Filter -->
                <form method="get" class="wsb-finance-filter-form" style="display:flex; align-items:center; gap:10px;">
                    <input type="hidden" name="page" value="wsb_main">
                    <input type="hidden" name="tab" value="finance">
                    <span style="color:var(--wsb-text-muted); font-size:14px;">Reporting Period:</span>
                    <select name="period"
                        style="background:#0f172a; color:white; border:1px solid var(--wsb-primary); padding:6px 12px; border-radius:6px; font-weight:bold;"
                        onchange="this.form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}))">
                        <option value="all" <?php selected($period, 'all'); ?>>All Time</option>
                        <option value="today" <?php selected($period, 'today'); ?>>Today</option>
                        <option value="7days" <?php selected($period, '7days'); ?>>Last 7 Days</option>
                        <option value="30days" <?php selected($period, '30days'); ?>>Last 30 Days</option>
                        <option value="year" <?php selected($period, 'year'); ?>>This Year</option>
                    </select>
                </form>
            </div>

            <!-- Metric Cards -->
            <div class="wsb-dashboard-grid"
                style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom:30px;">
                <div class="wsb-stat-card" style="border-left: 4px solid var(--wsb-success);">
                    <h3 style="margin-top:0; font-size:16px;">Total Realized Revenue</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--wsb-success);">
                        <?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo number_format((float) $total_revenue, 2); ?></p>
                </div>
                <div class="wsb-stat-card" style="border-left: 4px solid var(--wsb-primary);">
                    <h3 style="margin-top:0; font-size:16px;">Verified Transactions</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--wsb-primary);">
                        <?php echo intval($total_transactions); ?></p>
                </div>
                <div class="wsb-stat-card" style="border-left: 4px solid #10b981;">
                    <h3 style="margin-top:0; font-size:16px;">Avg. Transaction Value</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:#10b981;">
                        <?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo number_format((float) $avg_transaction, 2); ?></p>
                </div>
                <div class="wsb-stat-card" style="border-left: 4px solid var(--wsb-warning);">
                    <h3 style="margin-top:0; font-size:16px;">Pending / Outstanding</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--wsb-warning);">
                        <?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo number_format((float) $pending_revenue, 2); ?></p>
                </div>
            </div>

            <!-- Dynamic Chart -->
            <div
                style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); margin-bottom:30px;">
                <h3 style="margin:0 0 20px 0; color: #fff;">Revenue Performance Analysis</h3>
                <div style="position:relative; height:300px; width:100%;">
                    <canvas id="wsbRevenueChart" data-chart='<?php echo $chart_json; ?>'></canvas>
                </div>
            </div>

            <!-- Ledger Data Table -->
            <div
                style="background:var(--wsb-panel-dark); border-radius:12px; border:1px solid var(--wsb-border); overflow:hidden;">
                <div style="padding: 20px; border-bottom: 1px solid var(--wsb-border);">
                    <h3 style="margin:0; color: #fff;">Recent Activity</h3>
                </div>
                <!-- Scrollable Ledger Container -->
                <div style="max-height: 450px; overflow-y: auto;">
                    <table class="wsb-modern-table" style="margin:0; width: 100%;">
                        <thead style="position: sticky; top: 0; background: #0f172a; z-index: 10;">
                            <tr>
                                <th>Transaction ID</th>
                                <th>Gateway</th>
                                <th>Amount</th>
                                <th>Related Booking</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($payments)):
                                foreach ($payments as $p): ?>
                                    <tr class="wsb-clickable-row" data-href="?page=wsb_main&tab=services&action=edit&id=<?php echo $s->id; ?>">
                                        <td><strong
                                                style="color:var(--wsb-text-muted); font-family:monospace;"><?php echo esc_html($p->transaction_id ?: 'N/A'); ?></strong>
                                        </td>
                                        <td><span
                                                style="background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:12px;"><?php echo esc_html(strtoupper($p->gateway)); ?></span>
                                        </td>
                                        <td><strong
                                                style="color:var(--wsb-success);"><?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo number_format((float) $p->amount, 2); ?></strong>
                                        </td>
                                        <td>
                                            <div class="wsb-customer-info">
                                                <span class="wsb-customer-name" style="color:var(--wsb-primary);">Booking
                                                    #<?php echo esc_html(str_pad($p->booking_id, 5, '0', STR_PAD_LEFT)); ?></span>
                                                <span
                                                    class="wsb-customer-meta"><?php echo esc_html($p->first_name . ' ' . $p->last_name . ' - ' . $p->service_name); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html(date('M d, Y', strtotime($p->created_at))); ?></td>
                                        <td><span
                                                class="wsb-status wsb-status-<?php echo esc_attr($p->status); ?>"><?php echo esc_html(ucfirst($p->status)); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 40px; color: var(--wsb-text-muted);">No payment
                                        records found in this timeframe.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function display_settings_page()
    {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['wsb_settings_nonce']) && wp_verify_nonce($_POST['wsb_settings_nonce'], 'wsb_save_settings')) {
                update_option('wsb_currency', sanitize_text_field($_POST['wsb_currency']));

                // Payment Integrations
                update_option('wsb_stripe_publishable_key', sanitize_text_field($_POST['wsb_stripe_publishable_key']));
                update_option('wsb_stripe_secret_key', sanitize_text_field($_POST['wsb_stripe_secret_key']));

                echo '<div class="notice notice-success is-dismissible"><p>System Integration Settings securely saved!</p></div>';
            }

            if (isset($_POST['wsb_dummy_nonce']) && wp_verify_nonce($_POST['wsb_dummy_nonce'], 'wsb_generate_dummy')) {
                $this->generate_dummy_data($wpdb);
                echo '<div class="notice notice-success is-dismissible"><p>Successfully injected comprehensive dummy data across all tables!</p></div>';
            }
        }

        $currency = get_option('wsb_currency', 'USD');
        $stripe_pk = get_option('wsb_stripe_publishable_key', '');
        $stripe_sk = get_option('wsb_stripe_secret_key', '');
        ?>
        <div class="wrap wsb-admin-wrap">
            <div style="margin-bottom:30px;">
                <h1 style="margin:0; font-size:28px; font-weight:800; color:#fff;">System Settings & Integrations</h1>
                <p style="color:var(--wsb-text-muted); margin-top:5px; font-size:15px;">Configure your global booking architecture, payment gateways, and design language.</p>
            </div>

            <form method="post">
                <?php wp_nonce_field('wsb_save_settings', 'wsb_settings_nonce'); ?>
                
                <div style="display:grid; grid-template-columns: 2fr 1.2fr; gap:30px; align-items: start;">
                    
                    <!-- Left Column: Core Configuration -->
                    <div style="display:flex; flex-direction:column; gap:30px;">
                        
                        <!-- Payment Ecosystem Card -->
                        <div style="background:var(--wsb-panel-dark); border-radius:16px; border:1px solid var(--wsb-border); overflow:hidden; border-top:4px solid var(--wsb-primary);">
                            <div style="padding:25px; border-bottom:1px solid var(--wsb-border); display:flex; align-items:center; justify-content:space-between;">
                                <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-money-alt" style="color:var(--wsb-primary);"></span> Payment Gateway Ecosystem
                                </h3>
                                <span style="background:rgba(99, 102, 241, 0.1); color:var(--wsb-primary); padding:4px 12px; border-radius:20px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">Stripe Integration</span>
                            </div>
                            
                            <div style="padding:25px; display:flex; flex-direction:column; gap:30px;">
                                
                                <!-- Stripe Section -->
                                <div>
                                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                                        <div style="width:32px; height:32px; background:#635bff; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:900; font-size:18px;">S</div>
                                        <h4 style="margin:0; color:#fff; font-size:16px;">Stripe Professional</h4>
                                    </div>
                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted); font-size:13px;">Publishable API Key</label>
                                            <input name="wsb_stripe_publishable_key" type="text" value="<?php echo esc_attr($stripe_pk); ?>" placeholder="pk_test_..."
                                                style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:12px; border-radius:8px;">
                                        </div>
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted); font-size:13px;">Secret API Key</label>
                                            <input name="wsb_stripe_secret_key" id="wsb_stripe_secret_key" type="password" value="<?php echo esc_attr($stripe_sk); ?>" placeholder="sk_test_..."
                                                style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:12px; border-radius:8px;">
                                        </div>
                                    </div>
                                    <div style="margin-top:15px; display:flex; align-items:center; gap:15px;">
                                        <button type="button" id="wsb-test-stripe-btn" class="wsb-btn-primary" style="padding:8px 18px; font-size:13px; background:rgba(255,255,255,0.05); border:1px solid var(--wsb-border); color:#fff;">Verify Stripe API</button>
                                        <span id="wsb-stripe-test-spinner" style="display:none; color:var(--wsb-text-muted); font-size:12px;">Connecting to Stripe...</span>
                                        <div id="wsb-stripe-test-result" style="font-weight:600; font-size:13px; display:none;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- General & Regional Configuration -->
                        <div style="background:var(--wsb-panel-dark); border-radius:16px; border:1px solid var(--wsb-border); overflow:hidden;">
                            <div style="padding:25px; border-bottom:1px solid var(--wsb-border);">
                                <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-admin-settings" style="color:var(--wsb-warning);"></span> Regional & Locale Settings
                                </h3>
                            </div>
                            <div style="padding:25px;">
                                <div style="max-width:400px;">
                                    <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted); font-size:13px;">System Default Currency</label>
                                    <select name="wsb_currency" style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:12px; border-radius:8px; font-weight:600;">
                                        <option value="USD" <?php selected($currency, 'USD'); ?>>USD - United States Dollar ($)</option>
                                        <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR - Euro (€)</option>
                                        <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP - British Pound (£)</option>
                                        <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD - Canadian Dollar (C$)</option>
                                        <option value="AUD" <?php selected($currency, 'AUD'); ?>>AUD - Australian Dollar (A$)</option>
                                        <option value="JPY" <?php selected($currency, 'JPY'); ?>>JPY - Japanese Yen (¥)</option>
                                        <option value="INR" <?php selected($currency, 'INR'); ?>>INR - Indian Rupee (₹)</option>
                                    </select>
                                    <p style="margin-top:10px; font-size:12px; color:var(--wsb-text-muted);">This currency will be applied across all service pricing and invoice generation.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Integration & Tools -->
                    <div style="display:flex; flex-direction:column; gap:30px;">
                        
                        <!-- Shortcode Generator Card -->
                        <div style="background:var(--wsb-panel-dark); border-radius:16px; border:1px solid var(--wsb-border); overflow:hidden; border-top:4px solid var(--wsb-success);">
                            <div style="padding:25px; border-bottom:1px solid var(--wsb-border);">
                                <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-shortcode" style="color:var(--wsb-success);"></span> Frontend Deployment
                                </h3>
                            </div>
                            <div style="padding:25px;">
                                <p style="color:var(--wsb-text-muted); margin-bottom:20px; font-size:13px; line-height:1.6;">Paste this shortcode anywhere on your site to render the premium booking widget.</p>
                                <div style="background:rgba(16, 185, 129, 0.05); border:1px dashed var(--wsb-success); padding:15px; border-radius:10px; text-align:center; margin-bottom:20px;">
                                    <code style="font-size:20px; color:var(--wsb-success); font-weight:900; letter-spacing:1px;">[wsb_booking_widget]</code>
                                </div>
                                
                                <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted); font-size:13px;">Direct System Link</label>
                                <div style="position:relative;">
                                    <input type="text" readonly value="<?php echo site_url('/booking'); ?>" onclick="this.select();"
                                        style="width:100%; background:#0f172a; color:var(--wsb-primary); border:1px solid var(--wsb-border); padding:10px 12px; border-radius:8px; font-size:12px; cursor:pointer;">
                                    <span style="position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:10px; color:var(--wsb-text-muted);">Click to Copy</span>
                                </div>
                            </div>
                        </div>

                        <!-- Save Actions -->
                        <div style="background:var(--wsb-panel-dark); padding:25px; border-radius:16px; border:1px solid var(--wsb-border); display:flex; flex-direction:column; gap:15px;">
                            <button type="submit" class="wsb-btn-primary" style="width:100%; padding:15px; font-size:16px; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);">Save Global Architecture</button>
                            <p style="text-align:center; margin:0; font-size:12px; color:var(--wsb-text-muted);">Last saved: <?php echo date('M d, H:i'); ?></p>
                        </div>

                        <!-- Maintenance / Danger Zone -->
                        <div style="background:rgba(239, 68, 68, 0.02); border-radius:16px; border:1px solid rgba(239, 68, 68, 0.2); overflow:hidden;">
                            <div style="padding:20px; border-bottom:1px solid rgba(239, 68, 68, 0.1); background:rgba(239, 68, 68, 0.05);">
                                <h4 style="margin:0; color:#ef4444; display:flex; align-items:center; gap:8px; font-size:14px; text-transform:uppercase; letter-spacing:0.05em;">
                                    <span class="dashicons dashicons-warning"></span> Advanced Maintenance
                                </h4>
                            </div>
                            <div style="padding:20px;">
                                <p style="color:rgba(239, 68, 68, 0.7); font-size:12px; margin-bottom:15px; line-height:1.5;">Force inject comprehensive dummy data for testing purposes. This will duplicate records if run multiple times.</p>
                                <form method="post">
                                    <?php wp_nonce_field('wsb_generate_dummy', 'wsb_dummy_nonce'); ?>
                                    <button type="submit" name="generate_dummy" class="wsb-btn-primary" 
                                        style="width:100%; background:transparent; border:1px solid rgba(239, 68, 68, 0.3); color:#ef4444; padding:10px;"
                                        onclick="return confirm('CRITICAL: This will inject dummy data into your live database. Proceed?');">
                                        Inject Dummy Ecosystem
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    private function generate_dummy_data($wpdb)
    {
        $tables = array(
            'services' => $wpdb->prefix . 'wsb_services',
            'staff' => $wpdb->prefix . 'wsb_staff',
            'customers' => $wpdb->prefix . 'wsb_customers',
            'bookings' => $wpdb->prefix . 'wsb_bookings',
            'payments' => $wpdb->prefix . 'wsb_payments',
            'staff_services' => $wpdb->prefix . 'wsb_staff_services'
        );

        // 1. Insert Services
        $services_data = array(
            array('name' => 'Premium Haircut', 'description' => 'A full premium styling session.', 'price' => 45.00, 'duration' => 45, 'category' => 'Hair', 'capacity' => 1),
            array('name' => 'Beard Trim & Grooming', 'description' => 'Beard shaping and hot towel.', 'price' => 25.00, 'duration' => 30, 'category' => 'Hair', 'capacity' => 1),
            array('name' => 'Deep Tissue Massage', 'description' => 'Recovery therapy massage.', 'price' => 90.00, 'duration' => 60, 'category' => 'Spa', 'capacity' => 1),
            array('name' => 'Fitness Consultation', 'description' => '1-on-1 private training assessment.', 'price' => 60.00, 'duration' => 60, 'category' => 'Fitness', 'capacity' => 1),
        );
        $service_ids = array();
        foreach ($services_data as $s) {
            $wpdb->insert($tables['services'], $s);
            $service_ids[] = $wpdb->insert_id;
        }

        // 2. Insert Staff
        $staff_data = array(
            array('name' => 'Alex Turner', 'email' => 'alex@example.com', 'phone' => '555-0101'),
            array('name' => 'Sarah Jenkins', 'email' => 'sarah@example.com', 'phone' => '555-0102'),
            array('name' => 'Michael Chen', 'email' => 'michael@example.com', 'phone' => '555-0103'),
        );
        $staff_ids = array();
        foreach ($staff_data as $st) {
            $wpdb->insert($tables['staff'], $st);
            $staff_ids[] = $wpdb->insert_id;
        }

        // Assign Staff to Services
        if (!empty($staff_ids) && !empty($service_ids)) {
            $wpdb->insert($tables['staff_services'], array('staff_id' => $staff_ids[0], 'service_id' => $service_ids[0]));
            $wpdb->insert($tables['staff_services'], array('staff_id' => $staff_ids[0], 'service_id' => $service_ids[1]));
            $wpdb->insert($tables['staff_services'], array('staff_id' => $staff_ids[1], 'service_id' => $service_ids[2]));
            $wpdb->insert($tables['staff_services'], array('staff_id' => $staff_ids[2], 'service_id' => $service_ids[3]));
        }

        // 3. Insert Customers
        $customer_data = array(
            array('first_name' => 'John', 'last_name' => 'Doe', 'email' => 'johndoe@test.com', 'phone' => '123-456-7890'),
            array('first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'janes@test.com', 'phone' => '987-654-3210'),
            array('first_name' => 'Emily', 'last_name' => 'Davis', 'email' => 'emilyd@test.com', 'phone' => '555-111-2222'),
            array('first_name' => 'Chris', 'last_name' => 'Wilson', 'email' => 'chrisw@test.com', 'phone' => '555-333-4444'),
        );
        $customer_ids = array();
        foreach ($customer_data as $c) {
            $wpdb->insert($tables['customers'], $c);
            $customer_ids[] = $wpdb->insert_id;
        }

        // 4. Insert Bookings & Payments
        $statuses = array('confirmed', 'pending', 'completed');
        for ($i = 0; $i < 15; $i++) {
            $cid = $customer_ids[array_rand($customer_ids)];
            $sid = $service_ids[array_rand($service_ids)];
            $stid = $staff_ids[array_rand($staff_ids)];

            // Generate random date between today and next 14 days
            $random_days = rand(0, 14);
            $booking_date = date('Y-m-d', strtotime("+$random_days days"));
            $hour = rand(9, 16); // 9am to 4pm
            $start_time = sprintf('%02d:00:00', $hour);
            $end_time = sprintf('%02d:00:00', $hour + 1);
            $status = $statuses[array_rand($statuses)];
            $amount = rand(25, 120) . '.00';

            $wpdb->insert($tables['bookings'], array(
                'customer_id' => $cid,
                'service_id' => $sid,
                'staff_id' => $stid,
                'booking_date' => $booking_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'status' => $status,
                'total_amount' => $amount
            ));
            $booking_id = $wpdb->insert_id;

            $wpdb->insert($tables['payments'], array(
                'booking_id' => $booking_id,
                'amount' => $amount,
                'gateway' => 'stripe',
                'transaction_id' => 'ch_test_' . rand(1000, 9999),
                'status' => ($status === 'pending') ? 'pending' : 'completed'
            ));
        }
    }

    public function display_design_page()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wsb_design_nonce']) && wp_verify_nonce($_POST['wsb_design_nonce'], 'wsb_save_design')) {
            update_option('wsb_service_layout', sanitize_text_field($_POST['wsb_service_layout']));
            update_option('wsb_brand_color', sanitize_hex_color($_POST['wsb_brand_color']));
            update_option('wsb_brand_color_end', sanitize_hex_color($_POST['wsb_brand_color_end']));
            update_option('wsb_accent_color', sanitize_hex_color($_POST['wsb_accent_color']));
            update_option('wsb_virtual_bg_color', sanitize_hex_color($_POST['wsb_virtual_bg_color']));
            echo '<div class="notice notice-success is-dismissible"><p>Design and aesthetic preferences saved!</p></div>';
        }

        $service_layout = get_option('wsb_service_layout', 'modern_grid');
        $brand_color = get_option('wsb_brand_color', '#6366f1');
        $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
        $accent_color = get_option('wsb_accent_color', '#4f46e5');
        $virtual_bg_color = get_option('wsb_virtual_bg_color', '#f8fafc');
        ?>
        <div class="wrap wsb-admin-wrap">
            <h1 style="margin-bottom:20px;">Frontend Experience & Designer</h1>
            <p style="color:var(--wsb-text-muted); margin-bottom:30px;">Customize how your booking widget looks and feels to
                your customers.</p>

            <form method="post">
                <?php wp_nonce_field('wsb_save_design', 'wsb_design_nonce'); ?>

                <div class="wsb-design-section">
                    <h2 style="color:white; margin-bottom:25px; font-weight: 700; letter-spacing: -0.02em; display:flex; align-items:center; gap:10px;">
                        <span class="dashicons dashicons-art"></span> Brand Identity & Gradients
                    </h2>
                    <div class="wsb-color-row">
                        <label class="wsb-color-card">
                            <strong style="color:white; font-size: 14px; font-weight: 600; display:block;">Primary Color (Start)</strong>
                            <div class="wsb-color-picker-wrapper">
                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($brand_color); ?>;"></div>
                                <span class="wsb-color-hex"><?php echo esc_html($brand_color); ?></span>
                                <input type="color" name="wsb_brand_color" value="<?php echo esc_attr($brand_color); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.textContent = this.value; this.previousElementSibling.previousElementSibling.style.background = this.value;">
                            </div>
                        </label>
                        
                        <label class="wsb-color-card">
                            <strong style="color:white; font-size: 14px; font-weight: 600; display:block;">Gradient Color (End)</strong>
                            <div class="wsb-color-picker-wrapper">
                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($brand_color_end); ?>;"></div>
                                <span class="wsb-color-hex"><?php echo esc_html($brand_color_end); ?></span>
                                <input type="color" name="wsb_brand_color_end" value="<?php echo esc_attr($brand_color_end); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.textContent = this.value; this.previousElementSibling.previousElementSibling.style.background = this.value;">
                            </div>
                        </label>
                        
                        <label class="wsb-color-card">
                            <strong style="color:white; font-size: 14px; font-weight: 600; display:block;">Interactive Accent</strong>
                            <div class="wsb-color-picker-wrapper">
                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($accent_color); ?>;"></div>
                                <span class="wsb-color-hex"><?php echo esc_html($accent_color); ?></span>
                                <input type="color" name="wsb_accent_color" value="<?php echo esc_attr($accent_color); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.textContent = this.value; this.previousElementSibling.previousElementSibling.style.background = this.value;">
                            </div>
                        </label>
                        
                        <label class="wsb-color-card">
                            <strong style="color:white; font-size: 14px; font-weight: 600; display:block;">Service Page Background</strong>
                            <div class="wsb-color-picker-wrapper">
                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($virtual_bg_color); ?>;"></div>
                                <span class="wsb-color-hex"><?php echo esc_html($virtual_bg_color); ?></span>
                                <input type="color" name="wsb_virtual_bg_color" value="<?php echo esc_attr($virtual_bg_color); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.textContent = this.value; this.previousElementSibling.previousElementSibling.style.background = this.value;">
                            </div>
                        </label>
                    </div>

                    <h2 style="color:white; margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                        <span class="dashicons dashicons-layout"></span> Layout & Aesthetic Style
                    </h2>
                    <p style="color:var(--wsb-text-muted); font-size:13px; margin-bottom:25px;">Choose from 18 professionally
                        crafted design languages for your service display.</p>

                    <div class="wsb-layout-selector">
                        <?php
                        $layouts = [
                            'modern_grid' => 'Signature Grid',
                            'glass_cards_v2' => 'Glass Elite',
                            'metro_grid' => 'Immersive Metro',
                            'neon_night' => 'Cyber Dark'
                        ];
                        foreach ($layouts as $val => $name): ?>
                            <label class="wsb-layout-option">
                                <input type="radio" name="wsb_service_layout" value="<?php echo $val; ?>" <?php checked($service_layout, $val); ?>>
                                <div class="wsb-layout-preview">
                                    <div
                                        style="font-size:10px; color:rgba(255,255,255,0.4); text-transform:uppercase; font-weight:700; z-index: 10; position: relative;">
                                        <?php echo $name; ?></div>
                                    
                                    <?php if ($val == 'modern_grid'): ?>
                                        <div style="position:absolute; inset:0; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); opacity: 0.5;"></div>
                                        <div style="position:absolute; top: 15%; left: 15%; width: 70%; height: 70%; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px;"></div>
                                    <?php endif; ?>

                                    <?php if ($val == 'neon_night'): ?>
                                        <div style="position:absolute; inset:0; background: #020617;"></div>
                                        <div style="position:absolute; top: 20%; left: 20%; width: 60%; height: 60%; border: 1px solid #6366f1; border-radius: 12px; box-shadow: 0 0 15px rgba(99, 102, 241, 0.4);"></div>
                                    <?php endif; ?>

                                    <?php if ($val == 'glass_cards_v2'): ?>
                                        <div style="position:absolute; inset:0; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); opacity: 0.3;"></div>
                                        <div style="position:absolute; top: 20%; left: 20%; width: 60%; height: 60%; background: rgba(255,255,255,0.1); backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px;"></div>
                                    <?php endif; ?>

                                    <?php if ($val == 'metro_grid'): ?>
                                        <div style="position:absolute; inset:0; background: url('https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&q=80&w=200') center/cover; opacity: 0.4;"></div>
                                        <div style="position:absolute; bottom: 0; left: 0; right: 0; height: 40%; background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);"></div>
                                    <?php endif; ?>
                                </div>
                                <span class="wsb-layout-name"><?php echo $name; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="wsb-btn-premium"
                        style="background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: #fff; border: none; padding: 14px 35px; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2); transition: all 0.3s ease; display: flex; align-items: center; gap: 10px;">
                        <span>✨</span> Apply Premium Design
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
}
