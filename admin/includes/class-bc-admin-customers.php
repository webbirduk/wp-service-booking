<?php
class Bc_Admin_Customers {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        global $wpdb;
        $table_customers = $wpdb->prefix . 'bc_customers';
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';



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
                                </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 40px; color: var(--bc-text-muted);"><?php _e('No client records found.', 'boocommerce'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}
