<?php
/**
 * Send Invoice Email with PDF Attachment and Payment Link
 * Updated to use email template with build_email_body function and update child order statuses
 */

// Add admin menu
add_action('admin_menu', 'urmentor_add_user_management_menu_invoice');
function urmentor_add_user_management_menu_invoice() {
    add_submenu_page(
        'urmentor-manage-users',
        'Parent Invoices',
        'parent Invoices',
        'manage_options',
        'urmentor-monthly-invoices',
        'urmentor_monthly_invoices_page'
    );
}

function urmentor_send_invoice_email($parent_id, $year, $month, $appointments, $master_order_id = null) {
    $parent = get_user_by('id', $parent_id);
    if (!$parent) {
        return new WP_Error('invalid_parent', 'Invalid parent user.');
    }

    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    $parent_name = !empty($parent->display_name) ? $parent->display_name : ($parent->user_nicename ?: $parent->user_login);
    $filename = sanitize_file_name($parent_name) . '_' . strtolower($month_name) . '_' . $year . '.pdf';

    // Generate PDF
    $tcpdf_path = ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php';
    if (!file_exists($tcpdf_path)) {
        return new WP_Error('tcpdf_missing', 'TCPDF library not found.');
    }

    require_once $tcpdf_path;
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('UrMentor');
    $pdf->SetAuthor('UrMentor');
    $pdf->SetTitle('Invoice_' . $parent_name . '_' . $month_name . '_' . $year);
    $pdf->SetMargins(15, 20, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10, '', true);

    // Generate HTML for PDF using the fixed function
    $html = urmentor_generate_pdf_html($parent_id, $year, $month, $appointments);
    $pdf->writeHTML($html, true, false, true, false, '');

    // Save PDF to temporary file
    $temp_dir = sys_get_temp_dir();
    $pdf_path = $temp_dir . '/' . $filename;
    $pdf->Output($pdf_path, 'F');

    // Get payment link and order details
    $payment_section = '';
    if ($master_order_id) {
        $master_order = wc_get_order($master_order_id);
        if ($master_order) {
            $payment_link = $master_order->get_checkout_payment_url();
            $order_total = $master_order->get_formatted_order_total();
            $payment_section = '
                <div class="payment-section">
                    <h3>Invoice Details</h3>
                    <p><strong>Total Amount:</strong> ' . $order_total . '</p>
                    <a href="' . esc_url($payment_link) . '" class="payment-link">Pay Now</a>
                    <p><small>You can pay securely using your preferred payment method.</small></p>
                </div>';

            // Hook to update child order statuses when master order is paid
            add_action('woocommerce_order_status_changed', function($order_id, $old_status, $new_status) use ($master_order_id, $appointments) {
                if ($order_id == $master_order_id && $new_status == 'processing') { // Assuming 'processing' means paid
                    foreach ($appointments['appointments'] as $appt) {
                        $child_order = wc_get_order($appt['order_id']);
                        if ($child_order && $child_order->get_status() !== 'completed') { // Avoid overriding completed statuses
                            $child_order->update_status('processing', 'Updated due to master order payment.');
                        }
                    }
                }
            }, 10, 3);
        }
    }

    // Prepare email replacements
    $replacements = [
        'name' => esc_html($parent_name),
        'month_name' => esc_html($month_name),
        'year' => $year,
        'payment_section' => $payment_section
    ];

    // Build email body using template
    $body = build_email_body('invoice-email-template.html', $replacements);
    if (empty($body)) {
        return new WP_Error('template_failed', 'Failed to load email template.');
    }

    // Prepare email
    $to = $parent->user_email;
    $subject = 'UrMentor Monthly Invoice - ' . $month_name . ' ' . $year;
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
 * Get Monthly Appointments for a Parent's Children
 * Updated to exclude a specific order ID (e.g., the master order) to prevent duplicates
 */
function urmentor_get_parent_monthly_appointments($parent_id, $year, $month, $exclude_order_id = null) {
    $children_ids = get_users(array(
        'role' => 'child_user',
        'meta_key' => 'assigned_parent_id',
        'meta_value' => $parent_id,
        'fields' => 'ID'
    ));
    $start_date = new DateTime("$year-$month-01 00:00:00", new DateTimeZone('Asia/Kolkata'));
    $end_date = clone $start_date;
    $end_date->modify('last day of this month')->setTime(23, 59, 59);

    $args = array(
        'status' => array('wc-processing', 'wc-on-hold', 'wc-pending', 'wc-completed'),
        'limit' => -1,
    );
    
    // Exclude the master order if provided
    if ($exclude_order_id) {
        $args['exclude'] = array($exclude_order_id);
    }

    $orders = wc_get_orders($args);

    $appointments = array();
    $total = 0;

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item_id => $item) {
            $child_id = $item->get_meta('child_id');
            $session_date_time_str = $item->get_meta('session_date_time');
            $appointment_status = $item->get_meta('appointment_status');

            // Skip if child_id is invalid, session_date_time is empty, or appointment_status is 'cancelled'
            if (!in_array($child_id, $children_ids) || empty($session_date_time_str) || $appointment_status === 'cancelled') {
                continue;
            }

            $session_date_time = new DateTime($session_date_time_str, new DateTimeZone('Asia/Kolkata'));

            if ($session_date_time >= $start_date && $session_date_time <= $end_date) {
                $mentor_id = $item->get_meta('mentor_id');
                $mentor = get_user_by('id', $mentor_id);
                $child = get_user_by('id', $child_id);
                $price = $item->get_total();

                $appointments[] = array(
                    'date_time' => $session_date_time->format('Y-m-d H:i:s'),
                    'mentor_name' => $mentor ? $mentor->display_name : 'Unknown',
                    'child_name' => $child ? $child->display_name : 'Unknown',
                    'status' => $appointment_status ?: 'N/A',
                    'price' => $price,
                    'order_id' => $order->get_id(),
                    'item_id' => $item_id,
                );

                $total += $price;
            }
        }
    }

    return array(
        'appointments' => $appointments,
        'total' => $total,
    );
}

