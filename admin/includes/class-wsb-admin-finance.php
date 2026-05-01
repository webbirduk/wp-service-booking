<?php
class Wsb_Admin_Finance {
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
                                    <tr>
                                        <td><strong
                                                style="color:var(--wsb-text-muted); font-family:monospace;"><?php echo esc_html($p->transaction_id ?: 'N/A'); ?></strong>
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <?php if ($p->gateway === 'stripe'): ?>
                                                    <img src="<?php echo WSB_PLUGIN_URL . 'assets/images/stripe.png'; ?>" style="height:14px; width:auto;" alt="Stripe">
                                                <?php endif; ?>
                                                <span style="background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:12px;"><?php echo esc_html(strtoupper($p->gateway)); ?></span>
                                            </div>
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
}
