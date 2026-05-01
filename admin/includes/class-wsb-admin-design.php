<?php
class Wsb_Admin_Design {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wsb_design_nonce']) && wp_verify_nonce($_POST['wsb_design_nonce'], 'wsb_save_design')) {
            update_option('wsb_service_layout', sanitize_text_field($_POST['wsb_service_layout']));
            update_option('wsb_brand_color', sanitize_hex_color($_POST['wsb_brand_color']));
            update_option('wsb_brand_color_end', sanitize_hex_color($_POST['wsb_brand_color_end']));
            update_option('wsb_accent_color', sanitize_hex_color($_POST['wsb_accent_color']));
            update_option('wsb_virtual_bg_color', sanitize_hex_color($_POST['wsb_virtual_bg_color']));
            echo '<div class="notice notice-success is-dismissible"><p>Design and aesthetic preferences saved!</p></div>';
        }

        $service_layout = get_option('wsb_service_layout', 'modern_grid');
        $brand_color = get_option('wsb_brand_color', '#6366f1');
        $brand_color_end = get_option('wsb_brand_color_end', '#a855f7');
        $accent_color = get_option('wsb_accent_color', '#4f46e5');
        $virtual_bg_color = get_option('wsb_virtual_bg_color', '#f8fafc');
        ?>
        <div class="wrap wsb-admin-wrap">
            <h1 style="margin-bottom:20px;">Frontend Experience & Designer</h1>
            <p style="color:var(--wsb-text-muted); margin-bottom:30px;">Customize how your booking widget looks and feels to
                your customers.</p>

            <form method="post">
                <?php wp_nonce_field('wsb_save_design', 'wsb_design_nonce'); ?>

                <div class="wsb-design-section">
                    <h2 style="color:white; margin-bottom:25px; font-weight: 700; letter-spacing: -0.02em; display:flex; align-items:center; gap:10px;">
                        <span class="dashicons dashicons-art"></span> Brand Identity & Gradients
                    </h2>
                    <div class="wsb-color-row">
                        <label class="wsb-color-card">
                            <strong style="color:white; font-size: 14px; font-weight: 600; display:block;">Primary Color (Start)</strong>
                            <div class="wsb-color-picker-wrapper">
                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($brand_color); ?>;"></div>
                                <span class="wsb-color-hex"><?php echo esc_html($brand_color); ?></span>
                                <input type="color" name="wsb_brand_color" value="<?php echo esc_attr($brand_color); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.textContent = this.value; this.previousElementSibling.previousElementSibling.style.background = this.value;">
                            </div>
                        </label>
                        
                        <label class="wsb-color-card">
                            <strong style="color:white; font-size: 14px; font-weight: 600; display:block;">Gradient Color (End)</strong>
                            <div class="wsb-color-picker-wrapper">
                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($brand_color_end); ?>;"></div>
                                <span class="wsb-color-hex"><?php echo esc_html($brand_color_end); ?></span>
                                <input type="color" name="wsb_brand_color_end" value="<?php echo esc_attr($brand_color_end); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.textContent = this.value; this.previousElementSibling.previousElementSibling.style.background = this.value;">
                            </div>
                        </label>
                        
                        <label class="wsb-color-card">
                            <strong style="color:white; font-size: 14px; font-weight: 600; display:block;">Interactive Accent</strong>
                            <div class="wsb-color-picker-wrapper">
                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($accent_color); ?>;"></div>
                                <span class="wsb-color-hex"><?php echo esc_html($accent_color); ?></span>
                                <input type="color" name="wsb_accent_color" value="<?php echo esc_attr($accent_color); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.textContent = this.value; this.previousElementSibling.previousElementSibling.style.background = this.value;">
                            </div>
                        </label>
                        
                        <label class="wsb-color-card">
                            <strong style="color:white; font-size: 14px; font-weight: 600; display:block;">Service Page Background</strong>
                            <div class="wsb-color-picker-wrapper">
                                <div class="wsb-color-swatch" style="background: <?php echo esc_attr($virtual_bg_color); ?>;"></div>
                                <span class="wsb-color-hex"><?php echo esc_html($virtual_bg_color); ?></span>
                                <input type="color" name="wsb_virtual_bg_color" value="<?php echo esc_attr($virtual_bg_color); ?>" class="wsb-hidden-color-input" onchange="this.previousElementSibling.textContent = this.value; this.previousElementSibling.previousElementSibling.style.background = this.value;">
                            </div>
                        </label>
                    </div>

                    <h2 style="color:white; margin-bottom:10px; display:flex; align-items:center; gap:10px;">
                        <span class="dashicons dashicons-layout"></span> Layout & Aesthetic Style
                    </h2>
                    <p style="color:var(--wsb-text-muted); font-size:13px; margin-bottom:25px;">Choose from professionally crafted design languages for your service display.</p>

                    <div class="wsb-layout-selector">
                        <?php
                        $layouts = [
                            'modern_grid' => 'Signature Grid',
                            'glass_cards_v2' => 'Glass Elite',
                            'metro_grid' => 'Immersive Metro',
                            'neon_night' => 'Cyber Dark'
                        ];
                        foreach ($layouts as $val => $name): ?>
                            <label class="wsb-layout-option">
                                <input type="radio" name="wsb_service_layout" value="<?php echo $val; ?>" <?php checked($service_layout, $val); ?>>
                                <div class="wsb-layout-preview">
                                    <div style="font-size:10px; color:rgba(255,255,255,0.4); text-transform:uppercase; font-weight:700; z-index: 10; position: relative;">
                                        <?php echo $name; ?></div>
                                    
                                    <?php if ($val == 'modern_grid'): ?>
                                        <div style="position:absolute; inset:0; background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); opacity: 0.5;"></div>
                                    <?php endif; ?>

                                    <?php if ($val == 'neon_night'): ?>
                                        <div style="position:absolute; inset:0; background: #020617;"></div>
                                    <?php endif; ?>

                                    <?php if ($val == 'glass_cards_v2'): ?>
                                        <div style="position:absolute; inset:0; background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); opacity: 0.3;"></div>
                                    <?php endif; ?>

                                    <?php if ($val == 'metro_grid'): ?>
                                        <div style="position:absolute; inset:0; background: url('https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&q=80&w=200') center/cover; opacity: 0.4;"></div>
                                    <?php endif; ?>
                                </div>
                                <span class="wsb-layout-name"><?php echo $name; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <button type="submit" class="wsb-btn-premium"
                        style="background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color: #fff; border: none; padding: 14px 35px; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2); transition: all 0.3s ease; display: flex; align-items: center; gap: 10px;">
                        <span>✨</span> Apply Premium Design
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
}