/**
 * Updated Monthly Invoices Page Handler - Modified to exclude master order when fetching appointments
 */
function urmentor_monthly_invoices_page() {
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
    $parents = get_users(array('role' => 'parent_user'));

    // Preselect values
    $selected_year = isset($_GET['invoice_year']) ? intval($_GET['invoice_year']) : $current_year;
    $selected_month = isset($_GET['invoice_month']) ? intval($_GET['invoice_month']) : $current_month;
    $selected_parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : (!empty($parents) ? $parents[0]->ID : 0);
    $master_order_id = null; // Store master order ID for email

    ob_start(); // Start output buffering

    // Handle form submission for generating invoice
    if (isset($_POST['generate_invoice']) && wp_verify_nonce($_POST['invoice_nonce'], 'generate_invoice')) {
        $selected_year = intval($_POST['invoice_year']);
        $selected_month = intval($_POST['invoice_month']);
        $selected_parent_id = intval($_POST['parent_id']);
        
        $children = get_users(array(
            'role' => 'child_user',
            'meta_key' => 'assigned_parent_id',
            'meta_value' => $selected_parent_id,
            'fields' => 'ID'
        ));
        
        if (empty($children)) {
            $message = '<div class="notice notice-error is-dismissible"><p>No children assigned to this parent.</p></div>';
        } else {
            // Check for existing master order first to determine exclusion
            $existing_order = urmentor_get_existing_master_order($selected_parent_id, $selected_year, $selected_month);
            $exclude_order_id = $existing_order ? $existing_order->get_id() : null;
            
            $appointments = urmentor_get_parent_monthly_appointments($selected_parent_id, $selected_year, $selected_month, $exclude_order_id);
            
            if (empty($appointments['appointments'])) {
                $message = '<div class="notice notice-info is-dismissible"><p>No appointments found for this month.</p></div>';
            } else {
                
                if ($existing_order) {
                    $master_order_id = urmentor_update_master_order($existing_order, $selected_parent_id, $selected_year, $selected_month, $appointments);
                    $message = '<div class="notice notice-success is-dismissible"><p>Master order updated successfully! Order ID: ' . $master_order_id . '</p></div>';
                } else {
                    $master_order_id = urmentor_create_master_order($selected_parent_id, $selected_year, $selected_month, $appointments);
                    if (is_wp_error($master_order_id)) {
                        $message = '<div class="notice notice-error is-dismissible"><p>Error creating master order: ' . $master_order_id->get_error_message() . '</p></div>';
                        $master_order_id = null;
                    } else {
                        $message = '<div class="notice notice-success is-dismissible"><p>Master order created successfully! Order ID: ' . $master_order_id . '</p></div>';
                    }
                }

                if ($master_order_id) {
                    $invoice_html = urmentor_generate_invoice_html($selected_parent_id, $selected_year, $selected_month, $appointments);
                }
            }
        }
    }

    // Handle PDF download with improved generation
    if (isset($_POST['download_invoice']) && wp_verify_nonce($_POST['download_nonce'], 'download_invoice')) {
        $selected_year = intval($_POST['invoice_year']);
        $selected_month = intval($_POST['invoice_month']);
        $selected_parent_id = intval($_POST['parent_id']);
    
        // Check for existing master order to exclude
        $existing_order = urmentor_get_existing_master_order($selected_parent_id, $selected_year, $selected_month);
        $exclude_order_id = $existing_order ? $existing_order->get_id() : null;
    
        $appointments = urmentor_get_parent_monthly_appointments($selected_parent_id, $selected_year, $selected_month, $exclude_order_id);
    
        if (empty($appointments['appointments'])) {
            $message = '<div class="notice notice-error is-dismissible"><p>No appointments found to generate PDF.</p></div>';
        } else {
            $tcpdf_path = ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php';
            if (file_exists($tcpdf_path)) {
                require_once $tcpdf_path;
    
                // Get parent data
                $parent = get_user_by('id', $selected_parent_id);
                $month_name = date('F', mktime(0, 0, 0, $selected_month, 1));
                $parent_name = !empty($parent->display_name) ? $parent->display_name : ($parent->user_nicename ?: $parent->user_login);
                $filename = sanitize_file_name($parent_name) . '_' . strtolower($month_name) . '_' . $selected_year . '.pdf';
    
                // Clear output buffers
                ob_clean();
                @ob_end_clean();
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
    
                // Create PDF with better settings
                $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->SetCreator('UrMentor');
                $pdf->SetAuthor('UrMentor');
                $pdf->SetTitle('Invoice_' . $parent_name . '_' . $month_name . '_' . $selected_year);
                $pdf->SetMargins(15, 20, 15);
                $pdf->SetAutoPageBreak(TRUE, 20);
                $pdf->AddPage();
                
                // Use a better font for currency support
                $pdf->SetFont('helvetica', '', 10, '', true);
    
                // Generate HTML with proper styling for PDF
                $html = urmentor_generate_pdf_html($selected_parent_id, $selected_year, $selected_month, $appointments);
    
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

    // Handle Send Invoice Email (updated to include master order ID and exclude for appointments)
    if (isset($_POST['send_invoice']) && wp_verify_nonce($_POST['send_nonce'], 'send_invoice')) {
        $selected_year = intval($_POST['invoice_year']);
        $selected_month = intval($_POST['invoice_month']);
        $selected_parent_id = intval($_POST['parent_id']);
    
        // Check for existing master order first
        $existing_order = urmentor_get_existing_master_order($selected_parent_id, $selected_year, $selected_month);
        $exclude_order_id = $existing_order ? $existing_order->get_id() : null;
    
        $appointments = urmentor_get_parent_monthly_appointments($selected_parent_id, $selected_year, $selected_month, $exclude_order_id);
    
        if (empty($appointments['appointments'])) {
            $message = '<div class="notice notice-error is-dismissible"><p>No appointments found to send invoice.</p></div>';
        } else {
            // Get or create master order for payment link
            if ($existing_order) {
                $master_order_id = $existing_order->get_id();
                // Optionally update the master order with current appointments
                // urmentor_update_master_order($existing_order, $selected_parent_id, $selected_year, $selected_month, $appointments);
            } else {
                $master_order_id = urmentor_create_master_order($selected_parent_id, $selected_year, $selected_month, $appointments);
                if (is_wp_error($master_order_id)) {
                    $message = '<div class="notice notice-error is-dismissible"><p>Error creating order for payment: ' . $master_order_id->get_error_message() . '</p></div>';
                    $master_order_id = null;
                }
            }
            
            // Send email with payment link
            $result = urmentor_send_invoice_email($selected_parent_id, $selected_year, $selected_month, $appointments, $master_order_id);
            if (is_wp_error($result)) {
                $message = '<div class="notice notice-error is-dismissible"><p>Error sending invoice: ' . $result->get_error_message() . '</p></div>';
            } else {
                $payment_info = $master_order_id ? ' with payment link' : '';
                $message = '<div class="notice notice-success is-dismissible"><p>Invoice sent successfully to parent' . $payment_info . '!</p></div>';
            }
        }
    }

    // Output the page content
    ?>
    <style>
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
        
        /* Enhanced Preview Styles */
        .invoice-container {
            width: 100%;
            max-width: 800px;
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
            width: 120px;
        }
        .invoice-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .total-row td {
            background-color: #e9ecef !important;
            font-weight: bold;
            font-size: 16px;
        }
        
        /* Payment link styling */
        .payment-info {
            background-color: #e7f3ff;
            border: 1px solid #0073aa;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .payment-link {
            display: inline-block;
            background-color: #0073aa;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .payment-link:hover {
            background-color: #005a87;
            color: white;
        }
    </style>
    <div class="wrap">
        <h1>Generate Monthly Invoice</h1>
        <?php echo $message; ?>
        <form method="post" action="">
            <?php wp_nonce_field('generate_invoice', 'invoice_nonce'); ?>
            <table class="monthly-invoice-table">
                <tr>
                    <th><label for="invoice_year">Year</label></th>
                    <th><label for="invoice_month">Month</label></th>
                    <th><label for="parent_id">Parent</label></th>
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
                        <select name="parent_id" id="parent_id" required>
                            <option value="">Select Parent</option>
                            <?php foreach ($parents as $parent): ?>
                                <option value="<?php echo esc_attr($parent->ID); ?>" <?php selected($selected_parent_id, $parent->ID); ?>>
                                    <?php echo esc_html($parent->display_name . ' (' . $parent->user_email . ')'); ?>
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
                        <input type="hidden" name="parent_id" value="<?php echo esc_attr($selected_parent_id); ?>">
                        <?php submit_button('Download Invoice as PDF', 'secondary', 'download_invoice'); ?>
                    </form>
                    <form method="post" action="" style="display: inline-block; margin: 0 10px;">
                        <?php wp_nonce_field('send_invoice', 'send_nonce'); ?>
                        <input type="hidden" name="invoice_year" value="<?php echo esc_attr($selected_year); ?>">
                        <input type="hidden" name="invoice_month" value="<?php echo esc_attr($selected_month); ?>">
                        <input type="hidden" name="parent_id" value="<?php echo esc_attr($selected_parent_id); ?>">
                        <?php submit_button('Send Invoice with Payment Link', 'secondary', 'send_invoice'); ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    ob_end_flush(); // End buffering and flush output
}

/**
 * Generate PDF-specific HTML with proper styling
 */
function urmentor_generate_pdf_html($parent_id, $year, $month, $appointments_data) {
    $parent = get_user_by('id', $parent_id);
    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    $appointments = $appointments_data['appointments'];
    $total = $appointments_data['total'];

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
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #666;
        }
        .logo-cell img {
            max-width: 120px;
            height: 80px;
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
            width: 100%;
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
        }
        .invoice-table td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .invoice-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .price-column {
            text-align: right;
            width: 100px;
        }
        .total-row {
            font-weight: bold;
            font-size: 12px;
        }
        .total-row td {
            border: 1px solid #ddd !important;
            padding: 12px 8px;
        }
    </style>
    
    <div class="invoice-container">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <img src="http://localhost/urmentor-pwa/wp-content/uploads/2025/08/URMENTOR-WP-LOGO-1.png" alt="UrMentor Logo" />
                </td>
                <td class="details-cell">
                    <strong>Parent:</strong> ' . esc_html($parent->display_name . ' (' . $parent->user_email . ')') . '<br/>
                    <strong>Invoice Date:</strong> ' . esc_html($month_name . ' ' . $year) . '
                </td>
            </tr>
        </table>
        
        <hr>
        
        <h1 class="invoice-title">Monthly Invoice</h1>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Appointment Time</th>
                    <th>Child</th>
                    <th>Mentor</th>
                    <th>Status</th>
                    <th class="price-column">Price</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($appointments as $appt) {
            $date_time = new DateTime($appt['date_time'], new DateTimeZone('Asia/Kolkata'));
            $formatted_datetime = $date_time->format('j F Y, h:i A'); 
            $formatted_price = number_format($appt['price'], 2) . ' AED';
            
            $html .= '<tr>
                <td>' . esc_html($formatted_datetime) . '</td>
                <td>' . esc_html($appt['child_name']) . '</td>
                <td>' . esc_html($appt['mentor_name']) . '</td>
                <td>' . esc_html(ucfirst($appt['status'])) . '</td>
                <td class="price-column">' . esc_html($formatted_price) . '</td>
            </tr>';
        }

    $formatted_total = number_format($total, 2) . ' AED';
    $html .= '<tr class="total-row">
            <td colspan="4"><strong>Total</strong></td>
            <td class="price-column"><strong>' . esc_html($formatted_total) . '</strong></td>
        </tr>
            </tbody>
        </table>
    </div>';

    return $html;
}

/**
 * Check for existing master order
 */
function urmentor_get_existing_master_order($parent_id, $year, $month) {
    $args = array(
        'limit' => 1,
        'customer_id' => $parent_id,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'is_monthly_invoice',
                'value' => true,
                'compare' => '='
            ),
            array(
                'key' => 'invoice_month',
                'value' => sprintf('%04d-%02d', $year, $month),
                'compare' => '='
            )
        )
    );

    $orders = wc_get_orders($args);
    return !empty($orders) ? $orders[0] : false;
}

