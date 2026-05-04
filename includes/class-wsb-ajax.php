<?php
class Wsb_Ajax {
    public function get_time_slots() {
        check_ajax_referer('wsb_nonce', 'nonce');
        global $wpdb;

        $staff_id = isset($_POST['staff_id']) ? sanitize_text_field($_POST['staff_id']) : 'any';
        $date     = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');
        $service_ids = isset($_POST['service_id']) ? sanitize_text_field($_POST['service_id']) : '';
        $booking_id  = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

        if (empty($service_ids) && $booking_id) {
            $service_ids = $wpdb->get_var($wpdb->prepare("SELECT service_id FROM {$wpdb->prefix}wsb_bookings WHERE id = %d", $booking_id));
        }

        // Calculate total duration for slot filtering
        $total_duration = 30; // Default
        if (!empty($service_ids)) {
            $ids = array_map('intval', explode(',', $service_ids));
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $services = $wpdb->get_results($wpdb->prepare("SELECT duration FROM {$wpdb->prefix}wsb_services WHERE id IN ($placeholders)", $ids));
            if ($services) {
                $total_duration = 0;
                foreach ($services as $s) {
                    $total_duration += intval($s->duration);
                }
            }
        }

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
        } else if (get_option('wsb_filter_staff_by_service', 'no') === 'yes' && !empty($service_ids)) {
            // If "Any" is selected and filtering is enabled, we should ideally check against ANY eligible staff
            // But for conflict checking, we only care about staff who are actually eligible.
            $ids = array_map('intval', explode(',', $service_ids));
            $count = count($ids);
            $placeholders = implode(',', array_fill(0, $count, '%d'));
            
            $eligible_staff = $wpdb->get_col($wpdb->prepare(
                "SELECT staff_id FROM {$wpdb->prefix}wsb_staff_services WHERE service_id IN ($placeholders) GROUP BY staff_id HAVING COUNT(*) = %d",
                array_merge($ids, array($count))
            ));
            
            if (!empty($eligible_staff)) {
                $query .= " AND staff_id IN (" . implode(',', array_map('intval', $eligible_staff)) . ")";
            }
        }

        $booked_slots = $wpdb->get_col($wpdb->prepare($query, ...$params));

        // 3. Filter out booked slots (Account for total duration)
        $available_slots = array();
        $duration_seconds = $total_duration * 60;

