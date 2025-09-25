<?php
/**
 * Parent Invoice Overview Dashboard
 * This creates a new page that lists all parents with their summary data
 * and provides quick links to generate invoices for each parent.
 */

// Add admin menu for Parent Invoice Overview
add_action('admin_menu', 'urmentor_add_parent_invoice_overview_menu');
function urmentor_add_parent_invoice_overview_menu() {
    add_submenu_page(
        'urmentor-manage-users',
        'Parent Invoice Overview',
        'Parent Overview',
        'manage_options',
        'urmentor-parent-overview',
        'urmentor_parent_overview_page'
    );
}

/**
 * Get parent monthly summary for overview
 */
function urmentor_get_parent_monthly_summary($parent_id, $year, $month) {
    $children_ids = get_users(array(
        'role' => 'child_user',
        'meta_key' => 'assigned_parent_id',
        'meta_value' => $parent_id,
        'fields' => 'ID'
    ));

    if (empty($children_ids)) {
        return array(
            'appointment_count' => 0,
            'total_amount' => 0,
            'children_count' => 0,
            'has_data' => false,
            'has_paid' => false
        );
    }

    $start_date = new DateTime("$year-$month-01 00:00:00", new DateTimeZone('Asia/Kolkata'));
    $end_date = clone $start_date;
    $end_date->modify('last day of this month')->setTime(23, 59, 59);

    $args = array(
        'status' => array('wc-processing', 'wc-on-hold', 'wc-pending', 'wc-completed'),
        'limit' => -1,
    );

    $orders = wc_get_orders($args);
    $appointment_count = 0;
    $total_amount = 0;
    $has_paid = false;

    // Check for existing master order to determine payment status
    $master_order = urmentor_get_existing_master_order($parent_id, $year, $month);
    if ($master_order && ($master_order->get_status() === 'processing' || $master_order->get_status() === 'completed')) {
        $has_paid = true;
    }

    foreach ($orders as $order) {
        // Skip master orders in calculation
        if ($order->get_meta('is_monthly_invoice', true)) {
            continue;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $child_id = $item->get_meta('child_id');
            $session_date_time_str = $item->get_meta('session_date_time');
            $appointment_status = $item->get_meta('appointment_status');

            if (!in_array($child_id, $children_ids) || empty($session_date_time_str) || $appointment_status === 'cancelled') {
                continue;
            }

            $session_date_time = new DateTime($session_date_time_str, new DateTimeZone('Asia/Kolkata'));

            if ($session_date_time >= $start_date && $session_date_time <= $end_date) {
                $price = $item->get_total();
                $appointment_count++;
                $total_amount += $price;
            }
        }
    }

    return array(
        'appointment_count' => $appointment_count,
        'total_amount' => $total_amount,
        'children_count' => count($children_ids),
        'has_data' => ($appointment_count > 0),
        'has_paid' => $has_paid
    );
}

/**
 * Parent Overview Page Handler
 */