/**
 * Filter Payment Methods for Monthly Invoice Orders
 * Excludes Cash on Delivery (COD) for orders created through monthly invoice system
 */
add_filter('woocommerce_available_payment_gateways', 'urmentor_filter_payment_methods_for_invoice_orders');
function urmentor_filter_payment_methods_for_invoice_orders($available_gateways) {
    // Only filter on checkout page and for specific orders
    if (!is_admin() && is_wc_endpoint_url('order-pay')) {
        // Get the order ID from the URL
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order = wc_get_order($order_id);
            
            if ($order) {
                // Check if this is a monthly invoice order
                $is_monthly_invoice = $order->get_meta('is_monthly_invoice', true);
                
                if ($is_monthly_invoice) {
                    // Remove Cash on Delivery payment method
                    unset($available_gateways['cod']);
                    
                    // Optionally, you can also remove other specific payment methods
                    // unset($available_gateways['bacs']); // Bank transfer
                    // unset($available_gateways['cheque']); // Check payments
                }
            }
        }
    }
    
    return $available_gateways;
}

/**
 * Updated Create Master Order Function - Add invoice flag
 */
function urmentor_create_master_order($parent_id, $year, $month, $appointments_data) {
    $parent = get_user_by('id', $parent_id);
    if (!$parent) {
        return new WP_Error('invalid_parent', 'Invalid parent user.');
    }

    $appointments = $appointments_data['appointments'];
    $months = array(
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    );

    // Create new order
    $order = wc_create_order(array(
        'customer_id' => $parent_id,
    ));

    // Add individual order items from appointments
    foreach ($appointments as $appt) {
        $original_order = wc_get_order($appt['order_id']);
        if (!$original_order) {
            continue;
        }
        $item = $original_order->get_item($appt['item_id']);
        if (!$item) {
            continue;
        }

        $new_item = new WC_Order_Item_Product();
        $new_item->set_name($item->get_name());
        $new_item->set_quantity($item->get_quantity());
        $new_item->set_subtotal($item->get_subtotal());
        $new_item->set_total($item->get_total());
        $new_item->set_product_id($item->get_product_id());
        $new_item->set_variation_id($item->get_variation_id());
        foreach ($item->get_meta_data() as $meta) {
            $new_item->add_meta_data($meta->key, $meta->value);
        }
        $order->add_item($new_item);
    }

    // Set billing details from parent
    $order->set_billing_first_name($parent->first_name);
    $order->set_billing_last_name($parent->last_name);
    $order->set_billing_email($parent->user_email);

    // Add meta data - IMPORTANT: Mark as monthly invoice order
    $order->update_meta_data('invoice_month', sprintf('%04d-%02d', $year, $month));
    $order->update_meta_data('parent_id', $parent_id);
    $order->update_meta_data('is_monthly_invoice', true); // This flag will be used to filter payment methods

    // Store linked appointments
    $linked_items = array();
    foreach ($appointments as $appt) {
        $linked_items[] = array(
            'order_id' => $appt['order_id'],
            'item_id' => $appt['item_id'],
        );
    }
    $order->update_meta_data('linked_appointments', $linked_items);

    $order->calculate_totals();
    $order->update_status('pending', 'Monthly invoice generated.');

    return $order->get_id();
}

