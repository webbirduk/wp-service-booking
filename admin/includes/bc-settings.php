<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bc_Settings
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
            if (isset($_POST['bc_restore_defaults'])) {
                delete_option('bc_currency');
                delete_option('bc_skip_professional_step');
                delete_option('bc_skip_payment_step');
                delete_option('bc_filter_staff_by_service');
                delete_option('bc_enable_split_scheduling');
                delete_option('bc_booking_buffer');
                delete_option('bc_min_notice');
                delete_option('bc_instant_confirm');
                delete_option('bc_enable_notifications');
                delete_option('bc_cancellation_policy');
                delete_option('bc_allow_user_skip_prof');
                
                echo '<div class="notice bc-custom-notice notice-warning is-dismissible bc-custom-notice"><p>' . __('All settings have been restored to factory defaults.', 'boocommerce') . '</p></div>';
            } elseif (isset($_POST['bc_settings_nonce']) && wp_verify_nonce($_POST['bc_settings_nonce'], 'bc_save_settings')) {
                do_action('bc_before_save_settings', $_POST);
                update_option('bc_currency', sanitize_text_field($_POST['bc_currency']));

                // Flow Control
                update_option('bc_skip_professional_step', isset($_POST['bc_skip_professional_step']) ? 'yes' : 'no');
                update_option('bc_skip_payment_step', isset($_POST['bc_skip_payment_step']) ? 'yes' : 'no');
                update_option('bc_filter_staff_by_service', isset($_POST['bc_filter_staff_by_service']) ? 'yes' : 'no');
                update_option('bc_enable_split_scheduling', isset($_POST['bc_enable_split_scheduling']) ? 'yes' : 'no');
                update_option('bc_allow_user_skip_prof', isset($_POST['bc_allow_user_skip_prof']) ? 'yes' : 'no');

                // Advanced Rules
                update_option('bc_booking_buffer', intval($_POST['bc_booking_buffer']));
                update_option('bc_min_notice', intval($_POST['bc_min_notice']));
                update_option('bc_instant_confirm', isset($_POST['bc_instant_confirm']) ? 'yes' : 'no');
                update_option('bc_enable_notifications', isset($_POST['bc_enable_notifications']) ? 'yes' : 'no');
                update_option('bc_cancellation_policy', sanitize_textarea_field($_POST['bc_cancellation_policy']));

                do_action('bc_after_save_settings', $_POST);
                echo '<div class="notice bc-custom-notice notice-success is-dismissible bc-custom-notice"><p>' . __('System Integration Settings securely saved!', 'boocommerce') . '</p></div>';
            }
        }

        $currency = get_option('bc_currency', 'USD');
        $skip_prof = get_option('bc_skip_professional_step', 'no');
        $skip_pay = get_option('bc_skip_payment_step', 'no');
        $filter_staff = get_option('bc_filter_staff_by_service', 'yes');
        $enable_split = get_option('bc_enable_split_scheduling', 'yes');
        $allow_user_skip_prof = get_option('bc_allow_user_skip_prof', 'no');
        $buffer = get_option('bc_booking_buffer', '15');
        $min_notice = get_option('bc_min_notice', '2');
        $instant_confirm = get_option('bc_instant_confirm', 'yes');
        $enable_notif = get_option('bc_enable_notifications', 'yes');
        $policy = get_option('bc_cancellation_policy', 'Please cancel at least 24 hours in advance.');
        ?>
        <div class="wrap bc-admin-wrap bc-settings-wrapper">
            <style>
                /* Settings Responsive Layouts */
                .bc-settings-header { margin-bottom: 30px; }
                .bc-settings-column-stack { display: flex; flex-direction: column; gap: 30px; }
                .bc-settings-row { display: flex; align-items: center; justify-content: space-between; }
                .bc-settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
                .bc-settings-action-bar { background: var(--bc-panel-dark); padding: 25px; border-radius: 16px; border: 1px solid var(--bc-border); display: flex; gap: 15px; }

                @media (max-width: 768px) {
                    .bc-settings-row { flex-direction: column; align-items: flex-start; gap: 15px; }
                    .bc-settings-row > select { width: 100% !important; }
                    .bc-settings-grid { grid-template-columns: 1fr; }
                    .bc-settings-action-bar { flex-direction: column; }
                    .bc-settings-action-bar button { width: 100%; }
                }
            </style>
            <div class="bc-settings-header">
                <h1 style="margin:0; font-size:28px; font-weight:800; color:#fff;"><?php _e('System Settings', 'boocommerce'); ?></h1>
                <p style="color:var(--bc-text-muted); margin-top:5px; font-size:15px;"><?php _e('Configure your global booking architecture, payment gateways, and design language.', 'boocommerce'); ?></p>
            </div>

            <form method="post">
                <?php wp_nonce_field('bc_save_settings', 'bc_settings_nonce'); ?>

                <div class="bc-settings-column-stack">

                    <!-- General Settings Card -->
                    <div
                        style="background:var(--bc-panel-dark); border-radius:16px; border:1px solid var(--bc-border); overflow:hidden; border-top:4px solid #6366f1;">
                        <div style="padding:25px; border-bottom:1px solid var(--bc-border);">
                            <h3 style="margin:0; color:#fff; display:flex; align-items:center; gap:10px;">
                                <span class="dashicons dashicons-admin-settings" style="color:#6366f1;"></span> <?php _e('General Settings', 'boocommerce'); ?>
                            </h3>
                        </div>
                        <div style="padding:25px; display:flex; flex-direction:column; gap:30px;">

                            <style>
                                .bc-switch {
                                    position: relative;
                                    display: inline-block;
                                    width: 50px;
                                    height: 26px;
                                }

                                .bc-switch input {
                                    opacity: 0;
                                    width: 0;
                                    height: 0;
                                }

                                .bc-slider {
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

                                .bc-slider:before {
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

                                input:checked+.bc-slider {
                                    background-color: var(--bc-primary);
                                    border-color: var(--bc-primary);
                                }

                                input:checked+.bc-slider:before {
                                    transform: translateX(24px);
                                    background-color: #fff;
                                }
                            </style>

                            <!-- Core Architecture -->
                            <div style="background:rgba(255,255,255,0.02); padding:25px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                <h4 style="color:var(--bc-primary); margin:0 0 20px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.1em; font-weight:800;"><?php _e('Core Architecture', 'boocommerce'); ?></h4>
                                <div style="display:flex; flex-direction:column; gap:20px;">
                                        <div class="bc-settings-row">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                    <?php _e('System Default Currency', 'boocommerce'); ?>
                                                    <span class="bc-info-icon" data-tooltip="<?php esc_attr_e('Sets the primary currency used for all pricing and checkout transactions throughout the plugin.', 'boocommerce'); ?>">?</span>
                                                </label>
                                                <span style="color:var(--bc-text-muted); font-size:12px;"><?php _e('Primary currency for all financial transactions.', 'boocommerce'); ?></span>
                                            </div>
                                            <select name="bc_currency" style="width:180px; background:#0f172a; color:#fff; border:1px solid var(--bc-border); padding:10px; border-radius:8px;">
                                                <option value="USD" <?php selected($currency, 'USD'); ?>>USD ($)</option>
                                                <option value="EUR" <?php selected($currency, 'EUR'); ?>>EUR (€)</option>
                                                <option value="GBP" <?php selected($currency, 'GBP'); ?>>GBP (£)</option>
                                                <option value="INR" <?php selected($currency, 'INR'); ?>>INR (₹)</option>
                                            </select>
                                        </div>
                                        <div class="bc-settings-row">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                    <?php _e('Skip Professional Selection', 'boocommerce'); ?>
                                                    <span class="bc-info-icon" data-tooltip="<?php esc_attr_e('If enabled, the \'Choose Professional\' step will be removed, and bookings will be assigned automatically.', 'boocommerce'); ?>">?</span>
                                                </label>
                                                <span style="color:var(--bc-text-muted); font-size:12px;"><?php _e('Automatically bypass the team step if not required.', 'boocommerce'); ?></span>
                                            </div>
                                            <label class="bc-switch">
                                                <input type="checkbox" name="bc_skip_professional_step" value="yes" <?php checked($skip_prof, 'yes'); ?>>
                                                <span class="bc-slider"></span>
                                            </label>
                                        </div>
                                        <div class="bc-settings-row">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                    <?php _e('Disable Online Payments', 'boocommerce'); ?>
                                                    <span class="bc-info-icon" data-tooltip="<?php esc_attr_e('Hides Stripe/PayPal options and allows users to submit booking requests without immediate payment.', 'boocommerce'); ?>">?</span>
                                                </label>
                                                <span style="color:var(--bc-text-muted); font-size:12px;"><?php _e('Skip checkout and confirm bookings instantly.', 'boocommerce'); ?></span>
                                            </div>
                                            <label class="bc-switch">
                                                <input type="checkbox" name="bc_skip_payment_step" value="yes" <?php checked($skip_pay, 'yes'); ?>>
                                                <span class="bc-slider"></span>
                                            </label>
                                        </div>
                                        <div class="bc-settings-row">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                    <?php _e('Filter Staff by Service', 'boocommerce'); ?>
                                                    <span class="bc-info-icon" data-tooltip="<?php esc_attr_e('When selecting a service, only professionals who are marked as specialists for that service will be displayed.', 'boocommerce'); ?>">?</span>
                                                </label>
                                                <span style="color:var(--bc-text-muted); font-size:12px;"><?php _e('Only show professionals assigned to selected services.', 'boocommerce'); ?></span>
                                            </div>
                                            <label class="bc-switch">
                                                <input type="checkbox" name="bc_filter_staff_by_service" value="yes" <?php checked($filter_staff, 'yes'); ?>>
                                                <span class="bc-slider"></span>
                                            </label>
                                        </div>
                                        <div class="bc-settings-row">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                    <?php _e('Enable Split Scheduling', 'boocommerce'); ?>
                                                    <span class="bc-info-icon" data-tooltip="<?php esc_attr_e('Allows customers to book multiple services with different team members and at different times within one session.', 'boocommerce'); ?>">?</span>
                                                </label>
                                                <span style="color:var(--bc-text-muted); font-size:12px;"><?php _e('Allow customers to pick different pros/times for each service.', 'boocommerce'); ?></span>
                                            </div>
                                            <label class="bc-switch">
                                                <input type="checkbox" name="bc_enable_split_scheduling" value="yes" <?php checked($enable_split, 'yes'); ?>>
                                                <span class="bc-slider"></span>
                                            </label>
                                        </div>
                                        <div class="bc-settings-row">
                                            <div>
                                                <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                    <?php _e('Allow User to Skip Professional', 'boocommerce'); ?>
                                                    <span class="bc-info-icon" data-tooltip="<?php esc_attr_e('If enabled, a Skip button will appear on the professional selection step, allowing customers to proceed without choosing a specific team member.', 'boocommerce'); ?>">?</span>
                                                </label>
                                                <span style="color:var(--bc-text-muted); font-size:12px;"><?php _e('Adds a "Skip" button to the staff selection step.', 'boocommerce'); ?></span>
                                            </div>
                                            <label class="bc-switch">
                                                <input type="checkbox" name="bc_allow_user_skip_prof" value="yes" <?php checked($allow_user_skip_prof, 'yes'); ?>>
                                                <span class="bc-slider"></span>
                                            </label>
                                        </div>
                                </div>
                            </div>

                            <!-- Scheduling Rules & Time Control -->
                            <div style="background:rgba(255,255,255,0.02); padding:25px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                <h4 style="color:#10b981; margin:0 0 20px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.1em; font-weight:800;"><?php _e('Scheduling & Time Control', 'boocommerce'); ?></h4>
                                <div class="bc-settings-grid">
                                    <div>
                                        <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:10px;"><?php _e('Booking Buffer (Min)', 'boocommerce'); ?></label>
                                        <input type="number" name="bc_booking_buffer" value="<?php echo esc_attr($buffer); ?>" style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--bc-border); padding:12px; border-radius:8px;">
                                        <span style="color:var(--bc-text-muted); font-size:11px; margin-top:5px; display:block;"><?php _e('Time between slots for preparation.', 'boocommerce'); ?></span>
                                    </div>
                                    <div>
                                        <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:10px;"><?php _e('Min. Notice Time (Hrs)', 'boocommerce'); ?></label>
                                        <input type="number" name="bc_min_notice" value="<?php echo esc_attr($min_notice); ?>" style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--bc-border); padding:12px; border-radius:8px;">
                                        <span style="color:var(--bc-text-muted); font-size:11px; margin-top:5px; display:block;"><?php _e('How far in advance clients must book.', 'boocommerce'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Email Settings -->
                            <div style="background:rgba(255,255,255,0.02); padding:25px; border-radius:12px; border:1px solid rgba(255,255,255,0.05);">
                                <h4 style="color:#f59e0b; margin:0 0 20px 0; font-size:13px; text-transform:uppercase; letter-spacing:0.1em; font-weight:800;"><?php _e('Email Settings', 'boocommerce'); ?></h4>
                                <div style="display:flex; flex-direction:column; gap:20px; margin-bottom:25px;">
                                    <div class="bc-settings-row">
                                        <div>
                                            <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                <?php _e('Instant Confirmation', 'boocommerce'); ?>
                                                <span class="bc-info-icon" data-tooltip="<?php esc_attr_e('If enabled, all new bookings will be automatically marked as \'Confirmed\' without requiring administrative approval.', 'boocommerce'); ?>">?</span>
                                            </label>
                                            <span style="color:var(--bc-text-muted); font-size:12px;"><?php _e('Auto-confirm new bookings without manual review.', 'boocommerce'); ?></span>
                                        </div>
                                        <label class="bc-switch">
                                            <input type="checkbox" name="bc_instant_confirm" value="yes" <?php checked($instant_confirm, 'yes'); ?>>
                                            <span class="bc-slider"></span>
                                        </label>
                                    </div>
                                    <div class="bc-settings-row">
                                        <div>
                                            <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:4px;">
                                                <?php _e('Email Notifications', 'boocommerce'); ?>
                                                <span class="bc-info-icon" data-tooltip="<?php esc_attr_e('Toggles the automated delivery of professional HTML receipts and welcome emails to your customers.', 'boocommerce'); ?>">?</span>
                                            </label>
                                            <span style="color:var(--bc-text-muted); font-size:12px;"><?php _e('Send automated confirmation emails to customers.', 'boocommerce'); ?></span>
                                        </div>
                                        <label class="bc-switch">
                                            <input type="checkbox" name="bc_enable_notifications" value="yes" <?php checked($enable_notif, 'yes'); ?>>
                                            <span class="bc-slider"></span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label style="display:block; color:#fff; font-weight:700; font-size:15px; margin-bottom:10px;">
                                        <?php _e('Cancellation Policy', 'boocommerce'); ?>
                                        <span class="bc-info-icon" data-tooltip="<?php esc_attr_e('This legal text will be prominently displayed in all booking confirmation emails and receipts sent to customers.', 'boocommerce'); ?>">?</span>
                                    </label>
                                    <textarea name="bc_cancellation_policy" rows="3" style="width:100%; background:#0f172a; color:#fff; border:1px solid var(--bc-border); padding:15px; border-radius:8px; line-height:1.5;"><?php echo esc_textarea($policy); ?></textarea>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Save Actions -->
                    <div class="bc-settings-action-bar">
                        <button type="submit" name="bc_save_settings" class="bc-btn-primary" style="flex:2; padding:15px; font-size:16px; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);"><?php _e('Save Settings', 'boocommerce'); ?></button>
                        <button type="submit" name="bc_restore_defaults" id="bc-restore-defaults-btn" class="bc-btn" style="flex:1; background:rgba(255,255,255,0.05); color:#94a3b8; border:1px solid rgba(255,255,255,0.1); padding:15px; font-size:14px; font-weight:700;"><?php _e('Restore Defaults', 'boocommerce'); ?></button>
                    </div>

                </div>
            </form>
        </div>
        <?php
    }

}
