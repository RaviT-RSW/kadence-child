<?php
defined( 'ABSPATH' ) || exit;

if ( $order ) :
    $order_id = $order->get_id();
    $order_date = $order->get_date_created()->format('F j, Y');
    $order_email = $order->get_billing_email();
    $payment_method = $order->get_payment_method_title();
    $order_total = $order->get_total();

    // Loop through order items
    foreach ( $order->get_items() as $item ) {
        $product_name = $item->get_name();
        $mentor_id = $item->get_meta('mentor_id');
        $child_id = $item->get_meta('child_id');
        $session_datetime_raw = $item->get_meta('session_date_time');

        $mentor = $mentor_id ? get_user_by('id', $mentor_id) : null;
        $child = $child_id ? get_user_by('id', $child_id) : null;

        // Format date/time
        $start = new DateTime($session_datetime_raw, new DateTimeZone(wp_timezone_string()));
        $end = clone $start;
        $end->modify('+1 hour');
        $session_datetime = $start->format('M j, Y — g:i A') . ' to ' . $end->format('g:i A');

        break; // Assuming one item per session
    }
?>

<!-- Don't move this css as needed only for this page -->
<style>
    .content-area{ margin-top:0; }
    .entry-content-wrap{ padding:0; }
    .site-container { padding:0;  max-width: 100%;}
</style>
<!-- Don't move this css as needed only for this page -->


<!-- Begin HTML Output -->
<div class="thankyou-container">
    <div class="thankyou-card">
        <div class="thankyou-header">
            <i class="fas fa-check-circle"></i>
            <h2>Thank You for Your Booking!</h2>
            <p>Your appointment has been confirmed successfully.</p>
        </div>

        <div class="thankyou-appointment">
            <h3><i class="fas fa-calendar-check"></i> Appointment Details</h3>
            <div class="detail-item">
                <span class="label">Appointment Title:</span>
                <span class="value"><?php echo esc_html($product_name); ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Child Name:</span>
                <span class="value"><?php echo ucfirst(esc_html($child ? $child->display_name : 'N/A')); ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Mentor Name:</span>
                <span class="value"><?php echo ucfirst(esc_html($mentor ? $mentor->display_name : 'N/A')); ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Time:</span>
                <span class="value"><?php echo esc_html($session_datetime); ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Location:</span>
                <span class="value">Online</span>
            </div>
        </div>

        <div class="thankyou-order">
            <h3><i class="fas fa-receipt"></i> Order Details</h3>
            <div class="detail-item">
                <span class="label">Order Number:</span>
                <span class="value">#<?php echo esc_html($order_id); ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Date:</span>
                <span class="value"><?php echo esc_html($order_date); ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Customer Email:</span>
                <span class="value"><?php echo esc_html($order_email); ?></span>
            </div>
        </div>

        <div class="thankyou-summary">
            <h3><i class="fas fa-money-bill"></i> Payment Details</h3>
            <div class="summary-item">
                <span class="label">Payment Status:</span>
                <span class="value"> <b>  <?php echo urm_get_payment_status($order->get_id()); ?> </b> </span>
            </div>

            <div class="summary-item">
                <span class="label">Payment Method:</span>
                <span class="value"> <?php echo esc_html($payment_method); ?> </span>
            </div>

            <div class="summary-item">
                <span class="label">Subtotal:</span>
                <span class="value"><?php echo wc_price($order->get_subtotal()); ?></span>
            </div>
            <div class="summary-item total">
                <span class="label">Total:</span>
                <span class="value"><?php echo wc_price($order_total); ?></span>
            </div>
        </div>

        <div class="thankyou-footer">
			<p><a href="<?php echo esc_url(home_url()); ?>" class="btn-primary thankyou-buttons">Go to My Dashboard</a></p>
			<p><a href="<?php echo esc_url(home_url('/book-session/')); ?>" class="btn-secondary">Book Another Session</a></p>
			<?php echo do_shortcode('[wcpdf_download_pdf document_type="invoice" link_text="Download PDF Invoice" order_id="' . $order_id . '" id="invoice-link" class="btn-download btn-sm pdf-invoice pdf-shortcode thankyou-buttons"]'); ?>
			
        </div>
    </div>
</div>


<?php endif; ?>
