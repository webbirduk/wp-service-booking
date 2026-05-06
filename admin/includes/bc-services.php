<?php
class Bc_Services {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        global $wpdb;
        $table_services = $wpdb->prefix . 'bc_services';
        $table_staff = $wpdb->prefix . 'bc_staff';

        // Auto-patch schema if image columns don't exist
        $wpdb->query("ALTER TABLE {$table_services} ADD COLUMN IF NOT EXISTS image_url varchar(255) DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table_services} ADD COLUMN IF NOT EXISTS gallery_urls text DEFAULT NULL");

        $action = isset($_REQUEST['bc_action']) ? sanitize_text_field($_REQUEST['bc_action']) : (isset($_GET['action']) ? $_GET['action'] : 'list');
        $service_id = isset($_REQUEST['service_id']) ? intval($_REQUEST['service_id']) : (isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0);

        if ($action === 'delete' && $service_id) {
            $wpdb->delete($table_services, array('id' => $service_id));
            if ($wpdb->last_error) echo '<div class="notice bc-custom-notice notice-error"><p>' . esc_html($wpdb->last_error) . '</p></div>';
            else echo '<div class="notice bc-custom-notice notice-success is-dismissible"><p>' . __('Service permanently deleted.', 'boocommerce') . '</p></div>';
            $action = 'list';
        }



        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bc_service_nonce']) && wp_verify_nonce($_POST['bc_service_nonce'], 'bc_add_service')) {
            $data = array(
                'name' => sanitize_text_field($_POST['service_name']),
                'description' => wp_kses_post($_POST['service_description']),
                'duration' => intval($_POST['service_duration']),
                'price' => floatval($_POST['service_price']),
                'buffer_time' => intval($_POST['service_buffer_time']),
                'category' => sanitize_text_field($_POST['service_category']),
                'capacity' => intval($_POST['service_capacity']),
                'image_url' => esc_url_raw($_POST['service_image_url']),
                'gallery_urls' => sanitize_text_field($_POST['service_gallery_urls']),
                'status' => 'active'
            );

            if ($service_id) {
                // Edit existing
                $wpdb->update($table_services, $data, array('id' => $service_id));
                if ($wpdb->last_error) echo '<div class="notice bc-custom-notice notice-error"><p>' . esc_html($wpdb->last_error) . '</p></div>';
                else echo '<div class="notice bc-custom-notice notice-success is-dismissible"><p>' . __('Service updated successfully!', 'boocommerce') . '</p></div>';
            } else {
                // Add new
                $wpdb->insert($table_services, $data);
                if ($wpdb->last_error) echo '<div class="notice bc-custom-notice notice-error"><p>' . esc_html($wpdb->last_error) . '</p></div>';
                else {
                    $service_id = $wpdb->insert_id;
                    echo '<div class="notice bc-custom-notice notice-success is-dismissible"><p>' . __('Service created successfully!', 'boocommerce') . '</p></div>';
                }
            }

            // Sync staff
            $assigned_staff = isset($_POST['assigned_staff']) ? array_map('intval', $_POST['assigned_staff']) : [];
            $table_staff_services = $wpdb->prefix . 'bc_staff_services';
            $wpdb->delete($table_staff_services, array('service_id' => $service_id));
            foreach ($assigned_staff as $staff_id) {
                $wpdb->insert($table_staff_services, array('staff_id' => $staff_id, 'service_id' => $service_id, 'custom_price' => $data['price']));
            }