/**
 * Updated Update Master Order Function - Ensure invoice flag is set
 */
function urmentor_update_master_order($order, $parent_id, $year, $month, $appointments_data) {
    $parent = get_user_by('id', $parent_id);
    if (!$parent) {
        return new WP_Error('invalid_parent', 'Invalid parent user.');
    }

    $appointments = $appointments_data['appointments'];

    // Remove existing items
    foreach ($order->get_items() as $item_id => $item) {
        $order->remove_item($item_id);
    }

    // Add individual order items from appointments
    foreach ($appointments as $appt) {
        $original_order = wc_get_order($appt['order_id']);
        if (!$original_order) {
            continue;
        }
        $item = $original_order->get_item($appt['item_id']);
        if (!$item) {
            continue;
        }

        $new_item = new WC_Order_Item_Product();
        $new_item->set_name($item->get_name());
        $new_item->set_quantity($item->get_quantity());
        $new_item->set_subtotal($item->get_subtotal());
        $new_item->set_total($item->get_total());
        $new_item->set_product_id($item->get_product_id());
        $new_item->set_variation_id($item->get_variation_id());
        foreach ($item->get_meta_data() as $meta) {
            $new_item->add_meta_data($meta->key, $meta->value);
        }
        $order->add_item($new_item);
    }

    // Update billing details
    $order->set_billing_first_name($parent->first_name);
    $order->set_billing_last_name($parent->last_name);
    $order->set_billing_email($parent->user_email);

    // Update meta data - ENSURE the invoice flag is set
    $order->update_meta_data('invoice_month', sprintf('%04d-%02d', $year, $month));
    $order->update_meta_data('parent_id', $parent_id);
    $order->update_meta_data('is_monthly_invoice', true); // Ensure this is set for payment method filtering

    // Store linked appointments
    $linked_items = array();
    foreach ($appointments as $appt) {
        $linked_items[] = array(
            'order_id' => $appt['order_id'],
            'item_id' => $appt['item_id'],
        );
    }
    $order->update_meta_data('linked_appointments', $linked_items);

    $order->calculate_totals();
    $order->update_status('pending', 'Monthly invoice updated.');

    return $order->get_id();
}

