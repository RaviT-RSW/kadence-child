<?php

add_action('wp_enqueue_scripts', 'kadence_child_enqueue_styles');
function kadence_child_enqueue_styles() {
    // Enqueue parent theme styles
    wp_enqueue_style('kadence-parent-style', get_template_directory_uri() . '/style.css');


    wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
    wp_enqueue_style('flatpicker', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');

    wp_enqueue_style('kadence-child-style', get_stylesheet_directory_uri() . '/assets/css/style.css');


    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js');
    wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js');

    wp_enqueue_script('script-mentor-js', get_stylesheet_directory_uri() . '/assets/js/script-mentor.js', '1.0.0', true);

    wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr"');

    wp_enqueue_script('kadence-child-script', get_stylesheet_directory_uri() . '/assets/js/script.js');

    $data_for_js = array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'current_user_id' => get_current_user_id(),
        'mentor_dashboard_nonce' => wp_create_nonce('mentor_dashboard_nonce'),

        // Add more here as required
    );

    wp_localize_script( 'kadence-child-script', 'php', $data_for_js );
}

// Include custom user roles
require_once get_stylesheet_directory() . '/includes/manage-custom-roles.php';
require_once get_stylesheet_directory() . '/includes/manage-hourly-rate.php';
require_once get_stylesheet_directory() . '/includes/mentor-management.php';
require_once get_stylesheet_directory() . '/includes/functions-general.php';
require_once get_stylesheet_directory() . '/includes/shortcodes.php';

require_once get_stylesheet_directory() . '/includes/class.zoom.php';

require_once get_stylesheet_directory() . '/functions-ajax.php';

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

            // Check if mentor_id and child_id exist
            if ($mentor_id && $child_id) {
                // Insert record into wp_assigned_mentees table
                $wpdb->insert(
                    $wpdb->prefix . 'assigned_mentees',
                    array(
                        'mentor_id' => $mentor_id,
                        'child_id' => $child_id,
                        'order_id' => $order_id,
                    ),
                    array('%d', '%d', '%d')
                );

                if ($wpdb->last_error) {
                    // Log error if insertion fails
                    error_log('Failed to insert record into wp_assigned_mentees: ' . $wpdb->last_error);
                }
            }
        }
    }
}

// Function to build email body from template
function build_email_body($template_name, $replacements = []) {
    $base_path = get_stylesheet_directory() . '/assets/email/';
    $header = file_get_contents($base_path . 'header.html');
    $footer = file_get_contents($base_path . 'footer.html');
    $body = file_get_contents($base_path . $template_name);
    
    if (!$body) {
        return '';
    }
    
    $full = $header . $body . $footer;
    
    $defaults = [
        'site_name' => get_bloginfo('name'),
        'site_url'  => site_url(),
        'year'      => date('Y')
    ];
    
    $replacements = array_merge($defaults, $replacements);
    
    foreach ($replacements as $key => $value) {
        $full = str_replace('{' . $key . '}', $value, $full);
    }
    
    return $full;
}

// Define a custom cron schedule for every minute
add_filter('cron_schedules', 'add_custom_cron_interval');
function add_custom_cron_interval($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60, // seconds
        'display' => __('Every Minute')
    );
    return $schedules;
}

// Schedule the cron event if not already scheduled
add_action('init', 'setup_cron_job');
function setup_cron_job() {
    if (!wp_next_scheduled('check_and_send_session_reminders')) {
        wp_schedule_event(time(), 'every_minute', 'check_and_send_session_reminders');
    }
}

