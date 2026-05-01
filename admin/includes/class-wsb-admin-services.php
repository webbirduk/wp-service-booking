<?php
class Wsb_Admin_Services {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        global $wpdb;
        $table_services = $wpdb->prefix . 'wsb_services';
        $table_staff = $wpdb->prefix . 'wsb_staff';

        // Auto-patch schema if image columns don't exist
        $wpdb->query("ALTER TABLE {$table_services} ADD COLUMN IF NOT EXISTS image_url varchar(255) DEFAULT NULL");
        $wpdb->query("ALTER TABLE {$table_services} ADD COLUMN IF NOT EXISTS gallery_urls text DEFAULT NULL");

        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        $service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if ($action === 'delete' && $service_id) {
            $wpdb->delete($table_services, array('id' => $service_id));
            echo '<div class="notice notice-success is-dismissible"><p>Service permanently deleted.</p></div>';
            $action = 'list';
        }

        // Seed Dummy Data Handler
        if ($action === 'seed' && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'seed_services')) {
            $dummy_services = array(
                array(
                    'name' => 'Signature Haircut',
                    'description' => 'Precision cut tailored to your face shape by our top stylists.',
                    'duration' => 45,
                    'price' => 50.00,
                    'buffer_time' => 15,
                    'category' => 'Hair',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Balayage Color Treatment',
                    'description' => 'Beautiful, natural-looking hand-painted highlights.',
                    'duration' => 120,
                    'price' => 180.00,
                    'buffer_time' => 30,
                    'category' => 'Color',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1512496015851-a1dc8f411906?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Deep Tissue Massage',
                    'description' => 'Intense pressure therapy to release knots and muscle tension.',
                    'duration' => 60,
                    'price' => 90.00,
                    'buffer_time' => 15,
                    'category' => 'Spa & Relax',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Bridal Makeup Session',
                    'description' => 'Full makeup session for the big day, including consultations.',
                    'duration' => 90,
                    'price' => 120.00,
                    'buffer_time' => 30,
                    'category' => 'Makeup',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Classic Manicure',
                    'description' => 'Nail shaping, cuticle care, and standard professional polish.',
                    'duration' => 30,
                    'price' => 35.00,
                    'buffer_time' => 10,
                    'category' => 'Nails',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1604654894610-df490c81ac36?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Deluxe Pedicure',
                    'description' => 'Exfoliating scrub, massage, and perfect nail lacquer.',
                    'duration' => 45,
                    'price' => 45.00,
                    'buffer_time' => 15,
                    'category' => 'Nails',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1610992015732-2449b7de358c?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Hatha Yoga Session',
                    'description' => 'Gentle physical postures and breathing techniques.',
                    'duration' => 60,
                    'price' => 25.00,
                    'buffer_time' => 15,
                    'category' => 'Wellness',
                    'capacity' => 10,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Aromatherapy Facial',
                    'description' => 'Soothing essential oils matched with deep skin cleansing.',
                    'duration' => 60,
                    'price' => 75.00,
                    'buffer_time' => 15,
                    'category' => 'Spa & Relax',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1600334089648-b0d9d3028eb2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Men\'s Beard Grooming',
                    'description' => 'Hot towel shave, beard trim, and premium oils.',
                    'duration' => 30,
                    'price' => 25.00,
                    'buffer_time' => 10,
                    'category' => 'Hair',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1620331311520-246422fd82f9?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Keratin Hair Treatment',
                    'description' => 'Smooth and de-frizz your hair for up to 12 weeks.',
                    'duration' => 150,
                    'price' => 250.00,
                    'buffer_time' => 30,
                    'category' => 'Hair',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1560869713-7d0a29430873?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Highlights & Lowlights',
                    'description' => 'Dimensional coloring for a rich, natural shine.',
                    'duration' => 90,
                    'price' => 140.00,
                    'buffer_time' => 15,
                    'category' => 'Color',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1595476108010-b4d1f102b1b1?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Hot Stone Massage',
                    'description' => 'Heated basalt stones melt away muscle tightness.',
                    'duration' => 75,
                    'price' => 110.00,
                    'buffer_time' => 15,
                    'category' => 'Spa & Relax',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1519823551278-64ac92734fb1?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Evening Glam Makeup',
                    'description' => 'Bold, contour-heavy aesthetic for evening events.',
                    'duration' => 60,
                    'price' => 80.00,
                    'buffer_time' => 15,
                    'category' => 'Makeup',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Gel Nail Extensions',
                    'description' => 'Full set of durable sculpted gel nails.',
                    'duration' => 90,
                    'price' => 65.00,
                    'buffer_time' => 15,
                    'category' => 'Nails',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1632345031435-8727f6897d53?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Guided Meditation Hour',
                    'description' => 'Mindfulness and deep relaxation training.',
                    'duration' => 60,
                    'price' => 20.00,
                    'buffer_time' => 10,
                    'category' => 'Wellness',
                    'capacity' => 15,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1599447421416-3414500d18e5?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Full Body Scrub & Glow',
                    'description' => 'Sea salt exfoliation followed by deep moisturizing.',
                    'duration' => 45,
                    'price' => 70.00,
                    'buffer_time' => 15,
                    'category' => 'Spa & Relax',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Eyebrow Shaping & Tinting',
                    'description' => 'Precision mapping and semi-permanent brow tint.',
                    'duration' => 30,
                    'price' => 30.00,
                    'buffer_time' => 10,
                    'category' => 'Makeup',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1516979187457-637abb4f9353?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Scalp Revitalization',
                    'description' => 'Exfoliating treatment to enhance natural hair growth.',
                    'duration' => 45,
                    'price' => 55.00,
                    'buffer_time' => 10,
                    'category' => 'Hair',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1582095133179-bf108e2fc6b9?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Thai Massage Therapy',
                    'description' => 'Dynamic stretching and joint pressure application.',
                    'duration' => 90,
                    'price' => 120.00,
                    'buffer_time' => 15,
                    'category' => 'Spa & Relax',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                ),
                array(
                    'name' => 'Holistic Wellness Consultation',
                    'description' => 'Comprehensive analysis of nutrition and routines.',
                    'duration' => 60,
                    'price' => 95.00,
                    'buffer_time' => 15,
                    'category' => 'Wellness',
                    'capacity' => 1,
                    'status' => 'active',
                    'image_url' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
                )
            );
            foreach ($dummy_services as $srv) {
                $wpdb->insert($table_services, $srv);
            }
            echo '<div class="notice notice-success is-dismissible"><p>Fully loaded dummy services seamlessly injected.</p></div>';
            $action = 'list';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wsb_service_nonce']) && wp_verify_nonce($_POST['wsb_service_nonce'], 'wsb_add_service')) {
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
                echo '<div class="notice notice-success is-dismissible"><p>Service updated successfully!</p></div>';
            } else {
                // Add new
                $wpdb->insert($table_services, $data);
                $service_id = $wpdb->insert_id;
                echo '<div class="notice notice-success is-dismissible"><p>Service created successfully!</p></div>';
            }

            // Sync staff
            $assigned_staff = isset($_POST['assigned_staff']) ? array_map('intval', $_POST['assigned_staff']) : [];
            $table_staff_services = $wpdb->prefix . 'wsb_staff_services';
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
                $staff_relations = $wpdb->get_results($wpdb->prepare("SELECT staff_id FROM {$wpdb->prefix}wsb_staff_services WHERE service_id = %d", $service_id));
                foreach ($staff_relations as $sr)
                    $assigned_staff_ids[] = $sr->staff_id;
            }

            ?>
            <div class="wrap wsb-admin-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h1 style="margin:0;"><?php echo $action === 'edit' ? 'Manage Service' : 'Add New Service'; ?></h1>
                    <a href="?page=wsb_main&tab=services" class="wsb-btn-primary" style="background:var(--wsb-border);">Back to
                        Services</a>
                </div>
                <hr class="wp-header-end" style="margin-bottom:20px;">

                <form method="post" action="">
                    <?php wp_nonce_field('wsb_add_service', 'wsb_service_nonce'); ?>
                    <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 20px;">

                        <!-- Main Panel -->
                        <div
                            style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--wsb-border); border-top: 4px solid var(--wsb-primary);">
                            <h3
                                style="margin-top:0; color:var(--wsb-primary); font-size:18px; display:flex; align-items:center; gap:8px;">
                                <span class="dashicons dashicons-edit"></span> Basic Information</h3>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Service Name</label>
                                <input name="service_name" type="text"
                                    value="<?php echo $service ? esc_attr($service->name) : ''; ?>"
                                    style="width:100%; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; background:#0f172a; color:white; font-size:16px;"
                                    required>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Category</label>
                                <input name="service_category" type="text"
                                    value="<?php echo $service ? esc_attr($service->category) : ''; ?>"
                                    style="width:100%; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; background:#0f172a; color:white;">
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Description</label>
                                <textarea name="service_description" rows="4"
                                    style="width:100%; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; background:#0f172a; color:white;"><?php echo $service ? esc_textarea($service->description) : ''; ?></textarea>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Featured Image</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <div id="wsb-featured-preview"
                                        style="width:60px; height:60px; border-radius:6px; border:1px dashed var(--wsb-border); background:#0f172a <?php echo $service && $service->image_url ? 'url(' . esc_url($service->image_url) . ') center/cover' : ''; ?>;">
                                    </div>
                                    <input type="hidden" name="service_image_url" id="service_image_url"
                                        value="<?php echo $service ? esc_url($service->image_url) : ''; ?>">
                                    <button type="button" class="wsb-btn-primary wsb-select-image" data-target="#service_image_url"
                                        data-preview="#wsb-featured-preview"
                                        style="background:var(--wsb-border); color:white;">Select Image</button>
                                </div>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Service Gallery
                                    (Multiple Images)</label>
                                <input type="hidden" name="service_gallery_urls" id="service_gallery_urls"
                                    value="<?php echo $service && isset($service->gallery_urls) ? esc_attr($service->gallery_urls) : ''; ?>">
                                <div id="wsb-gallery-preview" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:10px;">
                                    <?php
                                    if ($service && !empty($service->gallery_urls)) {
                                        $urls = explode(',', $service->gallery_urls);
                                        foreach ($urls as $url) {
                                            echo '<div style="width:50px; height:50px; border-radius:4px; background:url(' . esc_url($url) . ') center/cover; border:1px solid #334155;"></div>';
                                        }
                                    }
                                    ?>
                                </div>
                                <button type="button" class="wsb-btn-primary wsb-select-gallery"
                                    style="background:var(--wsb-border); color:white;">Select Gallery Images</button>
                            </div>
                        </div>

                        <!-- Side Panel -->
                        <div>
                            <div
                                style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--wsb-border); margin-bottom: 20px; border-top: 4px solid var(--wsb-success);">
                                <h3
                                    style="margin-top:0; color:var(--wsb-success); font-size:18px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-money-alt"></span> Pricing & Duration</h3>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom: 15px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Price
                                            ($)</label>
                                        <input name="service_price" type="number" step="0.01"
                                            value="<?php echo $service ? esc_attr($service->price) : '0.00'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px; font-weight:bold;"
                                            required>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Duration
                                            (m)</label>
                                        <input name="service_duration" type="number"
                                            value="<?php echo $service ? esc_attr($service->duration) : '30'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px;"
                                            required>
                                    </div>
                                </div>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                    <div>
                                        <label style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Buffer
                                            (m)</label>
                                        <input name="service_buffer_time" type="number"
                                            value="<?php echo $service ? esc_attr($service->buffer_time) : '0'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px;">
                                    </div>
                                    <div>
                                        <label
                                            style="display:block; margin-bottom:5px; color:var(--wsb-text-muted);">Capacity</label>
                                        <input name="service_capacity" type="number"
                                            value="<?php echo $service ? esc_attr($service->capacity) : '1'; ?>"
                                            style="width:100%; background:#0f172a; color:white; border:1px solid var(--wsb-border); border-radius:6px; padding:10px;"
                                            required>
                                    </div>
                                </div>
                            </div>

                            <div
                                style="background: var(--wsb-panel-dark); padding: 20px; border-radius: 12px; border: 1px solid var(--wsb-border); border-top: 4px solid var(--wsb-warning);">
                                <h3
                                    style="margin-top:0; color:var(--wsb-warning); font-size:18px; display:flex; align-items:center; gap:8px;">
                                    <span class="dashicons dashicons-groups"></span> Assign Staff</h3>
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
                                    <p class="description" style="color:var(--wsb-warning);">No staff members found.</p>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="wsb-btn-primary"
                                style="width:100%; margin-top:20px; padding:15px; font-size:16px;">
                                <?php echo $action === 'edit' ? 'Update Service' : 'Publish Service'; ?>
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
            $page_url = "?page=wsb_main&tab=services";
            ?>
            <div class="wrap wsb-admin-wrap">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h1 style="margin:0;">Services Repository</h1>
                    <div>
                        <a href="<?php echo wp_nonce_url("?page=wsb_main&tab=services&action=seed", 'seed_services'); ?>"
                            class="wsb-btn-primary" style="background:var(--wsb-warning); margin-right:10px;">⚡ Inject Dummy
                            Services</a>
                        <a href="?page=wsb_main&tab=services&action=add" class="wsb-btn-primary">+ Add New Service</a>
                    </div>
                </div>

                <style>
                    .service-filter-card {
                        border-left: 4px solid transparent;
                        text-decoration: none;
                        color: inherit;
                        display: block;
                        border: 1px solid var(--wsb-border);
                        border-radius: 12px;
                        background: var(--wsb-panel-dark);
                        padding: 20px;
                        transition: transform 0.2s;
                    }

                    .service-filter-card:hover {
                        transform: translateY(-3px);
                        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
                    }

                    .card-active {
                        background: rgba(59, 130, 246, 0.1) !important;
                        border-left: 4px solid var(--wsb-primary) !important;
                        border-color: var(--wsb-primary) !important;
                    }
                </style>
                <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top:20px; margin-bottom:20px;">
                    <a href="<?php echo $page_url; ?>&filter_status=all"
                        class="service-filter-card <?php echo $filter_status === 'all' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-text-muted);">Total Services</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                            <?php echo intval($total_services); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=active"
                        class="service-filter-card <?php echo $filter_status === 'active' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-success);">Live Offerings</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                            <?php echo intval($active_services); ?></p>
                    </a>
                    <a href="<?php echo $page_url; ?>&filter_status=inactive"
                        class="service-filter-card <?php echo $filter_status === 'inactive' ? 'card-active' : ''; ?>">
                        <h3 style="margin-top:0; font-size:15px; color:var(--wsb-warning);">Draft / Inactive</h3>
                        <p style="margin:0; font-size:28px; font-weight:bold; color:var(--wsb-text-main);">
                            <?php echo intval($inactive_services); ?></p>
                    </a>
                </div>

                <form method="get" action="" style="display:flex; gap:10px; margin-bottom:20px;">
                    <input type="hidden" name="page" value="wsb_main">
                    <input type="hidden" name="tab" value="services">
                    <?php if ($filter_status !== 'all'): ?>
                        <input type="hidden" name="filter_status" value="<?php echo esc_attr($filter_status); ?>">
                    <?php endif; ?>
                    <input type="text" name="s" placeholder="Search services..." value="<?php echo esc_attr($search); ?>"
                        style="background:#0f172a; border:1px solid var(--wsb-border); color:white; padding:8px 12px; border-radius:6px; flex-grow:1;">
                    <select name="cat"
                        style="background:#0f172a; border:1px solid var(--wsb-border); color:white; padding:8px 12px; border-radius:6px;">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat); ?>" <?php selected($filter, $cat); ?>>
                                <?php echo esc_html($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="wsb-btn-primary" style="padding:8px 20px;">Filter</button>
                    <?php if ($search || $filter): ?>
                        <a href="?page=wsb_main&tab=services" class="wsb-btn-primary" style="background:var(--wsb-danger);">Clear</a>
                    <?php endif; ?>
                </form>

                <table class="wsb-modern-table">
                    <thead>
                        <tr>
                            <th style="width:60px;">Image</th>
                            <th>Service Name</th>
                            <th>Pricing & Duration</th>
                            <th>Category</th>
                            <th align="right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($services)): ?>
                            <?php foreach ($services as $service): ?>
                                <tr class="wsb-clickable-row"
                                    data-href="?page=wsb_main&tab=services&action=edit&id=<?php echo $service->id; ?>">
                                    <td>
                                        <?php if (!empty($service->image_url)): ?>
                                            <div
                                                style="width:40px; height:40px; border-radius:6px; background:url('<?php echo esc_url($service->image_url); ?>') center/cover; border:1px solid var(--wsb-border);">
                                            </div>
                                        <?php else: ?>
                                            <div
                                                style="width:40px; height:40px; border-radius:6px; background:var(--wsb-border); display:flex; align-items:center; justify-content:center; color:var(--wsb-text-muted); font-size:20px;">
                                                ✂️</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span class="wsb-customer-name"
                                                style="font-size:15px;"><?php echo esc_html($service->name); ?></span>
                                            <span class="wsb-customer-meta">Cap: <?php echo esc_html($service->capacity); ?> | Buffer:
                                                <?php echo esc_html($service->buffer_time); ?>m</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wsb-customer-info">
                                            <span class="wsb-customer-name"><?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo esc_html($service->price); ?></span>
                                            <span class="wsb-customer-meta"><?php echo esc_html($service->duration); ?> minutes</span>
                                        </div>
                                    </td>
                                    <td><span
                                            style="background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:12px;"><?php echo esc_html($service->category ?: 'Uncategorized'); ?></span>
                                    </td>
                                    <td align="right">
                                        <div class="wsb-row-actions">
                                            <a href="?page=wsb_main&tab=services&action=edit&id=<?php echo $service->id; ?>"
                                                class="wsb-row-action wsb-action-edit" title="Edit Service">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                </svg>
                                            </a>
                                            <a href="?page=wsb_main&tab=services&action=delete&id=<?php echo $service->id; ?>"
                                                class="wsb-row-action wsb-action-delete" title="Delete Service"
                                                onclick="return confirm('Are you sure you want to completely delete this service?');">
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
                                <td colspan="5" style="text-align:center; padding: 40px; color: var(--wsb-text-muted);">No services
                                    match your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    }
}