/**
 * Optional: Add custom notice on checkout for monthly invoice orders
 */
add_action('woocommerce_before_checkout_form', 'urmentor_monthly_invoice_checkout_notice');
function urmentor_monthly_invoice_checkout_notice() {
    if (is_wc_endpoint_url('order-pay')) {
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order = wc_get_order($order_id);
            
            if ($order && $order->get_meta('is_monthly_invoice', true)) {
                $invoice_month = $order->get_meta('invoice_month', true);
                $formatted_month = $invoice_month ? date('F Y', strtotime($invoice_month . '-01')) : 'Unknown';
                
                echo '<div class="woocommerce-info" style="margin-bottom: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #0073aa;">';
                echo '<strong>Monthly Invoice Payment</strong><br>';
                echo 'You are paying for your UrMentor monthly invoice for ' . esc_html($formatted_month) . '.<br>';
                echo '<em>Note: Cash on Delivery is not available for invoice payments. Please choose from the available online payment methods below.</em>';
                echo '</div>';
            }
        }
    }
}

/**
 * Optional: Customize checkout page title for monthly invoice orders
 */
add_filter('woocommerce_endpoint_order-pay_title', 'urmentor_customize_invoice_payment_title');
function urmentor_customize_invoice_payment_title($title) {
    global $wp;
    if (isset($wp->query_vars['order-pay'])) {
        $order_id = absint($wp->query_vars['order-pay']);
        $order = wc_get_order($order_id);
        
        if ($order && $order->get_meta('is_monthly_invoice', true)) {
            $invoice_month = $order->get_meta('invoice_month', true);
            $formatted_month = $invoice_month ? date('F Y', strtotime($invoice_month . '-01')) : '';
            return 'Pay Monthly Invoice' . ($formatted_month ? ' - ' . $formatted_month : '');
        }
    }
    return $title;
}