// Check approved sessions and send reminders
add_action('check_and_send_session_reminders', 'check_and_send_session_reminders');
function check_and_send_session_reminders() {
    global $wpdb;

    $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $log_time = $now->format('Y-m-d H:i:s T');
    error_log("Cron ran at: $log_time - Checking sessions...\n", 3, WP_CONTENT_DIR . '/debug.log');

    // Query to get approved sessions
    $order_items = $wpdb->get_results("
        SELECT oi.order_item_id, oi.order_id, oi.order_item_name as session_name,
               om1.meta_value as mentor_id, om2.meta_value as child_id,
               om3.meta_value as session_date_time, om4.meta_value as appointment_status
        FROM {$wpdb->prefix}woocommerce_order_items oi
        JOIN {$wpdb->prefix}posts o ON oi.order_id = o.ID
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta om1 ON oi.order_item_id = om1.order_item_id AND om1.meta_key = 'mentor_id'
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta om2 ON oi.order_item_id = om2.order_item_id AND om2.meta_key = 'child_id'
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta om3 ON oi.order_item_id = om3.order_item_id AND om3.meta_key = 'session_date_time'
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta om4 ON oi.order_item_id = om4.order_item_id AND om4.meta_key = 'appointment_status'
        WHERE oi.order_item_type = 'line_item' AND om4.meta_value = 'approved'
    ");

    foreach ($order_items as $item) {
        $session_datetime = new DateTime($item->session_date_time, new DateTimeZone('Asia/Kolkata'));
        $interval = $now->diff($session_datetime);
        $hours = ($interval->days * 24) + $interval->h;
        $minutes = $interval->i;
        $total_minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $minutes;
        $mentor = get_user_by('id', $item->mentor_id);
        $child = get_user_by('id', $item->child_id);
        $order = wc_get_order($item->order_id);
        $parent = $order ? get_user_by('id', $order->get_customer_id()) : null;

        if (!$mentor || !$child || !$parent) {
            continue;
        }

        $session_date = $session_datetime->format('Y-m-d');
        $session_time = $session_datetime->format('H:i');
        $time_frame = sprintf('%d hours', $hours);
        if ($interval->days > 0) {
            $time_frame = sprintf('%d hours', $hours);
        }

        $replacements = [
            'session_name' => $item->session_name,
            'mentor_name' => $mentor->display_name,
            'child_name' => $child->display_name,
            'session_date' => $session_date,
            'session_time' => $session_time,
            'time_frame' => $time_frame
        ];

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        // Check for 24-hour reminder
        if ($total_minutes == 24 * 60) {
            $child_subject = '24-Hour Session Reminder';
            $child_body = build_email_body('child-notification.html', $replacements);
            wp_mail($child->user_email, $child_subject, $child_body, $headers);

            $parent_subject = '24-Hour Reminder: Your Child\'s Session';
            $parent_body = build_email_body('parent-notification.html', array_merge($replacements, ['child_name' => $child->display_name]));
            wp_mail($parent->user_email, $parent_subject, $parent_body, $headers);

            $mentor_subject = '24-Hour Mentoring Session Reminder';
            $mentor_body = build_email_body('mentor-notification.html', $replacements);
            wp_mail($mentor->user_email, $mentor_subject, $mentor_body, $headers);

            error_log("24-hour reminder sent for session {$item->session_name} at $log_time\n", 3, WP_CONTENT_DIR . '/debug.log');
        }

        // Check for 1-hour reminder
        if ($total_minutes == 60) {
            $child_subject = '1-Hour Session Reminder';
            $child_body = build_email_body('child-notification.html', $replacements);
            wp_mail($child->user_email, $child_subject, $child_body, $headers);

            $parent_subject = '1-Hour Reminder: Your Child\'s Session';
            $parent_body = build_email_body('parent-notification.html', array_merge($replacements, ['child_name' => $child->display_name]));
            wp_mail($parent->user_email, $parent_subject, $parent_body, $headers);

            $mentor_subject = '1-Hour Mentoring Session Reminder';
            $mentor_body = build_email_body('mentor-notification.html', $replacements);
            wp_mail($mentor->user_email, $mentor_subject, $mentor_body, $headers);

            error_log("1-hour reminder sent for session {$item->session_name} at $log_time\n", 3, WP_CONTENT_DIR . '/debug.log');
        }
    }
}

// Clean up on plugin/theme deactivation
register_deactivation_hook(__FILE__, 'deactivate_cron_job');
function deactivate_cron_job() {
    wp_clear_scheduled_hook('check_and_send_session_reminders');
}