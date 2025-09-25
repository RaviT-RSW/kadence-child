<?php
/**
 * Mentor Invoice Functionality
 * This adds a new submenu for Mentor Invoices, similar to Parent Invoices.
 * It fetches mentor's appointments, calculates total time (assuming 1 hour per appointment),
 * and computes total amount based on hourly rate. Now includes expense data linked to appointments.
 */

// Add admin menu for Mentor Invoices
add_action('admin_menu', 'urmentor_add_user_management_menu_mentor_invoice');
function urmentor_add_user_management_menu_mentor_invoice() {
    add_submenu_page(
        'urmentor-manage-users',
        'Mentor Invoices',
        'Mentor Invoices',
        'manage_options',
        'urmentor-mentor-invoices',
        'urmentor_mentor_invoices_page'
    );
}

/**
 * Send Mentor Invoice Email with PDF Attachment
 */
function urmentor_send_mentor_invoice_email($mentor_id, $year, $month, $appointments_data) {
    $mentor = get_user_by('id', $mentor_id);
    if (!$mentor) {
        return new WP_Error('invalid_mentor', 'Invalid mentor user.');
    }

    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    $mentor_name = !empty($mentor->display_name) ? $mentor->display_name : ($mentor->user_nicename ?: $mentor->user_login);
    $filename = sanitize_file_name($mentor_name) . '_' . strtolower($month_name) . '_' . $year . '.pdf';

    // Generate PDF
    $tcpdf_path = ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php';
    if (!file_exists($tcpdf_path)) {
        return new WP_Error('tcpdf_missing', 'TCPDF library not found.');
    }

    require_once $tcpdf_path;
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false); // Use portrait orientation
    $pdf->SetCreator('UrMentor');
    $pdf->SetAuthor('UrMentor');
    $pdf->SetTitle('Mentor_Invoice_' . $mentor_name . '_' . $month_name . '_' . $year);
    $pdf->SetMargins(15, 20, 15);
    $pdf->SetAutoPageBreak(TRUE, 20); // Add consistent page break margin
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10, '', true);

    // Generate HTML for PDF with dynamic logo path
    $logo_url = wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full') ?: home_url('/wp-content/uploads/2025/08/URMENTOR-WP-LOGO-1.png');
    $html = urmentor_generate_mentor_pdf_html($mentor_id, $year, $month, $appointments_data, $logo_url);
    $pdf->writeHTML($html, true, false, true, false, '');

    // Save PDF to temporary file
    $temp_dir = sys_get_temp_dir();
    $pdf_path = $temp_dir . '/' . $filename;
    $pdf->Output($pdf_path, 'F');

    // Prepare email replacements
    $total_amount = $appointments_data['total_amount'];
    $total_time = $appointments_data['total_time'];
    $total_expense = $appointments_data['total_expense'];
    $net_amount = $total_amount + $total_expense;
    $replacements = [
        'name' => esc_html($mentor_name),
        'month_name' => esc_html($month_name),
        'year' => $year,
        'payment_section' => '<div class="payment-section"><h3>Invoice Details</h3><p><strong>Total Hours:</strong> ' . $total_time . '</p><p><strong>Total Amount:</strong> ' . number_format($total_amount, 2) . ' AED</p><p><strong>Total Expenses:</strong> ' . number_format($total_expense, 2) . ' AED</p><p><strong>Net Amount:</strong> ' . number_format($net_amount, 2) . ' AED</p><p><small>This is your monthly payout invoice. Contact admin for payment details.</small></p></div>'
    ];

    // Build email body
    $body = build_email_body('invoice-email-template.html', $replacements);
    if (empty($body)) {
        return new WP_Error('template_failed', 'Failed to load email template.');
    }

    // Prepare email
    $to = $mentor->user_email;
    $subject = 'UrMentor Monthly Payout Invoice - ' . $month_name . ' ' . $year;
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: UrMentor <admin@urmentor.com>'
    );
    $attachments = array($pdf_path);

    // Send email
    $result = wp_mail($to, $subject, $body, $headers, $attachments);

    // Clean up temporary file
    if (file_exists($pdf_path)) {
        unlink($pdf_path);
    }

    return $result ? true : new WP_Error('email_failed', 'Failed to send invoice email.');
}

/**
 * Get Monthly Appointments and Expenses for a Mentor
 */
