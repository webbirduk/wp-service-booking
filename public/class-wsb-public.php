<?php
class Wsb_Public {
    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( dirname(__FILE__) ) . 'assets/public/css/wsb-public.css', array(), time(), 'all' );
    }

    public function handle_stripe_return() {
        if (!isset($_GET['wsb_checkout']) || $_GET['wsb_checkout'] !== 'success') {
            return;
        }

        global $wpdb;
        $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
        $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';

        if (!$booking_id || !$session_id) {
            return;
        }

        $booking_table = $wpdb->prefix . 'wsb_bookings';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $booking_table WHERE id = %d AND status = 'pending'", $booking_id));

        if ($booking) {
            // Confirm the booking
            $wpdb->update($booking_table, array('status' => 'confirmed'), array('id' => $booking_id));

            // Create payment record
            $payment_table = $wpdb->prefix . 'wsb_payments';
            $wpdb->insert($payment_table, array(
                'booking_id' => $booking_id,
                'amount' => $booking->total_amount,
                'gateway' => 'stripe',
                'status' => 'completed',
                'transaction_id' => $session_id
            ));

            // Send confirmation email
            $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsb_customers WHERE id = %d", $booking->customer_id));
            if ($customer) {
                // Log the user in automatically if they have an account
                $user = get_user_by('email', $customer->email);
                if ($user) {
                    wp_clear_auth_cookie();
                    wp_set_current_user($user->ID);
                    wp_set_auth_cookie($user->ID);
                }

                $mail_subject = 'Booking Confirmed - Appointment #' . $booking_id;
                $mail_body = "Hello " . esc_html($customer->first_name) . ",\n\n";
                $mail_body .= "Your payment was successful and your booking is now confirmed!\n\n";
                $mail_body .= "Booking ID: #" . $booking_id . "\n";
                $mail_body .= "Date: " . $booking->booking_date . "\n";
                $mail_body .= "Time: " . $booking->start_time . "\n\n";
                $mail_body .= "Thank you for choosing our services.";
                wp_mail($customer->email, $mail_subject, $mail_body);
            }

            // Redirect to dashboard with success message
            // Try to find the dashboard page dynamically
            global $wpdb;
            $page_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[wsb_client_dashboard]%' AND post_status = 'publish' LIMIT 1");
            $dash_url = $page_id ? get_permalink($page_id) : home_url('/booking-dashboard');

            wp_redirect(add_query_arg('wsb_payment_confirmed', '1', $dash_url));
            exit;
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, false);
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( dirname(__FILE__) ) . 'assets/public/js/wsb-public.js', array( 'jquery', 'stripe-js' ), time(), true );
        wp_localize_script( $this->plugin_name, 'wsb_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wsb_nonce' ),
            'login_url'=> wp_login_url(),
            'dashboard_url' => home_url('/booking-dashboard'),
            'stripe_pk' => get_option('wsb_stripe_publishable_key', '')
        ));
    }

    public function render_booking_widget( $atts ) {
        global $wpdb;
        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wsb_services WHERE status = 'active'");
        $staff = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wsb_staff WHERE status = 'active'");
        
        ob_start();
        $brand_color = get_option('wsb_brand_color', '#6366f1');
        $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
        $accent_color = get_option('wsb_accent_color', '#4f46e5');
        
        // Extract unique categories for filter
        $categories = array();
        if(!empty($services)) {
            foreach($services as $s) {
                if(!empty($s->category)) {
                    $categories[] = $s->category;
                }
            }
        }
        $categories = array_unique($categories);

        $current_user = wp_get_current_user();
        $user_first_name = $current_user ? $current_user->user_firstname : '';
        $user_last_name = $current_user ? $current_user->user_lastname : '';
        $user_email = $current_user ? $current_user->user_email : '';
        $user_phone = '';
        
        if ($current_user && $current_user->ID) {
            $user_phone = get_user_meta($current_user->ID, 'phone', true);
            if (empty($user_phone)) {
                $user_phone = get_user_meta($current_user->ID, 'billing_phone', true);
            }
            if (empty($user_phone)) {
                $user_phone = get_user_meta($current_user->ID, 'user_phone', true);
            }
        }

        if (empty($user_first_name) && !empty($current_user->display_name)) {
            $name_parts = explode(' ', $current_user->display_name);
            $user_first_name = isset($name_parts[0]) ? $name_parts[0] : '';
            $user_last_name = isset($name_parts[1]) ? implode(' ', array_slice($name_parts, 1)) : '';
        }
        ?>
        <style>
            :root {
                --wsb-brand: <?php echo esc_attr($brand_color); ?>;
                --wsb-brand-alt: <?php echo esc_attr($accent_color); ?>;
                --wsb-gradient: linear-gradient(135deg, <?php echo esc_attr($brand_color); ?> 0%, <?php echo esc_attr($brand_color_end); ?> 100%);
                --wsb-ring: <?php echo esc_attr($brand_color); ?>33;
            }
        </style>
        <div id="wsb-booking-wizard-container" class="wsb-wrapper">
            <div class="wsb-wizard-step" id="wsb-step-service">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 25px;">
                    <h3 style="margin:0;">1. Select a Service</h3>
                    <a href="<?php echo esc_url(home_url('/booking-dashboard')); ?>" class="wsb-btn" style="background:#fff; border:1.5px solid var(--wsb-border); color:var(--wsb-text-muted); text-decoration:none; font-size: 14px; padding: 10px 20px; border-radius: 12px; font-weight: 700;">Manage My Bookings</a>
                </div>
                
                <?php if(!empty($categories)): ?>
                <div class="wsb-category-filter">
                    <button class="wsb-filter-btn active" data-category="all">All Services</button>
                    <?php foreach($categories as $cat): ?>
                        <button class="wsb-filter-btn" data-category="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php $layout = get_option('wsb_service_layout', 'modern_grid'); ?>
                <div class="wsb-services-container wsb-layout-<?php echo esc_attr($layout); ?>">
                    <?php if (!empty($services)) : foreach($services as $s): ?>
                    <div class="wsb-card-option" data-service-id="<?php echo esc_attr($s->id); ?>">
                        <?php if($s->category): ?>
                            <span class="wsb-category-badge"><?php echo esc_html($s->category); ?></span>
                        <?php endif; ?>
                        
                        <?php if($layout !== 'minimal'): ?>
                            <div class="wsb-service-image" style="background: #f8fafc <?php echo $s->image_url ? 'url('.esc_url($s->image_url).') center/cover' : ''; ?>;"></div>
                        <?php endif; ?>
                        <div class="wsb-service-content">
                            <h4><?php echo esc_html($s->name); ?></h4>
                            <div class="wsb-service-meta">
                                <span><?php echo esc_html($s->duration); ?> mins</span>
                                <span class="wsb-price-tag"><?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo esc_html($s->price); ?></span>
                            </div>
                            <?php if(in_array($layout, ['modern_grid', 'glass_cards_v2', 'metro_grid', 'neon_night'])): ?>
                                <p class="wsb-service-desc" style="margin-top:10px; font-size:14px; opacity:0.8; line-height:1.5;">
                                    <?php echo esc_html(wp_trim_words($s->description, 12)); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="wsb-selection-indicator"></div>
                    </div>
                    <?php endforeach; else: ?>
                        <p>No services available yet.</p>
                    <?php endif; ?>
                </div>
                <div class="wsb-actions">
                    <div></div>
                    <button class="wsb-next-btn wsb-btn" data-next="wsb-step-staff" disabled>Next Step</button>
                </div>
            </div>

            <div class="wsb-wizard-step" id="wsb-step-staff" style="display:none;">
                <h3>2. Choose a Professional</h3>
                <div class="wsb-staff-grid">
                    <div class="wsb-staff-card" data-staff-id="any">
                        <div class="wsb-staff-avatar wsb-any-avatar">
                            <span>✨</span>
                        </div>
                        <div class="wsb-staff-info">
                            <h4>Any Available</h4>
                            <p class="wsb-staff-title">Optimal Availability</p>
                            <div class="wsb-staff-rating">★★★★★</div>
                        </div>
                        <div class="wsb-selection-indicator"></div>
                    </div>
                    
                    <?php foreach($staff as $member): ?>
                    <div class="wsb-staff-card" data-staff-id="<?php echo esc_attr($member->id); ?>">
                        <div class="wsb-staff-avatar" style="<?php echo !empty($member->image_url) ? 'background-image: url('.esc_url($member->image_url).');' : ''; ?>">
                            <?php if (empty($member->image_url)): ?>
                                <span><?php echo esc_html(strtoupper(substr($member->name, 0, 1))); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="wsb-staff-info">
                            <h4><?php echo esc_html($member->name); ?></h4>
                            <p class="wsb-staff-title"><?php echo esc_html($member->qualification ?: 'Senior Specialist'); ?></p>
                            <div class="wsb-staff-rating">★★★★★</div>
                        </div>
                        <div class="wsb-selection-indicator"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="wsb-actions">
                    <button class="wsb-prev-btn wsb-btn" data-prev="wsb-step-service">Back</button>
                    <button class="wsb-next-btn wsb-btn" data-next="wsb-step-time" disabled>Next Step</button>
                </div>
            </div>

            <div class="wsb-wizard-step" id="wsb-step-time" style="display:none;">
                <h3>3. Select Date & Time</h3>
                <div class="wsb-datetime-layout-stacked">
                    <label class="wsb-datetime-label">📅 Choose Your Date</label>
                    
                    <div class="wsb-modern-calendar-full">
                        <div class="wsb-calendar-header">
                            <button type="button" id="wsb-prev-month" class="wsb-cal-nav">❮</button>
                            <span id="wsb-current-month-year" class="wsb-cal-month-title"></span>
                            <button type="button" id="wsb-next-month" class="wsb-cal-nav">❯</button>
                        </div>
                        <div class="wsb-calendar-weekdays">
                            <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
                        </div>
                        <div id="wsb-calendar-days" class="wsb-calendar-days-grid"></div>
                    </div>
                    
                    <input type="hidden" id="wsb-booking-date" />
                    
                    <div class="wsb-time-picker-section" style="display:none; margin-top: 35px;">
                        <label class="wsb-datetime-label">🕒 Available Slots</label>
                        <div class="wsb-time-slots"></div>
                    </div>
                </div>
                <div class="wsb-actions">
                    <button class="wsb-prev-btn wsb-btn" data-prev="wsb-step-staff">Back</button>
                    <button class="wsb-next-btn wsb-btn" data-next="wsb-step-details" disabled>Next Step</button>
                </div>
            </div>

            <div class="wsb-wizard-step" id="wsb-step-details" style="display:none;">
                <h3>4. Your Details</h3>
                <div class="wsb-form-card">
                    <div class="wsb-form-container">
                        <div class="wsb-form-grid">
                            <div class="wsb-field-wrap">
                                <label>First Name <span style="color:#ef4444;">*</span></label>
                                <input type="text" placeholder="e.g. John" id="wsb-first-name" value="<?php echo esc_attr($user_first_name); ?>" required />
                                <span class="wsb-error-msg" id="wsb-error-first-name"></span>
                            </div>
                            <div class="wsb-field-wrap">
                                <label>Last Name <span style="color:#ef4444;">*</span></label>
                                <input type="text" placeholder="e.g. Doe" id="wsb-last-name" value="<?php echo esc_attr($user_last_name); ?>" required />
                                <span class="wsb-error-msg" id="wsb-error-last-name"></span>
                            </div>
                        </div>
                        
                        <div class="wsb-field-wrap">
                            <label>Email Address <span style="color:#ef4444;">*</span></label>
                            <input type="email" placeholder="john.doe@example.com" id="wsb-email" value="<?php echo esc_attr($user_email); ?>" <?php echo !empty($user_email) ? 'readonly style="background:rgba(255,255,255,0.05); cursor:not-allowed;"' : ''; ?> required />
                            <span class="wsb-error-msg" id="wsb-error-email"></span>
                        </div>
                        
                        <div class="wsb-field-wrap">
                            <label>Phone Number <span style="color:#ef4444;">*</span></label>
                            <div class="wsb-phone-input-group">
                                <select id="wsb-phone-code" class="wsb-phone-code-select">
                                    <option value="+1">🇺🇸 +1</option>
                                    <option value="+44">🇬🇧 +44</option>
                                    <option value="+91">🇮🇳 +91</option>
                                    <option value="+880">🇧🇩 +880</option>
                                    <option value="+61">🇦🇺 +61</option>
                                    <option value="+49">🇩🇪 +49</option>
                                    <option value="+33">🇫🇷 +33</option>
                                    <option value="+971">🇦🇪 +971</option>
                                    <option value="+966">🇸🇦 +966</option>
                                    <option value="+27">🇿🇦 +27</option>
                                    <option value="+55">🇧🇷 +55</option>
                                    <option value="+7">🇷🇺 +7</option>
                                    <option value="+34">🇪🇸 +34</option>
                                    <option value="+39">🇮🇹 +39</option>
                                    <option value="+86">🇨🇳 +86</option>
                                    <option value="+81">🇯🇵 +81</option>
                                </select>
                                <input type="tel" placeholder="(555) 000-0000" id="wsb-phone" value="<?php echo esc_attr($user_phone); ?>" required />
                            </div>
                            <span class="wsb-error-msg" id="wsb-error-phone"></span>
                        </div>
                        
                        <div class="wsb-field-wrap">
                            <label>Additional Notes</label>
                            <textarea placeholder="Any special requests or details we should know?" rows="4" id="wsb-notes"></textarea>
                        </div>
                        
                        <div class="wsb-account-info-note" style="margin-top: 15px; padding: 15px; background: rgba(99,102,241,0.05); border-left: 4px solid var(--wsb-brand); border-radius: 8px; font-size: 14px; color: var(--wsb-text-muted); line-height: 1.5;">
                            💡 <strong>Note:</strong> An account will be created automatically using your email address. You'll receive temporary login details to securely monitor and manage your bookings.
                        </div>
                    </div>
                </div>
                
                <div class="wsb-actions">
                    <button class="wsb-prev-btn wsb-btn" data-prev="wsb-step-time">Back</button>
                    <button class="wsb-next-btn wsb-btn" data-next="wsb-step-payment">Pay Now</button>
                </div>
            </div>

            <div class="wsb-wizard-step" id="wsb-step-payment" style="display:none; gap: 30px; align-items: flex-start; padding-top: 10px; flex-wrap: wrap;">
                
                <!-- LEFT COLUMN: Payment Details -->
                <div style="flex: 1 1 600px; background: #ffffff; border: 1px solid var(--wsb-border); padding: 30px; border-radius: 12px;">
                    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                        <h3 style="margin:0; font-size: 20px; font-weight: 700; color: #0f172a;">Payment Details</h3>
                        <span style="color: #10b981; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 5px;">
                            🛡️ Secure encrypted
                        </span>
                    </div>

                    <!-- Invisible Radios For Compatibility -->
                    <input type="radio" name="payment_method" value="stripe_card" checked style="display:none;">

                    <!-- Stripe payment elements -->
                    <div id="wsb-stripe-element-container">
                        <div id="wsb-payment-loading" style="text-align: center; padding: 40px 0; color: var(--wsb-text-muted);">
                            <div class="wsb-spinner" style="width: 40px; height: 40px; border: 4px solid rgba(0,0,0,0.1); border-top: 4px solid var(--wsb-brand); border-radius: 50%; margin: 0 auto 15px; animation: wsb-spin 1s linear infinite;"></div>
                            Loading secure payment interface...
                        </div>

                        <div id="wsb-payment-element"></div>
                        <div id="wsb-stripe-error" style="color:#ef4444; margin-top:15px; font-size:14px; font-weight:bold;"></div>
                    </div>
                    
                    <!-- Buyer Protection Box -->
                    <div style="margin-top: 30px; background: #eff6ff; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 15px;">
                        <span style="font-size: 24px;">🛡️</span>
                        <div>
                            <strong style="color: #1e3a8a; font-size: 14px; display: block;">Buyer Protection</strong>
                            <span style="color: #1e40af; font-size: 13px;">Your purchase is fully protected by secure, advanced fraud monitoring.</span>
                        </div>
                    </div>
                </div>

                    <!-- RIGHT COLUMN: Order Summary -->
                    <div style="flex: 0 0 350px; background: #ffffff; border: 1px solid var(--wsb-border); padding: 30px; border-radius: 12px;">
                        <h3 style="margin-top: 0; font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 25px;">Order Summary</h3>
                        
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; color: #64748b; font-size: 15px;">
                            <span id="wsb-summary-service-name">Premium Service</span>
                            <span id="wsb-summary-service-price">$0.00</span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; margin-bottom: 15px; color: #64748b; font-size: 15px;">
                            <span>Platform Fee</span>
                            <span>$0.00</span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; padding-bottom: 20px; border-bottom: 1px solid #f1f5f9; margin-bottom: 20px; color: #64748b; font-size: 15px;">
                            <span>Tax (0%)</span>
                            <span>$0.00</span>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; margin-bottom: 30px; font-weight: 700; font-size: 18px; color: #0f172a;">
                            <span>Total</span>
                            <span id="wsb-summary-total-price">$0.00</span>
                        </div>

                        <button id="wsb-complete-checkout-btn" class="wsb-btn" style="width: 100%; background: #1e3a8a; color: #ffffff; padding: 15px; font-size: 16px; font-weight: 700; border-radius: 8px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; border: none;">
                            🔒 Complete Purchase
                        </button>
                        
                        <div style="text-align: center; margin-top: 15px; font-size: 12px; color: #94a3b8;">
                            By completing this purchase, you agree to our <a href="#" style="color: #64748b; text-decoration: underline;">Terms of Service</a>.
                        </div>
                    </div>
                </div>
                
                <div class="wsb-actions" style="margin-top: 30px;">

                    <button class="wsb-submit-btn wsb-btn" id="wsb-confirm-booking" style="display:none;">Confirm Booking</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_client_dashboard() {
        $brand_color = get_option('wsb_brand_color', '#6366f1');
        $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
        $accent_color = get_option('wsb_accent_color', '#4f46e5');
        
        if (!is_user_logged_in()) {
            ob_start();
            ?>
            <style>
                :root {
                    --wsb-brand: <?php echo esc_attr($brand_color); ?>;
                    --wsb-brand-alt: <?php echo esc_attr($accent_color); ?>;
                    --wsb-gradient: linear-gradient(135deg, <?php echo esc_attr($brand_color); ?> 0%, <?php echo esc_attr($brand_color_end); ?> 100%);
                    --wsb-ring: <?php echo esc_attr($brand_color); ?>33;
                }
            </style>
            <div class="wsb-client-login-container" style="max-width:480px; margin: 80px auto; padding: 45px 35px; background:#fff; border-radius:24px; border:1px solid var(--wsb-border); box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.1); text-align:center; position:relative; overflow:hidden;">
                
                <div style="position:absolute; top:-50px; left:-50px; width:120px; height:120px; background:var(--wsb-ring); border-radius:50%; filter:blur(30px); z-index:0;"></div>
                <div style="position:absolute; bottom:-50px; right:-50px; width:120px; height:120px; background:var(--wsb-ring); border-radius:50%; filter:blur(30px); z-index:0;"></div>

                <div style="position:relative; z-index:1;">
                    <div style="width: 70px; height: 70px; background:var(--wsb-ring); color:var(--wsb-brand); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:30px; margin:0 auto 25px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">🔐</div>
                    <h3 style="margin:0 0 10px; font-size:26px; font-weight:800; color:var(--wsb-text-main); letter-spacing:-0.5px;">Client Dashboard</h3>
                    <p style="color:var(--wsb-text-muted); font-size:15px; line-height:1.6; margin:0 0 35px; padding: 0 10px;">Welcome back! Please sign in securely below to track, modify, or review upcoming appointments.</p>
                    <a href="<?php echo wp_login_url(home_url('/booking-dashboard')); ?>" class="wsb-btn" style="display:inline-block; text-decoration:none; padding: 14px 35px; border-radius: 14px; background:var(--wsb-gradient); color:#fff; font-weight:700; font-size:15px; box-shadow:var(--wsb-shadow-md); transition: transform 0.2s, box-shadow 0.2s; border:none; width:100%; box-sizing:border-box;">Sign In to My Dashboard</a>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        global $wpdb;
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;
        
        $customer_table = $wpdb->prefix . 'wsb_customers';
        $booking_table = $wpdb->prefix . 'wsb_bookings';
        $services_table = $wpdb->prefix . 'wsb_services';
        $staff_table = $wpdb->prefix . 'wsb_staff';
        
        $all_staff = $wpdb->get_results("SELECT * FROM $staff_table ORDER BY name ASC");
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, s.name as service_name, st.name as staff_name 
             FROM $booking_table b 
             JOIN $customer_table c ON b.customer_id = c.id
             LEFT JOIN $services_table s ON b.service_id = s.id
             LEFT JOIN {$wpdb->prefix}wsb_staff st ON b.staff_id = st.id
             WHERE c.email = %s 
             ORDER BY b.booking_date DESC, b.start_time DESC", 
            $email
        ));
        
        ob_start();
        $brand_color = get_option('wsb_brand_color', '#6366f1');
        $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
        $accent_color = get_option('wsb_accent_color', '#4f46e5');
        ?>
        <style>
            :root {
                --wsb-brand: <?php echo esc_attr($brand_color); ?>;
                --wsb-brand-alt: <?php echo esc_attr($accent_color); ?>;
                --wsb-gradient: linear-gradient(135deg, <?php echo esc_attr($brand_color); ?> 0%, <?php echo esc_attr($brand_color_end); ?> 100%);
                --wsb-ring: <?php echo esc_attr($brand_color); ?>33;
            }
        </style>
        

        
        <div class="wsb-client-dash" style="max-width: 900px; margin: 40px auto; padding: 35px; background:#fff; border-radius:20px; border:1.5px solid var(--wsb-border); box-shadow:var(--wsb-shadow-md);">
            
            <?php if(isset($_GET['wsb_payment_confirmed'])): ?>
            <div id="wsb-success-overlay" style="background: #ffffff; border: 1.5px solid #10b981; padding: 40px; border-radius: 24px; margin-bottom: 40px; text-align: center; box-shadow: 0 20px 40px -10px rgba(16, 185, 129, 0.15); position: relative; overflow: hidden;">
                <button onclick="wsbCloseSuccess()" style="position: absolute; top: 15px; right: 15px; background: none; border: none; color: #10b981; font-size: 24px; cursor: pointer; opacity: 0.5; font-weight: 800;">&times;</button>
                <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(16, 185, 129, 0.05); border-radius: 50%;"></div>
                
                <div style="width: 80px; height: 80px; background: #10b981; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 25px; animation: wsbPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);">✓</div>
                
                <h2 style="margin: 0 0 10px; font-size: 28px; font-weight: 800; color: #064e3b;">Payment Successful!</h2>
                <p style="color: #065f46; font-size: 16px; margin: 0 0 30px; line-height: 1.6; max-width: 500px; margin-left: auto; margin-right: auto;">Your secure transaction was confirmed. Your appointment has been successfully scheduled and added to your dashboard.</p>
                
                <div style="display: flex; gap: 15px; justify-content: center;">
                    <button onclick="wsbCloseSuccess()" class="wsb-btn" style="background: #10b981; border: none; color: #fff; padding: 12px 30px; border-radius: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);">Manage My Bookings</button>
                </div>
            </div>
            <?php endif; ?>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 35px; padding-bottom: 20px; border-bottom: 1px solid var(--wsb-border);">
                <h3 style="margin:0; font-size: 24px; font-weight:800;">Welcome Back, <?php echo esc_html($current_user->display_name); ?>!</h3>
                <a href="<?php echo wp_logout_url(get_permalink()); ?>" style="font-size:14px; color:#ef4444; font-weight:600; text-decoration:none;">Logout</a>
            </div>

            <div class="wsb-dash-tabs" style="display:flex; gap:15px; border-bottom:1.5px solid var(--wsb-border); margin-bottom: 30px; padding-bottom: 1px;">
                <button class="wsb-dash-tab active" data-target="wsb-dash-bookings" style="background:none; border:none; border-bottom:3px solid var(--wsb-brand); padding: 10px 20px; font-size:16px; font-weight:700; color:var(--wsb-brand); cursor:pointer;">📅 My Bookings</button>
                <button class="wsb-dash-tab" data-target="wsb-dash-account" style="background:none; border:none; border-bottom:3px solid transparent; padding: 10px 20px; font-size:16px; font-weight:700; color:var(--wsb-text-muted); cursor:pointer;">⚙️ Account Details</button>
            </div>
            
            <div id="wsb-dash-bookings" class="wsb-dash-content-panel">
            
            <?php if(empty($bookings)): ?>
                <div style="text-align:center; padding:40px 0; color:var(--wsb-text-muted);">
                    <p>You haven't made any appointments yet.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; text-align:left;">
                        <thead>
                            <tr style="border-bottom:2px solid var(--wsb-border); color:var(--wsb-text-muted); font-size:14px; font-weight:700;">
                                <th style="padding:12px 15px;">Booking ID</th>
                                <th style="padding:12px 15px;">Service</th>
                                <th style="padding:12px 15px;">Date & Time</th>
                                <th style="padding:12px 15px;">Amount</th>
                                <th style="padding:12px 15px;">Status</th>
                                <th style="padding:12px 15px; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($bookings as $b): ?>
                                <tr class="wsb-booking-row" data-id="<?php echo esc_attr($b->id); ?>" data-service="<?php echo esc_attr($b->service_name ?: 'Custom Service'); ?>" data-staff="<?php echo esc_attr($b->staff_name ?: 'Assigned Professional'); ?>" data-date="<?php echo esc_attr(date('M d, Y', strtotime($b->booking_date))); ?>" data-time="<?php echo esc_attr(date('h:i A', strtotime($b->start_time))); ?>" data-amount="<?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')).esc_attr($b->total_amount); ?>" data-status="<?php echo esc_attr(ucfirst($b->status)); ?>" style="border-bottom:1px solid var(--wsb-border); font-size:15px; color:var(--wsb-text-main); cursor:pointer;">
                                    <td data-label="Booking ID" style="padding:15px; font-weight:700;">#<?php echo esc_html($b->id); ?></td>
                                    <td data-label="Service" style="padding:15px; font-weight:600;"><?php echo esc_html($b->service_name ?: 'Custom Service'); ?></td>
                                    <td data-label="Date & Time" style="padding:15px; color:var(--wsb-text-muted);"><?php echo esc_html(date('M d, Y', strtotime($b->booking_date))); ?> @ <?php echo esc_html(date('h:i A', strtotime($b->start_time))); ?></td>
                                    <td data-label="Amount" style="padding:15px; font-weight:600;"><?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo esc_html($b->total_amount); ?></td>
                                    <td data-label="Status" style="padding:15px;">
                                        <span style="padding:5px 12px; border-radius:20px; font-size:12px; font-weight:700; text-transform:uppercase; 
                                        <?php 
                                            if($b->status === 'confirmed' || $b->status === 'completed') echo 'background:rgba(16, 185, 129, 0.1); color:#10b981;';
                                            elseif($b->status === 'cancelled') echo 'background:rgba(239, 68, 68, 0.1); color:#ef4444;';
                                            else echo 'background:rgba(245, 158, 11, 0.1); color:#f59e0b;';
                                        ?>">
                                            <?php echo esc_html($b->status); ?>
                                        </span>
                                    </td>
                                    <td style="padding:15px; text-align:right;">
                                        <div style="display:flex; justify-content:flex-end; gap:8px; align-items:center;">
                                            <?php if($b->status !== 'cancelled'): ?>
                                                <button class="wsb-client-action-btn" data-action="reschedule" data-id="<?php echo esc_attr($b->id); ?>" style="background:rgba(99, 102, 241, 0.08); color:var(--wsb-brand); border:1px solid rgba(99, 102, 241, 0.2); padding:8px 15px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s ease;">Reschedule</button>
                                                <button class="wsb-client-action-btn" data-action="cancel" data-id="<?php echo esc_attr($b->id); ?>" style="background:rgba(239, 68, 68, 0.05); color:#ef4444; border:1px solid rgba(239, 68, 68, 0.2); padding:8px 15px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s ease;">Cancel</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div> <!-- Close wsb-dash-bookings -->

            <div id="wsb-dash-account" class="wsb-dash-content-panel" style="display:none;">
                <h4 style="margin:0 0 25px; font-size:18px; color:var(--wsb-text-main);">⚙️ Manage Account Details</h4>
                <form id="wsb-client-account-form">
                    <div class="wsb-form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div class="wsb-form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">First Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="first_name" value="<?php echo esc_attr($current_user->first_name); ?>" required style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px;">
                        </div>
                        <div class="wsb-form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Last Name <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="last_name" value="<?php echo esc_attr($current_user->last_name); ?>" required style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px;">
                        </div>
                    </div>
                    
                    <div class="wsb-form-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div class="wsb-form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Email <span style="color:#ef4444;">*</span></label>
                            <input type="email" name="email" value="<?php echo esc_attr($current_user->user_email); ?>" disabled style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px; background:#f8fafc; cursor:not-allowed;">
                        </div>
                        <div class="wsb-form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Phone Number</label>
                            <input type="text" name="phone" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'wsb_client_phone', true)); ?>" style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px;">
                        </div>
                    </div>
                    
                    <div class="wsb-form-group" style="margin-bottom: 20px;">
                        <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Residential Address</label>
                        <textarea name="address" rows="3" style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px; resize:vertical;"><?php echo esc_textarea(get_user_meta($current_user->ID, 'wsb_client_address', true)); ?></textarea>
                    </div>

                    <div class="wsb-form-group" style="margin-bottom: 25px;">
                        <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Change Password <span style="color:var(--wsb-text-muted); font-weight:normal; font-size:13px;">(Leave blank to keep current)</span></label>
                        <input type="password" name="password" placeholder="••••••••" style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px;">
                    </div>
                    
                    <button type="submit" class="wsb-btn wsb-next-btn" style="padding: 12px 30px; border-radius:10px; border:none; font-weight:700; cursor:pointer; background:var(--wsb-gradient); color:#fff;">Save Changes</button>
                    <div id="wsb-account-msg" style="margin-top: 15px; font-size:14px; display:none; font-weight:600;"></div>
                </form>
            </div>
        </div>

        <!-- Booking Details Modal -->
        <div id="wsb-details-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.5); backdrop-filter:blur(8px); z-index:99999; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:40px; border-radius:24px; width:100%; max-width:520px; position:relative; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); margin: auto 20px;">
                <span class="wsb-modal-close" style="position:absolute; top:25px; right:25px; font-size:28px; font-weight:bold; color:var(--wsb-text-muted); cursor:pointer; line-height:1; transition:color 0.2s;">&times;</span>
                
                <div style="display:flex; align-items:center; gap:15px; margin-bottom:30px;">
                    <div style="width:50px; height:50px; background:var(--wsb-ring); color:var(--wsb-brand); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:24px;">📋</div>
                    <h3 style="margin:0; font-size:22px; font-weight:800; color:var(--wsb-text-main);">Booking Details</h3>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; padding-bottom:20px; border-bottom:1px solid var(--wsb-border);">
                    <div>
                        <label style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Booking ID</label>
                        <div id="wsb-modal-id" style="font-size:18px; font-weight:800; margin-top:5px; color:var(--wsb-brand);"></div>
                    </div>
                    <div>
                        <label style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Amount</label>
                        <div id="wsb-modal-amount" style="font-size:18px; font-weight:800; margin-top:5px; color:var(--wsb-text-main);"></div>
                    </div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Service</label>
                    <div id="wsb-modal-service" style="font-size:16px; font-weight:700; margin-top:5px; color:var(--wsb-text-main);"></div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Assigned Professional</label>
                    <div id="wsb-modal-staff" style="font-size:16px; font-weight:600; margin-top:5px; color:var(--wsb-text-main);"></div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Date & Time</label>
                    <div id="wsb-modal-datetime" style="font-size:16px; font-weight:600; margin-top:5px; color:var(--wsb-text-main);"></div>
                </div>

                <div>
                    <label style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Status</label>
                    <div style="margin-top:8px;">
                        <span id="wsb-modal-status" style="padding:6px 14px; border-radius:20px; font-size:13px; font-weight:700; text-transform:uppercase;"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reschedule Request Modal -->
        <div id="wsb-reschedule-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.5); backdrop-filter:blur(8px); z-index:99999; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:40px; border-radius:24px; width:100%; max-width:520px; position:relative; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); margin: auto 20px;">
                <span class="wsb-reschedule-close" style="position:absolute; top:25px; right:25px; font-size:28px; font-weight:bold; color:var(--wsb-text-muted); cursor:pointer; line-height:1; transition:color 0.2s;">&times;</span>
                
                <div style="display:flex; align-items:center; gap:15px; margin-bottom:30px;">
                    <div style="width:50px; height:50px; background:var(--wsb-ring); color:var(--wsb-brand); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:24px;">📅</div>
                    <h3 style="margin:0; font-size:22px; font-weight:800; color:var(--wsb-text-main);">Reschedule Appointment</h3>
                </div>

                <form id="wsb-reschedule-form">
                    <input type="hidden" name="booking_id" id="wsb-reschedule-id">
                    
                    <div style="margin-bottom:20px;">
                        <label style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; text-transform:uppercase; color:var(--wsb-text-muted); letter-spacing:0.5px;">Choose a Professional</label>
                        <select name="reschedule_staff" id="wsb-reschedule-staff" style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:12px; font-size:15px; background:#fff;" required>
                            <option value="">-- Select Professional --</option>
                            <?php foreach($all_staff as $staff): ?>
                                <option value="<?php echo esc_attr($staff->id); ?>"><?php echo esc_html($staff->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; text-transform:uppercase; color:var(--wsb-text-muted); letter-spacing:0.5px;">Pick a Date</label>
                        <input type="date" name="reschedule_date" id="wsb-reschedule-date" min="<?php echo date('Y-m-d'); ?>" style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:12px; font-size:15px;" required>
                    </div>

                    <div id="wsb-reschedule-slots-container" style="display:none; margin-bottom:30px;">
                        <label style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; text-transform:uppercase; color:var(--wsb-text-muted); letter-spacing:0.5px;">Available Time Slots</label>
                        <div class="wsb-reschedule-slots" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap:10px; max-height: 150px; overflow-y: auto; padding: 5px;">
                            <!-- Loaded via AJAX -->
                        </div>
                        <input type="hidden" name="reschedule_time" id="wsb-reschedule-time-input" required>
                        <div id="wsb-reschedule-time-error" style="color:#ef4444; font-size:13px; margin-top:5px; display:none;">Please select a time slot.</div>
                    </div>

                    <div id="wsb-reschedule-msg" style="margin-bottom:15px; font-size:14px; font-weight:600; display:none;"></div>

                    <button type="submit" class="wsb-btn" style="display:block; width:100%; padding:14px; border:none; border-radius:14px; background:var(--wsb-gradient); color:#fff; font-weight:700; font-size:16px; cursor:pointer; box-shadow:var(--wsb-shadow-md);">Request Reschedule</button>
                </form>
            </div>
        </div>

        <!-- Cancel Booking Modal -->
        <div id="wsb-cancel-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.5); backdrop-filter:blur(8px); z-index:99999; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:40px; border-radius:24px; width:100%; max-width:450px; position:relative; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); margin: auto 20px; text-align:center;">
                <span class="wsb-cancel-close" style="position:absolute; top:25px; right:25px; font-size:28px; font-weight:bold; color:var(--wsb-text-muted); cursor:pointer; line-height:1;">&times;</span>
                
                <div style="width:70px; height:70px; background:rgba(239, 68, 68, 0.1); color:#ef4444; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:32px; margin:0 auto 25px;">⚠️</div>
                
                <h3 style="margin:0 0 10px; font-size:22px; font-weight:800; color:var(--wsb-text-main);">Request Cancellation</h3>
                <p style="color:var(--wsb-text-muted); font-size:15px; line-height:1.6; margin:0 0 30px;">Are you sure you want to request cancellation for appointment <strong id="wsb-cancel-title-id" style="color:var(--wsb-text-main);"></strong>? This request is subject to administrative review.</p>

                <form id="wsb-cancel-form">
                    <input type="hidden" name="booking_id" id="wsb-cancel-id">
                    <div id="wsb-cancel-msg" style="margin-bottom:15px; font-size:14px; font-weight:600; display:none;"></div>
                    
                    <div style="display:flex; gap:15px; justify-content:center;">
                        <button type="button" class="wsb-cancel-close" style="flex:1; padding:14px; border:1.5px solid var(--wsb-border); border-radius:12px; background:#fff; color:var(--wsb-text-main); font-weight:700; font-size:15px; cursor:pointer; transition:all 0.2s;">Keep Booking</button>
                        <button type="submit" class="wsb-btn" style="flex:1; padding:14px; border:none; border-radius:12px; background:#ef4444; color:#fff; font-weight:700; font-size:15px; cursor:pointer; box-shadow:var(--wsb-shadow-sm);">Request Cancellation</button>
                    </div>
                </form>
            </div>
        </div>

        <div style="text-align:center; margin: 35px auto;">
            <a href="<?php echo esc_url(home_url('/booking')); ?>" class="wsb-btn" style="display:inline-block; text-decoration:none; padding: 14px 40px; border-radius: 12px; background:var(--wsb-gradient); color:#fff; font-weight:700; font-size:16px; box-shadow:var(--wsb-shadow-md);">Book a Service</a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Virtual route for /booking
     */
    public function virtual_booking_route() {
        $request_uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
        $path = trim( $request_uri, '/' );
        $parts = explode( '/', $path );
        $slug = end( $parts );

        if ( $slug === 'booking' || $slug === 'booking-dashboard' ) {
            status_header( 200 );
            $brand_color = get_option('wsb_brand_color', '#6366f1');
            $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
            $virtual_bg_color = get_option('wsb_virtual_bg_color', '#f8fafc');
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo( 'charset' ); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php wp_head(); ?>
                <style>
                    body { background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; margin: 0; }
                    .wsb-virtual-page { 
                        background: <?php echo esc_attr($virtual_bg_color); ?>; 
                        width: 100%; 
                        max-width: 850px; 
                        padding: 40px; 
                        border-radius: 24px; 
                        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15); 
                        border: 1px solid rgba(255,255,255,0.2); 
                    }
                    #wsb-booking-wizard-container { margin: 0; padding: 20px; background: transparent; border: none; box-shadow: none; }
                </style>
            </head>
            <body>
                <div class="wsb-virtual-page">
                    <?php 
                    if ($slug === 'booking-dashboard') {
                        echo $this->render_client_dashboard(); 
                    } else {
                        echo $this->render_booking_widget( array() ); 
                    }
                    ?>
                </div>
                <?php wp_footer(); ?>
            </body>
            </html>
            <?php
            exit;
        }
    }
    public function wsb_login_redirect($redirect_to, $request, $user) {
        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('subscriber', $user->roles)) {
                return home_url('/booking-dashboard');
            }
        }
        return $redirect_to;
    }

    public function wsb_restrict_admin_access() {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }
        if (is_user_logged_in() && !current_user_can('manage_options')) {
            wp_redirect(home_url('/booking-dashboard'));
            exit;
        }
    }

    /**
     * Redirect to home on logout
     */
    public function wsb_logout_redirect($redirect_to, $requested_redirect_to, $user) {
        // If logging out from the dashboard specifically, or generally for subscribers
        return home_url();
    }
}
