<?php
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

/**
 * Handles AJAX request to get mentor working hours.
 *
 * @since 1.0.0
 *
 * @param int $mentor_id Mentor ID
 */
function handle_get_mentor_working_hours() {
    check_ajax_referer('mentor_dashboard_nonce', 'nonce');

    $mentor_id = isset($_POST['mentor_id']) ? intval($_POST['mentor_id']) : 0;
    if (!$mentor_id) {
        wp_send_json_error(['message' => 'Invalid mentor ID']);
        wp_die();
    }

    // Verify mentor exists and has mentor_user role
    $mentor = get_user_by('id', $mentor_id);
    if (!$mentor || !in_array('mentor_user', (array)$mentor->roles)) {
        wp_send_json_error(['message' => 'Mentor not found or invalid']);
        wp_die();
    }

    // Fetch mentor working hours
    global $wpdb;
    $hours = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mentor_working_hours WHERE mentor_id = %d", $mentor_id)
    );

    $working_hours = $hours ? [
        'monday' => $hours->monday,
        'tuesday' => $hours->tuesday,
        'wednesday' => $hours->wednesday,
        'thursday' => $hours->thursday,
        'friday' => $hours->friday,
        'saturday' => $hours->saturday,
        'sunday' => $hours->sunday,
    ] : [];

    wp_send_json_success([
        'working_hours' => $working_hours
    ]);
    wp_die();
}
add_action('wp_ajax_get_mentor_working_hours', 'handle_get_mentor_working_hours');