function urmentor_get_mentor_monthly_appointments($mentor_id, $year, $month) {
    $start_date = new DateTime("$year-$month-01 00:00:00", new DateTimeZone('Asia/Kolkata'));
    $end_date = clone $start_date;
    $end_date->modify('last day of this month')->setTime(23, 59, 59);

    $args = array(
        'status' => array('wc-processing', 'wc-completed', 'wc-on-hold', 'wc-pending'), // Paid orders
        'limit' => -1,
    );

    $orders = wc_get_orders($args);

    $appointments = array();
    $total_time = 0;
    $total_amount = 0;
    $total_expense = 0;
    $hourly_rate = get_user_meta($mentor_id, 'mentor_hourly_rate', true);
    if (empty($hourly_rate)) {
        return array(
            'appointments' => array(),
            'total_time' => 0,
            'total_amount' => 0,
            'total_expense' => 0,
            'error' => 'No hourly rate set for mentor.'
        );
    }

    $processed_items = array(); // To track processed order items and avoid duplicates
    global $wpdb;

    foreach ($orders as $order) {
        // Skip if this is a master order for parent payment
        if ($order->get_meta('is_monthly_invoice', true)) {
            continue;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $item_mentor_id = $item->get_meta('mentor_id');
            $session_date_time_str = $item->get_meta('session_date_time');
            $appointment_status = $item->get_meta('appointment_status');

            // Skip if not matching mentor, empty date, cancelled, or already processed
            if ($item_mentor_id != $mentor_id || empty($session_date_time_str) || $appointment_status === 'cancelled' || in_array($item_id, $processed_items)) {
                continue;
            }

            $session_date_time = new DateTime($session_date_time_str, new DateTimeZone('Asia/Kolkata'));

            if ($session_date_time >= $start_date && $session_date_time <= $end_date) {
                $child_id = $item->get_meta('child_id');
                $child = get_user_by('id', $child_id);
                $parent_id = get_user_meta($child_id, 'assigned_parent_id', true);
                $parent = get_user_by('id', $parent_id);

                $duration = 1; // Assuming 1 hour per appointment
                $amount = $hourly_rate * $duration;

                // Fetch expenses for this order
                $expenses = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT expense_id, expense_amount, expense_description, created_at 
                         FROM wp_appointment_expense 
                         WHERE order_id = %d AND expense_status = 'approved'",
                        $order->get_id()
                    )
                );
                $expense_amount = 0;
                $expense_description = '';
                if (!empty($expenses)) {
                    foreach ($expenses as $expense) {
                        $expense_amount += $expense->expense_amount;
                        $expense_description .= ($expense_description ? ', ' : '') . $expense->expense_description;
                    }
                }

                $appointments[] = array(
                    'date_time' => $session_date_time->format('Y-m-d H:i:s'),
                    'child_name' => $child ? $child->display_name : 'Unknown',
                    'parent_name' => $parent ? $parent->display_name : 'Unknown',
                    'status' => $appointment_status ?: 'N/A',
                    'duration' => $duration,
                    'rate' => $hourly_rate,
                    'amount' => $amount,
                    'order_id' => $order->get_id(),
                    'item_id' => $item_id,
                    'expense_amount' => $expense_amount,
                    'expense_description' => $expense_description,
                );

                $total_time += $duration;
                $total_amount += $amount;
                $total_expense += $expense_amount;
                $processed_items[] = $item_id; // Mark this item as processed
            }
        }
    }

    $net_amount = $total_amount + $total_expense;

    return array(
        'appointments' => $appointments,
        'total_time' => $total_time,
        'total_amount' => $total_amount,
        'total_expense' => $total_expense,
        'net_amount' => $net_amount,
    );
}

/**
 * Mentor Invoices Page Handler
 */
