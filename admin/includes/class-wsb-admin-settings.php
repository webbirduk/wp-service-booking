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
                update_option('wsb_currency', sanitize_text_field($_POST['wsb_currency']));

                // Payment Integrations
                update_option('wsb_stripe_publishable_key', sanitize_text_field($_POST['wsb_stripe_publishable_key']));
                update_option('wsb_stripe_secret_key', sanitize_text_field($_POST['wsb_stripe_secret_key']));

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
                        
                        <!-- Payment Ecosystem Card -->
                        <div style="background:var(--wsb-panel-dark); border-radius:16px; border:1px solid var(--wsb-border); overflow:hidden; border-top:4px solid var(--wsb-primary);">
                            <div style="padding:25px; border-bottom:1px solid var(--wsb-border); display:flex; align-items:center; justify-content:space-between;">
                                <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-money-alt" style="color:var(--wsb-primary);"></span> Payment Gateway Ecosystem
                                </h3>
                                <span style="background:rgba(99, 102, 241, 0.1); color:var(--wsb-primary); padding:4px 12px; border-radius:20px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">Stripe Integration</span>
                            </div>
                            
                            <div style="padding:25px; display:flex; flex-direction:column; gap:30px;">
                                
                                <!-- Stripe Section -->
                                <div>
                                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                                        <div style="width:32px; height:32px; background:#635bff; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:900; font-size:18px;">S</div>
                                        <h4 style="margin:0; color:#fff; font-size:16px;">Stripe Professional</h4>
                                    </div>
                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted); font-size:13px;">Publishable API Key</label>
                                            <input name="wsb_stripe_publishable_key" type="text" value="<?php echo esc_attr($stripe_pk); ?>" placeholder="pk_test_..."
                                                style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:12px; border-radius:8px;">
                                        </div>
                                        <div>
                                            <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted); font-size:13px;">Secret API Key</label>
                                            <input name="wsb_stripe_secret_key" id="wsb_stripe_secret_key" type="password" value="<?php echo esc_attr($stripe_sk); ?>" placeholder="sk_test_..."
                                                style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:12px; border-radius:8px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- General & Regional Configuration -->
                        <div style="background:var(--wsb-panel-dark); border-radius:16px; border:1px solid var(--wsb-border); overflow:hidden;">
                            <div style="padding:25px; border-bottom:1px solid var(--wsb-border);">
                                <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-admin-settings" style="color:var(--wsb-warning);"></span> Regional & Locale Settings
                                </h3>
                            </div>
                            <div style="padding:25px;">
                                <div style="max-width:400px;">
                                    <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted); font-size:13px;">System Default Currency</label>
                                    <select name="wsb_currency" style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:12px; border-radius:8px; font-weight:600;">
                                        <option value="USD" <?php selected($currency, 'USD'); ?>>USD - United States Dollar ($)</option>
                                        <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR - Euro (€)</option>
                                        <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP - British Pound (£)</option>
                                        <option value="CAD" <?php selected($currency, 'CAD'); ?>>CAD - Canadian Dollar (C$)</option>
                                        <option value="AUD" <?php selected($currency, 'AUD'); ?>>AUD - Australian Dollar (A$)</option>
                                        <option value="JPY" <?php selected($currency, 'JPY'); ?>>JPY - Japanese Yen (¥)</option>
                                        <option value="INR" <?php selected($currency, 'INR'); ?>>INR - Indian Rupee (₹)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Integration & Tools -->
                    <div style="display:flex; flex-direction:column; gap:30px;">
                        
                        <!-- Shortcode Generator Card -->
                        <div style="background:var(--wsb-panel-dark); border-radius:16px; border:1px solid var(--wsb-border); overflow:hidden; border-top:4px solid var(--wsb-success);">
                            <div style="padding:25px; border-bottom:1px solid var(--wsb-border);">
                                <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-shortcode" style="color:var(--wsb-success);"></span> Frontend Deployment
                                </h3>
                            </div>
                            <div style="padding:25px;">
                                <p style="color:var(--wsb-text-muted); margin-bottom:20px; font-size:13px; line-height:1.6;">Paste this shortcode anywhere on your site to render the premium booking widget.</p>
                                <div style="background:rgba(16, 185, 129, 0.05); border:1px dashed var(--wsb-success); padding:15px; border-radius:10px; text-align:center; margin-bottom:20px;">
                                    <code style="font-size:20px; color:var(--wsb-success); font-weight:900; letter-spacing:1px;">[wsb_booking_widget]</code>
                                </div>
                                
                                <label style="display:block; margin-bottom:8px; color:var(--wsb-text-muted); font-size:13px;">Direct System Link</label>
                                <div style="position:relative;">
                                    <input type="text" readonly value="<?php echo site_url('/booking'); ?>" onclick="this.select();"
                                        style="width:100%; background:#0f172a; color:var(--wsb-primary); border:1px solid var(--wsb-border); padding:10px 12px; border-radius:8px; font-size:12px; cursor:pointer;">
                                </div>
                            </div>
                        </div>

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
        $tables = array(
            'services' => $wpdb->prefix . 'wsb_services',
            'staff' => $wpdb->prefix . 'wsb_staff',
            'customers' => $wpdb->prefix . 'wsb_customers',
            'bookings' => $wpdb->prefix . 'wsb_bookings',
            'payments' => $wpdb->prefix . 'wsb_payments',
            'staff_services' => $wpdb->prefix . 'wsb_staff_services'
        );

        // 1. Insert Services
        $services_data = array(
            array('name' => 'Premium Haircut', 'description' => 'A full premium styling session.', 'price' => 45.00, 'duration' => 45, 'category' => 'Hair', 'capacity' => 1),
            array('name' => 'Beard Trim & Grooming', 'description' => 'Beard shaping and hot towel.', 'price' => 25.00, 'duration' => 30, 'category' => 'Hair', 'capacity' => 1),
            array('name' => 'Deep Tissue Massage', 'description' => 'Recovery therapy massage.', 'price' => 90.00, 'duration' => 60, 'category' => 'Spa', 'capacity' => 1),
            array('name' => 'Fitness Consultation', 'description' => '1-on-1 private training assessment.', 'price' => 60.00, 'duration' => 60, 'category' => 'Fitness', 'capacity' => 1),
        );
        $service_ids = array();
        foreach ($services_data as $s) {
            $wpdb->insert($tables['services'], $s);
            $service_ids[] = $wpdb->insert_id;
        }

        // 2. Insert Staff
        $staff_data = array(
            array('name' => 'Alex Turner', 'email' => 'alex@example.com', 'phone' => '555-0101'),
            array('name' => 'Sarah Jenkins', 'email' => 'sarah@example.com', 'phone' => '555-0102'),
            array('name' => 'Michael Chen', 'email' => 'michael@example.com', 'phone' => '555-0103'),
        );
        $staff_ids = array();
        foreach ($staff_data as $st) {
            $wpdb->insert($tables['staff'], $st);
            $staff_ids[] = $wpdb->insert_id;
        }

        // Assign Staff to Services
        if (!empty($staff_ids) && !empty($service_ids)) {
            $wpdb->insert($tables['staff_services'], array('staff_id' => $staff_ids[0], 'service_id' => $service_ids[0]));
            $wpdb->insert($tables['staff_services'], array('staff_id' => $staff_ids[0], 'service_id' => $service_ids[1]));
            $wpdb->insert($tables['staff_services'], array('staff_id' => $staff_ids[1], 'service_id' => $service_ids[2]));
            $wpdb->insert($tables['staff_services'], array('staff_id' => $staff_ids[2], 'service_id' => $service_ids[3]));
        }

        // 3. Insert Customers
        $customer_data = array(
            array('first_name' => 'John', 'last_name' => 'Doe', 'email' => 'johndoe@test.com', 'phone' => '123-456-7890'),
            array('first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'janes@test.com', 'phone' => '987-654-3210'),
            array('first_name' => 'Emily', 'last_name' => 'Davis', 'email' => 'emilyd@test.com', 'phone' => '555-111-2222'),
            array('first_name' => 'Chris', 'last_name' => 'Wilson', 'email' => 'chrisw@test.com', 'phone' => '555-333-4444'),
        );
        $customer_ids = array();
        foreach ($customer_data as $c) {
            $wpdb->insert($tables['customers'], $c);
            $customer_ids[] = $wpdb->insert_id;
        }

        // 4. Insert Bookings & Payments
        $statuses = array('confirmed', 'pending', 'completed');
        for ($i = 0; $i < 15; $i++) {
            $cid = $customer_ids[array_rand($customer_ids)];
            $sid = $service_ids[array_rand($service_ids)];
            $stid = $staff_ids[array_rand($staff_ids)];

            $random_days = rand(0, 14);
            $booking_date = date('Y-m-d', strtotime("+$random_days days"));
            $hour = rand(9, 16);
            $start_time = sprintf('%02d:00:00', $hour);
            $end_time = sprintf('%02d:00:00', $hour + 1);
            $status = $statuses[array_rand($statuses)];
            $amount = rand(25, 120) . '.00';

            $wpdb->insert($tables['bookings'], array(
                'customer_id' => $cid,
                'service_id' => $sid,
                'staff_id' => $stid,
                'booking_date' => $booking_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'status' => $status,
                'total_amount' => $amount
            ));
            $booking_id = $wpdb->insert_id;

            $wpdb->insert($tables['payments'], array(
                'booking_id' => $booking_id,
                'amount' => $amount,
                'gateway' => 'stripe',
                'transaction_id' => 'ch_test_' . rand(1000, 9999),
                'status' => ($status === 'pending') ? 'pending' : 'completed'
            ));
        }
    }
}
