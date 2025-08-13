<?php

add_action('wp_ajax_get_mentor_booked_slots', 'get_mentor_booked_slots');
function get_mentor_booked_slots() {
    check_ajax_referer('mentor_dashboard_nonce', 'nonce');
    global $wpdb;

    $mentor_id = intval($_POST['mentor_id']);
    $year = intval($_POST['year']);
    $month = intval($_POST['month']);

    if (!$mentor_id || !$year || !$month) {
        wp_send_json_error(['message' => 'Invalid parameters']);
        return;
    }

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT session_meta.meta_value AS session_date_time 
             FROM {$wpdb->prefix}woocommerce_order_itemmeta AS session_meta
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS mentor_meta
                 ON session_meta.order_item_id = mentor_meta.order_item_id
             WHERE session_meta.meta_key = 'session_date_time'
               AND mentor_meta.meta_key = 'mentor_id'
               AND mentor_meta.meta_value = %d
               AND DATE_FORMAT(session_meta.meta_value, '%%Y-%%m') = %s",
            $mentor_id,
            sprintf('%04d-%02d', $year, $month)
        )
    );
    

    $booked_slots = [];
    foreach ($results as $row) {
        $date = date('Y-m-d', strtotime($row->session_date_time));
        $start_time = date('H:i', strtotime($row->session_date_time));
        $booked_slots[$date][] = $start_time;
    }

    wp_send_json_success(['booked_slots' => $booked_slots]);
}

add_action('wp_ajax_add_session_to_cart', 'handle_add_session_to_cart');
function handle_add_session_to_cart() {
    check_ajax_referer('mentor_dashboard_nonce', 'nonce');

    $session_product_id = intval($_POST['sessionProduct']);
    $mentor_id = intval($_POST['mentorSelect']);
    $session_date_time = sanitize_text_field($_POST['sessionDateTime']);
    $child_id = intval($_POST['childSelect']);
    $appointment_status = 'pending';

    if (!$session_product_id || !$mentor_id || !$session_date_time || !$child_id) {
        wp_send_json_error(['message' => 'All fields are required.']);
        return;
    }

    // Validate slot availability
    $start_datetime = new DateTime($session_date_time, new DateTimeZone(wp_timezone_string()));
    $end_datetime = clone $start_datetime;
    $end_datetime->modify('+1 hour');

    global $wpdb;
    $overlap_check = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_itemmeta 
             WHERE order_item_id IN (
                 SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items 
                 WHERE order_id IN (
                     SELECT post_id FROM {$wpdb->prefix}postmeta 
                     WHERE meta_key = 'mentor_id' AND meta_value = %d
                 )
             ) AND meta_key = 'session_date_time' 
             AND meta_value BETWEEN %s AND %s 
             AND meta_value != %s",
            $mentor_id,
            $start_datetime->format('Y-m-d H:i:s'),
            $end_datetime->format('Y-m-d H:i:s'),
            $session_date_time
        )
    );

    if ($overlap_check > 0) {
        wp_send_json_error(['message' => 'This time slot is already booked.']);
        return;
    }

    // Add product to cart with meta data
    $cart_item_key = WC()->cart->add_to_cart($session_product_id, 1, 0, array(), array(
        'mentor_id' => $mentor_id,
        'child_id' => $child_id,
        'session_date_time' => $session_date_time,
        'appointment_status' => $appointment_status,
        'appointment_duration' => 60
    ));

    if ($cart_item_key) {
        wp_send_json_success(['success' => true]);
    } else {
        wp_send_json_error(['message' => 'Failed to add session to cart.']);
    }
}

// Transfer cart item meta to order item meta
add_action('woocommerce_checkout_create_order_line_item', 'transfer_cart_item_meta_to_order', 10, 4);
function transfer_cart_item_meta_to_order($item, $cart_item_key, $values, $order) {
    if (isset($values['mentor_id'])) {
        $item->update_meta_data('mentor_id', $values['mentor_id']);
    }
    if (isset($values['child_id'])) {
        $item->update_meta_data('child_id', $values['child_id']);
    }
    if (isset($values['session_date_time'])) {
        $item->update_meta_data('session_date_time', $values['session_date_time']);
    }
    if (isset($values['appointment_status'])) {
        $item->update_meta_data('appointment_status', $values['appointment_status']);
    }
    if (isset($values['appointment_duration'])) {
        $item->update_meta_data('appointment_duration', $values['appointment_duration']);
    }
}

