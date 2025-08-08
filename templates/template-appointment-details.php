<?php
/**
 * Template Name: Appointment Details
 */
get_header();
?>



<div class="container my-5">
  <div class="row">
    <div class="col-12">
      <?php
      // Retrieve order_id and item_id from URL parameters
      $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
      $item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : 0;

      // Fetch the order object
      $order = wc_get_order($order_id);
      if ($order) {
          // Fetch the specific order item
          $item = $order->get_item($item_id);
          if ($item) {
              // Get custom meta data
              $mentor_id = $item->get_meta('mentor_id');
              $child_id = $item->get_meta('child_id');
              $session_date_time = $item->get_meta('session_date_time');
              $appointment_status = $item->get_meta('appointment_status') ?: 'N/A';

              $appointment_status_class = '';
              if ( $appointment_status === 'approved' ) {
                $appointment_status_class = 'badge bg-success text-light';
              } elseif ( $appointment_status === 'cancelled' ) {
                $appointment_status_class = 'badge bg-danger text-light';
              } else {
                $appointment_status_class = 'badge bg-info text-light';
              }

              // Get mentor and child names
              $mentor = get_user_by('id', $mentor_id);
              $child = get_user_by('id', $child_id);

              // Get product name
              $product_name = $item->get_name();
      ?>
      <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
          <h4 class="card-title mb-3 fw-bold text-primary">Order Information</h4>
          <div class="row g-3">
            <div class="col-md-6">
              <p class="mb-2"><strong>Order ID:</strong> <span class="text-secondary"><?php echo esc_html($order_id); ?></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>Order Status:</strong> <span class="badge bg-info text-dark"><?php echo esc_html(ucfirst($order->get_status())); ?></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>Total Amount:</strong> <span class="text-success fw-medium"><?php echo wc_price($order->get_total()); ?></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>Order Date:</strong> <span class="text-secondary"><?php echo esc_html($order->get_date_created()->format('F d, Y')); ?></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>Payment Method:</strong> <span class="text-secondary"><?php echo esc_html($order->get_payment_method_title()); ?></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>Product:</strong> <span class="text-primary fw-medium"><?php echo esc_html($product_name); ?></span></p>
            </div>
          </div>

          <h4 class="card-title mt-4 mb-3 fw-bold text-primary">Session Details</h4>
          <div class="row g-3">
            <div class="col-md-6">
              <p class="mb-2"><strong>Session Date and Time:</strong> <span class="text-success fw-medium"><?php echo esc_html($session_date_time); ?></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>Mentor:</strong> <span class="text-primary fw-medium"><?php echo esc_html($mentor ? $mentor->display_name : 'Unknown'); ?> (ID: <?php echo esc_html($mentor_id); ?>)</span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>Child:</strong> <span class="text-primary fw-medium"><?php echo esc_html($child ? $child->display_name : 'Unknown'); ?> (ID: <?php echo esc_html($child_id); ?>)</span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>Appointment Status:</strong> <span class="<?php echo esc_attr($appointment_status_class); ?>"><?php echo esc_html(ucfirst($appointment_status)); ?></span></p>
            </div>
          </div>

          <h4 class="card-title mt-4 mb-3 fw-bold text-primary">Billing Address</h4>
          <div class="row g-3">
            <div class="col-md-6">
              <p class="mb-2"><strong>Name:</strong> <span class="text-secondary"><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>Email:</strong> <span class="text-secondary"><?php echo esc_html($order->get_billing_email()); ?></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>Address:</strong> <span class="text-secondary"><?php echo esc_html($order->get_billing_address_1()); ?></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>City:</strong> <span class="text-secondary"><?php echo esc_html($order->get_billing_city()); ?></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>State:</strong> <span class="text-secondary"><?php echo esc_html($order->get_billing_state()); ?></span></p>
            </div>
            <div class="col-md-6">
              <p class="mb-2"><strong>Country:</strong> <span class="text-secondary"><?php echo esc_html($order->get_billing_country()); ?></span></p>
            </div>
          </div>


          <h4 class="card-title mt-4 mb-3 fw-bold text-primary">Actions</h4>
          <div class="d-flex gap-3">
            <?php echo do_shortcode('[wcpdf_download_pdf document_type="invoice" link_text="Download PDF Invoice" order_id="' . $order_id . '" id="invoice-link" class="btn btn-primary btn-sm pdf-invoice pdf-shortcode"]'); ?>
          </div>
        </div>
      </div>
      <?php
          } else {
              echo '<div class="alert alert-danger text-center">Invalid item ID.</div>';
          }
      } else {
          echo '<div class="alert alert-danger text-center">Invalid order ID.</div>';
      }
      ?>
    </div>
  </div>
</div>

<style>
.card-title {
  font-size: 1.25rem;
}
.card-body p {
  font-size: 0.95rem;
}
.text-success {
  font-weight: 500;
}
.badge {
  padding: 0.25rem 0.5rem;
  font-size: 0.85rem;
}
.btn-primary {
  padding: 0.5rem 1rem;
}
.alert {
  font-size: 1rem;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php get_footer(); ?>