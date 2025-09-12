<?php
/**
 * Adds a custom admin page to the WordPress dashboard for managing WooCommerce-based appointment orders with filters, pagination, CSV export, and add new appointment functionality.
 */

/**
 * Registers a custom admin page for managing WooCommerce-based appointment orders.
 * 
 * @since 1.0.0
 */
function register_manage_appointments_menu() {
    add_menu_page(
        'Manage Appointments',
        'Manage Appointments',
        'manage_options',
        'manage-appointments',
        'render_manage_appointments_page',
        'dashicons-calendar-alt',
        4
    );
}
add_action('admin_menu', 'register_manage_appointments_menu');

/**
 * Handles CSV export early in the admin load process to avoid HTML output.
 */
function handle_appointments_export() {
    if (isset($_GET['page']) && $_GET['page'] === 'manage-appointments' && isset($_GET['export_csv']) && $_GET['export_csv'] === '1') {
        export_appointments_to_csv();
        exit;
    }
}
add_action('admin_init', 'handle_appointments_export');

/**
 * AJAX handler to get children based on selected parent
 */
function get_children_by_parent_ajax() {
    check_ajax_referer('manage_appointments_nonce', 'nonce');
    
    $parent_id = intval($_POST['parent_id']);
    
    $children = get_users(array(
        'role'       => 'child_user',
        'meta_key'   => 'assigned_parent_id',
        'meta_value' => $parent_id,
    ));
    
    $response = array();
    foreach ($children as $child) {
        $response[] = array(
            'id' => $child->ID,
            'name' => $child->display_name
        );
    }
    
    wp_send_json_success($response);
}
add_action('wp_ajax_get_children_by_parent', 'get_children_by_parent_ajax');

/**
 * AJAX handler to get mentor assigned to a child
 */
function get_mentor_by_child_ajax() {
    check_ajax_referer('manage_appointments_nonce', 'nonce');
    
    $child_id = intval($_POST['child_id']);
    $mentor_id = get_user_meta($child_id, 'assigned_mentor_id', true);
    
    if ($mentor_id) {
        $mentor = get_user_by('id', $mentor_id);
        if ($mentor) {
            wp_send_json_success(array(
                'id' => $mentor->ID,
                'name' => $mentor->display_name,
                'email' => $mentor->user_email
            ));
        }
    }
    
    wp_send_json_error('No mentor assigned to this child');
}
add_action('wp_ajax_get_mentor_by_child', 'get_mentor_by_child_ajax');

/**
 * AJAX handler to get mentor working hours and booked slots
 */
function get_mentor_availability_ajax() {
    global $wpdb;
    check_ajax_referer('manage_appointments_nonce', 'nonce');
    
    $mentor_id = intval($_POST['mentor_id']);
    $year = intval($_POST['year']);
    $month = intval($_POST['month']);
    
    // Get mentor working hours
    $hours = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mentor_working_hours WHERE mentor_id = %d", $mentor_id));
    $mentor_hours = array();
    
    if ($hours) {
        $mentor_hours = array(
            'monday' => $hours->monday ? json_decode($hours->monday, true) : array('off' => true, 'slots' => array()),
            'tuesday' => $hours->tuesday ? json_decode($hours->tuesday, true) : array('off' => true, 'slots' => array()),
            'wednesday' => $hours->wednesday ? json_decode($hours->wednesday, true) : array('off' => true, 'slots' => array()),
            'thursday' => $hours->thursday ? json_decode($hours->thursday, true) : array('off' => true, 'slots' => array()),
            'friday' => $hours->friday ? json_decode($hours->friday, true) : array('off' => true, 'slots' => array()),
            'saturday' => $hours->saturday ? json_decode($hours->saturday, true) : array('off' => true, 'slots' => array()),
            'sunday' => $hours->sunday ? json_decode($hours->sunday, true) : array('off' => true, 'slots' => array()),
        );
    }
    
    // Get booked slots for the month
    $booked_slots = array();
    $all_orders = wc_get_orders(array(
        'type'   => 'shop_order',
        'status' => array('wc-processing', 'wc-completed', 'wc-pending', 'wc-on-hold'),
        'return' => 'ids',
        'limit'  => -1,
    ));
    
    foreach ($all_orders as $order_id) {
        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $item_id => $item) {
            $item_mentor_id = $item->get_meta('mentor_id');
            $session_date_time = $item->get_meta('session_date_time');
            $appointment_status = $item->get_meta('appointment_status') ?: 'pending';
            
            if ($item_mentor_id == $mentor_id && $session_date_time && $appointment_status !== 'cancelled') {
                $date_time = new DateTime($session_date_time, new DateTimeZone('Asia/Kolkata'));
                if ($date_time->format('Y') == $year && $date_time->format('n') == $month) {
                    $date_key = $date_time->format('Y-m-d');
                    $time_key = $date_time->format('H:i');
                    
                    if (!isset($booked_slots[$date_key])) {
                        $booked_slots[$date_key] = array();
                    }
                    $booked_slots[$date_key][] = $time_key;
                }
            }
        }
    }
    
    wp_send_json_success(array(
        'working_hours' => $mentor_hours,
        'booked_slots' => $booked_slots
    ));
}
add_action('wp_ajax_get_mentor_availability', 'get_mentor_availability_ajax');

