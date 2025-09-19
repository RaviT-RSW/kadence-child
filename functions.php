<?php

global $wpdb;
define('CHILD_GOAL_TABLE', $wpdb->prefix.'user_goals');

add_action('wp_enqueue_scripts', 'kadence_child_enqueue_styles');
function kadence_child_enqueue_styles() {
    // Enqueue parent theme styles
    wp_enqueue_style('kadence-parent-style', get_template_directory_uri() . '/style.css');


    wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css');
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
    wp_enqueue_style('flatpicker', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css');

    wp_enqueue_style('kadence-child-style', get_stylesheet_directory_uri() . '/assets/css/style.css');

    wp_enqueue_style('child-user-style', get_stylesheet_directory_uri() . '/assets/css/child-style.css');

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

    // Enqueue and localize mentor dashboard specific scripts
    if (is_mentor_user()) {
        enqueue_mentor_dashboard_scripts();
    }
}

// Function to handle mentor dashboard specific scripts
function enqueue_mentor_dashboard_scripts() {
    // Get session data for mentor dashboard
    $current_mentor = wp_get_current_user();
    $mentor_id = $current_mentor->ID;
    $sessions = array();
    $all_orders = wc_get_orders(array(
        'limit' => -1,
        'status' => array('wc-processing', 'wc-on-hold', 'wc-completed', 'wc-pending'),
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'is_monthly_invoice',
                'value' => '1',
                'compare' => '!=', // Orders where is_monthly_invoice is not 1
            ),
            array(
                'key' => 'is_monthly_invoice',
                'compare' => 'NOT EXISTS', // Orders where is_monthly_invoice is not set
            ),
        ),
    ));

    foreach ($all_orders as $order) {
        foreach ($order->get_items() as $item_id => $item) {
            $item_mentor_id = $item->get_meta('mentor_id');
            $child_id = $item->get_meta('child_id');
            $session_date_time = $item->get_meta('session_date_time');
            $appointment_status = $item->get_meta('appointment_status') ?: 'N/A';
            $zoom_meeting = $item->get_meta('zoom_meeting') ?: '';
            $location = $item->get_meta('location') ?: 'online';

            $zoom_link = '';

            if($location == 'online' && !empty($zoom_meeting) && class_exists('Zoom')) {
                $zoom = new Zoom();
                $zoom_link = $zoom->getMeetingUrl($zoom_meeting, 'start_url');
            }

            if ($item_mentor_id == $mentor_id && $child_id && $session_date_time) {
                $child = get_user_by('id', $child_id);
                $product_name = $item->get_name();
                $session_data = array(
                    'date_time' => new DateTime($session_date_time, new DateTimeZone('Asia/Kolkata')),
                    'child_name' => $child ? $child->display_name : 'Unknown Child',
                    'child_id' => $child_id,
                    'appointment_status' => $appointment_status,
                    'order_id' => $order->get_id(),
                    'product_name' => $product_name,
                    'zoom_link' => $zoom_link,
                    'location' => $location,
                    'customer_id' => $order->get_customer_id(),
                    'item_id' => $item_id,
                );
                $sessions[] = $session_data;
            }
        }
    }

    usort($sessions, function($a, $b) {
        return $a['date_time'] <=> $b['date_time'];
    });

    // Prepare sessions data for JavaScript (calendar format)
    $js_sessions = array_map(function($session) {
        $date_time_clone = clone $session['date_time'];
        return [
            'id' => $session['order_id'],
            'title' => $session['child_name'] . ' - ' . $session['product_name'],
            'start' => $session['date_time']->format('Y-m-d\TH:i:s'),
            'end' => $date_time_clone->add(new DateInterval('PT1H'))->format('Y-m-d\TH:i:s'),
            'extendedProps' => [
                'child_name' => $session['child_name'],
                'child_id' => $session['child_id'],
                'product_name' => $session['product_name'],
                'appointment_status' => $session['appointment_status'],
                'order_id' => $session['order_id'],
                'zoom_link' => $session['zoom_link'],
                'location' => $session['location'],
                'customer_id' => $session['customer_id'],
                'item_id' => $session['item_id']
            ]
        ];
    }, $sessions);

    // Localize script with mentor dashboard data
    wp_localize_script('script-mentor-js', 'mentorDashboardData', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mentor_dashboard_nonce'),
        'sessions' => $js_sessions,
    ));
}

// Include custom user roles
require_once get_stylesheet_directory() . '/includes/manage-custom-roles.php';
require_once get_stylesheet_directory() . '/includes/manage-hourly-rate.php';
require_once get_stylesheet_directory() . '/includes/manage-appointments.php';
require_once get_stylesheet_directory() . '/includes/manage-appointment-details.php';
require_once get_stylesheet_directory() . '/includes/functions-general.php';
require_once get_stylesheet_directory() . '/includes/functions-cronjob.php';
require_once get_stylesheet_directory() . '/includes/shortcodes.php';
require_once get_stylesheet_directory() . '/includes/functions-sql.php';
require_once get_stylesheet_directory() . '/includes/function-manage-users.php';
require_once get_stylesheet_directory() . '/includes/appointment-booking-ajax-callback.php';

require_once get_stylesheet_directory() . '/includes/class.zoom.php';

require_once get_stylesheet_directory() . '/functions-ajax.php';
require_once get_stylesheet_directory() . '/mentor/goals.php';

require 'functions-child.php';

require_once get_stylesheet_directory() . '/admin/urmentor-admin-dashboard.php';
require_once get_stylesheet_directory() . '/admin/functions.php';

add_action('wp_ajax_save_working_hours', 'handle_save_working_hours');
function handle_save_working_hours() {
    check_ajax_referer('mentor_dashboard_nonce', 'nonce');
    global $wpdb;

    $data = json_decode(stripslashes($_POST['data']), true);
    $mentor_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'mentor_working_hours';

    $hours_data = [
        'mentor_id' => $mentor_id,
        'monday' => !empty($data['Monday']) ? json_encode($data['Monday']) : NULL,
        'tuesday' => !empty($data['Tuesday']) ? json_encode($data['Tuesday']) : NULL,
        'wednesday' => !empty($data['Wednesday']) ? json_encode($data['Wednesday']) : NULL,
        'thursday' => !empty($data['Thursday']) ? json_encode($data['Thursday']) : NULL,
        'friday' => !empty($data['Friday']) ? json_encode($data['Friday']) : NULL,
        'saturday' => !empty($data['Saturday']) ? json_encode($data['Saturday']) : NULL,
        'sunday' => !empty($data['Sunday']) ? json_encode($data['Sunday']) : NULL,
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

        if (!empty($_FILES['profile_picture']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attachment_id = media_handle_upload('profile_picture', 0);
            if (!is_wp_error($attachment_id)) {
                update_user_meta($user_id, 'custom_profile_picture', $attachment_id);
            } else {
                wp_send_json_error(['message' => 'Image upload failed.']);
            }
        }

        wp_send_json_success();
    }
    wp_send_json_error();
}

add_action('woocommerce_thankyou', function($order_id) {
    $order = wc_get_order($order_id);

    if ($order && $order->get_meta('_is_booking_order') === 'yes') {
        $order->save();
    }
}, 10, 1);

add_filter( 'woocommerce_cod_process_payment_order_status', 'set_cod_order_status_pending' );

function set_cod_order_status_pending( $order_status ) {
    return 'pending';
}
