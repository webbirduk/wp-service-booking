<?php
class Bc_Admin_Bookings
{
    private $admin;

    public function __construct($admin)
    {
        $this->admin = $admin;
    }

    public function display()
    {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'bc_bookings';

        // Auto-patch schema
        $wpdb->query("ALTER TABLE {$table_bookings} MODIFY COLUMN service_id varchar(255) NOT NULL");

        $action = isset($_REQUEST['bc_action']) ? sanitize_text_field($_REQUEST['bc_action']) : (isset($_GET['action']) ? $_GET['action'] : 'list');
        $booking_id = isset($_REQUEST['booking_id']) ? intval($_REQUEST['booking_id']) : (isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0);

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
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('Reschedule request approved and applied successfully.', 'boocommerce') . '</p></div>';
                    } elseif ($booking_record->request_type === 'cancel') {
                        $wpdb->update($table_bookings, array(
                            'status' => 'cancelled',
                            'request_type' => NULL
                        ), array('id' => $booking_id));
                        echo '<div class="notice notice-success is-dismissible"><p>' . __('Cancellation request approved successfully.', 'boocommerce') . '</p></div>';
                    }
                    $this->admin->bc_notify_status_change($booking_id, 'confirmed');
                } elseif ($decision === 'reject') {
                    $wpdb->update($table_bookings, array(
                        'status' => 'confirmed',
                        'request_type' => NULL,
                        'requested_date' => NULL,
                        'requested_time' => NULL,
                        'requested_staff_id' => NULL
                    ), array('id' => $booking_id));
                    echo '<div class="notice notice-warning is-dismissible"><p>' . __('Client request declined. Booking remains active.', 'boocommerce') . '</p></div>';
                    $this->admin->bc_notify_status_change($booking_id, 'confirmed');
                }
            }
            $action = 'list';
        }

        // Standard quick status overrides
        if ($action === 'status' && $booking_id) {
            $new_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
            if (in_array($new_status, ['confirmed', 'cancelled', 'pending', 'completed'])) {
                $wpdb->update($table_bookings, array('status' => $new_status), array('id' => $booking_id));
                $this->admin->bc_notify_status_change($booking_id, $new_status);
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Status updated cleanly.', 'boocommerce') . '</p></div>';
            }
            $action = 'list';
        }

        // Full Edit Submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bc_edit_booking_nonce'])) {
            if (wp_verify_nonce($_POST['bc_edit_booking_nonce'], 'bc_edit_booking')) {
                // Fetch current state for intelligent change detection
                $old_booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_bookings WHERE id = %d", $booking_id));

                $data = array(
                    'booking_date' => sanitize_text_field($_POST['booking_date']),
                    'start_time' => sanitize_text_field($_POST['start_time']),
                    'end_time' => sanitize_text_field($_POST['end_time']),
                    'total_amount' => floatval($_POST['total_amount']),
                    'status' => sanitize_text_field($_POST['status']),
                    'staff_id' => isset($_POST['staff_id']) ? intval($_POST['staff_id']) : ($old_booking ? $old_booking->staff_id : 0)
                );

                $result = $wpdb->update($table_bookings, $data, array('id' => $booking_id));

                if ($result === false) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('Database Error: Could not update booking.', 'boocommerce') . ' ' . esc_html($wpdb->last_error) . '</p></div>';
                } else {
                    // Identify what exactly changed for custom notifications
                    $changes = array();
                    if ($old_booking) {
                        if ($old_booking->booking_date !== $data['booking_date'])
                            $changes['booking_date'] = $data['booking_date'];
                        if (substr($old_booking->start_time, 0, 5) !== substr($data['start_time'], 0, 5))
                            $changes['start_time'] = $data['start_time'];
                        if ($old_booking->staff_id != $data['staff_id'])
                            $changes['staff_id'] = $data['staff_id'];
                        if ($old_booking->status !== $data['status'])
                            $changes['status'] = $data['status'];
                    }

                    if (!empty($changes)) {
                        $this->admin->bc_notify_booking_update($booking_id, $changes);
                    }

                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Booking information updated successfully.', 'boocommerce') . '</p></div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Security Check Failed: Nonce verification unsuccessful. Please refresh and try again.', 'boocommerce') . '</p></div>';
            }
        }

        if ($action === 'edit' && $booking_id) {
            $booking = $wpdb->get_row($wpdb->prepare("
                SELECT b.*, c.first_name, c.last_name, c.email as customer_email, c.phone as customer_phone, st.name as staff_name
                FROM $table_bookings b
                LEFT JOIN {$wpdb->prefix}bc_customers c ON b.customer_id = c.id
                LEFT JOIN {$wpdb->prefix}bc_staff st ON b.staff_id = st.id
                WHERE b.id = %d
            ", $booking_id));

            // Fetch service names manually for multi-service support
            $service_names = __('Unknown Service', 'boocommerce');
            if (!empty($booking->service_id)) {
                $ids = array_map('intval', explode(',', $booking->service_id));
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $services = $wpdb->get_results($wpdb->prepare("SELECT name FROM {$wpdb->prefix}bc_services WHERE id IN ($placeholders)", $ids));
                if ($services) {
                    $names = array_map(function ($s) {
                        return $s->name; }, $services);
                    $service_names = implode(', ', $names);
                }
            }
            $payment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bc_payments WHERE booking_id = %d", $booking_id));

            if ($booking) {
                ?>
                <div class="wrap bc-admin-wrap bc-booking-edit-wrapper">
                    <style>
                        /* Edit Booking Responsive Layouts */
                        .bc-booking-edit-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
                        .bc-booking-edit-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
                        .bc-booking-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                        .bc-booking-time-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
                        
                        @media (max-width: 1024px) {
                            .bc-booking-edit-grid { grid-template-columns: 1fr; }
                        }
                        
                        @media (max-width: 768px) {
                            .bc-booking-edit-header { flex-direction: column; align-items: flex-start; gap: 15px; }
                            .bc-booking-info-grid, .bc-booking-time-grid { grid-template-columns: 1fr; }
                            .bc-booking-edit-wrapper .bc-btn-primary { width: 100%; text-align: center; display: block; margin-left: 0 !important; }
                        }
                    </style>
                    <div class="bc-booking-edit-header">
                        <h1 style="margin:0; font-size:24px; color:#fff;"><?php _e('Manage Booking', 'boocommerce'); ?>
                            #<?php echo esc_html(str_pad($booking->id, 5, '0', STR_PAD_LEFT)); ?></h1>
                        <a href="?page=bc_main&tab=bookings" class="bc-btn-primary" style="background:var(--bc-border);"><?php _e('Back to Bookings', 'boocommerce'); ?></a>
                    </div>

                    <form method="post" action="">
                        <?php wp_nonce_field('bc_edit_booking', 'bc_edit_booking_nonce'); ?>
                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                        <input type="hidden" name="bc_action" value="edit">
                        <input type="hidden" name="tab" value="bookings">

                        <div class="bc-booking-edit-grid">

                            <!-- Left Column: Core Booking Details -->
                            <div style="display:flex; flex-direction:column; gap:25px;">

                                <!-- Customer Identity Card -->
                                <div
                                    style="background:var(--bc-panel-dark); padding:25px; border-radius:12px; border:1px solid var(--bc-border); border-top:4px solid var(--bc-primary);">
                                    <h3
                                        style="margin:0 0 20px 0; color:var(--bc-primary); display:flex; align-items:center; gap:10px;">
                                        <span class="dashicons dashicons-admin-users"></span> <?php _e('Customer Information', 'boocommerce'); ?>
                                    </h3>
                                    <div class="bc-booking-info-grid">
                                        <div>
                                            <label
                                                style="display:block; margin-bottom:6px; color:var(--bc-text-muted); font-size:13px;"><?php _e('Full Name', 'boocommerce'); ?></label>
                                            <div
                                                style="padding:12px; background:#0f172a; border:1px solid var(--bc-border); border-radius:8px; color:#fff; font-weight:600;">
                                                <?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <label
                                                style="display:block; margin-bottom:6px; color:var(--bc-text-muted); font-size:13px;"><?php _e('Contact Email', 'boocommerce'); ?></label>
                                            <div
                                                style="padding:12px; background:#0f172a; border:1px solid var(--bc-border); border-radius:8px; color:#fff;">
                                                <?php echo esc_html($booking->customer_email); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <label
                                                style="display:block; margin-bottom:6px; color:var(--bc-text-muted); font-size:13px;"><?php _e('Phone Number', 'boocommerce'); ?></label>
                                            <div
                                                style="padding:12px; background:#0f172a; border:1px solid var(--bc-border); border-radius:8px; color:#fff;">
                                                <?php echo esc_html($booking->customer_phone ?: __('No phone provided', 'boocommerce')); ?>
                                            </div>
                                        </div>
                                        <div>
                                            <label
                                                style="display:block; margin-bottom:6px; color:var(--bc-text-muted); font-size:13px;"><?php _e('Assigned Professional', 'boocommerce'); ?></label>
                                            <select name="staff_id"
                                                style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--bc-border); border-radius:8px; padding:12px; font-weight:700;">
                                                <?php
                                                $all_staff = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}bc_staff ORDER BY name ASC");
                                                foreach ($all_staff as $st): ?>
                                                    <option value="<?php echo $st->id; ?>" <?php selected($booking->staff_id, $st->id); ?>>
                                                        <?php echo esc_html($st->name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Appointment Configuration -->
                                <div
                                    style="background:var(--bc-panel-dark); padding:25px; border-radius:12px; border:1px solid var(--bc-border); border-top:4px solid var(--bc-warning);">
                                    <h3
                                        style="margin:0 0 20px 0; color:var(--bc-warning); display:flex; align-items:center; gap:10px;">
                                        <span class="dashicons dashicons-calendar-alt"></span> <?php _e('Schedule & Service', 'boocommerce'); ?>
                                    </h3>

                                    <div style="margin-bottom:20px;">
                                        <label style="display:block; margin-bottom:8px; color:var(--bc-text-muted);"><?php _e('Selected Services', 'boocommerce'); ?></label>
                                        <div
                                            style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--bc-border); padding:12px; border-radius:8px; font-weight:600; opacity:0.8; min-height:45px;">
                                            <?php echo esc_html($service_names); ?>
                                        </div>
                                    </div>

                                    <div class="bc-booking-time-grid">
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--bc-text-muted);"><?php _e('Booking Date', 'boocommerce'); ?></label>
                                            <input name="booking_date" type="date"
                                                value="<?php echo esc_attr($booking->booking_date); ?>"
                                                style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:12px; border-radius:8px;"
                                                required>
                                        </div>
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--bc-text-muted);"><?php _e('Start Time', 'boocommerce'); ?></label>
                                            <input name="start_time" type="time" value="<?php echo esc_attr($booking->start_time); ?>"
                                                style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:12px; border-radius:8px;"
                                                required>
                                        </div>
                                    </div>

                                    <div class="bc-booking-time-grid" style="margin-bottom:0;">
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--bc-text-muted);"><?php _e('End Time', 'boocommerce'); ?></label>
                                            <input name="end_time" type="time" value="<?php echo esc_attr($booking->end_time); ?>"
                                                style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:12px; border-radius:8px;"
                                                required>
                                        </div>
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--bc-text-muted);"><?php _e('Total Duration (Auto)', 'boocommerce'); ?></label>
                                            <div
                                                style="padding:12px; background:rgba(255,255,255,0.03); border:1px dashed var(--bc-border); border-radius:8px; color:var(--bc-text-muted);">
                                                <?php _e('Calculated from start/end', 'boocommerce'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Status & Financials -->
                            <div style="display:flex; flex-direction:column; gap:25px;">

                                <!-- Status Card -->
                                <div
                                    style="background:var(--bc-panel-dark); padding:25px; border-radius:12px; border:1px solid var(--bc-border); border-top:4px solid #fff;">
                                    <h3 style="margin:0 0 20px 0; color:#fff; display:flex; align-items:center; gap:10px;">
                                        <span class="dashicons dashicons-marker"></span> <?php _e('Booking Status', 'boocommerce'); ?>
                                    </h3>
                                    <div style="margin-bottom:20px;">
                                        <select name="status"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:14px; border-radius:8px; font-weight:700; font-size:15px; border-left:4px solid <?php echo $booking->status === 'confirmed' ? 'var(--bc-success)' : ($booking->status === 'pending' ? 'var(--bc-warning)' : '#ef4444'); ?>;">
                                            <option value="pending" <?php selected($booking->status, 'pending'); ?>><?php _e('Pending Approval', 'boocommerce'); ?>
                                            </option>
                                            <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>><?php _e('Confirmed', 'boocommerce'); ?>
                                            </option>
                                            <option value="completed" <?php selected($booking->status, 'completed'); ?>><?php _e('Completed', 'boocommerce'); ?>
                                            </option>
                                            <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>><?php _e('Cancelled', 'boocommerce'); ?>
                                            </option>
                                        </select>
                                    </div>
                                    <p style="font-size:12px; color:var(--bc-text-muted); line-height:1.5;">
                                        <?php _e('Updating the status will automatically trigger a notification email to the customer.', 'boocommerce'); ?>
                                    </p>
                                </div>

                                <!-- Financial Insights Card -->
                                <div
                                    style="background:var(--bc-panel-dark); padding:25px; border-radius:12px; border:1px solid var(--bc-border); border-top:4px solid var(--bc-success);">
                                    <h3
                                        style="margin:0 0 20px 0; color:var(--bc-success); display:flex; align-items:center; gap:10px;">
                                        <span class="dashicons dashicons-money-alt"></span> <?php _e('Financial Details', 'boocommerce'); ?>
                                    </h3>
                                    <div style="margin-bottom:20px;">
                                        <label style="display:block; margin-bottom:8px; color:var(--bc-text-muted);"><?php _e('Amount Receivable', 'boocommerce'); ?></label>
                                        <div style="position:relative;">
                                            <span
                                                style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--bc-text-muted); font-weight:bold;"><?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?></span>
                                            <input name="total_amount" type="number" step="0.01"
                                                value="<?php echo esc_attr($booking->total_amount); ?>"
                                                style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:12px 12px 12px 30px; border-radius:8px; font-size:18px; font-weight:800;"
                                                required>
                                        </div>
                                    </div>

                                    <div
                                        style="background:rgba(16, 185, 129, 0.05); padding:15px; border-radius:8px; border:1px solid rgba(16, 185, 129, 0.1);">
                                        <div
                                            style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                            <span style="font-size:13px; color:var(--bc-text-muted);"><?php _e('Payment Strategy', 'boocommerce'); ?></span>
                                            <span
                                                style="font-size:11px; font-weight:800; text-transform:uppercase; color:var(--bc-success);"><?php _e('Secured', 'boocommerce'); ?></span>
                                        </div>
                                        <?php if ($payment): ?>
                                            <div style="border-top:1px solid rgba(16, 185, 129, 0.1); padding-top:10px; margin-top:5px;">
                                                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                                                    <span style="font-size:12px; color:var(--bc-text-muted);"><?php _e('Gateway', 'boocommerce'); ?></span>
                                                    <span
                                                        style="font-size:12px; color:#fff; font-weight:600;"><?php echo strtoupper(esc_html($payment->gateway)); ?></span>
                                                </div>
                                                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                                                    <span style="font-size:12px; color:var(--bc-text-muted);"><?php _e('Transaction ID', 'boocommerce'); ?></span>
                                                    <span
                                                        style="font-size:12px; color:#fff; font-family:monospace;"><?php echo esc_html($payment->transaction_id ?: 'N/A'); ?></span>
                                                </div>
                                                <div style="display:flex; justify-content:space-between;">
                                                    <span style="font-size:12px; color:var(--bc-text-muted);"><?php _e('Payment Status', 'boocommerce'); ?></span>
                                                    <span
                                                        style="font-size:11px; padding:2px 6px; border-radius:4px; background:<?php echo $payment->status === 'completed' ? 'var(--bc-success)' : '#f59e0b'; ?>; color:#fff; font-weight:bold;"><?php echo strtoupper(esc_html($payment->status)); ?></span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div
                                                style="border-top:1px solid rgba(16, 185, 129, 0.1); padding-top:10px; margin-top:5px; text-align:center; color:var(--bc-text-muted); font-size:11px; font-style:italic;">
                                                <?php _e('No payment transaction linked to this booking yet.', 'boocommerce'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Action Container -->
                                <div style="display:flex; flex-direction:column; gap:10px;">
                                    <button type="submit" class="bc-btn-primary"
                                        style="width:100%; padding:15px; font-size:16px; background:var(--bc-primary); border:none; box-shadow:0 4px 15px rgba(99, 102, 241, 0.3);">
                                        <?php _e('Update Booking Details', 'boocommerce'); ?>
                                    </button>
                                    <a href="?page=bc_main&tab=bookings"
                                        style="text-align:center; padding:10px; color:var(--bc-text-muted); text-decoration:none; font-size:14px;"><?php _e('Discard Changes', 'boocommerce'); ?></a>
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
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_staff = isset($_GET['filter_staff']) ? intval($_GET['filter_staff']) : 0;
        $filter_service = isset($_GET['filter_service']) ? intval($_GET['filter_service']) : 0;
        $filter_search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
        $filter_date_start = isset($_GET['filter_date_start']) ? sanitize_text_field($_GET['filter_date_start']) : '';
        $filter_date_end = isset($_GET['filter_date_end']) ? sanitize_text_field($_GET['filter_date_end']) : '';

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
                 st.name as staff_name,
                 req_staff.name as requested_staff_name
          FROM {$wpdb->prefix}bc_bookings b
          LEFT JOIN {$wpdb->prefix}bc_customers c ON b.customer_id = c.id
          LEFT JOIN {$wpdb->prefix}bc_staff st ON b.staff_id = st.id
          LEFT JOIN {$wpdb->prefix}bc_staff req_staff ON b.requested_staff_id = req_staff.id
          {$where_clause}
          ORDER BY b.booking_date DESC, b.start_time DESC";

        $query = apply_filters('bc_admin_bookings_query', $query);
        $bookings = $wpdb->get_results($query);
        $bookings = apply_filters('bc_admin_bookings_results', $bookings);

        $all_staff = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}bc_staff ORDER BY name ASC");
        $all_services = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}bc_services ORDER BY name ASC");

        $view = isset($_GET['view']) ? $_GET['view'] : 'list';
        $page_url = "?page=bc_main&tab=bookings";
        ?>
        <div class="wrap bc-admin-wrap bc-bookings-list-wrapper">
            <style>
                /* List View Responsive Layouts */
                .bc-bookings-list-header { display: flex; justify-content: space-between; align-items: center; }
                .bc-bookings-filter-form { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
                .bc-bookings-meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-top: 20px; margin-bottom: 20px; }
                
                @media (max-width: 1024px) {
                    .bc-bookings-meta-grid { grid-template-columns: repeat(2, 1fr); }
                }
                
                @media (max-width: 768px) {
                    .bc-bookings-list-header { flex-direction: column; align-items: flex-start; gap: 15px; }
                    .bc-bookings-list-header > div { width: 100%; display: flex; gap: 10px; }
                    .bc-bookings-list-wrapper .bc-btn-primary { width: 100%; text-align: center; display: block; margin-left: 0 !important; margin-bottom: 0; flex: 1; }
                    .bc-bookings-filter-form > div { width: 100% !important; min-width: 100%; }
                    .bc-bookings-filter-form { flex-direction: column; align-items: stretch; }
                    .bc-bookings-filter-form .bc-btn-primary { width: 100%; margin-bottom: 0; }
                    .bc-bookings-meta-grid { grid-template-columns: 1fr; }
                    .bc-bookings-table-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
                    .bc-modern-table { min-width: 800px; }
                }
            </style>
            <div class="bc-bookings-list-header">
                <h1 style="margin:0;"><?php _e('Manage Bookings', 'boocommerce'); ?></h1>
                <div>
                    <a href="<?php echo $page_url; ?>&view=list&filter_status=<?php echo esc_attr($filter_status); ?>"
                        class="bc-btn-primary"
                        style="background: <?php echo $view === 'list' ? 'var(--bc-primary)' : 'rgba(255,255,255,0.05)'; ?>;"><?php _e('List View', 'boocommerce'); ?></a>
                    <a href="<?php echo $page_url; ?>&view=calendar&filter_status=<?php echo esc_attr($filter_status); ?>"
                        class="bc-btn-primary"
                        style="margin-left:5px; background: <?php echo $view === 'calendar' ? 'var(--bc-primary)' : 'rgba(255,255,255,0.05)'; ?>;"><?php _e('Calendar View', 'boocommerce'); ?></a>
                </div>
            </div>

            <!-- Advanced Filter Toolbar -->
            <div
                style="background: var(--bc-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--bc-border); margin-top:20px;">
                <form method="get" action="" class="bc-bookings-filter-form">
                    <input type="hidden" name="page" value="bc_main">
                    <input type="hidden" name="tab" value="bookings">
                    <input type="hidden" name="view" value="<?php echo esc_attr($view); ?>">
                    <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">

                    <div style="flex:1; min-width:200px;">
                        <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted); font-size:12px;"><?php _e('Search Bookings', 'boocommerce'); ?></label>
                        <input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>"
                            placeholder="<?php esc_attr_e('Name, Email, or ID...', 'boocommerce'); ?>"
                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:8px; border-radius:6px;">
                    </div>

                    <div style="width:180px;">
                        <label
                            style="display:block; margin-bottom:5px; color:var(--bc-text-muted); font-size:12px;"><?php _e('Professional', 'boocommerce'); ?></label>
                        <select name="filter_staff"
                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:8px; border-radius:6px;">
                            <option value="0"><?php _e('All Professionals', 'boocommerce'); ?></option>
                            <?php foreach ($all_staff as $st): ?>
                                <option value="<?php echo $st->id; ?>" <?php selected($filter_staff, $st->id); ?>>
                                    <?php echo esc_html($st->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="width:180px;">
                        <label
                            style="display:block; margin-bottom:5px; color:var(--bc-text-muted); font-size:12px;"><?php _e('Service', 'boocommerce'); ?></label>
                        <select name="filter_service"
                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:8px; border-radius:6px;">
                            <option value="0"><?php _e('All Services', 'boocommerce'); ?></option>
                            <?php foreach ($all_services as $srv): ?>
                                <option value="<?php echo $srv->id; ?>" <?php selected($filter_service, $srv->id); ?>>
                                    <?php echo esc_html($srv->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="width:150px;">
                        <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted); font-size:12px;"><?php _e('Start Date', 'boocommerce'); ?></label>
                        <input type="date" name="filter_date_start" value="<?php echo esc_attr($filter_date_start); ?>"
                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:8px; border-radius:6px;">
                    </div>

                    <div style="width:150px;">
                        <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted); font-size:12px;"><?php _e('End Date', 'boocommerce'); ?></label>
                        <input type="date" name="filter_date_end" value="<?php echo esc_attr($filter_date_end); ?>"
                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:8px; border-radius:6px;">
                    </div>

                    <div style="display:flex; gap:5px;">
                        <button type="submit" class="bc-btn-primary"><?php _e('Apply', 'boocommerce'); ?></button>
                        <a href="?page=bc_main&tab=bookings&view=<?php echo esc_attr($view); ?>" class="bc-btn-primary"
                            style="background:var(--bc-border);"><?php _e('Clear', 'boocommerce'); ?></a>
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
                    border: 1px solid var(--bc-border);
                    border-radius: 12px;
                    background: var(--bc-panel-dark);
                    padding: 20px;
                    transition: transform 0.2s;
                }

                .booking-filter-card:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
                }

                .card-active {
                    background: rgba(59, 130, 246, 0.1) !important;
                    border-left: 4px solid var(--bc-primary) !important;
                    border-color: var(--bc-primary) !important;
                }
            </style>
            <div class="bc-bookings-meta-grid">
                <a href="<?php echo $page_url; ?>&view=<?php echo esc_attr($view); ?>&filter_status=all"
                    class="booking-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--bc-text-muted);"><?php _e('Total Bookings', 'boocommerce'); ?></h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                        <?php echo intval($total_bookings); ?>
                    </p>
                </a>
                <a href="<?php echo $page_url; ?>&view=<?php echo esc_attr($view); ?>&filter_status=pending"
                    class="booking-filter-card <?php echo $filter_status === 'pending' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:#f59e0b;"><?php _e('Pending Approvals', 'boocommerce'); ?></h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                        <?php echo intval($pending_count); ?>
                    </p>
                </a>
                <a href="<?php echo $page_url; ?>&view=<?php echo esc_attr($view); ?>&filter_status=confirmed"
                    class="booking-filter-card <?php echo $filter_status === 'confirmed' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--bc-success);"><?php _e('Confirmed / Completed', 'boocommerce'); ?></h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                        <?php echo intval($confirmed_count); ?>
                    </p>
                </a>
                <a href="<?php echo $page_url; ?>&view=<?php echo esc_attr($view); ?>&filter_status=pending_requests"
                    class="booking-filter-card <?php echo $filter_status === 'pending_requests' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:#ef4444;"><?php _e('Client Requests', 'boocommerce'); ?></h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                        <?php echo intval($client_requests_count); ?>
                    </p>
                </a>
            </div>

            <?php if ($view === 'calendar'): ?>
                <!-- Calendar View powered by FullCalendar -->
                <div id="bc-calendar"
                    style="background: var(--bc-panel-dark); padding: 20px; border-radius: 12px; margin-top:20px; border: 1px solid var(--bc-border); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                </div>
                <script>
                    (function () {
                        var initCalendar = function () {
                            var calendarEl = document.getElementById('bc-calendar');
                            if (!calendarEl || calendarEl.classList.contains('fc')) return;

                            var events = [
                                <?php foreach ($bookings as $b): ?>
                                                                {
                                        title: '<?php echo esc_js($b->first_name . " - " . $b->service_name); ?>',
                                        start: '<?php echo esc_js($b->booking_date); ?>T<?php echo esc_js($b->start_time); ?>',
                                        end: '<?php echo esc_js($b->booking_date); ?>T<?php echo esc_js($b->end_time); ?>',
                                        color: '<?php echo $b->status === 'confirmed' ? '#10b981' : ($b->status === 'pending' ? '#f59e0b' : '#3b82f6'); ?>',
                                        url: '<?php echo "?page=bc_main&tab=bookings&action=edit&id=" . $b->id; ?>',
                                        extendedProps: {
                                            customer: '<?php echo esc_js($b->first_name . " " . $b->last_name); ?>',
                                            email: '<?php echo esc_js($b->customer_email); ?>',
                                            phone: '<?php echo esc_js($b->customer_phone); ?>',
                                            amount: '<?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?><?php echo esc_js($b->total_amount); ?>',
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
                        jQuery(document).off('bc-tab-loaded.bc_bookings').on('bc-tab-loaded.bc_bookings', function (e, tab) {
                            if (tab === 'bookings') initCalendar();
                        });
                    })();
                </script>
                <style>
                    /* FullCalendar overrides for Dark Mode */
                    .fc-theme-standard td,
                    .fc-theme-standard th {
                        border-color: var(--bc-border);
                        color: #fff;
                    }

                    .fc-col-header-cell {
                        background: rgba(0, 0, 0, 0.2) !important;
                        padding: 10px 0;
                    }

                    .fc-timegrid-slot-label {
                        color: var(--bc-text-muted);
                        font-size: 12px;
                    }

                    .fc-button-primary {
                        background-color: var(--bc-primary) !important;
                        border-color: var(--bc-primary) !important;
                        text-transform: capitalize;
                        border-radius: 6px !important;
                    }

                    .fc .fc-toolbar-title {
                        font-weight: 600;
                        font-family: 'Inter', sans-serif;
                        font-size: 20px;
                        color: var(--bc-text-main);
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
                <div class="bc-bookings-table-wrapper">
                    <table class="bc-modern-table">
                    <thead>
                        <tr>
                            <th><?php _e('Booking REF', 'boocommerce'); ?></th>
                            <th><?php _e('Customer Info', 'boocommerce'); ?></th>
                            <th><?php _e('Service & Staff', 'boocommerce'); ?></th>
                            <th><?php _e('Date & Time', 'boocommerce'); ?></th>
                            <th><?php _e('Amount', 'boocommerce'); ?></th>
                            <th><?php _e('Status', 'boocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($bookings)): ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr class="bc-clickable-row" data-href="?page=bc_main&tab=bookings&action=edit&id=<?php echo $b->id; ?>">
                                    <td><strong
                                            style="color:var(--bc-primary);">#<?php echo esc_html(str_pad($b->id, 5, '0', STR_PAD_LEFT)); ?></strong>
                                    </td>
                                    <td>
                                        <div class="bc-customer-info">
                                            <span
                                                class="bc-customer-name"><?php echo esc_html($b->first_name . ' ' . $b->last_name); ?></span>
                                            <span class="bc-customer-meta"><?php echo esc_html($b->customer_email); ?> |
                                                <?php echo esc_html($b->customer_phone); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="bc-customer-info">
                                            <span class="bc-customer-name">
                                                <?php
                                                if (!empty($b->service_id)) {
                                                    $ids = array_map('intval', explode(',', $b->service_id));
                                                    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                                                    $services = $wpdb->get_results($wpdb->prepare("SELECT name FROM {$wpdb->prefix}bc_services WHERE id IN ($placeholders)", $ids));
                                                    if ($services) {
                                                        $names = array_map(function ($s) {
                                                            return $s->name; }, $services);
                                                        echo esc_html(implode(', ', $names));
                                                    } else {
                                                        echo __('Unknown Service', 'boocommerce');
                                                    }
                                                } else {
                                                    echo __('Unknown Service', 'boocommerce');
                                                }
                                                ?>
                                            </span>
                                            <span class="bc-customer-meta">
                                                <?php if ($b->request_type === 'reschedule' && !empty($b->requested_staff_id)): ?>
                                                    <span
                                                        style="text-decoration:line-through; color:var(--bc-text-muted);"><?php echo esc_html($b->staff_name); ?></span>
                                                    <span style="color:var(--bc-warning); font-weight:bold;">➔
                                                        <?php echo esc_html($b->requested_staff_name); ?></span>
                                                <?php else: ?>
                                                    <?php _e('with', 'boocommerce'); ?> <?php echo esc_html($b->staff_name); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="bc-customer-info">
                                            <span class="bc-customer-name">
                                                <?php if ($b->request_type === 'reschedule' && !empty($b->requested_date)): ?>
                                                    <span
                                                        style="text-decoration:line-through; color:var(--bc-text-muted);"><?php echo esc_html(date('M d, Y', strtotime($b->booking_date))); ?></span>
                                                    <span style="color:var(--bc-warning); font-weight:bold;">➔
                                                        <?php echo esc_html(date('M d, Y', strtotime($b->requested_date))); ?></span>
                                                <?php else: ?>
                                                    <?php echo esc_html(date('M d, Y', strtotime($b->booking_date))); ?>
                                                <?php endif; ?>
                                            </span>
                                            <span class="bc-customer-meta">
                                                <?php if ($b->request_type === 'reschedule' && !empty($b->requested_time)): ?>
                                                    <span
                                                        style="text-decoration:line-through; color:var(--bc-text-muted);"><?php echo esc_html(date('g:i A', strtotime($b->start_time))); ?></span>
                                                    <span style="color:var(--bc-warning); font-weight:bold;">➔
                                                        <?php echo esc_html(date('g:i A', strtotime($b->requested_time))); ?></span>
                                                <?php else: ?>
                                                    <?php echo esc_html(date('g:i A', strtotime($b->start_time))); ?> -
                                                    <?php echo esc_html(date('g:i A', strtotime($b->end_time))); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td><strong><?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?><?php echo esc_html($b->total_amount); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($b->status === 'pending' && !empty($b->request_type)): ?>
                                            <span
                                                style="background:rgba(245, 158, 11, 0.15); color:var(--bc-warning); padding:4px 10px; border-radius:12px; font-size:12px; font-weight:bold; white-space:nowrap; display:block; text-align:center; margin-bottom:5px;">
                                                <?php echo esc_html(ucfirst($b->request_type)); ?> <?php _e('Request', 'boocommerce'); ?>
                                            </span>
                                            <div style="display:flex; gap:5px;">
                                                <a href="?page=bc_main&tab=bookings&action=request_action&decision=approve&id=<?php echo $b->id; ?>"
                                                    class="button"
                                                    style="flex:1; text-align:center; background:var(--bc-success); color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:bold; padding:4px 0; text-decoration:none;"><?php _e('Accept', 'boocommerce'); ?></a>
                                                <a href="?page=bc_main&tab=bookings&action=request_action&decision=reject&id=<?php echo $b->id; ?>"
                                                    class="button"
                                                    style="flex:1; text-align:center; background:#ef4444; color:#fff; border:none; border-radius:6px; font-size:11px; font-weight:bold; padding:4px 0; text-decoration:none;"><?php _e('Reject', 'boocommerce'); ?></a>
                                            </div>
                                        <?php else: ?>
                                            <span
                                                class="bc-status bc-status-<?php echo esc_attr($b->status); ?>"><?php echo esc_html(ucfirst($b->status)); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 40px; color: var(--bc-text-muted);"><?php _e('No bookings found.', 'boocommerce'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