/**
 * AJAX handler to create new appointment
 */
function create_new_appointment_ajax() {
    check_ajax_referer('manage_appointments_nonce', 'nonce');
    
    $parent_id = intval($_POST['parent_id']);
    $child_id = intval($_POST['child_id']);
    $mentor_id = intval($_POST['mentor_id']);
    $session_date_time = sanitize_text_field($_POST['session_date_time']);
    $product_id = intval($_POST['session_product']);
    
    // Validate inputs
    if (!$parent_id || !$child_id || !$mentor_id || !$session_date_time || !$product_id) {
        wp_send_json_error('All fields are required');
    }
    
    // Get parent user
    $parent = get_user_by('id', $parent_id);
    if (!$parent) {
        wp_send_json_error('Invalid parent selected');
    }
    
    // Get product
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Invalid session product selected');
    }
    
    try {
        // Create WooCommerce order
        $order = wc_create_order(array(
            'customer_id' => $parent_id,
            'status' => 'pending'
        ));
        
        if (is_wp_error($order)) {
            wp_send_json_error('Failed to create order: ' . $order->get_error_message());
        }
        
        // Add product to order
        $item_id = $order->add_product($product, 1);
        
        // Add custom meta to order item
        wc_add_order_item_meta($item_id, 'child_id', $child_id);
        wc_add_order_item_meta($item_id, 'mentor_id', $mentor_id);
        wc_add_order_item_meta($item_id, 'session_date_time', $session_date_time);
        wc_add_order_item_meta($item_id, 'appointment_status', 'pending');
        wc_add_order_item_meta($item_id, 'location', 'online');
        
        // Update order item name to product title
        $order_item = $order->get_item($item_id);
        $order_item->set_name($product->get_name());
        $order_item->save();
        
        // Calculate order total
        $order->calculate_totals();
        $order->save();
        
        // Add order note
        $child = get_user_by('id', $child_id);
        $mentor = get_user_by('id', $mentor_id);
        $order->add_order_note(sprintf(
            'Appointment created by admin for %s with mentor %s on %s for session %s',
            $child ? $child->display_name : 'Unknown Child',
            $mentor ? $mentor->display_name : 'Unknown Mentor',
            $session_date_time,
            $product->get_name()
        ));
        
        wp_send_json_success(array(
            'order_id' => $order->get_id(),
            'message' => 'Appointment created successfully!'
        ));
        
    } catch (Exception $e) {
        wp_send_json_error('Error creating appointment: ' . $e->getMessage());
    }
}
add_action('wp_ajax_create_new_appointment', 'create_new_appointment_ajax');

/**
 * Renders the custom admin page for managing WooCommerce-based appointment orders with filters and pagination.
 *
 * @since 1.0.0
 */
