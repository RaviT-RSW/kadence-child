<?php
/**
 * Template Name: Appointment History
 */
get_header();

// Get the child_id and mentor_id from the query parameter or current user
$child_id = isset($_GET['child_id']) ? intval($_GET['child_id']) : 0;
$current_mentor = wp_get_current_user();
$mentor_id = $current_mentor->ID;

if (!$child_id || !$mentor_id) {
    wp_redirect(home_url());
    exit;
}

// Fetch mentee details
$mentee = get_user_by('id', $child_id);
$mentee_name = $mentee ? $mentee->display_name : 'Unknown Mentee';

// Fetch records from wp_assigned_mentees for the specific child and mentor
global $wpdb;
$records = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}assigned_mentees WHERE child_id = %d AND mentor_id = %d ORDER BY created_at DESC",
        $child_id,
        $mentor_id
    )
);

// Fetch existing feedback and expense for each order
$feedbacks = [];
$expenses = [];
if ($records) {
    foreach ($records as $record) {
        $feedback = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}appointment_feedback WHERE order_id = %d",
                $record->order_id
            )
        );
        $feedbacks[$record->order_id] = $feedback;

        $expense = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}appointment_expense WHERE order_id = %d",
                $record->order_id
            )
        );
        $expenses[$record->order_id] = $expense;
    }
}
?>

