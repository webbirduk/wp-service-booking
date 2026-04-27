<?php
class Wsb_Public {
    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/wsb-public.css', array(), time(), 'all' );
    }

    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/wsb-public.js', array( 'jquery' ), time(), true );
        wp_localize_script( $this->plugin_name, 'wsb_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wsb_nonce' )
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
                <h3>1. Select a Service</h3>
                
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
                                <span class="wsb-price-tag">$<?php echo esc_html($s->price); ?></span>
                            </div>
                            <?php if($layout === 'elegant_wide' || strpos($layout, 'detail') !== false): ?>
                                <p class="wsb-service-desc"><?php echo esc_html(wp_trim_words($s->description, 15)); ?></p>
                            <?php endif; ?>
                        </div>
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
                <div style="margin-bottom:20px;">
                    <input type="date" id="wsb-booking-date" style="padding:12px; border:1px solid #e2e8f0; border-radius:8px; width:100%; font-size:16px;" min="<?php echo date('Y-m-d'); ?>" />
                </div>
                <div class="wsb-time-slots">
                    <p style="text-align:center; padding:20px; color:#64748b;">Select a date to view available times.</p>
                </div>
                <div class="wsb-actions">
                    <button class="wsb-prev-btn wsb-btn" data-prev="wsb-step-staff">Back</button>
                    <button class="wsb-next-btn wsb-btn" data-next="wsb-step-details" disabled>Next Step</button>
                </div>
            </div>

            <div class="wsb-wizard-step" id="wsb-step-details" style="display:none;">
                <h3>4. Your Details</h3>
                <div class="wsb-form-container">
                    <div class="wsb-form-grid">
                        <div class="wsb-field-wrap">
                            <label>First Name</label>
                            <input type="text" placeholder="e.g. John" id="wsb-first-name" required />
                        </div>
                        <div class="wsb-field-wrap">
                            <label>Last Name</label>
                            <input type="text" placeholder="e.g. Doe" id="wsb-last-name" required />
                        </div>
                    </div>
                    
                    <div class="wsb-field-wrap">
                        <label>Email Address</label>
                        <input type="email" placeholder="john.doe@example.com" id="wsb-email" required />
                    </div>
                    
                    <div class="wsb-field-wrap">
                        <label>Phone Number</label>
                        <input type="tel" placeholder="+1 (555) 000-0000" id="wsb-phone" />
                    </div>
                    
                    <div class="wsb-field-wrap">
                        <label>Additional Notes</label>
                        <textarea placeholder="Any special requests or details we should know?" rows="4" id="wsb-notes"></textarea>
                    </div>
                </div>
                
                <div class="wsb-actions">
                    <button class="wsb-prev-btn wsb-btn" data-prev="wsb-step-time">Back</button>
                    <button class="wsb-next-btn wsb-btn" data-next="wsb-step-payment">Complete Details</button>
                </div>
            </div>

            <div class="wsb-wizard-step" id="wsb-step-payment" style="display:none;">
                <h3>5. Confirm & Pay</h3>
                <div class="wsb-summary-card">
                    <h4 style="margin-top:0; font-size: 20px;">Secure Checkout</h4>
                    <div style="margin-bottom:20px; padding-bottom:20px; border-bottom:1px solid var(--wsb-border);">
                        <p id="wsb-summary-service" style="margin:0; font-weight:700; font-size: 18px;"></p>
                        <p id="wsb-summary-time" style="margin:8px 0 0; font-size:15px; color:var(--wsb-text-muted);"></p>
                    </div>
                    <label class="wsb-payment-method">
                        <input type="radio" name="payment_method" value="manual" checked> 
                        <span style="margin-left:10px;">Pay in Person on Arrival</span>
                    </label>
                    <label class="wsb-payment-method" style="opacity:0.5; cursor: not-allowed;">
                        <input type="radio" name="payment_method" value="stripe" disabled>
                        <span style="margin-left:10px;">Credit Card (Stripe)</span>
                        <span style="display:block; font-size:12px; margin-left: 24px; color:var(--wsb-text-muted);">Coming Soon</span>
                    </label>
                </div>
                <div class="wsb-actions">
                    <button class="wsb-prev-btn wsb-btn" data-prev="wsb-step-details">Back</button>
                    <button class="wsb-submit-btn wsb-btn" id="wsb-confirm-booking">Confirm Booking</button>
                </div>
            </div>
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

        if ( $slug === 'booking' ) {
            status_header( 200 );
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo( 'charset' ); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php wp_head(); ?>
                <style>
                    body { background: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; margin: 0; }
                    .wsb-virtual-page { background: white; width: 100%; max-width: 800px; padding: 40px; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
                </style>
            </head>
            <body>
                <div class="wsb-virtual-page">
                    <?php echo $this->render_booking_widget( array() ); ?>
                </div>
                <?php wp_footer(); ?>
            </body>
            </html>
            <?php
            exit;
        }
    }
}
