<?php
/**
 * Mentor Invoice Overview Dashboard
 * This creates a new page that lists all mentors with their summary data
 * and provides quick links to generate invoices for each mentor.
 */

// Add admin menu for Mentor Invoice Overview
add_action('admin_menu', 'urmentor_add_mentor_invoice_overview_menu');
function urmentor_add_mentor_invoice_overview_menu() {
    add_submenu_page(
        'urmentor-manage-users',
        'Mentor Invoice Overview',
        'Mentor Overview',
        'manage_options',
        'urmentor-mentor-overview',
        'urmentor_mentor_overview_page'
    );
}

/**
 * Get mentor summary data for a specific month/year
 */
function urmentor_get_mentor_monthly_summary($mentor_id, $year, $month) {
    $start_date = new DateTime("$year-$month-01 00:00:00", new DateTimeZone('Asia/Kolkata'));
    $end_date = clone $start_date;
    $end_date->modify('last day of this month')->setTime(23, 59, 59);

    $args = array(
        'status' => array('wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending'),
        'limit' => -1,
    );

    $orders = wc_get_orders($args);

    $total_hours = 0;
    $total_amount = 0;
    $total_expense = 0;
    $appointment_count = 0;
    $hourly_rate = get_user_meta($mentor_id, 'mentor_hourly_rate', true);
    
    if (empty($hourly_rate)) {
        return array(
            'total_hours' => 0,
            'total_amount' => 0,
            'total_expense' => 0,
            'net_amount' => 0,
            'appointment_count' => 0,
            'hourly_rate' => 0,
            'has_data' => false
        );
    }

    $processed_items = array();
    global $wpdb;

    foreach ($orders as $order) {
        if ($order->get_meta('is_monthly_invoice', true)) {
            continue;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $item_mentor_id = $item->get_meta('mentor_id');
            $session_date_time_str = $item->get_meta('session_date_time');
            $appointment_status = $item->get_meta('appointment_status');

            if ($item_mentor_id != $mentor_id || empty($session_date_time_str) || 
                $appointment_status === 'cancelled' || in_array($item_id, $processed_items)) {
                continue;
            }

            $session_date_time = new DateTime($session_date_time_str, new DateTimeZone('Asia/Kolkata'));

            if ($session_date_time >= $start_date && $session_date_time <= $end_date) {
                $duration = 1; // Assuming 1 hour per appointment
                $amount = $hourly_rate * $duration;

                // Fetch expenses for this order
                $expenses = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT expense_amount FROM wp_appointment_expense 
                         WHERE order_id = %d AND expense_status = 'approved'",
                        $order->get_id()
                    )
                );

                $expense_amount = 0;
                if (!empty($expenses)) {
                    foreach ($expenses as $expense) {
                        $expense_amount += $expense->expense_amount;
                    }
                }

                $total_hours += $duration;
                $total_amount += $amount;
                $total_expense += $expense_amount;
                $appointment_count++;
                $processed_items[] = $item_id;
            }
        }
    }

    $net_amount = $total_amount + $total_expense;

    return array(
        'total_hours' => $total_hours,
        'total_amount' => $total_amount,
        'total_expense' => $total_expense,
        'net_amount' => $net_amount,
        'appointment_count' => $appointment_count,
        'hourly_rate' => $hourly_rate,
        'has_data' => ($appointment_count > 0)
    );
}

/**
 * Mentor Overview Page Handler
 */
