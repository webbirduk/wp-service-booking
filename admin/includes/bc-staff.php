<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bc_Staff {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        global $wpdb;
        $table_staff = $wpdb->prefix . 'bc_staff';

        // Auto-patch schema for advanced fields
        $wpdb->hide_errors();
        $wpdb->query("ALTER TABLE {$table_staff} ADD COLUMN schedule_config text AFTER description");
        $wpdb->query("ALTER TABLE {$table_staff} ADD COLUMN holidays text AFTER schedule_config");
        $wpdb->query("ALTER TABLE {$table_staff} ADD COLUMN image_url text AFTER holidays");
        $wpdb->query("ALTER TABLE {$table_staff} ADD COLUMN qualification text AFTER image_url");
        $wpdb->query("ALTER TABLE {$table_staff} ADD COLUMN address text AFTER qualification");
        $wpdb->show_errors();

        $action = isset($_REQUEST['bc_action']) ? sanitize_text_field($_REQUEST['bc_action']) : (isset($_GET['action']) ? $_GET['action'] : 'list');
        $staff_id = isset($_REQUEST['staff_id']) ? intval($_REQUEST['staff_id']) : (isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0);

        // Delete Handler
        if ($action === 'delete' && $staff_id && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_staff_' . $staff_id)) {
            $wpdb->delete($table_staff, array('id' => $staff_id));
            echo '<div class="notice bc-custom-notice notice-success is-dismissible"><p>' . __('Staff record purged from the system.', 'boocommerce') . '</p></div>';
            $action = 'list';
        }



        // Form Submit Handler
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bc_staff_nonce']) && wp_verify_nonce($_POST['bc_staff_nonce'], 'bc_staff_save')) {
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
                if ($wpdb->last_error) echo '<div class="notice bc-custom-notice notice-error"><p>' . esc_html($wpdb->last_error) . '</p></div>';
                else echo '<div class="notice bc-custom-notice notice-success is-dismissible"><p>' . __('Staff profile updated securely.', 'boocommerce') . '</p></div>';
            } else {
                $wpdb->insert($table_staff, $data);
                if ($wpdb->last_error) echo '<div class="notice bc-custom-notice notice-error"><p>' . esc_html($wpdb->last_error) . '</p></div>';
                else {
                    $staff_id = $wpdb->insert_id;
                    echo '<div class="notice bc-custom-notice notice-success is-dismissible"><p>' . __('New professional added to the roster.', 'boocommerce') . '</p></div>';
                }
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
                $perf_bookings = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}bc_bookings WHERE staff_id = %d AND (status = 'confirmed' OR status = 'completed')", $staff_id));
                $perf_revenue = $wpdb->get_var($wpdb->prepare("SELECT SUM(total_amount) FROM {$wpdb->prefix}bc_bookings WHERE staff_id = %d AND (status = 'confirmed' OR status = 'completed')", $staff_id)) ?: 0;
            }
            $days = array(
                'mon' => __('Monday', 'boocommerce'), 
                'tue' => __('Tuesday', 'boocommerce'), 
                'wed' => __('Wednesday', 'boocommerce'), 
                'thu' => __('Thursday', 'boocommerce'), 
                'fri' => __('Friday', 'boocommerce'), 
                'sat' => __('Saturday', 'boocommerce'), 
                'sun' => __('Sunday', 'boocommerce')
            );
            ?>
            <div class="wrap bc-admin-wrap bc-staff-edit-wrapper">
                <style>
                    /* Staff Edit Responsive Layouts */
                    .bc-staff-edit-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
                    .bc-staff-edit-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
                    .bc-staff-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                    .bc-schedule-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }

                    @media (max-width: 1024px) {
                        .bc-staff-edit-grid { grid-template-columns: 1fr; }
                    }

                    @media (max-width: 768px) {
                        .bc-staff-edit-header { flex-direction: column; align-items: flex-start; gap: 15px; }
                        .bc-staff-edit-header > div { width: 100%; }
                        .bc-staff-edit-wrapper .bc-btn-primary { width: 100%; text-align: center; display: block; margin-left: 0 !important; }
                        .bc-staff-info-grid { grid-template-columns: 1fr; }
                        .bc-schedule-row { flex-direction: column; align-items: flex-start; gap: 10px; }
                        .bc-schedule-row > label { width: 100% !important; }
                        .bc-schedule-row > div { width: 100%; justify-content: flex-start; }
                    }
                </style>
                <div class="bc-staff-edit-header">
                    <h1 style="margin:0; font-size:24px; color:#fff;"><?php _e('Manage Staff Profile', 'boocommerce'); ?></h1>
                    <a href="?page=bc_main&tab=staff" class="bc-btn-primary" style="background:var(--bc-border);"><?php _e('Back to Roster', 'boocommerce'); ?></a>
                </div>
                <hr class="wp-header-end" style="margin-bottom:20px;">

                <form method="post" action="">
                    <?php wp_nonce_field('bc_staff_save', 'bc_staff_nonce'); ?>
                    <input type="hidden" name="staff_id" value="<?php echo $staff_id; ?>">
                    <input type="hidden" name="bc_action" value="<?php echo $action; ?>">
                    <input type="hidden" name="tab" value="staff">

                    <div class="bc-staff-edit-grid">
                        <div>
                            <!-- Core Identity -->
                            <div
                                style="background:var(--bc-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--bc-border); border-top:4px solid var(--bc-primary); margin-bottom:20px;">
                                <h3 style="margin-top:0; color:var(--bc-primary); display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-admin-users"></span> <?php _e('Personal Information', 'boocommerce'); ?>
                                </h3>
                                <div class="bc-staff-info-grid" style="margin-bottom:15px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Full Name', 'boocommerce'); ?></label>
                                        <input name="staff_name" type="text" value="<?php echo $s ? esc_attr($s->name) : ''; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:10px; border-radius:6px;"
                                            required>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Profession / Qualification', 'boocommerce'); ?></label>
                                        <input name="staff_qualification" type="text"
                                            placeholder="<?php esc_attr_e('e.g. Senior Hairstylist, Master Technician', 'boocommerce'); ?>"
                                            value="<?php echo $s && isset($s->qualification) ? esc_attr($s->qualification) : ''; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:10px; border-radius:6px;">
                                    </div>
                                </div>
                                <div class="bc-staff-info-grid" style="margin-bottom:15px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Email Address', 'boocommerce'); ?></label>
                                        <input name="staff_email" type="email" value="<?php echo $s ? esc_attr($s->email) : ''; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:10px; border-radius:6px;"
                                            required>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Phone Number', 'boocommerce'); ?></label>
                                        <input name="staff_phone" type="text" value="<?php echo $s ? esc_attr($s->phone) : ''; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:10px; border-radius:6px;">
                                    </div>
                                </div>
                                <div style="margin-bottom:15px;">
                                    <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Physical Address', 'boocommerce'); ?></label>
                                    <textarea name="staff_address" rows="2"
                                        style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:10px; border-radius:6px;"><?php echo $s && isset($s->address) ? esc_textarea($s->address) : ''; ?></textarea>
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Public Biography / Description', 'boocommerce'); ?></label>
                                    <textarea name="description" rows="3"
                                        style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:10px; border-radius:6px;"><?php echo $s ? esc_textarea($s->description) : ''; ?></textarea>
                                </div>
                            </div>

                            <!-- Schedule Settings -->
                            <div
                                style="background:var(--bc-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--bc-border); border-top:4px solid var(--bc-warning);">
                                <h3 style="margin-top:0; color:var(--bc-warning); display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-calendar-alt"></span> <?php _e('Weekly Schedule', 'boocommerce'); ?>
                                </h3>
                                <p style="color:var(--bc-text-muted); font-size:13px; margin-bottom:15px;"><?php _e('Configure the working hours for this provider. If not working on a day, leave times blank or uncheck.', 'boocommerce'); ?></p>

                                <?php foreach ($days as $key => $label):
                                    $is_working = isset($schedule[$key]['active']) && $schedule[$key]['active'] == '1';
                                    $start = isset($schedule[$key]['start']) ? $schedule[$key]['start'] : '09:00';
                                    $end = isset($schedule[$key]['end']) ? $schedule[$key]['end'] : '17:00';
                                    ?>
                                    <div class="bc-schedule-row">
                                        <label style="display:flex; align-items:center; gap:10px; width:150px; cursor:pointer;">
                                            <input type="checkbox" name="schedule[<?php echo $key; ?>][active]" value="1" <?php checked($is_working); ?>
                                                style="background:#0f172a; border:1px solid var(--bc-primary);">
                                            <strong
                                                style="color:<?php echo $is_working ? 'white' : 'var(--bc-text-muted)'; ?>"><?php echo $label; ?></strong>
                                        </label>
                                        <div
                                            style="display:flex; align-items:center; gap:10px; opacity:<?php echo $is_working ? '1' : '0.4'; ?>;">
                                            <input type="time" name="schedule[<?php echo $key; ?>][start]"
                                                value="<?php echo esc_attr($start); ?>"
                                                style="background:#0f172a; color:white; border:1px solid var(--bc-border); padding:4px 8px; border-radius:4px;">
                                            <span style="color:var(--bc-text-muted);"><?php _e('to', 'boocommerce'); ?></span>
                                            <input type="time" name="schedule[<?php echo $key; ?>][end]"
                                                value="<?php echo esc_attr($end); ?>"
                                                style="background:#0f172a; color:white; border:1px solid var(--bc-border); padding:4px 8px; border-radius:4px;">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <!-- Performance Metrics Card -->
                            <?php if ($s): ?>
                                <div
                                    style="background:var(--bc-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--bc-border); border-top:4px solid var(--bc-success); margin-bottom:20px;">
                                    <h3 style="margin-top:0; color:var(--bc-success); font-size:16px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-chart-line"></span> <?php _e('Performance Insights', 'boocommerce'); ?>
                                </h3>
                                    <div class="bc-staff-info-grid">
                                        <div style="background:rgba(16, 185, 129, 0.05); padding:15px; border-radius:8px; border:1px solid rgba(16, 185, 129, 0.1);">
                                            <span style="display:block; font-size:12px; color:var(--bc-text-muted); margin-bottom:5px;"><?php _e('Total Revenue', 'boocommerce'); ?></span>
                                            <strong style="font-size:20px; color:#fff;"><?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?><?php echo number_format($perf_revenue, 2); ?></strong>
                                        </div>
                                        <div style="background:rgba(59, 130, 246, 0.05); padding:15px; border-radius:8px; border:1px solid rgba(59, 130, 246, 0.1);">
                                            <span style="display:block; font-size:12px; color:var(--bc-text-muted); margin-bottom:5px;"><?php _e('Sessions', 'boocommerce'); ?></span>
                                            <strong style="font-size:20px; color:#fff;"><?php echo intval($perf_bookings); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Side Panel - Image/Status -->
                            <div
                                style="background:var(--bc-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--bc-border); margin-bottom:20px;">
                                <h3 style="margin-top:0; color:#fff; font-size:16px;"><?php _e('Profile Image', 'boocommerce'); ?></h3>
                                <div style="display:flex; flex-direction:column; gap:10px; align-items:center; margin-bottom:15px;">
                                    <div id="bc-staff-preview"
                                        style="width:140px; height:140px; border-radius:50%; border:4px solid #fff; box-shadow:0 10px 25px rgba(0,0,0,0.5); background:#fff <?php echo $s && isset($s->image_url) && $s->image_url ? 'url(' . esc_url($s->image_url) . ') center/cover' : ''; ?>;">
                                    </div>
                                    <input type="hidden" name="staff_image_url" id="staff_image_url"
                                        value="<?php echo $s && isset($s->image_url) ? esc_url($s->image_url) : ''; ?>">
                                    <button type="button" class="bc-btn-primary bc-select-image" data-target="#staff_image_url"
                                        data-preview="#bc-staff-preview"
                                        style="background:var(--bc-border); color:white; width:100%;"><?php _e('Select Avatar', 'boocommerce'); ?></button>
                                </div>
                                <hr style="border:0; border-top:1px solid var(--bc-border); margin:15px 0;">
                                <div style="margin-bottom:15px;">
                                    <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('System Status', 'boocommerce'); ?></label>
                                    <select name="status"
                                        style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:10px; border-radius:6px;">
                                        <option value="active" <?php selected($s ? $s->status : 'active', 'active'); ?>><?php _e('Active (Accepting Bookings)', 'boocommerce'); ?></option>
                                        <option value="inactive" <?php selected($s ? $s->status : '', 'inactive'); ?>><?php _e('Inactive (Hidden)', 'boocommerce'); ?></option>
                                    </select>
                                </div>
                                <button type="submit" class="bc-btn-primary"
                                    style="width:100%; padding:12px; font-size:16px; background:var(--bc-success);"><?php _e('Save Staff Configuration', 'boocommerce'); ?></button>
                            </div>

                            <div
                                style="background:var(--bc-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--bc-border); border-top:4px solid #ef4444;">
                                <h3 style="margin-top:0; color:#ef4444; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-palmtree"></span> <?php _e('Time off & Holidays', 'boocommerce'); ?>
                                </h3>
                                <p style="color:var(--bc-text-muted); font-size:13px;"><?php _e('Enter exact dates where this staff member is unavailable. Use YYYY-MM-DD format on a new line for each date.', 'boocommerce'); ?></p>
                                <textarea name="holidays" rows="5"
                                    style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); padding:10px; border-radius:6px;"
                                    placeholder="2026-12-25&#10;2026-11-28"><?php echo $s ? esc_textarea($s->holidays) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Personalized Booking Calendar -->
                    <?php if ($s): ?>
                        <div style="margin-top:30px;">
                            <div style="background:var(--bc-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--bc-border); border-top:4px solid var(--bc-primary);">
                                <h3 style="margin:0 0 20px 0; color:#fff; display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-calendar"></span> <?php echo esc_html($s->name); ?><?php _e('\'s Booking Schedule', 'boocommerce'); ?>
                                </h3>
                                <div id="bc-staff-calendar" style="min-height:500px;"></div>
                            </div>

                            <script>
                                (function() {
                                    var initStaffCalendar = function() {
                                        var calendarEl = document.getElementById('bc-staff-calendar');
                                        if (!calendarEl || calendarEl.classList.contains('fc')) return;

                                        <?php
                                        $staff_bookings = $wpdb->get_results($wpdb->prepare("
                                            SELECT b.*, c.first_name, c.last_name, s.name as service_name
                                            FROM {$wpdb->prefix}bc_bookings b
                                            LEFT JOIN {$wpdb->prefix}bc_customers c ON b.customer_id = c.id
                                            LEFT JOIN {$wpdb->prefix}bc_services s ON b.service_id = s.id
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
                                                url: '<?php echo "?page=bc_main&tab=bookings&action=edit&id=" . $sb->id; ?>'
                                            },
                                            <?php endforeach; ?>
                                        ];

                                        var calendar = new FullCalendar.Calendar(calendarEl, {
                                            initialView: 'timeGridWeek',
                                            events: events,
                                            slotMinTime: '07:00:00',
                                            slotMaxTime: '21:00:00',
                                            allDaySlot: false,
                                            height: 'auto'
                                        });
                                        calendar.render();
                                    };

                                    initStaffCalendar();
                                    jQuery(document).on('bc-tab-loaded', function(e, tab) {
                                        if (tab === 'staff') initStaffCalendar();
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
                LEFT JOIN {$wpdb->prefix}bc_bookings b ON s.id = b.staff_id AND (b.status = 'confirmed' OR b.status = 'completed')
                {$where_clause}
                GROUP BY s.id
                ORDER BY total_revenue DESC, s.created_at DESC
            ");
            $total_staff = $wpdb->get_var("SELECT COUNT(*) FROM {$table_staff}");
            $active_staff = $wpdb->get_var("SELECT COUNT(*) FROM {$table_staff} WHERE status='active'");
            $inactive_staff = $wpdb->get_var("SELECT COUNT(*) FROM {$table_staff} WHERE status='inactive'");

            $page_url = "?page=bc_main&tab=staff";
            ?>
            <div class="wrap bc-admin-wrap bc-staff-list-wrapper">
                <style>
                    /* Staff List Responsive Layouts */
                    .bc-staff-list-header { display: flex; justify-content: space-between; align-items: center; }
                    .bc-staff-meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; margin-bottom: 20px; }

                    @media (max-width: 1024px) {
                        .bc-staff-meta-grid { grid-template-columns: repeat(2, 1fr); }
                    }

                    @media (max-width: 768px) {
                        .bc-staff-list-header { flex-direction: column; align-items: flex-start; gap: 15px; }
                        .bc-staff-list-header > div { width: 100%; }
                        .bc-staff-list-wrapper .bc-btn-primary { width: 100%; text-align: center; display: block; margin-left: 0 !important; }
                        .bc-staff-meta-grid { grid-template-columns: 1fr; }
                        .bc-staff-table-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
                        .bc-modern-table { min-width: 800px; }
                    }
                </style>
                <div class="bc-staff-list-header">
                    <h1 style="margin:0;"><?php _e('Staff Roster', 'boocommerce'); ?></h1>
                    <div>

                        <a href="?page=bc_main&tab=staff&action=add" class="bc-btn-primary"><?php _e('+ Onboard Staff', 'boocommerce'); ?></a>
                    </div>
                </div>

                <style>
                    .staff-filter-card {
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

                    .staff-filter-card:hover {
                        transform: translateY(-3px);
                        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
                    }

                    .card-active {
                        background: rgba(59, 130, 246, 0.1) !important;
                        border-left: 4px solid var(--bc-primary) !important;
                        border-color: var(--bc-primary) !important;
                    }
                </style>
                <div class="bc-staff-meta-grid">
                    <a href="<?php echo $page_url; ?>&filter_status=all"
                        class="staff-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--bc-text-muted);"><?php _e('Total Staff', 'boocommerce'); ?></h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                            <?php echo intval($total_staff); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=active"
                        class="staff-filter-card <?php echo $filter_status === 'active' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--bc-success);"><?php _e('Active Providers', 'boocommerce'); ?></h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                            <?php echo intval($active_staff); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=inactive"
                        class="staff-filter-card <?php echo $filter_status === 'inactive' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--bc-warning);"><?php _e('Inactive / On Leave', 'boocommerce'); ?></h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                            <?php echo intval($inactive_staff); ?></p>
                    </a>
                </div>

                <div class="bc-staff-table-wrapper">
                    <table class="bc-modern-table" style="margin:0; width:100%;">
                        <thead>
                            <tr>
                                <th><?php _e('Name', 'boocommerce'); ?></th>
                                <th><?php _e('Contact details', 'boocommerce'); ?></th>
                                <th><?php _e('Status', 'boocommerce'); ?></th>
                                <th style="text-align:center;"><?php _e('Performance Index', 'boocommerce'); ?></th>
                                <th style="text-align:right;"><?php _e('Actions', 'boocommerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($staff)):
                                foreach ($staff as $s): ?>
                                    <tr class="bc-clickable-row" data-href="?page=bc_main&tab=staff&action=edit&id=<?php echo $s->id; ?>">
                                        <td>
                                            <div style="display:flex; align-items:center; gap:15px;">
                                                <?php if (!empty($s->image_url)): ?>
                                                    <div
                                                        style="width:40px; height:40px; border-radius:50%; background:url('<?php echo esc_url($s->image_url); ?>') center/cover; border:2px solid var(--bc-border);">
                                                    </div>
                                                <?php else: ?>
                                                    <div
                                                        style="width:40px; height:40px; border-radius:50%; background:var(--bc-border); display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:16px;">
                                                        <?php echo esc_html(strtoupper(substr($s->name, 0, 1))); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong
                                                        style="color:white; font-size:15px; display:block;"><?php echo esc_html($s->name); ?></strong>
                                                    <?php if (!empty($s->qualification)): ?>
                                                        <span
                                                            style="color:var(--bc-primary); font-size:12px;"><?php echo esc_html($s->qualification); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="bc-customer-info">
                                                <span style="color:var(--bc-text-muted); font-size:13px;">✉️
                                                    <?php echo esc_html($s->email); ?></span>
                                                <span style="color:var(--bc-text-muted); font-size:13px; margin-top:3px;">📞
                                                    <?php echo esc_html($s->phone ?: __('N/A', 'boocommerce')); ?></span>
                                            </div>
                                        </td>
                                        <td><span
                                                class="bc-status bc-status-<?php echo $s->status === 'active' ? 'completed' : 'cancelled'; ?>"><?php echo esc_html(ucfirst($s->status)); ?></span>
                                        </td>
                                        <td align="center">
                                            <div style="display:inline-flex; flex-direction:column; align-items:center; gap:5px;">
                                                <div style="display:flex; align-items:center; gap:8px;">
                                                    <span style="color:var(--bc-success); font-weight:bold; font-size:14px;">
                                                        <?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?><?php echo number_format($s->total_revenue, 2); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td align="right">
                                            <div class="bc-row-actions">
                                                <a href="?page=bc_main&tab=staff&action=edit&id=<?php echo $s->id; ?>"
                                                    class="bc-row-action bc-action-edit" title="<?php esc_attr_e('Edit Staff Member', 'boocommerce'); ?>">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                    </svg>
                                                </a>
                                                <a href="<?php echo wp_nonce_url("?page=bc_main&tab=staff&action=delete&id=" . $s->id, 'delete_staff_' . $s->id); ?>"
                                                    class="bc-row-action bc-action-delete" title="<?php esc_attr_e('Remove Staff Member', 'boocommerce'); ?>"
                                                    onclick="return confirm('<?php esc_attr_e('Are you sure you want to fire this staff member?', 'boocommerce'); ?>');">
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
                                    <td colspan="4" style="padding:40px; text-align:center; color:var(--bc-text-muted);"><?php _e('Roster is empty.', 'boocommerce'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php
        }
    }
}
