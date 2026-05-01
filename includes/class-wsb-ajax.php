<?php
class Wsb_Ajax {
    public function get_time_slots() {
        check_ajax_referer('wsb_nonce', 'nonce');
        global $wpdb;

        $staff_id = isset($_POST['staff_id']) ? sanitize_text_field($_POST['staff_id']) : 'any';
        $date     = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');
        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;

        // Holiday Check
        if ($staff_id !== 'any') {
            $staff = $wpdb->get_row($wpdb->prepare("SELECT holidays FROM {$wpdb->prefix}wsb_staff WHERE id = %d", intval($staff_id)));
            if ($staff && !empty($staff->holidays)) {
                $holidays = array_map('trim', explode("\n", $staff->holidays));
                // Remove empty lines and sanitize
                $holidays = array_filter($holidays);
                
                if (in_array($date, $holidays)) {
                    wp_send_json_success(array('slots' => array(), 'message' => 'Staff is currently on holiday/time-off.'));
                    return;
                }
            }
        }

        // 1. Define all possible slots (e.g., 09:00 to 17:00 every 30 mins)
        // In a real app, these might come from staff working hours.
        $all_slots = array(
            '09:00:00', '09:30:00', '10:00:00', '10:30:00', '11:00:00', '11:30:00',
            '12:00:00', '12:30:00', '13:00:00', '13:30:00', '14:00:00', '14:30:00',
            '15:00:00', '15:30:00', '16:00:00', '16:30:00', '17:00:00'
        );

        // 2. Fetch existing bookings for this staff and date
        $booking_table = $wpdb->prefix . 'wsb_bookings';
        $query = "SELECT start_time FROM $booking_table WHERE booking_date = %s AND status IN ('confirmed', 'completed')";
        $params = array($date);

        if ($staff_id !== 'any') {
            $query .= " AND staff_id = %d";
            $params[] = intval($staff_id);
        }

        $booked_slots = $wpdb->get_col($wpdb->prepare($query, ...$params));

        // 3. Filter out booked slots
        $available_slots = array();
        foreach ($all_slots as $slot) {
            if (!in_array($slot, $booked_slots)) {
                // Format for display (HH:MM)
                $available_slots[] = date('H:i', strtotime($slot));
            }
        }

        wp_send_json_success(array('slots' => $available_slots));
    }

    public function create_booking() {
        $booking_id = $this->internal_create_booking($_POST);
        if ($booking_id) {
            wp_send_json_success(array('message' => 'Booking #' . $booking_id . ' confirmed & saved!', 'booking_id' => $booking_id));
        } else {
            wp_send_json_error(array('message' => 'Failed to create booking.'));
        }
    }