// Display mentor and child details on admin order details page
add_action('woocommerce_admin_order_data_after_order_details', 'display_mentor_child_details', 10, 1);
function display_mentor_child_details($order) {
    // Loop through order items
    foreach ($order->get_items() as $item_id => $item) {
        $mentor_id = $item->get_meta('mentor_id');
        $child_id = $item->get_meta('child_id');
        $session_date_time = $item->get_meta('session_date_time');
        $appointment_status = $item->get_meta('appointment_status');

        if ($mentor_id && $child_id) {
            // Get mentor and child user objects
            $mentor = get_user_by('id', $mentor_id);
            $child = get_user_by('id', $child_id);

            // Display the details
            echo '<div class="form-field form-field-wide order_data_column">';
            echo '<h4>Mentor and Child Information</h4>';
            if ($mentor) {
                echo '<p><strong>Mentor:</strong> ' . esc_html($mentor->display_name) . ' (ID: ' . esc_html($mentor_id) . ')</p>';
            }
            if ($child) {
                echo '<p><strong>Child:</strong> ' . esc_html($child->display_name) . ' (ID: ' . esc_html($child_id) . ')</p>';
            }
            if ($session_date_time) {
                echo '<p><strong>Session Date & Time:</strong> ' . esc_html($session_date_time) . '</p>';
            }
            if ($appointment_status) {
                echo '<p><strong>Appointment Status:</strong> ' . esc_html($appointment_status) . '</p>';
            }
            echo '</div>';
        }
    }
}

// Add record to wp_assigned_mentees table when an order is placed
add_action('woocommerce_checkout_order_processed', 'add_assigned_mentees_record', 10, 2);
function add_assigned_mentees_record($order_id, $posted_data) {
    global $wpdb;

    // Get the order object
    $order = wc_get_order($order_id);

    if ($order) {
        // Loop through order items
        foreach ($order->get_items() as $item_id => $item) {
            // Retrieve mentor_id and child_id from item meta
            $mentor_id = $item->get_meta('mentor_id');
            $child_id = $item->get_meta('child_id');
            $session_date_time = $item->get_meta('session_date_time');

            // Check if mentor_id and child_id exist
            if ($mentor_id && $child_id) {
                // Insert record into wp_assigned_mentees table
                $wpdb->insert(
                    $wpdb->prefix . 'assigned_mentees',
                    array(
                        'mentor_id' => $mentor_id,
                        'child_id' => $child_id,
                        'order_id' => $order_id,
                        'appointment_date_time' => $session_date_time,
                    ),
                    array('%d', '%d', '%d', '%s')
                );

                if ($wpdb->last_error) {
                    // Log error if insertion fails
                    error_log('Failed to insert record into wp_assigned_mentees: ' . $wpdb->last_error);
                }
            }
        }
    }
}

// Add AJAX action for rescheduling a session
add_action('wp_ajax_reschedule_session', 'handle_reschedule_session');
function handle_reschedule_session() {
    check_ajax_referer('mentor_dashboard_nonce', 'nonce');

    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $session_date_time = isset($_POST['session_date_time']) ? sanitize_text_field($_POST['session_date_time']) : '';

    if ($item_id && $order_id && $session_date_time) {
        $order = wc_get_order($order_id);
        if ($order) {
            $items = $order->get_items();
            if (isset($items[$item_id])) {
                $item = $items[$item_id];
                $item->update_meta_data('session_date_time', $session_date_time);
                $item->save();

                wp_send_json_success(array('message' => 'Session rescheduled successfully.'));
            } else {
                wp_send_json_error(array('message' => 'Invalid item ID.'));
            }
        } else {
            wp_send_json_error(array('message' => 'Invalid order ID.'));
        }
    } else {
        wp_send_json_error(array('message' => 'Missing required data.'));
    }
    wp_die();
}
