<?php
class Wsb_Admin_Integrations {
    private $admin;

    public function __construct($admin) {
        $this->admin = $admin;
    }

    public function display() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['wsb_integrations_nonce']) && wp_verify_nonce($_POST['wsb_integrations_nonce'], 'wsb_save_integrations')) {
                // Payment Integrations
                update_option('wsb_stripe_publishable_key', sanitize_text_field($_POST['wsb_stripe_publishable_key']));
                update_option('wsb_stripe_secret_key', sanitize_text_field($_POST['wsb_stripe_secret_key']));

                // Extension Toggles
                update_option('wsb_enable_paypal', isset($_POST['wsb_enable_paypal']) ? 'yes' : 'no');
                update_option('wsb_enable_aura_luxe', isset($_POST['wsb_enable_aura_luxe']) ? 'yes' : 'no');

                do_action('wsb_after_save_integrations', $_POST);
                echo '<div class="notice notice-success is-dismissible"><p>Integrations and gateways securely updated!</p></div>';
            }
        }

        $stripe_pk = get_option('wsb_stripe_publishable_key', '');
        $stripe_sk = get_option('wsb_stripe_secret_key', '');
        ?>
        <div class="wrap wsb-admin-wrap">
            <div style="margin-bottom:30px;">
                <h1 style="margin:0; font-size:28px; font-weight:800; color:#fff;">Integrations & Ecosystem</h1>
                <p style="color:var(--wsb-text-muted); margin-top:5px; font-size:15px;">Connect your booking engine to external payment processors, marketing tools, and third-party APIs.</p>
            </div>

            <form method="post">
                <?php wp_nonce_field('wsb_save_integrations', 'wsb_integrations_nonce'); ?>
                
                <style>
                    .wsb-switch { position: relative; display: inline-block; width: 44px; height: 22px; }
                    .wsb-switch input { opacity: 0; width: 0; height: 0; }
                    .wsb-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #1e293b; transition: .4s; border-radius: 34px; border: 1px solid #334155; }
                    .wsb-slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 4px; bottom: 3px; background-color: #94a3b8; transition: .4s; border-radius: 50%; }
                    input:checked + .wsb-slider { background-color: #22c55e; border-color: #22c55e; }
                    input:checked + .wsb-slider:before { transform: translateX(22px); background-color: #fff; }
                </style>

                <div>
                    
                    <!-- Main Content: Active Integrations -->
                    <div style="display:flex; flex-direction:column; gap:30px;">
                        
                        <!-- Payment Ecosystem Card -->
                        <div style="background:var(--wsb-panel-dark); border-radius:16px; border:1px solid var(--wsb-border); overflow:hidden; border-top:4px solid #22c55e;">
                            <div style="padding:25px; border-bottom:1px solid var(--wsb-border); display:flex; align-items:center; justify-content:space-between;">
                                <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-money-alt" style="color:#22c55e;"></span> Payment Gateway Ecosystem
                                </h3>
                                <span style="background:rgba(34, 197, 94, 0.1); color:#22c55e; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.05em;">Stripe Active</span>
                            </div>
                            
                            <div style="padding:25px; display:flex; flex-direction:column; gap:30px;">
                                
                                <!-- Stripe Section -->
                                <div>
                                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
                                        <img src="<?php echo WSB_PLUGIN_URL . 'assets/images/stripe.png'; ?>" style="height:32px; width:auto; display:block;" alt="Stripe Logo">
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

                                <?php do_action('wsb_admin_settings_payment_gateways', $this); ?>
                            </div>
                        </div>

                        <!-- Third-Party Integrations / Marketplace Card -->
                        <div style="background:var(--wsb-panel-dark); border-radius:16px; border:1px solid var(--wsb-border); overflow:hidden;">
                            <div style="padding:25px; border-bottom:1px solid var(--wsb-border);">
                                <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px;">
                                    <span class="dashicons dashicons-networking" style="color:var(--wsb-primary);"></span> Active Ecosystem Extensions
                                </h3>
                            </div>
                            <div style="padding:25px; display:flex; flex-direction:column; gap:15px;">
                                <?php 
                                // Detect known integrations
                                $extensions = [];
                                if (class_exists('Wsb_Paypal_Integration')) {
                                    $extensions[] = [
                                        'id' => 'paypal',
                                        'name' => 'PayPal Professional Gateway', 
                                        'icon' => 'dashicons-money-alt', 
                                        'color' => '#f59e0b', 
                                        'desc' => 'High-conversion checkout engine.',
                                        'enabled' => get_option('wsb_enable_paypal', 'yes') === 'yes'
                                    ];
                                }
                                if (class_exists('WSB_Aura_Luxe_Integration')) {
                                    $extensions[] = [
                                        'id' => 'aura_luxe',
                                        'name' => 'Aura Luxe Design Pack', 
                                        'icon' => 'dashicons-art', 
                                        'color' => '#c084fc', 
                                        'desc' => 'Premium aesthetic and layout engine.',
                                        'enabled' => get_option('wsb_enable_aura_luxe', 'yes') === 'yes'
                                    ];
                                }

                                if (!empty($extensions)) {
                                    foreach ($extensions as $int):
                                    ?>
                                    <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.02); padding:18px; border-radius:14px; border:1px solid rgba(255,255,255,0.05);">
                                        <div style="display:flex; align-items:center; gap:15px;">
                                            <div style="width:40px; height:40px; background:rgba(255,255,255,0.03); border-radius:10px; display:flex; align-items:center; justify-content:center;">
                                                <span class="dashicons <?php echo $int['icon']; ?>" style="color:<?php echo $int['color']; ?>; font-size:20px; width:auto; height:auto;"></span>
                                            </div>
                                            <div>
                                                <div style="color:#fff; font-weight:700; font-size:14px;"><?php echo $int['name']; ?></div>
                                                <div style="color:var(--wsb-text-muted); font-size:11px;"><?php echo $int['desc']; ?></div>
                                            </div>
                                        </div>
                                        <div style="display:flex; align-items:center; gap:20px;">
                                            <div style="text-align:right;">
                                                <span style="display:block; color:<?php echo $int['enabled'] ? '#22c55e' : '#64748b'; ?>; font-size:10px; font-weight:800; text-transform:uppercase; margin-bottom:4px;">
                                                    <?php echo $int['enabled'] ? 'Enabled' : 'Disabled'; ?>
                                                </span>
                                                <label class="wsb-switch">
                                                    <input type="checkbox" name="wsb_enable_<?php echo $int['id']; ?>" value="yes" <?php checked($int['enabled']); ?>>
                                                    <span class="wsb-slider"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    endforeach;
                                }

                                // Dynamic hook for any other integrations
                                do_action('wsb_admin_integrations_list');

                                if (empty($extensions) && !has_action('wsb_admin_integrations_list')) {
                                    ?>
                                    <div style="text-align:center; padding:40px 20px; background:rgba(255,255,255,0.02); border-radius:12px; border:1px dashed rgba(255,255,255,0.1);">
                                        <span class="dashicons dashicons-admin-plugins" style="font-size:48px; width:48px; height:48px; color:rgba(255,255,255,0.1); margin-bottom:15px; display:block; margin-left:auto; margin-right:auto;"></span>
                                        <p style="color:var(--wsb-text-muted); margin:0; font-size:14px;">No additional third-party integrations detected.</p>
                                        <p style="color:rgba(255,255,255,0.3); font-size:12px; margin-top:5px;">Install official extensions to enable more features.</p>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Save Actions -->
                        <div style="background:var(--wsb-panel-dark); padding:25px; border-radius:16px; border:1px solid var(--wsb-border); display:flex; flex-direction:column; gap:15px;">
                            <button type="submit" class="wsb-btn-primary" style="width:100%; padding:15px; font-size:16px; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);">Save Integration Ecosystem</button>
                        </div>

                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}