    public function internal_create_booking($data) {
        global $wpdb;
        $first_name = sanitize_text_field($data['first_name']);
        $last_name = sanitize_text_field($data['last_name']);
        $email = sanitize_email($data['email']);
        $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';

        // Automatically create User Account if does not exist
        $user_id = email_exists($email);
        $generated_password = '';
        $created_account = false;

        if (!$user_id) {
            $username = sanitize_user(current(explode('@', $email)));
            if (username_exists($username)) {
                $username .= time();
            }
            $generated_password = wp_generate_password(12, false);
            
            $user_id = wp_create_user($username, $generated_password, $email);
            if (!is_wp_error($user_id)) {
                wp_update_user(array(
                    'ID' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => $first_name . ' ' . $last_name,
                    'role' => 'subscriber'
                ));
                $created_account = true;
            }
        }

        // Check if customer exists
        $customer_table = $wpdb->prefix . 'wsb_customers';
        $customer = $wpdb->get_row($wpdb->prepare("SELECT id FROM $customer_table WHERE email = %s", $email));
        
        if ($customer) {
            $customer_id = $customer->id;
        } else {
            $wpdb->insert($customer_table, array('first_name' => $first_name, 'last_name' => $last_name, 'email' => $email));
            $customer_id = $wpdb->insert_id;
        }

        // Create booking
        $booking_table = $wpdb->prefix . 'wsb_bookings';
        $service_id     = isset($data['service_id']) ? intval($data['service_id']) : 0;
        $staff_id       = isset($data['staff_id']) ? sanitize_text_field($data['staff_id']) : 'any';
        $booking_date   = isset($data['booking_date']) ? sanitize_text_field($data['booking_date']) : date('Y-m-d');
        $start_time     = isset($data['start_time']) ? sanitize_text_field($data['start_time']) : '09:00';
        $payment_method = isset($data['payment_method']) ? sanitize_text_field($data['payment_method']) : 'manual';
        $status         = isset($data['status']) ? sanitize_text_field($data['status']) : 'confirmed';

        $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsb_services WHERE id = %d", $service_id));
        $price = $service ? floatval($service->price) : 0.00;

        if ($staff_id === 'any') {
            $any_staff = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wsb_staff WHERE status = 'active' LIMIT 1");
            $staff_id = $any_staff ? intval($any_staff) : 1;
        } else {
            $staff_id = intval($staff_id);
        }

        $end_time = date('H:i:s', strtotime($start_time) + 1800);

        $wpdb->insert($booking_table, array(
            'customer_id' => $customer_id,
            'service_id' => $service_id,
            'staff_id' => $staff_id,
            'booking_date' => $booking_date,
            'start_time' => date('H:i:s', strtotime($start_time)),
            'end_time' => $end_time,
            'status' => $status,
            'total_amount' => $price
        ));
        $booking_id = $wpdb->insert_id;

        if ($booking_id && $status === 'confirmed') {
            $payment_table = $wpdb->prefix . 'wsb_payments';
            $wpdb->insert($payment_table, array(
                'booking_id' => $booking_id,
                'amount' => $price,
                'gateway' => $payment_method,
                'status' => ($payment_method === 'stripe') ? 'completed' : 'pending'
            ));

            // Mail notification (only for confirmed)
            $mail_subject = 'Booking Confirmed & Your Account Details';
            $mail_body = "Hello " . esc_html($first_name) . ",\n\n";
            $mail_body .= "Thank you for your booking! We've securely scheduled your appointment (#$booking_id).\n\n";
            
            if ($created_account) {
                $mail_body .= "An automated client dashboard profile was established securely for you.\n";
                $mail_body .= "Login Portal: " . wp_login_url() . "\n";
                $mail_body .= "Username: " . esc_html($email) . "\n";
                $mail_body .= "Temporary Password: " . esc_html($generated_password) . "\n\n";
            }
            
            $mail_body .= "Best Regards.";
            wp_mail($email, $mail_subject, $mail_body);
        }

        return $booking_id;
    }

    public function wsb_client_booking_action() {
        global $wpdb;
        
        if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsb_nonce')){
            wp_send_json_error(array('message' => 'Security verification failed.'));
        }
        
        if(!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Access Denied. Please login.'));
        }
        
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $client_action = isset($_POST['client_action']) ? sanitize_text_field($_POST['client_action']) : '';
        
        if(!$booking_id || !in_array($client_action, array('cancel', 'reschedule'))) {
            wp_send_json_error(array('message' => 'Invalid request parameters.'));
        }
        
        $booking_table = $wpdb->prefix . 'wsb_bookings';
        $wpdb->query("ALTER TABLE {$booking_table} ADD COLUMN IF NOT EXISTS request_type VARCHAR(50) DEFAULT NULL");
        
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $booking_table WHERE id = %d", $booking_id));
        
        if(!$booking) {
            wp_send_json_error(array('message' => 'Booking record not found.'));
        }
        
        $admin_email = get_option('admin_email');
        $current_user = wp_get_current_user();
        
