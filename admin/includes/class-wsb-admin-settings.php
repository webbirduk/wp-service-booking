<?php
class Wsb_Admin_Settings
{
    private $admin;

    public function __construct($admin)
    {
        $this->admin = $admin;
    }

    public function display()
    {
        global $wpdb;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['wsb_restore_defaults'])) {
                delete_option('wsb_currency');
                delete_option('wsb_skip_professional_step');
                delete_option('wsb_skip_payment_step');
                delete_option('wsb_filter_staff_by_service');
                delete_option('wsb_enable_split_scheduling');
                delete_option('wsb_booking_buffer');
                delete_option('wsb_min_notice');
                delete_option('wsb_instant_confirm');
                delete_option('wsb_enable_notifications');
                delete_option('wsb_cancellation_policy');
                
                echo '<div class="notice notice-warning is-dismissible"><p>All settings have been restored to factory defaults.</p></div>';
            } elseif (isset($_POST['wsb_settings_nonce']) && wp_verify_nonce($_POST['wsb_settings_nonce'], 'wsb_save_settings')) {
                do_action('wsb_before_save_settings', $_POST);
                update_option('wsb_currency', sanitize_text_field($_POST['wsb_currency']));

                // Flow Control
                update_option('wsb_skip_professional_step', isset($_POST['wsb_skip_professional_step']) ? 'yes' : 'no');
                update_option('wsb_skip_payment_step', isset($_POST['wsb_skip_payment_step']) ? 'yes' : 'no');
                update_option('wsb_filter_staff_by_service', isset($_POST['wsb_filter_staff_by_service']) ? 'yes' : 'no');
                update_option('wsb_enable_split_scheduling', isset($_POST['wsb_enable_split_scheduling']) ? 'yes' : 'no');

                // Advanced Rules
                update_option('wsb_booking_buffer', intval($_POST['wsb_booking_buffer']));
                update_option('wsb_min_notice', intval($_POST['wsb_min_notice']));
                update_option('wsb_instant_confirm', isset($_POST['wsb_instant_confirm']) ? 'yes' : 'no');
                update_option('wsb_enable_notifications', isset($_POST['wsb_enable_notifications']) ? 'yes' : 'no');
                update_option('wsb_cancellation_policy', sanitize_textarea_field($_POST['wsb_cancellation_policy']));

                do_action('wsb_after_save_settings', $_POST);
                echo '<div class="notice notice-success is-dismissible"><p>System Integration Settings securely saved!</p></div>';
            }
        }

        $currency = get_option('wsb_currency', 'USD');
        $skip_prof = get_option('wsb_skip_professional_step', 'no');
        $skip_pay = get_option('wsb_skip_payment_step', 'no');
        $filter_staff = get_option('wsb_filter_staff_by_service', 'yes');
        $enable_split = get_option('wsb_enable_split_scheduling', 'yes');
        $buffer = get_option('wsb_booking_buffer', '15');
        $min_notice = get_option('wsb_min_notice', '2');
        $instant_confirm = get_option('wsb_instant_confirm', 'yes');
        $enable_notif = get_option('wsb_enable_notifications', 'yes');
        $policy = get_option('wsb_cancellation_policy', 'Please cancel at least 24 hours in advance.');
        ?>
        <div class="wrap wsb-admin-wrap">
            <div style="margin-bottom:30px;">
                <h1 style="margin:0; font-size:28px; font-weight:800; color:#fff;">System Settings</h1>
                <p style="color:var(--wsb-text-muted); margin-top:5px; font-size:15px;">Configure your global booking
                    architecture, payment gateways, and design language.</p>
            </div>

            <form method="post">
                <?php wp_nonce_field('wsb_save_settings', 'wsb_settings_nonce'); ?>

                <div style="display:flex; flex-direction:column; gap:30px;">

                    <!-- General Settings Card -->
                    <div
                        style="background:var(--wsb-panel-dark); border-radius:16px; border:1px solid var(--wsb-border); overflow:hidden; border-top:4px solid #6366f1;">
                        <div style="padding:25px; border-bottom:1px solid var(--wsb-border);">
                            <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px;">
                                <span class="dashicons dashicons-admin-settings" style="color:#6366f1;"></span> General Settings
                            </h3>
                        </div>
                        <div style="padding:25px; display:flex; flex-direction:column; gap:30px;">

                            <style>
                                .wsb-switch {
                                    position: relative;
                                    display: inline-block;
                                    width: 50px;
                                    height: 26px;
                                }

                                .wsb-switch input {
                                    opacity: 0;
                                    width: 0;
                                    height: 0;
                                }

                                .wsb-slider {
                                    position: absolute;
                                    cursor: pointer;
                                    top: 0;
                                    left: 0;
                                    right: 0;
                                    bottom: 0;
                                    background-color: #1e293b;
                                    transition: .4s;
                                    border-radius: 34px;
                                    border: 1px solid #334155;
                                }

                                .wsb-slider:before {
                                    position: absolute;
                                    content: "";
                                    height: 18px;
                                    width: 18px;
                                    left: 4px;
                                    bottom: 3px;
                                    background-color: #94a3b8;
                                    transition: .4s;
                                    border-radius: 50%;
                                }

                                input:checked+.wsb-slider {
                                    background-color: var(--wsb-primary);
                                    border-color: var(--wsb-primary);
                                }

                                input:checked+.wsb-slider:before {
                                    transform: translateX(24px);
                                    background-color: #fff;
                                }
                            </style>

                            <!-- 1. Core Architecture -->
                            <div style="background:rgba(255,255,255,0.02); padding:25px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                <h4 style="color:var(--wsb-primary); margin:0 0 20px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.1em; font-weight:800;">01. Core Architecture</h4>
                                <div style="display:flex; flex-direction:column; gap:20px;">
                                        <div style="display:flex; align-items:center; justify-content:space-between;">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                    System Default Currency
                                                    <span class="wsb-info-icon" data-tooltip="Sets the primary currency used for all pricing and checkout transactions throughout the plugin.">?</span>
                                                </label>
                                                <span style="color:var(--wsb-text-muted); font-size:12px;">Primary currency for all financial transactions.</span>
                                            </div>
                                            <select name="wsb_currency" style="width:180px; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:10px; border-radius:8px;">
                                                <option value="USD" <?php selected($currency, 'USD'); ?>>USD ($)</option>
                                                <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR (€)</option>
                                                <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP (£)</option>
                                                <option value="INR" <?php selected($currency, 'INR'); ?>>INR (₹)</option>
                                            </select>
                                        </div>
                                        <div style="display:flex; align-items:center; justify-content:space-between;">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                    Skip Professional Selection
                                                    <span class="wsb-info-icon" data-tooltip="If enabled, the 'Choose Professional' step will be removed, and bookings will be assigned automatically.">?</span>
                                                </label>
                                                <span style="color:var(--wsb-text-muted); font-size:12px;">Automatically bypass the team step if not required.</span>
                                            </div>
                                            <label class="wsb-switch">
                                                <input type="checkbox" name="wsb_skip_professional_step" value="yes" <?php checked($skip_prof, 'yes'); ?>>
                                                <span class="wsb-slider"></span>
                                            </label>
                                        </div>
                                        <div style="display:flex; align-items:center; justify-content:space-between;">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                    Disable Online Payments
                                                    <span class="wsb-info-icon" data-tooltip="Hides Stripe/PayPal options and allows users to submit booking requests without immediate payment.">?</span>
                                                </label>
                                                <span style="color:var(--wsb-text-muted); font-size:12px;">Skip checkout and confirm bookings instantly.</span>
                                            </div>
                                            <label class="wsb-switch">
                                                <input type="checkbox" name="wsb_skip_payment_step" value="yes" <?php checked($skip_pay, 'yes'); ?>>
                                                <span class="wsb-slider"></span>
                                            </label>
                                        </div>
                                        <div style="display:flex; align-items:center; justify-content:space-between;">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                    Filter Staff by Service
                                                    <span class="wsb-info-icon" data-tooltip="When selecting a service, only professionals who are marked as specialists for that service will be displayed.">?</span>
                                                </label>
                                                <span style="color:var(--wsb-text-muted); font-size:12px;">Only show professionals assigned to selected services.</span>
                                            </div>
                                            <label class="wsb-switch">
                                                <input type="checkbox" name="wsb_filter_staff_by_service" value="yes" <?php checked($filter_staff, 'yes'); ?>>
                                                <span class="wsb-slider"></span>
                                            </label>
                                        </div>
                                        <div style="display:flex; align-items:center; justify-content:space-between;">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                    Enable Split Scheduling
                                                    <span class="wsb-info-icon" data-tooltip="Allows customers to book multiple services with different team members and at different times within one session.">?</span>
                                                </label>
                                                <span style="color:var(--wsb-text-muted); font-size:12px;">Allow customers to pick different pros/times for each service.</span>
                                            </div>
                                            <label class="wsb-switch">
                                                <input type="checkbox" name="wsb_enable_split_scheduling" value="yes" <?php checked($enable_split, 'yes'); ?>>
                                                <span class="wsb-slider"></span>
                                            </label>
                                        </div>
                                </div>
                            </div>

                            <!-- 2. Scheduling Rules & Time Control -->
                            <div style="background:rgba(255,255,255,0.02); padding:25px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                <h4 style="color:#10b981; margin:0 0 20px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.1em; font-weight:800;">02. Scheduling & Time Control</h4>
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px;">
                                    <div>
                                        <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:10px;">01. Booking Buffer (Min)</label>
                                        <input type="number" name="wsb_booking_buffer" value="<?php echo esc_attr($buffer); ?>" style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:12px; border-radius:8px;">
                                        <span style="color:var(--wsb-text-muted); font-size:11px; margin-top:5px; display:block;">Time between slots for preparation.</span>
                                    </div>
                                    <div>
                                        <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:10px;">02. Min. Notice Time (Hrs)</label>
                                        <input type="number" name="wsb_min_notice" value="<?php echo esc_attr($min_notice); ?>" style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:12px; border-radius:8px;">
                                        <span style="color:var(--wsb-text-muted); font-size:11px; margin-top:5px; display:block;">How far in advance clients must book.</span>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Operational Policies -->
                            <div style="background:rgba(255,255,255,0.02); padding:25px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                <h4 style="color:#f59e0b; margin:0 0 20px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.1em; font-weight:800;">03. Operational Policies</h4>
                                <div style="display:flex; flex-direction:column; gap:20px; margin-bottom:25px;">
                                    <div style="display:flex; align-items:center; justify-content:space-between;">
                                        <div>
                                            <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">01. Instant Confirmation</label>
                                            <span style="color:var(--wsb-text-muted); font-size:12px;">Auto-confirm new bookings without manual review.</span>
                                        </div>
                                        <label class="wsb-switch">
                                            <input type="checkbox" name="wsb_instant_confirm" value="yes" <?php checked($instant_confirm, 'yes'); ?>>
                                            <span class="wsb-slider"></span>
                                        </label>
                                    </div>
                                    <div style="display:flex; align-items:center; justify-content:space-between;">
                                        <div>
                                            <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">02. Email Notifications</label>
                                            <span style="color:var(--wsb-text-muted); font-size:12px;">Send automated confirmation emails to customers.</span>
                                        </div>
                                        <label class="wsb-switch">
                                            <input type="checkbox" name="wsb_enable_notifications" value="yes" <?php checked($enable_notif, 'yes'); ?>>
                                            <span class="wsb-slider"></span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:10px;">03. Cancellation Policy</label>
                                    <textarea name="wsb_cancellation_policy" rows="3" style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--wsb-border); padding:15px; border-radius:8px; line-height:1.5;"><?php echo esc_textarea($policy); ?></textarea>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Actions -->
                    <div style="background:var(--wsb-panel-dark); padding:25px; border-radius:16px; border:1px solid var(--wsb-border); display:flex; gap:15px;">
                        <button type="submit" name="wsb_save_settings" class="wsb-btn-primary" style="flex:2; padding:15px; font-size:16px; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);">Save Settings</button>
                        <button type="submit" name="wsb_restore_defaults" id="wsb-restore-defaults-btn" class="wsb-btn" style="flex:1; background:rgba(255,255,255,0.05); color:#94a3b8; border:1px solid rgba(255,255,255,0.1); padding:15px; font-size:14px; font-weight:700;">Restore Defaults</button>
                    </div>

                </div>
            </form>
        </div>
        <?php
    }

}
