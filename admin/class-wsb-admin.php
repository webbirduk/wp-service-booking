<?php
class Wsb_Admin {
    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wsb-admin.css', array(), $this->version, 'all' );
    }

    public function enqueue_scripts() {
        wp_enqueue_media();
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wsb-admin.js', array( 'jquery' ), $this->version, false );
    }

    public function add_plugin_admin_menu() {
        add_menu_page(
            'Service Booking', 
            'Service Booking', 
            'manage_options', 
            $this->plugin_name, 
            array($this, 'display_plugin_setup_page'), 
            'dashicons-calendar-alt', 
            26
        );

        add_submenu_page(
            $this->plugin_name,
            'Dashboard',
            'Dashboard',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'Bookings',
            'Bookings',
            'manage_options',
            $this->plugin_name . '-bookings',
            array($this, 'display_bookings_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'Finance',
            'Finance',
            'manage_options',
            $this->plugin_name . '-finance',
            array($this, 'display_finance_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'Services',
            'Services',
            'manage_options',
            $this->plugin_name . '-services',
            array($this, 'display_services_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'Staff',
            'Staff',
            'manage_options',
            $this->plugin_name . '-staff',
            array($this, 'display_staff_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'Customers',
            'Customers',
            'manage_options',
            $this->plugin_name . '-customers',
            array($this, 'display_customers_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'Design',
            'Design',
            'manage_options',
            $this->plugin_name . '-design',
            array($this, 'display_design_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'Settings',
            'Settings',
            'manage_options',
            $this->plugin_name . '-settings',
            array($this, 'display_settings_page')
        );
    }

    public function display_plugin_setup_page() {
        global $wpdb;

        // Metric Queries
        $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_bookings");
        $pending_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_bookings WHERE status = 'pending'");
        $total_revenue = $wpdb->get_var("SELECT SUM(total_amount) FROM {$wpdb->prefix}wsb_bookings WHERE status = 'confirmed'");
        $total_customers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_customers");
        $total_services = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_services");

        // Recent Bookings Query
        $recent_bookings = $wpdb->get_results("
            SELECT b.*, c.first_name, c.last_name, s.name as service_name 
            FROM {$wpdb->prefix}wsb_bookings b
            LEFT JOIN {$wpdb->prefix}wsb_customers c ON b.customer_id = c.id
            LEFT JOIN {$wpdb->prefix}wsb_services s ON b.service_id = s.id
            ORDER BY b.created_at DESC LIMIT 5
        ");
        
        ?>
        <style>
            .wsb-clickable-card { text-decoration: none; color: inherit; transition: transform 0.2s ease, box-shadow 0.2s ease; display: block; border-left: 4px solid transparent; }
            .wsb-clickable-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); border-left-color: var(--wsb-primary); }
            .wsb-clickable-card h3 { transition: color 0.2s ease; }
            .wsb-clickable-card:hover h3 { color: var(--wsb-primary); }
        </style>
        <div class="wrap wsb-admin-wrap">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h1 style="margin:0;">Dashboard Overview</h1>
                <div>
                    <a href="?page=<?php echo esc_attr($this->plugin_name . '-bookings'); ?>&view=calendar" class="wsb-btn-primary">View Calendar</a>
                    <a href="?page=<?php echo esc_attr($this->plugin_name . '-services'); ?>&action=add" class="wsb-btn-primary" style="margin-left:5px; background:var(--wsb-success);">+ New Service</a>
                </div>
            </div>
            <hr class="wp-header-end" style="margin-bottom:20px;">

            <div class="wsb-dashboard-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom:30px;">
                <a href="?page=<?php echo esc_attr($this->plugin_name . '-bookings'); ?>" class="wsb-stat-card wsb-clickable-card">
                    <h3 style="margin-top:0; font-size:16px;">Total Bookings</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;"><?php echo intval($total_bookings); ?></p>
                    <?php if($pending_bookings > 0): ?>
                        <span style="display:inline-block; margin-top:10px; background:rgba(245,158,11,0.2); color:#f59e0b; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold;">🔥 <?php echo $pending_bookings; ?> Pending Actions</span>
                    <?php else: ?>
                        <span style="display:inline-block; margin-top:10px; color:var(--wsb-text-muted); font-size:12px;">All caught up!</span>
                    <?php endif; ?>
                </a>
                
                <a href="?page=<?php echo esc_attr($this->plugin_name . '-bookings'); ?>" class="wsb-stat-card wsb-clickable-card">
                    <h3 style="margin-top:0; font-size:16px;">Total Revenue</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--wsb-success);">$<?php echo number_format((float)$total_revenue, 2); ?></p>
                    <span style="display:inline-block; margin-top:10px; color:var(--wsb-text-muted); font-size:12px;">Confirmed Earnings</span>
                </a>
                
                <a href="?page=<?php echo esc_attr($this->plugin_name . '-customers'); ?>" class="wsb-stat-card wsb-clickable-card">
                    <h3 style="margin-top:0; font-size:16px;">Total Customers</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;"><?php echo intval($total_customers); ?></p>
                    <span style="display:inline-block; margin-top:10px; color:var(--wsb-text-muted); font-size:12px;">Registered Users</span>
                </a>
                
                <a href="?page=<?php echo esc_attr($this->plugin_name . '-services'); ?>" class="wsb-stat-card wsb-clickable-card">
                    <h3 style="margin-top:0; font-size:16px;">Active Services</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;"><?php echo intval($total_services); ?></p>
                    <span style="display:inline-block; margin-top:10px; color:var(--wsb-text-muted); font-size:12px;">Manage Catalog &rarr;</span>
                </a>
            </div>
            
            <div style="background: var(--wsb-panel-dark); border-radius:12px; border:1px solid var(--wsb-border); overflow:hidden;">
                <div style="padding: 20px; border-bottom: 1px solid var(--wsb-border); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; color: #fff;">Recent Activity</h3>
                    <a href="?page=<?php echo esc_attr($this->plugin_name . '-bookings'); ?>" style="color:var(--wsb-primary); text-decoration:none; font-weight:500;">View All</a>
                </div>
                <!-- Inline Table -->
                <table style="width:100%; border-collapse:collapse; text-align:left;">
                    <thead style="background:rgba(0,0,0,0.2);">
                        <tr>
                            <th style="padding:15px 20px; color:var(--wsb-text-muted); font-weight:500; font-size:13px;">Customer</th>
                            <th style="padding:15px 20px; color:var(--wsb-text-muted); font-weight:500; font-size:13px;">Service</th>
                            <th style="padding:15px 20px; color:var(--wsb-text-muted); font-weight:500; font-size:13px;">Date</th>
                            <th style="padding:15px 20px; color:var(--wsb-text-muted); font-weight:500; font-size:13px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($recent_bookings)): foreach($recent_bookings as $rb): ?>
                        <tr class="wsb-clickable-row" data-href="?page=<?php echo esc_attr($this->plugin_name . '-bookings'); ?>&action=edit&id=<?php echo $rb->id; ?>" style="border-bottom:1px solid var(--wsb-border);">
                            <td style="padding:15px 20px; font-weight:500;"><?php echo esc_html($rb->first_name . ' ' . $rb->last_name); ?></td>
                            <td style="padding:15px 20px; color:var(--wsb-text-muted);"><?php echo esc_html($rb->service_name); ?></td>
                            <td style="padding:15px 20px;"><?php echo esc_html(date('M d, Y', strtotime($rb->booking_date))); ?></td>
                            <td style="padding:15px 20px;"><span class="wsb-status wsb-status-<?php echo esc_attr($rb->status); ?>" style="font-size:11px;"><?php echo esc_html(ucfirst($rb->status)); ?></span></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" style="padding:30px; text-align:center; color:var(--wsb-text-muted);">No recent activity.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function display_bookings_page() {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'wsb_bookings';

        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        // Quick Status Updates (Approve / Reject)
        if ($action === 'status' && $booking_id) {
            $new_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
            if (in_array($new_status, ['confirmed', 'cancelled', 'pending', 'completed'])) {
                $wpdb->update($table_bookings, array('status' => $new_status), array('id' => $booking_id));
                echo '<div class="notice notice-success is-dismissible"><p>Booking status securely updated to '.esc_html($new_status).'.</p></div>';
            }
            $action = 'list'; // go back
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
            echo '<div class="notice notice-success is-dismissible"><p>Booking information updated successfully.</p></div>';
            $action = 'list';
        }

        if ($action === 'edit' && $booking_id) {
            $booking = $wpdb->get_row($wpdb->prepare("
                SELECT b.*, c.first_name, c.last_name, s.name as service_name, st.name as staff_name 
                FROM {$table_bookings} b
                LEFT JOIN {$wpdb->prefix}wsb_customers c ON b.customer_id = c.id
                LEFT JOIN {$wpdb->prefix}wsb_services s ON b.service_id = s.id
                LEFT JOIN {$wpdb->prefix}wsb_staff st ON b.staff_id = st.id
                WHERE b.id = %d
            ", $booking_id));

            if ($booking) {
                ?>
                <div class="wrap wsb-admin-wrap">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <h1 style="margin:0;">Edit Booking #<?php echo esc_html(str_pad($booking->id, 5, '0', STR_PAD_LEFT)); ?></h1>
                        <a href="?page=<?php echo esc_attr($this->plugin_name . '-bookings'); ?>" class="wsb-btn-primary" style="background:var(--wsb-border);">Back to Bookings</a>
                    </div>
                    <hr class="wp-header-end" style="margin-bottom:20px;">
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('wsb_edit_booking', 'wsb_edit_booking_nonce'); ?>
                        <div style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--wsb-border); max-width: 600px;">
                            <p style="color:var(--wsb-text-muted); margin-top:0;"><strong>Customer:</strong> <?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?> <br>
                            <strong>Service:</strong> <?php echo esc_html($booking->service_name); ?> with <?php echo esc_html($booking->staff_name); ?></p>
                            
                            <hr style="border:0; border-top:1px solid var(--wsb-border); margin:20px 0;">

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                <div>
                                    <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Booking Date</label>
                                    <input name="booking_date" type="date" value="<?php echo esc_attr($booking->booking_date); ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:8px; border-radius:6px;" required>
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Status</label>
                                    <select name="status" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:8px; border-radius:6px;">
                                        <option value="pending" <?php selected($booking->status, 'pending'); ?>>Pending</option>
                                        <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>>Confirmed</option>
                                        <option value="completed" <?php selected($booking->status, 'completed'); ?>>Completed</option>
                                        <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>>Cancelled</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                <div>
                                    <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Start Time</label>
                                    <input name="start_time" type="time" value="<?php echo esc_attr($booking->start_time); ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:8px; border-radius:6px;" required>
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">End Time</label>
                                    <input name="end_time" type="time" value="<?php echo esc_attr($booking->end_time); ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:8px; border-radius:6px;" required>
                                </div>
                            </div>
                            
                            <div style="margin-bottom:20px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Total Amount ($)</label>
                                <input name="total_amount" type="number" step="0.01" value="<?php echo esc_attr($booking->total_amount); ?>" style="width:100%; max-width:200px; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:8px; border-radius:6px;" required>
                            </div>

                            <button type="submit" class="wsb-btn-primary">Update Booking</button>
                        </div>
                    </form>
                </div>
                <?php
            }
            return;
        }

        // Filter Engine
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $where_clause = "WHERE 1=1";
        if (in_array($filter_status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
            $where_clause .= " AND b.status = '{$filter_status}'";
        }

        // Metrics for Top Cards
        $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$table_bookings}");
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_bookings} WHERE status='pending'");
        $confirmed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_bookings} WHERE status='confirmed' OR status='completed'");

        // Joined query to get names instead of raw IDs for a professional look
        $query = "SELECT b.*, 
                         c.first_name, c.last_name, c.email as customer_email, 
                         s.name as service_name, 
                         st.name as staff_name 
                  FROM {$wpdb->prefix}wsb_bookings b
                  LEFT JOIN {$wpdb->prefix}wsb_customers c ON b.customer_id = c.id
                  LEFT JOIN {$wpdb->prefix}wsb_services s ON b.service_id = s.id
                  LEFT JOIN {$wpdb->prefix}wsb_staff st ON b.staff_id = st.id
                  {$where_clause}
                  ORDER BY b.booking_date DESC, b.start_time DESC";

        $bookings = $wpdb->get_results($query);
        
        $view = isset($_GET['view']) ? $_GET['view'] : 'list';
        $page_url = "?page=" . esc_attr($this->plugin_name . '-bookings');
        ?>
        <div class="wrap wsb-admin-wrap">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h1 style="margin:0;">Manage Bookings</h1>
                <div>
                    <a href="<?php echo $page_url; ?>&view=list&filter_status=<?php echo esc_attr($filter_status); ?>" class="wsb-btn-primary" style="background: <?php echo $view === 'list' ? 'var(--wsb-primary)' : 'var(--wsb-border)'; ?>;">List View</a>
                    <a href="<?php echo $page_url; ?>&view=calendar&filter_status=<?php echo esc_attr($filter_status); ?>" class="wsb-btn-primary" style="margin-left:5px; background: <?php echo $view === 'calendar' ? 'var(--wsb-primary)' : 'var(--wsb-border)'; ?>;">Calendar View</a>
                </div>
            </div>

            <!-- Clickable Meta Cards -->
            <style>
                .booking-filter-card { border-left: 4px solid transparent; text-decoration: none; color: inherit; display: block; border: 1px solid var(--wsb-border); border-radius: 12px; background: var(--wsb-panel-dark); padding: 20px; transition: transform 0.2s; }
                .booking-filter-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }
                .card-active { background: rgba(59, 130, 246, 0.1) !important; border-left: 4px solid var(--wsb-primary) !important; border-color: var(--wsb-primary) !important; }
            </style>
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top:20px; margin-bottom:20px;">
                <a href="<?php echo $page_url; ?>&view=<?php echo esc_attr($view); ?>&filter_status=all" class="booking-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--wsb-text-muted);">Total Bookings</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($total_bookings); ?></p>
                </a>
                <a href="<?php echo $page_url; ?>&view=<?php echo esc_attr($view); ?>&filter_status=pending" class="booking-filter-card <?php echo $filter_status === 'pending' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--wsb-warning);">Pending Approvals</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($pending_count); ?></p>
                </a>
                <a href="<?php echo $page_url; ?>&view=<?php echo esc_attr($view); ?>&filter_status=confirmed" class="booking-filter-card <?php echo $filter_status === 'confirmed' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--wsb-success);">Confirmed / Completed</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($confirmed_count); ?></p>
                </a>
            </div>

            <?php if($view === 'calendar'): ?>
                <!-- Calendar View powered by FullCalendar -->
                <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
                <div id="wsb-calendar" style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; margin-top:20px; border: 1px solid var(--wsb-border); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);"></div>
                <script>
                    (function() {
                        var initCalendar = function() {
                            var calendarEl = document.getElementById('wsb-calendar');
                            if (!calendarEl || calendarEl.classList.contains('fc')) return;
                            
                            var events = [
                                <?php foreach($bookings as $b): ?>
                                {
                                    title: '<?php echo esc_js($b->first_name . " - " . $b->service_name); ?>',
                                    start: '<?php echo esc_js($b->booking_date); ?>T<?php echo esc_js($b->start_time); ?>',
                                    end: '<?php echo esc_js($b->booking_date); ?>T<?php echo esc_js($b->end_time); ?>',
                                    color: '<?php echo $b->status === 'confirmed' ? '#10b981' : ($b->status === 'pending' ? '#f59e0b' : '#3b82f6'); ?>',
                                    url: '<?php echo "?page=" . esc_attr($this->plugin_name . "-bookings") . "&action=edit&id=" . $b->id; ?>'
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
                                height: 700
                            });
                            calendar.render();
                        };

                        if (document.readyState === 'loading') {
                            document.addEventListener('DOMContentLoaded', initCalendar);
                        } else {
                            initCalendar();
                        }
                        // Also listen for our custom AJAX load event
                        jQuery(document).on('wsb-page-loaded', initCalendar);
                    })();
                </script>
                <style>
                    /* FullCalendar overrides for Dark Mode */
                    .fc-theme-standard td, .fc-theme-standard th { border-color: var(--wsb-border); color: #fff; }
                    .fc-col-header-cell { background: rgba(0,0,0,0.2) !important; padding: 10px 0; }
                    .fc-timegrid-slot-label { color: var(--wsb-text-muted); font-size:12px; }
                    .fc-button-primary { background-color: var(--wsb-primary) !important; border-color: var(--wsb-primary) !important; text-transform: capitalize; border-radius: 6px !important; }
                    .fc .fc-toolbar-title { font-weight: 600; font-family: 'Inter', sans-serif; font-size: 20px; color: var(--wsb-text-main); }
                    .fc-event { border: none !important; border-radius: 4px; padding: 2px 4px; font-size: 11px; }
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
                        <?php if (!empty($bookings)) : ?>
                            <?php foreach ($bookings as $b) : ?>
                                <tr class="wsb-clickable-row" data-href="?page=<?php echo esc_attr($this->plugin_name . '-bookings'); ?>&action=edit&id=<?php echo $b->id; ?>">
                                    <td><strong style="color:var(--wsb-primary);">#<?php echo esc_html(str_pad($b->id, 5, '0', STR_PAD_LEFT)); ?></strong></td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span class="wsb-customer-name"><?php echo esc_html($b->first_name . ' ' . $b->last_name); ?></span>
                                            <span class="wsb-customer-meta"><?php echo esc_html($b->customer_email); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span class="wsb-customer-name"><?php echo esc_html($b->service_name); ?></span>
                                            <span class="wsb-customer-meta">with <?php echo esc_html($b->staff_name); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span class="wsb-customer-name"><?php echo esc_html(date('M d, Y', strtotime($b->booking_date))); ?></span>
                                            <span class="wsb-customer-meta"><?php echo esc_html(date('g:i A', strtotime($b->start_time))); ?> - <?php echo esc_html(date('g:i A', strtotime($b->end_time))); ?></span>
                                        </div>
                                    </td>
                                    <td><strong>$<?php echo esc_html($b->total_amount); ?></strong></td>
                                    <td><span class="wsb-status wsb-status-<?php echo esc_attr($b->status); ?>"><?php echo esc_html(ucfirst($b->status)); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="6" style="text-align:center; padding: 40px; color: var(--wsb-text-muted);">No bookings found. Generate some dummy data!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function display_services_page() {
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
                    'name' => 'Signature Haircut', 'description' => 'Precision cut tailored to your face shape by our top stylists.',
                    'duration' => 45, 'price' => 50.00, 'buffer_time' => 15, 'category' => 'Hair', 'capacity' => 1, 'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Balayage Color Treatment', 'description' => 'Beautiful, natural-looking hand-painted highlights.',
                    'duration' => 120, 'price' => 180.00, 'buffer_time' => 30, 'category' => 'Color', 'capacity' => 1, 'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1512496015851-a1dc8f411906?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Deep Tissue Massage', 'description' => 'Intense pressure therapy to release knots and muscle tension.',
                    'duration' => 60, 'price' => 90.00, 'buffer_time' => 15, 'category' => 'Spa & Relax', 'capacity' => 1, 'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Bridal Makeup Session', 'description' => 'Full makeup session for the big day, including consultations.',
                    'duration' => 90, 'price' => 120.00, 'buffer_time' => 30, 'category' => 'Makeup', 'capacity' => 1, 'status' => 'inactive',
                    'image_url' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                )
            );
            foreach($dummy_services as $srv) {
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
                foreach($staff_relations as $sr) $assigned_staff_ids[] = $sr->staff_id;
            }

            ?>
            <div class="wrap wsb-admin-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h1 style="margin:0;"><?php echo $action === 'edit' ? 'Edit Service' : 'Add New Service'; ?></h1>
                    <a href="?page=<?php echo esc_attr($this->plugin_name . '-services'); ?>" class="wsb-btn-primary" style="background:var(--wsb-border);">Back to Services</a>
                </div>
                <hr class="wp-header-end" style="margin-bottom:20px;">
                
                <form method="post" action="">
                    <?php wp_nonce_field('wsb_add_service', 'wsb_service_nonce'); ?>
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                        
                        <!-- Main Panel -->
                        <div style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--wsb-border); border-top: 4px solid var(--wsb-primary);">
                            <h3 style="margin-top:0; color:var(--wsb-primary); font-size:18px; display:flex; align-items:center; gap:8px;">📝 Basic Information</h3>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Service Name</label>
                                <input name="service_name" type="text" value="<?php echo $service ? esc_attr($service->name) : ''; ?>" style="width:100%; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; background:#0f172a; color:white; font-size:16px;" required>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Category</label>
                                <input name="service_category" type="text" value="<?php echo $service ? esc_attr($service->category) : ''; ?>" style="width:100%; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; background:#0f172a; color:white;">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Description</label>
                                <textarea name="service_description" rows="4" style="width:100%; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; background:#0f172a; color:white;"><?php echo $service ? esc_textarea($service->description) : ''; ?></textarea>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Featured Image</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <div id="wsb-featured-preview" style="width:60px; height:60px; border-radius:6px; border:1px dashed var(--wsb-border); background:#0f172a <?php echo $service && $service->image_url ? 'url('.esc_url($service->image_url).') center/cover' : ''; ?>;"></div>
                                    <input type="hidden" name="service_image_url" id="service_image_url" value="<?php echo $service ? esc_url($service->image_url) : ''; ?>">
                                    <button type="button" class="wsb-btn-primary wsb-select-image" data-target="#service_image_url" data-preview="#wsb-featured-preview" style="background:var(--wsb-border); color:white;">Select Image</button>
                                </div>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Service Gallery (Multiple Images)</label>
                                <input type="hidden" name="service_gallery_urls" id="service_gallery_urls" value="<?php echo $service && isset($service->gallery_urls) ? esc_attr($service->gallery_urls) : ''; ?>">
                                <div id="wsb-gallery-preview" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                                    <?php 
                                    if ($service && !empty($service->gallery_urls)) {
                                        $urls = explode(',', $service->gallery_urls);
                                        foreach($urls as $url) {
                                            echo '<div style="width:50px; height:50px; border-radius:4px; background:url('.esc_url($url).') center/cover; border:1px solid #334155;"></div>';
                                        }
                                    }
                                    ?>
                                </div>
                                <button type="button" class="wsb-btn-primary wsb-select-gallery" style="background:var(--wsb-border); color:white;">Select Gallery Images</button>
                            </div>
                        </div>

                        <!-- Side Panel -->
                        <div>
                            <div style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--wsb-border); margin-bottom: 20px; border-top: 4px solid var(--wsb-success);">
                                <h3 style="margin-top:0; color:var(--wsb-success); font-size:18px; display:flex; align-items:center; gap:8px;">💰 Pricing & Duration</h3>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom: 15px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Price ($)</label>
                                        <input name="service_price" type="number" step="0.01" value="<?php echo $service ? esc_attr($service->price) : '0.00'; ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; font-weight:bold;" required>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Duration (m)</label>
                                        <input name="service_duration" type="number" value="<?php echo $service ? esc_attr($service->duration) : '30'; ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px;" required>
                                    </div>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Buffer (m)</label>
                                        <input name="service_buffer_time" type="number" value="<?php echo $service ? esc_attr($service->buffer_time) : '0'; ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px;">
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Capacity</label>
                                        <input name="service_capacity" type="number" value="<?php echo $service ? esc_attr($service->capacity) : '1'; ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px;" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--wsb-border); border-top: 4px solid var(--wsb-warning);">
                                <h3 style="margin-top:0; color:var(--wsb-warning); font-size:18px; display:flex; align-items:center; gap:8px;">👥 Assign Staff</h3>
                                <?php if (!empty($staff_members)) : ?>
                                    <div style="max-height:150px; overflow-y:auto; padding-right:10px;">
                                    <?php foreach ($staff_members as $staff) : ?>
                                        <label style="display:block; margin-bottom:8px;">
                                            <input type="checkbox" name="assigned_staff[]" value="<?php echo esc_attr($staff->id); ?>" <?php echo in_array($staff->id, $assigned_staff_ids) ? 'checked' : ''; ?>> 
                                            <?php echo esc_html($staff->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <p class="description" style="color:var(--wsb-warning);">No staff members found.</p>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="wsb-btn-primary" style="width:100%; margin-top:20px; padding:15px; font-size:16px;">
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
            if ($search) $query .= $wpdb->prepare(" AND name LIKE %s", '%' . $wpdb->esc_like($search) . '%');
            if ($filter) $query .= $wpdb->prepare(" AND category = %s", $filter);
            if ($filter_status === 'active') $query .= " AND status = 'active'";
            if ($filter_status === 'inactive') $query .= " AND status = 'inactive'";
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
                        <a href="<?php echo wp_nonce_url("?page=".$this->plugin_name."-services&action=seed", 'seed_services'); ?>" class="wsb-btn-primary" style="background:var(--wsb-warning); margin-right:10px;">⚡ Inject Dummy Services</a>
                        <a href="?page=<?php echo esc_attr($this->plugin_name . '-services'); ?>&action=add" class="wsb-btn-primary">+ Add New Service</a>
                    </div>
                </div>

                <style>
                    .service-filter-card { border-left: 4px solid transparent; text-decoration: none; color: inherit; display: block; border: 1px solid var(--wsb-border); border-radius: 12px; background: var(--wsb-panel-dark); padding: 20px; transition: transform 0.2s; }
                    .service-filter-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }
                    .card-active { background: rgba(59, 130, 246, 0.1) !important; border-left: 4px solid var(--wsb-primary) !important; border-color: var(--wsb-primary) !important; }
                </style>
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top:20px; margin-bottom:20px;">
                    <a href="<?php echo $page_url; ?>&filter_status=all" class="service-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-text-muted);">Total Services</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($total_services); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=active" class="service-filter-card <?php echo $filter_status === 'active' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-success);">Live Offerings</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($active_services); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=inactive" class="service-filter-card <?php echo $filter_status === 'inactive' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-warning);">Draft / Inactive</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($inactive_services); ?></p>
                    </a>
                </div>
                
                <form method="get" action="" style="display:flex; gap:10px; margin-bottom:20px;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                    <?php if($filter_status !== 'all'): ?>
                        <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">
                    <?php endif; ?>
                    <input type="text" name="s" placeholder="Search services..." value="<?php echo esc_attr($search); ?>" style="background:#0f172a; border:1px solid var(--wsb-border); color:white; padding:8px 12px; border-radius:6px; flex-grow:1;">
                    <select name="cat" style="background:#0f172a; border:1px solid var(--wsb-border); color:white; padding:8px 12px; border-radius:6px;">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat); ?>" <?php selected($filter, $cat); ?>><?php echo esc_html($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="wsb-btn-primary" style="padding:8px 20px;">Filter</button>
                    <?php if($search || $filter): ?>
                        <a href="?page=<?php echo esc_attr($this->plugin_name . '-services'); ?>" class="wsb-btn-primary" style="background:var(--wsb-danger);">Clear</a>
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
                        <?php if (!empty($services)) : ?>
                            <?php foreach ($services as $service) : ?>
                                <tr class="wsb-clickable-row" data-href="?page=<?php echo esc_attr($this->plugin_name . '-services'); ?>&action=edit&id=<?php echo $service->id; ?>">
                                    <td>
                                        <?php if(!empty($service->image_url)): ?>
                                            <div style="width:40px; height:40px; border-radius:6px; background:url('<?php echo esc_url($service->image_url); ?>') center/cover; border:1px solid var(--wsb-border);"></div>
                                        <?php else: ?>
                                            <div style="width:40px; height:40px; border-radius:6px; background:var(--wsb-border); display:flex; align-items:center; justify-content:center; color:var(--wsb-text-muted); font-size:20px;">✂️</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span class="wsb-customer-name" style="font-size:15px;"><?php echo esc_html($service->name); ?></span>
                                            <span class="wsb-customer-meta">Cap: <?php echo esc_html($service->capacity); ?> | Buffer: <?php echo esc_html($service->buffer_time); ?>m</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span class="wsb-customer-name">$<?php echo esc_html($service->price); ?></span>
                                            <span class="wsb-customer-meta"><?php echo esc_html($service->duration); ?> minutes</span>
                                        </div>
                                    </td>
                                    <td><span style="background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:12px;"><?php echo esc_html($service->category ?: 'Uncategorized'); ?></span></td>
                                    <td align="right">
                                        <div class="wsb-row-actions">
                                            <a href="?page=<?php echo esc_attr($this->plugin_name . '-services'); ?>&action=edit&id=<?php echo $service->id; ?>" class="wsb-row-action wsb-action-edit" title="Edit Service">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </a>
                                            <a href="?page=<?php echo esc_attr($this->plugin_name . '-services'); ?>&action=delete&id=<?php echo $service->id; ?>" class="wsb-row-action wsb-action-delete" title="Delete Service" onclick="return confirm('Are you sure you want to completely delete this service?');">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="5" style="text-align:center; padding: 40px; color: var(--wsb-text-muted);">No services match your criteria.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    }

    public function display_staff_page() {
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
        if ($action === 'delete' && $staff_id && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_staff_'.$staff_id)) {
            $wpdb->delete($table_staff, array('id' => $staff_id));
            echo '<div class="notice notice-success is-dismissible"><p>Staff record purged from the system.</p></div>';
            $action = 'list';
        }

        // Seed Dummy Data Handler
        if ($action === 'seed' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'seed_staff')) {
            $dummy_staff = array(
                array(
                    'name' => 'Alexander Pierce', 'email' => 'alex@example.com', 'phone' => '555-0102', 'status' => 'active',
                    'description' => 'Master barber with 10 years of experience in classic cuts and hot towel shaves.',
                    'qualification' => 'Master Barber', 'address' => '123 Main St, Suite 100',
                    'image_url' => 'https://ui-avatars.com/api/?name=Alexander+Pierce&background=0D8ABC&color=fff&size=200',
                    'schedule_config' => '{"mon":{"active":"1","start":"09:00","end":"17:00"},"tue":{"active":"1","start":"09:00","end":"17:00"},"wed":{"active":"1","start":"09:00","end":"17:00"},"thu":{"active":"1","start":"09:00","end":"17:00"},"fri":{"active":"1","start":"09:00","end":"17:00"}}'
                ),
                array(
                    'name' => 'Sophia Lauren', 'email' => 'sophia@example.com', 'phone' => '555-0199', 'status' => 'active',
                    'description' => 'Expert colorist specializing in balayage and creative lifting.',
                    'qualification' => 'Senior Colorist', 'address' => '456 Styling Ave',
                    'image_url' => 'https://ui-avatars.com/api/?name=Sophia+Lauren&background=D81B60&color=fff&size=200',
                    'schedule_config' => '{"mon":{"active":"1","start":"10:00","end":"18:00"},"tue":{"active":"1","start":"10:00","end":"18:00"},"thu":{"active":"1","start":"10:00","end":"18:00"},"sat":{"active":"1","start":"08:00","end":"14:00"}}'
                ),
                array(
                    'name' => 'Marcus Reed', 'email' => 'marcus@example.com', 'phone' => '555-0211', 'status' => 'inactive',
                    'description' => 'Specializes in therapeutic massages and deep tissue recovery.',
                    'qualification' => 'Licensed Massage Therapist', 'address' => '789 Recovery Blvd',
                    'image_url' => 'https://ui-avatars.com/api/?name=Marcus+Reed&background=43A047&color=fff&size=200',
                    'schedule_config' => '{"mon":{"active":"1","start":"08:00","end":"14:00"},"wed":{"active":"1","start":"12:00","end":"20:00"},"fri":{"active":"1","start":"08:00","end":"16:00"}}'
                )
            );
            foreach($dummy_staff as $st) {
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
            }
            $days = array('mon'=>'Monday', 'tue'=>'Tuesday', 'wed'=>'Wednesday', 'thu'=>'Thursday', 'fri'=>'Friday', 'sat'=>'Saturday', 'sun'=>'Sunday');
            ?>
            <div class="wrap wsb-admin-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h1 style="margin:0;"><?php echo $action === 'edit' ? 'Edit Staff Profile' : 'Add New Staff'; ?></h1>
                    <a href="?page=<?php echo esc_attr($this->plugin_name . '-staff'); ?>" class="wsb-btn-primary" style="background:var(--wsb-border);">Back to Roster</a>
                </div>
                <hr class="wp-header-end" style="margin-bottom:20px;">

                <form method="post" action="?page=<?php echo esc_attr($this->plugin_name . '-staff'); ?>&action=<?php echo $action; ?><?php echo $staff_id ? '&id='.$staff_id : ''; ?>">
                    <?php wp_nonce_field('wsb_staff_save', 'wsb_staff_nonce'); ?>
                    
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
                        <div>
                            <!-- Core Identity -->
                            <div style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid var(--wsb-primary); margin-bottom:20px;">
                                <h3 style="margin-top:0; color:var(--wsb-primary);">👤 Personal Information</h3>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Full Name</label>
                                        <input name="staff_name" type="text" value="<?php echo $s ? esc_attr($s->name) : ''; ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;" required>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Profession / Qualification</label>
                                        <input name="staff_qualification" type="text" placeholder="e.g. Senior Hairstylist, Master Technician" value="<?php echo $s && isset($s->qualification) ? esc_attr($s->qualification) : ''; ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;">
                                    </div>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Email Address</label>
                                        <input name="staff_email" type="email" value="<?php echo $s ? esc_attr($s->email) : ''; ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;" required>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Phone Number</label>
                                        <input name="staff_phone" type="text" value="<?php echo $s ? esc_attr($s->phone) : ''; ?>" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;">
                                    </div>
                                </div>
                                <div style="margin-bottom:15px;">
                                    <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Physical Address</label>
                                    <textarea name="staff_address" rows="2" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;"><?php echo $s && isset($s->address) ? esc_textarea($s->address) : ''; ?></textarea>
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Public Biography / Description</label>
                                    <textarea name="description" rows="3" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;"><?php echo $s ? esc_textarea($s->description) : ''; ?></textarea>
                                </div>
                            </div>

                            <!-- Schedule Settings -->
                            <div style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid var(--wsb-warning);">
                                <h3 style="margin-top:0; color:var(--wsb-warning);">📅 Weekly Schedule</h3>
                                <p style="color:var(--wsb-text-muted); font-size:13px; margin-bottom:15px;">Configure the working hours for this provider. If not working on a day, leave times blank or uncheck.</p>
                                
                                <?php foreach($days as $key => $label): 
                                    $is_working = isset($schedule[$key]['active']) && $schedule[$key]['active'] == '1';
                                    $start = isset($schedule[$key]['start']) ? $schedule[$key]['start'] : '09:00';
                                    $end = isset($schedule[$key]['end']) ? $schedule[$key]['end'] : '17:00';
                                ?>
                                    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.05);">
                                        <label style="display:flex; align-items:center; gap:10px; width:150px; cursor:pointer;">
                                            <input type="checkbox" name="schedule[<?php echo $key; ?>][active]" value="1" <?php checked($is_working); ?> style="background:#0f172a; border:1px solid var(--wsb-primary);">
                                            <strong style="color:<?php echo $is_working ? 'white' : 'var(--wsb-text-muted)'; ?>"><?php echo $label; ?></strong>
                                        </label>
                                        <div style="display:flex; align-items:center; gap:10px; opacity:<?php echo $is_working ? '1' : '0.4'; ?>;">
                                            <input type="time" name="schedule[<?php echo $key; ?>][start]" value="<?php echo esc_attr($start); ?>" style="background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:4px 8px; border-radius:4px;">
                                            <span style="color:var(--wsb-text-muted);">to</span>
                                            <input type="time" name="schedule[<?php echo $key; ?>][end]" value="<?php echo esc_attr($end); ?>" style="background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:4px 8px; border-radius:4px;">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <!-- Side Panel - Image/Status -->
                            <div style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); margin-bottom:20px;">
                                <h3 style="margin-top:0;">Profile Image</h3>
                                <div style="display:flex; flex-direction:column; gap:10px; align-items:center; margin-bottom:15px;">
                                    <div id="wsb-staff-preview" style="width:120px; height:120px; border-radius:50%; border:2px dashed var(--wsb-border); background:#0f172a <?php echo $s && isset($s->image_url) && $s->image_url ? 'url('.esc_url($s->image_url).') center/cover' : ''; ?>;"></div>
                                    <input type="hidden" name="staff_image_url" id="staff_image_url" value="<?php echo $s && isset($s->image_url) ? esc_url($s->image_url) : ''; ?>">
                                    <button type="button" class="wsb-btn-primary wsb-select-image" data-target="#staff_image_url" data-preview="#wsb-staff-preview" style="background:var(--wsb-border); color:white; width:100%;">Select Avatar</button>
                                </div>
                                <hr style="border:0; border-top:1px solid var(--wsb-border); margin:15px 0;">
                                <div style="margin-bottom:15px;">
                                    <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">System Status</label>
                                    <select name="status" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;">
                                        <option value="active" <?php selected($s ? $s->status : 'active', 'active'); ?>>Active (Accepting Bookings)</option>
                                        <option value="inactive" <?php selected($s ? $s->status : '', 'inactive'); ?>>Inactive (Hidden)</option>
                                    </select>
                                </div>
                                <button type="submit" class="wsb-btn-primary" style="width:100%; padding:12px; font-size:16px; background:var(--wsb-success);">Save Staff Configuration</button>
                            </div>

                            <div style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); border-top:4px solid #ef4444;">
                                <h3 style="margin-top:0; color:#ef4444;">🌴 Time off & Holidays</h3>
                                <p style="color:var(--wsb-text-muted); font-size:13px;">Enter exact dates where this staff member is unavailable. Use YYYY-MM-DD format on a new line for each date.</p>
                                <textarea name="holidays" rows="5" style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); padding:10px; border-radius:6px;" placeholder="2026-12-25&#10;2026-11-28"><?php echo $s ? esc_textarea($s->holidays) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php
        } else {
            // View: List Filter Logic
            $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
            $where_clause = "WHERE 1=1";
            if (in_array($filter_status, ['active', 'inactive'])) {
                $where_clause .= " AND status = '{$filter_status}'";
            }

            $staff = $wpdb->get_results("SELECT * FROM {$table_staff} {$where_clause} ORDER BY created_at DESC");
            $total_staff = $wpdb->get_var("SELECT COUNT(*) FROM {$table_staff}");
            $active_staff = $wpdb->get_var("SELECT COUNT(*) FROM {$table_staff} WHERE status='active'");
            $inactive_staff = $wpdb->get_var("SELECT COUNT(*) FROM {$table_staff} WHERE status='inactive'");

            $page_url = "?page=" . esc_attr($this->plugin_name . '-staff');
            ?>
            <div class="wrap wsb-admin-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h1 style="margin:0;">Staff Roster</h1>
                    <div>
                        <a href="<?php echo wp_nonce_url("?page=".$this->plugin_name."-staff&action=seed", 'seed_staff'); ?>" class="wsb-btn-primary" style="background:var(--wsb-warning); margin-right:10px;">⚡ Inject Dummy Staff</a>
                        <a href="?page=<?php echo esc_attr($this->plugin_name . '-staff'); ?>&action=add" class="wsb-btn-primary">+ Onboard Staff</a>
                    </div>
                </div>

                <style>
                    .staff-filter-card { border-left: 4px solid transparent; text-decoration: none; color: inherit; display: block; border: 1px solid var(--wsb-border); border-radius: 12px; background: var(--wsb-panel-dark); padding: 20px; transition: transform 0.2s; }
                    .staff-filter-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }
                    .card-active { background: rgba(59, 130, 246, 0.1) !important; border-left: 4px solid var(--wsb-primary) !important; border-color: var(--wsb-primary) !important; }
                </style>
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top:20px; margin-bottom:20px;">
                    <a href="<?php echo $page_url; ?>&filter_status=all" class="staff-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-text-muted);">Total Staff</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($total_staff); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=active" class="staff-filter-card <?php echo $filter_status === 'active' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-success);">Active Providers</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($active_staff); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=inactive" class="staff-filter-card <?php echo $filter_status === 'inactive' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-warning);">Inactive / On Leave</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($inactive_staff); ?></p>
                    </a>
                </div>
                
                <div style="background: var(--wsb-panel-dark); border-radius: 12px; border: 1px solid var(--wsb-border); overflow: hidden;">
                    <table class="wsb-modern-table" style="margin:0; width:100%;">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact details</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($staff)) : foreach ($staff as $s) : ?>
                                <tr class="wsb-clickable-row" data-href="?page=<?php echo esc_attr($this->plugin_name . '-staff'); ?>&action=edit&id=<?php echo $s->id; ?>">
                                    <td>
                                        <div style="display:flex; align-items:center; gap:15px;">
                                            <?php if (!empty($s->image_url)) : ?>
                                                <div style="width:40px; height:40px; border-radius:50%; background:url('<?php echo esc_url($s->image_url); ?>') center/cover; border:2px solid var(--wsb-border);"></div>
                                            <?php else: ?>
                                                <div style="width:40px; height:40px; border-radius:50%; background:var(--wsb-border); display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:16px;">
                                                    <?php echo esc_html(strtoupper(substr($s->name, 0, 1))); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong style="color:white; font-size:15px; display:block;"><?php echo esc_html($s->name); ?></strong>
                                                <?php if(!empty($s->qualification)): ?>
                                                    <span style="color:var(--wsb-primary); font-size:12px;"><?php echo esc_html($s->qualification); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span style="color:var(--wsb-text-muted); font-size:13px;">✉️ <?php echo esc_html($s->email); ?></span>
                                            <span style="color:var(--wsb-text-muted); font-size:13px; margin-top:3px;">📞 <?php echo esc_html($s->phone ?: 'N/A'); ?></span>
                                        </div>
                                    </td>
                                    <td><span class="wsb-status wsb-status-<?php echo $s->status === 'active' ? 'completed' : 'cancelled'; ?>"><?php echo esc_html(ucfirst($s->status)); ?></span></td>
                                    <td align="right">
                                        <div class="wsb-row-actions">
                                            <a href="?page=<?php echo esc_attr($this->plugin_name . '-staff'); ?>&action=edit&id=<?php echo $s->id; ?>" class="wsb-row-action wsb-action-edit" title="Edit Staff Member">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                            </a>
                                            <a href="<?php echo wp_nonce_url("?page=".$this->plugin_name."-staff&action=delete&id=".$s->id, 'delete_staff_'.$s->id); ?>" class="wsb-row-action wsb-action-delete" title="Remove Staff Member" onclick="return confirm('Are you sure you want to fire this staff member? This deletes their record entirely.');">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="4" style="padding:40px; text-align:center; color:var(--wsb-text-muted);">Roster is empty.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
        }
    }

    public function display_customers_page() {
        global $wpdb;
        $table_customers = $wpdb->prefix . 'wsb_customers';
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';

        // Seed Dummy Data Handler
        if ($action === 'seed' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'seed_customers')) {
            $dummies = array(
                array('first_name'=>'Emily', 'last_name'=>'Blunt', 'email'=>'emily@example.com', 'phone'=>'(555) 123-4567'),
                array('first_name'=>'John', 'last_name'=>'Krasinski', 'email'=>'john@example.com', 'phone'=>'(555) 987-6543'),
                array('first_name'=>'Margot', 'last_name'=>'Robbie', 'email'=>'margot@example.com', 'phone'=>'(555) 222-3333'),
                array('first_name'=>'Ryan', 'last_name'=>'Gosling', 'email'=>'ryan@example.com', 'phone'=>'(555) 444-5555')
            );
            foreach($dummies as $d) {
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
                    <a href="<?php echo wp_nonce_url("?page=".$this->plugin_name."-customers&action=seed", 'seed_customers'); ?>" class="wsb-btn-primary" style="background:var(--wsb-warning);">⚡ Inject Dummy Customers</a>
                </div>
            </div>

            <!-- Dashboard Interactive Filters -->
            <style>
                .customer-filter-card { border-left: 4px solid transparent; text-decoration: none; color: inherit; display: block; border: 1px solid var(--wsb-border); border-radius: 12px; background: var(--wsb-panel-dark); padding: 20px; transition: transform 0.2s; }
                .customer-filter-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); }
                .card-active { background: rgba(59, 130, 246, 0.1) !important; border-left: 4px solid var(--wsb-primary) !important; border-color: var(--wsb-primary) !important; }
            </style>
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top:20px; margin-bottom:20px;">
                <a href="<?php echo $page_url; ?>&filter_status=all" class="customer-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--wsb-text-muted);">Total Client Base</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($total_customers); ?></p>
                </a>
                <a href="<?php echo $page_url; ?>&filter_status=recent" class="customer-filter-card <?php echo $filter_status === 'recent' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--wsb-success);">Recent Signups (30d)</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($recent_customers); ?></p>
                </a>
                <a href="<?php echo $page_url; ?>&filter_status=vip" class="customer-filter-card <?php echo $filter_status === 'vip' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--wsb-warning);">VIP Clients (LTV)</h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);"><?php echo intval($vip_customers); ?></p>
                </a>
            </div>

            <div style="background: var(--wsb-panel-dark); border-radius: 12px; border: 1px solid var(--wsb-border); overflow: hidden;">
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
                        <?php if (!empty($customers)) : foreach ($customers as $c) : ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap:15px;">
                                        <div style="width:40px; height:40px; border-radius:50%; background:var(--wsb-border); display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:16px;">
                                            <?php echo esc_html(strtoupper(substr($c->first_name, 0, 1) . substr($c->last_name, 0, 1))); ?>
                                        </div>
                                        <div>
                                            <strong style="color:white; font-size:15px; display:block;"><?php echo esc_html($c->first_name . ' ' . $c->last_name); ?></strong>
                                            <span style="color:var(--wsb-text-muted); font-size:12px; font-family:monospace;">ID: #<?php echo esc_html(str_pad($c->id, 5, '0', STR_PAD_LEFT)); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wsb-customer-info">
                                        <span style="color:var(--wsb-text-muted); font-size:13px;">✉️ <?php echo esc_html($c->email); ?></span>
                                        <span style="color:var(--wsb-text-muted); font-size:13px; margin-top:3px;">📞 <?php echo esc_html($c->phone ?: 'N/A'); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span style="color:var(--wsb-text-muted); font-size:13px; display:block;">Joined: <?php echo esc_html(date('M d, Y', strtotime($c->created_at))); ?></span>
                                    <span style="color:var(--wsb-primary); font-size:12px; font-weight:bold; margin-top:3px; display:block;">Bookings: <?php echo intval($c->booking_count); ?></span>
                                </td>
                                <td align="right">
                                    <strong style="color:var(--wsb-success); font-size:16px;"><?php echo $c->total_spent > 0 ? '$' . number_format($c->total_spent, 2) : '-'; ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="4" style="text-align:center; padding: 40px; color: var(--wsb-text-muted);">No client records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    public function display_finance_page() {
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
                <form method="get" style="display:flex; align-items:center; gap:10px;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
                    <span style="color:var(--wsb-text-muted); font-size:14px;">Reporting Period:</span>
                    <select name="period" style="background:#0f172a; color:white; border:1px solid var(--wsb-primary); padding:6px 12px; border-radius:6px; font-weight:bold;" onchange="this.form.submit()">
                        <option value="all" <?php selected($period, 'all'); ?>>All Time</option>
                        <option value="today" <?php selected($period, 'today'); ?>>Today</option>
                        <option value="7days" <?php selected($period, '7days'); ?>>Last 7 Days</option>
                        <option value="30days" <?php selected($period, '30days'); ?>>Last 30 Days</option>
                        <option value="year" <?php selected($period, 'year'); ?>>This Year</option>
                    </select>
                </form>
            </div>
            
            <!-- Metric Cards -->
            <div class="wsb-dashboard-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom:30px;">
                <div class="wsb-stat-card" style="border-left: 4px solid var(--wsb-success);">
                    <h3 style="margin-top:0; font-size:16px;">Total Realized Revenue</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--wsb-success);">$<?php echo number_format((float)$total_revenue, 2); ?></p>
                </div>
                <div class="wsb-stat-card" style="border-left: 4px solid var(--wsb-primary);">
                    <h3 style="margin-top:0; font-size:16px;">Verified Transactions</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--wsb-primary);"><?php echo intval($total_transactions); ?></p>
                </div>
                <div class="wsb-stat-card" style="border-left: 4px solid #10b981;">
                    <h3 style="margin-top:0; font-size:16px;">Avg. Transaction Value</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:#10b981;">$<?php echo number_format((float)$avg_transaction, 2); ?></p>
                </div>
                <div class="wsb-stat-card" style="border-left: 4px solid var(--wsb-warning);">
                    <h3 style="margin-top:0; font-size:16px;">Pending / Outstanding</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--wsb-warning);">$<?php echo number_format((float)$pending_revenue, 2); ?></p>
                </div>
            </div>

            <!-- Dynamic Chart -->
            <div style="background:var(--wsb-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--wsb-border); margin-bottom:30px;">
                <h3 style="margin:0 0 20px 0; color: #fff;">Revenue Performance Analysis</h3>
                <div style="position:relative; height:300px; width:100%;">
                    <canvas id="wsbRevenueChart"></canvas>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const rawData = <?php echo $chart_json; ?>;
                    const ctx = document.getElementById('wsbRevenueChart').getContext('2d');
                    const labels = rawData.map(item => item.label);
                    const values = rawData.map(item => parseFloat(item.val));

                    // Multi-step Gradient for Bar Chart
                    let gradient = ctx.createLinearGradient(0, 0, 0, 300);
                    gradient.addColorStop(0, '#3b82f6');
                    gradient.addColorStop(1, 'rgba(59, 130, 246, 0.2)');

                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels.length ? labels : ['No Data in Period'],
                            datasets: [{
                                label: 'Revenue ($)',
                                data: values.length ? values : [0],
                                backgroundColor: gradient,
                                borderColor: '#60a5fa',
                                borderWidth: 1,
                                borderRadius: 6, // Rounded bars for a modern look
                                borderSkipped: false,
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
                                    bodyFont: { weight: 'bold' },
                                    displayColors: false,
                                    padding: 12,
                                    borderColor: 'var(--wsb-primary)',
                                    borderWidth: 1
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                                    ticks: { color: '#94a3b8', callback: function(val) { return '$' + val; } }
                                },
                                x: {
                                    grid: { display: false, drawBorder: false },
                                    ticks: { color: '#94a3b8' }
                                }
                            }
                        }
                    });
                });
            </script>
            
            <!-- Ledger Data Table -->
            <div style="background:var(--wsb-panel-dark); border-radius:12px; border:1px solid var(--wsb-border); overflow:hidden;">
                <div style="padding: 20px; border-bottom: 1px solid var(--wsb-border);">
                    <h3 style="margin:0; color: #fff;">Recent Activity</h3>
                </div>
                <table class="wsb-modern-table" style="margin:0;">
                    <thead>
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
                        <?php if (!empty($payments)) : foreach ($payments as $p) : ?>
                            <tr>
                                <td><strong style="color:var(--wsb-text-muted); font-family:monospace;"><?php echo esc_html($p->transaction_id ?: 'N/A'); ?></strong></td>
                                <td><span style="background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:12px;"><?php echo esc_html(strtoupper($p->gateway)); ?></span></td>
                                <td><strong style="color:var(--wsb-success);">$<?php echo number_format((float)$p->amount, 2); ?></strong></td>
                                <td>
                                    <div class="wsb-customer-info">
                                        <span class="wsb-customer-name" style="color:var(--wsb-primary);">Booking #<?php echo esc_html(str_pad($p->booking_id, 5, '0', STR_PAD_LEFT)); ?></span>
                                        <span class="wsb-customer-meta"><?php echo esc_html($p->first_name . ' ' . $p->last_name . ' - ' . $p->service_name); ?></span>
                                    </div>
                                </td>
                                <td><?php echo esc_html(date('M d, Y', strtotime($p->created_at))); ?></td>
                                <td><span class="wsb-status wsb-status-<?php echo esc_attr($p->status); ?>"><?php echo esc_html(ucfirst($p->status)); ?></span></td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="6" style="text-align:center; padding: 40px; color: var(--wsb-text-muted);">No payment records found in this timeframe.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public function display_settings_page() {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['wsb_settings_nonce']) && wp_verify_nonce($_POST['wsb_settings_nonce'], 'wsb_save_settings')) {
                update_option('wsb_currency', sanitize_text_field($_POST['wsb_currency']));
                
                // Payment Integrations
                update_option('wsb_stripe_publishable_key', sanitize_text_field($_POST['wsb_stripe_publishable_key']));
                update_option('wsb_stripe_secret_key', sanitize_text_field($_POST['wsb_stripe_secret_key']));
                
                update_option('wsb_paypal_client_id', sanitize_text_field($_POST['wsb_paypal_client_id']));
                update_option('wsb_paypal_secret', sanitize_text_field($_POST['wsb_paypal_secret']));
                
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
        $paypal_cid = get_option('wsb_paypal_client_id', '');
        $paypal_sec = get_option('wsb_paypal_secret', '');
        ?>
        <div class="wrap wsb-admin-wrap">
            <h1 style="margin-bottom:20px;">System Settings & Integrations</h1>
            
            <style>
                .wsb-accordion { background: var(--wsb-panel-dark); border: 1px solid var(--wsb-border); border-radius: 8px; margin-bottom: 15px; overflow: hidden; }
                .wsb-accordion summary { background: rgba(255,255,255,0.02); padding: 15px 20px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: space-between; user-select: none; border-bottom: 1px solid transparent; }
                .wsb-accordion details[open] summary { border-bottom: 1px solid var(--wsb-border); }
                .wsb-accordion .content { padding: 20px; }
                .wsb-accordion input[type="text"], .wsb-accordion input[type="password"], .wsb-accordion select { background: #0f172a; color: white; border: 1px solid var(--wsb-border); padding: 10px; border-radius: 6px; width: 100%; max-width: 400px; margin-top: 5px; }
                .wsb-layout-selector { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px; }
                .wsb-layout-option { position: relative; cursor: pointer; }
                .wsb-layout-option input { position: absolute; opacity: 0; }
                .wsb-layout-preview { aspect-ratio: 4/3; background: #0f172a; border: 2px solid var(--wsb-border); border-radius: 10px; transition: all 0.2s; display: flex; flex-direction: column; gap: 4px; padding: 10px; overflow: hidden; position: relative; }
                .wsb-layout-option input:checked + .wsb-layout-preview { border-color: var(--wsb-primary); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
                .wsb-layout-preview::after { content: '✓'; position: absolute; top: 5px; right: 5px; background: var(--wsb-primary); color: white; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; opacity: 0; transform: scale(0); transition: all 0.2s; }
                .wsb-layout-option input:checked + .wsb-layout-preview::after { opacity: 1; transform: scale(1); }
                .wsb-layout-name { display: block; text-align: center; margin-top: 8px; font-size: 13px; color: var(--wsb-text-muted); font-weight: 500; }
                .wsb-layout-option:hover .wsb-layout-preview { border-color: rgba(255,255,255,0.2); }
            </style>

            <form method="post">
                <?php wp_nonce_field('wsb_save_settings', 'wsb_settings_nonce'); ?>
                
                <!-- General Settings -->
                <details class="wsb-accordion" open>
                    <summary>🌍 General Configuration</summary>
                    <div class="content">
                        <label style="color:var(--wsb-text-muted); display:block; margin-bottom:15px;">
                            <strong style="color:white; display:block; margin-bottom:5px;">System Currency</strong>
                            <select name="wsb_currency">
                                <option value="USD" <?php selected($currency, 'USD'); ?>>USD - United States Dollar ($)</option>
                                <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR - Euro (€)</option>
                                <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP - British Pound (£)</option>
                                <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD - Canadian Dollar (C$)</option>
                                <option value="AUD" <?php selected($currency, 'AUD'); ?>>AUD - Australian Dollar (A$)</option>
                                <option value="JPY" <?php selected($currency, 'JPY'); ?>>JPY - Japanese Yen (¥)</option>
                                <option value="INR" <?php selected($currency, 'INR'); ?>>INR - Indian Rupee (₹)</option>
                            </select>
                        </label>
                    </div>
                </details>



                <!-- Stripe Settings -->
                <details class="wsb-accordion">
                    <summary>💳 Stripe Integration</summary>
                    <div class="content">
                        <label style="color:var(--wsb-text-muted); display:block; margin-bottom:15px;">
                            <strong style="color:white; display:block; margin-bottom:5px;">Publishable Key</strong>
                            <input name="wsb_stripe_publishable_key" type="text" value="<?php echo esc_attr($stripe_pk); ?>">
                        </label>
                        <label style="color:var(--wsb-text-muted); display:block; margin-bottom:15px;">
                            <strong style="color:white; display:block; margin-bottom:5px;">Secret Key</strong>
                            <input name="wsb_stripe_secret_key" type="password" value="<?php echo esc_attr($stripe_sk); ?>">
                        </label>
                    </div>
                </details>

                <!-- PayPal Settings -->
                <details class="wsb-accordion">
                    <summary>🅿️ PayPal Integration</summary>
                    <div class="content">
                        <label style="color:var(--wsb-text-muted); display:block; margin-bottom:15px;">
                            <strong style="color:white; display:block; margin-bottom:5px;">Client ID</strong>
                            <input name="wsb_paypal_client_id" type="text" value="<?php echo esc_attr($paypal_cid); ?>">
                        </label>
                        <label style="color:var(--wsb-text-muted); display:block; margin-bottom:15px;">
                            <strong style="color:white; display:block; margin-bottom:5px;">Secret Key</strong>
                            <input name="wsb_paypal_secret" type="password" value="<?php echo esc_attr($paypal_sec); ?>">
                        </label>
                    </div>
                </details>

                <!-- Booking Widget Code -->
                <details class="wsb-accordion" open>
                    <summary>🔗 Frontend Integration</summary>
                    <div class="content">
                        <p style="color:var(--wsb-text-muted);">Embed the scheduling widget onto any page or post utilizing this exact shortcode:</p>
                        <code style="background: rgba(59, 130, 246, 0.1); color: var(--wsb-primary); padding: 15px; border-radius: 8px; display: block; font-size: 18px; border: 1px dashed var(--wsb-primary); text-align: center;">[wsb_booking_widget]</code>
                        <p style="color:var(--wsb-text-muted); margin-top:15px;">Or directly copy your Booking System static route link:</p>
                        <input type="text" readonly value="<?php echo site_url('/booking'); ?>" onclick="this.select();" style="width:100%; max-width:100% !important; background:rgba(0,0,0,0.2) !important; color:var(--wsb-success) !important; cursor:pointer;">
                    </div>
                </details>

                <div style="margin-bottom:40px;">
                    <button type="submit" class="wsb-btn-primary" style="padding:12px 30px; font-size:16px;">Save System Architecture</button>
                </div>
            </form>

            <div style="background: rgba(239, 68, 68, 0.05); padding: 20px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.3);">
                <h3 style="margin-top:0; color: #ef4444;">Developer Tools</h3>
                <p style="color: var(--wsb-text-muted);">Use this to populate the custom database tables with dummy realistic data to test the system visually.</p>
                <form method="post">
                    <?php wp_nonce_field('wsb_generate_dummy', 'wsb_dummy_nonce'); ?>
                    <input type="submit" name="generate_dummy" value="Force Global Dummy Injection" class="wsb-btn-primary" style="background:#ef4444; border:none; padding:10px 15px;" onclick="return confirm('WARNING: Generating data will duplicate records. Proceed?');" />
                </form>
            </div>
        </div>
        <?php
    }

    private function generate_dummy_data($wpdb) {
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
        foreach($services_data as $s) {
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
        foreach($staff_data as $st) {
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
        foreach($customer_data as $c) {
            $wpdb->insert($tables['customers'], $c);
            $customer_ids[] = $wpdb->insert_id;
        }

        // 4. Insert Bookings & Payments
        $statuses = array('confirmed', 'pending', 'completed');
        for ($i=0; $i<15; $i++) {
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
                'transaction_id' => 'ch_test_' . rand(1000,9999),
                'status' => ($status === 'pending') ? 'pending' : 'completed'
            ));
        }
    }

    public function display_design_page() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wsb_design_nonce']) && wp_verify_nonce($_POST['wsb_design_nonce'], 'wsb_save_design')) {
            update_option('wsb_service_layout', sanitize_text_field($_POST['wsb_service_layout']));
            update_option('wsb_brand_color', sanitize_hex_color($_POST['wsb_brand_color']));
            update_option('wsb_brand_color_end', sanitize_hex_color($_POST['wsb_brand_color_end']));
            update_option('wsb_accent_color', sanitize_hex_color($_POST['wsb_accent_color']));
            echo '<div class="notice notice-success is-dismissible"><p>Design and aesthetic preferences saved!</p></div>';
        }

        $service_layout = get_option('wsb_service_layout', 'modern_grid');
        $brand_color = get_option('wsb_brand_color', '#6366f1');
        $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
        $accent_color = get_option('wsb_accent_color', '#4f46e5');
        ?>
        <div class="wrap wsb-admin-wrap">
            <h1 style="margin-bottom:20px;">Frontend Experience & Designer</h1>
            <p style="color:var(--wsb-text-muted); margin-bottom:30px;">Customize how your booking widget looks and feels to your customers.</p>

            <style>
                .wsb-design-section { background: var(--wsb-panel-dark); border: 1px solid var(--wsb-border); border-radius: 12px; padding: 30px; margin-bottom: 25px; }
                .wsb-color-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; padding-bottom: 30px; border-bottom: 1px solid var(--wsb-border); }
                .wsb-layout-selector { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 20px; }
                .wsb-layout-option { position: relative; cursor: pointer; }
                .wsb-layout-option input { position: absolute; opacity: 0; }
                .wsb-layout-preview { aspect-ratio: 4/3; background: #0f172a; border: 2px solid var(--wsb-border); border-radius: 10px; transition: all 0.2s; display: flex; justify-content:center; align-items:center; overflow: hidden; position: relative; }
                .wsb-layout-option input:checked + .wsb-layout-preview { border-color: var(--wsb-primary); box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2); }
                .wsb-layout-preview::after { content: '✓'; position: absolute; top: 8px; right: 8px; background: var(--wsb-primary); color: white; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; opacity: 0; transform: scale(0); transition: all 0.2s; z-index:10; }
                .wsb-layout-option input:checked + .wsb-layout-preview::after { opacity: 1; transform: scale(1); }
                .wsb-layout-name { display: block; text-align: center; margin-top: 10px; font-size: 13px; color: var(--wsb-text-muted); font-weight: 600; }
                .wsb-layout-option:hover .wsb-layout-preview { border-color: rgba(255,255,255,0.2); }
                
                @media (max-width: 1200px) { .wsb-layout-selector { grid-template-columns: repeat(3, 1fr); } }
                @media (max-width: 900px) { .wsb-layout-selector { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 768px) { .wsb-color-row { grid-template-columns: 1fr; } }
            </style>

            <form method="post">
                <?php wp_nonce_field('wsb_save_design', 'wsb_design_nonce'); ?>

                <div class="wsb-design-section">
                    <h2 style="color:white; margin-bottom:20px;">🎨 Brand Identity & Gradients</h2>
                    <div class="wsb-color-row">
                        <div>
                            <strong style="color:white; display:block; margin-bottom:10px;">Primary Color (Start)</strong>
                            <input type="color" name="wsb_brand_color" value="<?php echo esc_attr($brand_color); ?>" style="width:100%; height:50px; cursor:pointer;" />
                        </div>
                        <div>
                            <strong style="color:white; display:block; margin-bottom:10px;">Gradient Color (End)</strong>
                            <input type="color" name="wsb_brand_color_end" value="<?php echo esc_attr($brand_color_end); ?>" style="width:100%; height:50px; cursor:pointer;" />
                        </div>
                        <div>
                            <strong style="color:white; display:block; margin-bottom:10px;">Interactive Accent</strong>
                            <input type="color" name="wsb_accent_color" value="<?php echo esc_attr($accent_color); ?>" style="width:100%; height:50px; cursor:pointer;" />
                        </div>
                    </div>

                    <h2 style="color:white; margin-bottom:10px;">📐 Layout & Aesthetic Style</h2>
                    <p style="color:var(--wsb-text-muted); font-size:13px; margin-bottom:25px;">Choose from 18 professionally crafted design languages for your service display.</p>

                    <div class="wsb-layout-selector">
                        <?php 
                        $layouts = [
                            'modern_grid' => 'Modern Grid',
                            'classic_list' => 'Classic List',
                            'glass_cards' => 'Glassmorphism',
                            'minimal' => 'Pure Minimal',
                            'carousel' => 'Hero View',
                            'elegant_wide' => 'Elegant Wide',
                            'neon_night' => 'Neon Night',
                            'retro_pop' => 'Retro Pop',
                            'brutalist_mono' => 'Brutalist',
                            'soft_blush' => 'Soft Blush',
                            'metro_grid' => 'Metro Grid',
                            'outline_modern' => 'Outline',
                            'gradient_mesh' => 'Mesh Gradient',
                            'royal_gold' => 'Royal Gold',
                            'eco_fresh' => 'Eco Fresh',
                            'floating_cards' => 'Floating',
                            'dark_minimal_list' => 'Dark Minimal',
                            'glass_cards_v2' => 'Glass V2'
                        ];
                        foreach($layouts as $val => $name): ?>
                        <label class="wsb-layout-option">
                            <input type="radio" name="wsb_service_layout" value="<?php echo $val; ?>" <?php checked($service_layout, $val); ?>>
                            <div class="wsb-layout-preview">
                                <div style="font-size:10px; color:rgba(255,255,255,0.2); text-transform:uppercase; font-weight:700;"><?php echo $name; ?></div>
                                <!-- Background preview hint based on name -->
                                <?php if($val == 'neon_night'): ?><div style="position:absolute; inset:0; border:1px solid #818cf8; opacity:0.3;"></div><?php endif; ?>
                                <?php if($val == 'royal_gold'): ?><div style="position:absolute; inset:0; border:1px solid #c5a059; opacity:0.3;"></div><?php endif; ?>
                            </div>
                            <span class="wsb-layout-name"><?php echo $name; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="button button-primary button-large" style="padding:10px 30px; height:auto;">Apply Premium Design</button>
                </div>
            </form>
        </div>
        <?php
    }
}