function urmentor_mentor_overview_page() {
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

    // Get all mentors
    $mentors = get_users(array('role' => 'mentor_user'));
    $mentor_data = array();

    // Get summary data for each mentor
    foreach ($mentors as $mentor) {
        $summary = urmentor_get_mentor_monthly_summary($mentor->ID, $selected_year, $selected_month);
        $mentor_data[] = array(
            'mentor' => $mentor,
            'summary' => $summary
        );
    }

    // Calculate totals
    $grand_total_hours = 0;
    $grand_total_amount = 0;
    $grand_total_expense = 0;
    $grand_net_amount = 0;
    $mentors_with_data = 0;

    foreach ($mentor_data as $data) {
        if ($data['summary']['has_data']) {
            $grand_total_hours += $data['summary']['total_hours'];
            $grand_total_amount += $data['summary']['total_amount'];
            $grand_total_expense += $data['summary']['total_expense'];
            $grand_net_amount += $data['summary']['net_amount'];
            $mentors_with_data++;
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
        
        .mentor-overview-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .mentor-overview-table th,
        .mentor-overview-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .mentor-overview-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
            position: sticky;
            top: 0;
        }
        
        .mentor-overview-table .text-right {
            text-align: right;
        }
        
        .mentor-overview-table .text-center {
            text-align: center;
        }
        
        .mentor-overview-table tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        .mentor-overview-table tbody tr.no-data {
            opacity: 0.6;
            background-color: #fafafa;
        }
        
        .mentor-overview-table tbody tr.no-data:hover {
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
        <h1>Mentor Invoice Overview</h1>
        
        <div class="overview-container">
            <!-- Filter Section -->
            <div class="filter-section">
                <h3>Filter by Month & Year</h3>
                <form method="get" class="filter-form">
                    <input type="hidden" name="page" value="urmentor-mentor-overview">
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
                    <h4>Total Mentors</h4>
                    <div class="value"><?php echo count($mentors); ?></div>
                </div>
                <div class="summary-card">
                    <h4>Total Hours</h4>
                    <div class="value"><?php echo $grand_total_hours; ?></div>
                </div>
                <div class="summary-card">
                    <h4>Total Amount</h4>
                    <div class="value"><?php echo number_format($grand_total_amount, 2); ?> AED</div>
                </div>
                <div class="summary-card">
                    <h4>Total Expenses</h4>
                    <div class="value"><?php echo number_format($grand_total_expense, 2); ?> AED</div>
                </div>
                <div class="summary-card">
                    <h4>Net Payout</h4>
                    <div class="value"><?php echo number_format($grand_net_amount, 2); ?> AED</div>
                </div>
            </div>

            <!-- Mentor Data Table -->
            <table class="mentor-overview-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Mentor Name</th>
                        <th>Email</th>
                        <th class="text-center">Appointments</th>
                        <th class="text-center">Total Hours</th>
                        <th class="text-right">Hourly Rate (AED)</th>
                        <th class="text-right">Total Amount (AED)</th>
                        <th class="text-right">Total Expenses (AED)</th>
                        <th class="text-right">Net Amount (AED)</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mentor_data as $data): 
                        $mentor = $data['mentor'];
                        $summary = $data['summary'];
                        $has_data = $summary['has_data'];
                        $row_class = $has_data ? '' : 'no-data';
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td>
                            <span class="status-indicator <?php echo $has_data ? 'status-active' : 'status-inactive'; ?>"></span>
                        </td>
                        <td>
                            <strong><?php echo esc_html($mentor->display_name); ?></strong>
                            <?php if (!$has_data): ?>
                                <br><small style="color: #666;">No activity this month</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($mentor->user_email); ?></td>
                        <td class="text-center">
                            <?php echo $has_data ? $summary['appointment_count'] : '-'; ?>
                        </td>
                        <td class="text-center">
                            <?php echo $has_data ? $summary['total_hours'] : '-'; ?>
                        </td>
                        <td class="text-right">
                            <?php echo $summary['hourly_rate'] > 0 ? number_format($summary['hourly_rate'], 2) : 'Not Set'; ?>
                        </td>
                        <td class="text-right">
                            <?php echo $has_data ? number_format($summary['total_amount'], 2) : '0.00'; ?>
                        </td>
                        <td class="text-right">
                            <?php echo $has_data ? number_format($summary['total_expense'], 2) : '0.00'; ?>
                        </td>
                        <td class="text-right">
                            <strong><?php echo $has_data ? number_format($summary['net_amount'], 2) : '0.00'; ?></strong>
                        </td>
                        <td class="text-center">
                            <?php if ($has_data && $summary['hourly_rate'] > 0): ?>
                                <a href="<?php echo admin_url('admin.php?page=urmentor-mentor-invoices&mentor_id=' . $mentor->ID . '&invoice_year=' . $selected_year . '&invoice_month=' . $selected_month); ?>" 
                                   class="action-button">Generate Invoice</a>
                            <?php else: ?>
                                <span class="action-button disabled">No Data</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Totals Row -->
                    <?php if ($mentors_with_data > 0): ?>
                    <tr class="totals-row">
                        <td colspan="3"><strong>TOTALS</strong></td>
                        <td class="text-center"><strong><?php 
                            $total_appointments = 0;
                            foreach ($mentor_data as $data) {
                                if ($data['summary']['has_data']) {
                                    $total_appointments += $data['summary']['appointment_count'];
                                }
                            }
                            echo $total_appointments;
                        ?></strong></td>
                        <td class="text-center"><strong><?php echo $grand_total_hours; ?></strong></td>
                        <td class="text-right">-</td>
                        <td class="text-right"><strong><?php echo number_format($grand_total_amount, 2); ?></strong></td>
                        <td class="text-right"><strong><?php echo number_format($grand_total_expense, 2); ?></strong></td>
                        <td class="text-right"><strong><?php echo number_format($grand_net_amount, 2); ?></strong></td>
                        <td class="text-center">-</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if (empty($mentors)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>No mentors found</h3>
                    <p>There are no users with the 'mentor_user' role in your system.</p>
                </div>
            <?php elseif ($mentors_with_data == 0): ?>
                <div style="text-align: center; padding: 40px; color: #666; background: #f9f9f9; margin-top: 20px; border-radius: 5px;">
                    <h3>No activity found</h3>
                    <p>No mentors have appointments or activity for <strong><?php echo $months[$selected_month] . ' ' . $selected_year; ?></strong></p>
                    <p>Try selecting a different month or year, or ensure mentors have completed appointments.</p>
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