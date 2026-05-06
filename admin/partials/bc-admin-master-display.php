<?php
/**
 * Master Admin Layout for Service Booking
 * High-performance, full-width, professional UI
 */
$tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'dashboard';
?>

<style>
/* STANDALONE APP ENGINE - Hide WordPress Bloat */
.toplevel_page_bc_main #adminmenuback, 
.toplevel_page_bc_main #adminmenuwrap, 
.toplevel_page_bc_main #wpadminbar { display: none !important; }
.toplevel_page_bc_main #wpcontent, 
.toplevel_page_bc_main #wpfooter { margin-left: 0 !important; padding-left: 0 !important; }
.toplevel_page_bc_main html.wp-toolbar { padding-top: 0 !important; }

.bc-master-wrapper {
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
.bc-master-sidebar {
    width: 280px;
    min-width: 280px;
    flex-shrink: 0;
    background: #0f172a;
    border-right: 1px solid #1e293b;
    display: flex;
    flex-direction: column;
    height: 100vh;
}

.bc-sidebar-header {
    padding: 30px;
    border-bottom: 1px solid #1e293b;
    margin-bottom: 20px;
}

.bc-sidebar-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 800;
    background: linear-gradient(135deg, #818cf8, #c084fc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.bc-sidebar-nav {
    flex-grow: 1;
    padding: 0 15px;
}

.bc-nav-item {
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

.bc-nav-item:hover {
    background: rgba(255,255,255,0.05);
    color: #fff;
}

.bc-nav-item.active {
    background: #6366f1;
    color: #fff;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.bc-nav-icon {
    margin-right: 12px;
    font-size: 18px;
}

/* Content Area */
.bc-master-content {
    flex-grow: 1;
    padding: 40px;
    overflow-y: auto;
}

/* Override standard WP styles to match our theme */
.bc-master-content .wrap { margin: 0; }
.bc-master-content h1 { color: #fff; font-size: 32px; font-weight: 800; letter-spacing: -0.02em; margin-bottom: 30px !important; }

/* Responsive */
@media (max-width: 960px) {
    .bc-master-sidebar { width: 80px; min-width: 80px; flex-shrink: 0; }
    .bc-nav-text, .bc-sidebar-header h2 { display: none; }
}
.bc-loader {
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
    animation: bc-spin 1s linear infinite;
    z-index: 1000;
}
@keyframes bc-spin { to { transform: translate(-50%, -50%) rotate(360deg); } }

.bc-nav-item { cursor: pointer; }
    .bc-nav-bar { background: #0f172a; }
    
    /* Admin Notices Visibility Override */
    .notice {
        background: #0f172a !important;
        color: #ffffff !important;
        border: 1px solid var(--bc-border) !important;
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

<div class="bc-master-wrapper">
    <!-- Main Sidebar Navigation -->
    <div class="bc-master-sidebar">
        <div class="bc-sidebar-header">
            <h2>WSB ELITE</h2>
        </div>
        
        <nav class="bc-sidebar-nav">
            <?php
            $nav_items = apply_filters('bc_admin_nav_items', [
                'dashboard' => ['icon' => 'dashicons-chart-bar', 'label' => __('Overview', 'boocommerce')],
                'bookings'  => ['icon' => 'dashicons-calendar-alt', 'label' => __('Bookings', 'boocommerce')],
                'finance'   => ['icon' => 'dashicons-money-alt', 'label' => __('Finance', 'boocommerce')],
                'services'  => ['icon' => 'dashicons-admin-tools', 'label' => __('Services', 'boocommerce')],
                'staff'     => ['icon' => 'dashicons-groups', 'label' => __('Professional Team', 'boocommerce')],
                'customers' => ['icon' => 'dashicons-admin-users', 'label' => __('Clients', 'boocommerce')],
                'design'    => ['icon' => 'dashicons-art', 'label' => __('Customization', 'boocommerce')],
                'integrations' => ['icon' => 'dashicons-networking', 'label' => __('Integrations', 'boocommerce')],
                'settings'  => ['icon' => 'dashicons-admin-settings', 'label' => __('System Settings', 'boocommerce')],
            ]);
            foreach ($nav_items as $key => $item):
                $active = ($tab === $key) ? 'active' : '';
            ?>
                <div class="bc-nav-item <?php echo $active; ?>" data-tab="<?php echo esc_attr($key); ?>">
                    <span class="bc-nav-icon dashicons <?php echo esc_attr($item['icon']); ?>" style="font-size: 18px; width: auto; height: auto;"></span>
                    <span class="bc-nav-text"><?php echo esc_html($item['label']); ?></span>
                </div>
            <?php endforeach; ?>
        </nav>

        <!-- Sidebar Footer Action -->
        <div style="padding: 20px; border-top: 1px solid #1e293b;">
            <a href="<?php echo admin_url(); ?>" class="bc-nav-item" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                <span class="bc-nav-icon dashicons dashicons-wordpress" style="font-size: 20px; width: auto; height: auto;"></span>
                <span class="bc-nav-text"><?php _e('Exit to WordPress', 'boocommerce'); ?></span>
            </a>
        </div>
    </div>

    <!-- Dynamic Content Rendering Area -->
    <div class="bc-master-content">
        <div class="bc-loader"></div>
        <div id="bc-ajax-response">
            <?php
            switch ($tab) {
            case 'bookings':
                (new Bc_Admin_Bookings($this))->display();
                break;
            case 'finance':
                (new Bc_Admin_Finance($this))->display();
                break;
            case 'services':
                (new Bc_Admin_Services($this))->display();
                break;
            case 'staff':
                (new Bc_Admin_Staff($this))->display();
                break;
            case 'customers':
                (new Bc_Admin_Customers($this))->display();
                break;
            case 'design':
                (new Bc_Admin_Design($this))->display();
                break;
            case 'settings':
                (new Bc_Admin_Settings($this))->display();
                break;
            case 'integrations':
                (new Bc_Admin_Integrations($this))->display();
                break;
            default:
                (new Bc_Admin_Dashboard($this))->display();
                break;
        }
        ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