function render_manage_appointments_page() {
    global $wpdb;

    // Handle filters
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date   = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    $status     = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

    // Pagination
    $per_page     = 10;
    $current_page = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
    $offset       = ($current_page - 1) * $per_page;

    // Fetch all orders
    $all_orders = wc_get_orders(array(
        'type'   => 'shop_order',
        'status' => array('wc-processing', 'wc-completed', 'wc-pending', 'wc-on-hold', 'wc-cancelled'),
        'return' => 'ids',
        'limit'  => -1,
    ));

    // Filter orders manually
    $filtered_orders = array();
    foreach ($all_orders as $order_id) {
        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $item_id => $item) {
            $session_date_time   = $item->get_meta('session_date_time');
            $appointment_status  = $item->get_meta('appointment_status') ?: 'N/A';

            if ($session_date_time) {
                $date_time = new DateTime($session_date_time, new DateTimeZone('Asia/Kolkata'));
                $item_date = $date_time->format('Y-m-d');

                $date_filter = true;
                if ($start_date && $end_date) {
                    $date_filter = ($item_date >= $start_date && $item_date <= $end_date);
                } elseif ($start_date) {
                    $date_filter = ($item_date >= $start_date);
                } elseif ($end_date) {
                    $date_filter = ($item_date <= $end_date);
                }

                $status_filter = ($status === '' || $appointment_status === $status);

                if ($date_filter && $status_filter) {
                    $child_id    = $item->get_meta('child_id');
                    $child       = get_user_by('id', $child_id);
                    $child_name  = $child ? $child->display_name . ' (ID: ' . $child_id . ')' : 'Unknown Child';

                    $mentor_id   = $item->get_meta('mentor_id');
                    $mentor      = get_user_by('id', $mentor_id);
                    $mentor_name = $mentor ? $mentor->display_name . ' (ID: ' . $mentor_id . ')' : 'Unknown Mentor';

                    $appointment_title = $item->get_name();

                    $filtered_orders[] = array(
                        'order_id'          => $order_id,
                        'child_name'        => $child_name,
                        'mentor_name'       => $mentor_name,
                        'appointment_title' => $appointment_title,
                        'session_date_time' => $date_time->format('M d, Y — h:i A'),
                        'appointment_status'=> $appointment_status,
                        'item_id'           => $item_id,
                    );
                }
            }
        }
    }

    // Pagination
    $total_items = count($filtered_orders);
    $total_pages = ceil($total_items / $per_page);
    $paged_orders = array_slice($filtered_orders, $offset, $per_page);

    // Get all parents for the add appointment form
    $parents = get_users(array(
        'role' => 'parent_user',
        'orderby' => 'display_name',
        'order' => 'ASC'
    ));

    // Fetch WooCommerce products (sessions)
    $products = get_posts(array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => 'simple',
            ),
        ),
    ));

    ?>
    <div class="wrap manage-appointments-wrap">
        <div class="appointments-header">
            <h1 class="appointments-title">Manage Appointments</h1>
            <span class="appointments-subtitle">View, filter, export, and manage all your scheduled appointments with ease.</span>
        </div>
        <hr class="wp-header-end">

        <!-- Add New Appointment Button -->
        <div class="add-appointment-section" style="margin: 20px;">
            <button type="button" class="button button-primary" id="add-appointment-btn">
                Add New Appointment
            </button>
        </div>

        <!-- Add Appointment Modal -->
        <div id="add-appointment-modal" class="appointment-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add New Appointment</h2>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="add-appointment-form">
                        <?php wp_nonce_field('manage_appointments_nonce', 'nonce'); ?>
                        
                        <div class="form-group">
                            <label for="parent_select">Select Parent *</label>
                            <select id="parent_select" name="parent_id" required>
                                <option value="">-- Select Parent --</option>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?php echo $parent->ID; ?>">
                                        <?php echo esc_html($parent->display_name . ' (' . $parent->user_email . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="child_select">Select Child *</label>
                            <select id="child_select" name="child_id" required disabled>
                                <option value="">-- Select Parent First --</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="mentor_info">Assigned Mentor</label>
                            <div id="mentor_info" class="mentor-info-display">
                                <p>Select a child to see assigned mentor</p>
                            </div>
                            <input type="hidden" id="mentor_id" name="mentor_id" required>
                            <div id="assign-mentor-section" style="display: none; margin-top: 10px;">
                                <a href="#" id="assign-mentor-link" class="button button-secondary">Click here to assign mentor</a>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="session_product">Select Session *</label>
                            <select id="session_product" name="session_product" required disabled>
                                <option value="">-- Select Session First --</option>
                                <?php foreach ($products as $product) : ?>
                                    <option value="<?php echo esc_attr($product->ID); ?>">
                                        <?php echo esc_html($product->post_title . ' - $' . get_post_meta($product->ID, '_price', true)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Select Date & Time *</label>
                            <div class="calendar-container" id="appointment-calendar">
                                <div class="calendar-header">
                                    <select id="month-select"></select>
                                    <select id="year-select"></select>
                                </div>
                                <div class="calendar-grid" id="calendar-days">
                                    <div class="day">Mon</div>
                                    <div class="day">Tue</div>
                                    <div class="day">Wed</div>
                                    <div class="day">Thu</div>
                                    <div class="day">Fri</div>
                                    <div class="day">Sat</div>
                                    <div class="day">Sun</div>
                                </div>
                                <div id="selected-date-text" style="margin: 10px 0; font-weight: bold;"></div>
                                <div class="time-slots" id="time-slots"></div>
                            </div>
                            <input type="hidden" id="selected_slot" name="session_date_time" required>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="button" id="cancel-appointment">Cancel</button>
                            <button type="submit" class="button button-primary" disabled>Create Appointment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="get" action="" class="appointments-filters">
            <input type="hidden" name="page" value="manage-appointments">
            <div class="filter-fields">
                <label>Start Date:
                    <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
                </label>
                <label>End Date:
                    <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
                </label>
                <label>Status:
                    <select name="status">
                        <option value="">All</option>
                        <option value="pending" <?php selected($status, 'pending'); ?>>Pending</option>
                        <option value="approved" <?php selected($status, 'approved'); ?>>Approved</option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>>Cancelled</option>
                        <option value="finished" <?php selected($status, 'finished'); ?>>Finished</option>
                    </select>
                </label>
                <button type="submit" class="button button-primary">Filter</button>
                <div class="export-button" style="padding-left: 10%;">
                    <button type="submit" name="export_csv" value="1" class="csv-export-button">
                        Export as CSV
                    </button>
                </div>
            </div>
        </form>

        <!-- Table -->
        <div class="appointments-table-wrapper">
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th>Sr. No</th>
                        <th>Order Id</th>
                        <th>Child</th>
                        <th>Mentor</th>
                        <th>Appointment</th>
                        <th>Date/Time</th>
                        <th>Status</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sr_no = $offset + 1;
                    foreach ($paged_orders as $appointment) :
                        $order_detail_url = admin_url('post.php?post=' . $appointment['order_id'] . '&action=edit');
                        $view_url = esc_url(add_query_arg(array('order_id' => $appointment['order_id'], 'item_id' => $appointment['item_id']), site_url('/appointment-details/')));

                        // status badge color
                        $status_class = 'status-badge ';
                        switch (strtolower($appointment['appointment_status'])) {
                            case 'approved': $status_class .= 'status-approved'; break;
                            case 'cancelled': $status_class .= 'status-cancelled'; break;
                            case 'finished': $status_class .= 'status-finished'; break;
                            default: $status_class .= 'status-pending'; break;
                        }
                    ?>
                        <tr>
                            <td><?php echo $sr_no++; ?></td>
                            <td><a href="<?php echo esc_url($order_detail_url); ?>" target="_blank"><strong>#<?php echo esc_html($appointment['order_id']); ?></strong></a></td>
                            <td><?php echo ucfirst($appointment['child_name']); ?></td>
                            <td><?php echo ucfirst($appointment['mentor_name']); ?></td>
                            <td><?php echo ucfirst($appointment['appointment_title']); ?></td>
                            <td><?php echo esc_html($appointment['session_date_time']); ?></td>
                            <td><span class="<?php echo $status_class; ?>"><?php echo ucfirst(esc_html($appointment['appointment_status'])); ?></span></td>
                            <td style="text-align:center;">
                                <a href="<?php echo admin_url('admin.php?page=appointment-details&order_id=' . $appointment['order_id']. '&item_id=' . $appointment['item_id']); ?>" class="button button-small">🔍 View</a>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($paged_orders)) : ?>
                        <tr><td colspan="8" style="text-align:center;">No appointments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="appointments-pagination">
            <?php
            echo paginate_links(array(
                'total'   => $total_pages,
                'current' => $current_page,
                'base'    => add_query_arg('paged', '%#%'),
                'format'  => '',
                'prev_text' => __('« Previous'),
                'next_text' => __('Next »'),
                'type'    => 'list', // outputs <ul><li>
            ));
            ?>
        </div>
    </div>

    <style>
        .appointments-filters {
            background: #fff;
            padding: 15px;
            border: 1px solid #ccd0d4;
            border-radius: 6px;
            margin-bottom: 20px;
            margin-top: 20px;
        }
        .appointments-filters .filter-fields {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .appointments-filters label {
            font-weight: 500;
        }
        .appointments-table-wrapper table tbody tr:hover {
            background: #f9f9f9;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-finished { background: #1787ff4f; color: #2271b1; }
        .appointments-pagination {
            text-align: center;
            margin: 25px 0;
        }
        .appointments-pagination ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: inline-flex;
            gap: 6px;
        }
        .appointments-pagination ul li {
            display: inline;
        }
        .appointments-pagination ul li a,
        .appointments-pagination ul li span {
            display: inline-block;
            padding: 6px 12px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            background: #fff;
            color: #0073aa;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        .appointments-pagination ul li a:hover {
            background: #0073aa;
            color: #fff;
        }
        .appointments-pagination ul li span.current {
            background: #0073aa;
            color: #fff;
            border-color: #0073aa;
            font-weight: 600;
        }
        .csv-export-button {
            background-color: #4CAF50;
            color: white;
            border: 1px solid #388E3C;
            border-radius: 5px;
            cursor: pointer;
            min-height: 30px;
            margin: 0;
            padding: 0 10px;
        }
        .export-button :hover {
            background-color: #1d7a21 !important;
        }
        .appointments-header {
            padding: 15px 20px;
            background: linear-gradient(135deg, #ffffff, #519dc1);
            border-radius: 10px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }
        .appointments-title {
            font-size: 26px;
            font-weight: 600 !important;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .appointments-subtitle {
            font-size: 14px;
            font-weight: 400;
            display: block;
            margin-top: 5px;
        }

        /* Modal Styles */
        .appointment-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999999;
        }
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 {
            margin: 0;
            color: #0073aa;
        }
        .close-modal {
            font-size: 28px;
            cursor: pointer;
            color: #aaa;
        }
        .close-modal:hover {
            color: #000;
        }
        .modal-body {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #0073aa;
            box-shadow: 0 0 0 1px #0073aa;
        }
        .form-group input:disabled, .form-group select:disabled {
            background: #f1f1f1;
            color: #666;
        }
        .mentor-info-display {
            padding: 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .mentor-info-display p {
            margin: 0;
            color: #666;
        }
        .mentor-info-display.has-mentor {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        /* Calendar Styles */
        .calendar-container {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .calendar-header select {
            width: auto;
            min-width: 120px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            margin-bottom: 20px;
        }
        .day {
            text-align: center;
            padding: 8px 0;
            font-weight: bold;
            color: #666;
        }
        .date {
            text-align: center;
            padding: 8px 0;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid transparent;
            min-height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .date:hover {
            background: #f0e6dd;
        }
        .date.inactive {
            color: #ccc;
            pointer-events: none;
            background: #f8f9fa;
        }
        .date.selected {
            background: #0073aa;
            color: white;
        }
        .date.today {
            border: 2px solid #0073aa;
        }
        .time-slots {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
        }
        .time-slot {
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            background: #fff;
            font-size: 14px;
            transition: all 0.2s;
        }
        .time-slot:hover {
            background: #f0e6dd;
        }
        .time-slot.inactive {
            color: #ccc;
            background: #f8f9fa;
            pointer-events: none;
        }
        .time-slot.selected {
            background: #0073aa;
            color: white;
        }

        /* Notification styles */
        .admin-notification {
            position: fixed;
            top: 32px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 4px;
            z-index: 999999;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .admin-notification.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .admin-notification.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .admin-notification.loading {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        let mentorWorkingHours = {};
        let bookedSlots = {};
        let currentMentorId = null;
        let selectedDate = null;
        const now = new Date();
        let currentMonth = now.getMonth();
        let currentYear = now.getFullYear();

        const months = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = $('<div class="admin-notification ' + type + '">' + message + '</div>');
            $('body').append(notification);
            setTimeout(() => notification.fadeOut(() => notification.remove()), 5000);
        }

        // Open modal
        $('#add-appointment-btn').on('click', function() {
            $('#add-appointment-modal').show();
            resetForm();
        });

        // Close modal
        $('.close-modal, #cancel-appointment').on('click', function() {
            $('#add-appointment-modal').hide();
        });

        // Close modal on background click
        $('#add-appointment-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });

        // Reset form
        function resetForm() {
            $('#add-appointment-form')[0].reset();
            $('#child_select').prop('disabled', true).html('<option value="">-- Select Parent First --</option>');
            $('#mentor_info').html('<p>Select a child to see assigned mentor</p>').removeClass('has-mentor');
            $('#mentor_id').val('');
            $('#session_product').prop('disabled', true).val('');
            $('#selected_slot').val('');
            $('#assign-mentor-section').hide();
            $('button[type="submit"]').prop('disabled', true);
            clearCalendar();
        }

        // Handle parent selection
        $('#parent_select').on('change', function() {
            const parentId = $(this).val();
            if (parentId) {
                loadChildren(parentId);
            } else {
                $('#child_select').prop('disabled', true).html('<option value="">-- Select Parent First --</option>');
                resetMentorInfo();
            }
        });

        // Load children for selected parent
        function loadChildren(parentId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_children_by_parent',
                    parent_id: parentId,
                    nonce: $('#nonce').val()
                },
                beforeSend: function() {
                    $('#child_select').prop('disabled', true).html('<option value="">Loading...</option>');
                },
                success: function(response) {
                    if (response.success) {
                        let options = '<option value="">-- Select Child --</option>';
                        response.data.forEach(function(child) {
                            options += '<option value="' + child.id + '">' + child.name + '</option>';
                        });
                        $('#child_select').prop('disabled', false).html(options);
                    } else {
                        $('#child_select').html('<option value="">No children found</option>');
                        showNotification('No children found for this parent', 'error');
                    }
                },
                error: function() {
                    $('#child_select').html('<option value="">Error loading children</option>');
                    showNotification('Error loading children', 'error');
                }
            });
        }

        // Handle child selection
        $('#child_select').on('change', function() {
            const childId = $(this).val();
            if (childId) {
                loadMentor(childId);
                // Store child ID for assign mentor link
                $('#assign-mentor-link').data('child-id', childId);
                // Update assign mentor link
                const assignUrl = '<?php echo esc_url(admin_url('admin.php?page=urmentor-child-mentor-assignments')); ?>&child_id=' + childId;
                $('#assign-mentor-link').attr('href', assignUrl);
            } else {
                resetMentorInfo();
            }
        });

        // Load mentor for selected child
        function loadMentor(childId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_mentor_by_child',
                    child_id: childId,
                    nonce: $('#nonce').val()
                },
                beforeSend: function() {
                    $('#mentor_info').html('<p>Loading mentor information...</p>').removeClass('has-mentor');
                    $('#session_product').prop('disabled', true);
                    $('#assign-mentor-section').hide();
                },
                success: function(response) {
                    if (response.success) {
                        const mentor = response.data;
                        $('#mentor_info').addClass('has-mentor').html(
                            '<strong>' + mentor.name + '</strong><br>' +
                            '<small>Email: ' + mentor.email + '</small>'
                        );
                        $('#mentor_id').val(mentor.id);
                        $('#session_product').prop('disabled', false);
                        $('#assign-mentor-section').hide();
                        currentMentorId = mentor.id;
                        loadMentorAvailability();
                    } else {
                        $('#mentor_info').removeClass('has-mentor').html('<p style="color: #721c24;">No mentor assigned to this child</p>');
                        $('#mentor_id').val('');
                        $('#session_product').prop('disabled', true);
                        $('#assign-mentor-section').show();
                        currentMentorId = null;
                        clearCalendar();
                    }
                },
                error: function() {
                    $('#mentor_info').removeClass('has-mentor').html('<p style="color: #721c24;">Error loading mentor information</p>');
                    $('#mentor_id').val('');
                    $('#session_product').prop('disabled', true);
                    $('#assign-mentor-section').show();
                    showNotification('Error loading mentor information', 'error');
                }
            });
        }

        // Reset mentor info
        function resetMentorInfo() {
            $('#mentor_info').removeClass('has-mentor').html('<p>Select a child to see assigned mentor</p>');
            $('#mentor_id').val('');
            $('#session_product').prop('disabled', true);
            $('#assign-mentor-section').hide();
            currentMentorId = null;
            clearCalendar();
        }

        // Load mentor availability
        function loadMentorAvailability() {
            if (!currentMentorId) return;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_mentor_availability',
                    mentor_id: currentMentorId,
                    year: currentYear,
                    month: currentMonth + 1,
                    nonce: $('#nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        mentorWorkingHours = response.data.working_hours || {};
                        bookedSlots = response.data.booked_slots || {};
                        populateCalendar();
                        renderCalendar();
                    } else {
                        showNotification('Error loading mentor availability', 'error');
                    }
                },
                error: function() {
                    showNotification('Error loading mentor availability', 'error');
                }
            });
        }

        // Populate month and year selects
        function populateCalendar() {
            const monthSelect = $('#month-select');
            const yearSelect = $('#year-select');
            
            monthSelect.empty();
            months.forEach((month, index) => {
                monthSelect.append(`<option value="${index}" ${index === currentMonth ? 'selected' : ''}>${month}</option>`);
            });
            
            yearSelect.empty();
            for (let year = currentYear; year <= currentYear + 2; year++) {
                yearSelect.append(`<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`);
            }
        }

        // Handle month/year changes
        $('#month-select, #year-select').on('change', function() {
            currentMonth = parseInt($('#month-select').val());
            currentYear = parseInt($('#year-select').val());
            loadMentorAvailability();
        });

        // Render calendar
        function renderCalendar() {
            const calendarDays = $('#calendar-days');
            calendarDays.find('.date').remove();
            
            const firstDay = new Date(currentYear, currentMonth, 1);
            const startDay = (firstDay.getDay() + 6) % 7; // Make Monday start
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            
            // Previous month dates
            const prevMonthDays = new Date(currentYear, currentMonth, 0).getDate();
            for (let i = startDay; i > 0; i--) {
                calendarDays.append(`<div class="date inactive">${prevMonthDays - i + 1}</div>`);
            }
            
            // Current month dates
            for (let day = 1; day <= daysInMonth; day++) {
                const currentDate = new Date(currentYear, currentMonth, day);
                const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const isPast = currentDate < new Date(now.getFullYear(), now.getMonth(), now.getDate());
                const isToday = currentDate.toDateString() === now.toDateString();
                
                const dayName = currentDate.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
                const dayData = mentorWorkingHours[dayName];
                const isDayOff = !dayData || dayData.off || !dayData.slots || dayData.slots.length === 0;
                
                let dateClass = 'date';
                if (isPast || isDayOff) dateClass += ' inactive';
                if (isToday) dateClass += ' today';
                
                const dateEl = $(`<div class="${dateClass}" data-date="${dateStr}">${day}</div>`);
                
                if (!isPast && !isDayOff) {
                    dateEl.on('click', () => selectDate(dateStr, day));
                }
                
                calendarDays.append(dateEl);
            }
            
            // Next month dates
            const totalCells = startDay + daysInMonth;
            const nextDays = (7 - (totalCells % 7)) % 7;
            for (let i = 1; i <= nextDays; i++) {
                calendarDays.append(`<div class="date inactive">${i}</div>`);
            }
        }

        // Select date
        function selectDate(dateStr, day) {
            $('.date').removeClass('selected');
            $(`.date[data-date="${dateStr}"]`).addClass('selected');
            
            selectedDate = dateStr;
            const dateObj = new Date(dateStr);
            $('#selected-date-text').text(dateObj.toDateString());
            
            renderTimeSlots(dateStr);
        }

        // Render time slots
        function renderTimeSlots(dateStr) {
            const timeSlotsContainer = $('#time-slots');
            const submitButton = $('button[type="submit"]');
            timeSlotsContainer.empty();
            submitButton.prop('disabled', true);
            
            if (!selectedDate || !currentMentorId) return;
            
            const selectedDateObj = new Date(dateStr);
            const dayName = selectedDateObj.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
            const dayData = mentorWorkingHours[dayName];
            
            if (!dayData || dayData.off || !dayData.slots || dayData.slots.length === 0) return;
            
            const bookedTimes = bookedSlots[dateStr] || [];
            const isToday = selectedDateObj.toDateString() === now.toDateString();
            
            dayData.slots.forEach(slot => {
                if (!slot.start_time || !slot.end_time) return;
                
                let currentTime = new Date(`2000-01-01 ${slot.start_time}`);
                const endTime = new Date(`2000-01-01 ${slot.end_time}`);
                
                while (currentTime < endTime) {
                    const slotStart = currentTime.toTimeString().slice(0, 5);
                    const slotEndTime = new Date(currentTime.getTime() + 60 * 60 * 1000);
                    const slotEnd = slotEndTime.toTimeString().slice(0, 5);
                    
                    if (slotEndTime > endTime) break;
                    
                    const slotTime = `${slotStart} - ${slotEnd}`;
                    const slotDateTime = `${dateStr} ${slotStart}:00`;
                    
                    // Check if slot is in the past for today
                    const isPast = isToday && new Date(`${dateStr}T${slotStart}:00`) < now;
                    
                    // Check if slot is booked
                    const isBooked = bookedTimes.includes(slotStart);
                    
                    let slotClass = 'time-slot';
                    if (isPast || isBooked) slotClass += ' inactive';
                    
                    const slotEl = $(`<div class="${slotClass}" data-slot="${slotDateTime}">${slotTime}</div>`);
                    
                    if (!isPast && !isBooked) {
                        slotEl.on('click', () => selectTimeSlot(slotDateTime, slotEl));
                    }
                    
                    timeSlotsContainer.append(slotEl);
                    currentTime = new Date(currentTime.getTime() + 60 * 60 * 1000);
                }
            });
        }

        // Select time slot
        function selectTimeSlot(slotDateTime, slotEl) {
            $('.time-slot').removeClass('selected');
            slotEl.addClass('selected');
            $('#selected_slot').val(slotDateTime);
            checkFormValidity();
        }

        // Check form validity to enable/disable submit button
        function checkFormValidity() {
            const parentId = $('#parent_select').val();
            const childId = $('#child_select').val();
            const mentorId = $('#mentor_id').val();
            const sessionProduct = $('#session_product').val();
            const sessionDateTime = $('#selected_slot').val();
            
            $('button[type="submit"]').prop('disabled', !(parentId && childId && mentorId && sessionProduct && sessionDateTime));
        }

        // Clear calendar
        function clearCalendar() {
            $('#calendar-days').find('.date').remove();
            $('#time-slots').empty();
            $('#selected-date-text').text('');
            selectedDate = null;
        }

        // Handle form field changes to check validity
        $('#parent_select, #child_select, #mentor_id, #session_product, #selected_slot').on('change', checkFormValidity);

        // Handle form submission
        $('#add-appointment-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create_new_appointment');
            
            showNotification('Creating appointment...', 'loading');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotification(response.data.message, 'success');
                        $('#add-appointment-modal').hide();
                        // Refresh the page to show new appointment
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showNotification('Error: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    showNotification('Error creating appointment', 'error');
                }
            });
        });

        // Initialize calendar when modal opens
        $('#add-appointment-modal').on('show', function() {
            populateCalendar();
        });
    });
    </script>

    <?php
}