/**
 * Optional: Add specific styling for monthly invoice checkout page
 */
add_action('wp_head', 'urmentor_invoice_checkout_styles');
function urmentor_invoice_checkout_styles() {
    if (is_wc_endpoint_url('order-pay')) {
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order = wc_get_order($order_id);
            
            if ($order && $order->get_meta('is_monthly_invoice', true)) {
                ?>
                <style>
                    /* Add any custom styles here if needed */
                </style>
                <?php
            }
        }
    }
}

/**
 * Generate Invoice HTML
 */
function urmentor_generate_invoice_html($parent_id, $year, $month, $appointments_data, $for_pdf = false) {
    $parent = get_user_by('id', $parent_id);
    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    $appointments = $appointments_data['appointments'];
    $total = $appointments_data['total'];

    // Currency symbol handling
    $currency_symbol = 'AED'; // or 'د.إ' if you prefer Arabic
    
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
                <p><strong>Parent:</strong> <?php echo esc_html($parent->display_name . ' (' . $parent->user_email . ')'); ?></p>
                <p><strong>Invoice Date:</strong> <?php echo esc_html($month_name . ' ' . $year); ?></p>
            </div>
        </div>
        <h1 class="invoice-title">Monthly Invoice</h1>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Appointment Time</th>
                    <th>Child</th>
                    <th>Mentor</th>
                    <th>Status</th>
                    <th class="price-column">Price</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($appointments as $appt): ?>
                <tr>
                    <td><?php 
                        $date_time = new DateTime($appt['date_time'], new DateTimeZone('Asia/Kolkata'));
                        // Format the date and time (e.g., 'F j, Y, h:i A')
                        echo esc_html($date_time->format('j F Y, h:i A')); 
                    ?></td>
                    <td><?php echo esc_html($appt['child_name']); ?></td>
                    <td><?php echo esc_html($appt['mentor_name']); ?></td>
                    <td><?php echo esc_html(ucfirst($appt['status'])); ?></td>
                    <td class="price-column"><?php echo esc_html(number_format($appt['price'], 2) . ' ' . $currency_symbol); ?></td>
                </tr>
            <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4"><strong>Total</strong></td>
                    <td class="price-column"><strong><?php echo esc_html(number_format($total, 2) . ' ' . $currency_symbol); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}