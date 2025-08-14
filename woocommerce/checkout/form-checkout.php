<?php
/**
 * Checkout Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-checkout.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="<?php echo esc_attr__( 'Checkout', 'woocommerce' ); ?>">

	<?php if ( $checkout->get_checkout_fields() ) : ?>

		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

		<div class="col2-set" id="customer_details">
			<div class="col-1">
				<?php do_action( 'woocommerce_checkout_billing' ); ?>
			</div>

			<div class="col-2">
				<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			</div>
		</div>

		<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

	<?php endif; ?>
	
	<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
	
	<h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>
	
	
	<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

	<div id="order_review" class="woocommerce-checkout-review-order">
		<?php
			/* ======================== Dynamic Appointment Data ======================== */
				$cart_items = WC()->cart->get_cart();
				if ( ! empty( $cart_items ) ) {
					$cart_item = $cart_items[ key( $cart_items ) ]; // First cart item
					$product_name = $cart_item['data']->get_name();
					$mentor_id = $cart_item['mentor_id'] ?? '';
					$child_id = $cart_item['child_id'] ?? '';
					$session_date_time = $cart_item['session_date_time'] ?? '';

					$mentor_user = $mentor_id ? get_userdata( $mentor_id ) : null;
					$mentor_name = $mentor_user ? $mentor_user->display_name : 'Unknown';

					$child_user = $child_id ? get_userdata( $child_id ) : null;
					$child_name = $child_user ? $child_user->display_name : 'Unknown';

					if ( $session_date_time ) {
						$datetime = new DateTime( $session_date_time, new DateTimeZone( wp_timezone_string() ) );
						$formatted_datetime = $datetime->format( 'M d, Y — h:i A' ) . ' to ' . $datetime->modify( '+1 hour' )->format( 'h:i A' );
					} else {
						$formatted_datetime = 'Not set';
					}
					?>
					
					<div class="appointment-details" style="margin-bottom:20px;padding:15px;border:2px solid #ddd;border-radius:8px;background:#f9f9f9;">
						<h3 style="margin-bottom:15px;font-size:18px;color:#333;"><i class="fas fa-calendar-check" style="color:#0073aa;margin-right:8px;"></i> Appointment Details</h3>
						
						<div class="detail-item" style="margin-bottom:8px;">
							<strong>Appointment Title:</strong> <?php echo esc_html( $product_name ); ?>
						</div>
						<div class="detail-item" style="margin-bottom:8px;">
							<strong>Child Name:</strong> <?php echo ucfirst( esc_html( $child_name ) ); ?>
						</div>
						<div class="detail-item" style="margin-bottom:8px;">
							<strong>Mentor Name:</strong> <?php echo ucfirst( esc_html( $mentor_name ) ); ?>
						</div>
						<div class="detail-item" style="margin-bottom:8px;">
							<strong>Time:</strong> <?php echo esc_html( $formatted_datetime ); ?>
						</div>
						<div class="detail-item">
							<strong>Location:</strong> Online
						</div>
					</div>
					<?php
				}
			/* ======================== Dynamic Appointment Data ======================== */
		?>
		<?php do_action( 'woocommerce_checkout_order_review' ); ?>
	</div>

	<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
