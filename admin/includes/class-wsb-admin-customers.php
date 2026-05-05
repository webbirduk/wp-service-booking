<?php
class Wsb_Admin_Customers {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        global $wpdb;
        $table_customers = $wpdb->prefix . 'wsb_customers';
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

        $page_url = "?page=wsb_main&tab=customers";
        ?>
        <div class="wrap wsb-admin-wrap wsb-crm-list-wrapper">
            <style>
                /* CRM Responsive Layouts */
                .wsb-crm-header { display: flex; justify-content: space-between; align-items: center; }
                .wsb-crm-meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; margin-bottom: 20px; }
                .wsb-crm-filter-form { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
                
                @media (max-width: 1024px) {
                    .wsb-crm-meta-grid { grid-template-columns: repeat(2, 1fr); }
                }
                
                @media (max-width: 768px) {
                    .wsb-crm-header { flex-direction: column; align-items: flex-start; gap: 15px; }
                    .wsb-crm-list-wrapper .wsb-btn-primary { width: 100%; text-align: center; display: block; margin-left: 0 !important; }
                    .wsb-crm-meta-grid { grid-template-columns: 1fr; }
                    .wsb-crm-filter-form { flex-direction: column; align-items: stretch; }
                    .wsb-crm-filter-form input[type="text"], .wsb-crm-filter-form button, .wsb-crm-filter-form a { width: 100%; box-sizing: border-box; }
                    .wsb-crm-table-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
                    .wsb-modern-table { min-width: 800px; }
                }
            </style>
            <div class="wsb-crm-header">
                <h1 style="margin:0;">Client CRM & Directory</h1>

            </div>

            <!-- Dashboard Interactive Filters -->
            <div class="wsb-crm-meta-grid">
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

            <!-- Search Filter Bar -->
            <form method="get" action="" class="wsb-crm-filter-form">
                <input type="hidden" name="page" value="wsb_main">
                <input type="hidden" name="tab" value="customers">
                <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">
                
                <input type="text" name="filter_search" value="<?php echo esc_attr($filter_search); ?>" 
                    placeholder="Search by name, email or phone..." 
                    style="flex-grow:1; background:#0f172a; border:1px solid var(--wsb-border); color:white; padding:10px 15px; border-radius:8px;">
                
                <button type="submit" class="wsb-btn-primary">Search Clients</button>
                <?php if (!empty($filter_search)): ?>
                    <a href="?page=wsb_main&tab=customers&filter_status=<?php echo esc_attr($filter_status); ?>" 
                        class="wsb-btn-primary" style="background:var(--wsb-border);">Clear</a>
                <?php endif; ?>
            </form>

            <div class="wsb-crm-table-wrapper">
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
}
