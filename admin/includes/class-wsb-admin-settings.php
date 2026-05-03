<?php
class Wsb_Admin_Settings {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['wsb_settings_nonce']) && wp_verify_nonce($_POST['wsb_settings_nonce'], 'wsb_save_settings')) {
                do_action('wsb_before_save_settings', $_POST);
                update_option('wsb_currency', sanitize_text_field($_POST['wsb_currency']));

                // Flow Control
                update_option('wsb_skip_professional_step', isset($_POST['wsb_skip_professional_step']) ? 'yes' : 'no');
                update_option('wsb_skip_payment_step', isset($_POST['wsb_skip_payment_step']) ? 'yes' : 'no');

                do_action('wsb_after_save_settings', $_POST);
                echo '<div class="notice notice-success is-dismissible"><p>System Integration Settings securely saved!</p></div>';
            }

            if (isset($_POST['wsb_dummy_nonce']) && wp_verify_nonce($_POST['wsb_dummy_nonce'], 'wsb_generate_dummy')) {
                $this->generate_dummy_data($wpdb);
                echo '<div class="notice notice-success is-dismissible"><p>Successfully injected comprehensive dummy data across all tables!</p></div>';
            }
        }

        $currency = get_option('wsb_currency', 'USD');
        $stripe_pk = get_option('wsb_stripe_publishable_key', '');
        $stripe_sk = get_option('wsb_stripe_secret_key', '');
        
        $skip_prof = get_option('wsb_skip_professional_step', 'no');
        $skip_pay = get_option('wsb_skip_payment_step', 'no');
        ?>
        <div class="wrap wsb-admin-wrap">
            <div style="margin-bottom:30px;">
                <h1 style="margin:0; font-size:28px; font-weight:800; color:#fff;">System Settings & Integrations</h1>
                <p style="color:var(--wsb-text-muted); margin-top:5px; font-size:15px;">Configure your global booking architecture, payment gateways, and design language.</p>
            </div>

            <form method="post">
                <?php wp_nonce_field('wsb_save_settings', 'wsb_settings_nonce'); ?>
                
                <div style="display:grid; grid-template-columns: 2fr 1.2fr; gap:30px; align-items: start;">
                    
                    <!-- Left Column: Core Configuration -->
                    <div style="display:flex; flex-direction:column; gap:30px;">
                        
                        <!-- General Settings Card -->
                        <div style="background:var(--wsb-panel-dark); border-radius:16px; border:1px solid var(--wsb-border); overflow:hidden; border-top:4px solid #6366f1;">
                            <div style="padding:25px; border-bottom:1px solid var(--wsb-border);">
                                <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-admin-settings" style="color:#6366f1;"></span> General Settings
                                </h3>
                            </div>
                            <div style="padding:25px; display:flex; flex-direction:column; gap:30px;">
                                
                                <style>
                                    .wsb-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
                                    .wsb-switch input { opacity: 0; width: 0; height: 0; }
                                    .wsb-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #1e293b; transition: .4s; border-radius: 34px; border: 1px solid #334155; }
                                    .wsb-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 3px; background-color: #94a3b8; transition: .4s; border-radius: 50%; }
                                    input:checked + .wsb-slider { background-color: var(--wsb-primary); border-color: var(--wsb-primary); }
                                    input:checked + .wsb-slider:before { transform: translateX(24px); background-color: #fff; }
                                </style>

                                <!-- Integrated Settings Section -->
                                <div>
                                    <div style="display:flex; flex-direction:column; gap:15px;">
                                        <!-- System Default Currency (Integrated) -->
                                        <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.02); padding:20px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">System Default Currency</label>
                                                <span style="color:var(--wsb-text-muted); font-size:12px; line-height:1.4; display:block; max-width:400px;">Select the primary currency for all bookings and financial reports.</span>
                                            </div>
                                            <div style="width:200px;">
                                                <select name="wsb_currency" style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:10px; border-radius:8px; font-weight:600; font-size:13px;">
                                                    <option value="USD" <?php selected($currency, 'USD'); ?>>USD ($)</option>
                                                    <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR (€)</option>
                                                    <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP (£)</option>
                                                    <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD (C$)</option>
                                                    <option value="AUD" <?php selected($currency, 'AUD'); ?>>AUD (A$)</option>
                                                    <option value="JPY" <?php selected($currency, 'JPY'); ?>>JPY (¥)</option>
                                                    <option value="INR" <?php selected($currency, 'INR'); ?>>INR (₹)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Toggle 1: Skip Professional -->
                                        <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.02); padding:20px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">Skip Professional Selection</label>
                                                <span style="color:var(--wsb-text-muted); font-size:12px; line-height:1.4; display:block; max-width:400px;">Automatically bypass the professional team step. Recommended if you only have one provider.</span>
                                            </div>
                                            <label class="wsb-switch">
                                                <input type="checkbox" name="wsb_skip_professional_step" value="yes" <?php checked($skip_prof, 'yes'); ?>>
                                                <span class="wsb-slider"></span>
                                            </label>
                                        </div>

                                        <!-- Toggle 2: Disable Payment -->
                                        <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.02); padding:20px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">Disable Online Payments</label>
                                                <span style="color:var(--wsb-text-muted); font-size:12px; line-height:1.4; display:block; max-width:400px;">Bypass all payment selection and checkout screens. Bookings will be confirmed instantly.</span>
                                            </div>
                                            <label class="wsb-switch">
                                                <input type="checkbox" name="wsb_skip_payment_step" value="yes" <?php checked($skip_pay, 'yes'); ?>>
                                                <span class="wsb-slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Integration & Tools -->
                    <div style="display:flex; flex-direction:column; gap:30px;">
                        


                        <!-- Save Actions -->
                        <div style="background:var(--wsb-panel-dark); padding:25px; border-radius:16px; border:1px solid var(--wsb-border); display:flex; flex-direction:column; gap:15px;">
                            <button type="submit" class="wsb-btn-primary" style="width:100%; padding:15px; font-size:16px; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);">Save Global Architecture</button>
                        </div>

                        <!-- Maintenance / Danger Zone -->
                        <div style="background:rgba(239, 68, 68, 0.02); border-radius:16px; border:1px solid rgba(239, 68, 68, 0.2); overflow:hidden;">
                            <div style="padding:20px; border-bottom:1px solid rgba(239, 68, 68, 0.1); background:rgba(239, 68, 68, 0.05);">
                                <h4 style="margin:0; color:#ef4444; display:flex; align-items:center; gap:8px; font-size:14px; text-transform:uppercase; letter-spacing:0.05em;">
                                    <span class="dashicons dashicons-warning"></span> Advanced Maintenance
                                </h4>
                            </div>
                            <div style="padding:20px;">
                                <p style="color:rgba(239, 68, 68, 0.7); font-size:12px; margin-bottom:15px; line-height:1.5;">Force inject comprehensive dummy data for testing purposes.</p>
                                <form method="post">
                                    <?php wp_nonce_field('wsb_generate_dummy', 'wsb_dummy_nonce'); ?>
                                    <button type="submit" name="generate_dummy" class="wsb-btn-primary" 
                                        style="width:100%; background:transparent; border:1px solid rgba(239, 68, 68, 0.3); color:#ef4444; padding:10px;"
                                        onclick="return confirm('CRITICAL: This will inject dummy data into your live database. Proceed?');">
                                        Inject Dummy Ecosystem
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    private function generate_dummy_data($wpdb) {
        $table_services = $wpdb->prefix . 'wsb_services';
        $table_staff = $wpdb->prefix . 'wsb_staff';
        $table_customers = $wpdb->prefix . 'wsb_customers';
        $table_bookings = $wpdb->prefix . 'wsb_bookings';
        $table_payments = $wpdb->prefix . 'wsb_payments';
        $table_staff_services = $wpdb->prefix . 'wsb_staff_services';

        // 1. Clear existing (optional but recommended for clean dummy state)
        // $wpdb->query("TRUNCATE TABLE $table_payments");
        // $wpdb->query("TRUNCATE TABLE $table_bookings");
        // $wpdb->query("TRUNCATE TABLE $table_staff_services");

        // 2. Insert High-Quality Services
        $dummy_services = array(
            array('name' => 'Signature Haircut', 'description' => 'Precision cut tailored to your face shape.', 'duration' => 45, 'price' => 50.00, 'category' => 'Hair', 'capacity' => 1, 'status' => 'active', 'image_url' => 'https://images.unsplash.com/photo-1562322140-8baeececf3df?auto=format&fit=crop&w=400&q=80'),
            array('name' => 'Balayage Color', 'description' => 'Natural hand-painted highlights.', 'duration' => 120, 'price' => 180.00, 'category' => 'Color', 'capacity' => 1, 'status' => 'active', 'image_url' => 'https://images.unsplash.com/photo-1512496015851-a1dc8f411906?auto=format&fit=crop&w=400&q=80'),
            array('name' => 'Deep Tissue Massage', 'description' => 'Intense therapy for muscle tension.', 'duration' => 60, 'price' => 95.00, 'category' => 'Spa', 'capacity' => 1, 'status' => 'active', 'image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&w=400&q=80'),
            array('name' => 'Bridal Makeup', 'description' => 'Full glam for the big day.', 'duration' => 90, 'price' => 120.00, 'category' => 'Makeup', 'capacity' => 1, 'status' => 'active', 'image_url' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?auto=format&fit=crop&w=400&q=80')
        );
        $service_ids = [];
        foreach ($dummy_services as $s) {
            $wpdb->insert($table_services, $s);
            $service_ids[] = $wpdb->insert_id;
        }

        // 3. Insert Professional Staff
        $dummy_staff = array(
            array('name' => 'Alexander Pierce', 'email' => 'alex@example.com', 'phone' => '555-0102', 'status' => 'active', 'qualification' => 'Master Barber', 'image_url' => 'https://ui-avatars.com/api/?name=Alexander+Pierce&background=0D8ABC&color=fff&size=100'),
            array('name' => 'Sophia Lauren', 'email' => 'sophia@example.com', 'phone' => '555-0199', 'status' => 'active', 'qualification' => 'Senior Colorist', 'image_url' => 'https://ui-avatars.com/api/?name=Sophia+Lauren&background=D81B60&color=fff&size=100'),
            array('name' => 'Marcus Reed', 'email' => 'marcus@example.com', 'phone' => '555-0211', 'status' => 'active', 'qualification' => 'Therapist', 'image_url' => 'https://ui-avatars.com/api/?name=Marcus+Reed&background=43A047&color=fff&size=100')
        );
        $staff_ids = [];
        foreach ($dummy_staff as $st) {
            $wpdb->insert($table_staff, $st);
            $staff_ids[] = $wpdb->insert_id;
        }

        // 4. Map Staff to Services
        if (!empty($staff_ids) && !empty($service_ids)) {
            $wpdb->insert($table_staff_services, array('staff_id' => $staff_ids[0], 'service_id' => $service_ids[0]));
            $wpdb->insert($table_staff_services, array('staff_id' => $staff_ids[1], 'service_id' => $service_ids[1]));
            $wpdb->insert($table_staff_services, array('staff_id' => $staff_ids[2], 'service_id' => $service_ids[2]));
        }

        // 5. Insert VIP Customers
        $dummy_customers = array(
            array('first_name' => 'Emily', 'last_name' => 'Blunt', 'email' => 'emily@example.com', 'phone' => '555-123-4567'),
            array('first_name' => 'John', 'last_name' => 'Krasinski', 'email' => 'john@example.com', 'phone' => '555-987-6543'),
            array('first_name' => 'Margot', 'last_name' => 'Robbie', 'email' => 'margot@example.com', 'phone' => '555-222-3333'),
            array('first_name' => 'Ryan', 'last_name' => 'Gosling', 'email' => 'ryan@example.com', 'phone' => '555-444-5555')
        );
        $customer_ids = [];
        foreach ($dummy_customers as $c) {
            $wpdb->insert($table_customers, $c);
            $customer_ids[] = $wpdb->insert_id;
        }

        // 6. Generate Realistic Bookings & Payments
        $statuses = array('confirmed', 'pending', 'completed');
        for ($i = 0; $i < 30; $i++) {
            $cid = $customer_ids[array_rand($customer_ids)];
            $sid = $service_ids[array_rand($service_ids)];
            $stid = $staff_ids[array_rand($staff_ids)];

            $random_days = rand(-14, 14);
            $booking_date = date('Y-m-d', strtotime("+$random_days days"));
            $hour = rand(9, 16);
            $status = $statuses[array_rand($statuses)];
            $amount = rand(45, 180) . '.00';

            $wpdb->insert($table_bookings, array(
                'customer_id' => $cid,
                'service_id' => $sid,
                'staff_id' => $stid,
                'booking_date' => $booking_date,
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'status' => $status,
                'total_amount' => $amount,
                'created_at' => date('Y-m-d H:i:s', strtotime("-".rand(1, 30)." days"))
            ));
            $booking_id = $wpdb->insert_id;

            $wpdb->insert($table_payments, array(
                'booking_id' => $booking_id,
                'amount' => $amount,
                'gateway' => 'stripe',
                'transaction_id' => 'ch_test_' . wp_generate_password(12, false),
                'status' => ($status === 'pending') ? 'pending' : 'completed',
                'created_at' => date('Y-m-d H:i:s', strtotime("-".rand(1, 30)." days"))
            ));
        }
    }
}