<style>
    .appointment-history-container {
        max-width: 1300px;
        margin: 0 auto;
        padding: 20px;
    }
    .header-section {
        background: #007bff;
        color: #ffffff;
        padding: 10px 20px;
        border-radius: 5px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .header-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
    }
    .header-subtitle {
        font-size: 1rem;
        font-weight: 400;
        margin: 0;
    }
    .card {
        border: none;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    .table-custom {
        background-color: #ffffff;
        border-radius: 0 0 10px 10px;
    }
    .table-custom th {
        background-color: #46cdb4;
        color: #ffffff;
        font-weight: 600;
        padding: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .table-custom td {
        padding: 12px;
        vertical-align: middle;
        border-top: 1px solid #e9ecef;
    }
    .btn-view-details, .btn-finish-appointment, .btn-add-feedback, .btn-add-expense {
        background-color: #28a745;
        color: #ffffff;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        font-weight: 500;
        transition: background-color 0.3s ease, transform 0.2s ease;
        margin-right: 5px;
        margin-bottom: 5px;
    }
    .btn-view-details:hover, .btn-finish-appointment:hover, .btn-add-feedback:hover, .btn-add-expense:hover {
        background-color: #218838;
        transform: translateY(-2px);
        color: #ffffff;
    }
    .no-records {
        text-align: center;
        color: #6c757d;
        padding: 40px;
        font-size: 1.1rem;
        background-color: #f8f9fa;
        border-radius: 10px;
    }
    .modal-content {
        border-radius: 10px;
    }
    .modal-header {
        background-color: #46cdb4;
        color: #ffffff;
    }
    .modal-footer .btn-primary {
        background-color: #28a745;
        border: none;
    }
    .modal-footer .btn-primary:hover {
        background-color: #218838;
    }
    @media (max-width: 768px) {
        .header-title {
            font-size: 1.25rem;
        }
        .header-subtitle {
            font-size: 0.9rem;
        }
        .table-custom th,
        .table-custom td {
            padding: 8px;
        }
    }
</style>


<div class="container">

  <?php
    if (!isset($_GET['child_id']) || !is_numeric($_GET['child_id'])) {
        return '<p>No user ID provided.</p>';
    }

    $goals = get_child_goal($_GET['child_id']);

    ?>
    <!-- Target Goals -->
    <div class="row g-4 mb-4">
        <?php foreach ($goals as $index => $goal) : ?>
            <div class="col-md-4">
                <div class="card border-success shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title">🎯 Goal <?php echo $index + 1; ?></h5>
                        <p class="card-text"><?php echo !empty($goal) ? esc_html($goal) : 'No goal set.'; ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php echo do_shortcode('[mentor_set_goals_form]') ?>

</div>


<div class="appointment-history-container my-5">
    <div class="header-section">
        <div>
            <div class="header-title">Appointment History</div>
            <div class="header-subtitle">For <span class="fw-bold"><?php echo ucfirst($mentee_name); ?></span></div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if ($records) : ?>
                <div class="table-responsive">
                    <table class="table table-custom table-hover">
                        <thead>
                            <tr>
                                <th>Mentee Name</th>
                                <th>Title</th>
                                <th>Amount</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record) :
                                // Get the order
                                $order = wc_get_order($record->order_id);
                                if ($order) {
                                    // Get the first item (assuming one item per order for simplicity)
                                    $items = $order->get_items();
                                    $item = reset($items);
                                    $product_name = $item ? $item->get_name() : 'Unknown Product';
                                    $order_amount = $order->get_total();
                                    $session_date_time = $item->get_meta('session_date_time');
                                    $appointment_status = $item->get_meta('appointment_status');
                                    $session_date_time_obj = new DateTime($session_date_time, new DateTimeZone('Asia/Kolkata'));
                                } else {
                                    $product_name = 'Order Not Found';
                                    $order_amount = 'N/A';
                                    $session_date_time_obj = new DateTime($record->created_at, new DateTimeZone('Asia/Kolkata'));
                                }
                            ?>
                            <tr>
                                <td><?php echo ucfirst($mentee_name); ?></td>
                                <td><?php echo esc_html($product_name); ?></td>
                                <td>$<?php echo esc_html(number_format($order_amount, 2)); ?></td>
                                <td><?php echo esc_html($session_date_time_obj->format('F d, Y - h:i A')); ?></td>
                                <td><?php echo ucfirst($appointment_status); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(array('order_id' => $record->order_id, 'item_id' => $item ? $item->get_id() : 0), site_url('/appointment-details/'))); ?>" class="badge bg-info text-light text-decoration-none btn-view-details">View Details</a>
                                    <?php if (strtolower($appointment_status) === 'approved') : ?>
                                        <button class="badge bg-success text-light mt-2 btn-finish-appointment" data-item-id="<?php echo esc_attr($item ? $item->get_id() : 0); ?>" data-order-id="<?php echo esc_attr($record->order_id); ?>">Finish Appointment</button>
                                    <?php elseif (strtolower($appointment_status) === 'finished') : ?>
                                        <button class="badge bg-primary text-light mt-2 btn-add-feedback" data-order-id="<?php echo esc_attr($record->order_id); ?>" data-feedback-note="<?php echo isset($feedbacks[$record->order_id]) ? esc_attr($feedbacks[$record->order_id]->feedback_short_note) : ''; ?>" data-feedback-voice="<?php echo isset($feedbacks[$record->order_id]) ? esc_attr($feedbacks[$record->order_id]->feedback_voice_notes) : ''; ?>">Add/Edit Feedback</button>
                                        <button class="badge bg-warning text-dark mt-2 btn-add-expense" data-order-id="<?php echo esc_attr($record->order_id); ?>" data-expense-amount="<?php echo isset($expenses[$record->order_id]) ? esc_attr($expenses[$record->order_id]->expense_amount) : ''; ?>" data-expense-description="<?php echo isset($expenses[$record->order_id]) ? esc_attr($expenses[$record->order_id]->expense_description) : ''; ?>" data-expense-receipt="<?php echo isset($expenses[$record->order_id]) ? esc_attr($expenses[$record->order_id]->expense_receipt) : ''; ?>" data-expense-status="<?php echo isset($expenses[$record->order_id]) ? ucfirst($expenses[$record->order_id]->expense_status) : ''; ?>">Add/Edit Expense</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="no-records">
                    <p>No appointment history available for <?php echo esc_html($mentee_name); ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="feedbackModalLabel">Add/Edit Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="feedbackForm" enctype="multipart/form-data">
                    <input type="hidden" id="feedbackOrderId" name="order_id">
                    <div class="mb-3">
                        <label for="feedbackNote" class="form-label">Feedback Short Note</label>
                        <textarea class="form-control" id="feedbackNote" name="feedback_note" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="feedbackVoice" class="form-label">Voice Note (MP3)</label>
                        <input type="file" class="form-control" id="feedbackVoice" name="feedback_voice" accept=".mp3">
                        <small class="form-text text-muted" id="existingVoiceNote"></small>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Feedback</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1" aria-labelledby="expenseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="expenseModalLabel">Add/Edit Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="expenseForm" enctype="multipart/form-data">
                    <input type="hidden" id="expenseOrderId" name="order_id">
                    <div class="mb-3">
                        <label for="expenseAmount" class="form-label">Expense Amount</label>
                        <input type="number" class="form-control" id="expenseAmount" name="expense_amount" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="expenseDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="expenseDescription" name="expense_description" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="expenseReceipt" class="form-label">Receipt (PDF/Image)</label>
                        <input type="file" class="form-control" id="expenseReceipt" name="expense_receipt" accept=".pdf,.jpg,.jpeg,.png">
                        <small class="form-text text-muted" id="existingReceipt"></small>
                    </div>
                    <div class="mb-3">
                        <label for="expenseStatus" class="form-label">Status</label>
                        <input type="text" class="form-control" id="expenseStatus" name="expense_status" readonly>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Expense</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Finish Appointment
    $('.btn-finish-appointment').on('click', function() {
        var itemId = $(this).data('item-id');
        var orderId = $(this).data('order-id');

        if (confirm('Are you sure you want to finish this appointment?')) {
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'finish_appointment',
                    item_id: itemId,
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('finish_appointment_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Appointment finished successfully!');
                        location.reload();
                    } else {
                        alert('Error finishing appointment: ' + (response.data.message || 'Unknown error'));
                    }
                },
                error: function() {
                    alert('Error finishing appointment. Please try again.');
                }
            });
        }
    });

    // Open Feedback Modal
    $('.btn-add-feedback').on('click', function() {
        var orderId = $(this).data('order-id');
        var feedbackNote = $(this).data('feedback-note');
        var feedbackVoice = $(this).data('feedback-voice');

        $('#feedbackOrderId').val(orderId);
        $('#feedbackNote').val(feedbackNote);
        $('#existingVoiceNote').html(feedbackVoice ? 'Existing voice note: <a href="' + feedbackVoice + '" target="_blank">Listen</a>' : 'No voice note uploaded');
        $('#feedbackModal').modal('show');
    });

    // Submit Feedback
    $('#feedbackForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('action', 'save_appointment_feedback');
        formData.append('nonce', '<?php echo wp_create_nonce('save_feedback_nonce'); ?>');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Feedback saved successfully!');
                    $('#feedbackModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error saving feedback: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('Error saving feedback. Please try again.');
            }
        });
    });

    // Open Expense Modal
    $('.btn-add-expense').on('click', function() {
        var orderId = $(this).data('order-id');
        var expenseAmount = $(this).data('expense-amount');
        var expenseDescription = $(this).data('expense-description');
        var expenseReceipt = $(this).data('expense-receipt');
        var expenseStatus = $(this).data('expense-status');

        $('#expenseOrderId').val(orderId);
        $('#expenseAmount').val(expenseAmount);
        $('#expenseDescription').val(expenseDescription);
        $('#existingReceipt').html(expenseReceipt ? 'Existing receipt: <a href="' + expenseReceipt + '" target="_blank">View</a>' : 'No receipt uploaded');
        $('#expenseStatus').val(expenseStatus || 'Pending');
        $('#expenseModal').modal('show');
    });

    // Submit Expense
    $('#expenseForm').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('action', 'save_appointment_expense');
        formData.append('nonce', '<?php echo wp_create_nonce('save_expense_nonce'); ?>');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Expense saved successfully!');
                    $('#expenseModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error saving expense: ' + (response.data.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr.responseText, status, error);
                alert('Error saving expense. Please try again.');
            }
        });
    });
});
</script>

<?php get_footer(); ?>