// Add AJAX action for canceling a session
add_action('wp_ajax_approve_session', 'handle_approve_session');
function handle_approve_session() {
    check_ajax_referer('mentor_dashboard_nonce', 'nonce');

    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

    if ($item_id && $order_id) {
        $order = wc_get_order($order_id);


        if ($order) {
            $items = $order->get_items();
            if (isset($items[$item_id]))
            {
                $item = $items[$item_id];
                $location = $item->get_meta('location');

                if(!empty($location) && $location != "online")
                {
                    $item->update_meta_data('appointment_status', 'approved');
                    $item->save();

                    wp_send_json_success(array('message' => 'Session Approved successfully.'));
                
                    return;
                }

                // if location online then procced to create zoom 

                $session_date_time = $item->get_meta('session_date_time');
                $date = new DateTime($session_date_time, new DateTimeZone('UTC'));
                $isoFormat = $date->format('Y-m-d\TH:i:s\Z');

                if(class_exists('Zoom'))
                {
                    $meetingInfo = array(
                        'topic' => $item->get_name(),
                        'start_time'=> $isoFormat,
                        'duration'=> "60",
                        'timezone'=> "UTC",
                    );

                    $zoom = new Zoom();
                    $meeting = $zoom->createZoomMetting($meetingInfo);
                    $meeting = json_decode($meeting, true);
                    
                    if($meeting['status'] == 'success'){
                        $item->update_meta_data('zoom_meeting', $meeting['data']);
                        // Approve only if meeting is created successfully.
                        $item->update_meta_data('appointment_status', 'approved');
                        $item->save();
                    }
                }

                wp_send_json_success(array('message' => 'Session Approved successfully.'));

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


/**
 * Handles AJAX request to finish an appointment.
 *
 * @since 1.0.0
 */
function handle_finish_appointment() {
    check_ajax_referer('finish_appointment_nonce', 'nonce');

    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

    if (!$item_id || !$order_id) {
        wp_send_json_error(['message' => 'Invalid item or order ID']);
        wp_die();
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(['message' => 'Order not found']);
        wp_die();
    }

    $items = $order->get_items();
    $item = isset($items[$item_id]) ? $items[$item_id] : false;
    if (!$item) {
        wp_send_json_error(['message' => 'Item not found']);
        wp_die();
    }

    // Update appointment status
    $item->update_meta_data('appointment_status', 'finished');
    $item->save();

    wp_send_json_success(['message' => 'Appointment finished successfully']);
    wp_die();
}
add_action('wp_ajax_finish_appointment', 'handle_finish_appointment');


/**
 * Handles AJAX request to save feedback for an appointment.
 *
 * @since 1.0.0
 */
function handle_save_appointment_feedback() {
    check_ajax_referer('save_feedback_nonce', 'nonce');

    global $wpdb;
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $feedback_note = isset($_POST['feedback_note']) ? sanitize_textarea_field($_POST['feedback_note']) : '';

    if (!$order_id) {
        wp_send_json_error(['message' => 'Invalid order ID']);
        wp_die();
    }

    $feedback_voice = '';
    if (!empty($_FILES['feedback_voice']['name'])) {
        $upload = wp_upload_bits($_FILES['feedback_voice']['name'], null, file_get_contents($_FILES['feedback_voice']['tmp_name']));
        if (!$upload['error']) {
            $feedback_voice = $upload['url'];
        } else {
            wp_send_json_error(['message' => 'Error uploading voice note']);
            wp_die();
        }
    }

    // Check if feedback exists
    $existing_feedback = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}appointment_feedback WHERE order_id = %d", $order_id)
    );

    if ($existing_feedback) {
        // Update existing feedback
        $wpdb->update(
            $wpdb->prefix . 'appointment_feedback',
            [
                'feedback_short_note' => $feedback_note,
                'feedback_voice_notes' => $feedback_voice ?: $existing_feedback->feedback_voice_notes,
                'updated_at' => current_time('mysql')
            ],
            ['order_id' => $order_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    } else {
        // Insert new feedback
        $wpdb->insert(
            $wpdb->prefix . 'appointment_feedback',
            [
                'order_id' => $order_id,
                'feedback_short_note' => $feedback_note,
                'feedback_voice_notes' => $feedback_voice,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }

    wp_send_json_success(['message' => 'Feedback saved successfully']);
    wp_die();
}
add_action('wp_ajax_save_appointment_feedback', 'handle_save_appointment_feedback');


/**
 * Handles saving an expense record for an appointment order via AJAX.
 *
 * @since 1.0.0
 */
function handle_save_appointment_expense() {
    check_ajax_referer('save_expense_nonce', 'nonce');

    global $wpdb;
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $expense_amount = isset($_POST['expense_amount']) ? floatval($_POST['expense_amount']) : 0;
    $expense_description = isset($_POST['expense_description']) ? sanitize_textarea_field($_POST['expense_description']) : '';

    if (!$order_id || !$expense_amount) {
        wp_send_json_error(['message' => 'Invalid order ID or amount']);
        wp_die();
    }

    $expense_receipt = '';
    if (!empty($_FILES['expense_receipt']['name'])) {
        $upload = wp_upload_bits($_FILES['expense_receipt']['name'], null, file_get_contents($_FILES['expense_receipt']['tmp_name']));
        if ($upload['error']) {
            wp_send_json_error(['message' => 'Error uploading receipt: ' . $upload['error']]);
            wp_die();
        }
        $expense_receipt = $upload['url'];
    }

    // Check if expense exists
    $existing_expense = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}appointment_expense WHERE order_id = %d", $order_id)
    );

    if ($existing_expense) {
        // Update existing expense
        $wpdb->update(
            $wpdb->prefix . 'appointment_expense',
            [
                'expense_amount' => number_format($expense_amount, 2, '.', ''),
                'expense_description' => $expense_description,
                'expense_receipt' => $expense_receipt ?: $existing_expense->expense_receipt,
                'updated_at' => current_time('mysql')
            ],
            ['order_id' => $order_id],
            ['%f', '%s', '%s', '%s'],
            ['%d']
        );
    } else {
        // Insert new expense
        $wpdb->insert(
            $wpdb->prefix . 'appointment_expense',
            [
                'order_id' => $order_id,
                'expense_amount' => number_format($expense_amount, 2, '.', ''),
                'expense_description' => $expense_description,
                'expense_receipt' => $expense_receipt,
                'expense_status' => 'pending',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%d', '%f', '%s', '%s', '%s', '%s', '%s']
        );
    }

    // Check for insert/update errors
    if ($wpdb->last_error) {
        error_log('Failed to save expense: ' . $wpdb->last_error);
        wp_send_json_error(['message' => 'Failed to save expense: ' . $wpdb->last_error]);
        wp_die();
    }

    wp_send_json_success(['message' => 'Expense saved successfully']);
    wp_die();
}
add_action('wp_ajax_save_appointment_expense', 'handle_save_appointment_expense');


/**
 * Handles AJAX request to get assigned mentor for a child.
 *
 * @since 1.0.0
 *
 * @param int $child_id Child ID
 */
function handle_get_assigned_mentor() {
    check_ajax_referer('get_assigned_mentor_nonce', 'nonce');

    $child_id = isset($_POST['child_id']) ? intval($_POST['child_id']) : 0;
    if (!$child_id) {
        wp_send_json_error(['message' => 'Invalid child ID']);
        wp_die();
    }

    // Fetch assigned mentor ID from user meta
    $mentor_id = get_user_meta($child_id, 'assigned_mentor_id', true);
    if (!$mentor_id) {
        wp_send_json_error(['message' => 'No mentor assigned to this child']);
        wp_die();
    }

    // Verify mentor exists and has mentor_user role
    $mentor = get_user_by('id', $mentor_id);
    if (!$mentor || !in_array('mentor_user', (array)$mentor->roles)) {
        wp_send_json_error(['message' => 'Assigned mentor not found or invalid']);
        wp_die();
    }

    // Fetch mentor working hours
    global $wpdb;
    $hours = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mentor_working_hours WHERE mentor_id = %d", $mentor_id)
    );
    $working_hours = $hours ? [
        'monday' => $hours->monday,
        'tuesday' => $hours->tuesday,
        'wednesday' => $hours->wednesday,
        'thursday' => $hours->thursday,
        'friday' => $hours->friday,
        'saturday' => $hours->saturday,
        'sunday' => $hours->sunday,
    ] : [];

    wp_send_json_success([
        'mentor' => [
            'id' => $mentor->ID,
            'name' => $mentor->display_name
        ],
        'working_hours' => $working_hours
    ]);
    wp_die();
}
add_action('wp_ajax_get_assigned_mentor', 'handle_get_assigned_mentor');


/**
 * Handles AJAX request to get mentor appointment history.
 *
 * @since 1.0.0
 */
function handle_get_mentor_appointment_history() {
    check_ajax_referer('mentor_appointment_history_nonce', 'nonce');

    global $wpdb;
    $mentor_id = get_current_user_id();
    if (!in_array('mentor_user', (array)wp_get_current_user()->roles)) {
        wp_send_json_error(['message' => 'Unauthorized access']);
        wp_die();
    }

    $year = isset($_POST['year']) ? intval($_POST['year']) : date('Y');
    $month = isset($_POST['month']) ? sanitize_text_field($_POST['month']) : date('F');
    $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
    $per_page = 10;

    // Base query for counts and sums
    $base_query = "SELECT am.order_id, am.appointment_date_time, am.child_id FROM {$wpdb->prefix}assigned_mentees am WHERE am.mentor_id = %d";
    $params = [$mentor_id];

    if ($month !== 'all') {
        $month_num = date('m', strtotime($month));
        $base_query .= " AND YEAR(am.appointment_date_time) = %d AND MONTHNAME(am.appointment_date_time) = %s";
        $params[] = $year;
        $params[] = $month;
    } else {
        $base_query .= " AND YEAR(am.appointment_date_time) = %d";
        $params[] = $year;
    }

    // Get all order_ids for stats
    $all_orders = $wpdb->get_results($wpdb->prepare($base_query, $params));

    // Calculate stats
    $total_sessions = count($all_orders);
    $hourly_rate = floatval(get_user_meta($mentor_id, 'mentor_hourly_rate', true)) ?: 0;
    $total_duration = 0;

    foreach ($all_orders as $order_record) {
        $order = wc_get_order($order_record->order_id);
        if (!$order) continue;

        $item = reset($order->get_items());
        if (!$item) continue;

        $duration = intval($item->get_meta('appointment_duration')) ?: 0;
        $total_duration += $duration;
    }

    $total_hours = $total_duration / 60;
    $total_earnings = $total_hours * $hourly_rate;

    // Paged query for table
    $paged_query = $base_query . " ORDER BY am.appointment_date_time DESC LIMIT %d OFFSET %d";
    $paged_params = array_merge($params, [$per_page, ($page - 1) * $per_page]);
    $paged_orders = $wpdb->get_results($wpdb->prepare($paged_query, $paged_params));

    // Process paged appointments for table
    $appointment_data = [];
    foreach ($paged_orders as $index => $record) {
        $order = wc_get_order($record->order_id);
        if (!$order) continue;

        $item = reset($order->get_items());
        if (!$item) continue;
        $child = get_user_by('id', $record->child_id);
        $duration = intval($item->get_meta('appointment_duration')) ?: 0;
        $earnings = ($duration / 60) * $hourly_rate;

        $appointment_data[] = [
            'title' => $item->get_name() ?: 'Unknown Product',
            'attende_name' => $child ? ucfirst($child->display_name) : 'Unknown',
            'date_time' => $item->get_meta('session_date_time') ? date('M d, Y - h:i A', strtotime($item->get_meta('session_date_time'))) : 'N/A',
            'duration' => $duration,
            'earnings' => number_format($earnings, 2),
            'status' => $item->get_meta('appointment_status') ?: 'pending',
            'view_url' => esc_url(add_query_arg(['order_id' => $record->order_id, 'item_id' => $item->get_id()], site_url('/appointment-details/')))
        ];
    }

    // Prepare response
    wp_send_json_success([
        'hourly_rate' => number_format($hourly_rate, 2),
        'total_sessions' => $total_sessions,
        'total_hours' => number_format($total_hours, 2),
        'total_earnings' => number_format($total_earnings, 2),
        'appointments' => $appointment_data,
        'total_pages' => ceil($total_sessions / $per_page),
        'current_page' => $page
    ]);
    wp_die();
}
add_action('wp_ajax_get_mentor_appointment_history', 'handle_get_mentor_appointment_history');