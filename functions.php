<?php
// Enqueue parent theme styles
add_action('wp_enqueue_scripts', 'kadence_child_enqueue_styles');
function kadence_child_enqueue_styles() {
    wp_enqueue_style('kadence-parent-style', get_template_directory_uri() . '/style.css');
}

// Include custom user roles
require_once get_stylesheet_directory() . '/includes/custom-roles.php';
require_once get_stylesheet_directory() . '/includes/functions-general.php';
require_once get_stylesheet_directory() . '/includes/shortcodes.php';


add_filter('login_url', 'custom_login_url', 10, 3);
function custom_login_url($login_url, $redirect, $force_reauth) {
    return home_url('/custom-login'); // Replace with your login page slug
}


add_action('wp_ajax_save_working_hours', 'handle_save_working_hours');
function handle_save_working_hours() {
    check_ajax_referer('mentor_dashboard_nonce', 'nonce');
    global $wpdb;

    $data = json_decode(stripslashes($_POST['data']), true);
    $mentor_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'mentor_working_hours';

    $hours_data = [
        'mentor_id' => $mentor_id,
        'monday' => $data['Monday']['dayOff'] ? NULL : json_encode(['start_time' => $data['Monday']['startTime'], 'end_time' => $data['Monday']['endTime']]),
        'tuesday' => $data['Tuesday']['dayOff'] ? NULL : json_encode(['start_time' => $data['Tuesday']['startTime'], 'end_time' => $data['Tuesday']['endTime']]),
        'wednesday' => $data['Wednesday']['dayOff'] ? NULL : json_encode(['start_time' => $data['Wednesday']['startTime'], 'end_time' => $data['Wednesday']['endTime']]),
        'thursday' => $data['Thursday']['dayOff'] ? NULL : json_encode(['start_time' => $data['Thursday']['startTime'], 'end_time' => $data['Thursday']['endTime']]),
        'friday' => $data['Friday']['dayOff'] ? NULL : json_encode(['start_time' => $data['Friday']['startTime'], 'end_time' => $data['Friday']['endTime']]),
        'saturday' => $data['Saturday']['dayOff'] ? NULL : json_encode(['start_time' => $data['Saturday']['startTime'], 'end_time' => $data['Saturday']['endTime']]),
        'sunday' => $data['Sunday']['dayOff'] ? NULL : json_encode(['start_time' => $data['Sunday']['startTime'], 'end_time' => $data['Sunday']['endTime']]),
    ];

    $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_name WHERE mentor_id = %d", $mentor_id));

    if ($existing) {
        $wpdb->update($table_name, $hours_data, ['mentor_id' => $mentor_id]);
    } else {
        $wpdb->insert($table_name, $hours_data);
    }

    wp_send_json_success();
}

// Update Profile
add_action('wp_ajax_update_profile', 'handle_update_profile');
function handle_update_profile() {
    check_ajax_referer('mentor_dashboard_nonce', 'nonce');
    $user_id = intval($_POST['user_id']);
    if (current_user_can('edit_user', $user_id)) {
        $first_name = sanitize_text_field($_POST['firstName']);
        $last_name = sanitize_text_field($_POST['lastName']);
        $phone = sanitize_text_field($_POST['phone']);

        wp_update_user([
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name
        ]);
        update_user_meta($user_id, 'phone', $phone);
        wp_send_json_success();
    }
    wp_send_json_error();
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

    // Add product to cart with meta data
    $cart_item_key = WC()->cart->add_to_cart($session_product_id, 1, 0, array(), array(
        'mentor_id' => $mentor_id,
        'child_id' => $child_id,
        'session_date_time' => $session_date_time,
        'appointment_status' => $appointment_status,
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

// Add AJAX action for canceling a session
add_action('wp_ajax_cancel_session', 'handle_cancel_session');
function handle_cancel_session() {
    check_ajax_referer('mentor_dashboard_nonce', 'nonce');

    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

    if ($item_id && $order_id) {
        $order = wc_get_order($order_id);
        if ($order) {
            $items = $order->get_items();
            if (isset($items[$item_id])) {
                $item = $items[$item_id];
                $item->update_meta_data('appointment_status', 'cancelled');
                $item->save();

                wp_send_json_success(array('message' => 'Session cancelled successfully.'));
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

