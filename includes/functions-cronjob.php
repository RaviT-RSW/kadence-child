<?php
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