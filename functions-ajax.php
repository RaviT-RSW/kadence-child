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

