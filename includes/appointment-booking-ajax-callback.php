<?php
/**
 * Manage AJAX callbacks for appointment booking.
 */

/**
 * AJAX callback to get booked sessions for a mentor in a given month.
 *
 * @since 1.0.0
 */
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
            "SELECT session_meta.meta_value AS session_date_time,
                    status_meta.meta_value AS appointment_status
             FROM {$wpdb->prefix}woocommerce_order_itemmeta AS session_meta
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS mentor_meta
                 ON session_meta.order_item_id = mentor_meta.order_item_id
             LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS status_meta
                 ON session_meta.order_item_id = status_meta.order_item_id
                 AND status_meta.meta_key = 'appointment_status'
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
        $appointment_status = strtolower($row->appointment_status ?: 'pending');
        
        // Only include approved or pending sessions as booked slots
        // Skip cancelled sessions so they become available again
        if (in_array($appointment_status, ['approved', 'pending'])) {
            $date = date('Y-m-d', strtotime($row->session_date_time));
            $start_time = date('H:i', strtotime($row->session_date_time));
            $booked_slots[$date][] = $start_time;
        }
    }

    wp_send_json_success(['booked_slots' => $booked_slots]);
}
add_action('wp_ajax_get_mentor_booked_slots', 'get_mentor_booked_slots');

/**
 * Handles adding a session to the cart via AJAX.
 *
 * @since 1.0.0
 */
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

    // Get current user and check role
    $current_user = wp_get_current_user();
    $user_roles = (array)$current_user->roles;
    $is_mentor = in_array('mentor_user', $user_roles);
    
    // Validate slot availability
    $start_datetime = new DateTime($session_date_time, new DateTimeZone(wp_timezone_string()));
    $end_datetime = clone $start_datetime;
    $end_datetime->modify('+1 hour');

    global $wpdb;
    $overlap_check = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_itemmeta AS session_meta
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS mentor_meta
                 ON session_meta.order_item_id = mentor_meta.order_item_id
             LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS status_meta
                 ON session_meta.order_item_id = status_meta.order_item_id
                 AND status_meta.meta_key = 'appointment_status'
             WHERE session_meta.meta_key = 'session_date_time'
               AND mentor_meta.meta_key = 'mentor_id'
               AND mentor_meta.meta_value = %d
               AND session_meta.meta_value BETWEEN %s AND %s
               AND (status_meta.meta_value IS NULL OR status_meta.meta_value NOT IN ('cancelled'))",
            $mentor_id,
            $start_datetime->format('Y-m-d H:i:s'),
            $end_datetime->format('Y-m-d H:i:s')
        )
    );

    if ($overlap_check > 0) {
        wp_send_json_error(['message' => 'This time slot is already booked.']);
        return;
    }

    // Handle differently based on user role
    if ($is_mentor) {
        // For mentors: Create order directly (no checkout)
        try {
            $product = wc_get_product($session_product_id);
            
            if (!$product) {
                wp_send_json_error(['message' => 'Invalid product selected.']);
                return;
            }

            // Create new order
            $order = wc_create_order();
            
            // Add product to order
            $item_id = $order->add_product($product, 1);
            
            if (!$item_id) {
                wp_send_json_error(['message' => 'Failed to add product to order.']);
                return;
            }
            
            // Get the order item object
            $item = $order->get_item($item_id);
            
            if (!$item) {
                wp_send_json_error(['message' => 'Failed to retrieve order item.']);
                return;
            }
            
            // Add custom meta data to order item
            $item->update_meta_data('mentor_id', $mentor_id);
            $item->update_meta_data('child_id', $child_id);
            $item->update_meta_data('session_date_time', $session_date_time);
            $item->update_meta_data('appointment_status', $appointment_status);
            $item->update_meta_data('appointment_duration', 60);
            $item->save();
            
            // Set billing information
            $order->set_billing_first_name($current_user->first_name ?: 'User');
            $order->set_billing_last_name($current_user->last_name ?: 'Name');
            $order->set_billing_email($current_user->user_email);
            $order->set_status('pending');
            
            // Calculate totals
            $order->calculate_totals();
            
            // Save the order
            $order->save();
            
            // Clear any existing cart items to avoid conflicts
            WC()->cart->empty_cart();
            
            // Add record to assigned_mentees table
            $wpdb->insert(
                $wpdb->prefix . 'assigned_mentees',
                array(
                    'mentor_id' => $mentor_id,
                    'child_id' => $child_id,
                    'order_id' => $order->get_id(),
                    'appointment_date_time' => $session_date_time,
                ),
                array('%d', '%d', '%d', '%s')
            );

            if ($wpdb->last_error) {
                error_log('Failed to insert record into wp_assigned_mentees: ' . $wpdb->last_error);
            }

            // Return success with order ID for redirect to thank you page
            wp_send_json_success([
                'success' => true, 
                'order_id' => $order->get_id(),
                'redirect_url' => $order->get_checkout_order_received_url(),
                'is_direct_order' => true
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Failed to create order: ' . $e->getMessage()]);
        }
        
    } else {
        // For parents: Use original cart method (with checkout)
        $cart_item_key = WC()->cart->add_to_cart($session_product_id, 1, 0, array(), array(
            'mentor_id' => $mentor_id,
            'child_id' => $child_id,
            'session_date_time' => $session_date_time,
            'appointment_status' => $appointment_status,
            'appointment_duration' => 60
        ));

        if ($cart_item_key) {
            wp_send_json_success([
                'success' => true,
                'redirect_url' => wc_get_checkout_url(),
                'is_direct_order' => false
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to add session to cart.']);
        }
    }
}
add_action('wp_ajax_add_session_to_cart', 'handle_add_session_to_cart');

/**
 * Transfer cart item meta data to an order item when an order is placed.
 *
 * @since 1.0.0
 */
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
add_action('woocommerce_checkout_create_order_line_item', 'transfer_cart_item_meta_to_order', 10, 4);

/**
 * Displays mentor and child information for each order item on the order edit page.
 *
 * @since 1.0.0
 */
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
add_action('woocommerce_admin_order_data_after_order_details', 'display_mentor_child_details', 10, 1);

/**
 * Adds a record to the wp_assigned_mentees table for each order item.
 *
 * @since 1.0.0
 *
 */
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
add_action('woocommerce_checkout_order_processed', 'add_assigned_mentees_record', 10, 2);

/**
 * Handles rescheduling a session via AJAX.
 *
 * @since 1.0.0
 */
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
add_action('wp_ajax_reschedule_session', 'handle_reschedule_session');

/**
 * Customizes the checkout fields.
 *
 * @since 1.0.0
 */
function custom_override_checkout_fields($fields) {

    // Remove billing fields
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_address_1']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_state']);
    unset($fields['billing']['billing_city']);
    unset($fields['billing']['billing_postcode']);

    $fields['billing']['billing_first_name']['priority'] = 10;
    $fields['billing']['billing_last_name']['priority'] = 20;
    $fields['billing']['billing_email']['priority'] = 30;

    return $fields;
}
add_filter('woocommerce_checkout_fields', 'custom_override_checkout_fields');
