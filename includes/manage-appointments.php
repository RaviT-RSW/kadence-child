<?php
/**
 * Adds a custom admin page to the WordPress dashboard for managing WooCommerce-based appointment orders with filters and pagination.
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
        20
    );
}
add_action('admin_menu', 'register_manage_appointments_menu');

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

    ?>
    <div class="wrap manage-appointments-wrap">
        <h1 class="wp-heading-inline">Manage Appointments</h1>
        <hr class="wp-header-end">

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
    </style>
    <?php
}
