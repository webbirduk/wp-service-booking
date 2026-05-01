<?php
class Wsb_Admin_Dashboard {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'wsb_bookings';

        // Self-Healing Schema Patching
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
        
        $today_bookings = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_bookings WHERE booking_date = %s", date('Y-m-d')));
        $pending_approvals = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_bookings WHERE status = 'pending' AND (request_type IS NULL OR request_type = '')");
        $client_requests = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wsb_bookings WHERE status = 'pending' AND request_type IN ('cancel', 'reschedule')");

        // Revenue Trajectory Data
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

            <!-- Quick Actions Row -->
            <div class="wsb-dashboard-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom:30px;">
                <a href="?page=wsb_main&tab=bookings&filter_date_start=<?php echo date('Y-m-d'); ?>&filter_date_end=<?php echo date('Y-m-d'); ?>" class="wsb-stat-card wsb-clickable-card" style="border-left-color: #3b82f6;">
                    <h3 style="margin-top:0; font-size:16px; color:#3b82f6;">Today's Schedule</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;"><?php echo intval($today_bookings); ?></p>
                </a>

                <a href="?page=wsb_main&tab=bookings&filter_status=pending" class="wsb-stat-card wsb-clickable-card" style="border-left-color: #f59e0b;">
                    <h3 style="margin-top:0; font-size:16px; color:#f59e0b;">Pending Approvals</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;"><?php echo intval($pending_approvals); ?></p>
                </a>

                <a href="?page=wsb_main&tab=bookings&filter_status=pending_requests" class="wsb-stat-card wsb-clickable-card" style="border-left-color: #ef4444;">
                    <h3 style="margin-top:0; font-size:16px; color:#ef4444;">Client Requests</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;"><?php echo intval($client_requests); ?></p>
                </a>
            </div>

            <!-- Global Metrics Row -->
            <div class="wsb-dashboard-grid"
                style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom:30px;">
                <div class="wsb-stat-card">
                    <h3 style="margin-top:0; font-size:16px;">Total Bookings</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;">
                        <?php echo intval($total_bookings); ?></p>
                </div>

                <div class="wsb-stat-card">
                    <h3 style="margin-top:0; font-size:16px;">Total Revenue</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold; color:var(--wsb-success);">
                        <?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo number_format((float) $total_revenue, 2); ?></p>
                </div>

                <div class="wsb-stat-card">
                    <h3 style="margin-top:0; font-size:16px;">Total Customers</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;">
                        <?php echo intval($total_customers); ?></p>
                </div>

                <div class="wsb-stat-card">
                    <h3 style="margin-top:0; font-size:16px;">Active Services</h3>
                    <p class="wsb-stat-value" style="margin:0; font-size:32px; font-weight:bold;">
                        <?php echo intval($total_services); ?></p>
                </div>
            </div>

            <!-- Insights Section -->
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom:30px;">
                <div style="background: var(--wsb-panel-dark); border: 1px solid var(--wsb-border); border-radius:12px; padding:20px;">
                    <h3 style="margin-top:0; margin-bottom:20px; color:#fff; font-size:16px;">Revenue Trajectory (Last 7 Days)</h3>
                    <div style="height:250px; width:100%;">
                        <canvas id="wsb-revenue-chart"></canvas>
                    </div>
                </div>

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
                            <p style="color:var(--wsb-text-muted); font-size:13px; text-align:center; padding:20px;">No performance data available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <script>
                (function() {
                    var initDashboardCharts = function() {
                        if (typeof Chart === 'undefined') {
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
                                    borderWidth: 3
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { display: false } },
                                scales: {
                                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } },
                                    x: { grid: { display: false } }
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

            <div style="background: var(--wsb-panel-dark); border-radius:12px; border:1px solid var(--wsb-border); overflow:hidden;">
                <div style="padding: 20px; border-bottom: 1px solid var(--wsb-border); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="margin:0; color: #fff;">Recent Activity</h3>
                    <a href="?page=wsb_main&tab=bookings" style="color:var(--wsb-primary); text-decoration:none; font-weight:500;">View All</a>
                </div>
                <div style="max-height: 400px; overflow-y: auto;">
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
                        <?php if (!empty($recent_bookings)):
                            foreach ($recent_bookings as $rb): ?>
                                <tr class="wsb-clickable-row" data-href="?page=wsb_main&tab=bookings&action=edit&id=<?php echo $rb->id; ?>" style="border-bottom:1px solid var(--wsb-border);">
                                    <td style="padding:15px 20px; font-weight:500;"><?php echo esc_html($rb->first_name . ' ' . $rb->last_name); ?></td>
                                    <td style="padding:15px 20px; color:var(--wsb-text-muted);"><?php echo esc_html($rb->service_name); ?></td>
                                    <td style="padding:15px 20px;"><?php echo esc_html(date('M d, Y', strtotime($rb->booking_date))); ?></td>
                                    <td style="padding:15px 20px;"><span class="wsb-status wsb-status-<?php echo esc_attr($rb->status); ?>" style="font-size:11px;"><?php echo esc_html(ucfirst($rb->status)); ?></span></td>
                                </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="4" style="padding:30px; text-align:center; color:var(--wsb-text-muted);">No recent activity.</td>
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
