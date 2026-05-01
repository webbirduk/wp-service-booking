<?php
/**
 * Master Admin Layout for Service Booking
 * High-performance, full-width, professional UI
 */
$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
?>

<style>
/* STANDALONE APP ENGINE - Hide WordPress Bloat */
#adminmenuback, #adminmenuwrap, #wpadminbar { display: none !important; }
#wpcontent, #wpfooter { margin-left: 0 !important; padding-left: 0 !important; }
html.wp-toolbar { padding-top: 0 !important; }

.wsb-master-wrapper {
    display: flex;
    min-height: 100vh;
    background: #020617; /* Deep midnight */
    color: #f8fafc;
    font-family: 'Inter', -apple-system, sans-serif;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 99999; /* Stay on top of everything */
}

/* Sidebar Styling */
.wsb-master-sidebar {
    width: 280px;
    background: #0f172a;
    border-right: 1px solid #1e293b;
    display: flex;
    flex-direction: column;
    height: 100vh;
}

.wsb-sidebar-header {
    padding: 30px;
    border-bottom: 1px solid #1e293b;
    margin-bottom: 20px;
}

.wsb-sidebar-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    background: linear-gradient(135deg, #818cf8, #c084fc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.wsb-sidebar-nav {
    flex-grow: 1;
    padding: 0 15px;
}

.wsb-nav-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    text-decoration: none;
    color: #94a3b8;
    border-radius: 10px;
    margin-bottom: 5px;
    font-weight: 500;
    transition: all 0.2s;
}

.wsb-nav-item:hover {
    background: rgba(255,255,255,0.05);
    color: #fff;
}

.wsb-nav-item.active {
    background: #6366f1;
    color: #fff;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.wsb-nav-icon {
    margin-right: 12px;
    font-size: 18px;
}

/* Content Area */
.wsb-master-content {
    flex-grow: 1;
    padding: 40px;
    overflow-y: auto;
}

/* Override standard WP styles to match our theme */
.wsb-master-content .wrap { margin: 0; }
.wsb-master-content h1 { color: #fff; font-size: 32px; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 30px !important; }

/* Responsive */
@media (max-width: 960px) {
    .wsb-master-sidebar { width: 80px; }
    .wsb-nav-text, .wsb-sidebar-header h2 { display: none; }
}
.wsb-loader {
    display: none;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 40px;
    height: 40px;
    border: 3px solid rgba(255,255,255,0.1);
    border-radius: 50%;
    border-top-color: #6366f1;
    animation: wsb-spin 1s linear infinite;
    z-index: 1000;
}
@keyframes wsb-spin { to { transform: translate(-50%, -50%) rotate(360deg); } }

.wsb-nav-item { cursor: pointer; }
    .wsb-nav-bar { background: #0f172a; }
    
    /* Admin Notices Visibility Override */
    .notice {
        background: #0f172a !important;
        color: #ffffff !important;
        border: 1px solid var(--wsb-border) !important;
        border-left: 4px solid #22c55e !important; 
        border-radius: 8px !important;
        padding: 15px !important;
        margin: 15px 0 !important;
    }
    .notice p {
        color: #ffffff !important;
        font-weight: 600 !important;
    }
    .notice-error { border-left-color: #ef4444 !important; }
    .notice-warning { border-left-color: #f59e0b !important; }
    .notice-info { border-left-color: #3b82f6 !important; }
</style>

<div class="wsb-master-wrapper">
    <!-- Main Sidebar Navigation -->
    <div class="wsb-master-sidebar">
        <div class="wsb-sidebar-header">
            <h2>WSB ELITE</h2>
        </div>
        
        <nav class="wsb-sidebar-nav">
            <?php
            $nav_items = [
                'dashboard' => ['icon' => 'dashicons-chart-bar', 'label' => 'Overview'],
                'bookings'  => ['icon' => 'dashicons-calendar-alt', 'label' => 'Bookings'],
                'finance'   => ['icon' => 'dashicons-money-alt', 'label' => 'Finance'],
                'services'  => ['icon' => 'dashicons-admin-tools', 'label' => 'Services'],
                'staff'     => ['icon' => 'dashicons-groups', 'label' => 'Professional Team'],
                'customers' => ['icon' => 'dashicons-admin-users', 'label' => 'Clients'],
                'design'    => ['icon' => 'dashicons-art', 'label' => 'Designer Choice'],
                'settings'  => ['icon' => 'dashicons-admin-settings', 'label' => 'System Settings'],
            ];
            foreach ($nav_items as $key => $item):
                $active = ($tab === $key) ? 'active' : '';
            ?>
                <div class="wsb-nav-item <?php echo $active; ?>" data-tab="<?php echo esc_attr($key); ?>">
                    <span class="wsb-nav-icon dashicons <?php echo esc_attr($item['icon']); ?>" style="font-size: 18px; width: auto; height: auto;"></span>
                    <span class="wsb-nav-text"><?php echo $item['label']; ?></span>
                </div>
            <?php endforeach; ?>
        </nav>

        <!-- Sidebar Footer Action -->
        <div style="padding: 20px; border-top: 1px solid #1e293b;">
            <a href="<?php echo admin_url(); ?>" class="wsb-nav-item" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                <span class="wsb-nav-icon dashicons dashicons-wordpress" style="font-size: 20px; width: auto; height: auto;"></span>
                <span class="wsb-nav-text">Exit to WordPress</span>
            </a>
        </div>
    </div>

    <!-- Dynamic Content Rendering Area -->
    <div class="wsb-master-content">
        <div class="wsb-loader"></div>
        <div id="wsb-ajax-response">
            <?php
            switch ($tab) {
            case 'bookings':
                $this->display_bookings_page();
                break;
            case 'finance':
                $this->display_finance_page();
                break;
            case 'services':
                $this->display_services_page();
                break;
            case 'staff':
                $this->display_staff_page();
                break;
            case 'customers':
                $this->display_customers_page();
                break;
            case 'design':
                $this->display_design_page();
                break;
            case 'settings':
                $this->display_settings_page();
                break;
            default:
                $this->display_plugin_setup_page();
                break;
        }
        ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

