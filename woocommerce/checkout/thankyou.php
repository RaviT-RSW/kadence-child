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
            <h3><i class="fas fa-receipt"></i> Order Information</h3>
            <div class="detail-item">
                <span class="label">Order Number:</span>
                <span class="value">#<?php echo esc_html($order_id); ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Date:</span>
                <span class="value"><?php echo esc_html($order_date); ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Email:</span>
                <span class="value"><?php echo esc_html($order_email); ?></span>
            </div>
            <div class="detail-item">
                <span class="label">Payment Method:</span>
                <span class="value"><?php echo esc_html($payment_method); ?></span>
            </div>
        </div>

        <div class="thankyou-summary">
            <h3><i class="fas fa-box"></i> Order Summary</h3>
            <div class="summary-item">
                <span><?php echo esc_html($product_name); ?></span>
                <span><?php echo wc_price($item->get_total()); ?></span>
            </div>
            <div class="summary-item">
                <span>Subtotal</span>
                <span><?php echo wc_price($order->get_subtotal()); ?></span>
            </div>
            <div class="summary-item">
                <span>Shipping</span>
                <span><?php echo wc_price($order->get_shipping_total()); ?></span>
            </div>
            <div class="summary-item total">
                <span>Total</span>
                <span><?php echo wc_price($order_total); ?></span>
            </div>
        </div>

        <div class="thankyou-footer">
			<p><a href="<?php echo esc_url(home_url()); ?>" class="btn-primary thankyou-buttons">Go to My Dashboard</a></p>
			<p><a href="<?php echo esc_url(home_url('/book-session/')); ?>" class="btn-secondary">Book Another Session</a></p>
			<?php echo do_shortcode('[wcpdf_download_pdf document_type="invoice" link_text="Download PDF Invoice" order_id="' . $order_id . '" id="invoice-link" class="btn-download btn-sm pdf-invoice pdf-shortcode thankyou-buttons"]'); ?>
			
        </div>
    </div>
</div>

<!-- Include Styles -->
<style>
.thankyou-container {
    display: flex;
    justify-content: center;
    padding: 30px 15px;
    background: linear-gradient(to right, #f5f8fa, #e9f2f9);
    font-family: 'Segoe UI', sans-serif;
}
.thankyou-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 6px 30px rgba(0,0,0,0.1);
    max-width: 750px;
    width: 100%;
    padding: 30px;
    text-align: center;
    animation: fadeInUp 0.6s ease-in-out;
    transition: transform 0.3s ease;
}
.thankyou-card:hover {
    transform: translateY(-5px);
}
.thankyou-header i {
    font-size: 60px;
    color: #28a745;
    animation: bounce 1.2s ease-in-out;
}
.thankyou-header h2 {
    margin-top: 15px;
    font-size: 26px;
    color: #222;
}
.thankyou-header p {
    color: #555;
    font-size: 15px;
}
.thankyou-appointment, .thankyou-order, .thankyou-summary {
    margin-top: 30px;
    text-align: left;
    background: #f9f9f9;
    border-radius: 10px;
    padding: 20px;
}
.thankyou-appointment h3, .thankyou-order h3, .thankyou-summary h3 {
    font-size: 18px;
    margin-bottom: 15px;
    color: #0073aa;
    display: flex;
    align-items: center;
}
.thankyou-appointment h3 i,
.thankyou-order h3 i,
.thankyou-summary h3 i {
    margin-right: 8px;
    color: #0073aa;
}
.detail-item, .summary-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
.detail-item:last-child,
.summary-item:last-child {
    border-bottom: none;
}
.label {
    font-weight: 600;
    color: #333;
}
.value {
    color: #555;
}
.summary-item.total {
    font-weight: bold;
    color: #000;
    font-size: 16px;
}
.thankyou-footer {
    margin-top: 30px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
}
.btn-primary, .btn-secondary, .btn-download {
    padding: 10px 18px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-primary {
    background: #0073aa;
    color: #fff;
}
.btn-primary:hover {
    background: #005f87;
}
.btn-secondary {
    background: #e9ecef;
    color: #333;
}
.btn-secondary:hover {
    background: #d6d8db;
}
.btn-download {
    background: #28a745;
    color: #fff;
}
.btn-download:hover {
    background: #218838;
}
.thankyou-buttons:hover {
    color:white;
}
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}
</style>

<!-- FontAwesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

<?php endif; ?>