/**
 * Exports the filtered appointments data to a CSV file.
 *
 * @since 1.0.0
 */
function export_appointments_to_csv() {
    global $wpdb;

    // Start output buffering to capture and control all output
    ob_start();
    // Handle filters from GET
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date   = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    $status     = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

    // Fetch all orders
    $all_orders = wc_get_orders(array(
        'type'   => 'shop_order',
        'status' => array('wc-processing', 'wc-completed', 'wc-pending', 'wc-on-hold', 'wc-cancelled'),
        'return' => 'ids',
        'limit'  => -1,
    ));

    // Filter orders manually
    $filtered_orders = array();
    foreach ($all_orders as $order_id) {
        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $item_id => $item) {
            $session_date_time   = $item->get_meta('session_date_time');
            $appointment_status  = $item->get_meta('appointment_status') ?: 'N/A';

            if ($session_date_time) {
                $date_time = new DateTime($session_date_time, new DateTimeZone('Asia/Kolkata'));
                $item_date = $date_time->format('Y-m-d');

                $date_filter = true;
                if ($start_date && $end_date) {
                    $date_filter = ($item_date >= $start_date && $item_date <= $end_date);
                } elseif ($start_date) {
                    $date_filter = ($item_date >= $start_date);
                } elseif ($end_date) {
                    $date_filter = ($item_date <= $end_date);
                }

                $status_filter = ($status === '' || $appointment_status === $status);

                if ($date_filter && $status_filter) {
                    $child_id    = $item->get_meta('child_id');
                    $child       = get_user_by('id', $child_id);
                    $child_name  = $child ? $child->display_name . ' (ID: ' . $child_id . ')' : 'Unknown Child';

                    $mentor_id   = $item->get_meta('mentor_id');
                    $mentor      = get_user_by('id', $mentor_id);
                    $mentor_name = $mentor ? $mentor->display_name . ' (ID: ' . $mentor_id . ')' : 'Unknown Mentor';

                    $appointment_title = $item->get_name();

                    $filtered_orders[] = array(
                        'order_id'          => $order_id,
                        'child_name'        => $child_name,
                        'mentor_name'       => $mentor_name,
                        'appointment_title' => $appointment_title,
                        'session_date_time' => $date_time->format('M d, Y — h:i A'),
                        'appointment_status'=> $appointment_status,
                        'item_id'           => $item_id,
                    );
                }
            }
        }
    }

    // Clean the buffer to remove any prior output
    ob_end_clean();

    // Generate CSV
    $filename = 'sessions-report-' . date('d-m-Y') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputcsv($output, array('Sr. No', 'Order Id', 'Child Name', 'Mentor Name', 'Appointment Title', 'Appointment Date/Time', 'Appointment Status'));

    $sr_no = 1;
    foreach ($filtered_orders as $appointment) {
        fputcsv($output, array(
            $sr_no++,
            '#' . $appointment['order_id'],
            ucfirst($appointment['child_name']),
            ucfirst($appointment['mentor_name']),
            ucfirst($appointment['appointment_title']),
            $appointment['session_date_time'],
            ucfirst($appointment['appointment_status']),
        ));
    }

    fclose($output);
    exit;
}