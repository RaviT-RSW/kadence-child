<?php
/**
 * Registers a custom admin page to display detailed appointment and order information.
 */

 // Add Appointment Details admin page
add_action('admin_menu', function () {
    add_submenu_page(
        null, // No parent menu
        'Appointment Details', // Page title
        'Appointment Details', // Menu title (not used since it's hidden)
        'manage_woocommerce', // Capability
        'appointment-details', // Slug (used in URL)
        'render_appointment_details_admin_page' // Callback function
    );
});

/**
 * Renders the Appointment Details admin page.
 *
 * This page displays detailed information about an appointment and its related order.
 *
 * @since 1.0.0
 */
function render_appointment_details_admin_page() {
    global $wpdb;

    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $item_id  = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

    $order = wc_get_order($order_id);

    // Handle Expense Status Update
    if (isset($_POST['update_expense_status']) && isset($_POST['expense_id'])) {
        $new_status = sanitize_text_field($_POST['expense_status']);
        $expense_id = intval($_POST['expense_id']);
        $updated = $wpdb->update(
            "{$wpdb->prefix}appointment_expense",
            ['expense_status' => $new_status],
            ['expense_id' => $expense_id],
            ['%s'],
            ['%d']
        );

        if ($updated !== false) {
            echo '<div class="notice notice-success is-dismissible"><p>Expense status updated to <strong>' . ucfirst($new_status) . '</strong>.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to update expense status. Please try again.</p></div>';
        }
    }

    echo '<div class="wrap appointment-details-page">';
    echo '<h1 class="wp-heading-inline">Appointment Details</h1>';
    echo '<div class="notice notice-info" style="padding:8px 12px; display:flex; align-items:center; gap:6px;">
            <span class="dashicons dashicons-arrow-left-alt" style="line-height:20px;"></span>
            <a href="' . admin_url('admin.php?page=manage-appointments') . '" 
               style="font-weight:500; text-decoration:none;">
               Back to Appointments
            </a>
          </div>';
    echo '<hr class="wp-header-end">';

    if (!$order) {
        echo '<div class="notice notice-error"><p>Invalid Order ID.</p></div>';
        echo '</div>';
        return;
    }

    $item = $order->get_item($item_id);
    if (!$item) {
        echo '<div class="notice notice-error"><p>Invalid Item ID.</p></div>';
        echo '</div>';
        return;
    }

    // Get meta values
    $mentor_id          = $item->get_meta('mentor_id');
    $child_id           = $item->get_meta('child_id');
    $session_date_time  = $item->get_meta('session_date_time');
    $appointment_status = $item->get_meta('appointment_status') ?: 'N/A';

    // Related data
    $mentor  = get_user_by('id', $mentor_id);
    $child   = get_user_by('id', $child_id);
    $product = $item->get_name();

    $feedback = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}appointment_feedback WHERE order_id = %d", $order_id)
    );
    $expense = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$wpdb->prefix}appointment_expense WHERE order_id = %d", $order_id)
    );

    // ---- Order Info ----
    echo '<div class="appointment-card">';
    echo '<h2><span class="dashicons dashicons-cart"></span> Order Information</h2>';
    echo '<table class="form-table">';
    echo '<tbody>';
    echo '<tr><th>Order ID</th><td>#' . esc_html($order_id) . '</td></tr>';
    echo '<tr><th>Status</th><td><span class="badge status-' . esc_attr($order->get_status()) . '">' . ucfirst($order->get_status()) . '</span></td></tr>';
    echo '<tr><th>Total Amount</th><td><strong>' . wc_price($order->get_total()) . '</strong></td></tr>';
    echo '<tr><th>Order Date</th><td>' . esc_html($order->get_date_created()->format('F d, Y')) . '</td></tr>';
    echo '<tr><th>Payment Method</th><td>' . esc_html($order->get_payment_method_title()) . '</td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    // ---- Session Details ----
    echo '<div class="appointment-card">';
    echo '<h2><span class="dashicons dashicons-groups"></span> Appointment Details</h2>';
    echo '<table class="form-table">';
    echo '<tbody>';
    echo '<tr><th>Appointment Title</th><td>' . ucfirst($product) . '</td></tr>';
    echo '<tr><th>Appointment Date</th><td>' . esc_html($session_date_time) . '</td></tr>';
    echo '<tr><th>Mentor</th><td>' . ucfirst($mentor ? $mentor->display_name : 'Unknown') . ' (ID: ' . esc_html($mentor_id) . ')</td></tr>';
    echo '<tr><th>Child</th><td>' . ucfirst($child ? $child->display_name : 'Unknown') . ' (ID: ' . esc_html($child_id) . ')</td></tr>';
    echo '<tr><th>Appointment Status</th><td><span class="badge status-' . esc_attr(strtolower($appointment_status)) . '">' . ucfirst($appointment_status) . '</span></td></tr>';
    echo '</tbody></table>';
    echo '</div>';

    // ---- Feedback ----
    if ($appointment_status === 'finished') {
        echo '<div class="appointment-card">';
        echo '<h2><span class="dashicons dashicons-format-chat"></span> Feedback</h2>';
        echo '<table class="form-table"><tbody>';
        if ($feedback) {
            echo '<tr><th>Short Note</th><td>' . esc_html($feedback->feedback_short_note ?: 'N/A') . '</td></tr>';
            echo '<tr><th>Voice Note</th><td>' . ($feedback->feedback_voice_notes ? '<a class="button button-small" href="' . esc_url($feedback->feedback_voice_notes) . '" target="_blank">🎧 Listen</a>' : 'No voice note') . '</td></tr>';
            echo '<tr><th>Created At</th><td>' . esc_html(date('F d, Y, h:i A', strtotime($feedback->created_at))) . '</td></tr>';
            echo '<tr><th>Updated At</th><td>' . esc_html(date('F d, Y, h:i A', strtotime($feedback->updated_at))) . '</td></tr>';
        } else {
            echo '<tr><td colspan="2" style="text-align:center;">No feedback available.</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        // ---- Expense ----
        echo '<div class="appointment-card">';
        echo '<h2><span class="dashicons dashicons-money-alt"></span> Expense</h2>';
        echo '<table class="form-table"><tbody>';
        if ($expense) {
            echo '<tr><th>Amount</th><td><strong>' . wc_price($expense->expense_amount) . '</strong></td></tr>';
            echo '<tr><th>Description</th><td>' . esc_html($expense->expense_description ?: 'N/A') . '</td></tr>';
            echo '<tr><th>Receipt</th><td>' . ($expense->expense_receipt ? '<a class="button button-small" href="' . esc_url($expense->expense_receipt) . '" target="_blank">📄 View Receipt</a>' : 'No receipt uploaded') . '</td></tr>';
            echo '<tr><th>Status</th><td>
                    <form method="post" style="margin:0;">
                        <select name="expense_status">
                            <option value="pending" ' . selected($expense->expense_status, 'pending', false) . '>Pending</option>
                            <option value="approved" ' . selected($expense->expense_status, 'approved', false) . '>Approved</option>
                            <option value="rejected" ' . selected($expense->expense_status, 'rejected', false) . '>Rejected</option>
                        </select>
                        <input type="hidden" name="update_expense_status" value="1">
                        <input type="hidden" name="expense_id" value="' . intval($expense->expense_id) . '">
                        <button type="submit" class="button button-primary button-small">Update</button>
                    </form>
                </td></tr>';

        } else {
            echo '<tr><td colspan="2" style="text-align:center;">No expense available.</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    echo '</div>'; // .wrap

    echo '<style>
            .appointment-details-page .appointment-card {
                background: #fff;
                padding: 10px 20px;
                margin: 20px 0;
                border-radius: 8px;
                box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            }
            .appointment-details-page h2 {
                margin-bottom: 15px;
                margin-top: 5px;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
                font-size: 16px;
                color: #23282d;
            }
            .appointment-details-page table.form-table th {
                width: 200px;
                color: #555;
            }
            .appointment-details-page table.form-table td {
                font-weight: 500;
            }
            .badge {
                padding: 3px 8px;
                border-radius: 5px;
                font-size: 12px;
                font-weight: 600;
                display: inline-block;
            }
            .status-pending, .status-on-hold { background: #fff3cd; color: #856404; }
            .status-approved, .status-completed { background: #d4edda; color: #155724; }
            .status-cancelled { background: #f8d7da; color: #721c24; }
            .status-finished { background: #1787ff4f; color: #2271b1; }
        </style>';
}