        if ($client_action === 'cancel') {
            $wpdb->update($booking_table, array('status' => 'pending', 'request_type' => 'cancel'), array('id' => $booking_id));
            
            $admin_subject = "[Action Required] Cancellation Request: Appointment #$booking_id";
            $admin_body = "Client " . esc_html($current_user->display_name) . " requests to cancel appointment #$booking_id.\nPlease approve or decline via administrator modules.";
            wp_mail($admin_email, $admin_subject, $admin_body);
            
            $client_subject = "Cancellation Request Logged - Appointment #$booking_id";
            $client_body = "Hello " . esc_html($current_user->display_name) . ",\n\n";
            $client_body .= "We have successfully received your request to cancel appointment #$booking_id.\n\n";
            $client_body .= "Our staff team will review and process this shortly.\n\nBest Regards.";
            wp_mail($current_user->user_email, $client_subject, $client_body);
            
            wp_send_json_success(array('message' => 'Cancellation request submitted successfully!'));
        } else {
            $reschedule_staff = isset($_POST['reschedule_staff']) ? intval($_POST['reschedule_staff']) : 0;
            $reschedule_date  = isset($_POST['reschedule_date']) ? sanitize_text_field($_POST['reschedule_date']) : '';
            $reschedule_time  = isset($_POST['reschedule_time']) ? sanitize_text_field($_POST['reschedule_time']) : '';
            
            if (!$reschedule_staff || !$reschedule_date || !$reschedule_time) {
                wp_send_json_error(array('message' => 'Please fill all fields correctly.'));
            }
            
            $wpdb->query("ALTER TABLE {$booking_table} ADD COLUMN IF NOT EXISTS requested_date DATE DEFAULT NULL");
            $wpdb->query("ALTER TABLE {$booking_table} ADD COLUMN IF NOT EXISTS requested_time TIME DEFAULT NULL");
            $wpdb->query("ALTER TABLE {$booking_table} ADD COLUMN IF NOT EXISTS requested_staff_id BIGINT(20) DEFAULT NULL");

            $wpdb->update($booking_table, array(
                'status' => 'pending',
                'request_type' => 'reschedule',
                'requested_date' => $reschedule_date,
                'requested_time' => $reschedule_time,
                'requested_staff_id' => $reschedule_staff
            ), array('id' => $booking_id));
            
            $admin_subject = "[Alert] Reschedule Request: Appointment #$booking_id";
            $admin_body = "Client " . esc_html($current_user->display_name) . " rescheduled appointment #$booking_id to $reschedule_date at $reschedule_time.";
            wp_mail($admin_email, $admin_subject, $admin_body);
            
            $client_subject = "Reschedule Request Logged - Appointment #$booking_id";
            $client_body = "Hello " . esc_html($current_user->display_name) . ",\n\n";
            $client_body .= "We have successfully received your request to reschedule appointment #$booking_id to $reschedule_date at $reschedule_time.\n\n";
            $client_body .= "Our administrative team will verify and process the request shortly.\n\nBest Regards.";
            wp_mail($current_user->user_email, $client_subject, $client_body);
            
            wp_send_json_success(array('message' => 'Reschedule request logged for administrative review!'));
        }
    }

    public function wsb_update_account_details() {
        global $wpdb;
        
        if(!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wsb_nonce')){
            wp_send_json_error(array('message' => 'Security verification failed.'));
        }
        
        if(!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Access Denied. Please login.'));
        }
        
        $current_user = wp_get_current_user();
        
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name  = sanitize_text_field($_POST['last_name']);
        $phone      = sanitize_text_field($_POST['phone']);
        $address    = sanitize_textarea_field($_POST['address']);
        $password   = sanitize_text_field($_POST['password']);
        
        if (empty($first_name) || empty($last_name)) {
            wp_send_json_error(array('message' => 'First and Last name are required fields.'));
        }
        
        wp_update_user(array(
            'ID' => $current_user->ID,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $first_name . ' ' . $last_name
        ));
        
        update_user_meta($current_user->ID, 'wsb_client_phone', $phone);
        update_user_meta($current_user->ID, 'wsb_client_address', $address);
        
        if (!empty($password)) {
            if (strlen($password) < 6) {
                wp_send_json_error(array('message' => 'Password must be at least 6 characters long.'));
            }
            wp_set_password($password, $current_user->ID);
        }
        
        $customer_table = $wpdb->prefix . 'wsb_customers';
        $wpdb->update(
            $customer_table, 
            array('first_name' => $first_name, 'last_name' => $last_name, 'phone' => $phone), 
            array('email' => $current_user->user_email)
        );
        
        wp_send_json_success(array('message' => 'Account details successfully updated!'));
    }

    public function create_checkout_session() {
        check_ajax_referer('wsb_nonce', 'nonce');
        global $wpdb;

        $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name  = sanitize_text_field($_POST['last_name']);
        $email      = sanitize_email($_POST['email']);
        $phone      = sanitize_text_field($_POST['phone']);
        $staff_id   = isset($_POST['staff_id']) ? sanitize_text_field($_POST['staff_id']) : 'any';
        $date       = sanitize_text_field($_POST['booking_date']);
        $time       = sanitize_text_field($_POST['start_time']);

        if (!$service_id || !$email) {
            wp_send_json_error(array('message' => 'Missing required booking details.'));
        }

        $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsb_services WHERE id = %d", $service_id));
        if (!$service) {
            wp_send_json_error(array('message' => 'Service not found.'));
        }

        $stripe_sk = get_option('wsb_stripe_secret_key', '');
        if (empty($stripe_sk)) {
            wp_send_json_error(array('message' => 'Stripe is not configured.'));
        }

        // Create Pending Booking (Reuse core logic but with 'pending' status)
        $booking_id = $this->internal_create_booking(array(
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'email'          => $email,
            'phone'          => $phone,
            'service_id'     => $service_id,
            'staff_id'       => $staff_id,
            'booking_date'   => $date,
            'start_time'     => $time,
            'payment_method' => 'stripe',
            'status'         => 'pending' // Important: pending until paid
        ));

        if (!$booking_id) {
            wp_send_json_error(array('message' => 'Failed to log pending booking.'));
        }

        $amount_in_cents = intval($service->price * 100);
        $currency = strtolower(get_option('wsb_currency', 'gbp'));

        $success_url = add_query_arg(array(
            'wsb_checkout' => 'success',
            'booking_id'   => $booking_id,
            'session_id'   => '{CHECKOUT_SESSION_ID}'
        ), home_url('/'));

        $cancel_url = add_query_arg(array(
            'wsb_checkout' => 'cancelled'
        ), home_url('/booking'));

        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $stripe_sk,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => http_build_query(array(
                'mode' => 'payment',
                'line_items' => array(
                    array(
                        'price_data' => array(
                            'currency' => $currency,
                            'product_data' => array(
                                'name' => $service->name,
                                'description' => 'Professional booking for ' . $date . ' at ' . $time,
                            ),
                            'unit_amount' => $amount_in_cents,
                        ),
                        'quantity' => 1,
                    ),
                ),
                'customer_email' => $email,
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'metadata' => array(
                    'service_id'   => $service_id,
                    'staff_id'     => $staff_id,
                    'booking_date' => $date,
                    'start_time'   => $time,
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'phone'        => $phone
                )
            ))
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Stripe Error: ' . $response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            wp_send_json_error(array('message' => $data['error']['message']));
        }

        wp_send_json_success(array('url' => $data['url']));
    }

    public function create_stripe_intent() {
        // Deprecated in favor of Checkout Sessions
        $this->create_checkout_session();
    }
    
    public function test_stripe_connection() {
        check_ajax_referer('wsb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied.'));
        }

        $stripe_sk = isset($_POST['stripe_sk']) ? sanitize_text_field($_POST['stripe_sk']) : '';
        if (empty($stripe_sk)) {
            wp_send_json_error(array('message' => 'Please enter a valid Stripe Secret Key to test.'));
        }

        $response = wp_remote_get('https://api.stripe.com/v1/balance', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $stripe_sk,
            ),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'API request failed: ' . $response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            wp_send_json_error(array('message' => 'Connection Failed: ' . $data['error']['message']));
        }

        wp_send_json_success(array('message' => 'Connection Successful! Credentials validated securely.'));
    }
}