function urmentor_parent_overview_page() {
    $current_year = date('Y');
    $current_month = date('n');
    $years = range($current_year - 5, $current_year + 1);
    $months = array(
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    );

    // Get filter values
    $selected_year = isset($_GET['filter_year']) ? intval($_GET['filter_year']) : $current_year;
    $selected_month = isset($_GET['filter_month']) ? intval($_GET['filter_month']) : $current_month;

    // Get all parents
    $parents = get_users(array('role' => 'parent_user'));
    $parent_data = array();

    // Get summary data for each parent
    foreach ($parents as $parent) {
        $summary = urmentor_get_parent_monthly_summary($parent->ID, $selected_year, $selected_month);
        $parent_data[] = array(
            'parent' => $parent,
            'summary' => $summary
        );
    }

    // Calculate totals
    $grand_total_appointments = 0;
    $grand_total_amount = 0;
    $parents_with_data = 0;

    foreach ($parent_data as $data) {
        if ($data['summary']['has_data']) {
            $grand_total_appointments += $data['summary']['appointment_count'];
            $grand_total_amount += $data['summary']['total_amount'];
            $parents_with_data++;
        }
    }

    ?>
    <style>
        .overview-container {
            margin: 20px 0;
        }
        
        .filter-section {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .filter-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }
        
        .filter-form {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-form select {
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .summary-card h4 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            font-weight: normal;
        }
        
        .summary-card .value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .parent-overview-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .parent-overview-table th,
        .parent-overview-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .parent-overview-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
            position: sticky;
            top: 0;
        }
        
        .parent-overview-table .text-right {
            text-align: right;
        }
        
        .parent-overview-table .text-center {
            text-align: center;
        }
        
        .parent-overview-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .parent-overview-table tbody tr.no-data {
            opacity: 0.6;
            background-color: #fafafa;
        }
        
        .parent-overview-table tbody tr.no-data:hover {
            background-color: #fafafa;
        }
        
        .action-button {
            background: #0073aa;
            color: white;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .action-button:hover {
            background: #005a87;
            color: white;
        }
        
        .action-button.disabled {
            background: #ccc;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .totals-row {
            background-color: #e9ecef !important;
            font-weight: bold;
            border-top: 2px solid #007cba;
        }
        
        .totals-row td {
            border-bottom: 2px solid #007cba !important;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-active {
            background-color: #46b450;
        }
        
        .status-inactive {
            background-color: #dc3232;
        }
    </style>

    <div class="wrap">
        <h1>Parent Invoice Overview</h1>
        
        <div class="overview-container">
            <!-- Filter Section -->
            <div class="filter-section">
                <h3>Filter by Month & Year</h3>
                <form method="get" class="filter-form">
                    <input type="hidden" name="page" value="urmentor-parent-overview">
                    <label for="filter_month">Month:</label>
                    <select name="filter_month" id="filter_month">
                        <?php foreach ($months as $num => $name): ?>
                            <option value="<?php echo esc_attr($num); ?>" <?php selected($selected_month, $num); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label for="filter_year">Year:</label>
                    <select name="filter_year" id="filter_year">
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo esc_attr($year); ?>" <?php selected($selected_year, $year); ?>>
                                <?php echo esc_html($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h4>Total Parents</h4>
                    <div class="value"><?php echo count($parents); ?></div>
                </div>
                <div class="summary-card">
                    <h4>Parents with Activity</h4>
                    <div class="value"><?php echo $parents_with_data; ?></div>
                </div>
                <div class="summary-card">
                    <h4>Total Appointments</h4>
                    <div class="value"><?php echo $grand_total_appointments; ?></div>
                </div>
                <div class="summary-card">
                    <h4>Total Revenue</h4>
                    <div class="value"><?php echo number_format($grand_total_amount, 2); ?> AED</div>
                </div>
            </div>

            <!-- Parent Data Table -->
            <table class="parent-overview-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Parent Name</th>
                        <th>Email</th>
                        <th class="text-center">Children Count</th>
                        <th class="text-center">Appointments</th>
                        <th class="text-right">Total Amount (AED)</th>
                        <th class="text-center">Payment Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parent_data as $data): 
                        $parent = $data['parent'];
                        $summary = $data['summary'];
                        $has_data = $summary['has_data'];
                        $row_class = $has_data ? '' : 'no-data';
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td>
                            <span class="status-indicator <?php echo $has_data ? 'status-active' : 'status-inactive'; ?>"></span>
                        </td>
                        <td>
                            <strong><?php echo esc_html($parent->display_name); ?></strong>
                            <?php if (!$has_data): ?>
                                <br><small style="color: #666;">No activity this month</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($parent->user_email); ?></td>
                        <td class="text-center">
                            <?php echo $summary['children_count']; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $has_data ? $summary['appointment_count'] : '-'; ?>
                        </td>
                        <td class="text-right">
                            <?php echo $has_data ? number_format($summary['total_amount'], 2) : '0.00'; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($has_data): ?>
                                <?php if ($summary['has_paid']): ?>
                                    <span style="color: #46b450; font-weight: bold;">Paid</span>
                                <?php else: ?>
                                    <span style="color: #dc3232; font-weight: bold;">Pending</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #666;">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($has_data): ?>
                                <a href="<?php echo admin_url('admin.php?page=urmentor-monthly-invoices&tab=parent-invoices&parent_id=' . $parent->ID . '&invoice_year=' . $selected_year . '&invoice_month=' . $selected_month); ?>" 
                                   class="action-button">Generate Invoice</a>
                            <?php else: ?>
                                <span class="action-button disabled">No Data</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Totals Row -->
                    <?php if ($parents_with_data > 0): ?>
                    <tr class="totals-row">
                        <td colspan="4"><strong>TOTALS</strong></td>
                        <td class="text-center"><strong><?php echo $grand_total_appointments; ?></strong></td>
                        <td class="text-right"><strong><?php echo number_format($grand_total_amount, 2); ?></strong></td>
                        <td colspan="2">-</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (empty($parents)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No parents found</h3>
                    <p>There are no users with the 'parent_user' role in your system.</p>
                </div>
            <?php elseif ($parents_with_data == 0): ?>
                <div style="text-align: center; padding: 40px; color: #666; background: #f9f9f9; margin-top: 20px; border-radius: 5px;">
                    <h3>No activity found</h3>
                    <p>No parents have appointments for <strong><?php echo $months[$selected_month] . ' ' . $selected_year; ?></strong></p>
                    <p>Try selecting a different month or year, or ensure appointments have been completed.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Auto-submit form when filters change
        $('#filter_month, #filter_year').change(function() {
            $(this).closest('form').submit();
        });
    });
    </script>
    <?php
}
