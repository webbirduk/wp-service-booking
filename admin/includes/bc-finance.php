<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bc_Finance {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        global $wpdb;

        // Global Filter Logic
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'all';
        $where_date = "";
        if ($period === 'today') {
            $where_date = " AND DATE(p.created_at) = CURDATE()";
        } elseif ($period === '7days') {
            $where_date = " AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        } elseif ($period === '30days') {
            $where_date = " AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($period === 'year') {
            $where_date = " AND YEAR(p.created_at) = YEAR(CURDATE())";
        }

        // Financial Metrics globally respecting filter
        $total_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}bc_payments p WHERE status = 'completed' {$where_date}");
        $total_transactions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bc_payments p WHERE status = 'completed' {$where_date}");
        $avg_transaction = $total_transactions > 0 ? $total_revenue / $total_transactions : 0;
        $pending_revenue = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}bc_payments p WHERE status = 'pending' {$where_date}");

        // Dynamic Chart Query respecting filter
        if (in_array($period, ['30days', '7days', 'today'])) {
            $chart_query = "SELECT DATE_FORMAT(created_at, '%b %d') as label, SUM(amount) as val FROM {$wpdb->prefix}bc_payments p WHERE status='completed' {$where_date} GROUP BY label ORDER BY DATE(p.created_at) ASC";
        } else {
            $chart_query = "SELECT DATE_FORMAT(created_at, '%Y %b') as label, SUM(amount) as val FROM {$wpdb->prefix}bc_payments p WHERE status='completed' {$where_date} GROUP BY label ORDER BY YEAR(p.created_at) ASC, MONTH(p.created_at) ASC";
        }
        $chart_data = $wpdb->get_results($chart_query);
        $chart_json = json_encode($chart_data);

        // Grid of Payments respecting filter
        $query = "SELECT p.*, b.total_amount, c.first_name, c.last_name, s.name as service_name
                  FROM {$wpdb->prefix}bc_payments p
                  JOIN {$wpdb->prefix}bc_bookings b ON p.booking_id = b.id
                  LEFT JOIN {$wpdb->prefix}bc_customers c ON b.customer_id = c.id
                  LEFT JOIN {$wpdb->prefix}bc_services s ON b.service_id = s.id
                  WHERE 1=1 {$where_date}
                  ORDER BY p.created_at DESC";
        $payments = $wpdb->get_results($query);

        ?>
        <div class="wrap bc-admin-wrap bc-finance-wrapper">
            <style>
                /* Finance Responsive Layouts */
                .bc-finance-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
                .bc-finance-meta-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
                
                @media (max-width: 1024px) {
                    .bc-finance-meta-grid { grid-template-columns: repeat(2, 1fr); }
                }
                
                @media (max-width: 768px) {
                    .bc-finance-header { flex-direction: column; align-items: flex-start; gap: 15px; }
                    .bc-finance-filter-form { width: 100%; display: flex; flex-direction: column; align-items: stretch !important; gap: 10px; }
                    .bc-finance-filter-form select { width: 100%; }
                    .bc-finance-meta-grid { grid-template-columns: 1fr; }
                    .bc-finance-table-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
                    .bc-modern-table { min-width: 800px; }
                }
            </style>
            <div class="bc-finance-header">
                <h1 style="margin:0;"><?php _e('Financial Ledger & Revenue', 'boocommerce'); ?></h1>

                <!-- Master Dashboard Filter -->
                <form method="get" class="bc-finance-filter-form" style="display:flex; align-items:center; gap:10px;">
                    <input type="hidden" name="page" value="bc_main">
                    <input type="hidden" name="tab" value="finance">
                    <span style="color:var(--bc-text-muted); font-size:14px;"><?php _e('Reporting Period:', 'boocommerce'); ?></span>
                    <select name="period"
                        style="background:#0f172a; color:white; border:1px solid var(--bc-primary); padding:6px 12px; border-radius:6px; font-weight:bold;"
                        onchange="this.form.dispatchEvent(new Event('submit', {cancelable: true, bubbles: true}))">
                        <option value="all" <?php selected($period, 'all'); ?>><?php _e('All Time', 'boocommerce'); ?></option>
                        <option value="today" <?php selected($period, 'today'); ?>><?php _e('Today', 'boocommerce'); ?></option>
                        <option value="7days" <?php selected($period, '7days'); ?>><?php _e('Last 7 Days', 'boocommerce'); ?></option>
                        <option value="30days" <?php selected($period, '30days'); ?>><?php _e('Last 30 Days', 'boocommerce'); ?></option>
                        <option value="year" <?php selected($period, 'year'); ?>><?php _e('This Year', 'boocommerce'); ?></option>
                    </select>
                </form>
            </div>

            <!-- Metric Cards -->
            <div class="bc-finance-meta-grid">
                <div class="bc-stat-card" style="border-left: 4px solid var(--bc-success);">
                    <h3 style="margin-top:0; font-size:16px;"><?php _e('Total Realized Revenue', 'boocommerce'); ?></h3>
                    <p class="bc-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--bc-success);">
                        <?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?><?php echo number_format((float) $total_revenue, 2); ?></p>
                </div>
                <div class="bc-stat-card" style="border-left: 4px solid var(--bc-primary);">
                    <h3 style="margin-top:0; font-size:16px;"><?php _e('Verified Transactions', 'boocommerce'); ?></h3>
                    <p class="bc-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--bc-primary);">
                        <?php echo intval($total_transactions); ?></p>
                </div>
                <div class="bc-stat-card" style="border-left: 4px solid #10b981;">
                    <h3 style="margin-top:0; font-size:16px;"><?php _e('Avg. Transaction Value', 'boocommerce'); ?></h3>
                    <p class="bc-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:#10b981;">
                        <?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?><?php echo number_format((float) $avg_transaction, 2); ?></p>
                </div>
                <div class="bc-stat-card" style="border-left: 4px solid var(--bc-warning);">
                    <h3 style="margin-top:0; font-size:16px;"><?php _e('Pending / Outstanding', 'boocommerce'); ?></h3>
                    <p class="bc-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--bc-warning);">
                        <?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?><?php echo number_format((float) $pending_revenue, 2); ?></p>
                </div>
            </div>

            <!-- Dynamic Chart -->
            <div
                style="background:var(--bc-panel-dark); padding:20px; border-radius:12px; border:1px solid var(--bc-border); margin-bottom:30px;">
                <h3 style="margin:0 0 20px 0; color: #fff;"><?php _e('Revenue Performance Analysis', 'boocommerce'); ?></h3>
                <div style="position:relative; height:300px; width:100%;">
                    <canvas id="bcRevenueChart" data-chart='<?php echo $chart_json; ?>'></canvas>
                </div>
            </div>

            <!-- Ledger Data Table -->
            <div
                style="background:var(--bc-panel-dark); border-radius:12px; border:1px solid var(--bc-border); overflow:hidden;">
                <div style="padding: 20px; border-bottom: 1px solid var(--bc-border);">
                    <h3 style="margin:0; color: #fff;"><?php _e('Recent Activity', 'boocommerce'); ?></h3>
                </div>
                <div class="bc-finance-table-wrapper" style="max-height: 450px; overflow-y: auto;">
                    <table class="bc-modern-table" style="margin:0; width: 100%;">
                        <thead style="position: sticky; top: 0; background: #0f172a; z-index: 10;">
                            <tr>
                                <th><?php _e('Transaction ID', 'boocommerce'); ?></th>
                                <th><?php _e('Gateway', 'boocommerce'); ?></th>
                                <th><?php _e('Amount', 'boocommerce'); ?></th>
                                <th><?php _e('Related Booking', 'boocommerce'); ?></th>
                                <th><?php _e('Date', 'boocommerce'); ?></th>
                                <th><?php _e('Status', 'boocommerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($payments)):
                                foreach ($payments as $p): ?>
                                    <tr>
                                        <td><strong
                                                style="color:var(--bc-text-muted); font-family:monospace;"><?php echo esc_html($p->transaction_id ?: 'N/A'); ?></strong>
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <?php if ($p->gateway === 'stripe'): ?>
                                                    <img src="<?php echo BC_PLUGIN_URL . 'assets/images/stripe.png'; ?>" style="height:14px; width:auto;" alt="Stripe">
                                                <?php endif; ?>
                                                <span style="background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:12px;"><?php echo esc_html(strtoupper($p->gateway)); ?></span>
                                            </div>
                                        </td>
                                        <td><strong
                                                style="color:var(--bc-success);"><?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?><?php echo number_format((float) $p->amount, 2); ?></strong>
                                        </td>
                                        <td>
                                            <div class="bc-customer-info">
                                                <span class="bc-customer-name" style="color:var(--bc-primary);"><?php _e('Booking', 'boocommerce'); ?>
                                                    #<?php echo esc_html(str_pad($p->booking_id, 5, '0', STR_PAD_LEFT)); ?></span>
                                                <span
                                                    class="bc-customer-meta"><?php echo esc_html($p->first_name . ' ' . $p->last_name . ' - ' . $p->service_name); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html(date('M d, Y', strtotime($p->created_at))); ?></td>
                                        <td><span
                                                class="bc-status bc-status-<?php echo esc_attr($p->status); ?>"><?php echo esc_html(ucfirst($p->status)); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding: 40px; color: var(--bc-text-muted);"><?php _e('No payment records found in this timeframe.', 'boocommerce'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}
