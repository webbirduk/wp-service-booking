<?php
class Wsb_Public
{
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles()
    {
        wp_enqueue_style('dashicons');
        wp_enqueue_style($this->plugin_name, plugin_dir_url(dirname(__FILE__)) . 'assets/public/css/wsb-public.css', array(), time(), 'all');
    }

    public function handle_stripe_return()
    {
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

                $mail_subject = 'Payment Received: Booking Confirmed';
                $details_html = '
                <div style="background:#f0fdf4; padding:25px; border-radius:16px; border:1px solid #dcfce7;">
                    <div style="font-size:12px; text-transform:uppercase; color:#166534; font-weight:800; margin-bottom:10px;">Payment Successful</div>
                    <div style="margin-bottom:10px;"><strong>Booking ID:</strong> #' . $booking_id . '</div>
                    <div style="margin-bottom:10px;"><strong>Scheduled Date:</strong> ' . $booking->booking_date . '</div>
                    <div style="margin-bottom:10px;"><strong>Start Time:</strong> ' . $booking->start_time . '</div>
                </div>';

                wsb_send_modern_email($customer->email, $mail_subject, 'Payment Confirmed', "Hello " . $customer->first_name . ", your payment was successful and your appointment is now fully confirmed!", $details_html);
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

    public function enqueue_scripts()
    {
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, false);
        wp_enqueue_script($this->plugin_name, plugin_dir_url(dirname(__FILE__)) . 'assets/public/js/wsb-public.js', array('jquery', 'stripe-js'), time(), true);
        // Fetch staff-service mapping
        global $wpdb;
        $staff_services_raw = $wpdb->get_results("SELECT staff_id, service_id FROM {$wpdb->prefix}wsb_staff_services");
        $mapping = array();
        foreach ($staff_services_raw as $row) {
            $mapping[$row->service_id][] = intval($row->staff_id);
        }

        wp_localize_script($this->plugin_name, 'wsb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wsb_nonce'),
            'login_url' => wp_login_url(),
            'dashboard_url' => home_url('/booking-dashboard'),
            'stripe_pk' => get_option('wsb_stripe_publishable_key', ''),
            'skip_professional' => get_option('wsb_skip_professional_step', 'no'),
            'skip_payment' => get_option('wsb_skip_payment_step', 'no'),
            'filter_staff_by_service' => get_option('wsb_filter_staff_by_service', 'no'),
            'enable_split_scheduling' => get_option('wsb_enable_split_scheduling', 'no'),
            'staff_service_mapping' => $mapping,
            'currency_symbol' => wsb_get_currency_symbol(get_option('wsb_currency', 'USD')),
            'basket_mode' => get_option('wsb_basket_mode', 'hover')
        ));
    }

    public function render_booking_widget($atts)
    {
        global $wpdb;

        if (isset($_GET['wsb_service_id'])) {
            $service_id = intval($_GET['wsb_service_id']);
            $s = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsb_services WHERE id = %d", $service_id));
            if ($s) {
                ob_start();
                $brand_color = get_option('wsb_brand_color', '#6366f1');
                $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
                $font_family = get_option('wsb_font_family', 'Inter');
                $back_url = remove_query_arg('wsb_service_id');
                ?>
                <style>
                    :root {
                        --wsb-brand: <?php echo esc_attr($brand_color); ?>;
                        --wsb-gradient: linear-gradient(135deg, <?php echo esc_attr($brand_color); ?> 0%, <?php echo esc_attr($brand_color_end); ?> 100%);
                        --wsb-font: "<?php echo esc_attr($font_family); ?>", sans-serif;
                    }
                </style>
                <div class="wsb-single-service-page" style="font-family: var(--wsb-font); max-width: 800px; margin: 40px auto; background: #fff; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e2e8f0;">
                    <?php if ($s->image_url): ?>
                        <div style="width: 100%; height: 400px; background: url('<?php echo esc_url($s->image_url); ?>') center/cover;"></div>
                    <?php endif; ?>
                    <div style="padding: 40px;">
                        <a href="<?php echo esc_url($back_url); ?>" style="display: inline-block; margin-bottom: 20px; color: #64748b; text-decoration: none; font-weight: 600;">&larr; Back to Services</a>
                        <h1 style="margin: 0 0 15px; font-size: 36px; font-weight: 800; color: #0f172a;"><?php echo esc_html($s->name); ?></h1>
                        <div style="display: flex; gap: 15px; margin-bottom: 30px;">
                            <span style="background: rgba(99, 102, 241, 0.1); color: var(--wsb-brand); padding: 8px 16px; border-radius: 20px; font-size: 15px; font-weight: 700;">⏱️ <?php echo esc_html($s->duration); ?> mins</span>
                            <span style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 8px 16px; border-radius: 20px; font-size: 15px; font-weight: 700;">💰 <?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')) . esc_html($s->price); ?></span>
                        </div>
                        <div style="color: #475569; line-height: 1.8; font-size: 16px; margin-bottom: 40px;">
                            <?php echo wpautop(esc_html($s->description)); ?>
                        </div>
                        <a href="<?php echo esc_url(add_query_arg(['wsb_select_service' => $s->id, 'wsb_jump_to_staff' => '1'], $back_url)); ?>" class="wsb-btn" style="display: block; text-align: center; background: var(--wsb-gradient); color: #fff; padding: 18px; border-radius: 16px; font-weight: 800; font-size: 18px; text-decoration: none; box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4);">Book This Service Now</a>
                    </div>
                </div>
                <?php
                return ob_get_clean();
            }
        }

        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wsb_services WHERE status = 'active'");
        $staff = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wsb_staff WHERE status = 'active'");

        ob_start();
        $brand_color = get_option('wsb_brand_color', '#6366f1');
        $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
        $accent_color = get_option('wsb_accent_color', '#4f46e5');

        // Extract unique categories for filter
        $categories = array();
        if (!empty($services)) {
            foreach ($services as $s) {
                if (!empty($s->category)) {
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

        // Customization Options
        $font_family = get_option('wsb_font_family', 'Inter');
        $border_radius = get_option('wsb_border_radius', 16);
        $shadow_intensity = get_option('wsb_shadow_intensity', 'medium');
        
        $shadow_map = [
            'none' => 'none',
            'low' => '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
            'medium' => '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
            'high' => '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1)'
        ];
        $shadow_value = isset($shadow_map[$shadow_intensity]) ? $shadow_map[$shadow_intensity] : $shadow_map['medium'];

        $l_step1 = get_option('wsb_label_step1', '1. Select a Service');
        $l_step2 = get_option('wsb_label_step2', '2. Choose a Professional');
        $l_step3 = get_option('wsb_label_step3', '3. Select Date & Time');
        $l_step4 = get_option('wsb_label_step4', '4. Your Details');
        $l_next = get_option('wsb_label_next_btn', 'Next Step');
        $l_prev = get_option('wsb_label_prev_btn', 'Back');

        // Dynamic Numbering Engine
        $step_idx = 1;
        $skip_prof = get_option('wsb_skip_professional_step', 'no');
        $skip_pay = get_option('wsb_skip_payment_step', 'no');
        
        // Helper to strip numbers from labels (e.g. "1. Select Service" -> "Select Service")
        $clean_label = function($label) {
            return preg_replace('/^\d+[\.\)\-\s]+/', '', $label);
        };

        // Detailed Styling
        $card_bg = get_option('wsb_card_bg_color', '#ffffff');
        $heading_color = get_option('wsb_heading_text_color', '#0f172a');
        $body_color = get_option('wsb_body_text_color', '#64748b');
        $input_bg = get_option('wsb_input_bg_color', '#ffffff');
        $input_border = get_option('wsb_input_border_color', '#e2e8f0');
        ?>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;600;700;800&family=Jost:wght@400;600;700&family=Lora:wght@400;600;700&family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600;700&family=Outfit:wght@400;600;700;800&family=Playfair+Display:wght@400;600;700;900&family=Poppins:wght@400;600;700&family=Roboto:wght@400;500;700;900&family=Space+Grotesk:wght@400;600;700&family=Syne:wght@400;600;700;800&display=swap");
            
            :root {
                --wsb-brand: <?php echo esc_attr($brand_color); ?>;
                --wsb-brand-alt: <?php echo esc_attr($accent_color); ?>;
                --wsb-gradient: linear-gradient(135deg, <?php echo esc_attr($brand_color); ?> 0%, <?php echo esc_attr($brand_color_end); ?> 100%);
                --wsb-ring: <?php echo esc_attr($brand_color); ?>33;
                --wsb-font: "<?php echo esc_attr($font_family); ?>", sans-serif;
                --wsb-radius: <?php echo esc_attr($border_radius); ?>px;
                --wsb-shadow-custom: <?php echo esc_attr($shadow_value); ?>;
                
                /* Detailed Styling Variables */
                --wsb-card-bg: <?php echo esc_attr($card_bg); ?>;
                --wsb-heading: <?php echo esc_attr($heading_color); ?>;
                --wsb-body: <?php echo esc_attr($body_color); ?>;
                --wsb-input-bg: <?php echo esc_attr($input_bg); ?>;
                --wsb-input-border: <?php echo esc_attr($input_border); ?>;
            }
            #wsb-booking-wizard-container { font-family: var(--wsb-font) !important; color: var(--wsb-body); }
            #wsb-booking-wizard-container h1, #wsb-booking-wizard-container h2, #wsb-booking-wizard-container h3, #wsb-booking-wizard-container h4 { color: var(--wsb-heading); }
            .wsb-card-option, .wsb-staff-card, .wsb-form-card { background-color: var(--wsb-card-bg) !important; }
            .wsb-card-option, .wsb-staff-card, .wsb-btn, .wsb-form-card, .wsb-field-wrap input, .wsb-field-wrap select, .wsb-field-wrap textarea, .wsb-phone-input-group { border-radius: var(--wsb-radius) !important; }
            .wsb-card-option, .wsb-staff-card, .wsb-form-card { box-shadow: var(--wsb-shadow-custom) !important; }
            .wsb-field-wrap input, .wsb-field-wrap select, .wsb-field-wrap textarea { background-color: var(--wsb-input-bg) !important; border-color: var(--wsb-input-border) !important; color: var(--wsb-body); }
            
            /* Modern Header System - Centered */
            .wsb-step-header { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 15px; margin-bottom: 40px; border-bottom: 1px solid var(--wsb-input-border); padding-bottom: 30px; position: relative; z-index: 1000; }
            .wsb-step-badge { 
                background: var(--wsb-gradient); color: white; width: 50px; height: 50px; 
                display: flex; align-items: center; justify-content: center; 
                border-radius: 50%; font-weight: 800; font-size: 20px; flex-shrink: 0;
                box-shadow: 0 10px 20px -5px var(--wsb-ring);
                margin-bottom: 5px;
            }
            .wsb-step-details h3 { margin: 0 0 8px 0 !important; font-size: 28px !important; font-weight: 800 !important; letter-spacing: -0.03em !important; }
            .wsb-step-details p { margin: 0; color: var(--wsb-body); opacity: 0.7; font-size: 16px; font-weight: 500; max-width: 500px; margin-left: auto; margin-right: auto; }
            
            @media (max-width: 768px) {
                .wsb-step-header { gap: 10px; margin-bottom: 30px; }
                .wsb-step-details h3 { font-size: 22px !important; }
                .wsb-step-details p { font-size: 14px; }
                .wsb-step-badge { width: 40px; height: 40px; font-size: 16px; }
            }
        </style>
        <div id="wsb-booking-wizard-container" class="wsb-wrapper">
            <div class="wsb-wizard-step" id="wsb-step-service">
                <div class="wsb-step-header">
                    <div class="wsb-step-badge"><?php echo str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                    <div class="wsb-step-details">
                        <h3><?php echo esc_html($clean_label($l_step1)); ?></h3>
                        <p>Select your desired service to begin your experience.</p>
                        
                        <?php 
                        $l_basket = get_option('wsb_label_basket_btn', 'Services Selected');
                        $i_basket = get_option('wsb_icon_basket_btn', 'dashicons-cart');
                        ?>
                        
                        <div style="margin-top:20px; display:flex; align-items:center; justify-content:center; gap:15px; flex-wrap:wrap;">
                            <a href="<?php echo esc_url(home_url('/booking-dashboard')); ?>" class="wsb-btn"
                                style="display:inline-flex; align-items:center; background:rgba(99, 102, 241, 0.05); border:1.5px solid var(--wsb-brand); color:var(--wsb-brand); text-decoration:none; font-size: 13px; padding: 10px 22px; border-radius: 12px; font-weight: 700; transition:all 0.3s; gap:8px;">
                                <span class="dashicons dashicons-admin-users" style="font-size:18px;"></span> Client Portal
                            </a>

                            <div id="wsb-basket-trigger" class="wsb-basket-trigger-btn wsb-btn"
                                style="position:relative; display:inline-flex; align-items:center; background:var(--wsb-gradient); color:#fff; cursor:pointer; font-size: 13px; padding: 10px 26px; border-radius: 12px; font-weight: 700; transition:all 0.3s; gap:12px; box-shadow: 0 4px 12px var(--wsb-ring);">
                                <div style="position:relative; display:flex; align-items:center; justify-content:center;">
                                    <span class="dashicons <?php echo esc_attr($i_basket); ?>" style="font-size:20px; width:20px; height:20px;"></span> 
                                    <span class="wsb-basket-count-val" style="position:absolute; top:-10px; right:-12px; background:var(--wsb-brand-alt); color:#fff; min-width:18px; height:18px; border-radius:10px; font-size:10px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff; font-weight:900; line-height:1; box-shadow:0 2px 4px rgba(0,0,0,0.1);">0</span>
                                </div>
                                <span><?php echo esc_html($l_basket); ?></span>
                                
                                <!-- Basket Popup -->
                                <div id="wsb-basket-popup" style="display:none; position:absolute; top:calc(100% + 15px); right:0; width:320px; background:#fff; border-radius:16px; border:1px solid var(--wsb-input-border); box-shadow:0 20px 40px rgba(0,0,0,0.2); z-index:999999; padding:20px; text-align:left; cursor:default;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--wsb-input-border); padding-bottom:10px;">
                                        <h4 style="margin:0; font-size:16px; font-weight:800; color:var(--wsb-heading);">Your Selection</h4>
                                        <span id="wsb-close-basket" style="font-size:20px; cursor:pointer; color:var(--wsb-body);">&times;</span>
                                    </div>
                                    <div id="wsb-basket-items" style="max-height:250px; overflow-y:auto; margin-bottom:15px; display:flex; flex-direction:column; gap:10px;">
                                        <!-- Items populated via JS -->
                                        <p id="wsb-empty-basket-msg" style="text-align:center; color:var(--wsb-body); opacity:0.6; font-size:14px; margin:20px 0;">No services selected yet.</p>
                                    </div>
                                    <div id="wsb-basket-footer" style="border-top:1px solid var(--wsb-input-border); padding-top:15px; display:none;">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-weight:800; color:var(--wsb-heading);">
                                            <span>Total</span>
                                            <span id="wsb-basket-total">0.00</span>
                                        </div>
                                        <button class="wsb-btn wsb-next-btn" data-next="wsb-step-staff" style="width:100%; padding:12px;">Continue Booking</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($categories)): ?>
                    <div class="wsb-category-filter">
                        <button class="wsb-filter-btn active" data-category="all">All Services</button>
                        <?php foreach ($categories as $cat): ?>
                            <button class="wsb-filter-btn"
                                data-category="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php $layout = get_option('wsb_service_layout', 'modern_grid'); ?>
                <div class="wsb-services-container wsb-layout-<?php echo esc_attr($layout); ?>">
                    <?php if (!empty($services)):
                        foreach ($services as $s): ?>
                            <div class="wsb-card-option" 
                                data-service-id="<?php echo esc_attr($s->id); ?>"
                                data-price="<?php echo esc_attr($s->price); ?>"
                                data-duration="<?php echo esc_attr($s->duration); ?>">
                                <?php if ($s->category): ?>
                                    <span class="wsb-category-badge"><?php echo esc_html($s->category); ?></span>
                                <?php endif; ?>

                                <?php if ($layout !== 'minimal'): ?>
                                    <div class="wsb-service-image-container" style="position:relative; overflow:hidden;">
                                        <div class="wsb-service-image"
                                            style="background: #f8fafc <?php echo $s->image_url ? 'url(' . esc_url($s->image_url) . ') center/cover' : ''; ?>;">
                                        </div>
                                        <a href="<?php echo esc_url(add_query_arg('wsb_service_id', $s->id)); ?>" class="wsb-view-service-btn" title="View Product Details">
                                            <span class="dashicons <?php echo esc_attr(get_option('wsb_icon_view_details', 'dashicons-visibility')); ?>"></span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="wsb-service-content">
                                    <h4><?php echo esc_html($s->name); ?></h4>
                                    <div class="wsb-service-meta">
                                        <span><?php echo esc_html($s->duration); ?> mins</span>
                                        <span
                                            class="wsb-price-tag"><?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo esc_html($s->price); ?></span>
                                    </div>
                                    <?php if (in_array($layout, ['modern_grid', 'glass_cards_v2', 'metro_grid', 'neon_night'])): ?>
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
                    <button class="wsb-next-btn wsb-btn" data-next="wsb-step-staff" disabled><?php echo esc_html($l_next); ?></button>
                </div>
            </div>

            <div class="wsb-wizard-step" id="wsb-step-staff" style="display:none;">
                <div class="wsb-step-header">
                    <div class="wsb-step-badge"><?php echo ($skip_prof === 'yes') ? '--' : str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                    <div class="wsb-step-details">
                        <h3><?php echo esc_html($clean_label($l_step2)); ?></h3>
                        <p>Our team of experts is ready to provide exceptional care.</p>
                        
                        <!-- MULTI-SERVICE SESSION NOTICE -->
                        <div id="wsb-multi-session-notice" style="display:none; margin-top:20px; padding:15px 25px; background:rgba(99, 102, 241, 0.05); border:1px solid var(--wsb-brand); border-radius:12px; font-size:14px; color:var(--wsb-brand); font-weight:600; text-align:left;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid rgba(99, 102, 241, 0.1); padding-bottom:8px;">
                                <span style="font-size:16px; font-weight:800;">✨ Selected Bundle</span>
                                <span style="background:var(--wsb-brand); color:#fff; padding:2px 10px; border-radius:20px; font-size:11px;"><span id="wsb-session-duration">0</span> mins</span>
                            </div>
                            <div id="wsb-service-breakdown" style="display:flex; flex-direction:column; gap:6px;">
                                <!-- Populated via JS -->
                            </div>
                            <div id="wsb-split-indicator" style="display:none; margin-top:12px; padding-top:10px; border-top:1px dashed var(--wsb-brand); color:var(--wsb-brand); font-style:italic; font-size:12px;">
                                📍 Scheduling: <span id="wsb-current-split-service-name">Service Name</span>
                            </div>
                        </div>
                    </div>
                </div>
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

                    <?php foreach ($staff as $member): ?>
                        <div class="wsb-staff-card" data-staff-id="<?php echo esc_attr($member->id); ?>">
                            <div class="wsb-staff-avatar"
                                style="<?php echo !empty($member->image_url) ? 'background-image: url(' . esc_url($member->image_url) . ');' : ''; ?>">
                                <?php if (empty($member->image_url)): ?>
                                    <span><?php echo esc_html(strtoupper(substr($member->name, 0, 1))); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="wsb-staff-info">
                                <h4><?php echo esc_html($member->name); ?></h4>
                                <p class="wsb-staff-title"><?php echo esc_html($member->qualification ?: 'Senior Specialist'); ?>
                                </p>
                                <div class="wsb-staff-rating">★★★★★</div>
                            </div>
                            <div class="wsb-selection-indicator"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="wsb-actions">
                    <button class="wsb-prev-btn wsb-btn" data-prev="wsb-step-service"><?php echo esc_html($l_prev); ?></button>
                    <button class="wsb-next-btn wsb-btn" data-next="wsb-step-time" disabled><?php echo esc_html($l_next); ?></button>
                </div>
            </div>

            <div class="wsb-wizard-step" id="wsb-step-time" style="display:none;">
                <div class="wsb-step-header">
                    <div class="wsb-step-badge"><?php echo str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                    <div class="wsb-step-details">
                        <h3><?php echo esc_html($clean_label($l_step3)); ?></h3>
                        <p>Find a time that perfectly fits your schedule.</p>

                        <!-- MULTI-SERVICE TIME NOTICE -->
                        <div id="wsb-multi-time-notice" style="display:none; margin-top:20px; padding:12px 20px; background:rgba(16, 185, 129, 0.05); border:1px solid #10b981; border-radius:12px; font-size:14px; color:#10b981; font-weight:600;">
                            <span style="font-size:18px; margin-right:8px;">🕒</span> 
                            <span>Finding a <span id="wsb-session-duration-time">0</span> min opening for your combined services.</span>
                        </div>
                    </div>
                </div>
                <div class="wsb-datetime-layout-stacked">
                    <label class="wsb-datetime-label">📅 Choose Your Date</label>

                    <div class="wsb-modern-calendar-full">
                        <div class="wsb-calendar-header">
                            <button type="button" id="wsb-prev-month" class="wsb-cal-nav">❮</button>
                            <span id="wsb-current-month-year" class="wsb-cal-month-title"></span>
                            <button type="button" id="wsb-next-month" class="wsb-cal-nav">❯</button>
                        </div>
                        <div class="wsb-calendar-weekdays">
                            <div>Su</div>
                            <div>Mo</div>
                            <div>Tu</div>
                            <div>We</div>
                            <div>Th</div>
                            <div>Fr</div>
                            <div>Sa</div>
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
                    <button class="wsb-prev-btn wsb-btn" data-prev="wsb-step-staff"><?php echo esc_html($l_prev); ?></button>
                    <button class="wsb-next-btn wsb-btn" data-next="wsb-step-details" disabled><?php echo esc_html($l_next); ?></button>
                </div>
            </div>

            <div class="wsb-wizard-step" id="wsb-step-details" style="display:none;">
                <div class="wsb-step-header">
                    <div class="wsb-step-badge"><?php echo str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                    <div class="wsb-step-details">
                        <h3><?php echo esc_html($clean_label($l_step4)); ?></h3>
                        <p>Tell us a little about yourself to secure your spot.</p>
                    </div>
                </div>
                <div class="wsb-form-card">
                    <div class="wsb-form-container">
                        <div class="wsb-form-grid">
                            <div class="wsb-field-wrap">
                                <label>First Name <span style="color:#ef4444;">*</span></label>
                                <input type="text" placeholder="e.g. John" id="wsb-first-name"
                                    value="<?php echo esc_attr($user_first_name); ?>" required />
                                <span class="wsb-error-msg" id="wsb-error-first-name"></span>
                            </div>
                            <div class="wsb-field-wrap">
                                <label>Last Name <span style="color:#ef4444;">*</span></label>
                                <input type="text" placeholder="e.g. Doe" id="wsb-last-name"
                                    value="<?php echo esc_attr($user_last_name); ?>" required />
                                <span class="wsb-error-msg" id="wsb-error-last-name"></span>
                            </div>
                        </div>

                        <div class="wsb-field-wrap">
                            <label>Email Address <span style="color:#ef4444;">*</span></label>
                            <input type="email" placeholder="john.doe@example.com" id="wsb-email"
                                value="<?php echo esc_attr($user_email); ?>" <?php echo !empty($user_email) ? 'readonly style="background:rgba(255,255,255,0.05); cursor:not-allowed;"' : ''; ?> required />
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
                                <input type="tel" placeholder="(555) 000-0000" id="wsb-phone"
                                    value="<?php echo esc_attr($user_phone); ?>" required />
                            </div>
                            <span class="wsb-error-msg" id="wsb-error-phone"></span>
                        </div>

                        <div class="wsb-field-wrap">
                            <label>Additional Notes</label>
                            <textarea placeholder="Any special requests or details we should know?" rows="4"
                                id="wsb-notes"></textarea>
                        </div>

                        <div class="wsb-account-info-note"
                            style="margin-top: 15px; padding: 15px; background: rgba(99,102,241,0.05); border-left: 4px solid var(--wsb-brand); border-radius: 8px; font-size: 14px; color: var(--wsb-text-muted); line-height: 1.5;">
                            💡 <strong>Note:</strong> An account will be created automatically using your email address. You'll
                            receive temporary login details to securely monitor and manage your bookings.
                        </div>
                    </div>
                </div>

                <div class="wsb-actions">
                    <button class="wsb-prev-btn wsb-btn" data-prev="wsb-step-time"><?php echo esc_html($l_prev); ?></button>
                    <?php $skip_pay = get_option('wsb_skip_payment_step', 'no'); ?>
                    <button class="wsb-next-btn wsb-btn" data-next="wsb-step-payment">
                        <?php echo ($skip_pay === 'yes') ? 'Confirm Booking' : 'Next Step'; ?>
                    </button>
                </div>
            </div>

            <div class="wsb-wizard-step" id="wsb-step-payment"
                style="display:none; padding-top: 10px; justify-content: center;">

                <!-- Payment Selection Panel -->
                <div
                    style="width: 100%; max-width: 700px; background: #ffffff; border: 1px solid var(--wsb-border); padding: 40px; border-radius: 24px; box-shadow: var(--wsb-shadow-sm);">
                    <div class="wsb-step-header">
                        <div class="wsb-step-badge"><?php echo ($skip_pay === 'yes') ? '--' : str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                        <div class="wsb-step-details">
                            <h3>Select Payment Method</h3>
                            <p>Your transaction is secure and encrypted.</p>
                            
                            <div style="margin-top:15px;">
                                <span style="background: #ecfdf5; color: #10b981; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #d1fae5;">
                                    <span style="font-size: 14px;">🛡️</span> 256-bit SSL Secure
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method Selector -->
                    <div id="wsb-payment-methods-wrapper" style="margin-bottom: 35px;">
                        <div class="wsb-payment-methods-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 20px;">
                            
                            <!-- Default Stripe Option -->
                            <?php if (!has_action('wsb_public_payment_methods')): ?>
                            <div class="wsb-payment-method-card active" data-method="stripe_card" 
                                style="border: 2px solid var(--wsb-brand); padding: 25px; border-radius: 20px; cursor: pointer; text-align: center; background: #fff; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.1);">
                                <div style="width: 50px; height: 50px; background: #f1f5f9; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 15px; transition: all 0.3s;">💳</div>
                                <div style="font-weight: 800; color: #0f172a; font-size: 16px; margin-bottom: 4px;">Credit Card</div>
                                <div style="font-size: 12px; color: #64748b; font-weight: 500;">Secure via Stripe</div>
                                <input type="radio" name="payment_method" value="stripe_card" checked style="display:none;">
                                <div class="wsb-method-check" style="position: absolute; top: 12px; right: 12px; width: 22px; height: 22px; background: var(--wsb-brand); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900;">✓</div>
                            </div>
                            <?php endif; ?>

                            <?php do_action('wsb_public_payment_methods'); ?>
                        </div>
                    </div>

                    <!-- Buyer Protection Box -->
                    <div
                        style="margin-top: 30px; background: #eff6ff; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 15px; margin-bottom: 30px;">
                        <span style="font-size: 24px;">🛡️</span>
                        <div>
                            <strong style="color: #1e3a8a; font-size: 14px; display: block;">Buyer Protection</strong>
                            <span style="color: #1e40af; font-size: 13px;">Your purchase is fully protected by secure, advanced
                                fraud monitoring.</span>
                        </div>
                    </div>

                    <div style="display:flex; gap:15px; align-items:center;">
                        <button class="wsb-prev-btn wsb-btn" data-prev="wsb-step-details" style="background:#fff; border:1.5px solid var(--wsb-border); color:var(--wsb-text-muted); padding:20px; border-radius:var(--wsb-radius);"><?php echo esc_html($l_prev); ?></button>
                        <button class="wsb-next-btn wsb-btn" data-next="wsb-step-checkout"
                            style="flex:1; background: var(--wsb-gradient); color: #ffffff; padding: 20px; font-size: 18px; font-weight: 800; border-radius: 16px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 12px; border: none; box-shadow: 0 10px 15px -3px var(--wsb-ring); transition: all 0.3s;">
                            <span>🔒</span> Continue to Secure Payment
                        </button>
                    </div>

                    <div style="text-align: center; margin-top: 15px; font-size: 12px; color: #94a3b8;">
                        By continuing, you agree to our <a href="#"
                            style="color: #64748b; text-decoration: underline;">Terms of Service</a>.
                    </div>
                </div>
            </div>

            <!-- NEW FINAL CHECKOUT STEP -->
            <div class="wsb-wizard-step" id="wsb-step-checkout" style="display:none; gap: 30px; align-items: flex-start; padding-top: 10px; flex-wrap: wrap;">
                <!-- LEFT COLUMN: Final Payment Execution -->
                <div style="flex: 1 1 600px; background: #ffffff; border: 1px solid var(--wsb-border); padding: 30px; border-radius: 12px;">
                    <div class="wsb-step-header">
                        <div class="wsb-step-badge"><?php echo ($skip_pay === 'yes') ? '--' : str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                        <div class="wsb-step-details">
                            <h3>Complete Your Payment</h3>
                            <p>Finalize your booking with our secure gateway.</p>
                            
                            <div style="margin-top:15px;">
                                <span style="background: #ecfdf5; color: #10b981; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #d1fae5;">
                                    <span style="font-size: 14px;">🛡️</span> Encrypted Transaction
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Stripe Payment Element Container -->
                    <div id="wsb-stripe-payment-container" style="margin-top: 10px; display: none;">
                        <div id="wsb-payment-element" style="margin-bottom: 25px;">
                            <!-- Stripe.js injects the Payment Element here -->
                        </div>
                        <div id="wsb-payment-loading" style="text-align: center; padding: 30px; color: #64748b; font-size: 14px; background: #f8fafc; border-radius: 12px; border: 1px dashed var(--wsb-border);">
                            <div class="wsb-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid rgba(0,0,0,0.1); border-top-color: var(--wsb-brand); border-radius: 50%; animation: wsb-spin 0.8s linear infinite; margin-right: 12px; vertical-align: middle;"></div>
                            Preparing your secure payment session...
                        </div>
                        <div id="wsb-stripe-error" style="color: #ef4444; font-size: 13px; margin-top: 10px; display: none; padding: 12px; background: #fef2f2; border-radius: 8px; border: 1px solid #fee2e2;"></div>
                        
                        <button id="wsb-complete-checkout-btn" class="wsb-btn"
                            style="width: 100%; background: var(--wsb-gradient); color: #ffffff; padding: 18px; font-size: 17px; font-weight: 800; border-radius: 16px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 12px; border: none; box-shadow: 0 10px 15px -3px var(--wsb-ring); transition: all 0.3s; margin-top: 20px;">
                            <span>✅</span> Pay & Confirm Booking
                        </button>
                    </div>

                    <!-- PayPal Button Container -->
                    <div id="wsb-paypal-checkout-container" style="display: none; margin-top: 10px; text-align: center; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px dashed var(--wsb-border);">
                        <p style="margin-bottom: 20px; font-weight: 600; color: #475569;">Click the button below to pay with PayPal</p>
                        <div id="wsb-paypal-button-container">
                            <!-- PayPal button will be rendered here -->
                        </div>
                    </div>

                    <div style="margin-top: 30px; display: flex; align-items: center; gap: 10px; padding: 15px; background: #f1f5f9; border-radius: 10px;">
                        <span style="font-size: 18px;">💡</span>
                        <p style="margin: 0; font-size: 13px; color: #475569;">You are one step away! Your professional is reserved once the payment is confirmed.</p>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Summary (Static) -->
                <div style="flex: 0 0 350px; background: #ffffff; border: 1px solid var(--wsb-border); padding: 30px; border-radius: 12px;">
                    <h3 style="margin-top: 0; font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 25px;">Order Summary</h3>
                    
                    <div style="padding: 15px; background: #f8fafc; border-radius: 12px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-weight: 600;">
                            <span id="wsb-checkout-summary-service">Service</span>
                            <span id="wsb-checkout-summary-price">$0.00</span>
                        </div>
                        <div id="wsb-checkout-summary-datetime" style="font-size: 13px; color: #64748b;">Loading details...</div>
                    </div>

                    <div style="display: flex; justify-content: space-between; margin-bottom: 30px; font-weight: 800; font-size: 20px; color: var(--wsb-brand);">
                        <span>Total</span>
                        <span id="wsb-checkout-summary-total">$0.00</span>
                    </div>

                    <button class="wsb-prev-btn wsb-btn" data-prev="wsb-step-payment" style="width: 100%; padding: 14px;"><?php echo esc_html($l_prev); ?> to Methods</button>
                </div>
            </div>

            <div class="wsb-actions" style="margin-top: 30px;">

        </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_client_dashboard()
    {
        $brand_color = get_option('wsb_brand_color', '#6366f1');
        $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
        $accent_color = get_option('wsb_accent_color', '#4f46e5');

        if (!is_user_logged_in()) {
            ob_start();
            ?>
            <style>
                :root {
                    --wsb-brand:
                        <?php echo esc_attr($brand_color); ?>
                    ;
                    --wsb-brand-alt:
                        <?php echo esc_attr($accent_color); ?>
                    ;
                    --wsb-gradient: linear-gradient(135deg,
                            <?php echo esc_attr($brand_color); ?>
                            0%,
                            <?php echo esc_attr($brand_color_end); ?>
                            100%);
                    --wsb-ring:
                        <?php echo esc_attr($brand_color); ?>
                        33;
                }
            </style>
            <div class="wsb-client-login-container"
                style="max-width:480px; margin: 80px auto; padding: 45px 35px; background:#fff; border-radius:24px; border:1px solid var(--wsb-border); box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.1); text-align:center; position:relative; overflow:hidden;">

                <div
                    style="position:absolute; top:-50px; left:-50px; width:120px; height:120px; background:var(--wsb-ring); border-radius:50%; filter:blur(30px); z-index:0;">
                </div>
                <div
                    style="position:absolute; bottom:-50px; right:-50px; width:120px; height:120px; background:var(--wsb-ring); border-radius:50%; filter:blur(30px); z-index:0;">
                </div>

                <div style="position:relative; z-index:1;">
                    <div
                        style="width: 70px; height: 70px; background:var(--wsb-ring); color:var(--wsb-brand); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:30px; margin:0 auto 25px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                        🔐</div>
                    <h3
                        style="margin:0 0 10px; font-size:26px; font-weight:800; color:var(--wsb-text-main); letter-spacing:-0.5px;">
                        Client Dashboard</h3>
                    <p style="color:var(--wsb-text-muted); font-size:15px; line-height:1.6; margin:0 0 35px; padding: 0 10px;">
                        Welcome back! Please sign in securely below to track, modify, or review upcoming appointments.</p>
                    <a href="<?php echo wp_login_url(home_url('/booking-dashboard')); ?>" class="wsb-btn"
                        style="display:inline-block; text-decoration:none; padding: 14px 35px; border-radius: 14px; background:var(--wsb-gradient); color:#fff; font-weight:700; font-size:15px; box-shadow:var(--wsb-shadow-md); transition: transform 0.2s, box-shadow 0.2s; border:none; width:100%; box-sizing:border-box;">Sign
                        In to My Dashboard</a>
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
            "SELECT b.*, st.name as staff_name 
             FROM $booking_table b 
             JOIN $customer_table c ON b.customer_id = c.id
             LEFT JOIN {$wpdb->prefix}wsb_staff st ON b.staff_id = st.id
             WHERE c.email = %s 
             ORDER BY b.booking_date DESC, b.start_time DESC",
            $email
        ));

        // Enqueue service names for bookings
        foreach ($bookings as &$b) {
            $b->service_name = 'Unknown Service';
            if (!empty($b->service_id)) {
                $ids = array_map('intval', explode(',', $b->service_id));
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $services = $wpdb->get_results($wpdb->prepare("SELECT name FROM $services_table WHERE id IN ($placeholders)", $ids));
                if ($services) {
                    $names = array_map(function($s) { return $s->name; }, $services);
                    $b->service_name = implode(', ', $names);
                }
            }
        }
        unset($b);

        ob_start();
        $brand_color = get_option('wsb_brand_color', '#6366f1');
        $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
        $accent_color = get_option('wsb_accent_color', '#4f46e5');
        ?>
        <style>
            :root {
                --wsb-brand:
                    <?php echo esc_attr($brand_color); ?>
                ;
                --wsb-brand-alt:
                    <?php echo esc_attr($accent_color); ?>
                ;
                --wsb-gradient: linear-gradient(135deg,
                        <?php echo esc_attr($brand_color); ?>
                        0%,
                        <?php echo esc_attr($brand_color_end); ?>
                        100%);
                --wsb-ring:
                    <?php echo esc_attr($brand_color); ?>
                    33;
            }
        </style>



        <div class="wsb-client-dash"
            style="max-width: 900px; margin: 40px auto; padding: 35px; background:#fff; border-radius:20px; border:1.5px solid var(--wsb-border); box-shadow:var(--wsb-shadow-md);">

            <?php if (isset($_GET['wsb_payment_confirmed'])): ?>
                <div id="wsb-success-overlay"
                    style="background: #ffffff; border: 1.5px solid #10b981; padding: 40px; border-radius: 24px; margin-bottom: 40px; text-align: center; box-shadow: 0 20px 40px -10px rgba(16, 185, 129, 0.15); position: relative; overflow: hidden;">
                    <button onclick="wsbCloseSuccess()"
                        style="position: absolute; top: 15px; right: 15px; background: none; border: none; color: #10b981; font-size: 24px; cursor: pointer; opacity: 0.5; font-weight: 800;">&times;</button>
                    <div
                        style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: rgba(16, 185, 129, 0.05); border-radius: 50%;">
                    </div>

                    <div
                        style="width: 80px; height: 80px; background: #10b981; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 25px; animation: wsbPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                        ✓</div>

                    <h2 style="margin: 0 0 10px; font-size: 28px; font-weight: 800; color: #064e3b;">Payment Successful!</h2>
                    <p
                        style="color: #065f46; font-size: 16px; margin: 0 0 30px; line-height: 1.6; max-width: 500px; margin-left: auto; margin-right: auto;">
                        Your secure transaction was confirmed. Your appointment has been successfully scheduled and added to your
                        dashboard.</p>

                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <button onclick="wsbCloseSuccess()" class="wsb-btn"
                            style="background: #10b981; border: none; color: #fff; padding: 12px 30px; border-radius: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);">Manage
                            My Bookings</button>
                    </div>
                </div>
            <?php endif; ?>

            <div
                style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 35px; padding-bottom: 20px; border-bottom: 1px solid var(--wsb-border);">
                <h3 style="margin:0; font-size: 24px; font-weight:800;">Welcome Back,
                    <?php echo esc_html($current_user->display_name); ?>!</h3>
                <a href="<?php echo wp_logout_url(get_permalink()); ?>"
                    style="font-size:14px; color:#ef4444; font-weight:600; text-decoration:none;">Logout</a>
            </div>

            <div class="wsb-dash-tabs"
                style="display:flex; gap:15px; border-bottom:1.5px solid var(--wsb-border); margin-bottom: 30px; padding-bottom: 1px;">
                <button class="wsb-dash-tab active" data-target="wsb-dash-bookings"
                    style="background:none; border:none; border-bottom:3px solid var(--wsb-brand); padding: 10px 20px; font-size:16px; font-weight:700; color:var(--wsb-brand); cursor:pointer;">📅
                    My Bookings</button>
                <button class="wsb-dash-tab" data-target="wsb-dash-account"
                    style="background:none; border:none; border-bottom:3px solid transparent; padding: 10px 20px; font-size:16px; font-weight:700; color:var(--wsb-text-muted); cursor:pointer;">⚙️
                    Account Details</button>
            </div>

            <div id="wsb-dash-bookings" class="wsb-dash-content-panel">

                <?php if (empty($bookings)): ?>
                    <div style="text-align:center; padding:40px 0; color:var(--wsb-text-muted);">
                        <p>You haven't made any appointments yet.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; text-align:left;">
                            <thead>
                                <tr
                                    style="border-bottom:2px solid var(--wsb-border); color:var(--wsb-text-muted); font-size:14px; font-weight:700;">
                                    <th style="padding:12px 15px;">Booking ID</th>
                                    <th style="padding:12px 15px;">Service</th>
                                    <th style="padding:12px 15px;">Date & Time</th>
                                    <th style="padding:12px 15px;">Amount</th>
                                    <th style="padding:12px 15px;">Status</th>
                                    <th style="padding:12px 15px; text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $b): ?>
                                    <tr class="wsb-booking-row" data-id="<?php echo esc_attr($b->id); ?>"
                                        data-service="<?php echo esc_attr($b->service_name ?: 'Custom Service'); ?>"
                                        data-staff="<?php echo esc_attr($b->staff_name ?: 'Assigned Professional'); ?>"
                                        data-date="<?php echo esc_attr(date('M d, Y', strtotime($b->booking_date))); ?>"
                                        data-time="<?php echo esc_attr(date('h:i A', strtotime($b->start_time))); ?>"
                                        data-amount="<?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')) . esc_attr($b->total_amount); ?>"
                                        data-status="<?php echo esc_attr(ucfirst($b->status)); ?>"
                                        style="border-bottom:1px solid var(--wsb-border); font-size:15px; color:var(--wsb-text-main); cursor:pointer;">
                                        <td data-label="Booking ID" style="padding:15px; font-weight:700;">
                                            #<?php echo esc_html($b->id); ?></td>
                                        <td data-label="Service" style="padding:15px; font-weight:600;">
                                            <?php echo esc_html($b->service_name ?: 'Custom Service'); ?></td>
                                        <td data-label="Date & Time" style="padding:15px; color:var(--wsb-text-muted);">
                                            <?php echo esc_html(date('M d, Y', strtotime($b->booking_date))); ?> @
                                            <?php echo esc_html(date('h:i A', strtotime($b->start_time))); ?></td>
                                        <td data-label="Amount" style="padding:15px; font-weight:600;">
                                            <?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?>                <?php echo esc_html($b->total_amount); ?>
                                        </td>
                                        <td data-label="Status" style="padding:15px;">
                                            <span style="padding:5px 12px; border-radius:20px; font-size:12px; font-weight:700; text-transform:uppercase; 
                                        <?php
                                        if ($b->status === 'confirmed' || $b->status === 'completed')
                                            echo 'background:rgba(16, 185, 129, 0.1); color:#10b981;';
                                        elseif ($b->status === 'cancelled')
                                            echo 'background:rgba(239, 68, 68, 0.1); color:#ef4444;';
                                        else
                                            echo 'background:rgba(245, 158, 11, 0.1); color:#f59e0b;';
                                        ?>">
                                                <?php echo esc_html($b->status); ?>
                                            </span>
                                        </td>
                                        <td style="padding:15px; text-align:right;">
                                            <div style="display:flex; justify-content:flex-end; gap:8px; align-items:center;">
                                                <?php if ($b->status !== 'cancelled'): ?>
                                                    <button class="wsb-client-action-btn" data-action="reschedule"
                                                        data-id="<?php echo esc_attr($b->id); ?>"
                                                        style="background:rgba(99, 102, 241, 0.08); color:var(--wsb-brand); border:1px solid rgba(99, 102, 241, 0.2); padding:8px 15px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s ease;">Reschedule</button>
                                                    <button class="wsb-client-action-btn" data-action="cancel"
                                                        data-id="<?php echo esc_attr($b->id); ?>"
                                                        style="background:rgba(239, 68, 68, 0.05); color:#ef4444; border:1px solid rgba(239, 68, 68, 0.2); padding:8px 15px; border-radius:10px; font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s ease;">Cancel</button>
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
                    <div class="wsb-form-grid"
                        style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div class="wsb-form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">First Name <span
                                    style="color:#ef4444;">*</span></label>
                            <input type="text" name="first_name" value="<?php echo esc_attr($current_user->first_name); ?>"
                                required
                                style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px;">
                        </div>
                        <div class="wsb-form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Last Name <span
                                    style="color:#ef4444;">*</span></label>
                            <input type="text" name="last_name" value="<?php echo esc_attr($current_user->last_name); ?>"
                                required
                                style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px;">
                        </div>
                    </div>

                    <div class="wsb-form-grid"
                        style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 20px;">
                        <div class="wsb-form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Email <span
                                    style="color:#ef4444;">*</span></label>
                            <input type="email" name="email" value="<?php echo esc_attr($current_user->user_email); ?>" disabled
                                style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px; background:#f8fafc; cursor:not-allowed;">
                        </div>
                        <div class="wsb-form-group">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Phone
                                Number</label>
                            <input type="text" name="phone"
                                value="<?php echo esc_attr(get_user_meta($current_user->ID, 'wsb_client_phone', true)); ?>"
                                style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px;">
                        </div>
                    </div>

                    <div class="wsb-form-group" style="margin-bottom: 20px;">
                        <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Residential
                            Address</label>
                        <textarea name="address" rows="3"
                            style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px; resize:vertical;"><?php echo esc_textarea(get_user_meta($current_user->ID, 'wsb_client_address', true)); ?></textarea>
                    </div>

                    <div class="wsb-form-group" style="margin-bottom: 25px;">
                        <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px;">Change Password <span
                                style="color:var(--wsb-text-muted); font-weight:normal; font-size:13px;">(Leave blank to keep
                                current)</span></label>
                        <input type="password" name="password" placeholder="••••••••"
                            style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:10px; font-size:15px;">
                    </div>

                    <button type="submit" class="wsb-btn wsb-next-btn"
                        style="padding: 12px 30px; border-radius:10px; border:none; font-weight:700; cursor:pointer; background:var(--wsb-gradient); color:#fff;">Save
                        Changes</button>
                    <div id="wsb-account-msg" style="margin-top: 15px; font-size:14px; display:none; font-weight:600;"></div>
                </form>
            </div>
        </div>

        <!-- Booking Details Modal -->
        <div id="wsb-details-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.5); backdrop-filter:blur(8px); z-index:99999; align-items:center; justify-content:center;">
            <div
                style="background:#fff; padding:40px; border-radius:24px; width:100%; max-width:520px; position:relative; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); margin: auto 20px;">
                <span class="wsb-modal-close"
                    style="position:absolute; top:25px; right:25px; font-size:28px; font-weight:bold; color:var(--wsb-text-muted); cursor:pointer; line-height:1; transition:color 0.2s;">&times;</span>

                <div style="display:flex; align-items:center; gap:15px; margin-bottom:30px;">
                    <div
                        style="width:50px; height:50px; background:var(--wsb-ring); color:var(--wsb-brand); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:24px;">
                        📋</div>
                    <h3 style="margin:0; font-size:22px; font-weight:800; color:var(--wsb-text-main);">Booking Details</h3>
                </div>

                <div
                    style="display:grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; padding-bottom:20px; border-bottom:1px solid var(--wsb-border);">
                    <div>
                        <label
                            style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Booking
                            ID</label>
                        <div id="wsb-modal-id" style="font-size:18px; font-weight:800; margin-top:5px; color:var(--wsb-brand);">
                        </div>
                    </div>
                    <div>
                        <label
                            style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Amount</label>
                        <div id="wsb-modal-amount"
                            style="font-size:18px; font-weight:800; margin-top:5px; color:var(--wsb-text-main);"></div>
                    </div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label
                        style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Service</label>
                    <div id="wsb-modal-service"
                        style="font-size:16px; font-weight:700; margin-top:5px; color:var(--wsb-text-main);"></div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label
                        style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Assigned
                        Professional</label>
                    <div id="wsb-modal-staff"
                        style="font-size:16px; font-weight:600; margin-top:5px; color:var(--wsb-text-main);"></div>
                </div>

                <div style="margin-bottom: 25px;">
                    <label
                        style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Date
                        & Time</label>
                    <div id="wsb-modal-datetime"
                        style="font-size:16px; font-weight:600; margin-top:5px; color:var(--wsb-text-main);"></div>
                </div>

                <div>
                    <label
                        style="font-size:12px; color:var(--wsb-text-muted); font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Status</label>
                    <div style="margin-top:8px;">
                        <span id="wsb-modal-status"
                            style="padding:6px 14px; border-radius:20px; font-size:13px; font-weight:700; text-transform:uppercase;"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reschedule Request Modal -->
        <div id="wsb-reschedule-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.5); backdrop-filter:blur(8px); z-index:99999; align-items:center; justify-content:center;">
            <div
                style="background:#fff; padding:40px; border-radius:24px; width:100%; max-width:520px; position:relative; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); margin: auto 20px;">
                <span class="wsb-reschedule-close"
                    style="position:absolute; top:25px; right:25px; font-size:28px; font-weight:bold; color:var(--wsb-text-muted); cursor:pointer; line-height:1; transition:color 0.2s;">&times;</span>

                <div style="display:flex; align-items:center; gap:15px; margin-bottom:30px;">
                    <div
                        style="width:50px; height:50px; background:var(--wsb-ring); color:var(--wsb-brand); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:24px;">
                        📅</div>
                    <h3 style="margin:0; font-size:22px; font-weight:800; color:var(--wsb-text-main);">Reschedule Appointment
                    </h3>
                </div>

                <form id="wsb-reschedule-form">
                    <input type="hidden" name="booking_id" id="wsb-reschedule-id">

                    <div style="margin-bottom:20px;">
                        <label
                            style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; text-transform:uppercase; color:var(--wsb-text-muted); letter-spacing:0.5px;">Choose
                            a Professional</label>
                        <select name="reschedule_staff" id="wsb-reschedule-staff"
                            style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:12px; font-size:15px; background:#fff;"
                            required>
                            <option value="">-- Select Professional --</option>
                            <?php foreach ($all_staff as $staff): ?>
                                <option value="<?php echo esc_attr($staff->id); ?>"><?php echo esc_html($staff->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label
                            style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; text-transform:uppercase; color:var(--wsb-text-muted); letter-spacing:0.5px;">Pick
                            a Date</label>
                        <input type="date" name="reschedule_date" id="wsb-reschedule-date" min="<?php echo date('Y-m-d'); ?>"
                            style="width:100%; padding:12px; border:1.5px solid var(--wsb-border); border-radius:12px; font-size:15px;"
                            required>
                    </div>

                    <div id="wsb-reschedule-slots-container" style="display:none; margin-bottom:30px;">
                        <label
                            style="display:block; margin-bottom:8px; font-weight:700; font-size:13px; text-transform:uppercase; color:var(--wsb-text-muted); letter-spacing:0.5px;">Available
                            Time Slots</label>
                        <div class="wsb-reschedule-slots"
                            style="display:grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap:10px; max-height: 150px; overflow-y: auto; padding: 5px;">
                            <!-- Loaded via AJAX -->
                        </div>
                        <input type="hidden" name="reschedule_time" id="wsb-reschedule-time-input" required>
                        <div id="wsb-reschedule-time-error"
                            style="color:#ef4444; font-size:13px; margin-top:5px; display:none;">Please select a time slot.
                        </div>
                    </div>

                    <div id="wsb-reschedule-msg" style="margin-bottom:15px; font-size:14px; font-weight:600; display:none;">
                    </div>

                    <button type="submit" class="wsb-btn"
                        style="display:block; width:100%; padding:14px; border:none; border-radius:14px; background:var(--wsb-gradient); color:#fff; font-weight:700; font-size:16px; cursor:pointer; box-shadow:var(--wsb-shadow-md);">Request
                        Reschedule</button>
                </form>
            </div>
        </div>

        <!-- Cancel Booking Modal -->
        <div id="wsb-cancel-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.5); backdrop-filter:blur(8px); z-index:99999; align-items:center; justify-content:center;">
            <div
                style="background:#fff; padding:40px; border-radius:24px; width:100%; max-width:450px; position:relative; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); margin: auto 20px; text-align:center;">
                <span class="wsb-cancel-close"
                    style="position:absolute; top:25px; right:25px; font-size:28px; font-weight:bold; color:var(--wsb-text-muted); cursor:pointer; line-height:1;">&times;</span>

                <div
                    style="width:70px; height:70px; background:rgba(239, 68, 68, 0.1); color:#ef4444; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:32px; margin:0 auto 25px;">
                    ⚠️</div>

                <h3 style="margin:0 0 10px; font-size:22px; font-weight:800; color:var(--wsb-text-main);">Request Cancellation
                </h3>
                <p style="color:var(--wsb-text-muted); font-size:15px; line-height:1.6; margin:0 0 30px;">Are you sure you want
                    to request cancellation for appointment <strong id="wsb-cancel-title-id"
                        style="color:var(--wsb-text-main);"></strong>? This request is subject to administrative review.</p>

                <form id="wsb-cancel-form">
                    <input type="hidden" name="booking_id" id="wsb-cancel-id">
                    <div id="wsb-cancel-msg" style="margin-bottom:15px; font-size:14px; font-weight:600; display:none;"></div>

                    <div style="display:flex; gap:15px; justify-content:center;">
                        <button type="button" class="wsb-cancel-close"
                            style="flex:1; padding:14px; border:1.5px solid var(--wsb-border); border-radius:12px; background:#fff; color:var(--wsb-text-main); font-weight:700; font-size:15px; cursor:pointer; transition:all 0.2s;">Keep
                            Booking</button>
                        <button type="submit" class="wsb-btn"
                            style="flex:1; padding:14px; border:none; border-radius:12px; background:#ef4444; color:#fff; font-weight:700; font-size:15px; cursor:pointer; box-shadow:var(--wsb-shadow-sm);">Request
                            Cancellation</button>
                    </div>
                </form>
            </div>
        </div>

        <div style="text-align:center; margin: 35px auto;">
            <a href="<?php echo esc_url(home_url('/booking')); ?>" class="wsb-btn"
                style="display:inline-block; text-decoration:none; padding: 14px 40px; border-radius: 12px; background:var(--wsb-gradient); color:#fff; font-weight:700; font-size:16px; box-shadow:var(--wsb-shadow-md);">Book
                a Service</a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Virtual route for /booking
     */
    public function virtual_booking_route()
    {
        $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = trim($request_uri, '/');
        $parts = explode('/', $path);
        $slug = end($parts);

        if ($slug === 'booking' || $slug === 'booking-dashboard') {
            get_header();
            $brand_color = get_option('wsb_brand_color', '#6366f1');
            $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
            $virtual_bg_color = get_option('wsb_virtual_bg_color', '#f8fafc');
            ?>
            <div class="wsb-virtual-wrapper" style="padding: 60px 20px; background: #f1f5f9; min-height: 80vh; display: flex; justify-content: center; align-items: flex-start;">
                <div class="wsb-virtual-page" style="width: 100%; max-width: 900px; background: <?php echo esc_attr($virtual_bg_color); ?>; padding: 40px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); border: 1px solid rgba(0, 0, 0, 0.05);">
                    <style>
                        #wsb-booking-wizard-container { margin: 0; padding: 0; background: transparent; border: none; box-shadow: none; }
                        @media (max-width: 768px) { .wsb-virtual-wrapper { padding: 20px 10px; } .wsb-virtual-page { padding: 20px; } }
                    </style>
                    <?php
                    if ($slug === 'booking-dashboard') {
                        echo $this->render_client_dashboard();
                    } else {
                        echo $this->render_booking_widget(array());
                    }
                    ?>
                </div>
            </div>
            <?php
            get_footer();
            exit;
        }
    }
    public function wsb_login_redirect($redirect_to, $request, $user)
    {

        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('subscriber', $user->roles)) {
                return home_url('/booking-dashboard');
            }
        }
        return $redirect_to;
    }

    public function wsb_restrict_admin_access()
    {
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
    public function wsb_logout_redirect($redirect_to, $requested_redirect_to, $user)
    {
        return home_url();
    }

    /**
     * Render standalone services showcase widget
     */
    public function render_services_widget($atts)
    {
        $atts = shortcode_atts(array(
            'category' => '',
            'ids'      => '',
            'layout'   => '', // Override global layout
        ), $atts, 'wsb_services');

        global $wpdb;
        $query = "SELECT * FROM {$wpdb->prefix}wsb_services WHERE status = 'active'";
        
        if (!empty($atts['category'])) {
            $query .= $wpdb->prepare(" AND category = %s", $atts['category']);
        }
        
        if (!empty($atts['ids'])) {
            $ids = explode(',', $atts['ids']);
            $ids = array_map('intval', $ids);
            $query .= " AND id IN (" . implode(',', $ids) . ")";
        }

        $services = $wpdb->get_results($query);

        if (empty($services)) {
            return '<p style="text-align:center; color:var(--wsb-text-muted);">No services found matching your criteria.</p>';
        }

        // Fetch styling preferences
        $brand_color = get_option('wsb_brand_color', '#6366f1');
        $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
        $font_family = get_option('wsb_font_family', 'Inter');
        $border_radius = get_option('wsb_border_radius', '16');
        $layout = !empty($atts['layout']) ? $atts['layout'] : get_option('wsb_showcase_layout', 'grid');
        
        $card_bg = get_option('wsb_card_bg_color', '#ffffff');
        $heading_color = get_option('wsb_heading_text_color', '#0f172a');
        $body_color = get_option('wsb_body_text_color', '#64748b');

        ob_start();
        ?>
        <style>
            .wsb-services-showcase, .wsb-services-showcase * { box-sizing: border-box; }
            .wsb-services-showcase { margin: 40px 0; font-family: 'Inter', sans-serif; }
            
            /* Showcase Layout Engine */
            .wsb-showcase-container {
                display: grid;
                gap: 25px;
                width: 100%;
            }

            .wsb-layout-grid .wsb-showcase-container {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }

            /* Kinetic Carousel - High-End Design */
            .wsb-layout-carousel {
                overflow: hidden;
                position: relative;
                padding: 20px 0 60px 0;
                width: 100%;
                max-width: 1300px; /* Max width for 4 cards + gaps */
                margin-left: auto;
                margin-right: auto;
            }
            .wsb-layout-carousel .wsb-showcase-container {
                display: flex;
                gap: 25px;
                flex-wrap: nowrap;
                transition: transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
                will-change: transform;
                cursor: grab;
                user-select: none;
                width: max-content !important;
            }
            .wsb-layout-carousel .wsb-showcase-container:active { cursor: grabbing; }
            
            .wsb-layout-carousel .wsb-showcase-card {
                width: 300px; /* Fixed Width */
                flex: 0 0 300px;
                height: 480px; /* Fixed Height */
                transition: all 0.5s ease;
                margin: 0 !important;
            }
            
            /* Section Responsiveness based on Card Count */
            @media (max-width: 1350px) {
                .wsb-layout-carousel { max-width: 950px; } /* 3 items */
            }
            @media (max-width: 1000px) {
                .wsb-layout-carousel { max-width: 625px; } /* 2 items */
            }
            @media (max-width: 650px) {
                .wsb-layout-carousel { max-width: 300px; } /* 1 item */
            }

            /* Carousel Navigation Arrows - Corner Positioned */
            .wsb-carousel-nav {
                position: absolute;
                top: 50%;
                left: -25px;
                right: -25px;
                transform: translateY(-100%); /* Shift up slightly for better visual center */
                display: flex;
                justify-content: space-between;
                pointer-events: none;
                z-index: 10;
            }
            .wsb-nav-btn {
                width: 50px;
                height: 50px;
                background: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                pointer-events: auto;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                border: 1px solid #f1f5f9;
                color: <?php echo esc_attr($brand_color); ?>;
            }
            .wsb-nav-btn:hover {
                transform: scale(1.15);
                box-shadow: 0 15px 30px rgba(0,0,0,0.15);
                color: <?php echo esc_attr($brand_color_end); ?>;
            }
            .wsb-nav-btn.prev { margin-left: 10px; }
            .wsb-nav-btn.next { margin-right: 10px; }

            @media (max-width: 1400px) {
                .wsb-carousel-nav { left: 0; right: 0; }
                .wsb-nav-btn { width: 40px; height: 40px; }
            }

            /* Dots - Stylish */
            .wsb-carousel-dots {
                display: flex;
                justify-content: center;
                gap: 12px;
                margin-top: 40px;
            }
            .wsb-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: #e2e8f0;
                cursor: pointer;
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }
            .wsb-dot.active {
                background: linear-gradient(135deg, <?php echo esc_attr($brand_color); ?>, <?php echo esc_attr($brand_color_end); ?>);
                width: 35px;
                border-radius: 20px;
            }

            .wsb-showcase-card {
                background: <?php echo esc_attr($card_bg); ?>;
                border: 1px solid #e2e8f0;
                border-radius: <?php echo esc_attr($border_radius); ?>px;
                overflow: hidden;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex;
                flex-direction: column;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            }
            .wsb-showcase-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.1);
                border-color: <?php echo esc_attr($brand_color); ?>44;
            }
            .wsb-showcase-img {
                height: 220px;
                background-position: center;
                background-size: cover;
                position: relative;
                overflow: hidden;
                background-color: #f8fafc;
            }
            .wsb-showcase-card:hover .wsb-showcase-img { transform: scale(1.05); }
            .wsb-showcase-badge {
                position: absolute;
                top: 15px;
                left: 15px;
                background: rgba(255,255,255,0.95);
                padding: 5px 12px;
                border-radius: 30px;
                font-size: 10px;
                font-weight: 800;
                color: <?php echo esc_attr($brand_color); ?>;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                z-index: 2;
            }
            .wsb-showcase-content {
                padding: 24px;
                flex: 1;
                display: flex;
                flex-direction: column;
            }
            .wsb-showcase-title {
                margin: 0 0 12px 0;
                font-size: 20px;
                font-weight: 800;
                color: <?php echo esc_attr($heading_color); ?>;
                letter-spacing: -0.01em;
            }
            .wsb-showcase-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                font-size: 14px;
                font-weight: 600;
                color: <?php echo esc_attr($body_color); ?>;
            }
            .wsb-showcase-price {
                color: <?php echo esc_attr($brand_color); ?>;
                font-size: 18px;
                font-weight: 800;
            }
            .wsb-showcase-desc {
                font-size: 14px;
                line-height: 1.6;
                margin-bottom: 25px;
                color: <?php echo esc_attr($body_color); ?>;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .wsb-showcase-btn {
                display: block;
                text-align: center;
                background: linear-gradient(135deg, <?php echo esc_attr($brand_color); ?> 0%, <?php echo esc_attr($brand_color_end); ?> 100%);
                color: white;
                text-decoration: none;
                padding: 14px;
                border-radius: 14px;
                font-weight: 700;
                font-size: 14px;
                transition: all 0.3s;
                box-shadow: 0 4px 12px <?php echo esc_attr($brand_color); ?>33;
            }
            .wsb-showcase-btn:hover {
                filter: brightness(1.1);
                box-shadow: 0 8px 20px <?php echo esc_attr($brand_color); ?>44;
                color: white;
            }

            @media (max-width: 768px) {
                .wsb-layout-horizontal .wsb-showcase-card,
                .wsb-layout-list .wsb-showcase-card {
                    flex-direction: column;
                    height: auto;
                }
                .wsb-layout-horizontal .wsb-showcase-img,
                .wsb-layout-list .wsb-showcase-img {
                    width: 100%;
                    height: 180px;
                }
                .wsb-layout-list .wsb-showcase-content {
                    padding: 20px;
                    flex-direction: column;
                    align-items: stretch;
                }
                .wsb-layout-list .wsb-showcase-title { margin-bottom: 10px; }
                .wsb-layout-list .wsb-showcase-meta { margin-bottom: 15px; }
            }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const wrappers = document.querySelectorAll('.wsb-layout-carousel');
            wrappers.forEach(wrapper => {
                const container = wrapper.querySelector('.wsb-showcase-container');
                const cards = container.querySelectorAll('.wsb-showcase-card');
                if (cards.length === 0) return;

                // Create Nav Arrows
                const nav = document.createElement('div');
                nav.className = 'wsb-carousel-nav';
                nav.innerHTML = `
                    <div class="wsb-nav-btn prev"><span class="dashicons dashicons-arrow-left-alt2"></span></div>
                    <div class="wsb-nav-btn next"><span class="dashicons dashicons-arrow-right-alt2"></span></div>
                `;
                wrapper.appendChild(nav);
                
                nav.querySelector('.prev').addEventListener('click', () => { clearInterval(autoSlideInterval); goToSlide(currentIndex - 1); startAutoSlide(); });
                nav.querySelector('.next').addEventListener('click', () => { clearInterval(autoSlideInterval); goToSlide(currentIndex + 1); startAutoSlide(); });

                // Create Dots
                const dotsContainer = document.createElement('div');
                dotsContainer.className = 'wsb-carousel-dots';
                cards.forEach((_, i) => {
                    const dot = document.createElement('div');
                    dot.className = 'wsb-dot' + (i === 0 ? ' active' : '');
                    dot.addEventListener('click', () => { clearInterval(autoSlideInterval); goToSlide(i); startAutoSlide(); });
                    dotsContainer.appendChild(dot);
                });
                wrapper.appendChild(dotsContainer);
                const dots = dotsContainer.querySelectorAll('.wsb-dot');

                let currentIndex = 0;
                let startX = 0;
                let currentTranslate = 0;
                let prevTranslate = 0;
                let isDragging = false;
                let autoSlideInterval;

                const updateDots = () => {
                    dots.forEach((dot, i) => dot.classList.toggle('active', i === currentIndex));
                    cards.forEach((card, i) => card.classList.toggle('active-slide', i === currentIndex));
                };

                const getSlideWidth = () => {
                    const card = cards[0];
                    const gap = 25;
                    return card.getBoundingClientRect().width + gap; 
                };

                const goToSlide = (index) => {
                    currentIndex = index;
                    if (currentIndex < 0) currentIndex = cards.length - 1;
                    if (currentIndex > cards.length - 1) currentIndex = 0;
                    
                    currentTranslate = currentIndex * -getSlideWidth();
                    prevTranslate = currentTranslate;
                    container.style.transform = `translateX(${currentTranslate}px)`;
                    updateDots();
                };

                const autoSlide = () => {
                    currentIndex = (currentIndex + 1) % cards.length;
                    goToSlide(currentIndex);
                };

                const startAutoSlide = () => {
                    clearInterval(autoSlideInterval);
                    autoSlideInterval = setInterval(autoSlide, 5000);
                };

                updateDots();
                startAutoSlide();

                // Drag functionality
                container.addEventListener('mousedown', dragStart);
                container.addEventListener('touchstart', dragStart, {passive: true});
                container.addEventListener('mouseup', dragEnd);
                container.addEventListener('touchend', dragEnd);
                container.addEventListener('mousemove', dragAction);
                container.addEventListener('touchmove', dragAction, {passive: true});
                container.addEventListener('mouseleave', dragEnd);

                function dragStart(e) {
                    isDragging = true;
                    startX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
                    clearInterval(autoSlideInterval);
                    container.style.transition = 'none';
                }

                function dragAction(e) {
                    if (!isDragging) return;
                    const currentX = e.type.includes('touch') ? e.touches[0].clientX : e.clientX;
                    const diff = currentX - startX;
                    container.style.transform = `translateX(${prevTranslate + diff}px)`;
                }

                function dragEnd(e) {
                    if (!isDragging) return;
                    isDragging = false;
                    container.style.transition = 'transform 0.8s cubic-bezier(0.16, 1, 0.3, 1)';
                    
                    const endX = e.type.includes('touch') ? e.changedTouches[0].clientX : e.clientX;
                    const diff = endX - startX;

                    if (Math.abs(diff) > 70) {
                        if (diff > 0) goToSlide(currentIndex - 1);
                        else goToSlide(currentIndex + 1);
                    } else {
                        goToSlide(currentIndex);
                    }
                    startAutoSlide();
                }

                // Window resize support
                window.addEventListener('resize', () => goToSlide(currentIndex));
            });
        });
        </script>
        
        <div class="wsb-services-showcase wsb-layout-<?php echo esc_attr($layout); ?>">
            <div class="wsb-showcase-container">
                <?php foreach ($services as $s): ?>
                    <div class="wsb-showcase-card">
                        <div class="wsb-showcase-img" style="background-image: url('<?php echo esc_url($s->image_url ?: WSB_PLUGIN_URL . 'assets/public/img/service-placeholder.jpg'); ?>');">
                            <?php if ($s->category): ?>
                                <span class="wsb-showcase-badge"><?php echo esc_html($s->category); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="wsb-showcase-content">
                            <div>
                                <h4 class="wsb-showcase-title"><?php echo esc_html($s->name); ?></h4>
                                <div class="wsb-showcase-meta">
                                    <span><span class="dashicons dashicons-clock" style="font-size:14px; width:14px; height:14px; vertical-align:middle; margin-right:4px;"></span> <?php echo esc_html($s->duration); ?> min</span>
                                    <span class="wsb-showcase-price"><?php echo wsb_get_currency_symbol(get_option('wsb_currency', 'USD')); ?><?php echo esc_html($s->price); ?></span>
                                </div>
                                <p class="wsb-showcase-desc"><?php echo esc_html(wp_trim_words($s->description, 15)); ?></p>
                            </div>
                            <?php
                            global $wpdb;
                            $page_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[wsb_booking_widget]%' AND post_status = 'publish' LIMIT 1");
                            $booking_url = $page_id ? get_permalink($page_id) : home_url('/booking');
                            ?>
                            <a href="<?php echo esc_url(add_query_arg(['wsb_select_service' => $s->id, 'wsb_jump_to_staff' => '1'], $booking_url)); ?>" class="wsb-showcase-btn">Book Appointment</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render basket widget shortcode
     */
    public function render_basket_shortcode($atts) {
        $l_basket = get_option('wsb_label_basket_btn', 'Services Selected');
        $i_basket = get_option('wsb_icon_basket_btn', 'dashicons-cart');
        
        ob_start();
        ?>
        <div class="wsb-standalone-basket" style="display:inline-block; position:relative;">
            <div id="wsb-basket-trigger" class="wsb-basket-trigger-btn wsb-btn"
                style="position:relative; display:inline-flex; align-items:center; background:var(--wsb-gradient); color:#fff; cursor:pointer; font-size: 13px; padding: 10px 26px; border-radius: 12px; font-weight: 700; transition:all 0.3s; gap:12px; box-shadow: 0 4px 12px var(--wsb-ring);">
                <div style="position:relative; display:flex; align-items:center; justify-content:center;">
                    <span class="dashicons <?php echo esc_attr($i_basket); ?>" style="font-size:20px; width:20px; height:20px;"></span> 
                    <span class="wsb-basket-count-val" style="position:absolute; top:-10px; right:-12px; background:var(--wsb-brand-alt); color:#fff; min-width:18px; height:18px; border-radius:10px; font-size:10px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff; font-weight:900; line-height:1; box-shadow:0 2px 4px rgba(0,0,0,0.1);">0</span>
                </div>
                <span><?php echo esc_html($l_basket); ?></span>
                
                <!-- Basket Popup -->
                <div id="wsb-basket-popup" style="display:none; position:absolute; top:calc(100% + 15px); right:0; width:320px; background:#fff; border-radius:16px; border:1px solid var(--wsb-input-border); box-shadow:0 20px 40px rgba(0,0,0,0.2); z-index:999999; padding:20px; text-align:left; cursor:default;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--wsb-input-border); padding-bottom:10px;">
                        <h4 style="margin:0; font-size:16px; font-weight:800; color:var(--wsb-heading);">Your Selection</h4>
                        <span id="wsb-close-basket" style="font-size:20px; cursor:pointer; color:var(--wsb-body);">&times;</span>
                    </div>
                    <div id="wsb-basket-items" style="max-height:250px; overflow-y:auto; margin-bottom:15px; display:flex; flex-direction:column; gap:10px;">
                        <p id="wsb-empty-basket-msg" style="text-align:center; color:var(--wsb-body); opacity:0.6; font-size:14px; margin:20px 0;">No services selected yet.</p>
                    </div>
                    <div id="wsb-basket-footer" style="border-top:1px solid var(--wsb-input-border); padding-top:15px; display:none;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-weight:800; color:var(--wsb-heading);">
                            <span>Total</span>
                            <span id="wsb-basket-total">0.00</span>
                        </div>
                        <?php
                        global $wpdb;
                        $page_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[wsb_booking_widget]%' AND post_status = 'publish' LIMIT 1");
                        $booking_url = $page_id ? get_permalink($page_id) : home_url('/booking');
                        ?>
                        <a href="<?php echo esc_url(add_query_arg('wsb_jump_to_staff', '1', $booking_url)); ?>" class="wsb-btn wsb-next-btn wsb-basket-checkout-btn" style="width:100%; padding:12px; text-decoration:none; text-align:center;">Continue Booking</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Add basket to WordPress menu
     */
    public function add_basket_to_menu($items, $args) {
        $enabled = get_option('wsb_menu_basket_enable', 'no');
        if ($enabled !== 'yes') return $items;

        $text = get_option('wsb_menu_basket_text', 'Selection');
        $icon = get_option('wsb_menu_basket_icon', 'dashicons-cart');
        $pos = get_option('wsb_menu_basket_pos', 'after');

        $basket_html = '
        <li class="menu-item wsb-menu-basket-wrap" style="position:relative; display:inline-flex; align-items:center;">
            <a href="#" class="wsb-basket-trigger-btn" style="display:flex; align-items:center; gap:8px; text-decoration:none;">
                <div style="position:relative; display:flex; align-items:center; justify-content:center;">
                    <span class="dashicons ' . esc_attr($icon) . '"></span>
                    <span class="wsb-basket-count-val" style="position:absolute; top:-8px; right:-10px; background:var(--wsb-brand-alt, #ef4444); color:#fff; min-width:16px; height:16px; border-radius:50%; font-size:9px; display:flex; align-items:center; justify-content:center; padding:0 3px; border:1px solid #fff; font-weight:900;">0</span>
                </div>
                ' . ( !empty($text) ? '<span style="margin-left:5px;">' . esc_html($text) . '</span>' : '' ) . '
            </a>
            <div id="wsb-basket-popup" style="display:none; position:absolute; top:100%; right:0; width:300px; background:#fff; border-radius:12px; border:1px solid #eee; box-shadow:0 15px 30px rgba(0,0,0,0.1); z-index:99999; padding:15px; text-align:left; cursor:default; color:#333;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #eee; padding-bottom:8px;">
                    <h4 style="margin:0; font-size:14px; font-weight:800;">Your Selection</h4>
                </div>
                <div id="wsb-basket-items" style="max-height:200px; overflow-y:auto; margin-bottom:12px; display:flex; flex-direction:column; gap:8px;">
                    <p id="wsb-empty-basket-msg" style="text-align:center; font-size:12px; opacity:0.6; margin:15px 0;">Empty</p>
                </div>
                <div id="wsb-basket-footer" style="display:none;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-weight:800; font-size:13px;">
                        <span>Total</span>
                        <span id="wsb-basket-total">0.00</span>
                    </div>
                    <a href="' . esc_url(add_query_arg('wsb_jump_to_staff', '1', home_url('/booking'))) . '" class="wsb-basket-checkout-btn" style="display:block; background:var(--wsb-brand, #6366f1); color:#fff; text-align:center; padding:10px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:700;">Book Now</a>
                </div>
            </div>
        </li>';

        if ($pos === 'before') {
            return $basket_html . $items;
        } else {
            return $items . $basket_html;
        }
    }

    /**
     * Render floating booking button
     */
    public function render_floating_booking_btn() {
        $enabled = get_option('wsb_float_btn_enable', 'no');
        if ($enabled !== 'yes') return;

        $pos = get_option('wsb_float_btn_pos', 'bottom-right');
        $icon = get_option('wsb_float_btn_icon', 'dashicons-calendar-alt');
        
        $side = ($pos === 'bottom-left') ? 'left: 30px;' : 'right: 30px;';
        
        global $wpdb;
        $page_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[wsb_booking_widget]%' AND post_status = 'publish' LIMIT 1");
        $booking_url = $page_id ? get_permalink($page_id) : home_url('/booking');

        echo '
        <div class="wsb-standalone-basket wsb-floating-basket-wrap" style="position: fixed; bottom: 30px; ' . $side . ' z-index: 99999;">
            <div class="wsb-basket-trigger-btn" style="width: 60px; height: 60px; background: var(--wsb-gradient, linear-gradient(135deg, #6366f1 0%, #a855f7 100%)); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4); position: relative; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                <span class="dashicons ' . esc_attr($icon) . '" style="font-size: 24px; width: 24px; height: 24px;"></span>
                <span class="wsb-basket-count-val" style="position:absolute; top:-5px; right:-5px; background:var(--wsb-brand-alt, #ef4444); color:#fff; min-width:22px; height:22px; border-radius:50%; font-size:10px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff; font-weight:900; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">0</span>
            </div>
            
            <div id="wsb-basket-popup" style="display:none; position:absolute; bottom:80px; ' . (($pos === 'bottom-left') ? 'left:0;' : 'right:0;') . ' width:320px; background:#fff; border-radius:16px; border:1px solid #eee; box-shadow:0 20px 40px rgba(0,0,0,0.2); padding:20px; text-align:left; cursor:default; color:#333;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <h4 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">Your Selection</h4>
                    <span id="wsb-close-basket" style="font-size:24px; cursor:pointer; color:#64748b; line-height:1;">&times;</span>
                </div>
                <div id="wsb-basket-items" style="max-height:300px; overflow-y:auto; margin-bottom:15px; display:flex; flex-direction:column; gap:10px;">
                    <p id="wsb-empty-basket-msg" style="text-align:center; font-size:14px; color:#64748b; margin:30px 0;">Your basket is empty</p>
                </div>
                <div id="wsb-basket-footer" style="display:none; border-top:1px solid #eee; padding-top:15px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-weight:800; font-size:14px; color:#0f172a;">
                        <span>Total</span>
                        <span id="wsb-basket-total">0.00</span>
                    </div>
                    <a href="' . esc_url(add_query_arg('wsb_jump_to_staff', '1', $booking_url)) . '" class="wsb-basket-checkout-btn" style="display:block; background:var(--wsb-brand, #6366f1); color:#fff; text-align:center; padding:12px; border-radius:10px; text-decoration:none; font-size:14px; font-weight:700; transition:all 0.3s; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);">Book Now</a>
                </div>
                <div id="wsb-empty-basket-footer" style="display:block; text-align:center;">
                     <a href="' . esc_url($booking_url) . '" style="display:inline-block; background:rgba(15, 23, 42, 0.05); color:#0f172a; padding:12px 25px; border-radius:10px; text-decoration:none; font-size:13px; font-weight:700; transition:all 0.2s;">Start Booking</a>
                </div>
            </div>
        </div>
        <style>
            .wsb-basket-trigger-btn:hover { transform: scale(1.1) translateY(-3px); box-shadow: 0 15px 30px rgba(99, 102, 241, 0.5); }
            .wsb-basket-trigger-btn:active { transform: scale(0.95); }
            .wsb-basket-checkout-btn:hover { background: var(--wsb-primary-dark, #4f46e5); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(99, 102, 241, 0.3); }
        </style>';
    }
}