        foreach ($all_slots as $slot) {
            $slot_start = strtotime($date . ' ' . $slot);
            $slot_end = $slot_start + $duration_seconds;
            
            $is_booked = false;
            foreach ($booked_slots as $booked_start) {
                $b_start = strtotime($date . ' ' . $booked_start);
                // Simple check: if slot overlaps with any booking
                // For a more robust app, we'd check against actual booking end_times.
                // Assuming 30-min increments for now.
                if ($slot_start == $b_start) {
                    $is_booked = true;
                    break;
                }
            }

            if (!$is_booked) {
                // Check if it fits in working hours (simplified)
                if (date('H:i', $slot_end) <= '18:00') {
                    $available_slots[] = date('H:i', $slot_start);
                }
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
            // Robust Username Generation
            $username_base = sanitize_user(current(explode('@', $email)));
            $username = $username_base;
            $suffix = 1;
            while (username_exists($username)) {
                $username = $username_base . $suffix;
                $suffix++;
            }
            
            $generated_password = wp_generate_password(14, false);
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
            } else {
                error_log('WSB User Creation Error: ' . $user_id->get_error_message());
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
        $service_ids    = isset($data['service_id']) ? sanitize_text_field($data['service_id']) : '';
        $staff_id       = isset($data['staff_id']) ? sanitize_text_field($data['staff_id']) : 'any';
        $booking_date   = isset($data['booking_date']) ? sanitize_text_field($data['booking_date']) : date('Y-m-d');
        $start_time     = isset($data['start_time']) ? sanitize_text_field($data['start_time']) : '09:00';
        $payment_method = isset($data['payment_method']) ? sanitize_text_field($data['payment_method']) : 'manual';
        $status         = isset($data['status']) ? sanitize_text_field($data['status']) : 'confirmed';

        $total_price = 0.00;
        $total_duration = 0;
        if (!empty($service_ids)) {
            $ids = array_map('intval', explode(',', $service_ids));
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $services = $wpdb->get_results($wpdb->prepare("SELECT price, duration FROM {$wpdb->prefix}wsb_services WHERE id IN ($placeholders)", $ids));
            foreach ($services as $s) {
                $total_price += floatval($s->price);
                $total_duration += intval($s->duration);
            }
        }

        if ($staff_id === 'any') {
            if (get_option('wsb_filter_staff_by_service', 'no') === 'yes' && !empty($service_ids)) {
                $ids = array_map('intval', explode(',', $service_ids));
                $count = count($ids);
                $placeholders = implode(',', array_fill(0, $count, '%d'));
                $any_staff = $wpdb->get_var($wpdb->prepare(
                    "SELECT staff_id FROM {$wpdb->prefix}wsb_staff_services WHERE service_id IN ($placeholders) GROUP BY staff_id HAVING COUNT(*) = %d LIMIT 1",
                    array_merge($ids, array($count))
                ));
                $staff_id = $any_staff ? intval($any_staff) : $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wsb_staff WHERE status = 'active' LIMIT 1");
            } else {
                $any_staff = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wsb_staff WHERE status = 'active' LIMIT 1");
                $staff_id = $any_staff ? intval($any_staff) : 1;
            }
        } else {
            $staff_id = intval($staff_id);
        }
        if ($customer) {
            $customer_id = $customer->id;
        } else {
            $wpdb->insert($customer_table, array('first_name' => $first_name, 'last_name' => $last_name, 'email' => $email));
            $customer_id = $wpdb->insert_id;
        }

        // Send Welcome Email immediately if account was created (Guest -> User)
        if ($created_account) {
            $welcome_subject = 'Welcome to ' . get_bloginfo('name') . ' - Your Secure Client Portal';
            $welcome_content = '
            <div class="info-card" style="text-align:center;">
                <div style="font-size:12px; text-transform:uppercase; color:#6366f1; font-weight:800; letter-spacing:0.1em; margin-bottom:15px;">Security Credentials</div>
                <div style="background:#ffffff; border:1px solid #e2e8f0; padding:25px; border-radius:16px; display:inline-block; text-align:left; min-width:250px;">
                    <div style="margin-bottom:12px;"><strong style="color:#64748b;">Username:</strong> <span style="font-family:monospace; color:#0f172a; font-weight:600;">' . $email . '</span></div>
                    <div><strong style="color:#64748b;">Temp Password:</strong> <span style="font-family:monospace; color:#0f172a; font-weight:600;">' . $generated_password . '</span></div>
                </div>
                <br>
                <a href="' . wp_login_url() . '" class="btn-primary">Access Your Secure Portal</a>
            </div>';
            
            wsb_send_modern_email($email, $welcome_subject, 'Identity Verified', "Hello $first_name, we've established a secure client profile for you to manage your appointments.", $welcome_content);
        }

        // 3. Process Booking
        $service_ids = sanitize_text_field($data['service_id']);
        $total_duration = 0;
        $total_price = 0.00;
        if (!empty($service_ids)) {
            $ids = array_map('intval', explode(',', $service_ids));
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $services = $wpdb->get_results($wpdb->prepare("SELECT price, duration FROM {$wpdb->prefix}wsb_services WHERE id IN ($placeholders)", $ids));
            foreach ($services as $s) {
                $total_price += floatval($s->price);
                $total_duration += intval($s->duration);
            }
        }
        
        $end_time = date('H:i:s', strtotime($start_time) + ($total_duration * 60));

        $booking_table = $wpdb->prefix . 'wsb_bookings';
        $wpdb->insert($booking_table, array(
            'customer_id' => $customer_id,
            'service_id' => $service_ids,
            'staff_id' => $staff_id,
            'booking_date' => $booking_date,
            'start_time' => date('H:i:s', strtotime($start_time)),
            'end_time' => $end_time,
            'status' => $status,
            'total_amount' => $total_price
        ));
        $booking_id = $wpdb->insert_id;

        if ($booking_id) {
            // Log Payment if confirmed
            if ($status === 'confirmed') {
                $payment_table = $wpdb->prefix . 'wsb_payments';
                $transaction_id = isset($data['transaction_id']) ? sanitize_text_field($data['transaction_id']) : null;
                $wpdb->insert($payment_table, array(
                    'booking_id' => $booking_id,
                    'amount' => $total_price,
                    'gateway' => $payment_method,
                    'status' => ($payment_method === 'stripe' || $payment_method === 'paypal') ? 'completed' : 'pending',
                    'transaction_id' => $transaction_id
                ));
            }

            // Booking Receipt Email (Sent for BOTH confirmed and pending)
            $email_title = ($status === 'confirmed') ? 'Booking Secured' : 'Booking Received';
            $subject = $email_title . ': Appointment #' . $booking_id;
            $intro_text = ($status === 'confirmed') 
                ? "Hello $first_name, your appointment has been successfully registered and confirmed." 
                : "Hello $first_name, we have received your booking request. Our team will review it and confirm shortly.";

            $details_html = '
                <div class="info-card">
                    <div style="margin-bottom:12px; font-size:15px;"><strong style="color:#64748b;">ID:</strong> <span style="color:#0f172a; font-weight:600;">#' . $booking_id . '</span></div>
                    <div style="margin-bottom:12px; font-size:15px;"><strong style="color:#64748b;">Date:</strong> <span style="color:#0f172a; font-weight:600;">' . $booking_date . '</span></div>
                    <div style="margin-bottom:12px; font-size:15px;"><strong style="color:#64748b;">Time:</strong> <span style="color:#0f172a; font-weight:600;">' . $start_time . '</span></div>
                    <div style="font-size:15px;"><strong style="color:#64748b;">Status:</strong> <span style="color:' . ($status === 'confirmed' ? '#10b981' : '#6366f1') . '; font-weight:800; text-transform:uppercase;">' . $status . '</span></div>
                </div>';

            // Add Cancellation Policy
            $cancellation_policy = get_option('wsb_cancellation_policy', 'Cancellations must be made at least 24 hours in advance.');
            $details_html .= '
                <div style="margin-top:25px; padding:20px; background:#fff1f2; border:1px solid #fecdd3; border-radius:16px;">
                    <div style="font-size:11px; text-transform:uppercase; color:#e11d48; font-weight:800; margin-bottom:8px; letter-spacing:0.05em;">Cancellation Policy</div>
                    <div style="font-size:13px; color:#9f1239; line-height:1.5;">' . wp_kses_post($cancellation_policy) . '</div>
                </div>';

            wsb_send_modern_email($email, $subject, $email_title, $intro_text, $details_html);
            
            // Notify Admin
            $admin_email = get_option('admin_email');
            $admin_details = '
                <div class="info-card">
                    <strong style="color:#64748b;">Client:</strong> ' . $first_name . ' ' . $last_name . '<br>
                    <strong style="color:#64748b;">Email:</strong> ' . $email . '<br>
                    <strong style="color:#64748b;">Status:</strong> ' . strtoupper($status) . '
                </div>';
            wsb_send_modern_email($admin_email, 'New Lead: Booking #' . $booking_id, 'Lead Captured', "A new reservation has been logged in the system.", $admin_details);
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
            
            // Admin Notification
            $admin_subject = "[Action Required] Cancellation Request: #" . $booking_id;
            $admin_details = '<div style="background:#fef2f2; padding:20px; border-radius:12px; border:1px solid #fee2e2;">
                <strong>Client Name:</strong> ' . $current_user->display_name . '<br>
                <strong>Booking ID:</strong> #' . $booking_id . '
            </div>';
            wsb_send_modern_email($admin_email, $admin_subject, 'Cancellation Request', 'A client has requested to cancel their scheduled appointment.', $admin_details);
            
            // Client Notification
            $client_subject = "Cancellation Request Logged: #" . $booking_id;
            $client_content = '<p>Your request to cancel appointment #' . $booking_id . ' has been logged. Our team will review this shortly and update your status.</p>';
            wsb_send_modern_email($current_user->user_email, $client_subject, 'Request Received', "Hello " . $current_user->display_name . ",", $client_content);
            
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
            $wpdb->query("ALTER TABLE {$booking_table} ADD COLUMN IF NOT EXISTS requested_staff_id INT DEFAULT NULL");

            $wpdb->update($booking_table, array(
                'status' => 'pending',
                'request_type' => 'reschedule',
                'requested_date' => $reschedule_date,
                'requested_time' => $reschedule_time,
                'requested_staff_id' => $reschedule_staff
            ), array('id' => $booking_id));
            
            // Admin Notification
            $admin_subject = "[Action Required] Reschedule Request: #" . $booking_id;
            $admin_details = '<div style="background:#f0f9ff; padding:20px; border-radius:12px; border:1px solid #e0f2fe;">
                <strong>Client:</strong> ' . $current_user->display_name . '<br>
                <strong>Requested Date:</strong> ' . $reschedule_date . '<br>
                <strong>Requested Time:</strong> ' . $reschedule_time . '
            </div>';
            wsb_send_modern_email($admin_email, $admin_subject, 'Reschedule Requested', 'A client has requested to move their appointment.', $admin_details);
            
            // Client Notification
            $client_subject = "Reschedule Request Logged: #" . $booking_id;
            $client_content = '<p>We have received your request to reschedule appointment #' . $booking_id . '. Our team will check availability and confirm the change shortly.</p>';
            wsb_send_modern_email($current_user->user_email, $client_subject, 'Request Received', "Hello " . $current_user->display_name . ",", $client_content);

            wp_send_json_success(array('message' => 'Reschedule request submitted successfully!'));
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

        $service_ids_str = isset($_POST['service_id']) ? sanitize_text_field($_POST['service_id']) : '';
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name  = sanitize_text_field($_POST['last_name']);
        $email      = sanitize_email($_POST['email']);
        $phone      = sanitize_text_field($_POST['phone']);
        $staff_id   = isset($_POST['staff_id']) ? sanitize_text_field($_POST['staff_id']) : 'any';
        $date       = sanitize_text_field($_POST['booking_date']);
        $time       = sanitize_text_field($_POST['start_time']);

        if (empty($service_ids_str) || !$email) {
            wp_send_json_error(array('message' => 'Missing required booking details.'));
        }

        $ids = array_map('intval', explode(',', $service_ids_str));
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $services = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}wsb_services WHERE id IN ($placeholders)", $ids));
        
        if (empty($services)) {
            wp_send_json_error(array('message' => 'Services not found.'));
        }

        $stripe_sk = get_option('wsb_stripe_secret_key', '');
        if (empty($stripe_sk)) {
            wp_send_json_error(array('message' => 'Stripe is not configured.'));
        }

        // Create Pending Booking
        $booking_id = $this->internal_create_booking(array(
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'email'          => $email,
            'phone'          => $phone,
            'service_id'     => $service_ids_str,
            'staff_id'       => $staff_id,
            'booking_date'   => $date,
            'start_time'     => $time,
            'payment_method' => 'stripe',
            'status'         => 'pending' 
        ));

        if (!$booking_id) {
            wp_send_json_error(array('message' => 'Failed to log pending booking.'));
        }

        $currency = strtolower(get_option('wsb_currency', 'gbp'));

        $line_items = array();
        foreach ($services as $s) {
            $line_items[] = array(
                'price_data' => array(
                    'currency' => $currency,
                    'product_data' => array(
                        'name' => $s->name,
                        'description' => 'Professional booking for ' . $date . ' at ' . $time,
                    ),
                    'unit_amount' => intval($s->price * 100),
                ),
                'quantity' => 1,
            );
        }

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
                'line_items' => $line_items,
                'customer_email' => $email,
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'metadata' => array(
                    'service_id'   => $service_ids_str,
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
        check_ajax_referer('wsb_nonce', 'nonce');
        global $wpdb;

        $service_ids_str = isset($_POST['service_id']) ? sanitize_text_field($_POST['service_id']) : '';
        if (empty($service_ids_str)) {
            wp_send_json_error(array('message' => 'Services not selected.'));
        }

        $ids = array_map('intval', explode(',', $service_ids_str));
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $services = $wpdb->get_results($wpdb->prepare("SELECT price FROM {$wpdb->prefix}wsb_services WHERE id IN ($placeholders)", $ids));
        
        if (empty($services)) {
            wp_send_json_error(array('message' => 'Services not found.'));
        }

        $stripe_sk = get_option('wsb_stripe_secret_key', '');
        if (empty($stripe_sk)) {
            wp_send_json_error(array('message' => 'Stripe is not configured.'));
        }

        $total_amount = 0;
        foreach ($services as $s) {
            $total_amount += floatval($s->price);
        }

        $amount_in_cents = intval($total_amount * 100);
        $currency = strtolower(get_option('wsb_currency', 'usd'));

        $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $stripe_sk,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body' => http_build_query(array(
                'amount'   => $amount_in_cents,
                'currency' => $currency,
                'payment_method_types' => array('card'),
                'metadata' => array(
                    'service_id' => $service_ids_str
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

        wp_send_json_success(array('client_secret' => $data['client_secret']));
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
