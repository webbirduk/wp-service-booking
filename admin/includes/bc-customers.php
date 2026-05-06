<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bc_Customers {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        global $wpdb;
        $table_customers = $wpdb->prefix . 'bc_customers';
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($action === 'view' && $customer_id) {
            $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_customers} WHERE id = %d", $customer_id));
            if ($customer) {
                // Fetch All Bookings for this customer
                $bookings = $wpdb->get_results($wpdb->prepare("
                    SELECT b.*, st.name as staff_name
                    FROM {$wpdb->prefix}bc_bookings b
                    LEFT JOIN {$wpdb->prefix}bc_staff st ON b.staff_id = st.id
                    WHERE b.customer_id = %d
                    ORDER BY b.booking_date DESC, b.start_time DESC
                ", $customer_id));

                // Process service names for each booking
                foreach ($bookings as &$b) {
                    $b->service_names = __('Unknown Service', 'boocommerce');
                    if (!empty($b->service_id)) {
                        $s_ids = array_map('intval', explode(',', $b->service_id));
                        $placeholders = implode(',', array_fill(0, count($s_ids), '%d'));
                        $services = $wpdb->get_results($wpdb->prepare("SELECT name FROM {$wpdb->prefix}bc_services WHERE id IN ($placeholders)", $s_ids));
                        if ($services) {
                            $names = array_map(function($s) { return $s->name; }, $services);
                            $b->service_names = implode(', ', $names);
                        }
                    }
                }
                ?>
                <div class="wrap bc-admin-wrap bc-customer-details">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
                        <a href="?page=bc_main&tab=customers" class="bc-btn-primary" style="background:var(--bc-border); text-decoration:none;">&larr; <?php _e('Back to Client Directory', 'boocommerce'); ?></a>
                        <h2 style="margin:0; color:var(--bc-primary);"><?php _e('Client Profile Analysis', 'boocommerce'); ?></h2>
                    </div>

                    <div style="display:grid; grid-template-columns: 350px 1fr; gap:30px;">
                        <!-- Left Panel: Personal Details -->
                        <div>
                            <div style="background:var(--bc-panel-dark); border-radius:16px; border:1px solid var(--bc-border); padding:30px; text-align:center; position:sticky; top:20px;">
                                <div style="width:100px; height:100px; border-radius:50%; background:var(--bc-border); display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:40px; margin:0 auto 20px; border:4px solid var(--bc-primary);">
                                    <?php echo esc_html(strtoupper(substr($customer->first_name, 0, 1) . substr($customer->last_name, 0, 1))); ?>
                                </div>
                                <h3 style="margin:0; font-size:24px; color:white;"><?php echo esc_html($customer->first_name . ' ' . $customer->last_name); ?></h3>
                                <p style="color:var(--bc-text-muted); margin-bottom:25px;"><?php _e('Client ID:', 'boocommerce'); ?> #<?php echo esc_html(str_pad($customer->id, 5, '0', STR_PAD_LEFT)); ?></p>

                                <div style="text-align:left; border-top:1px solid var(--bc-border); padding-top:25px;">
                                    <div style="margin-bottom:15px;">
                                        <label style="display:block; color:var(--bc-text-muted); font-size:12px; text-transform:uppercase;"><?php _e('Email Address', 'boocommerce'); ?></label>
                                        <div style="color:white; font-weight:600;"><?php echo esc_html($customer->email); ?></div>
                                    </div>
                                    <div style="margin-bottom:15px;">
                                        <label style="display:block; color:var(--bc-text-muted); font-size:12px; text-transform:uppercase;"><?php _e('Phone Number', 'boocommerce'); ?></label>
                                        <div style="color:white; font-weight:600;"><?php echo esc_html($customer->phone ?: __('Not Provided', 'boocommerce')); ?></div>
                                    </div>
                                    <div style="margin-bottom:15px;">
                                        <label style="display:block; color:var(--bc-text-muted); font-size:12px; text-transform:uppercase;"><?php _e('Registration Date', 'boocommerce'); ?></label>
                                        <div style="color:white; font-weight:600;"><?php echo esc_html(date('F d, Y', strtotime($customer->created_at))); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Panel: Booking History -->
                        <div>
                            <div style="background:var(--bc-panel-dark); border-radius:16px; border:1px solid var(--bc-border); padding:30px;">
                                <h3 style="margin:0 0 25px; color:var(--bc-primary); display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-calendar-alt"></span> <?php _e('Complete Booking History', 'boocommerce'); ?>
                                </h3>

                                <?php if (!empty($bookings)): ?>
                                    <div class="bc-modern-table-wrapper">
                                        <table class="bc-modern-table" style="width:100%;">
                                            <thead>
                                                <tr>
                                                    <th><?php _e('Service & Date', 'boocommerce'); ?></th>
                                                    <th><?php _e('Assigned Pro', 'boocommerce'); ?></th>
                                                    <th><?php _e('Status', 'boocommerce'); ?></th>
                                                    <th style="text-align:right;"><?php _e('Amount', 'boocommerce'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($bookings as $b): 
                                                    $is_past = strtotime($b->booking_date) < strtotime(date('Y-m-d'));
                                                    $status_color = ($b->status === 'confirmed') ? 'var(--bc-success)' : (($b->status === 'pending') ? 'var(--bc-warning)' : '#ef4444');
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <strong style="color:white; display:block;"><?php echo esc_html($b->service_names); ?></strong>
                                                            <span style="color:var(--bc-text-muted); font-size:12px;">
                                                                📅 <?php echo esc_html(date('M d, Y', strtotime($b->booking_date))); ?> @ <?php echo esc_html($b->start_time); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span style="color:var(--bc-text-muted);"><?php echo esc_html($b->staff_name ?: __('Unassigned', 'boocommerce')); ?></span>
                                                        </td>
                                                        <td>
                                                            <span style="display:inline-block; padding:4px 10px; border-radius:20px; background:<?php echo $status_color; ?>22; color:<?php echo $status_color; ?>; font-size:11px; font-weight:bold; text-transform:uppercase; border:1px solid <?php echo $status_color; ?>44;">
                                                                <?php echo esc_html($b->status); ?>
                                                            </span>
                                                            <?php if ($is_past): ?>
                                                                <span style="display:block; font-size:10px; color:var(--bc-text-muted); margin-top:4px;"><?php _e('(Past Event)', 'boocommerce'); ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td align="right">
                                                            <strong style="color:white;"><?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')) . number_format($b->total_amount, 2); ?></strong>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div style="text-align:center; padding:50px; background:rgba(255,255,255,0.02); border-radius:12px; border:1px dashed var(--bc-border);">
                                        <span class="dashicons dashicons-calendar" style="font-size:40px; width:40px; height:40px; color:var(--bc-text-muted); margin-bottom:15px;"></span>
                                        <p style="color:var(--bc-text-muted);"><?php _e('This client has no booking records yet.', 'boocommerce'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                return;
            }
        }



        // Filter Matrix Engine
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
        $filter_search = isset($_GET['filter_search']) ? sanitize_text_field($_GET['filter_search']) : '';
        $where_clause = "WHERE 1=1";
        if ($filter_status === 'recent') {
            $where_clause .= " AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        }
        if (!empty($filter_search)) {
            $where_clause .= $wpdb->prepare(" AND (c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s OR c.phone LIKE %s)", 
                '%' . $wpdb->esc_like($filter_search) . '%',
                '%' . $wpdb->esc_like($filter_search) . '%',
                '%' . $wpdb->esc_like($filter_search) . '%',
                '%' . $wpdb->esc_like($filter_search) . '%'
            );
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
                  LEFT JOIN {$wpdb->prefix}bc_bookings b ON c.id = b.customer_id
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
                LEFT JOIN {$wpdb->prefix}bc_bookings b ON c.id = b.customer_id
                GROUP BY c.id
                HAVING SUM(b.total_amount) >= 10 OR COUNT(b.id) >= 1
            ) as vips
        ");

        $page_url = "?page=bc_main&tab=customers";
        ?>
        <div class="wrap bc-admin-wrap bc-crm-list-wrapper">
            <style>
                /* CRM Responsive Layouts */
                .bc-crm-header { display: flex; justify-content: space-between; align-items: center; }
                .bc-crm-meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; margin-bottom: 20px; }
                .bc-crm-filter-form { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
                
                @media (max-width: 1024px) {
                    .bc-crm-meta-grid { grid-template-columns: repeat(2, 1fr); }
                }
                
                @media (max-width: 768px) {
                    .bc-crm-header { flex-direction: column; align-items: flex-start; gap: 15px; }
                    .bc-crm-list-wrapper .bc-btn-primary { width: 100%; text-align: center; display: block; margin-left: 0 !important; }
                    .bc-crm-meta-grid { grid-template-columns: 1fr; }
                    .bc-crm-filter-form { flex-direction: column; align-items: stretch; }
                    .bc-crm-filter-form input[type="text"], .bc-crm-filter-form button, .bc-crm-filter-form a { width: 100%; box-sizing: border-box; }
                    .bc-crm-table-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
                    .bc-modern-table { min-width: 800px; }
                }
            </style>
            <div class="bc-crm-header">
                <h1 style="margin:0;"><?php _e('Client CRM & Directory', 'boocommerce'); ?></h1>

            </div>

            <!-- Dashboard Interactive Filters -->
            <div class="bc-crm-meta-grid">
                <a href="<?php echo $page_url; ?>&filter_status=all"
                    class="customer-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--bc-text-muted);"><?php _e('Total Client Base', 'boocommerce'); ?></h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                        <?php echo intval($total_customers); ?></p>
                </a>
                <a href="<?php echo $page_url; ?>&filter_status=recent"
                    class="customer-filter-card <?php echo $filter_status === 'recent' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--bc-success);"><?php _e('Recent Signups (30d)', 'boocommerce'); ?></h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                        <?php echo intval($recent_customers); ?></p>
                </a>
                <a href="<?php echo $page_url; ?>&filter_status=vip"
                    class="customer-filter-card <?php echo $filter_status === 'vip' ? 'card-active' : ''; ?>">
                    <h3 style="margin-top:0; font-size:15px; color:var(--bc-warning);"><?php _e('VIP Clients (LTV)', 'boocommerce'); ?></h3>
                    <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                        <?php echo intval($vip_customers); ?></p>
                </a>
            </div>

            <!-- Search Filter Bar -->
            <form method="get" action="" class="bc-crm-filter-form">
                <input type="hidden" name="page" value="bc_main">
                <input type="hidden" name="tab" value="customers">
                <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">
                
                <input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" 
                    placeholder="<?php esc_attr_e('Search by name, email or phone...', 'boocommerce'); ?>" 
                    style="flex-grow:1; background:#0f172a; border:1px solid var(--bc-border); color:white; padding:10px 15px; border-radius:8px;">
                
                <button type="submit" class="bc-btn-primary"><?php _e('Search Clients', 'boocommerce'); ?></button>
                <?php if (!empty($filter_search)): ?>
                    <a href="?page=bc_main&tab=customers&filter_status=<?php echo esc_attr($filter_status); ?>" 
                        class="bc-btn-primary" style="background:var(--bc-border);"><?php _e('Clear', 'boocommerce'); ?></a>
                <?php endif; ?>
            </form>

            <div class="bc-crm-table-wrapper">
                <table class="bc-modern-table" style="margin:0; width:100%;">
                    <thead>
                        <tr>
                            <th><?php _e('Client Identity', 'boocommerce'); ?></th>
                            <th><?php _e('Contact Information', 'boocommerce'); ?></th>
                            <th><?php _e('Platform History', 'boocommerce'); ?></th>
                            <th style="text-align:right;"><?php _e('Lifetime Value (LTV)', 'boocommerce'); ?></th>
                            <th style="text-align:right;"><?php _e('Actions', 'boocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($customers)):
                            foreach ($customers as $c): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:15px;">
                                            <div
                                                style="width:40px; height:40px; border-radius:50%; background:var(--bc-border); display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:16px;">
                                                <?php echo esc_html(strtoupper(substr($c->first_name, 0, 1) . substr($c->last_name, 0, 1))); ?>
                                            </div>
                                            <div>
                                                <strong
                                                    style="color:white; font-size:15px; display:block;"><?php echo esc_html($c->first_name . ' ' . $c->last_name); ?></strong>
                                                <span style="color:var(--bc-text-muted); font-size:12px; font-family:monospace;"><?php _e('ID:', 'boocommerce'); ?>
                                                    #<?php echo esc_html(str_pad($c->id, 5, '0', STR_PAD_LEFT)); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="bc-customer-info">
                                            <span style="color:var(--bc-text-muted); font-size:13px;">✉️
                                                <?php echo esc_html($c->email); ?></span>
                                            <span style="color:var(--bc-text-muted); font-size:13px; margin-top:3px;">📞
                                                <?php echo esc_html($c->phone ?: __('N/A', 'boocommerce')); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color:var(--bc-text-muted); font-size:13px; display:block;"><?php _e('Joined:', 'boocommerce'); ?>
                                            <?php echo esc_html(date('M d, Y', strtotime($c->created_at))); ?></span>
                                        <span
                                            style="color:var(--bc-primary); font-size:12px; font-weight:bold; margin-top:3px; display:block;"><?php _e('Bookings:', 'boocommerce'); ?>
                                            <?php echo intval($c->booking_count); ?></span>
                                    </td>
                                    <td align="right">
                                        <strong
                                            style="color:var(--bc-success); font-size:16px;"><?php echo $c->total_spent > 0 ? bc_get_currency_symbol(get_option('bc_currency', 'USD')) . number_format($c->total_spent, 2) : '-'; ?></strong>
                                    </td>
                                    <td align="right">
                                        <a href="?page=bc_main&tab=customers&action=view&id=<?php echo $c->id; ?>" class="bc-btn-primary" style="padding: 6px 12px; font-size: 11px; text-decoration:none;">
                                            <span class="dashicons dashicons-visibility" style="font-size:16px; width:16px; height:16px; margin-top:2px;"></span> <?php _e('Analyze Profile', 'boocommerce'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 40px; color: var(--bc-text-muted);"><?php _e('No client records found.', 'boocommerce'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