function urmentor_mentor_invoices_page() {
    $message = '';
    $invoice_html = '';
    $current_year = date('Y');
    $current_month = date('n');
    $years = range($current_year - 5, $current_year + 1);
    $months = array(
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    );
    $mentors = get_users(array('role' => 'mentor_user'));

    // Preselect values
    $selected_year = isset($_GET['invoice_year']) ? intval($_GET['invoice_year']) : $current_year;
    $selected_month = isset($_GET['invoice_month']) ? intval($_GET['invoice_month']) : $current_month;
    $selected_mentor_id = isset($_GET['mentor_id']) ? intval($_GET['mentor_id']) : (!empty($mentors) ? $mentors[0]->ID : 0);

    ob_start(); // Start output buffering

    // Handle form submission for generating invoice
    if (isset($_POST['generate_invoice']) && wp_verify_nonce($_POST['invoice_nonce'], 'generate_invoice')) {
        $selected_year = intval($_POST['invoice_year']);
        $selected_month = intval($_POST['invoice_month']);
        $selected_mentor_id = intval($_POST['mentor_id']);

        $appointments_data = urmentor_get_mentor_monthly_appointments($selected_mentor_id, $selected_year, $selected_month);

        if (isset($appointments_data['error'])) {
            $message = '<div class="notice notice-error is-dismissible"><p>' . $appointments_data['error'] . '</p></div>';
        } elseif (empty($appointments_data['appointments'])) {
            $message = '<div class="notice notice-info is-dismissible><p>No appointments found for this month.</p></div>';
        } else {
            $message = '<div class="notice notice-success is-dismissible"><p>Invoice generated successfully!</p></div>';
            $invoice_html = urmentor_generate_mentor_invoice_html($selected_mentor_id, $selected_year, $selected_month, $appointments_data);
        }
    }

    // Handle PDF download
    if (isset($_POST['download_invoice']) && wp_verify_nonce($_POST['download_nonce'], 'download_invoice')) {
        $selected_year = intval($_POST['invoice_year']);
        $selected_month = intval($_POST['invoice_month']);
        $selected_mentor_id = intval($_POST['mentor_id']);

        $appointments_data = urmentor_get_mentor_monthly_appointments($selected_mentor_id, $selected_year, $selected_month);

        if (empty($appointments_data['appointments'])) {
            $message = '<div class="notice notice-error is-dismissible"><p>No appointments found to generate PDF.</p></div>';
        } else {
            $tcpdf_path = ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php';
            if (file_exists($tcpdf_path)) {
                require_once $tcpdf_path;

                // Get mentor data
                $mentor = get_user_by('id', $selected_mentor_id);
                $month_name = date('F', mktime(0, 0, 0, $selected_month, 1));
                $mentor_name = !empty($mentor->display_name) ? $mentor->display_name : ($mentor->user_nicename ?: $mentor->user_login);
                $filename = sanitize_file_name($mentor_name) . '_' . strtolower($month_name) . '_' . $selected_year . '.pdf';

                // Clear output buffers
                ob_clean();
                @ob_end_clean();
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                // Create PDF
                $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->SetCreator('UrMentor');
                $pdf->SetAuthor('UrMentor');
                $pdf->SetTitle('Mentor_Invoice_' . $mentor_name . '_' . $month_name . '_' . $selected_year);
                $pdf->SetMargins(15, 20, 15);
                $pdf->SetAutoPageBreak(TRUE, 20);
                $pdf->AddPage();
                $pdf->SetFont('helvetica', '', 10, '', true);

                $html = urmentor_generate_mentor_pdf_html($selected_mentor_id, $selected_year, $selected_month, $appointments_data);
                $pdf->writeHTML($html, true, false, true, false, '');

                // Set headers
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');

                $pdf->Output($filename, 'D');
                exit;
            } else {
                $message = '<div class="notice notice-error is-dismissible"><p>PDF generation library (TCPDF) is not installed.</p></div>';
            }
        }
    }

    // Handle Send Invoice Email
    if (isset($_POST['send_invoice']) && wp_verify_nonce($_POST['send_nonce'], 'send_invoice')) {
        $selected_year = intval($_POST['invoice_year']);
        $selected_month = intval($_POST['invoice_month']);
        $selected_mentor_id = intval($_POST['mentor_id']);

        $appointments_data = urmentor_get_mentor_monthly_appointments($selected_mentor_id, $selected_year, $selected_month);

        if (empty($appointments_data['appointments'])) {
            $message = '<div class="notice notice-error is-dismissible"><p>No appointments found to send invoice.</p></div>';
        } else {
            $result = urmentor_send_mentor_invoice_email($selected_mentor_id, $selected_year, $selected_month, $appointments_data);
            if (is_wp_error($result)) {
                $message = '<div class="notice notice-error is-dismissible"><p>Error sending invoice: ' . $result->get_error_message() . '</p></div>';
            } else {
                $message = '<div class="notice notice-success is-dismissible"><p>Invoice sent successfully to mentor!</p></div>';
            }
        }
    }

    // Output the page content
    ?>
    <style>
        /* Reuse styles from parent invoice, with adjustments if needed */
        .monthly-invoice-table {
            border-collapse: collapse;
        }
        .monthly-invoice-table th,
        .monthly-invoice-table td {
            width: 200px;
            text-align: left;
        }
        .monthly-invoice-table select {
            width: 200px;
            margin-right: 10px;
        }
        
        /* Enhanced Preview Styles - Similar to Parent */
        .invoice-container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 0;
            border-bottom: 2px solid #000;
            margin-bottom: 20px;
        }
        .logo-placeholder {
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #666;
        }
        .company-details {
            text-align: right;
            line-height: 1.6;
        }
        .invoice-title {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            margin: 30px 0;
            color: #333;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        .invoice-table th, 
        .invoice-table td {
            padding: 12px 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .invoice-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .invoice-table .price-column {
            text-align: right;
        }
        .invoice-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .total-row td {
            background-color: #e9ecef !important;
            font-weight: bold;
            font-size: 16px;
        }
    </style>
    <div class="wrap">
        <h1>Generate Mentor Invoice</h1>
        <?php echo $message; ?>
        <form method="post" action="">
            <?php wp_nonce_field('generate_invoice', 'invoice_nonce'); ?>
            <table class="monthly-invoice-table">
                <tr>
                    <th><label for="invoice_year">Year</label></th>
                    <th><label for="invoice_month">Month</label></th>
                    <th><label for="mentor_id">Mentor</label></th>
                </tr>
                <tr>
                    <td>
                        <select name="invoice_year" id="invoice_year" required>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo esc_attr($year); ?>" <?php selected($selected_year, $year); ?>><?php echo esc_html($year); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="invoice_month" id="invoice_month" required>
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?php echo esc_attr($num); ?>" <?php selected($selected_month, $num); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="mentor_id" id="mentor_id" required>
                            <option value="">Select Mentor</option>
                            <?php foreach ($mentors as $mentor): ?>
                                <option value="<?php echo esc_attr($mentor->ID); ?>" <?php selected($selected_mentor_id, $mentor->ID); ?>>
                                    <?php echo esc_html($mentor->display_name . ' (' . $mentor->user_email . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Generate Invoice', 'primary', 'generate_invoice'); ?>
        </form>

        <?php if ($invoice_html): ?>
            <div class="invoice-preview" style="margin-top: 40px;">
                <?php echo $invoice_html; ?>
                
                <div style="margin-top: 20px; text-align: center;">
                    <form method="post" action="" style="display: inline-block; margin: 0 10px;">
                        <?php wp_nonce_field('download_invoice', 'download_nonce'); ?>
                        <input type="hidden" name="invoice_year" value="<?php echo esc_attr($selected_year); ?>">
                        <input type="hidden" name="invoice_month" value="<?php echo esc_attr($selected_month); ?>">
                        <input type="hidden" name="mentor_id" value="<?php echo esc_attr($selected_mentor_id); ?>">
                        <?php submit_button('Download Invoice as PDF', 'secondary', 'download_invoice'); ?>
                    </form>
                    <form method="post" action="" style="display: inline-block; margin: 0 10px;">
                        <?php wp_nonce_field('send_invoice', 'send_nonce'); ?>
                        <input type="hidden" name="invoice_year" value="<?php echo esc_attr($selected_year); ?>">
                        <input type="hidden" name="invoice_month" value="<?php echo esc_attr($selected_month); ?>">
                        <input type="hidden" name="mentor_id" value="<?php echo esc_attr($selected_mentor_id); ?>">
                        <?php submit_button('Send Invoice to Mentor', 'secondary', 'send_invoice'); ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    ob_end_flush(); // End buffering and flush output
}

/**
 * Generate PDF-specific HTML for Mentor with Parent Invoice Styling
 */
function urmentor_generate_mentor_pdf_html($mentor_id, $year, $month, $appointments_data) {
    $mentor = get_user_by('id', $mentor_id);
    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    $appointments = $appointments_data['appointments'];
    $total_time = $appointments_data['total_time'];
    $total_amount = $appointments_data['total_amount'];
    $total_expense = $appointments_data['total_expense'];
    $net_amount = $appointments_data['net_amount'];

    $html = '
    <style>
        body {
            font-family: helvetica, arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .header-table td {
            padding: 0;
            vertical-align: top;
            border: none;
        }
        .logo-cell {
            height: 50px; /* Reduced height of the logo cell */
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #666;
        }
        .logo-cell img {
            width: 60px; /* Explicitly set smaller width */
            height: 50px; /* Explicitly set smaller height */
            object-fit: contain; /* Ensures the image scales properly */
        }
        .details-cell {
            width: 50%;
            text-align: right;
            font-size: 11px;
            line-height: 1.5;
        }
        .invoice-title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin: 20px 0;
        }
        .invoice-table {
            width: 80%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 11px;
        }
        .invoice-table th {
            background-color: #f8f9fa;
            color: #333;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
            height: 40px;
        }
        .invoice-table td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
            height: 40px;
        }
        .invoice-table tbody tr {
            height: 40px;
        }
        .invoice-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .appointment-column {
            text-align: left;
            width: 200px;
        }
        .duration-column {
            text-align: center;
            width: 40px;
        }
        .price-column {
            text-align: right;
            width: 60px;
        }
        .expense-column {
            text-align: left;
            width: 150px;
        }
        .total-row {
            font-weight: bold;
            font-size: 12px;
        }
        .total-row td {
            border: 1px solid #ddd !important;
            padding: 12px 8px;
            height: 40px;
        }
        .summary-row {
            font-weight: bold;
            font-size: 11px;
        }
        .summary-row td {
            border: 1px solid #ddd !important;
            padding: 10px 8px;
            height: 40px;
        }
    </style>
    
    <div class="invoice-container">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <img src="http://localhost/urmentor-pwa/wp-content/uploads/2025/08/URMENTOR-WP-LOGO-1.png" alt="UrMentor Logo" />
                </td>
                <td class="details-cell">
                    <strong>Mentor:</strong> ' . esc_html($mentor->display_name . ' (' . $mentor->user_email . ')') . '<br/>
                    <strong>Invoice Date:</strong> ' . esc_html($month_name . ' ' . $year) . '<br/>
                    <strong>Total Hours:</strong> ' . esc_html($total_time) . ' hrs
                </td>
            </tr>
        </table>
        <hr>
        
        <h1 class="invoice-title">Mentor Monthly Invoice</h1>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th class="appointment-column">Appointment Time</th>
                    <th>Child</th>
                    <th>Parent</th>
                    <th>Status</th>
                    <th class="duration-column">Hours</th>
                    <th class="price-column">Rate (AED/hr)</th>
                    <th class="price-column">Amount (AED)</th>
                    <th class="expense-column">Expenses</th>
                    <th class="price-column">Expense (AED)</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($appointments as $appt) {
        $date_time = new DateTime($appt['date_time'], new DateTimeZone('Asia/Kolkata'));
        $formatted_datetime = $date_time->format('j F Y, h:i A'); 
        $formatted_rate = number_format($appt['rate'], 2);
        $formatted_amount = number_format($appt['amount'], 2);
        $formatted_expense = $appt['expense_amount'] > 0 ? number_format($appt['expense_amount'], 2) : '0.00';
        $expense_desc = $appt['expense_amount'] > 0 ? esc_html($appt['expense_description']) : '-';
        
        $html .= '<tr>
            <td class="appointment-column">' . esc_html($formatted_datetime) . '</td>
            <td>' . esc_html($appt['child_name']) . '</td>
            <td>' . esc_html($appt['parent_name']) . '</td>
            <td>' . esc_html(ucfirst($appt['status'])) . '</td>
            <td class="duration-column">' . esc_html($appt['duration']) . '</td>
            <td class="price-column">' . esc_html($formatted_rate) . '</td>
            <td class="price-column">' . esc_html($formatted_amount) . '</td>
            <td class="expense-column">' . $expense_desc . '</td>
            <td class="price-column">' . esc_html($formatted_expense) . '</td>
        </tr>';
    }

    $formatted_total_amount = number_format($total_amount, 2);
    $formatted_total_expense = number_format($total_expense, 2);
    $formatted_net_amount = number_format($net_amount, 2);
    
    // Summary rows
    $html .= '<tr class="summary-row">
            <td colspan="6"><strong>Total Amount</strong></td>
            <td class="price-column"><strong>' . esc_html($formatted_total_amount) . ' AED</strong></td>
            <td><strong>Total Expenses</strong></td>
            <td class="price-column"><strong>' . esc_html($formatted_total_expense) . ' AED</strong></td>
        </tr>';
    
    $html .= '<tr class="total-row">
            <td colspan="8"><strong>Net Payout Amount</strong></td>
            <td class="price-column"><strong>' . esc_html($formatted_net_amount) . ' AED</strong></td>
        </tr>
            </tbody>
        </table>
    </div>';

    return $html;
}

/**
 * Generate Invoice HTML for Mentor Preview (Updated with correct colspan)
 */
function urmentor_generate_mentor_invoice_html($mentor_id, $year, $month, $appointments_data) {
    $mentor = get_user_by('id', $mentor_id);
    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    $appointments = $appointments_data['appointments'];
    $total_time = $appointments_data['total_time'];
    $total_amount = $appointments_data['total_amount'];
    $total_expense = $appointments_data['total_expense'];
    $net_amount = $appointments_data['net_amount'];

    // Currency symbol
    $currency_symbol = 'AED';

    ob_start();
    ?>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="logo-section">
                <div class="logo-placeholder">
                    <img src="http://localhost/urmentor-pwa/wp-content/uploads/2025/08/URMENTOR-WP-LOGO-1.png" alt="UR Mentor Logo" />
                </div>
            </div>
            <div class="company-details">
                <p><strong>Mentor:</strong> <?php echo esc_html($mentor->display_name . ' (' . $mentor->user_email . ')'); ?></p>
                <p><strong>Invoice Date:</strong> <?php echo esc_html($month_name . ' ' . $year); ?></p>
            </div>
        </div>
        
        <div class="separator-line"></div>
        
        <h1 class="invoice-title">Mentor Monthly Invoice</h1>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Appointment Time</th>
                    <th>Child</th>
                    <th>Parent</th>
                    <th>Status</th>
                    <th>Duration (hrs)</th>
                    <th class="price-column">Rate (<?php echo $currency_symbol; ?>/hr)</th>
                    <th class="price-column">Amount (<?php echo $currency_symbol; ?>)</th>
                    <th>Expense Description</th>
                    <th class="price-column">Expense (<?php echo $currency_symbol; ?>)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($appointments as $appt): ?>
                <tr>
                    <td><?php 
                        $date_time = new DateTime($appt['date_time'], new DateTimeZone('Asia/Kolkata'));
                        echo esc_html($date_time->format('j F Y, h:i A')); 
                    ?></td>
                    <td><?php echo esc_html($appt['child_name']); ?></td>
                    <td><?php echo esc_html($appt['parent_name']); ?></td>
                    <td><?php echo esc_html(ucfirst($appt['status'])); ?></td>
                    <td><?php echo esc_html($appt['duration']); ?></td>
                    <td class="price-column"><?php echo esc_html(number_format($appt['rate'], 2)); ?></td>
                    <td class="price-column"><?php echo esc_html(number_format($appt['amount'], 2)); ?></td>
                    <td><?php echo esc_html($appt['expense_amount'] > 0 ? $appt['expense_description'] : '-'); ?></td>
                    <td class="price-column"><?php echo esc_html($appt['expense_amount'] > 0 ? number_format($appt['expense_amount'], 2) : '0.00'); ?></td>
                </tr>
            <?php endforeach; ?>
                <tr class="total-hours-row">
                    <td colspan="4" class="text-left"><strong>Total Hours: <?php echo esc_html($total_time); ?></strong></td>
                    <td colspan="2" class="text-right"><strong>Total: <?php echo esc_html(number_format($total_amount, 2) . ' ' . $currency_symbol); ?></strong></td>
                    <td colspan="3" class="text-right"><strong>Total Expenses: <?php echo esc_html(number_format($total_expense, 2) . ' ' . $currency_symbol); ?></strong></td>
                </tr>
                <tr class="net-amount-row">
                    <td colspan="6" class="text-left"><strong>Net Amount:</strong></td>
                    <td colspan="3" class="text-right"><strong><?php echo esc_html(number_format($net_amount, 2) . ' ' . $currency_symbol); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}