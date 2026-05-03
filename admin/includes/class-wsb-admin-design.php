<?php
class Wsb_Admin_Design {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wsb_design_nonce']) && wp_verify_nonce($_POST['wsb_design_nonce'], 'wsb_save_design')) {
            if (isset($_POST['wsb_reset_design'])) {
                delete_option('wsb_service_layout');
                delete_option('wsb_brand_color');
                delete_option('wsb_brand_color_end');
                delete_option('wsb_accent_color');
                delete_option('wsb_virtual_bg_color');
                delete_option('wsb_font_family');
                delete_option('wsb_border_radius');
                delete_option('wsb_shadow_intensity');
                delete_option('wsb_label_step1');
                delete_option('wsb_label_step2');
                delete_option('wsb_label_step3');
                delete_option('wsb_label_step4');
                delete_option('wsb_label_next_btn');
                delete_option('wsb_label_prev_btn');
                delete_option('wsb_card_bg_color');
                delete_option('wsb_heading_text_color');
                delete_option('wsb_body_text_color');
                delete_option('wsb_input_bg_color');
                delete_option('wsb_input_border_color');
                echo '<div class="notice notice-info is-dismissible"><p>Factory default settings restored successfully!</p></div>';
            } else {
                // Layout & Colors
                update_option('wsb_service_layout', sanitize_text_field($_POST['wsb_service_layout']));
                update_option('wsb_brand_color', sanitize_hex_color($_POST['wsb_brand_color']));
                update_option('wsb_brand_color_end', sanitize_hex_color($_POST['wsb_brand_color_end']));
                update_option('wsb_accent_color', sanitize_hex_color($_POST['wsb_accent_color']));
                update_option('wsb_virtual_bg_color', sanitize_hex_color($_POST['wsb_virtual_bg_color']));
                
                // UI Effects
                update_option('wsb_font_family', sanitize_text_field($_POST['wsb_font_family']));
                update_option('wsb_border_radius', intval($_POST['wsb_border_radius']));
                update_option('wsb_shadow_intensity', sanitize_text_field($_POST['wsb_shadow_intensity']));
                
                // Content & Labels
                update_option('wsb_label_step1', sanitize_text_field($_POST['wsb_label_step1']));
                update_option('wsb_label_step2', sanitize_text_field($_POST['wsb_label_step2']));
                update_option('wsb_label_step3', sanitize_text_field($_POST['wsb_label_step3']));
                update_option('wsb_label_step4', sanitize_text_field($_POST['wsb_label_step4']));
                update_option('wsb_label_next_btn', sanitize_text_field($_POST['wsb_label_next_btn']));
                update_option('wsb_label_prev_btn', sanitize_text_field($_POST['wsb_label_prev_btn']));

                // Detailed Element Styling
                update_option('wsb_card_bg_color', sanitize_hex_color($_POST['wsb_card_bg_color']));
                update_option('wsb_heading_text_color', sanitize_hex_color($_POST['wsb_heading_text_color']));
                update_option('wsb_body_text_color', sanitize_hex_color($_POST['wsb_body_text_color']));
                update_option('wsb_input_bg_color', sanitize_hex_color($_POST['wsb_input_bg_color']));
                update_option('wsb_input_border_color', sanitize_hex_color($_POST['wsb_input_border_color']));

                echo '<div class="notice notice-success is-dismissible"><p>Advanced customization settings applied successfully!</p></div>';
            }
        }

        $service_layout = get_option('wsb_service_layout', 'modern_grid');
        $brand_color = get_option('wsb_brand_color', '#baa7dd');
        $brand_color_end = get_option('wsb_brand_color_end', '#70ffbc');
        $accent_color = get_option('wsb_accent_color', '#d2b2ad');
        $virtual_bg_color = get_option('wsb_virtual_bg_color', '#f8fafc');

        // Detailed Styling Defaults
        $card_bg = get_option('wsb_card_bg_color', '#ffffff');
        $heading_color = get_option('wsb_heading_text_color', '#ff0000');
        $body_color = get_option('wsb_body_text_color', '#1572f4');
        $input_bg = get_option('wsb_input_bg_color', '#ffffff');
        $input_border = get_option('wsb_input_border_color', '#e2e8f0');

        // UI Effects Defaults
        $font_family = get_option('wsb_font_family', 'Inter');
        $border_radius = get_option('wsb_border_radius', 16);
        $shadow_intensity = get_option('wsb_shadow_intensity', 'medium');

        // Content Defaults
        $l_step1 = get_option('wsb_label_step1', '1. Select a Service');
        $l_step2 = get_option('wsb_label_step2', '2. Choose a Professional');
        $l_step3 = get_option('wsb_label_step3', '3. Select Date & Time');
        $l_step4 = get_option('wsb_label_step4', '4. Your Details');
        $l_next = get_option('wsb_label_next_btn', 'Next Step');
        $l_prev = get_option('wsb_label_prev_btn', 'Back');
        ?>
        <div class="wrap wsb-admin-wrap">
            <h1 style="margin-bottom:20px;">System Customization & Branding</h1>
            <p style="color:var(--wsb-text-muted); margin-bottom:30px;">Fully loaded control center for your premium booking ecosystem.</p>

            <form method="post">
                <?php wp_nonce_field('wsb_save_design', 'wsb_design_nonce'); ?>

                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px; align-items:start;">
                    
                    <!-- Left Column: Primary Settings -->
                    <div style="display:flex; flex-direction:column; gap:30px;">
                        
                        <!-- Section 1: Brand Identity & Palette -->
                        <div class="wsb-design-section" style="margin:0; border-left: 4px solid var(--wsb-primary);">
                            <h2 style="color:white; margin-bottom:20px; font-weight: 700; letter-spacing: -0.02em; display:flex; align-items:center; gap:10px;">
                                <span class="dashicons dashicons-art"></span> Brand Identity & Aesthetic Palette
                            </h2>
                            <p style="color:var(--wsb-text-muted); font-size:12px; margin-bottom:25px; line-height:1.6;">Fine-tune your visual identity. These colors define the primary mood, interactions, and granular elements of your booking system.</p>
                            
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:20px;">
                                <!-- Primary Brand Group -->
                                <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <label style="color:rgba(255,255,255,0.5); font-size:11px; text-transform:uppercase; letter-spacing:0.05em; font-weight:700; display:block; margin-bottom:10px;">Core Identity</label>
                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;">Primary Color</span>
                                            <div class="wsb-color-picker-wrapper" style="margin:0;">
                                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($brand_color); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="wsb_brand_color" value="<?php echo esc_attr($brand_color); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;">Gradient Accent</span>
                                            <div class="wsb-color-picker-wrapper" style="margin:0;">
                                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($brand_color_end); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="wsb_brand_color_end" value="<?php echo esc_attr($brand_color_end); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;">Interactions</span>
                                            <div class="wsb-color-picker-wrapper" style="margin:0;">
                                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($accent_color); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="wsb_accent_color" value="<?php echo esc_attr($accent_color); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Surface & Text Group -->
                                <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <label style="color:rgba(255,255,255,0.5); font-size:11px; text-transform:uppercase; letter-spacing:0.05em; font-weight:700; display:block; margin-bottom:10px;">Surfaces & Text</label>
                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;">Card BG</span>
                                            <div class="wsb-color-picker-wrapper" style="margin:0;">
                                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($card_bg); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="wsb_card_bg_color" value="<?php echo esc_attr($card_bg); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;">Heading Text</span>
                                            <div class="wsb-color-picker-wrapper" style="margin:0;">
                                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($heading_color); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="wsb_heading_text_color" value="<?php echo esc_attr($heading_color); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;">Body Text</span>
                                            <div class="wsb-color-picker-wrapper" style="margin:0;">
                                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($body_color); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="wsb_body_text_color" value="<?php echo esc_attr($body_color); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Forms & Inputs Group -->
                                <div style="background:rgba(255,255,255,0.02); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                    <label style="color:rgba(255,255,255,0.5); font-size:11px; text-transform:uppercase; letter-spacing:0.05em; font-weight:700; display:block; margin-bottom:10px;">Forms & Inputs</label>
                                    <div style="display:flex; flex-direction:column; gap:12px;">
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;">Input BG</span>
                                            <div class="wsb-color-picker-wrapper" style="margin:0;">
                                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($input_bg); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="wsb_input_bg_color" value="<?php echo esc_attr($input_bg); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                        <label style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;">
                                            <span style="color:white; font-size:13px;">Input Border</span>
                                            <div class="wsb-color-picker-wrapper" style="margin:0;">
                                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($input_border); ?>; width:28px; height:28px;"></div>
                                                <input type="color" name="wsb_input_border_color" value="<?php echo esc_attr($input_border); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.style.background = this.value;">
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: UI Components & Effects -->
                        <div class="wsb-design-section" style="margin:0;">
                            <h2 style="color:white; margin-bottom:25px; font-weight: 700; letter-spacing: -0.02em; display:flex; align-items:center; gap:10px;">
                                <span class="dashicons dashicons-admin-appearance"></span> Component Styling & Effects
                            </h2>
                            
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                <div>
                                    <label style="display:block; color:white; font-weight:600; font-size:14px; margin-bottom:10px;">Typography Engine</label>
                                    <select name="wsb_font_family" style="width:100%; background:rgba(255,255,255,0.05); color:white; border:1px solid rgba(255,255,255,0.1); padding:12px; border-radius:10px;">
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
                                    <label style="display:block; color:white; font-weight:600; font-size:14px; margin-bottom:10px;">Shadow Depth</label>
                                    <select name="wsb_shadow_intensity" style="width:100%; background:rgba(255,255,255,0.05); color:white; border:1px solid rgba(255,255,255,0.1); padding:12px; border-radius:10px;">
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
                                    <span style="color:var(--wsb-primary);"><?php echo $border_radius; ?>px</span>
                                </label>
                                <input type="range" name="wsb_border_radius" min="0" max="40" value="<?php echo esc_attr($border_radius); ?>" style="width:100%;">
                                <div style="display:flex; justify-content:space-between; font-size:11px; color:rgba(255,255,255,0.3); margin-top:5px;">
                                    <span>Sharp (0px)</span>
                                    <span>Circle (40px)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Section 4: Content & Labeling -->
                        <div class="wsb-design-section" style="margin:0;">
                            <h2 style="color:white; margin-bottom:25px; font-weight: 700; letter-spacing: -0.02em; display:flex; align-items:center; gap:10px;">
                                <span class="dashicons dashicons-editor-textcolor"></span> Content & Dynamic Labeling
                            </h2>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                                <div class="wsb-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;">Step 1 Title</label>
                                    <input type="text" name="wsb_label_step1" value="<?php echo esc_attr($l_step1); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                                <div class="wsb-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;">Step 2 Title</label>
                                    <input type="text" name="wsb_label_step2" value="<?php echo esc_attr($l_step2); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                                <div class="wsb-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;">Step 3 Title</label>
                                    <input type="text" name="wsb_label_step3" value="<?php echo esc_attr($l_step3); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                                <div class="wsb-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;">Step 4 Title</label>
                                    <input type="text" name="wsb_label_step4" value="<?php echo esc_attr($l_step4); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                                <div class="wsb-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;">Primary Action Button</label>
                                    <input type="text" name="wsb_label_next_btn" value="<?php echo esc_attr($l_next); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                                <div class="wsb-input-wrap">
                                    <label style="color:rgba(255,255,255,0.6); font-size:12px; display:block; margin-bottom:5px;">Secondary Back Button</label>
                                    <input type="text" name="wsb_label_prev_btn" value="<?php echo esc_attr($l_prev); ?>" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:white; padding:12px; border-radius:8px;">
                                </div>
                            </div>
                        </div>

                        <!-- Save Actions -->
                        <div style="background:var(--wsb-panel-dark); padding:20px; border-radius:16px; border:1px solid var(--wsb-border); margin-top:30px; display:flex; gap:15px;">
                            <button type="submit" class="wsb-btn-premium"
                                style="flex:2; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: #fff; border: none; padding: 15px; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content:center; gap: 10px;">
                                ✨ Apply Customization
                            </button>
                            <button type="button" id="wsb-reset-trigger-btn"
                                style="flex:1; background: rgba(255,255,255,0.05); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 15px; border-radius: 12px; font-size: 14px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content:center; gap: 8px;">
                                <span class="dashicons dashicons-undo" style="font-size:16px; width:16px; height:16px;"></span> Restore Defaults
                            </button>
                        </div>

                    </div>

                    <!-- Right Column: Layout & Deployment -->
                    <div style="display:flex; flex-direction:column; gap:30px;">
                        
                        <!-- Section 4: Aesthetic Style Selection -->
                        <div class="wsb-design-section" style="margin:0; border-top: 4px solid var(--wsb-primary);">
                            <h2 style="color:white; margin-bottom:15px; display:flex; align-items:center; gap:10px; font-size:16px;">
                                <span class="dashicons dashicons-layout"></span> Service Booking Page Style
                            </h2>
                            <p style="color:var(--wsb-text-muted); font-size:12px; margin-bottom:20px; line-height:1.5;">Select the core design language for your frontend booking experience.</p>
                            
                            <div style="display:grid; grid-template-columns: 1fr; gap:15px;">
                                <?php
                                $layouts = [
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
                                
                                foreach ($layouts as $val => $data): 
                                    $is_active = ($service_layout === $val);
                                ?>
                                    <label style="display:block; cursor:pointer; position:relative;" class="wsb-layout-card-label">
                                        <input type="radio" name="wsb_service_layout" value="<?php echo $val; ?>" <?php checked($service_layout, $val); ?> style="display:none;" onchange="updateWsbLayoutSelection(this)">
                                        <div class="wsb-layout-visual-card" style="display:flex; align-items:center; gap:15px; padding:15px; background:<?php echo $is_active ? 'rgba(99,102,241,0.1)' : 'rgba(255,255,255,0.02)'; ?>; border:1.5px solid <?php echo $is_active ? 'var(--wsb-primary)' : 'rgba(255,255,255,0.05)'; ?>; border-radius:12px; transition:all 0.3s ease;">
                                            <div style="width:45px; height:45px; border-radius:10px; background:<?php echo $data['gradient']; ?>; display:flex; align-items:center; justify-content:center; color:white;">
                                                <span class="dashicons <?php echo $data['icon']; ?>"></span>
                                            </div>
                                            <div style="flex:1;">
                                                <div style="color:white; font-weight:700; font-size:14px; margin-bottom:2px;"><?php echo $data['name']; ?></div>
                                                <div style="color:rgba(255,255,255,0.4); font-size:11px;"><?php echo $data['desc']; ?></div>
                                            </div>
                                            <div class="wsb-layout-check" style="width:20px; height:20px; background:var(--wsb-primary); color:white; border-radius:50%; display:<?php echo $is_active ? 'flex' : 'none'; ?>; align-items:center; justify-content:center; font-size:10px;">✓</div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <script>
                        function updateWsbLayoutSelection(input) {
                            // Reset all cards
                            document.querySelectorAll('.wsb-layout-visual-card').forEach(card => {
                                card.style.background = 'rgba(255,255,255,0.02)';
                                card.style.borderColor = 'rgba(255,255,255,0.05)';
                                card.querySelector('.wsb-layout-check').style.display = 'none';
                            });
                            
                            // Highlight selected card
                            const selectedCard = input.nextElementSibling;
                            selectedCard.style.background = 'rgba(99,102,241,0.1)';
                            selectedCard.style.borderColor = 'var(--wsb-primary)';
                            selectedCard.querySelector('.wsb-layout-check').style.display = 'flex';
                        }
                        </script>

                        <!-- Section 5: Deployment -->
                        <div style="background:var(--wsb-panel-dark); border-radius:16px; border:1px solid var(--wsb-border); overflow:hidden; border-top:4px solid var(--wsb-success);">
                            <div style="padding:20px; border-bottom:1px solid var(--wsb-border);">
                                <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px; font-size:15px;">
                                    <span class="dashicons dashicons-shortcode" style="color:var(--wsb-success);"></span> Frontend Deployment
                                </h3>
                            </div>
                            <div style="padding:20px;">
                                <div style="background:rgba(16, 185, 129, 0.05); border:1px dashed var(--wsb-success); padding:10px; border-radius:10px; text-align:center; margin-bottom:15px;">
                                    <code style="font-size:16px; color:var(--wsb-success); font-weight:900;">[wsb_booking_widget]</code>
                                </div>
                                <input type="text" readonly value="<?php echo site_url('/booking'); ?>" onclick="this.select();"
                                    style="width:100%; background:#0f172a; color:var(--wsb-primary); border:1px solid var(--wsb-border); padding:8px; border-radius:8px; font-size:11px;">
                            </div>
                        </div>


                    </div>

                </div>
            </form>
        </div>

        <!-- Reset Confirmation Modal -->
        <div id="wsb-reset-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); backdrop-filter:blur(8px); z-index:99999; align-items:center; justify-content:center; padding:20px;">
            <div style="background:#1e293b; border:1px solid rgba(255,255,255,0.1); border-radius:24px; max-width:450px; width:100%; padding:40px; text-align:center; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
                <div style="width:80px; height:80px; background:rgba(239, 68, 68, 0.1); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 25px;">
                    <span class="dashicons dashicons-undo" style="color:#ef4444; font-size:40px; width:40px; height:40px;"></span>
                </div>
                <h2 style="color:white; margin-bottom:15px; font-size:24px; font-weight:800;">Restore Factory Defaults?</h2>
                <p style="color:rgba(255,255,255,0.6); line-height:1.6; margin-bottom:35px; font-size:14px;">This action will permanently remove all your custom branding, color palettes, and typography settings. This cannot be undone.</p>
                
                <div style="display:flex; gap:15px;">
                    <button type="button" onclick="document.getElementById('wsb-reset-modal').style.display='none'" 
                        style="flex:1; background:rgba(255,255,255,0.05); color:white; border:1px solid rgba(255,255,255,0.1); padding:14px; border-radius:12px; font-weight:600; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="button" onclick="document.getElementById('wsb-actual-reset-trigger').click();" 
                        style="flex:1; background:#ef4444; color:white; border:none; padding:14px; border-radius:12px; font-weight:700; cursor:pointer; box-shadow:0 10px 15px -3px rgba(239, 68, 68, 0.3);">
                        Confirm Reset
                    </button>
                </div>
            </div>
        </div>

        <form id="wsb-reset-hidden-form" method="post" style="display:none;">
            <?php wp_nonce_field('wsb_save_design', 'wsb_design_nonce'); ?>
            <input type="hidden" name="wsb_reset_design" value="1">
            <button type="submit" id="wsb-actual-reset-trigger"></button>
        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const resetBtn = document.getElementById('wsb-reset-trigger-btn');
            const modal = document.getElementById('wsb-reset-modal');
            
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