            $action = 'list';
        }

        if ($action === 'add' || $action === 'edit') {
            $staff_members = $wpdb->get_results("SELECT id, name FROM $table_staff");
            $service = null;
            $assigned_staff_ids = [];

            if ($action === 'edit' && $service_id) {
                $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_services WHERE id = %d", $service_id));
                $staff_relations = $wpdb->get_results($wpdb->prepare("SELECT staff_id FROM {$wpdb->prefix}bc_staff_services WHERE service_id = %d", $service_id));
                foreach ($staff_relations as $sr)
                    $assigned_staff_ids[] = $sr->staff_id;
            }

            ?>
            <div class="wrap bc-admin-wrap bc-service-edit-wrapper">
                <style>
                    /* Service Edit Responsive Layouts */
                    .bc-service-edit-header { display: flex; justify-content: space-between; align-items: center; }
                    .bc-service-edit-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
                    .bc-service-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
                    
                    @media (max-width: 1024px) {
                        .bc-service-edit-grid { grid-template-columns: 1fr; }
                    }
                    @media (max-width: 768px) {
                        .bc-service-edit-header { flex-direction: column; align-items: flex-start; gap: 15px; }
                        .bc-service-edit-header > div { width: 100%; }
                        .bc-service-edit-wrapper .bc-btn-primary { width: 100%; text-align: center; display: block; margin-left: 0 !important; }
                        .bc-service-info-grid { grid-template-columns: 1fr; }
                    }
                </style>
                <div class="bc-service-edit-header">
                    <h1 style="margin:0;"><?php echo $action === 'edit' ? __('Manage Service', 'boocommerce') : __('Add New Service', 'boocommerce'); ?></h1>
                    <a href="?page=bc_main&tab=services" class="bc-btn-primary" style="background:var(--bc-border);"><?php _e('Back to Services', 'boocommerce'); ?></a>
                </div>
                <hr class="wp-header-end" style="margin-bottom:20px;">

                <form method="post" action="">
                    <?php wp_nonce_field('bc_add_service', 'bc_service_nonce'); ?>
                    <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">
                    <input type="hidden" name="bc_action" value="<?php echo $action; ?>">
                    <input type="hidden" name="tab" value="services">
                    <div class="bc-service-edit-grid">

                        <!-- Main Panel -->
                        <div
                            style="background: var(--bc-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--bc-border); border-top: 4px solid var(--bc-primary);">
                            <h3
                                style="margin-top:0; color:var(--bc-primary); font-size:18px; display:flex; align-items:center; gap:8px;">
                                <span class="dashicons dashicons-edit"></span> <?php _e('Basic Information', 'boocommerce'); ?></h3>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Service Name', 'boocommerce'); ?></label>
                                <input name="service_name" type="text"
                                    value="<?php echo $service ? esc_attr($service->name) : ''; ?>"
                                    style="width:100%; border:1px solid var(--bc-border); border-radius:6px; padding:10px; background:#0f172a; color:white; font-size:16px;"
                                    required>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Category', 'boocommerce'); ?></label>
                                <input name="service_category" type="text"
                                    value="<?php echo $service ? esc_attr($service->category) : ''; ?>"
                                    style="width:100%; border:1px solid var(--bc-border); border-radius:6px; padding:10px; background:#0f172a; color:white;">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Description', 'boocommerce'); ?></label>
                                <textarea name="service_description" rows="4"
                                    style="width:100%; border:1px solid var(--bc-border); border-radius:6px; padding:10px; background:#0f172a; color:white;"><?php echo $service ? esc_textarea($service->description) : ''; ?></textarea>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Featured Image', 'boocommerce'); ?></label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <div id="bc-featured-preview"
                                        style="width:60px; height:60px; border-radius:6px; border:1px dashed var(--bc-border); background:#0f172a <?php echo $service && $service->image_url ? 'url(' . esc_url($service->image_url) . ') center/cover' : ''; ?>;">
                                    </div>
                                    <input type="hidden" name="service_image_url" id="service_image_url"
                                        value="<?php echo $service ? esc_url($service->image_url) : ''; ?>">
                                    <button type="button" class="bc-btn-primary bc-select-image" data-target="#service_image_url"
                                        data-preview="#bc-featured-preview"
                                        style="background:var(--bc-border); color:white;"><?php _e('Select Image', 'boocommerce'); ?></button>
                                </div>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Service Gallery (Multiple Images)', 'boocommerce'); ?></label>
                                <input type="hidden" name="service_gallery_urls" id="service_gallery_urls"
                                    value="<?php echo $service && isset($service->gallery_urls) ? esc_attr($service->gallery_urls) : ''; ?>">
                                <div id="bc-gallery-preview" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                                    <?php
                                    if ($service && !empty($service->gallery_urls)) {
                                        $urls = explode(',', $service->gallery_urls);
                                        foreach ($urls as $url) {
                                            echo '<div style="width:50px; height:50px; border-radius:4px; background:url(' . esc_url($url) . ') center/cover; border:1px solid #334155;"></div>';
                                        }
                                    }
                                    ?>
                                </div>
                                <button type="button" class="bc-btn-primary bc-select-gallery"
                                    style="background:var(--bc-border); color:white;"><?php _e('Select Gallery Images', 'boocommerce'); ?></button>
                            </div>
                        </div>

                        <!-- Side Panel -->
                        <div>
                            <div
                                style="background: var(--bc-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--bc-border); margin-bottom: 20px; border-top: 4px solid var(--bc-success);">
                                <h3
                                    style="margin-top:0; color:var(--bc-success); font-size:18px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-money-alt"></span> <?php _e('Pricing & Duration', 'boocommerce'); ?></h3>
                                <div class="bc-service-info-grid" style="margin-bottom: 15px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Price', 'boocommerce'); ?>
                                            (<?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?>)</label>
                                        <input name="service_price" type="number" step="0.01"
                                            value="<?php echo $service ? esc_attr($service->price) : '0.00'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); border-radius:6px; padding:10px; font-weight:bold;"
                                            required>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Duration', 'boocommerce'); ?>
                                            (<?php _e('m', 'boocommerce'); ?>)</label>
                                        <input name="service_duration" type="number"
                                            value="<?php echo $service ? esc_attr($service->duration) : '30'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); border-radius:6px; padding:10px;"
                                            required>
                                    </div>
                                </div>
                                <div class="bc-service-info-grid">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Buffer', 'boocommerce'); ?>
                                            (<?php _e('m', 'boocommerce'); ?>)</label>
                                        <input name="service_buffer_time" type="number"
                                            value="<?php echo $service ? esc_attr($service->buffer_time) : '0'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); border-radius:6px; padding:10px;">
                                    </div>
                                    <div>
                                        <label
                                            style="display:block; margin-bottom:5px; color:var(--bc-text-muted);"><?php _e('Capacity', 'boocommerce'); ?></label>
                                        <input name="service_capacity" type="number"
                                            value="<?php echo $service ? esc_attr($service->capacity) : '1'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--bc-border); border-radius:6px; padding:10px;"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <div
                                style="background: var(--bc-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--bc-border); border-top: 4px solid var(--bc-warning);">
                                <h3
                                    style="margin-top:0; color:var(--bc-warning); font-size:18px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-groups"></span> <?php _e('Assign Staff', 'boocommerce'); ?></h3>
                                <?php if (!empty($staff_members)): ?>
                                    <div style="max-height:150px; overflow-y:auto; padding-right:10px;">
                                        <?php foreach ($staff_members as $staff): ?>
                                            <label style="display:block; margin-bottom:8px;">
                                                <input type="checkbox" name="assigned_staff[]" value="<?php echo esc_attr($staff->id); ?>"
                                                    <?php echo in_array($staff->id, $assigned_staff_ids) ? 'checked' : ''; ?>>
                                                <?php echo esc_html($staff->name); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="description" style="color:var(--bc-warning);"><?php _e('No staff members found.', 'boocommerce'); ?></p>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="bc-btn-primary"
                                style="width:100%; margin-top:20px; padding:15px; font-size:16px;">
                                 <?php echo $action === 'edit' ? __('Update Service', 'boocommerce') : __('Publish Service', 'boocommerce'); ?>
                            </button>
                        </div>

                    </div>
                </form>
            </div>
            <?php
        } else {
            $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
            $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : (isset($_POST['s']) ? $_POST['s'] : '');
            $filter = isset($_GET['cat']) ? sanitize_text_field($_GET['cat']) : (isset($_POST['cat']) ? $_POST['cat'] : '');

            $query = "SELECT * FROM $table_services WHERE 1=1";
            if ($search)
                $query .= $wpdb->prepare(" AND name LIKE %s", '%' . $wpdb->esc_like($search) . '%');
            if ($filter)
                $query .= $wpdb->prepare(" AND category = %s", $filter);
            if ($filter_status === 'active')
                $query .= " AND status = 'active'";
            if ($filter_status === 'inactive')
                $query .= " AND status = 'inactive'";
            $query .= " ORDER BY created_at DESC";

            $services = $wpdb->get_results($query);
            $categories = $wpdb->get_col("SELECT DISTINCT category FROM $table_services WHERE category != ''");

            // Meta Card Metrics
            $total_services = $wpdb->get_var("SELECT COUNT(*) FROM {$table_services}");
            $active_services = $wpdb->get_var("SELECT COUNT(*) FROM {$table_services} WHERE status='active'");
            $inactive_services = $wpdb->get_var("SELECT COUNT(*) FROM {$table_services} WHERE status='inactive'");
            $page_url = "?page=bc_main&tab=services";
            ?>
            <div class="wrap bc-admin-wrap bc-services-list-wrapper">
                <style>
                    /* Services List Responsive Layouts */
                    .bc-services-list-header { display: flex; justify-content: space-between; align-items: center; }
                    .bc-services-meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px; margin-bottom: 20px; }
                    .bc-services-filter-form { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
                    
                    @media (max-width: 1024px) {
                        .bc-services-meta-grid { grid-template-columns: repeat(2, 1fr); }
                    }
                    
                    @media (max-width: 768px) {
                        .bc-services-list-header { flex-direction: column; align-items: flex-start; gap: 15px; }
                        .bc-services-list-header > div { width: 100%; }
                        .bc-services-list-wrapper .bc-btn-primary { width: 100%; text-align: center; display: block; margin-left: 0 !important; }
                        .bc-services-meta-grid { grid-template-columns: 1fr; }
                        .bc-services-filter-form { flex-direction: column; align-items: stretch; }
                        .bc-services-filter-form input[type="text"], .bc-services-filter-form select, .bc-services-filter-form button, .bc-services-filter-form a { width: 100%; box-sizing: border-box; }
                        .bc-services-table-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
                        .bc-modern-table { min-width: 800px; }
                    }
                </style>
                <div class="bc-services-list-header">
                    <h1 style="margin:0;"><?php _e('Services Repository', 'boocommerce'); ?></h1>
                    <div>

                        <a href="?page=bc_main&tab=services&action=add" class="bc-btn-primary"><?php _e('+ Add New Service', 'boocommerce'); ?></a>
                    </div>
                </div>

                <style>
                    .service-filter-card {
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

                    .service-filter-card:hover {
                        transform: translateY(-3px);
                        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
                    }

                    .card-active {
                        background: rgba(59, 130, 246, 0.1) !important;
                        border-left: 4px solid var(--bc-primary) !important;
                        border-color: var(--bc-primary) !important;
                    }
                </style>
                <div class="bc-services-meta-grid">
                    <a href="<?php echo $page_url; ?>&filter_status=all"
                        class="service-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--bc-text-muted);"><?php _e('Total Services', 'boocommerce'); ?></h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                            <?php echo intval($total_services); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=active"
                        class="service-filter-card <?php echo $filter_status === 'active' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--bc-success);"><?php _e('Live Offerings', 'boocommerce'); ?></h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                            <?php echo intval($active_services); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=inactive"
                        class="service-filter-card <?php echo $filter_status === 'inactive' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--bc-warning);"><?php _e('Draft / Inactive', 'boocommerce'); ?></h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--bc-text-main);">
                            <?php echo intval($inactive_services); ?></p>
                    </a>
                </div>

                <form method="get" action="" class="bc-services-filter-form">
                    <input type="hidden" name="page" value="bc_main">
                    <input type="hidden" name="tab" value="services">
                    <?php if ($filter_status !== 'all'): ?>
                        <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">
                    <?php endif; ?>
                    <input type="text" name="s" placeholder="<?php esc_attr_e('Search services...', 'boocommerce'); ?>" value="<?php echo esc_attr($search); ?>"
                        style="background:#0f172a; border:1px solid var(--bc-border); color:white; padding:8px 12px; border-radius:6px; flex-grow:1;">
                    <select name="cat"
                        style="background:#0f172a; border:1px solid var(--bc-border); color:white; padding:8px 12px; border-radius:6px;">
                        <option value=""><?php _e('All Categories', 'boocommerce'); ?></option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat); ?>" <?php selected($filter, $cat); ?>>
                                <?php echo esc_html($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bc-btn-primary" style="padding:8px 20px;"><?php _e('Filter', 'boocommerce'); ?></button>
                    <?php if ($search || $filter): ?>
                        <a href="?page=bc_main&tab=services" class="bc-btn-primary" style="background:var(--bc-danger);"><?php _e('Clear', 'boocommerce'); ?></a>
                    <?php endif; ?>
                </form>

                <div class="bc-services-table-wrapper">
                    <table class="bc-modern-table">
                    <thead>
                        <tr>
                            <th style="width:40px; color:var(--bc-primary);"><?php _e('ID', 'boocommerce'); ?></th>
                            <th style="width:60px;"><?php _e('Image', 'boocommerce'); ?></th>
                            <th><?php _e('Service Name', 'boocommerce'); ?></th>
                            <th><?php _e('Pricing & Duration', 'boocommerce'); ?></th>
                            <th><?php _e('Category', 'boocommerce'); ?></th>
                            <th align="right"><?php _e('Actions', 'boocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($services)): ?>
                            <?php foreach ($services as $service): ?>
                                <tr class="bc-clickable-row"
                                    data-href="?page=bc_main&tab=services&action=edit&id=<?php echo $service->id; ?>">
                                    <td>
                                        <code style="background:rgba(99, 102, 241, 0.1); color:var(--bc-primary); padding:2px 6px; border-radius:4px; font-weight:800;"><?php echo $service->id; ?></code>
                                    </td>
                                    <td>
                                        <?php if (!empty($service->image_url)): ?>
                                            <div
                                                style="width:40px; height:40px; border-radius:6px; background:url('<?php echo esc_url($service->image_url); ?>') center/cover; border:1px solid var(--bc-border);">
                                            </div>
                                        <?php else: ?>
                                            <div
                                                style="width:40px; height:40px; border-radius:6px; background:var(--bc-border); display:flex; align-items:center; justify-content:center; color:var(--bc-text-muted); font-size:20px;">
                                                ✂️</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="bc-customer-info">
                                            <span class="bc-customer-name"
                                                style="font-size:15px;"><?php echo esc_html($service->name); ?></span>
                                            <span class="bc-customer-meta"><?php _e('Cap:', 'boocommerce'); ?> <?php echo esc_html($service->capacity); ?> | <?php _e('Buffer:', 'boocommerce'); ?>
                                                <?php echo esc_html($service->buffer_time); ?><?php _e('m', 'boocommerce'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="bc-customer-info">
                                            <span class="bc-customer-name"><?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?><?php echo esc_html($service->price); ?></span>
                                            <span class="bc-customer-meta"><?php echo esc_html($service->duration); ?> <?php _e('minutes', 'boocommerce'); ?></span>
                                        </div>
                                    </td>
                                    <td><span
                                            style="background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:12px;"><?php echo esc_html($service->category ?: __('Uncategorized', 'boocommerce')); ?></span>
                                    </td>
                                    <td align="right">
                                        <div class="bc-row-actions">
                                            <a href="<?php echo esc_url(home_url('/booking/?bc_service_id=' . $service->id)); ?>"
                                                target="_blank"
                                                class="bc-row-action bc-action-view" title="<?php esc_attr_e('View Service', 'boocommerce'); ?>">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </a>
                                            <a href="?page=bc_main&tab=services&action=edit&id=<?php echo $service->id; ?>"
                                                class="bc-row-action bc-action-edit" title="<?php esc_attr_e('Edit Service', 'boocommerce'); ?>">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </a>
                                            <a href="?page=bc_main&tab=services&action=delete&id=<?php echo $service->id; ?>"
                                                class="bc-row-action bc-action-delete" title="<?php esc_attr_e('Delete Service', 'boocommerce'); ?>"
                                                onclick="return confirm('<?php esc_attr_e('Are you sure you want to completely delete this service?', 'boocommerce'); ?>');">
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
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding: 40px; color: var(--bc-text-muted);"><?php _e('No services match your criteria.', 'boocommerce'); ?></td>
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
