<?php
class Wsb_Ajax {
    public function get_time_slots() {
        // Logic to get time slots from DB for a given date, staff, service
        wp_send_json_success(array('slots' => array('09:00', '10:00', '11:00', '14:00', '15:00')));
    }

    public function create_booking() {
        global $wpdb;
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $email = sanitize_email($_POST['email']);

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
        $wpdb->insert($booking_table, array(
            'customer_id' => $customer_id,
            'service_id' => 1, // mock service_id for now
            'staff_id' => 1, // mock staff_id
            'booking_date' => date('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => 'confirmed',
            'total_amount' => 50.00
        ));
        $booking_id = $wpdb->insert_id;

        // Payment record
        $payment_table = $wpdb->prefix . 'wsb_payments';
        $wpdb->insert($payment_table, array(
            'booking_id' => $booking_id,
            'amount' => 50.00,
            'gateway' => 'manual',
            'status' => 'pending'
        ));

        // Mail notification (mocked)
        wp_mail($email, 'Booking Confirmed', "Your booking #$booking_id has been confirmed.");

        wp_send_json_success(array('message' => 'Booking #' . $booking_id . ' confirmed & saved to database!', 'booking_id' => $booking_id));
    }
}
