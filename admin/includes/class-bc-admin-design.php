<?php
class Bc_Admin_Design {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bc_design_nonce']) && wp_verify_nonce($_POST['bc_design_nonce'], 'bc_save_design')) {
            if (isset($_POST['bc_reset_design'])) {
                delete_option('bc_label_basket_btn');
                delete_option('bc_icon_basket_btn');
                delete_option('bc_icon_view_details');
                delete_option('bc_menu_basket_enable');
                delete_option('bc_float_btn_pos');
                delete_option('bc_showcase_layout');
                delete_option('bc_menu_basket_text');
                delete_option('bc_menu_basket_pos');
                delete_option('bc_menu_basket_icon');
                echo '<div class="notice notice-info is-dismissible bc-custom-notice"><p>' . __('Factory default settings restored successfully!', 'boocommerce') . '</p></div>';
            } else {
                // Layout & Colors
                update_option('bc_service_layout', sanitize_text_field($_POST['bc_service_layout']));
                update_option('bc_brand_color', sanitize_hex_color($_POST['bc_brand_color']));
                update_option('bc_brand_color_end', sanitize_hex_color($_POST['bc_brand_color_end']));
                update_option('bc_accent_color', sanitize_hex_color($_POST['bc_accent_color']));
                update_option('bc_virtual_bg_color', sanitize_hex_color($_POST['bc_virtual_bg_color']));
                
                // UI Effects
                update_option('bc_font_family', sanitize_text_field($_POST['bc_font_family']));
                update_option('bc_border_radius', intval($_POST['bc_border_radius']));
                update_option('bc_shadow_intensity', sanitize_text_field($_POST['bc_shadow_intensity']));
                
                // Content & Labels
                update_option('bc_label_step1', sanitize_text_field($_POST['bc_label_step1']));
                update_option('bc_label_step2', sanitize_text_field($_POST['bc_label_step2']));
                update_option('bc_label_step3', sanitize_text_field($_POST['bc_label_step3']));
                update_option('bc_label_step4', sanitize_text_field($_POST['bc_label_step4']));
                update_option('bc_label_next_btn', sanitize_text_field($_POST['bc_label_next_btn']));
                update_option('bc_label_prev_btn', sanitize_text_field($_POST['bc_label_prev_btn']));

                // Detailed Element Styling
                update_option('bc_card_bg_color', sanitize_hex_color($_POST['bc_card_bg_color']));
                update_option('bc_heading_text_color', sanitize_hex_color($_POST['bc_heading_text_color']));
                update_option('bc_body_text_color', sanitize_hex_color($_POST['bc_body_text_color']));
                update_option('bc_input_bg_color', sanitize_hex_color($_POST['bc_input_bg_color']));
                update_option('bc_input_border_color', sanitize_hex_color($_POST['bc_input_border_color']));

                // Basket & Icons
                update_option('bc_label_basket_btn', sanitize_text_field($_POST['bc_label_basket_btn']));
                update_option('bc_icon_basket_btn', sanitize_text_field($_POST['bc_icon_basket_btn']));
                update_option('bc_icon_view_details', sanitize_text_field($_POST['bc_icon_view_details']));
                update_option('bc_menu_basket_enable', isset($_POST['bc_menu_basket_enable']) ? 'yes' : 'no');
                update_option('bc_float_btn_enable', isset($_POST['bc_float_btn_enable']) ? 'yes' : 'no');
                update_option('bc_float_btn_pos', sanitize_text_field($_POST['bc_float_btn_pos']));
                update_option('bc_float_btn_text', sanitize_text_field($_POST['bc_float_btn_text']));
                update_option('bc_float_btn_icon', sanitize_text_field($_POST['bc_float_btn_icon']));
                update_option('bc_basket_mode', sanitize_text_field($_POST['bc_basket_mode']));
                update_option('bc_menu_basket_text', sanitize_text_field($_POST['bc_menu_basket_text']));
                update_option('bc_menu_basket_pos', sanitize_text_field($_POST['bc_menu_basket_pos']));
                update_option('bc_menu_basket_icon', sanitize_text_field($_POST['bc_menu_basket_icon']));
                update_option('bc_showcase_layout', sanitize_text_field($_POST['bc_showcase_layout']));

                echo '<div class="notice notice-success is-dismissible bc-custom-notice"><p>' . __('Advanced customization settings applied successfully!', 'boocommerce') . '</p></div>';
            }
        }

        $service_layout = get_option('bc_service_layout', 'modern_grid');
        $brand_color = get_option('bc_brand_color', '#6366f1');
        $brand_color_end = get_option('bc_brand_color_end', '#a855f7');
        $accent_color = get_option('bc_accent_color', '#4f46e5');
        $virtual_bg_color = get_option('bc_virtual_bg_color', '#f8fafc');

        // Detailed Styling Defaults
        $card_bg = get_option('bc_card_bg_color', '#ffffff');
        $heading_color = get_option('bc_heading_text_color', '#0f172a');
        $body_color = get_option('bc_body_text_color', '#64748b');
        $input_bg = get_option('bc_input_bg_color', '#ffffff');
        $input_border = get_option('bc_input_border_color', '#e2e8f0');

        // UI Effects Defaults
        $font_family = get_option('bc_font_family', 'Inter');
        $border_radius = get_option('bc_border_radius', 16);
        $shadow_intensity = get_option('bc_shadow_intensity', 'medium');

        // Content Defaults
        $l_step1 = get_option('bc_label_step1', '1. Select a Service');
        $l_step2 = get_option('bc_label_step2', '2. Choose a Professional');
        $l_step3 = get_option('bc_label_step3', '3. Select Date & Time');
        $l_step4 = get_option('bc_label_step4', '4. Your Details');
        $l_next = get_option('bc_label_next_btn', 'Next Step');
        $l_prev = get_option('bc_label_prev_btn', 'Back');

        // Basket & Icons
        $l_basket = get_option('bc_label_basket_btn', 'Services Selected');
        $i_basket = get_option('bc_icon_basket_btn', 'dashicons-cart');
        $i_view = get_option('bc_icon_view_details', 'dashicons-visibility');
        $m_basket_enable = get_option('bc_menu_basket_enable', 'no');
        $m_basket_text = get_option('bc_menu_basket_text', 'Selection');
        $m_basket_icon = get_option('bc_menu_basket_icon', 'dashicons-cart');
        $m_basket_pos = get_option('bc_menu_basket_pos', 'after');
        $basket_mode = get_option('bc_basket_mode', 'hover');

        $float_enable = get_option('bc_float_btn_enable', 'no');
        $float_pos = get_option('bc_float_btn_pos', 'bottom-right');
        $float_text = get_option('bc_float_btn_text', 'Book Now');
        $float_icon = get_option('bc_float_btn_icon', 'dashicons-calendar-alt');
        $showcase_layout = get_option('bc_showcase_layout', 'grid');
        ?>
        <div class="wrap bc-admin-wrap bc-design-wrapper">
            <style>
                /* Design & Branding Responsive Layouts */
                .bc-design-header { margin-bottom: 20px; }
                .bc-design-main-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start; }
                .bc-design-column-stack { display: flex; flex-direction: column; gap: 30px; }
                .bc-palette-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; }
                .bc-effect-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .bc-label-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .bc-basket-toggle-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 30px; }
                .bc-basket-detail-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; padding: 20px; background: rgba(0,0,0,0.1); border-radius: 16px; }
                .bc-float-assistant-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
                .bc-float-detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .bc-action-bar { background: var(--bc-panel-dark); padding: 20px; border-radius: 16px; border: 1px solid var(--bc-border); margin-top: 30px; display: flex; gap: 15px; }

                @media (max-width: 1200px) {
                    .bc-design-main-grid { grid-template-columns: 1fr; }
                }

                @media (max-width: 768px) {
                    .bc-effect-grid, .bc-label-grid, .bc-basket-toggle-grid, .bc-float-assistant-grid, .bc-float-detail-grid { grid-template-columns: 1fr; }
                    .bc-basket-detail-grid { grid-template-columns: 1fr; }
                    .bc-action-bar { flex-direction: column; }
                    .bc-action-bar button { width: 100%; }
                }
            </style>
            <h1 class="bc-design-header"><?php _e('System Customization & Branding', 'boocommerce'); ?></h1>
            <p style="color:var(--bc-text-muted); margin-bottom:30px;"><?php _e('Fully loaded control center for your premium booking ecosystem.', 'boocommerce'); ?></p>

            <link rel="stylesheet" href="<?php echo esc_url(BC_PLUGIN_URL . 'assets/all.min.css'); ?>"  />
            <style>
                .bc-switch { position: relative; display: inline-block; width: 44px; height: 22px; }
                .bc-switch input { opacity: 0; width: 0; height: 0; }
                .bc-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(255,255,255,0.05); transition: .4s; border-radius: 34px; border: 1px solid rgba(255,255,255,0.1); }
                .bc-slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 4px; bottom: 3px; background-color: #94a3b8; transition: .4s; border-radius: 50%; }
                input:checked + .bc-slider { background-color: var(--bc-brand, #6366f1); border-color: var(--bc-brand, #6366f1); }
                input:checked + .bc-slider:before { transform: translateX(22px); background-color: #fff; }
            </style>

            <form method="post">
                <?php wp_nonce_field('bc_save_design', 'bc_design_nonce'); ?>

                <div class="bc-design-main-grid">
                    
                    <!-- Left Column: Primary Settings -->
                    <div class="bc-design-column-stack">
                        
                        <!-- Section 1: Brand Identity & Palette -->
                        <div class="bc-design-section" style="margin:0; border-left: 4px solid var(--bc-primary);">
                            <h2 style="color:white; margin-bottom:20px; font-weight: 700; letter-spacing: -0.02em; display:flex; align-items:center; gap:10px;">
                                <span class="dashicons dashicons-art"></span> <?php _e('01. Brand Identity & Aesthetic Palette', 'boocommerce'); ?>
                            </h2>
                            <p style="color:var(--bc-text-muted); font-size:12px; margin-bottom:25px; line-height:1.6;"><?php _e('Fine-tune your visual identity. These colors define the primary mood, interactions, and granular elements of your booking system.', 'boocommerce'); ?></p>
                            
                            <div class="bc-palette-grid">
                                <!-- Primary Brand Group -->
                                <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <label style="color:rgba(255,255,255,0.5); font-size:11px; text-transform:uppercase; letter-spacing:0.05em; font-weight:700; display:block; margin-bottom:10px;"><?php _e('Core Identity', 'boocommerce'); ?></label>
                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;"><?php _e('Primary Color', 'boocommerce'); ?></span>
                                            <div class="bc-color-picker-wrapper" style="margin:0;">
                                                <div class="bc-color-swatch" style="background: <?php echo esc_attr($brand_color); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="bc_brand_color" value="<?php echo esc_attr($brand_color); ?>" class="bc-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;"><?php _e('Gradient Accent', 'boocommerce'); ?></span>
                                            <div class="bc-color-picker-wrapper" style="margin:0;">
                                                <div class="bc-color-swatch" style="background: <?php echo esc_attr($brand_color_end); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="bc_brand_color_end" value="<?php echo esc_attr($brand_color_end); ?>" class="bc-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;"><?php _e('Interactions', 'boocommerce'); ?></span>
                                            <div class="bc-color-picker-wrapper" style="margin:0;">
                                                <div class="bc-color-swatch" style="background: <?php echo esc_attr($accent_color); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="bc_accent_color" value="<?php echo esc_attr($accent_color); ?>" class="bc-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Surface & Text Group -->
                                <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <label style="color:rgba(255,255,255,0.5); font-size:11px; text-transform:uppercase; letter-spacing:0.05em; font-weight:700; display:block; margin-bottom:10px;"><?php _e('Surfaces & Text', 'boocommerce'); ?></label>
                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;"><?php _e('Card BG', 'boocommerce'); ?></span>
                                            <div class="bc-color-picker-wrapper" style="margin:0;">
                                                <div class="bc-color-swatch" style="background: <?php echo esc_attr($card_bg); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="bc_card_bg_color" value="<?php echo esc_attr($card_bg); ?>" class="bc-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;"><?php _e('Heading Text', 'boocommerce'); ?></span>
                                            <div class="bc-color-picker-wrapper" style="margin:0;">
                                                <div class="bc-color-swatch" style="background: <?php echo esc_attr($heading_color); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="bc_heading_text_color" value="<?php echo esc_attr($heading_color); ?>" class="bc-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;"><?php _e('Body Text', 'boocommerce'); ?></span>
                                            <div class="bc-color-picker-wrapper" style="margin:0;">
                                                <div class="bc-color-swatch" style="background: <?php echo esc_attr($body_color); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="bc_body_text_color" value="<?php echo esc_attr($body_color); ?>" class="bc-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Forms & Inputs Group -->
                                <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <label style="color:rgba(255,255,255,0.5); font-size:11px; text-transform:uppercase; letter-spacing:0.05em; font-weight:700; display:block; margin-bottom:10px;"><?php _e('Forms & Inputs', 'boocommerce'); ?></label>
                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;"><?php _e('Input BG', 'boocommerce'); ?></span>
                                            <div class="bc-color-picker-wrapper" style="margin:0;">
                                                <div class="bc-color-swatch" style="background: <?php echo esc_attr($input_bg); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="bc_input_bg_color" value="<?php echo esc_attr($input_bg); ?>" class="bc-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;"><?php _e('Input Border', 'boocommerce'); ?></span>
                                            <div class="bc-color-picker-wrapper" style="margin:0;">
                                                <div class="bc-color-swatch" style="background: <?php echo esc_attr($input_border); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="bc_input_border_color" value="<?php echo esc_attr($input_border); ?>" class="bc-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: UI Components & Effects -->
                        <div class="bc-design-section" style="margin:0;">
                            <h2 style="color:white; margin-bottom:25px; font-weight: 700; letter-spacing: -0.02em; display:flex; align-items:center; gap:10px;">
                                <span class="dashicons dashicons-admin-appearance"></span> <?php _e('02. Component Styling & Effects', 'boocommerce'); ?>
                            </h2>
                            
                            <div class="bc-effect-grid">
                                <div>
                                    <label style="display:block; color:white; font-weight:600; font-size:14px; margin-bottom:10px;"><?php _e('Typography Engine', 'boocommerce'); ?></label>
                                    <select name="bc_font_family" style="width:100%; background:rgba(255,255,255,0.05); color:white; border:1px solid rgba(255,255,255,0.1); padding:12px; border-radius:10px;">
                                        <optgroup label="Modern Sans-Serif">
                                            <option value="Inter" <?php selected($font_family, 'Inter'); ?>>Inter (Default - High Clarity)</option>
                                            <option value="Outfit" <?php selected($font_family, 'Outfit'); ?>>Outfit (Premium Luxury)</option>
                                            <option value="Poppins" <?php selected($font_family, 'Poppins'); ?>>Poppins (Friendly & Round)</option>
                                            <option value="Jost" <?php selected($font_family, 'Jost'); ?>>Jost (Modern Geometric)</option>
                                            <option value="Roboto" <?php selected($font_family, 'Roboto'); ?>>Roboto (Industrial Tech)</option>
                                            <option value="Open Sans" <?php selected($font_family, 'Open Sans'); ?>>Open Sans (Classic Versatile)</option>
                                        </optgroup>
                                        <optgroup label="Elegant Serif">
                                            <option value="Playfair Display" <?php selected($font_family, 'Playfair Display'); ?>>Playfair Display (High Contrast)</option>
                                            <option value="Lora" <?php selected($font_family, 'Lora'); ?>>Lora (Artistic & Elegant)</option>
                                            <option value="DM Serif Display" <?php selected($font_family, 'DM Serif Display'); ?>>DM Serif (Bold Editorial)</option>
                                        </optgroup>
                                        <optgroup label="Unique & Avant-Garde">
                                            <option value="Syne" <?php selected($font_family, 'Syne'); ?>>Syne (Creative Bold)</option>
                                            <option value="Space Grotesk" <?php selected($font_family, 'Space Grotesk'); ?>>Space Grotesk (Neo-Grotesque)</option>
                                            <option value="Montserrat" <?php selected($font_family, 'Montserrat'); ?>>Montserrat (Bold Urban)</option>
                                        </optgroup>
                                    </select>
                                </div>
                                
                                <div>
                                    <label style="display:block; color:white; font-weight:600; font-size:14px; margin-bottom:10px;"><?php _e('Shadow Depth', 'boocommerce'); ?></label>
                                    <select name="bc_shadow_intensity" style="width:100%; background:rgba(255,255,255,0.05); color:white; border:1px solid rgba(255,255,255,0.1); padding:12px; border-radius:10px;">
                                        <option value="none" <?php selected($shadow_intensity, 'none'); ?>>None (Flat Design)</option>
                                        <option value="low" <?php selected($shadow_intensity, 'low'); ?>>Subtle (Minimalist)</option>
                                        <option value="medium" <?php selected($shadow_intensity, 'medium'); ?>>Medium (Standard)</option>
                                        <option value="high" <?php selected($shadow_intensity, 'high'); ?>>High (Floating Cards)</option>
                                    </select>
                                </div>
                            </div>

                            <div style="margin-top:20px;">
                                <label style="display:flex; justify-content:space-between; color:white; font-weight:600; font-size:14px; margin-bottom:10px;">
                                    <span>Corner Roundness (Border Radius)</span>
                                    <span style="color:var(--bc-primary);"><?php echo $border_radius; ?>px</span>
                                </label>
                                <input type="range" name="bc_border_radius" min="0" max="40" value="<?php echo esc_attr($border_radius); ?>" style="width:100%;">
                                <div style="display:flex; justify-content:space-between; font-size:11px; color:rgba(255,255,255,0.3); margin-top:5px;">
                                    <span>Sharp (0px)</span>
                                    <span>Circle (40px)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Content & Labeling -->
                        <div class="bc-design-section" style="margin:0;">
                            <h2 style="color:white; margin-bottom:25px; font-weight: 700; letter-spacing: -0.02em; display:flex; align-items:center; gap:10px;">
                                <span class="dashicons dashicons-editor-textcolor"></span> <?php _e('03. Content & Dynamic Labeling', 'boocommerce'); ?>
                            </h2>
                            <div class="bc-label-grid">
                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;"><?php _e('Step 1 Title', 'boocommerce'); ?></label>
                                    <input type="text" name="bc_label_step1" value="<?php echo esc_attr($l_step1); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;"><?php _e('Step 2 Title', 'boocommerce'); ?></label>
                                    <input type="text" name="bc_label_step2" value="<?php echo esc_attr($l_step2); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;"><?php _e('Step 3 Title', 'boocommerce'); ?></label>
                                    <input type="text" name="bc_label_step3" value="<?php echo esc_attr($l_step3); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;"><?php _e('Step 4 Title', 'boocommerce'); ?></label>
                                    <input type="text" name="bc_label_step4" value="<?php echo esc_attr($l_step4); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;"><?php _e('Primary Action Button', 'boocommerce'); ?></label>
                                    <input type="text" name="bc_label_next_btn" value="<?php echo esc_attr($l_next); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;"><?php _e('Secondary Back Button', 'boocommerce'); ?></label>
                                    <input type="text" name="bc_label_prev_btn" value="<?php echo esc_attr($l_prev); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                            </div>
                        </div>

                        <!-- Section 4: Basket & Interaction Ecosystem -->
                        <div class="bc-design-section" style="margin:0; border-top: 4px solid #f59e0b; background: rgba(245, 158, 11, 0.02);">
                            <h2 style="color:white; margin-bottom:25px; font-weight: 800; display:flex; align-items:center; gap:12px; font-size:18px;">
                                <span class="dashicons dashicons-cart" style="color:#f59e0b; font-size:24px; width:24px; height:24px;"></span> <?php _e('04. Basket & Interaction Ecosystem', 'boocommerce'); ?>
                            </h2>
                            
                            <div class="bc-basket-toggle-grid">
                                <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.03); padding:20px; border-radius:14px; border:1px solid rgba(255,255,255,0.05);">
                                    <div>
                                        <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                            <?php _e('Show Basket in Menu', 'boocommerce'); ?>
                                            <span class="bc-info-icon" data-tooltip="<?php esc_attr_e('Integrates the service selection basket directly into your primary WordPress navigation menu.', 'boocommerce'); ?>">?</span>
                                        </label>
                                        <span style="color:var(--bc-text-muted); font-size:12px;"><?php _e('Integrate selection into main nav.', 'boocommerce'); ?></span>
                                    </div>
                                    <label class="bc-switch">
                                        <input type="checkbox" name="bc_menu_basket_enable" value="yes" <?php checked($m_basket_enable, 'yes'); ?>>
                                        <span class="bc-slider"></span>
                                    </label>
                                </div>

                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:8px; font-weight:600;"><?php _e('Interaction Mode', 'boocommerce'); ?></label>
                                    <select name="bc_basket_mode" style="width:100%; background:rgba(255,255,255,0.05); color:white; border:1px solid rgba(255,255,255,0.1); padding:12px; border-radius:10px;">
                                        <option value="hover" <?php selected($basket_mode, 'hover'); ?>>Open on Hover (Premium)</option>
                                        <option value="click" <?php selected($basket_mode, 'click'); ?>>Open on Click (Standard)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="bc-basket-detail-grid">
                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.5); font-size:11px; text-transform:uppercase; margin-bottom:8px; display:block;"><?php _e('Basket Icon', 'boocommerce'); ?></label>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" id="bc_input_basket" name="bc_icon_basket_btn" value="<?php echo esc_attr($i_basket); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                        <button type="button" class="bc-icon-picker-btn" data-target="bc_input_basket" style="background:rgba(255,255,255,0.1); border:none; color:white; border-radius:8px; padding:0 15px; cursor:pointer; font-weight:600;" title="Browse Library"><span class="dashicons dashicons-search" style="margin-top:4px;"></span></button>
                                    </div>
                                </div>
                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.5); font-size:11px; text-transform:uppercase; margin-bottom:8px; display:block;"><?php _e('Details Icon', 'boocommerce'); ?></label>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" id="bc_input_details" name="bc_icon_view_details" value="<?php echo esc_attr($i_view); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                        <button type="button" class="bc-icon-picker-btn" data-target="bc_input_details" style="background:rgba(255,255,255,0.1); border:none; color:white; border-radius:8px; padding:0 15px; cursor:pointer; font-weight:600;" title="Browse Library"><span class="dashicons dashicons-search" style="margin-top:4px;"></span></button>
                                    </div>
                                </div>
                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.5); font-size:11px; text-transform:uppercase; margin-bottom:8px; display:block;"><?php _e('Menu Label', 'boocommerce'); ?></label>
                                    <input type="text" name="bc_menu_basket_text" value="<?php echo esc_attr($m_basket_text); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                            </div>
                        </div>

                        <!-- Section 5: Floating Booking Widget -->
                        <div class="bc-design-section" style="margin:0; border-top: 4px solid var(--bc-success); background: rgba(16, 185, 129, 0.02);">
                            <h2 style="color:white; margin-bottom:25px; font-weight: 800; display:flex; align-items:center; gap:12px; font-size:18px;">
                                <span class="dashicons dashicons-calendar-alt" style="color:var(--bc-success); font-size:24px; width:24px; height:24px;"></span> <?php _e('05. Floating Booking Assistant', 'boocommerce'); ?>
                            </h2>

                            <div class="bc-float-assistant-grid">
                                <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.03); padding:20px; border-radius:14px; border:1px solid rgba(255,255,255,0.05);">
                                    <div>
                                        <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                            <?php _e('Display Floating Widget', 'boocommerce'); ?>
                                            <span class="bc-info-icon" data-tooltip="<?php esc_attr_e('Enables a persistent floating mini-basket button that follows users across your entire website.', 'boocommerce'); ?>">?</span>
                                        </label>
                                        <span style="color:var(--bc-text-muted); font-size:12px;"><?php _e('Persistent site-wide mini-basket.', 'boocommerce'); ?></span>
                                    </div>
                                    <label class="bc-switch">
                                        <input type="checkbox" name="bc_float_btn_enable" value="yes" <?php checked($float_enable, 'yes'); ?>>
                                        <span class="bc-slider"></span>
                                    </label>
                                </div>

                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:8px; font-weight:600;"><?php _e('Button Position', 'boocommerce'); ?></label>
                                    <select name="bc_float_btn_pos" style="width:100%; background:rgba(255,255,255,0.05); color:white; border:1px solid rgba(255,255,255,0.1); padding:12px; border-radius:10px;">
                                        <option value="bottom-right" <?php selected($float_pos, 'bottom-right'); ?>>Bottom Right Corner</option>
                                        <option value="bottom-left" <?php selected($float_pos, 'bottom-left'); ?>>Bottom Left Corner</option>
                                    </select>
                                </div>
                            </div>

                            <div class="bc-float-detail-grid">
                                <div class="bc-input-wrap">
                                    <label style="color:rgba(255,255,255,0.5); font-size:11px; text-transform:uppercase; margin-bottom:8px; display:block;"><?php _e('Floating Widget Icon', 'boocommerce'); ?></label>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" id="bc_input_float" name="bc_float_btn_icon" value="<?php echo esc_attr($float_icon); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                        <button type="button" class="bc-icon-picker-btn" data-target="bc_input_float" style="background:rgba(255,255,255,0.1); border:none; color:white; border-radius:8px; padding:0 15px; cursor:pointer; font-weight:600;" title="Browse Library"><span class="dashicons dashicons-search" style="margin-top:4px;"></span></button>
                                    </div>
                                </div>
                                <div style="background:rgba(16, 185, 129, 0.05); padding:15px; border-radius:12px; border:1px solid rgba(16, 185, 129, 0.1); display:flex; align-items:center; gap:12px;">
                                    <span class="dashicons dashicons-info" style="color:var(--bc-success);"></span>
                                    <span style="color:rgba(255,255,255,0.6); font-size:11px; line-height:1.4;"><?php _e('The floating widget acts as a mini-basket that expands on click, allowing users to manage their selection from any page.', 'boocommerce'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Save Actions -->
                        <div class="bc-action-bar">
                            <button type="submit" class="bc-btn-premium"
                                style="flex:2; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: #fff; border: none; padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content:center; gap: 10px;">
                                ✨ <?php _e('Apply Customization', 'boocommerce'); ?>
                            </button>
                            <button type="button" id="bc-reset-trigger-btn"
                                style="flex:1; background: rgba(255,255,255,0.05); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 15px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content:center; gap: 8px;">
                                <span class="dashicons dashicons-undo" style="font-size:16px; width:16px; height:16px;"></span> <?php _e('Restore Defaults', 'boocommerce'); ?>
                            </button>
                        </div>

                    </div>

                    <!-- Right Column: Layout & Deployment -->
                    <div class="bc-design-column-stack">
                        
                        <!-- Section 4: Aesthetic Style Selection -->
                        <div class="bc-design-section" style="margin:0; border-top: 4px solid var(--bc-primary);">
                            <h2 style="color:white; margin-bottom:15px; display:flex; align-items:center; gap:10px; font-size:16px;">
                                <span class="dashicons dashicons-layout"></span> <?php _e('04. Service Booking Page Style', 'boocommerce'); ?>
                            </h2>
                            <p style="color:var(--bc-text-muted); font-size:12px; margin-bottom:20px; line-height:1.5;"><?php _e('Select the core design language for your frontend booking experience.', 'boocommerce'); ?></p>
                            
                            <div style="display:grid; grid-template-columns: 1fr; gap:15px;">
                                <?php
                                $base_layouts = [
                                    'modern_grid' => [
                                        'name' => 'Signature Grid',
                                        'desc' => 'Clean, balanced, and high-performance.',
                                        'icon' => 'dashicons-grid-view',
                                        'gradient' => 'linear-gradient(135deg, #1e293b 0%, #0f172a 100%)'
                                    ],
                                    'glass_cards_v2' => [
                                        'name' => 'Glass Elite',
                                        'desc' => 'Ultra-modern frosted glass aesthetic.',
                                        'icon' => 'dashicons-admin-appearance',
                                        'gradient' => 'linear-gradient(135deg, #6366f1 0%, #a855f7 100%)'
                                    ],
                                    'metro_grid' => [
                                        'name' => 'Immersive Metro',
                                        'desc' => 'Bold imagery and spacious typography.',
                                        'icon' => 'dashicons-format-image',
                                        'gradient' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)'
                                    ],
                                    'neon_night' => [
                                        'name' => 'Cyber Dark',
                                        'desc' => 'High-contrast glowing dark mode.',
                                        'icon' => 'dashicons-visibility',
                                        'gradient' => 'linear-gradient(135deg, #06b6d4 0%, #0891b2 100%)'
                                    ]
                                ];

                                // Allow external plugins (like Aura Luxe) to add layouts
                                $extra_layout_names = apply_filters('bc_admin_design_layouts', []);
                                
                                $layouts = $base_layouts;
                                foreach ($extra_layout_names as $extra_val => $extra_name) {
                                    if (!isset($layouts[$extra_val])) {
                                        $layouts[$extra_val] = [
                                            'name' => $extra_name,
                                            'desc' => 'Premium layout extension.',
                                            'icon' => 'dashicons-star-filled',
                                            'gradient' => 'linear-gradient(135deg, #f472b6 0%, #db2777 100%)'
                                        ];
                                    }
                                }
                                
                                foreach ($layouts as $val => $data): 
                                    $is_active = ($service_layout === $val);
                                ?>
                                    <label style="display:block; cursor:pointer; position:relative;" class="bc-layout-card-label">
                                        <input type="radio" name="bc_service_layout" value="<?php echo $val; ?>" <?php checked($service_layout, $val); ?> style="display:none;" onchange="updateWsbLayoutSelection(this)">
                                        <div class="bc-layout-visual-card" style="display:flex; align-items:center; gap:15px; padding:15px; background:<?php echo $is_active ? 'rgba(99,102,241,0.1)' : 'rgba(255,255,255,0.02)'; ?>; border:1.5px solid <?php echo $is_active ? 'var(--bc-primary)' : 'rgba(255,255,255,0.05)'; ?>; border-radius:12px; transition:all 0.3s ease;">
                                            <div style="width:45px; height:45px; border-radius:10px; background:<?php echo $data['gradient']; ?>; display:flex; align-items:center; justify-content:center; color:white;">
                                                <span class="dashicons <?php echo $data['icon']; ?>"></span>
                                            </div>
                                            <div style="flex:1;">
                                                <div style="color:white; font-weight:700; font-size:14px; margin-bottom:2px;"><?php echo $data['name']; ?></div>
                                                <div style="color:rgba(255,255,255,0.4); font-size:11px;"><?php echo $data['desc']; ?></div>
                                            </div>
                                            <div class="bc-layout-check" style="width:20px; height:20px; background:var(--bc-primary); color:white; border-radius:50%; display:<?php echo $is_active ? 'flex' : 'none'; ?>; align-items:center; justify-content:center; font-size:10px;">✓</div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <script>
                        function updateWsbLayoutSelection(input) {
                            // Reset all cards
                            document.querySelectorAll('.bc-layout-visual-card').forEach(card => {
                                card.style.background = 'rgba(255,255,255,0.02)';
                                card.style.borderColor = 'rgba(255,255,255,0.05)';
                                card.querySelector('.bc-layout-check').style.display = 'none';
                            });
                            
                            // Highlight selected card
                            const selectedCard = input.nextElementSibling;
                            selectedCard.style.background = 'rgba(99,102,241,0.1)';
                            selectedCard.style.borderColor = 'var(--bc-primary)';
                            selectedCard.querySelector('.bc-layout-check').style.display = 'flex';
                        }
                        </script>

                        <!-- Section 5: Deployment -->
                        <div style="background:var(--bc-panel-dark); border-radius:16px; border:1px solid var(--bc-border); overflow:hidden; border-top:4px solid var(--bc-success);">
                            <div style="padding:20px; border-bottom:1px solid var(--bc-border);">
                                <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px; font-size:15px;">
                                    <span class="dashicons dashicons-shortcode" style="color:var(--bc-success);"></span> <?php _e('05. Frontend Deployment', 'boocommerce'); ?>
                                </h3>
                            </div>
                            <div style="padding:20px;">
                                <div style="background:rgba(16, 185, 129, 0.05); border:1px dashed var(--bc-success); padding:10px; border-radius:10px; text-align:center; margin-bottom:15px;">
                                    <code style="font-size:16px; color:var(--bc-success); font-weight:900;">[bc_booking_widget]</code>
                                </div>
                                <input type="text" readonly value="<?php echo site_url('/booking'); ?>" onclick="this.select();"
                                    style="width:100%; background:#0f172a; color:var(--bc-primary); border:1px solid var(--bc-border); padding:8px; border-radius:8px; font-size:11px;">
                            </div>
                        </div>

                        <!-- Section 06: Service Display Showcase -->
                        <div class="bc-design-section" style="margin:0; border-left: 4px solid #0ea5e9;">
                            <h2 style="color:white; margin-bottom:10px; font-weight: 700; letter-spacing: -0.02em; display:flex; align-items:center; gap:10px;">
                                <span class="dashicons dashicons-layout" style="color:#0ea5e9;"></span> <?php _e('06. Service Display Showcase', 'boocommerce'); ?>
                            </h2>
                            <p style="color:var(--bc-text-muted); font-size:12px; margin-bottom:25px; line-height:1.6;"><?php _e('Embed your services anywhere on your site using these advanced shortcodes. Clients can view service details and jump directly into the booking flow.', 'boocommerce'); ?></p>

                            <!-- Shortcode Guide -->
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:35px;">
                                <div style="background:rgba(255,255,255,0.03); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <label style="display:block; color:rgba(255,255,255,0.5); font-size:10px; text-transform:uppercase; margin-bottom:8px; font-weight:700;">01. Show All Services</label>
                                    <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(14, 165, 233, 0.1); padding:4px 8px; border-radius:6px;">
                                        <code style="background:none; color:#0ea5e9; padding:0; font-size:13px; font-weight:700;">[bc_services]</code>
                                        <span class="dashicons dashicons-admin-page" style="color:#0ea5e9; cursor:pointer; font-size:14px; width:14px; height:14px; transition:0.3s;" onclick="navigator.clipboard.writeText('[bc_services]'); this.style.color='#10b981'; setTimeout(() => this.style.color='#0ea5e9', 1000);" title="Copy"></span>
                                    </div>
                                </div>
                                
                                <div style="background:rgba(255,255,255,0.03); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <label style="display:block; color:rgba(255,255,255,0.5); font-size:10px; text-transform:uppercase; margin-bottom:8px; font-weight:700;">02. Override Layout</label>
                                    <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(14, 165, 233, 0.1); padding:4px 8px; border-radius:6px;">
                                        <code style="background:none; color:#0ea5e9; padding:0; font-size:13px; font-weight:700;">[bc_services layout="carousel"]</code>
                                        <span class="dashicons dashicons-admin-page" style="color:#0ea5e9; cursor:pointer; font-size:14px; width:14px; height:14px; transition:0.3s;" onclick="navigator.clipboard.writeText('[bc_services layout=&quot;carousel&quot;]'); this.style.color='#10b981'; setTimeout(() => this.style.color='#0ea5e9', 1000);" title="Copy"></span>
                                    </div>
                                </div>

                                <div style="background:rgba(255,255,255,0.03); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <label style="display:block; color:rgba(255,255,255,0.5); font-size:10px; text-transform:uppercase; margin-bottom:8px; font-weight:700;">03. Specific IDs</label>
                                    <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(14, 165, 233, 0.1); padding:4px 8px; border-radius:6px;">
                                        <code style="background:none; color:#0ea5e9; padding:0; font-size:13px; font-weight:700;">[bc_services ids="1,5,8"]</code>
                                        <span class="dashicons dashicons-admin-page" style="color:#0ea5e9; cursor:pointer; font-size:14px; width:14px; height:14px; transition:0.3s;" onclick="navigator.clipboard.writeText('[bc_services ids=&quot;1,5,8&quot;]'); this.style.color='#10b981'; setTimeout(() => this.style.color='#0ea5e9', 1000);" title="Copy"></span>
                                    </div>
                                </div>

                                <div style="background:rgba(255,255,255,0.03); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <label style="display:block; color:rgba(255,255,255,0.5); font-size:10px; text-transform:uppercase; margin-bottom:8px; font-weight:700;">04. Category + Grid</label>
                                    <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(14, 165, 233, 0.1); padding:4px 8px; border-radius:6px;">
                                        <code style="background:none; color:#0ea5e9; padding:0; font-size:13px; font-weight:700;">[bc_services category="Hair" layout="grid"]</code>
                                        <span class="dashicons dashicons-admin-page" style="color:#0ea5e9; cursor:pointer; font-size:14px; width:14px; height:14px; transition:0.3s;" onclick="navigator.clipboard.writeText('[bc_services category=&quot;Hair&quot; layout=&quot;grid&quot;]'); this.style.color='#10b981'; setTimeout(() => this.style.color='#0ea5e9', 1000);" title="Copy"></span>
                                    </div>
                                </div>
                            </div>

                            <div style="padding:15px; background:rgba(14, 165, 233, 0.05); border-radius:12px; border:1px solid rgba(14, 165, 233, 0.1); margin-bottom:15px;">
                                <div style="display:flex; align-items:center; gap:8px; color:#0ea5e9; font-weight:700; font-size:12px; margin-bottom:5px;">
                                    <span class="dashicons dashicons-info" style="font-size:16px; width:16px; height:16px;"></span> <?php _e('Shortcode Power', 'boocommerce'); ?>
                                </div>
                                <p style="color:var(--bc-text-muted); font-size:11px; margin:0; line-height:1.5;"><?php _e('You can combine your own styles by using the <code>layout="grid"</code> or <code>layout="carousel"</code> attribute in the shortcode.', 'boocommerce'); ?></p>
                            </div>
                        </div>


                    </div>

                </div>
            </form>
        </div>

        <!-- Reset Confirmation Modal -->
        <div id="bc-reset-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(8px); z-index:99999; align-items:center; justify-content:center; padding:20px;">
            <div style="background:#1e293b; border:1px solid rgba(255,255,255,0.1); border-radius:24px; max-width:450px; width:100%; padding:40px; text-align:center; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
                <div style="width:80px; height:80px; background:rgba(239, 68, 68, 0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 25px;">
                    <span class="dashicons dashicons-undo" style="color:#ef4444; font-size:40px; width:40px; height:40px;"></span>
                </div>
                <h2 style="color:white; margin-bottom:15px; font-size:24px; font-weight:800;"><?php _e('Restore Factory Defaults?', 'boocommerce'); ?></h2>
                <p style="color:rgba(255,255,255,0.6); line-height:1.6; margin-bottom:35px; font-size:14px;"><?php _e('This action will permanently remove all your custom branding, color palettes, and typography settings. This cannot be undone.', 'boocommerce'); ?></p>
                
                <div style="display:flex; gap:15px;">
                    <button type="button" onclick="document.getElementById('bc-reset-modal').style.display='none'" 
                        style="flex:1; background:rgba(255,255,255,0.05); color:white; border:1px solid rgba(255,255,255,0.1); padding:14px; border-radius:12px; font-weight:600; cursor:pointer;">
                        <?php _e('Cancel', 'boocommerce'); ?>
                    </button>
                    <button type="button" onclick="document.getElementById('bc-actual-reset-trigger').click();" 
                        style="flex:1; background:#ef4444; color:white; border:none; padding:14px; border-radius:12px; font-weight:700; cursor:pointer; box-shadow:0 10px 15px -3px rgba(239, 68, 68, 0.3);">
                        <?php _e('Confirm Reset', 'boocommerce'); ?>
                    </button>
                </div>
            </div>
        </div>

        <form id="bc-reset-hidden-form" method="post" style="display:none;">
            <?php wp_nonce_field('bc_save_design', 'bc_design_nonce'); ?>
            <input type="hidden" name="bc_reset_design" value="1">
            <button type="submit" id="bc-actual-reset-trigger"></button>
        </form>

        <!-- Icon Library Modal -->
        <div id="bc-icon-library-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(8px); z-index:99999; align-items:center; justify-content:center; padding:20px;">
            <div style="background:#1e293b; border:1px solid rgba(255,255,255,0.1); border-radius:16px; width:100%; max-width:600px; padding:30px; display:flex; flex-direction:column; max-height:80vh;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:15px;">
                    <h2 style="color:white; margin:0; font-size:20px; font-weight:700;"><?php _e('Select an Icon', 'boocommerce'); ?></h2>
                    <span style="color:rgba(255,255,255,0.5); cursor:pointer; font-size:24px;" onclick="document.getElementById('bc-icon-library-modal').style.display='none'">&times;</span>
                </div>
                
                <div style="margin-bottom:20px;">
                    <input type="text" id="bc-icon-search" placeholder="<?php esc_attr_e('Search icons... (e.g., \'cart\', \'calendar\')', 'boocommerce'); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px; outline:none;">
                </div>

                <div id="bc-icon-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap:15px; overflow-y:auto; flex:1; padding-right:10px; max-height:400px;">
                    <!-- Icons injected via JS -->
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // 1. Initialize global cache if not present
            if (typeof window.wsbIconsCache === 'undefined') {
                window.wsbIconsCache = [
                    // Dashicons
                    'dashicons-cart', 'dashicons-visibility', 'dashicons-calendar-alt', 'dashicons-admin-users', 'dashicons-store',
                    'dashicons-heart', 'dashicons-star-filled', 'dashicons-clock', 'dashicons-location', 'dashicons-phone',
                    'dashicons-camera', 'dashicons-video-alt3', 'dashicons-money-alt', 'dashicons-smiley', 'dashicons-awards',
                    'dashicons-email', 'dashicons-yes', 'dashicons-no', 'dashicons-info', 'dashicons-lightbulb'
                ];

                // Fetch FA 7 icons exactly once
                fetch('<?php echo esc_url(BC_PLUGIN_URL . 'assets/all.min.css'); ?>')
                    .then(response => response.text())
                    .then(css => {
                        const iconSet = new Set();
                        const brandIndex = css.indexOf('Font Awesome 7 Brands');
                        const blockRegex = /((?:\.fa-[a-zA-Z0-9-]+\s*,\s*)*\.fa-[a-zA-Z0-9-]+)\s*\{[^}]*--fa:/g;
                        let match;
                        
                        while ((match = blockRegex.exec(css)) !== null) {
                            let selectors = match[1];
                            let currentPos = match.index;
                            
                            let isBrand = brandIndex !== -1 && currentPos > brandIndex;
                            let prefix = isBrand ? 'fa-brands' : 'fa-solid';
                            
                            let classMatches = selectors.match(/\.fa-([a-zA-Z0-9-]+)/g);
                            if (classMatches) {
                                classMatches.forEach(cls => {
                                    let name = cls.substring(1);
                                    if (!name.match(/^fa-(solid|regular|brands|light|thin|duotone|beat|bounce|fade|flip|shake|spin|fw|ul|li|border|pull|stack|inverse)$/)) {
                                        iconSet.add(prefix + ' ' + name);
                                    }
                                });
                            }
                        }
                        
                        window.wsbIconsCache = window.wsbIconsCache.concat(Array.from(iconSet));
                        
                        // Render immediately if modal is currently open
                        if ($('#bc-icon-library-modal').is(':visible')) {
                            $('#bc-icon-search').trigger('input');
                        }
                    })
                    .catch(err => console.log('FA Fetch Error', err));
            }

            // 2. Define universal render function
            window.wsbRenderIcons = function(filter = '') {
                const grid = $('#bc-icon-grid');
                if (!grid.length) return;

                grid.empty();
                
                const filterLower = filter.toLowerCase();
                const matchedIcons = window.wsbIconsCache.filter(icon => icon.toLowerCase().includes(filterLower));
                
                // For performance, cap rendering if no filter to prevent DOM freezing
                const renderLimit = filterLower === '' ? 200 : matchedIcons.length;
                
                for (let i = 0; i < Math.min(matchedIcons.length, renderLimit); i++) {
                    const icon = matchedIcons[i];
                    const div = $('<div></div>')
                        .attr('title', icon)
                        .css({
                            'background': 'rgba(255,255,255,0.05)',
                            'border': '1px solid rgba(255,255,255,0.1)',
                            'border-radius': '12px',
                            'height': '60px',
                            'display': 'flex',
                            'align-items': 'center',
                            'justify-content': 'center',
                            'cursor': 'pointer',
                            'font-size': '24px',
                            'color': 'white',
                            'transition': 'all 0.2s'
                        });

                    const iconEl = $('<i></i>');
                    if (icon.startsWith('dashicons-')) {
                        iconEl.addClass('dashicons ' + icon).css({ width: '24px', height: '24px', fontSize: '24px' });
                    } else {
                        iconEl.addClass(icon);
                    }
                    div.append(iconEl);

                    div.hover(
                        function() { $(this).css({ 'background': 'rgba(99,102,241,0.2)', 'transform': 'scale(1.05)' }); },
                        function() { $(this).css({ 'background': 'rgba(255,255,255,0.05)', 'transform': 'scale(1)' }); }
                    );

                    div.on('click', function() {
                        if (window.wsbCurrentTargetInput) {
                            $(window.wsbCurrentTargetInput).val(icon);
                        }
                        $('#bc-icon-library-modal').hide();
                    });

                    grid.append(div);
                }
            };

            // 3. Bind robust delegated events (using off.on to prevent duplicates)
            $(document).off('click.wsbIconPicker').on('click.wsbIconPicker', '.bc-icon-picker-btn', function(e) {
                e.preventDefault();
                const targetId = $(this).attr('data-target');
                window.wsbCurrentTargetInput = document.getElementById(targetId);
                
                $('#bc-icon-search').val('');
                window.wsbRenderIcons();
                $('#bc-icon-library-modal').css('display', 'flex');
            });

            $(document).off('input.wsbIconSearch').on('input.wsbIconSearch', '#bc-icon-search', function(e) {
                window.wsbRenderIcons($(this).val());
            });

            $(document).off('click.wsbIconClose').on('click.wsbIconClose', '#bc-icon-library-modal', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });
        });
        </script>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const resetBtn = document.getElementById('bc-reset-trigger-btn');
            const modal = document.getElementById('bc-reset-modal');
            
            if (resetBtn) {
                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    modal.style.display = 'flex';
                });
            }
        });
        </script>
        <?php
    }
}
