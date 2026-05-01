<?php
class Wsb_Admin_Bookings {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
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
                    $this->admin->wsb_notify_status_change($booking_id, 'confirmed');
                } elseif ($decision === 'reject') {
                    $wpdb->update($table_bookings, array(
                        'status' => 'confirmed',
                        'request_type' => NULL,
                        'requested_date' => NULL,
                        'requested_time' => NULL,
                        'requested_staff_id' => NULL
                    ), array('id' => $booking_id));
                    echo '<div class="notice notice-warning is-dismissible"><p>Client request declined. Booking remains active.</p></div>';
                    $this->admin->wsb_notify_status_change($booking_id, 'confirmed');
                }
            }
            $action = 'list';
        }

        // Standard quick status overrides
        if ($action === 'status' && $booking_id) {
            $new_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
            if (in_array($new_status, ['confirmed', 'cancelled', 'pending', 'completed'])) {
                $wpdb->update($table_bookings, array('status' => $new_status), array('id' => $booking_id));
                $this->admin->wsb_notify_status_change($booking_id, $new_status);
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
            $this->admin->wsb_notify_status_change($booking_id, sanitize_text_field($_POST['status']));
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
                                <td colspan="6" style="text-align:center; padding: 40px; color: var(--wsb-text-muted);">No bookings found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
