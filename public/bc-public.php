<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bc_Public
{
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public static function get_icon_class($icon) {
        $icon = apply_filters('bc_public_icon_class_raw', $icon);
        
        // Phosphor Icons Detection
        if (strpos($icon, 'ph-') !== false) {
            if (strpos($icon, 'ph ') === false) {
                return 'ph ' . esc_attr($icon);
            }
            return esc_attr($icon);
        }

        // Font Awesome Detection
        if (strpos($icon, 'fa-') !== false) {
            if (strpos($icon, 'fas ') === false && strpos($icon, 'fab ') === false && strpos($icon, 'far ') === false && strpos($icon, 'fa-solid') === false) {
                return 'fa-solid ' . esc_attr($icon);
            }
            return esc_attr($icon);
        }
        
        // Default to Dashicons
        return 'dashicons ' . esc_attr($icon);
    }

    public function enqueue_styles()
    {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1', 'all');
        wp_enqueue_style('phosphor-icons', 'https://unpkg.com/@phosphor-icons/web@2.1.1/src/strict/style.css', array(), '2.1.1', 'all');
        wp_enqueue_style($this->plugin_name, plugin_dir_url(dirname(__FILE__)) . 'assets/public/css/bc-public.css', array(), time(), 'all');
    }

    public function handle_stripe_return()
    {
        if (!isset($_GET['bc_checkout']) || $_GET['bc_checkout'] !== 'success') {
            return;
        }

        global $wpdb;
        $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
        $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';

        if (!$booking_id || !$session_id) {
            return;
        }

        $booking_table = $wpdb->prefix . 'bc_bookings';
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $booking_table WHERE id = %d AND status = 'pending'", $booking_id));

        if ($booking) {
            // Confirm the booking
            $wpdb->update($booking_table, array('status' => 'confirmed'), array('id' => $booking_id));

            // Create payment record
            $payment_table = $wpdb->prefix . 'bc_payments';
            $wpdb->insert($payment_table, array(
                'booking_id' => $booking_id,
                'amount' => $booking->total_amount,
                'gateway' => 'stripe',
                'status' => 'completed',
                'transaction_id' => $session_id
            ));

            // Send confirmation email
            $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bc_customers WHERE id = %d", $booking->customer_id));
            if ($customer) {
                // Log the user in automatically if they have an account
                $user = get_user_by('email', $customer->email);
                if ($user) {
                    wp_clear_auth_cookie();
                    wp_set_current_user($user->ID);
                    wp_set_auth_cookie($user->ID);
                }

                $mail_subject = __('Payment Received: Booking Confirmed', 'boocommerce');
                $details_html = '
                <div style="background:#f0fdf4; padding:25px; border-radius:16px; border:1px solid #dcfce7;">
                    <div style="font-size:12px; text-transform:uppercase; color:#166534; font-weight:800; margin-bottom:10px;">' . __('Payment Successful', 'boocommerce') . '</div>
                    <div style="margin-bottom:10px;"><strong>' . __('Booking ID:', 'boocommerce') . '</strong> #' . $booking_id . '</div>
                    <div style="margin-bottom:10px;"><strong>' . __('Scheduled Date:', 'boocommerce') . '</strong> ' . $booking->booking_date . '</div>
                    <div style="margin-bottom:10px;"><strong>' . __('Start Time:', 'boocommerce') . '</strong> ' . $booking->start_time . '</div>
                </div>';

                bc_send_modern_email($customer->email, $mail_subject, __('Payment Confirmed', 'boocommerce'), sprintf(__('Hello %s, your payment was successful and your appointment is now fully confirmed!', 'boocommerce'), $customer->first_name), $details_html);
            }

            // Redirect to dashboard with success message
            // Try to find the dashboard page dynamically
            global $wpdb;
            $page_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[bc_client_dashboard]%' AND post_status = 'publish' LIMIT 1");
            $dash_url = $page_id ? get_permalink($page_id) : home_url('/booking-dashboard');

            wp_redirect(add_query_arg('bc_payment_confirmed', '1', $dash_url));
            exit;
        }
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, false);
        wp_enqueue_script($this->plugin_name, plugin_dir_url(dirname(__FILE__)) . 'assets/public/js/bc-public.js', array('jquery', 'stripe-js'), time(), true);
        // Fetch staff-service mapping
        global $wpdb;
        $staff_services_raw = $wpdb->get_results("SELECT staff_id, service_id FROM {$wpdb->prefix}bc_staff_services");
        $mapping = array();
        foreach ($staff_services_raw as $row) {
            $mapping[$row->service_id][] = intval($row->staff_id);
        }

        wp_localize_script($this->plugin_name, 'bc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bc_nonce'),
            'login_url' => wp_login_url(),
            'dashboard_url' => home_url('/booking-dashboard'),
            'stripe_pk' => get_option('bc_stripe_publishable_key', ''),
            'skip_professional' => get_option('bc_skip_professional_step', 'no'),
            'skip_payment' => get_option('bc_skip_payment_step', 'no'),
            'filter_staff_by_service' => get_option('bc_filter_staff_by_service', 'no'),
            'enable_split_scheduling' => get_option('bc_enable_split_scheduling', 'no'),
            'staff_service_mapping' => $mapping,
            'currency_symbol' => bc_get_currency_symbol(get_option('bc_currency', 'USD')),
            'basket_mode' => get_option('bc_basket_mode', 'hover'),
            'allow_skip_prof' => get_option('bc_allow_user_skip_prof', 'no')
        ));
    }

    public function render_booking_widget($atts)
    {
        $atts = apply_filters('bc_public_booking_widget_atts', $atts);
        do_action('bc_public_before_booking_widget', $atts);
        global $wpdb;

        if (isset($_GET['bc_service_id'])) {
            $service_id = intval($_GET['bc_service_id']);
            $s = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}bc_services WHERE id = %d", $service_id));
            if ($s) {
                ob_start();
                $brand_color = get_option('bc_brand_color', '#6366f1');
                $brand_color_end = get_option('bc_brand_color_end', '#a855f7');
                $font_family = get_option('bc_font_family', 'Inter');
                $back_url = remove_query_arg('bc_service_id');
                ?>
                <style>
                    :root {
                        --bc-brand: <?php echo esc_attr($brand_color); ?>;
                        --bc-gradient: linear-gradient(135deg, <?php echo esc_attr($brand_color); ?> 0%, <?php echo esc_attr($brand_color_end); ?> 100%);
                        --bc-font: "<?php echo esc_attr($font_family); ?>", sans-serif;
                    }
                </style>
                <div class="bc-single-service-page" style="font-family: var(--bc-font); max-width: 800px; margin: 40px auto; background: #fff; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1); overflow: hidden; border: 1px solid #e2e8f0;">
                    <?php if ($s->image_url): ?>
                        <div style="width: 100%; height: 400px; background: url('<?php echo esc_url($s->image_url); ?>') center/cover;"></div>
                    <?php endif; ?>
                    <div style="padding: 40px;">
                        <a href="<?php echo esc_url($back_url); ?>" style="display: inline-block; margin-bottom: 20px; color: #64748b; text-decoration: none; font-weight: 600;">&larr; <?php _e('Back to Services', 'boocommerce'); ?></a>
                        <h1 style="margin: 0 0 15px; font-size: 36px; font-weight: 800; color: #0f172a;"><?php echo esc_html($s->name); ?></h1>
                        <div style="display: flex; gap: 15px; margin-bottom: 30px;">
                            <span style="background: rgba(99, 102, 241, 0.1); color: var(--bc-brand); padding: 8px 16px; border-radius: 20px; font-size: 15px; font-weight: 700;">⏱️ <?php echo esc_html($s->duration); ?> mins</span>
                            <span style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 8px 16px; border-radius: 20px; font-size: 15px; font-weight: 700;">💰 <?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')) . esc_html($s->price); ?></span>
                        </div>
                        <div style="color: #475569; line-height: 1.8; font-size: 16px; margin-bottom: 40px;">
                            <?php echo wp_kses_post($s->description); ?>
                        </div>
                        <a href="<?php echo esc_url(add_query_arg(['bc_select_service' => $s->id, 'bc_jump_to_staff' => '1'], $back_url)); ?>" class="bc-btn" style="display: block; text-align: center; background: var(--bc-gradient); color: #fff; padding: 18px; border-radius: 16px; font-weight: 800; font-size: 18px; text-decoration: none; box-shadow: 0 10px 20px -5px rgba(99, 102, 241, 0.4);"><?php _e('Book This Service Now', 'boocommerce'); ?></a>
                    </div>
                </div>
                <?php
                return ob_get_clean();
            }
        }

        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bc_services WHERE status = 'active'");
        $staff = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}bc_staff WHERE status = 'active'");

        ob_start();
        $brand_color = get_option('bc_brand_color', '#6366f1');
        $brand_color_end = get_option('bc_brand_color_end', '#a855f7');
        $accent_color = get_option('bc_accent_color', '#4f46e5');

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
        $font_family = get_option('bc_font_family', 'Inter');
        $border_radius = get_option('bc_border_radius', 16);
        $shadow_intensity = get_option('bc_shadow_intensity', 'medium');
        
        $shadow_map = [
            'none' => 'none',
            'low' => '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
            'medium' => '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
            'high' => '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1)'
        ];
        $shadow_value = isset($shadow_map[$shadow_intensity]) ? $shadow_map[$shadow_intensity] : $shadow_map['medium'];

        $l_step1 = get_option('bc_label_step1', __('1. Select a Service', 'boocommerce'));
        $l_step2 = get_option('bc_label_step2', __('2. Choose a Professional', 'boocommerce'));
        $l_step3 = get_option('bc_label_step3', __('3. Select Date & Time', 'boocommerce'));
        $l_step4 = get_option('bc_label_step4', __('4. Your Details', 'boocommerce'));
        $l_next = get_option('bc_label_next_btn', __('Next Step', 'boocommerce'));
        $l_prev = get_option('bc_label_prev_btn', __('Back', 'boocommerce'));

        // Dynamic Numbering Engine
        $step_idx = 1;
        $skip_prof = get_option('bc_skip_professional_step', 'no');
        $skip_pay = get_option('bc_skip_payment_step', 'no');
        
        // Helper to strip numbers from labels (e.g. "1. Select Service" -> "Select Service")
        $clean_label = function($label) {
            return preg_replace('/^\d+[\.\)\-\s]+/', '', $label);
        };

        // Detailed Styling
        $card_bg = get_option('bc_card_bg_color', '#ffffff');
        $heading_color = get_option('bc_heading_text_color', '#0f172a');
        $body_color = get_option('bc_body_text_color', '#64748b');
        $input_bg = get_option('bc_input_bg_color', '#ffffff');
        $input_border = get_option('bc_input_border_color', '#e2e8f0');
        ?>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Inter:wght@400;600;700;800&family=Jost:wght@400;600;700&family=Lora:wght@400;600;700&family=Montserrat:wght@400;600;700;800&family=Open+Sans:wght@400;600;700&family=Outfit:wght@400;600;700;800&family=Playfair+Display:wght@400;600;700;900&family=Poppins:wght@400;600;700&family=Roboto:wght@400;500;700;900&family=Space+Grotesk:wght@400;600;700&family=Syne:wght@400;600;700;800&display=swap");
            
            :root {
                --bc-brand: <?php echo esc_attr($brand_color); ?>;
                --bc-brand-alt: <?php echo esc_attr($accent_color); ?>;
                --bc-gradient: linear-gradient(135deg, <?php echo esc_attr($brand_color); ?> 0%, <?php echo esc_attr($brand_color_end); ?> 100%);
                --bc-ring: <?php echo esc_attr($brand_color); ?>33;
                --bc-font: "<?php echo esc_attr($font_family); ?>", sans-serif;
                --bc-radius: <?php echo esc_attr($border_radius); ?>px;
                --bc-shadow-custom: <?php echo esc_attr($shadow_value); ?>;
                
                /* Detailed Styling Variables */
                --bc-card-bg: <?php echo esc_attr($card_bg); ?>;
                --bc-heading: <?php echo esc_attr($heading_color); ?>;
                --bc-body: <?php echo esc_attr($body_color); ?>;
                --bc-input-bg: <?php echo esc_attr($input_bg); ?>;
                --bc-input-border: <?php echo esc_attr($input_border); ?>;
            }
            #bc-booking-wizard-container { font-family: var(--bc-font) !important; color: var(--bc-body); }
            #bc-booking-wizard-container h1, #bc-booking-wizard-container h2, #bc-booking-wizard-container h3, #bc-booking-wizard-container h4 { color: var(--bc-heading); }
            .bc-card-option, .bc-staff-card, .bc-form-card { background-color: var(--bc-card-bg) !important; }
            .bc-card-option, .bc-staff-card, .bc-btn, .bc-form-card, .bc-field-wrap input, .bc-field-wrap select, .bc-field-wrap textarea, .bc-phone-input-group { border-radius: var(--bc-radius) !important; }
            .bc-card-option, .bc-staff-card, .bc-form-card { box-shadow: var(--bc-shadow-custom) !important; }
            .bc-field-wrap input, .bc-field-wrap select, .bc-field-wrap textarea { background-color: var(--bc-input-bg) !important; border-color: var(--bc-input-border) !important; color: var(--bc-body); }
            
            /* Modern Header System - Centered */
            .bc-step-header { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 15px; margin-bottom: 40px; border-bottom: 1px solid var(--bc-input-border); padding-bottom: 30px; position: relative; z-index: 1000; }
            .bc-step-badge { 
                background: var(--bc-gradient); color: white; width: 50px; height: 50px; 
                display: flex; align-items: center; justify-content: center; 
                border-radius: 50%; font-weight: 800; font-size: 20px; flex-shrink: 0;
                box-shadow: 0 10px 20px -5px var(--bc-ring);
                margin-bottom: 5px;
            }
            .bc-step-details h3 { margin: 0 0 8px 0 !important; font-size: 28px !important; font-weight: 800 !important; letter-spacing: -0.03em !important; }
            .bc-step-details p { margin: 0; color: var(--bc-body); opacity: 0.7; font-size: 16px; font-weight: 500; max-width: 500px; margin-left: auto; margin-right: auto; }
            
            @media (max-width: 768px) {
                .bc-step-header { gap: 10px; margin-bottom: 30px; }
                .bc-step-details h3 { font-size: 22px !important; }
                .bc-step-details p { font-size: 14px; }
                .bc-step-badge { width: 40px; height: 40px; font-size: 16px; }
            }
        </style>
        <div id="bc-booking-wizard-container" class="bc-wrapper">
            <div class="bc-wizard-step" id="bc-step-service">
                <div class="bc-step-header">
                    <div class="bc-step-badge"><?php echo str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                    <div class="bc-step-details">
                        <h3><?php echo esc_html($clean_label($l_step1)); ?></h3>
                        <p><?php _e('Select your desired service to begin your experience.', 'boocommerce'); ?></p>
                        
                        <?php 
                        $l_basket = get_option('bc_label_basket_btn', __('Services Selected', 'boocommerce'));
                        $i_basket = get_option('bc_icon_basket_btn', 'dashicons-cart');
                        ?>
                        
                        <div style="margin-top:20px; display:flex; align-items:center; justify-content:center; gap:15px; flex-wrap:wrap;">
                            <a href="<?php echo esc_url(home_url('/booking-dashboard')); ?>" class="bc-btn"
                                style="display:inline-flex; align-items:center; background:rgba(99, 102, 241, 0.05); border:1.5px solid var(--bc-brand); color:var(--bc-brand); text-decoration:none; font-size: 13px; padding: 10px 22px; border-radius: 12px; font-weight: 700; transition:all 0.3s; gap:8px;">
                                <span class="dashicons dashicons-admin-users" style="font-size:18px;"></span> <?php _e('Client Portal', 'boocommerce'); ?>
                            </a>

                            <div id="bc-basket-trigger" class="bc-basket-trigger-btn bc-btn"
                                style="position:relative; display:inline-flex; align-items:center; background:var(--bc-gradient); color:#fff; cursor:pointer; font-size: 13px; padding: 10px 26px; border-radius: 12px; font-weight: 700; transition:all 0.3s; gap:12px; box-shadow: 0 4px 12px var(--bc-ring);">
                                <div style="position:relative; display:flex; align-items:center; justify-content:center;">
                                    <span class="<?php echo Bc_Public::get_icon_class($i_basket); ?>" style="font-size:20px; width:20px; height:20px;"></span> 
                                    <span class="bc-basket-count-val" style="position:absolute; top:-10px; right:-12px; background:var(--bc-brand-alt); color:#fff; min-width:18px; height:18px; border-radius:10px; font-size:10px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff; font-weight:900; line-height:1; box-shadow:0 2px 4px rgba(0,0,0,0.1);">0</span>
                                </div>
                                <span><?php echo esc_html($l_basket); ?></span>
                                
                                <!-- Basket Popup -->
                                <div id="bc-basket-popup" style="display:none; position:absolute; top:calc(100% + 15px); right:0; width:320px; background:#fff; border-radius:16px; border:1px solid var(--bc-input-border); box-shadow:0 20px 40px rgba(0,0,0,0.2); z-index:999999; padding:20px; text-align:left; cursor:default;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--bc-input-border); padding-bottom:10px;">
                                        <h4 style="margin:0; font-size:16px; font-weight:800; color:var(--bc-heading);"><?php _e('Your Selection', 'boocommerce'); ?></h4>
                                        <span id="bc-close-basket" style="font-size:20px; cursor:pointer; color:var(--bc-body);">&times;</span>
                                    </div>
                                    <div id="bc-basket-items" style="max-height:250px; overflow-y:auto; margin-bottom:15px; display:flex; flex-direction:column; gap:10px;">
                                        <!-- Items populated via JS -->
                                        <p id="bc-empty-basket-msg" style="text-align:center; color:var(--bc-body); opacity:0.6; font-size:14px; margin:20px 0;"><?php _e('No services selected yet.', 'boocommerce'); ?></p>
                                    </div>
                                    <div id="bc-basket-footer" style="border-top:1px solid var(--bc-input-border); padding-top:15px; display:none;">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-weight:800; color:var(--bc-heading);">
                                            <span><?php _e('Total', 'boocommerce'); ?></span>
                                            <span id="bc-basket-total">0.00</span>
                                        </div>
                                        <button class="bc-btn bc-next-btn" data-next="bc-step-staff" style="width:100%; padding:12px;"><?php _e('Continue Booking', 'boocommerce'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($categories)): ?>
                    <div class="bc-category-filter">
                        <button class="bc-filter-btn active" data-category="all"><?php _e('All Services', 'boocommerce'); ?></button>
                        <?php foreach ($categories as $cat): ?>
                            <button class="bc-filter-btn"
                                data-category="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php $layout = get_option('bc_service_layout', 'modern_grid'); ?>
                <div class="bc-services-container bc-layout-<?php echo esc_attr($layout); ?>">
                    <?php if (!empty($services)):
                        foreach ($services as $s): ?>
                            <div class="bc-card-option" 
                                data-service-id="<?php echo esc_attr($s->id); ?>"
                                data-price="<?php echo esc_attr($s->price); ?>"
                                data-duration="<?php echo esc_attr($s->duration); ?>">
                                <?php if ($s->category): ?>
                                    <span class="bc-category-badge"><?php echo esc_html($s->category); ?></span>
                                <?php endif; ?>

                                <?php if ($layout !== 'minimal'): ?>
                                    <div class="bc-service-image-container" style="position:relative; overflow:hidden;">
                                        <div class="bc-service-image"
                                            style="background: #f8fafc <?php echo $s->image_url ? 'url(' . esc_url($s->image_url) . ') center/cover' : ''; ?>;">
                                        </div>
                                        <?php 
                                        $page_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[bc_booking_widget]%' AND post_status = 'publish' LIMIT 1");
                                        $booking_url = $page_id ? get_permalink($page_id) : home_url('/booking');
                                        ?>
                                        <a href="<?php echo esc_url(add_query_arg('bc_service_id', $s->id, $booking_url)); ?>" class="bc-view-service-btn" title="View Product Details">
                                            <span class="<?php echo Bc_Public::get_icon_class(get_option('bc_icon_view_details', 'dashicons-visibility')); ?>"></span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="bc-service-content">
                                    <h4><?php echo esc_html($s->name); ?></h4>
                                    <div class="bc-service-meta">
                                        <span><?php echo esc_html($s->duration); ?> mins</span>
                                        <span
                                            class="bc-price-tag"><?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?><?php echo esc_html($s->price); ?></span>
                                    </div>
                                    <?php if (in_array($layout, ['modern_grid', 'glass_cards_v2', 'metro_grid', 'neon_night'])): ?>
                                        <p class="bc-service-desc" style="margin-top:10px; font-size:14px; opacity:0.8; line-height:1.5;">
                                            <?php echo esc_html(wp_trim_words($s->description, 12)); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="bc-selection-indicator"></div>
                            </div>
                        <?php endforeach; else: ?>
                        <p><?php _e('No services available yet.', 'boocommerce'); ?></p>
                    <?php endif; ?>
                </div>
                <?php do_action('bc_public_after_service_list', $services); ?>
                <div class="bc-actions">
                    <div></div>
                    <button class="bc-next-btn bc-btn" data-next="bc-step-staff" disabled><?php echo esc_html($l_next); ?></button>
                </div>
            </div>

            <div class="bc-wizard-step" id="bc-step-staff" style="display:none;">
                <div class="bc-step-header">
                    <div class="bc-step-badge"><?php echo ($skip_prof === 'yes') ? '--' : str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                    <div class="bc-step-details">
                        <h3><?php echo esc_html($clean_label($l_step2)); ?></h3>
                        <p><?php _e('Our team of experts is ready to provide exceptional care.', 'boocommerce'); ?></p>
                        
                        <!-- MULTI-SERVICE SESSION NOTICE -->
                        <div id="bc-multi-session-notice" style="display:none; margin-top:20px; padding:15px 25px; background:rgba(99, 102, 241, 0.05); border:1px solid var(--bc-brand); border-radius:12px; font-size:14px; color:var(--bc-brand); font-weight:600; text-align:left;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid rgba(99, 102, 241, 0.1); padding-bottom:8px;">
                                <span style="font-size:16px; font-weight:800;"><?php _e('✨ Selected Bundle', 'boocommerce'); ?></span>
                                <span style="background:var(--bc-brand); color:#fff; padding:2px 10px; border-radius:20px; font-size:11px;"><span id="bc-session-duration">0</span> mins</span>
                            </div>
                            <div id="bc-service-breakdown" style="display:flex; flex-direction:column; gap:6px;">
                                <!-- Populated via JS -->
                            </div>
                            <div id="bc-split-indicator" style="display:none; margin-top:12px; padding-top:10px; border-top:1px dashed var(--bc-brand); color:var(--bc-brand); font-style:italic; font-size:12px;">
                                📍 <?php _e('Scheduling:', 'boocommerce'); ?> <span id="bc-current-split-service-name">Service Name</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bc-staff-grid">
                    <div class="bc-staff-card" data-staff-id="any">
                        <div class="bc-staff-avatar bc-any-avatar">
                            <span>✨</span>
                        </div>
                        <div class="bc-staff-info">
                            <h4><?php _e('Any Available', 'boocommerce'); ?></h4>
                            <p class="bc-staff-title"><?php _e('Optimal Availability', 'boocommerce'); ?></p>
                            <div class="bc-staff-rating">★★★★★</div>
                        </div>
                        <div class="bc-selection-indicator"></div>
                    </div>

                    <?php foreach ($staff as $member): ?>
                        <div class="bc-staff-card" data-staff-id="<?php echo esc_attr($member->id); ?>">
                            <div class="bc-staff-avatar"
                                style="<?php echo !empty($member->image_url) ? 'background-image: url(' . esc_url($member->image_url) . ');' : ''; ?>">
                                <?php if (empty($member->image_url)): ?>
                                    <span><?php echo esc_html(strtoupper(substr($member->name, 0, 1))); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="bc-staff-info">
                                <h4><?php echo esc_html($member->name); ?></h4>
                                <p class="bc-staff-title"><?php echo esc_html($member->qualification ?: 'Senior Specialist'); ?>
                                </p>
                                <div class="bc-staff-rating">★★★★★</div>
                            </div>
                            <div class="bc-selection-indicator"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="bc-actions">
                    <button class="bc-prev-btn bc-btn" data-prev="bc-step-service"><?php echo esc_html($l_prev); ?></button>
                    <?php if (get_option('bc_allow_user_skip_prof', 'no') === 'yes'): ?>
                        <button class="bc-skip-staff-btn bc-btn" data-next="bc-step-time" style="background:rgba(0,0,0,0.05); color:var(--bc-text-muted); border:1.5px solid var(--bc-border);"><?php _e('Skip & Use Any', 'boocommerce'); ?></button>
                    <?php endif; ?>
                    <button class="bc-next-btn bc-btn" data-next="bc-step-time" disabled><?php echo esc_html($l_next); ?></button>
                </div>
            </div>

            <div class="bc-wizard-step" id="bc-step-time" style="display:none;">
                <div class="bc-step-header">
                    <div class="bc-step-badge"><?php echo str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                    <div class="bc-step-details">
                        <h3><?php echo esc_html($clean_label($l_step3)); ?></h3>
                        <p><?php _e('Find a time that perfectly fits your schedule.', 'boocommerce'); ?></p>

                        <!-- MULTI-SERVICE TIME NOTICE -->
                        <div id="bc-multi-time-notice" style="display:none; margin-top:20px; padding:12px 20px; background:rgba(16, 185, 129, 0.05); border:1px solid #10b981; border-radius:12px; font-size:14px; color:#10b981; font-weight:600;">
                            <span style="font-size:18px; margin-right:8px;">🕒</span> 
                            <span><?php printf(__('Finding a %s min opening for your combined services.', 'boocommerce'), '<span id="bc-session-duration-time">0</span>'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="bc-datetime-layout-stacked">
                    <label class="bc-datetime-label"><?php _e('📅 Choose Your Date', 'boocommerce'); ?></label>

                    <div class="bc-modern-calendar-full">
                        <div class="bc-calendar-header">
                            <button type="button" id="bc-prev-month" class="bc-cal-nav">❮</button>
                            <span id="bc-current-month-year" class="bc-cal-month-title"></span>
                            <button type="button" id="bc-next-month" class="bc-cal-nav">❯</button>
                        </div>
                        <div class="bc-calendar-weekdays">
                            <div><?php _e('Su', 'boocommerce'); ?></div>
                            <div><?php _e('Mo', 'boocommerce'); ?></div>
                            <div><?php _e('Tu', 'boocommerce'); ?></div>
                            <div><?php _e('We', 'boocommerce'); ?></div>
                            <div><?php _e('Th', 'boocommerce'); ?></div>
                            <div><?php _e('Fr', 'boocommerce'); ?></div>
                            <div><?php _e('Sa', 'boocommerce'); ?></div>
                        </div>
                        <div id="bc-calendar-days" class="bc-calendar-days-grid"></div>
                    </div>

                    <input type="hidden" id="bc-booking-date" />

                    <div class="bc-time-picker-section" style="display:none; margin-top: 35px;">
                        <label class="bc-datetime-label"><?php _e('🕒 Available Slots', 'boocommerce'); ?></label>
                        <div class="bc-time-slots"></div>
                    </div>
                </div>
                <div class="bc-actions">
                    <button class="bc-prev-btn bc-btn" data-prev="bc-step-staff"><?php echo esc_html($l_prev); ?></button>
                    <button class="bc-next-btn bc-btn" data-next="bc-step-details" disabled><?php echo esc_html($l_next); ?></button>
                </div>
            </div>

            <div class="bc-wizard-step" id="bc-step-details" style="display:none;">
                <div class="bc-step-header">
                    <div class="bc-step-badge"><?php echo str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                    <div class="bc-step-details">
                        <h3><?php echo esc_html($clean_label($l_step4)); ?></h3>
                        <p><?php _e('Tell us a little about yourself to secure your spot.', 'boocommerce'); ?></p>
                    </div>
                </div>
                <div class="bc-form-card">
                    <div class="bc-form-container">
                        <div class="bc-form-grid">
                            <div class="bc-field-wrap">
                                <label><?php _e('First Name', 'boocommerce'); ?> <span style="color:#ef4444;">*</span></label>
                                <input type="text" placeholder="<?php esc_attr_e('e.g. John', 'boocommerce'); ?>" id="bc-first-name"
                                    value="<?php echo esc_attr($user_first_name); ?>" required />
                                <span class="bc-error-msg" id="bc-error-first-name"></span>
                            </div>
                            <div class="bc-field-wrap">
                                <label><?php _e('Last Name', 'boocommerce'); ?> <span style="color:#ef4444;">*</span></label>
                                <input type="text" placeholder="<?php esc_attr_e('e.g. Doe', 'boocommerce'); ?>" id="bc-last-name"
                                    value="<?php echo esc_attr($user_last_name); ?>" required />
                                <span class="bc-error-msg" id="bc-error-last-name"></span>
                            </div>
                        </div>

                        <div class="bc-field-wrap">
                            <label><?php _e('Email Address', 'boocommerce'); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="email" placeholder="john.doe@example.com" id="bc-email"
                                value="<?php echo esc_attr($user_email); ?>" <?php echo !empty($user_email) ? 'readonly style="background:rgba(255,255,255,0.05); cursor:not-allowed;"' : ''; ?> required />
                            <span class="bc-error-msg" id="bc-error-email"></span>
                        </div>

                        <div class="bc-field-wrap">
                            <label><?php _e('Phone Number', 'boocommerce'); ?> <span style="color:#ef4444;">*</span></label>
                            <div class="bc-phone-input-group">
                                <select id="bc-phone-code" class="bc-phone-code-select">
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
                                <input type="tel" placeholder="(555) 000-0000" id="bc-phone"
                                    value="<?php echo esc_attr($user_phone); ?>" required />
                            </div>
                            <span class="bc-error-msg" id="bc-error-phone"></span>
                        </div>

                        <div class="bc-field-wrap">
                            <label><?php _e('Additional Notes', 'boocommerce'); ?></label>
                            <textarea placeholder="<?php esc_attr_e('Any special requests or details we should know?', 'boocommerce'); ?>" rows="4"
                                id="bc-notes"></textarea>
                        </div>

                        <div class="bc-account-info-note"
                            style="margin-top: 15px; padding: 15px; background: rgba(99,102,241,0.05); border-left: 4px solid var(--bc-brand); border-radius: 8px; font-size: 14px; color: var(--bc-text-muted); line-height: 1.5;">
                            💡 <strong><?php _e('Note:', 'boocommerce'); ?></strong> <?php _e('An account will be created automatically using your email address. You\'ll receive temporary login details to securely monitor and manage your bookings.', 'boocommerce'); ?>
                        </div>
                    </div>
                </div>

                <div class="bc-actions">
                    <button class="bc-prev-btn bc-btn" data-prev="bc-step-time"><?php echo esc_html($l_prev); ?></button>
                    <?php $skip_pay = get_option('bc_skip_payment_step', 'no'); ?>
                    <button class="bc-next-btn bc-btn" data-next="bc-step-payment">
                        <?php echo ($skip_pay === 'yes') ? __('Confirm Booking', 'boocommerce') : __('Next Step', 'boocommerce'); ?>
                    </button>
                </div>
            </div>

            <div class="bc-wizard-step" id="bc-step-payment"
                style="display:none; padding-top: 10px; justify-content: center;">

                <!-- Payment Selection Panel -->
                <div
                    style="width: 100%; max-width: 700px; background: #ffffff; border: 1px solid var(--bc-border); padding: 40px; border-radius: 24px; box-shadow: var(--bc-shadow-sm);">
                    <div class="bc-step-header">
                        <div class="bc-step-badge"><?php echo ($skip_pay === 'yes') ? '--' : str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                        <div class="bc-step-details">
                            <h3><?php _e('Select Payment Method', 'boocommerce'); ?></h3>
                            <p><?php _e('Your transaction is secure and encrypted.', 'boocommerce'); ?></p>
                            
                            <div style="margin-top:15px;">
                                <span style="background: #ecfdf5; color: #10b981; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #d1fae5;">
                                    <span style="font-size: 14px;">🛡️</span> <?php _e('256-bit SSL Secure', 'boocommerce'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method Selector -->
                    <div id="bc-payment-methods-wrapper" style="margin-bottom: 35px;">
                        <div class="bc-payment-methods-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 20px;">
                            
                            <!-- Default Stripe Option -->
                            <?php if (!has_action('bc_public_payment_methods')): ?>
                            <div class="bc-payment-method-card active" data-method="stripe_card" 
                                style="border: 2px solid var(--bc-brand); padding: 25px; border-radius: 20px; cursor: pointer; text-align: center; background: #fff; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative; box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.1);">
                                <div style="width: 50px; height: 50px; background: #f1f5f9; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 15px; transition: all 0.3s;">💳</div>
                                <div style="font-weight: 800; color: #0f172a; font-size: 16px; margin-bottom: 4px;"><?php _e('Credit Card', 'boocommerce'); ?></div>
                                <div style="font-size: 12px; color: #64748b; font-weight: 500;"><?php _e('Secure via Stripe', 'boocommerce'); ?></div>
                                <input type="radio" name="payment_method" value="stripe_card" checked style="display:none;">
                                <div class="bc-method-check" style="position: absolute; top: 12px; right: 12px; width: 22px; height: 22px; background: var(--bc-brand); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 900;">✓</div>
                            </div>
                            <?php endif; ?>

                            <?php do_action('bc_public_payment_methods'); ?>
                        </div>
                    </div>

                    <!-- Buyer Protection Box -->
                    <div
                        style="margin-top: 30px; background: #eff6ff; border-radius: 8px; padding: 20px; display: flex; align-items: center; gap: 15px; margin-bottom: 30px;">
                        <span style="font-size: 24px;">🛡️</span>
                        <div>
                            <strong style="color: #1e3a8a; font-size: 14px; display: block;"><?php _e('Buyer Protection', 'boocommerce'); ?></strong>
                            <span style="color: #1e40af; font-size: 13px;"><?php _e('Your purchase is fully protected by secure, advanced fraud monitoring.', 'boocommerce'); ?></span>
                        </div>
                    </div>

                    <div style="display:flex; gap:15px; align-items:center;">
                        <button class="bc-prev-btn bc-btn" data-prev="bc-step-details" style="background:#fff; border:1.5px solid var(--bc-border); color:var(--bc-text-muted); padding:20px; border-radius:var(--bc-radius);"><?php echo esc_html($l_prev); ?></button>
                        <button class="bc-next-btn bc-btn" data-next="bc-step-checkout"
                            style="flex:1; background: var(--bc-gradient); color: #ffffff; padding: 20px; font-size: 18px; font-weight: 800; border-radius: 16px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 12px; border: none; box-shadow: 0 10px 15px -3px var(--bc-ring); transition: all 0.3s;">
                            <span>🔒</span> <?php _e('Continue to Secure Payment', 'boocommerce'); ?>
                    </div>

                    <div style="text-align: center; margin-top: 15px; font-size: 12px; color: #94a3b8;">
                        <?php printf(__('By continuing, you agree to our %s.', 'boocommerce'), '<a href="#" style="color: #64748b; text-decoration: underline;">' . __('Terms of Service', 'boocommerce') . '</a>'); ?>
                    </div>
                </div>
            </div>

            <!-- NEW FINAL CHECKOUT STEP -->
            <div class="bc-wizard-step" id="bc-step-checkout" style="display:none; gap: 30px; align-items: flex-start; padding-top: 10px; flex-wrap: wrap;">
                <!-- LEFT COLUMN: Final Payment Execution -->
                <div style="flex: 1 1 600px; background: #ffffff; border: 1px solid var(--bc-border); padding: 30px; border-radius: 12px;">
                    <div class="bc-step-header">
                        <div class="bc-step-badge"><?php echo ($skip_pay === 'yes') ? '--' : str_pad($step_idx++, 2, '0', STR_PAD_LEFT); ?></div>
                        <div class="bc-step-details">
                            <h3><?php _e('Complete Your Payment', 'boocommerce'); ?></h3>
                            <p><?php _e('Finalize your booking with our secure gateway.', 'boocommerce'); ?></p>
                            
                            <div style="margin-top:15px;">
                                <span style="background: #ecfdf5; color: #10b981; padding: 6px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #d1fae5;">
                                    <span style="font-size: 14px;">🛡️</span> <?php _e('Encrypted Transaction', 'boocommerce'); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Stripe Payment Element Container -->
                    <div id="bc-stripe-payment-container" style="margin-top: 10px; display: none;">
                        <div id="bc-payment-element" style="margin-bottom: 25px;">
                            <!-- Stripe.js injects the Payment Element here -->
                        </div>
                        <div id="bc-payment-loading" style="text-align: center; padding: 30px; color: #64748b; font-size: 14px; background: #f8fafc; border-radius: 12px; border: 1px dashed var(--bc-border);">
                            <div class="bc-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid rgba(0,0,0,0.1); border-top-color: var(--bc-brand); border-radius: 50%; animation: bc-spin 0.8s linear infinite; margin-right: 12px; vertical-align: middle;"></div>
                            <?php _e('Preparing your secure payment session...', 'boocommerce'); ?>
                        </div>
                        <div id="bc-stripe-error" style="color: #ef4444; font-size: 13px; margin-top: 10px; display: none; padding: 12px; background: #fef2f2; border-radius: 8px; border: 1px solid #fee2e2;"></div>
                        
                        <button id="bc-complete-checkout-btn" class="bc-btn"
                            style="width: 100%; background: var(--bc-gradient); color: #ffffff; padding: 18px; font-size: 17px; font-weight: 800; border-radius: 16px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 12px; border: none; box-shadow: 0 10px 15px -3px var(--bc-ring); transition: all 0.3s; margin-top: 20px;">
                            <span>✅</span> <?php _e('Pay & Confirm Booking', 'boocommerce'); ?>
                    </div>

                    <!-- PayPal Button Container -->
                    <div id="bc-paypal-checkout-container" style="display: none; margin-top: 10px; text-align: center; padding: 20px; background: #f8fafc; border-radius: 12px; border: 1px dashed var(--bc-border);">
                        <p style="margin-bottom: 20px; font-weight: 600; color: #475569;"><?php _e('Click the button below to pay with PayPal', 'boocommerce'); ?></p>
                        <div id="bc-paypal-button-container">
                            <!-- PayPal button will be rendered here -->
                        </div>
                    </div>

                    <div style="margin-top: 30px; display: flex; align-items: center; gap: 10px; padding: 15px; background: #f1f5f9; border-radius: 10px;">
                        <span style="font-size: 18px;">💡</span>
                        <p style="margin: 0; font-size: 13px; color: #475569;"><?php _e('You are one step away! Your professional is reserved once the payment is confirmed.', 'boocommerce'); ?></p>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Summary (Static) -->
                <div style="flex: 0 0 350px; background: #ffffff; border: 1px solid var(--bc-border); padding: 30px; border-radius: 12px;">
                    <h3 style="margin-top: 0; font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 25px;"><?php _e('Order Summary', 'boocommerce'); ?></h3>
                    
                    <div style="padding: 15px; background: #f8fafc; border-radius: 12px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-weight: 600;">
                            <span id="bc-checkout-summary-service"><?php _e('Service', 'boocommerce'); ?></span>
                            <span id="bc-checkout-summary-price">$0.00</span>
                        </div>
                        <div id="bc-checkout-summary-datetime" style="font-size: 13px; color: #64748b;"><?php _e('Loading details...', 'boocommerce'); ?></div>
                    </div>

                    <div style="display: flex; justify-content: space-between; margin-bottom: 30px; font-weight: 800; font-size: 20px; color: var(--bc-brand);">
                        <span><?php _e('Total', 'boocommerce'); ?></span>
                        <span id="bc-checkout-summary-total">$0.00</span>
                    </div>

                    <button class="bc-prev-btn bc-btn" data-prev="bc-step-payment" style="width: 100%; padding: 14px;"><?php echo esc_html($l_prev); ?> <?php _e('to Methods', 'boocommerce'); ?></button>
                </div>
            </div>

            </div>
        <?php do_action('bc_public_after_booking_widget'); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_client_dashboard()
    {
        $brand_color = get_option('bc_brand_color', '#6366f1');
        $brand_color_end = get_option('bc_brand_color_end', '#a855f7');
        $accent_color = get_option('bc_accent_color', '#4f46e5');

        ob_start();
        ?>
        <style>
            :root {
                --bc-brand: <?php echo esc_attr($brand_color); ?>;
                --bc-brand-alt: <?php echo esc_attr($accent_color); ?>;
                --bc-gradient: linear-gradient(135deg, <?php echo esc_attr($brand_color); ?> 0%, <?php echo esc_attr($brand_color_end); ?> 100%);
                --bc-ring: <?php echo esc_attr($brand_color); ?>33;
                --bc-bg: #f8fafc;
                --bc-card-bg: #ffffff;
                --bc-border: #e2e8f0;
                --bc-text-main: #1e293b;
                --bc-text-muted: #64748b;
                --bc-success: #10b981;
                --bc-error: #ef4444;
                --bc-warning: #f59e0b;
                --bc-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
                --bc-shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            }

            .bc-dash-wrapper {
                font-family: 'Inter', sans-serif;
                color: var(--bc-text-main);
                line-height: 1.6;
            }

            .bc-dash-wrapper h1, .bc-dash-wrapper h2, .bc-dash-wrapper h3, .bc-dash-wrapper h4 {
                font-family: 'Outfit', sans-serif;
                font-weight: 700;
            }

            /* Phosphor Icon Fix */
            .ph {
                display: inline-block;
                line-height: 1;
                vertical-align: middle;
            }

            /* Login Container */
            .bc-login-card {
                max-width: 480px;
                margin: 60px auto;
                padding: 50px 40px;
                background: var(--bc-card-bg);
                border-radius: 32px;
                box-shadow: var(--bc-shadow-lg);
                text-align: center;
                border: 1px solid var(--bc-border);
                position: relative;
                overflow: hidden;
            }

            .bc-login-icon {
                width: 80px;
                height: 80px;
                background: var(--bc-ring);
                color: var(--bc-brand);
                border-radius: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 32px;
                margin: 0 auto 30px;
            }

            /* Dashboard Main */
            .bc-dashboard-main {
                max-width: 1000px;
                margin: 40px auto;
                background: var(--bc-card-bg);
                border-radius: 32px;
                box-shadow: var(--bc-shadow-lg);
                border: 1px solid var(--bc-border);
                overflow: hidden;
            }

            .bc-dash-header {
                padding: 40px 50px;
                border-bottom: 1px solid var(--bc-border);
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: linear-gradient(to right, #ffffff, #f8fafc);
            }

            .bc-dash-nav {
                display: flex;
                gap: 10px;
                padding: 20px 50px;
                background: #fcfcfd;
                border-bottom: 1px solid var(--bc-border);
            }

            .bc-nav-link {
                padding: 10px 24px;
                border-radius: 12px;
                font-weight: 700;
                font-size: 14px;
                color: var(--bc-text-muted);
                cursor: pointer;
                transition: all 0.3s ease;
                border: 1px solid transparent;
            }

            .bc-nav-link.active {
                background: #ffffff;
                color: var(--bc-brand);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
                border-color: var(--bc-border);
            }

            .bc-dash-body {
                padding: 40px 50px;
            }

            /* List View Redesign */
            .bc-booking-list {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            .bc-appointment-card {
                background: #ffffff;
                border: 1px solid var(--bc-border);
                border-radius: 20px;
                padding: 24px 30px;
                display: grid;
                grid-template-columns: 80px 1.5fr 1.2fr 1fr 140px;
                align-items: center;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                cursor: pointer;
                position: relative;
                overflow: hidden;
            }

            .bc-appointment-card:hover {
                transform: translateY(-3px);
                border-color: var(--bc-brand);
                box-shadow: var(--bc-shadow);
            }

            .bc-appointment-card::before {
                content: '';
                position: absolute;
                left: 0;
                top: 0;
                bottom: 0;
                width: 4px;
                background: var(--bc-brand);
                opacity: 0;
                transition: opacity 0.3s;
            }

            .bc-appointment-card:hover::before {
                opacity: 1;
            }

            .bc-app-ref {
                font-size: 13px;
                font-weight: 800;
                color: var(--bc-brand);
                background: var(--bc-ring);
                padding: 4px 10px;
                border-radius: 8px;
                width: fit-content;
            }

            .bc-app-info h4 {
                margin: 0;
                font-size: 16px;
                color: var(--bc-text-main);
            }

            .bc-app-info p {
                margin: 4px 0 0;
                font-size: 13px;
                color: var(--bc-text-muted);
            }

            .bc-app-meta {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }

            .bc-meta-item {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                color: var(--bc-text-muted);
                font-weight: 600;
            }

            .bc-meta-item i {
                color: var(--bc-brand);
                font-size: 16px;
            }

            /* Status Badges */
            .bc-status-pill {
                padding: 8px 16px;
                border-radius: 100px;
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                width: fit-content;
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .bc-status-pill::before {
                content: '';
                width: 6px;
                height: 6px;
                border-radius: 50%;
            }

            .status-confirmed { background: #ecfdf5; color: #059669; }
            .status-confirmed::before { background: #059669; }
            
            .status-pending { background: #fffbeb; color: #d97706; }
            .status-pending::before { background: #d97706; }
            
            .status-cancelled { background: #fef2f2; color: #dc2626; }
            .status-cancelled::before { background: #dc2626; }

            /* Action Buttons Group */
            .bc-app-actions {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }

            .bc-btn-icon {
                width: 42px;
                height: 42px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                border: 1px solid var(--bc-border);
                background: #fff;
                color: var(--bc-text-muted);
                cursor: pointer;
                transition: all 0.2s;
                font-size: 18px;
            }

            .bc-btn-icon:hover {
                border-color: var(--bc-brand);
                color: var(--bc-brand);
                background: var(--bc-ring);
                transform: scale(1.05);
            }

            .bc-btn-icon.btn-danger:hover {
                border-color: var(--bc-error);
                color: var(--bc-error);
                background: #fef2f2;
            }

            /* Buttons */
            .bc-action-btn {
                padding: 8px 16px;
                border-radius: 10px;
                font-weight: 700;
                font-size: 13px;
                cursor: pointer;
                transition: all 0.3s ease;
                border: 1px solid transparent;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn-reschedule { background: rgba(99, 102, 241, 0.08); color: var(--bc-brand); }
            .btn-cancel { background: rgba(239, 68, 68, 0.05); color: var(--bc-error); }

            .bc-action-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            }

            /* Form Styles */
            .bc-form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 25px;
                margin-bottom: 25px;
            }

            /* Sectioned Form Layout */
            .bc-form-section {
                background: #fcfcfd;
                border: 1px solid var(--bc-border);
                border-radius: 24px;
                padding: 30px;
                margin-bottom: 30px;
                transition: all 0.3s ease;
            }

            .bc-form-section:hover {
                background: #ffffff;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
                border-color: var(--bc-brand);
            }

            .bc-section-title {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 1px dashed var(--bc-border);
            }

            .bc-section-title i {
                width: 36px;
                height: 36px;
                background: var(--bc-ring);
                color: var(--bc-brand);
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
            }

            .bc-section-title h5 {
                margin: 0;
                font-size: 16px;
                font-weight: 800;
                color: var(--bc-text-main);
                letter-spacing: -0.2px;
            }

            .bc-input-group {
                margin-bottom: 0;
            }

            .bc-field-wrapper {
                position: relative;
                display: flex;
                align-items: center;
                background: #fff;
                border: 1.5px solid var(--bc-border);
                border-radius: 18px;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                padding: 5px;
            }

            .bc-field-wrapper:focus-within {
                border-color: var(--bc-brand);
                box-shadow: 0 0 0 4px var(--bc-ring);
                transform: translateY(-1px);
            }

            .bc-field-icon {
                width: 46px;
                height: 46px;
                background: #f8fafc;
                color: var(--bc-text-muted);
                border-radius: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                transition: all 0.3s;
            }

            .bc-field-wrapper:focus-within .bc-field-icon {
                background: var(--bc-brand);
                color: #fff;
            }

            .bc-input {
                border: none !important;
                background: transparent !important;
                padding: 12px 15px !important;
                box-shadow: none !important;
                font-weight: 600;
                color: var(--bc-text-main);
            }

            .bc-input::placeholder {
                color: #cbd5e1;
                font-weight: 400;
            }

            .bc-input:disabled {
                opacity: 0.6;
            }

            .bc-form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }

            /* Mobile Stack */
            @media (max-width: 768px) {
                .bc-dash-header { flex-direction: column; text-align: center; gap: 20px; padding: 30px; }
                .bc-dash-nav { padding: 15px; overflow-x: auto; }
                .bc-dash-body { padding: 30px 20px; }
                .bc-form-row { grid-template-columns: 1fr; }
                .bc-table thead { display: none; }
                .bc-row td { display: block; padding: 10px 20px; border: none !important; text-align: right; }
                .bc-row td:first-child { padding-top: 20px; }
                .bc-row td:last-child { padding-bottom: 20px; border-radius: 16px !important; }
                .bc-row td::before { content: attr(data-label); float: left; font-weight: 800; color: var(--bc-text-muted); font-size: 12px; }
            }
        </style>

        <div class="bc-dash-wrapper">
            <?php if (!is_user_logged_in()): ?>
                <div class="bc-login-card">
                    <div class="bc-login-icon">
                        <svg width="32" height="32" viewBox="0 0 256 256" fill="currentColor"><path d="M208,80H176V56a48,48,0,0,0-96,0V80H48A16,16,0,0,0,32,96V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V96A16,16,0,0,0,208,80ZM96,56a32,32,0,0,1,64,0V80H96ZM208,208H48V96H208V208Zm-80-56a12,12,0,1,1-12-12A12,12,0,0,1,128,152Z"></path></svg>
                    </div>
                    <h3 style="font-size: 28px; margin-bottom: 10px;"><?php _e('Patient Portal', 'boocommerce'); ?></h3>
                    <p style="color: var(--bc-text-muted); margin-bottom: 35px;"><?php _e('Please sign in to manage your appointments, view medical history, and update your clinical profile.', 'boocommerce'); ?></p>
                    <a href="<?php echo home_url('/patient-login'); ?>" class="bc-btn" style="display: block; text-decoration: none; background: var(--bc-gradient); color: #fff; padding: 16px; border-radius: 16px; font-weight: 700; font-size: 16px; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3); text-align: center;"><?php _e('Access My Portal', 'boocommerce'); ?></a>
                </div>
            <?php else: 
                $current_user = wp_get_current_user();
                $email = $current_user->user_email;
                global $wpdb;

                $customer_table = $wpdb->prefix . 'bc_customers';
                $booking_table = $wpdb->prefix . 'bc_bookings';
                $services_table = $wpdb->prefix . 'bc_services';
                $staff_table = $wpdb->prefix . 'bc_staff';

                $bookings = $wpdb->get_results($wpdb->prepare(
                    "SELECT b.*, st.name as staff_name 
                     FROM $booking_table b 
                     JOIN $customer_table c ON b.customer_id = c.id
                     LEFT JOIN $staff_table st ON b.staff_id = st.id
                     WHERE c.email = %s 
                     ORDER BY b.booking_date DESC, b.start_time DESC",
                    $email
                ));

                foreach ($bookings as &$b) {
                    $b->service_name = __('Treatment Session', 'boocommerce');
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
                ?>
                <div class="bc-dashboard-main">
                    <div class="bc-dash-header">
                        <div>
                            <h3 style="margin: 0; font-size: 24px;"><?php printf(__('Welcome back, %s', 'boocommerce'), esc_html($current_user->first_name)); ?></h3>
                            <p style="margin: 5px 0 0; color: var(--bc-text-muted); font-size: 14px;"><?php _e('Managing your health, simplified.', 'boocommerce'); ?></p>
                        </div>
                        <a href="<?php echo wp_logout_url(get_permalink()); ?>" style="color: var(--bc-error); font-weight: 700; text-decoration: none; font-size: 14px; padding: 10px 20px; background: rgba(239, 68, 68, 0.05); border-radius: 12px;"><?php _e('Secure Logout', 'boocommerce'); ?></a>
                    </div>

                    <div class="bc-dash-nav">
                        <div class="bc-nav-link active" data-target="bc-dash-bookings">
                            <i class="ph ph-calendar-check" style="margin-right: 8px;"></i> <?php _e('My Appointments', 'boocommerce'); ?>
                        </div>
                        <div class="bc-nav-link" data-target="bc-dash-account">
                            <i class="ph ph-user-gear" style="margin-right: 8px;"></i> <?php _e('Profile & Security', 'boocommerce'); ?>
                        </div>
                    </div>

                    <div class="bc-dash-body">
                        <!-- Bookings Tab -->
                        <div id="bc-dash-bookings" class="bc-dash-content-panel">
                            <?php if (empty($bookings)): ?>
                                <div style="text-align: center; padding: 60px 0;">
                                    <div style="font-size: 48px; color: #e2e8f0; margin-bottom: 20px;"><i class="ph ph-calendar-plus"></i></div>
                                    <p style="color: var(--bc-text-muted); font-size: 16px;"><?php _e('You have no appointments scheduled at this time.', 'boocommerce'); ?></p>
                                    <a href="<?php echo home_url('/booking'); ?>" style="display: inline-block; margin-top: 20px; color: var(--bc-brand); font-weight: 700; text-decoration: none;"><?php _e('Schedule a Treatment', 'boocommerce'); ?> →</a>
                                </div>
                            <?php else: ?>
                                <div class="bc-booking-list">
                                    <?php foreach ($bookings as $b): ?>
                                        <div class="bc-appointment-card bc-booking-row" 
                                            data-id="<?php echo esc_attr($b->id); ?>"
                                            data-service="<?php echo esc_attr($b->service_name); ?>"
                                            data-staff="<?php echo esc_attr($b->staff_name ?: __('ENT Specialist', 'boocommerce')); ?>"
                                            data-date="<?php echo esc_attr(date('M d, Y', strtotime($b->booking_date))); ?>"
                                            data-time="<?php echo esc_attr(date('h:i A', strtotime($b->start_time))); ?>"
                                            data-amount="<?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')) . esc_attr($b->total_amount); ?>"
                                            data-status="<?php echo esc_attr(ucfirst($b->status)); ?>">
                                            
                                            <div class="bc-app-ref">#<?php echo esc_html($b->id); ?></div>
                                            
                                            <div class="bc-app-info">
                                                <h4><?php echo esc_html($b->service_name); ?></h4>
                                                <p><?php echo esc_html($b->staff_name ?: __('ENT Specialist', 'boocommerce')); ?></p>
                                            </div>
                                            
                                            <div class="bc-app-meta">
                                                <div class="bc-meta-item">
                                                    <i class="ph ph-calendar-blank"></i>
                                                    <span><?php echo esc_html(date('M d, Y', strtotime($b->booking_date))); ?></span>
                                                </div>
                                                <div class="bc-meta-item">
                                                    <i class="ph ph-clock"></i>
                                                    <span><?php echo esc_html(date('h:i A', strtotime($b->start_time))); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="bc-app-status">
                                                <span class="bc-status-pill status-<?php echo esc_attr($b->status); ?>"><?php echo esc_html($b->status); ?></span>
                                            </div>
                                            
                                            <div class="bc-app-actions">
                                                <?php if ($b->status !== 'cancelled'): ?>
                                                    <button class="bc-btn-icon bc-client-action-btn" data-action="reschedule" data-id="<?php echo esc_attr($b->id); ?>" title="<?php _e('Reschedule', 'boocommerce'); ?>">
                                                        <i class="ph ph-calendar-check"></i>
                                                    </button>
                                                    <button class="bc-btn-icon btn-danger bc-client-action-btn" data-action="cancel" data-id="<?php echo esc_attr($b->id); ?>" title="<?php _e('Cancel Request', 'boocommerce'); ?>">
                                                        <i class="ph ph-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Account Tab -->
                        <div id="bc-dash-account" class="bc-dash-content-panel" style="display: none;">
                            <form id="bc-client-account-form">
                                <!-- Section 1: Personal Identity -->
                                <div class="bc-form-section">
                                    <div class="bc-section-title">
                                        <i class="ph ph-user"></i>
                                        <h5><?php _e('Personal Identity', 'boocommerce'); ?></h5>
                                    </div>
                                    <div class="bc-form-row">
                                        <div class="bc-input-group">
                                            <label><?php _e('First Name', 'boocommerce'); ?></label>
                                            <div class="bc-field-wrapper">
                                                <div class="bc-field-icon"><i class="ph ph-identification-card"></i></div>
                                                <input type="text" name="first_name" value="<?php echo esc_attr($current_user->first_name); ?>" required class="bc-input">
                                            </div>
                                        </div>
                                        <div class="bc-input-group">
                                            <label><?php _e('Last Name', 'boocommerce'); ?></label>
                                            <div class="bc-field-wrapper">
                                                <div class="bc-field-icon"><i class="ph ph-identification-card"></i></div>
                                                <input type="text" name="last_name" value="<?php echo esc_attr($current_user->last_name); ?>" required class="bc-input">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 2: Clinical Communication -->
                                <div class="bc-form-section">
                                    <div class="bc-section-title">
                                        <i class="ph ph-broadcast"></i>
                                        <h5><?php _e('Clinical Communication', 'boocommerce'); ?></h5>
                                    </div>
                                    <div class="bc-form-row">
                                        <div class="bc-input-group">
                                            <label><?php _e('Verified Email', 'boocommerce'); ?></label>
                                            <div class="bc-field-wrapper">
                                                <div class="bc-field-icon"><i class="ph ph-envelope-simple-open"></i></div>
                                                <input type="email" value="<?php echo esc_attr($current_user->user_email); ?>" disabled class="bc-input">
                                            </div>
                                        </div>
                                        <div class="bc-input-group">
                                            <label><?php _e('Primary Contact', 'boocommerce'); ?></label>
                                            <div class="bc-field-wrapper">
                                                <div class="bc-field-icon"><i class="ph ph-phone"></i></div>
                                                <input type="text" name="phone" value="<?php echo esc_attr(get_user_meta($current_user->ID, 'bc_client_phone', true)); ?>" class="bc-input">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bc-input-group" style="margin-top: 20px;">
                                        <label><?php _e('Residential Address', 'boocommerce'); ?></label>
                                        <div class="bc-field-wrapper">
                                            <div class="bc-field-icon"><i class="ph ph-map-pin"></i></div>
                                            <textarea name="address" rows="2" class="bc-input" style="resize: none;"><?php echo esc_textarea(get_user_meta($current_user->ID, 'bc_client_address', true)); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 3: Portal Security -->
                                <div class="bc-form-section">
                                    <div class="bc-section-title">
                                        <i class="ph ph-shield-check"></i>
                                        <h5><?php _e('Portal Security', 'boocommerce'); ?></h5>
                                    </div>
                                    <div class="bc-input-group">
                                        <label><?php _e('Access Password', 'boocommerce'); ?> <small style="font-weight:400; opacity:0.7;">(Leave blank to keep current)</small></label>
                                        <div class="bc-field-wrapper">
                                            <div class="bc-field-icon"><i class="ph ph-key"></i></div>
                                            <input type="password" name="password" placeholder="••••••••••••" class="bc-input">
                                        </div>
                                    </div>
                                </div>

                                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0 10px;">
                                    <button type="submit" class="bc-btn" style="background: var(--bc-gradient); color: #fff; border: none; padding: 18px 50px; border-radius: 20px; font-weight: 800; cursor: pointer; box-shadow: 0 15px 30px rgba(99, 102, 241, 0.3); font-size: 16px;"><?php _e('Sync Profile Changes', 'boocommerce'); ?></button>
                                    <div id="bc-account-msg" style="font-weight: 800; font-size: 14px;"></div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Details Modal Redesign -->
        <div id="bc-details-modal" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.4); backdrop-filter:blur(12px); z-index:99999; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:50px; border-radius:32px; width:100%; max-width:560px; position:relative; box-shadow: var(--bc-shadow-lg); margin: 20px;">
                <span class="bc-modal-close" style="position:absolute; top:30px; right:30px; font-size:24px; color:var(--bc-text-muted); cursor:pointer;"><i class="ph ph-x"></i></span>
                <div style="display:flex; align-items:center; gap:20px; margin-bottom:40px;">
                    <div style="width:60px; height:60px; background:var(--bc-ring); color:var(--bc-brand); border-radius:20px; display:flex; align-items:center; justify-content:center; font-size:28px;"><i class="ph ph-file-medical"></i></div>
                    <h3 style="margin:0; font-size:24px;"><?php _e('Appointment Dossier', 'boocommerce'); ?></h3>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px; margin-bottom:30px; padding-bottom:30px; border-bottom:1px solid var(--bc-border);">
                    <div>
                        <label style="font-size:11px; font-weight:800; color:var(--bc-text-muted); text-transform:uppercase; letter-spacing:1px;"><?php _e('Reference', 'boocommerce'); ?></label>
                        <div id="bc-modal-id" style="font-size:20px; font-weight:800; color:var(--bc-brand); margin-top:5px;"></div>
                    </div>
                    <div>
                        <label style="font-size:11px; font-weight:800; color:var(--bc-text-muted); text-transform:uppercase; letter-spacing:1px;"><?php _e('Total Fee', 'boocommerce'); ?></label>
                        <div id="bc-modal-amount" style="font-size:20px; font-weight:800; color:var(--bc-text-main); margin-top:5px;"></div>
                    </div>
                </div>
                <div style="margin-bottom:25px;">
                    <label style="font-size:11px; font-weight:800; color:var(--bc-text-muted); text-transform:uppercase; letter-spacing:1px;"><?php _e('Procedure', 'boocommerce'); ?></label>
                    <div id="bc-modal-service" style="font-size:16px; font-weight:700; margin-top:5px;"></div>
                </div>
                <div style="margin-bottom:25px;">
                    <label style="font-size:11px; font-weight:800; color:var(--bc-text-muted); text-transform:uppercase; letter-spacing:1px;"><?php _e('Assigned Specialist', 'boocommerce'); ?></label>
                    <div id="bc-modal-staff" style="font-size:16px; font-weight:700; margin-top:5px;"></div>
                </div>
                <div style="margin-bottom:25px;">
                    <label style="font-size:11px; font-weight:800; color:var(--bc-text-muted); text-transform:uppercase; letter-spacing:1px;"><?php _e('Scheduled Time', 'boocommerce'); ?></label>
                    <div id="bc-modal-datetime" style="font-size:16px; font-weight:700; margin-top:5px;"></div>
                </div>
                <div>
                    <label style="font-size:11px; font-weight:800; color:var(--bc-text-muted); text-transform:uppercase; letter-spacing:1px;"><?php _e('Current Status', 'boocommerce'); ?></label>
                    <div style="margin-top:10px;"><span id="bc-modal-status" class="bc-status"></span></div>
                </div>
            </div>
        </div>

        <!-- Reschedule Modal Redesign -->
        <div id="bc-reschedule-modal" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.4); backdrop-filter:blur(12px); z-index:99999; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:50px; border-radius:32px; width:100%; max-width:560px; position:relative; box-shadow: var(--bc-shadow-lg); margin: 20px;">
                <span class="bc-reschedule-close" style="position:absolute; top:30px; right:30px; font-size:24px; color:var(--bc-text-muted); cursor:pointer;"><i class="ph ph-x"></i></span>
                <div style="display:flex; align-items:center; gap:20px; margin-bottom:40px;">
                    <div style="width:60px; height:60px; background:var(--bc-ring); color:var(--bc-brand); border-radius:20px; display:flex; align-items:center; justify-content:center; font-size:28px;"><i class="ph ph-calendar-blank"></i></div>
                    <h3 style="margin:0; font-size:24px;"><?php _e('Reschedule Treatment', 'boocommerce'); ?></h3>
                </div>
                <form id="bc-reschedule-form">
                    <input type="hidden" name="booking_id" id="bc-reschedule-id">
                    <div style="margin-bottom:25px;">
                        <label style="display:block; margin-bottom:10px; font-weight:800; font-size:12px; text-transform:uppercase; color:var(--bc-text-muted); letter-spacing:1px;"><?php _e('Select Professional', 'boocommerce'); ?></label>
                        <?php 
                        $all_staff = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "bc_staff WHERE status = 'active' ORDER BY name ASC");
                        ?>
                        <select name="reschedule_staff" id="bc-reschedule-staff" class="bc-input" style="width:100%;" required>
                            <option value=""><?php _e('-- Select Specialist --', 'boocommerce'); ?></option>
                            <?php foreach ($all_staff as $staff): ?>
                                <option value="<?php echo esc_attr($staff->id); ?>"><?php echo esc_html($staff->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-bottom:25px;">
                        <label style="display:block; margin-bottom:10px; font-weight:800; font-size:12px; text-transform:uppercase; color:var(--bc-text-muted); letter-spacing:1px;"><?php _e('Proposed Date', 'boocommerce'); ?></label>
                        <input type="date" name="reschedule_date" id="bc-reschedule-date" min="<?php echo date('Y-m-d'); ?>" class="bc-input" style="width:100%;" required>
                    </div>
                    <div id="bc-reschedule-slots-container" style="display:none; margin-bottom:35px;">
                        <label style="display:block; margin-bottom:10px; font-weight:800; font-size:12px; text-transform:uppercase; color:var(--bc-text-muted); letter-spacing:1px;"><?php _e('Available Clinical Slots', 'boocommerce'); ?></label>
                        <div class="bc-reschedule-slots" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap:12px; max-height:180px; overflow-y:auto; padding:5px;"></div>
                        <input type="hidden" name="reschedule_time" id="bc-reschedule-time-input" required>
                    </div>
                    <button type="submit" class="bc-btn" style="width:100%; background: var(--bc-gradient); color: #fff; border: none; padding: 16px; border-radius: 16px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);"><?php _e('Confirm Reschedule Request', 'boocommerce'); ?></button>
                    <div id="bc-reschedule-msg" style="margin-top:20px; font-weight:700; font-size:14px; text-align:center;"></div>
                </form>
            </div>
        </div>

        <!-- Cancel Modal Redesign -->
        <div id="bc-cancel-modal" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.4); backdrop-filter:blur(12px); z-index:99999; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:50px; border-radius:32px; width:100%; max-width:480px; position:relative; box-shadow: var(--bc-shadow-lg); margin: 20px; text-align:center;">
                <span class="bc-cancel-close" style="position:absolute; top:30px; right:30px; font-size:24px; color:var(--bc-text-muted); cursor:pointer;"><i class="ph ph-x"></i></span>
                <div style="width:80px; height:80px; background:rgba(239, 68, 68, 0.1); color:var(--bc-error); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:32px; margin:0 auto 30px;"><i class="ph ph-warning-octagon"></i></div>
                <h3 style="margin:0 0 10px; font-size:24px;"><?php _e('Request Cancellation', 'boocommerce'); ?></h3>
                <p style="color:var(--bc-text-muted); margin-bottom:35px;"><?php printf(__('Are you sure you want to request cancellation for appointment %s? This request is subject to clinical review.', 'boocommerce'), '<strong id="bc-cancel-title-id"></strong>'); ?></p>
                <form id="bc-cancel-form">
                    <input type="hidden" name="booking_id" id="bc-cancel-id">
                    <div style="display:flex; gap:15px;">
                        <button type="button" class="bc-cancel-close" style="flex:1; padding:16px; border-radius:14px; border:1.5px solid var(--bc-border); background:#fff; font-weight:700; cursor:pointer;"><?php _e('Dismiss', 'boocommerce'); ?></button>
                        <button type="submit" class="bc-btn" style="flex:1; padding:16px; border-radius:14px; border:none; background:var(--bc-error); color:#fff; font-weight:700; cursor:pointer; box-shadow: 0 10px 20px rgba(239, 68, 68, 0.2);"><?php _e('Confirm', 'boocommerce'); ?></button>
                    </div>
                    <div id="bc-cancel-msg" style="margin-top:20px; font-weight:700; font-size:14px;"></div>
                </form>
            </div>
        </div>

        <div style="text-align:center; margin: 35px auto;">
            <a href="<?php echo esc_url(home_url('/booking')); ?>" class="bc-btn"
                style="display:inline-block; text-decoration:none; padding: 14px 40px; border-radius: 12px; background:var(--bc-gradient); color:#fff; font-weight:700; font-size:16px; box-shadow:var(--bc-shadow-md);"><?php _e('Book a Service', 'boocommerce'); ?></a>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Virtual route for /booking
     */
    public function render_booking_page_shortcode($atts)
    {
        $virtual_bg_color = get_option('bc_virtual_bg_color', '#f8fafc');
        ob_start();
        ?>
        <div class="bc-virtual-wrapper" style="padding: 60px 20px; background: #f1f5f9; min-height: 80vh; display: flex; justify-content: center; align-items: flex-start;">
            <div class="bc-virtual-page" style="width: 100%; max-width: 900px; background: <?php echo esc_attr($virtual_bg_color); ?>; padding: 40px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); border: 1px solid rgba(0, 0, 0, 0.05);">
                <style>
                    #bc-booking-wizard-container { margin: 0; padding: 0; background: transparent; border: none; box-shadow: none; }
                    @media (max-width: 768px) { .bc-virtual-wrapper { padding: 20px 10px; } .bc-virtual-page { padding: 20px; } }
                </style>
                <?php echo $this->render_booking_widget(array()); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_dashboard_page_shortcode($atts)
    {
        $virtual_bg_color = get_option('bc_virtual_bg_color', '#f8fafc');
        ob_start();
        ?>
        <div class="bc-virtual-wrapper" style="padding: 60px 20px; background: #f1f5f9; min-height: 80vh; display: flex; justify-content: center; align-items: flex-start;">
            <div class="bc-virtual-page" style="width: 100%; max-width: 900px; background: <?php echo esc_attr($virtual_bg_color); ?>; padding: 40px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.1); border: 1px solid rgba(0, 0, 0, 0.05);">
                <style>
                    @media (max-width: 768px) { .bc-virtual-wrapper { padding: 20px 10px; } .bc-virtual-page { padding: 20px; } }
                </style>
                <?php echo $this->render_client_dashboard(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_login_page_shortcode($atts)
    {
        ob_start();
        ?>
        <div class="bc-virtual-wrapper" style="padding: 80px 20px; background: #f1f5f9; min-height: 100vh; display: flex; justify-content: center; align-items: center;">
            <div class="bc-virtual-page" style="width: 100%; max-width: 480px; background: #fff; padding: 50px; border-radius: 32px; box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.15); border: 1px solid rgba(0, 0, 0, 0.05);">
                <div style="text-align: center; margin-bottom: 40px;">
                    <div style="width: 80px; height: 80px; background: var(--bc-ring); color: var(--bc-brand); border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 25px;">
                        <svg width="40" height="40" viewBox="0 0 256 256" fill="currentColor"><path d="M208,80H176V56a48,48,0,0,0-96,0V80H48A16,16,0,0,0,32,96V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V96A16,16,0,0,0,208,80ZM96,56a32,32,0,0,1,64,0V80H96ZM208,208H48V96H208V208Zm-80-56a12,12,0,1,1-12-12A12,12,0,0,1,128,152Z"></path></svg>
                    </div>
                    <h2 style="font-size: 28px; margin-bottom: 10px; color: var(--bc-text-main);"><?php _e('Clinical Login', 'boocommerce'); ?></h2>
                    <p style="color: var(--bc-text-muted); font-size: 15px;"><?php _e('Enter your credentials to access the portal.', 'boocommerce'); ?></p>
                </div>

                <?php 
                if (isset($_GET['login']) && $_GET['login'] === 'failed') {
                    echo '<div class="bc-login-error" style="background:#fef2f2; color:#dc2626; padding:15px; border-radius:12px; border:1px solid #fee2e2; margin-bottom:25px; font-size:14px; font-weight:700; text-align:center;"><i class="ph ph-warning-circle" style="margin-right:8px;"></i>' . __('Invalid credentials. Please try again.', 'boocommerce') . '</div>';
                }
                ?>
                <div id="bc-js-login-error" style="display:none; background:#fffbeb; color:#d97706; padding:15px; border-radius:12px; border:1px solid #fef3c7; margin-bottom:25px; font-size:14px; font-weight:700; text-align:center;"><i class="ph ph-info" style="margin-right:8px;"></i><?php _e('Please fill in all fields.', 'boocommerce'); ?></div>

                <?php 
                $args = array(
                    'echo'           => true,
                    'redirect'       => home_url('/booking-dashboard'), 
                    'form_id'        => 'bc-login-form',
                    'label_username' => __('Registered Email', 'boocommerce'),
                    'label_password' => __('Secure Password', 'boocommerce'),
                    'label_log_in'   => __('Enter My Portal', 'boocommerce'),
                    'remember'       => true,
                    'value_remember' => true
                );
                wp_login_form($args); 
                ?>

                <div style="margin-top: 30px; text-align: center; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                    <a href="javascript:void(0)" id="bc-forgot-password-trigger" style="color: var(--bc-brand); font-weight: 700; text-decoration: none; font-size: 14px;"><?php _e('Lost Clinical Access?', 'boocommerce'); ?></a>
                </div>
            </div>
        </div>

        <!-- Forgot Password Modal -->
        <div id="bc-forgot-modal" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.4); backdrop-filter:blur(12px); z-index:99999; align-items:center; justify-content:center;">
            <div style="background:#fff; padding:50px; border-radius:32px; width:100%; max-width:480px; position:relative; box-shadow: var(--bc-shadow-lg); margin: 20px; text-align:center;">
                <span class="bc-forgot-close" style="position:absolute; top:30px; right:30px; font-size:24px; color:var(--bc-text-muted); cursor:pointer;"><i class="ph ph-x"></i></span>
                <div style="width:80px; height:80px; background:var(--bc-ring); color:var(--bc-brand); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:32px; margin:0 auto 30px;"><i class="ph ph-shield-warning"></i></div>
                <h3 style="margin:0 0 10px; font-size:24px;"><?php _e('Credential Reset', 'boocommerce'); ?></h3>
                <p style="color:var(--bc-text-muted); margin-bottom:25px;"><?php _e('For security reasons, digital password resets are disabled. Please contact our clinical support team to verify your identity and reset your portal access.', 'boocommerce'); ?></p>
                
                <div style="background:#f8fafc; padding:20px; border-radius:16px; border:1px solid #e2e8f0; margin-bottom:30px;">
                    <div style="font-size:11px; font-weight:800; color:var(--bc-text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:10px;"><?php _e('Primary Support Contact', 'boocommerce'); ?></div>
                    <a href="mailto:<?php echo get_option('admin_email'); ?>" style="font-size:18px; font-weight:800; color:var(--bc-brand); text-decoration:none;"><?php echo get_option('admin_email'); ?></a>
                </div>

                <button type="button" class="bc-forgot-close" style="width:100%; background: var(--bc-gradient); color: #fff; border: none; padding: 16px; border-radius: 16px; font-weight: 700; cursor: pointer;"><?php _e('I Understand', 'boocommerce'); ?></button>
            </div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#bc-login-form').on('submit', function(e) {
                    const user = $('#user_login').val();
                    const pass = $('#user_pass').val();
                    if (!user || !pass) {
                        e.preventDefault();
                        $('#bc-js-login-error').fadeIn();
                        setTimeout(() => $('#bc-js-login-error').fadeOut(), 3000);
                    }
                });
            });
        </script>

        <style>
            #bc-login-form p { margin-bottom: 20px; }
            #bc-login-form label { display: block; margin-bottom: 8px; font-weight: 800; font-size: 12px; text-transform: uppercase; color: var(--bc-text-muted); letter-spacing: 1px; }
            #bc-login-form .input { width: 100%; padding: 16px; border: 1.5px solid #e2e8f0; border-radius: 16px; font-size: 15px; font-family: 'Inter', sans-serif; outline: none; transition: all 0.3s; color: var(--bc-text-main) !important; }
            #bc-login-form .input:focus { border-color: var(--bc-brand); box-shadow: 0 0 0 4px var(--bc-ring); }
            #bc-login-form .login-submit { margin-top: 30px; }
            #bc-login-form input#wp-submit { 
                all: unset !important;
                width: 100% !important; 
                box-sizing: border-box !important;
                background: var(--bc-gradient) !important; 
                color: #ffffff !important; 
                -webkit-text-fill-color: #ffffff !important;
                padding: 18px 20px !important; 
                border-radius: 18px !important; 
                font-weight: 800 !important; 
                cursor: pointer !important; 
                box-shadow: 0 12px 30px rgba(99, 102, 241, 0.3) !important; 
                font-size: 16px !important;
                font-family: 'Inter', sans-serif !important;
                text-align: center !important;
                display: block !important;
                transition: all 0.3s ease !important;
                -webkit-appearance: none !important;
                appearance: none !important;
                text-transform: none !important;
                letter-spacing: normal !important;
                text-indent: 0 !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            #bc-login-form input#wp-submit:hover {
                transform: translateY(-2px);
                box-shadow: 0 15px 35px rgba(99, 102, 241, 0.4) !important;
                opacity: 0.9;
            }
            #bc-login-form .login-remember { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--bc-text-muted); }
        </style>
        <?php
        return ob_get_clean();
    }

    public function bc_handle_login_failed($username) {
        $referrer = wp_get_referer();
        if ($referrer && strpos($referrer, 'patient-login') !== false) {
            wp_redirect(add_query_arg('login', 'failed', $referrer));
            exit;
        }
    }
    public function bc_override_login_template($template)
    {
        if (is_page('patient-login')) {
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo( 'charset' ); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php wp_head(); ?>
                <style>
                    body, html { margin: 0; padding: 0; width: 100%; height: 100%; overflow-x: hidden; }
                    .bc-virtual-wrapper { min-height: 100vh !important; width: 100vw !important; padding: 0 !important; margin: 0 !important; }
                </style>
            </head>
            <body <?php body_class(); ?>>
                <?php echo $this->render_login_page_shortcode(array()); ?>
                <?php wp_footer(); ?>
            </body>
            </html>
            <?php
            exit;
        }
        return $template;
    }
    public function bc_login_redirect($redirect_to, $request, $user)
    {

        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array('subscriber', $user->roles)) {
                return home_url('/booking-dashboard');
            }
        }
        return $redirect_to;
    }

    public function bc_restrict_admin_access()
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
    public function bc_logout_redirect($redirect_to, $requested_redirect_to, $user)
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
        ), $atts, 'bc_services');

        global $wpdb;
        $query = "SELECT * FROM {$wpdb->prefix}bc_services WHERE status = 'active'";
        
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
            return '<p style="text-align:center; color:var(--bc-text-muted);">' . __('No services found matching your criteria.', 'boocommerce') . '</p>';
        }

        // Fetch styling preferences
        $brand_color = get_option('bc_brand_color', '#6366f1');
        $brand_color_end = get_option('bc_brand_color_end', '#a855f7');
        $font_family = get_option('bc_font_family', 'Inter');
        $border_radius = get_option('bc_border_radius', '16');
        $layout = !empty($atts['layout']) ? $atts['layout'] : get_option('bc_showcase_layout', 'grid');
        
        $card_bg = get_option('bc_card_bg_color', '#ffffff');
        $heading_color = get_option('bc_heading_text_color', '#0f172a');
        $body_color = get_option('bc_body_text_color', '#64748b');

        ob_start();
        ?>
        <style>
            .bc-services-showcase, .bc-services-showcase * { box-sizing: border-box; }
            .bc-services-showcase { margin: 40px 0; font-family: 'Inter', sans-serif; }
            
            /* Showcase Layout Engine */
            .bc-showcase-container {
                display: grid;
                gap: 25px;
                width: 100%;
            }

            .bc-layout-grid .bc-showcase-container {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }

            /* Kinetic Carousel - High-End Design */
            .bc-layout-carousel {
                overflow: hidden;
                position: relative;
                padding: 20px 0 60px 0;
                width: 100%;
                max-width: 1300px; /* Max width for 4 cards + gaps */
                margin-left: auto;
                margin-right: auto;
            }
            .bc-layout-carousel .bc-showcase-container {
                display: flex;
                gap: 25px;
                flex-wrap: nowrap;
                transition: transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
                will-change: transform;
                cursor: grab;
                user-select: none;
                width: max-content !important;
            }
            .bc-layout-carousel .bc-showcase-container:active { cursor: grabbing; }
            
            .bc-layout-carousel .bc-showcase-card {
                width: 300px; /* Fixed Width */
                flex: 0 0 300px;
                height: 480px; /* Fixed Height */
                transition: all 0.5s ease;
                margin: 0 !important;
            }
            
            /* Section Responsiveness based on Card Count */
            @media (max-width: 1350px) {
                .bc-layout-carousel { max-width: 950px; } /* 3 items */
            }
            @media (max-width: 1000px) {
                .bc-layout-carousel { max-width: 625px; } /* 2 items */
            }
            @media (max-width: 650px) {
                .bc-layout-carousel { max-width: 300px; } /* 1 item */
            }

            /* Carousel Navigation Arrows - Corner Positioned */
            .bc-carousel-nav {
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
            .bc-nav-btn {
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
            .bc-nav-btn:hover {
                transform: scale(1.15);
                box-shadow: 0 15px 30px rgba(0,0,0,0.15);
                color: <?php echo esc_attr($brand_color_end); ?>;
            }
            .bc-nav-btn.prev { margin-left: 10px; }
            .bc-nav-btn.next { margin-right: 10px; }

            @media (max-width: 1400px) {
                .bc-carousel-nav { left: 0; right: 0; }
                .bc-nav-btn { width: 40px; height: 40px; }
            }

            /* Dots - Stylish */
            .bc-carousel-dots {
                display: flex;
                justify-content: center;
                gap: 12px;
                margin-top: 40px;
            }
            .bc-dot {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: #e2e8f0;
                cursor: pointer;
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }
            .bc-dot.active {
                background: linear-gradient(135deg, <?php echo esc_attr($brand_color); ?>, <?php echo esc_attr($brand_color_end); ?>);
                width: 35px;
                border-radius: 20px;
            }

            .bc-showcase-card {
                background: <?php echo esc_attr($card_bg); ?>;
                border: 1px solid #e2e8f0;
                border-radius: <?php echo esc_attr($border_radius); ?>px;
                overflow: hidden;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex;
                flex-direction: column;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            }
            .bc-showcase-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.1);
                border-color: <?php echo esc_attr($brand_color); ?>44;
            }
            .bc-showcase-img {
                height: 220px;
                background-position: center;
                background-size: cover;
                position: relative;
                overflow: hidden;
                background-color: #f8fafc;
            }
            .bc-showcase-card:hover .bc-showcase-img { transform: scale(1.05); }
            .bc-showcase-badge {
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
            .bc-showcase-content {
                padding: 24px;
                flex: 1;
                display: flex;
                flex-direction: column;
            }
            .bc-showcase-title {
                margin: 0 0 12px 0;
                font-size: 20px;
                font-weight: 800;
                color: <?php echo esc_attr($heading_color); ?>;
                letter-spacing: -0.01em;
            }
            .bc-showcase-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                font-size: 14px;
                font-weight: 600;
                color: <?php echo esc_attr($body_color); ?>;
            }
            .bc-showcase-price {
                color: <?php echo esc_attr($brand_color); ?>;
                font-size: 18px;
                font-weight: 800;
            }
            .bc-showcase-desc {
                font-size: 14px;
                line-height: 1.6;
                margin-bottom: 25px;
                color: <?php echo esc_attr($body_color); ?>;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .bc-showcase-btn {
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
            .bc-showcase-btn:hover {
                filter: brightness(1.1);
                box-shadow: 0 8px 20px <?php echo esc_attr($brand_color); ?>44;
                color: white;
            }

            @media (max-width: 768px) {
                .bc-layout-horizontal .bc-showcase-card,
                .bc-layout-list .bc-showcase-card {
                    flex-direction: column;
                    height: auto;
                }
                .bc-layout-horizontal .bc-showcase-img,
                .bc-layout-list .bc-showcase-img {
                    width: 100%;
                    height: 180px;
                }
                .bc-layout-list .bc-showcase-content {
                    padding: 20px;
                    flex-direction: column;
                    align-items: stretch;
                }
                .bc-layout-list .bc-showcase-title { margin-bottom: 10px; }
                .bc-layout-list .bc-showcase-meta { margin-bottom: 15px; }
            }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const wrappers = document.querySelectorAll('.bc-layout-carousel');
            wrappers.forEach(wrapper => {
                const container = wrapper.querySelector('.bc-showcase-container');
                const cards = container.querySelectorAll('.bc-showcase-card');
                if (cards.length === 0) return;

                // Create Nav Arrows
                const nav = document.createElement('div');
                nav.className = 'bc-carousel-nav';
                nav.innerHTML = `
                    <div class="bc-nav-btn prev"><span class="dashicons dashicons-arrow-left-alt2"></span></div>
                    <div class="bc-nav-btn next"><span class="dashicons dashicons-arrow-right-alt2"></span></div>
                `;
                wrapper.appendChild(nav);
                
                nav.querySelector('.prev').addEventListener('click', () => { clearInterval(autoSlideInterval); goToSlide(currentIndex - 1); startAutoSlide(); });
                nav.querySelector('.next').addEventListener('click', () => { clearInterval(autoSlideInterval); goToSlide(currentIndex + 1); startAutoSlide(); });

                // Create Dots
                const dotsContainer = document.createElement('div');
                dotsContainer.className = 'bc-carousel-dots';
                cards.forEach((_, i) => {
                    const dot = document.createElement('div');
                    dot.className = 'bc-dot' + (i === 0 ? ' active' : '');
                    dot.addEventListener('click', () => { clearInterval(autoSlideInterval); goToSlide(i); startAutoSlide(); });
                    dotsContainer.appendChild(dot);
                });
                wrapper.appendChild(dotsContainer);
                const dots = dotsContainer.querySelectorAll('.bc-dot');

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
        
        <div class="bc-services-showcase bc-layout-<?php echo esc_attr($layout); ?>">
            <div class="bc-showcase-container">
                <?php foreach ($services as $s): ?>
                    <div class="bc-showcase-card">
                        <div class="bc-showcase-img" style="background-image: url('<?php echo esc_url($s->image_url ?: BC_PLUGIN_URL . 'assets/public/img/service-placeholder.jpg'); ?>');">
                            <?php if ($s->category): ?>
                                <span class="bc-showcase-badge"><?php echo esc_html($s->category); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="bc-showcase-content">
                            <div>
                                <h4 class="bc-showcase-title"><?php echo esc_html($s->name); ?></h4>
                                <div class="bc-showcase-meta">
                                    <span><i class="ph ph-clock" style="font-size:14px; vertical-align:middle; margin-right:4px;"></i> <?php echo esc_html($s->duration); ?> min</span>
                                    <span class="bc-showcase-price"><?php echo bc_get_currency_symbol(get_option('bc_currency', 'USD')); ?><?php echo esc_html($s->price); ?></span>
                                </div>
                                <p class="bc-showcase-desc"><?php echo esc_html(wp_trim_words($s->description, 15)); ?></p>
                            </div>
                            <?php
                            global $wpdb;
                            $page_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[bc_booking_widget]%' AND post_status = 'publish' LIMIT 1");
                            $booking_url = $page_id ? get_permalink($page_id) : home_url('/booking');
                            ?>
                            <a href="<?php echo esc_url(add_query_arg(['bc_select_service' => $s->id, 'bc_jump_to_staff' => '1'], $booking_url)); ?>" class="bc-showcase-btn">Book Appointment</a>
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
        $l_basket = get_option('bc_label_basket_btn', __('Services Selected', 'boocommerce'));
        $i_basket = get_option('bc_icon_basket_btn', 'dashicons-cart');
        
        ob_start();
        ?>
        <div class="bc-standalone-basket" style="display:inline-block; position:relative;">
            <div id="bc-basket-trigger" class="bc-basket-trigger-btn bc-btn"
                style="position:relative; display:inline-flex; align-items:center; background:var(--bc-gradient); color:#fff; cursor:pointer; font-size: 13px; padding: 10px 26px; border-radius: 12px; font-weight: 700; transition:all 0.3s; gap:12px; box-shadow: 0 4px 12px var(--bc-ring);">
                <div style="position:relative; display:flex; align-items:center; justify-content:center;">
                    <span class="<?php echo Bc_Public::get_icon_class($i_basket); ?>" style="font-size:20px; width:20px; height:20px;"></span> 
                    <span class="bc-basket-count-val" style="position:absolute; top:-10px; right:-12px; background:var(--bc-brand-alt); color:#fff; min-width:18px; height:18px; border-radius:10px; font-size:10px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff; font-weight:900; line-height:1; box-shadow:0 2px 4px rgba(0,0,0,0.1);">0</span>
                </div>
                <span><?php echo esc_html($l_basket); ?></span>
                
                <!-- Basket Popup -->
                <div id="bc-basket-popup" style="display:none; position:absolute; top:calc(100% + 15px); right:0; width:320px; background:#fff; border-radius:16px; border:1px solid var(--bc-input-border); box-shadow:0 20px 40px rgba(0,0,0,0.2); z-index:999999; padding:20px; text-align:left; cursor:default;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--bc-input-border); padding-bottom:10px;">
                        <h4 style="margin:0; font-size:16px; font-weight:800; color:var(--bc-heading);"><?php _e('Your Selection', 'boocommerce'); ?></h4>
                        <span id="bc-close-basket" style="font-size:20px; cursor:pointer; color:var(--bc-body);">&times;</span>
                    </div>
                    <div id="bc-basket-items" style="max-height:250px; overflow-y:auto; margin-bottom:15px; display:flex; flex-direction:column; gap:10px;">
                        <p id="bc-empty-basket-msg" style="text-align:center; color:var(--bc-body); opacity:0.6; font-size:14px; margin:20px 0;"><?php _e('No services selected yet.', 'boocommerce'); ?></p>
                    </div>
                    <div id="bc-basket-footer" style="border-top:1px solid var(--bc-input-border); padding-top:15px; display:none;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-weight:800; color:var(--bc-heading);">
                            <span><?php _e('Total', 'boocommerce'); ?></span>
                            <span id="bc-basket-total">0.00</span>
                        </div>
                        <?php
                        global $wpdb;
                        $page_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[bc_booking_widget]%' AND post_status = 'publish' LIMIT 1");
                        $booking_url = $page_id ? get_permalink($page_id) : home_url('/booking');
                        ?>
                        <a href="<?php echo esc_url(add_query_arg('bc_jump_to_staff', '1', $booking_url)); ?>" class="bc-btn bc-next-btn bc-basket-checkout-btn" style="width:100%; padding:12px; text-decoration:none; text-align:center;"><?php _e('Continue Booking', 'boocommerce'); ?></a>
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
        $enabled = get_option('bc_menu_basket_enable', 'no');
        if ($enabled !== 'yes') return $items;

        $text = get_option('bc_menu_basket_text', __('Selection', 'boocommerce'));
        $icon = get_option('bc_menu_basket_icon', 'dashicons-cart');
        $pos = get_option('bc_menu_basket_pos', 'after');

        $basket_html = '
        <li class="menu-item bc-menu-basket-wrap" style="position:relative; display:inline-flex; align-items:center;">
            <a href="#" class="bc-basket-trigger-btn" style="display:flex; align-items:center; gap:8px; text-decoration:none;">
                <div style="position:relative; display:flex; align-items:center; justify-content:center;">
                    <span class="' . Bc_Public::get_icon_class($icon) . '"></span>
                    <span class="bc-basket-count-val" style="position:absolute; top:-8px; right:-10px; background:var(--bc-brand-alt, #ef4444); color:#fff; min-width:16px; height:16px; border-radius:50%; font-size:9px; display:flex; align-items:center; justify-content:center; padding:0 3px; border:1px solid #fff; font-weight:900;">0</span>
                </div>
                ' . ( !empty($text) ? '<span style="margin-left:5px;">' . esc_html($text) . '</span>' : '' ) . '
            </a>
            <div id="bc-basket-popup" style="display:none; position:absolute; top:100%; right:0; width:300px; background:#fff; border-radius:12px; border:1px solid #eee; box-shadow:0 15px 30px rgba(0,0,0,0.1); z-index:99999; padding:15px; text-align:left; cursor:default; color:#333;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; border-bottom:1px solid #eee; padding-bottom:8px;">
                    <h4 style="margin:0; font-size:14px; font-weight:800;">' . __('Your Selection', 'boocommerce') . '</h4>
                </div>
                <div id="bc-basket-items" style="max-height:200px; overflow-y:auto; margin-bottom:12px; display:flex; flex-direction:column; gap:8px;">
                    <p id="bc-empty-basket-msg" style="text-align:center; font-size:12px; opacity:0.6; margin:15px 0;">' . __('Empty', 'boocommerce') . '</p>
                </div>
                <div id="bc-basket-footer" style="display:none;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-weight:800; font-size:13px;">
                        <span>' . __('Total', 'boocommerce') . '</span>
                        <span id="bc-basket-total">0.00</span>
                    </div>
                    <a href="' . esc_url(add_query_arg('bc_jump_to_staff', '1', home_url('/booking'))) . '" class="bc-basket-checkout-btn" style="display:block; background:var(--bc-brand, #6366f1); color:#fff; text-align:center; padding:10px; border-radius:8px; text-decoration:none; font-size:12px; font-weight:700;">' . __('Book Now', 'boocommerce') . '</a>
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
        $enabled = get_option('bc_float_btn_enable', 'no');
        if ($enabled !== 'yes') return;

        $pos = get_option('bc_float_btn_pos', 'bottom-right');
        $icon = get_option('bc_float_btn_icon', 'dashicons-calendar-alt');
        
        $side = ($pos === 'bottom-left') ? 'left: 30px;' : 'right: 30px;';
        
        global $wpdb;
        $page_id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[bc_booking_widget]%' AND post_status = 'publish' LIMIT 1");
        $booking_url = $page_id ? get_permalink($page_id) : home_url('/booking');

        echo '
        <div class="bc-standalone-basket bc-floating-basket-wrap" style="position: fixed; bottom: 30px; ' . $side . ' z-index: 99999;">
            <div class="bc-basket-trigger-btn" style="width: 60px; height: 60px; background: var(--bc-gradient, linear-gradient(135deg, #6366f1 0%, #a855f7 100%)); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4); position: relative; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                <span class="' . Bc_Public::get_icon_class($icon) . '" style="font-size: 24px; width: 24px; height: 24px;"></span>
                <span class="bc-basket-count-val" style="position:absolute; top:-5px; right:-5px; background:var(--bc-brand-alt, #ef4444); color:#fff; min-width:22px; height:22px; border-radius:50%; font-size:10px; display:flex; align-items:center; justify-content:center; padding:0 4px; border:2px solid #fff; font-weight:900; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">0</span>
            </div>
            
            <div id="bc-basket-popup" style="display:none; position:absolute; bottom:80px; ' . (($pos === 'bottom-left') ? 'left:0;' : 'right:0;') . ' width:320px; background:#fff; border-radius:16px; border:1px solid #eee; box-shadow:0 20px 40px rgba(0,0,0,0.2); padding:20px; text-align:left; cursor:default; color:#333;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <h4 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">' . __('Your Selection', 'boocommerce') . '</h4>
                    <span id="bc-close-basket" style="font-size:24px; cursor:pointer; color:#64748b; line-height:1;">&times;</span>
                </div>
                <div id="bc-basket-items" style="max-height:300px; overflow-y:auto; margin-bottom:15px; display:flex; flex-direction:column; gap:10px;">
                    <p id="bc-empty-basket-msg" style="text-align:center; font-size:14px; color:#64748b; margin:30px 0;">' . __('Your basket is empty', 'boocommerce') . '</p>
                </div>
                <div id="bc-basket-footer" style="display:none; border-top:1px solid #eee; padding-top:15px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-weight:800; font-size:14px; color:#0f172a;">
                        <span>' . __('Total', 'boocommerce') . '</span>
                        <span id="bc-basket-total">0.00</span>
                    </div>
                    <a href="' . esc_url(add_query_arg('bc_jump_to_staff', '1', $booking_url)) . '" class="bc-basket-checkout-btn" style="display:block; background:var(--bc-brand, #6366f1); color:#fff; text-align:center; padding:12px; border-radius:10px; text-decoration:none; font-size:14px; font-weight:700; transition:all 0.3s; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);">' . __('Book Now', 'boocommerce') . '</a>
                </div>
                <div id="bc-empty-basket-footer" style="display:block; text-align:center;">
                     <a href="' . esc_url($booking_url) . '" style="display:inline-block; background:rgba(15, 23, 42, 0.05); color:#0f172a; padding:12px 25px; border-radius:10px; text-decoration:none; font-size:13px; font-weight:700; transition:all 0.2s;">' . __('Start Booking', 'boocommerce') . '</a>
                </div>
            </div>
        </div>
        <style>
            .bc-basket-trigger-btn:hover { transform: scale(1.1) translateY(-3px); box-shadow: 0 15px 30px rgba(99, 102, 241, 0.5); }
            .bc-basket-trigger-btn:active { transform: scale(0.95); }
            .bc-basket-checkout-btn:hover { background: var(--bc-primary-dark, #4f46e5); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(99, 102, 241, 0.3); }
        </style>';
    }
}
