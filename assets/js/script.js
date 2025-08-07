jQuery(document).ready(function ($)
{

    // Handle Appointment cancel button
    $('.cancel-btn').on('click', function () {
        const itemId = $(this).data('item-id');
        const orderId = $(this).data('order-id');

        if (confirm('Are you sure you want to cancel this appointment?')) {
            $.ajax({
                url: php.ajax_url,
                type: 'POST',
                data: {
                    action: 'cancel_session',
                    item_id: itemId,
                    order_id: orderId,
                    nonce: php.mentor_dashboard_nonce
                },
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        showNotification('Failed to cancel: ' + response.message, 'danger');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showNotification('An error occurred. Please try again.', 'danger');
                }
            });
        }
    });


    // Handle Appointment Approve button
    $('.approve-appoinment-btn').on('click', function () {
        const itemId = $(this).data('item-id');
        const orderId = $(this).data('order-id');

        if (confirm('Are you sure you want to approve this appointment?')) {
            $.ajax({
                url: php.ajax_url,
                type: 'POST',
                data: {
                    action: 'approve_session',
                    item_id: itemId,
                    order_id: orderId,
                    nonce: php.mentor_dashboard_nonce
                },
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        showNotification('Failed to approve: ' + response.message, 'danger');
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', error);
                    showNotification('An error occurred. Please try again.', 'danger');
                }
            });
        }
    });

});
