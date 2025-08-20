<?php
/**
 * Template Name: Child Dashboard
 */
get_header();
?>

<div class="container my-5">
<?php
$user = wp_get_current_user();
if (isset($_GET['child_id']) && is_numeric($_GET['child_id'])) {
    $child_id = intval($_GET['child_id']);
    $child_user = get_user_by('id', $child_id);
    if ($child_user instanceof WP_User) {
        $user = $child_user;
    }
}
$display_name = esc_html($user->display_name);
$current_child_id = $user->ID;
$parent_id = get_user_meta($current_child_id, 'assigned_parent_id', true);
?>

  <!-- Welcome Section -->
  <div class="mb-4 text-center text-md-start">
    <h2 class="mb-1">Welcome back, <span style="color: #114470;"><?php echo $display_name; ?></span></h2>
    <p class="text-muted">Here's what's coming up for you.</p>
  </div>

  <?php
  $sessions = array();
  if ($parent_id) {
      $args = array(
          'customer_id' => $parent_id,
          'limit' => -1,
      );
      $orders = wc_get_orders($args);
      foreach ($orders as $order) {
          foreach ($order->get_items() as $item_id => $item) {
              $mentor_id = $item->get_meta('mentor_id');
              $child_id_meta = $item->get_meta('child_id');
              $session_date_time = $item->get_meta('session_date_time');
              $appointment_status = $item->get_meta('appointment_status') ?: 'Scheduled';
              $zoom_link = $item->get_meta('zoom_link') ?: '';
              $location = $item->get_meta('location') ?: 'Online';
              if ($mentor_id && $child_id_meta == $current_child_id && $session_date_time) {
                  $mentor = get_user_by('id', $mentor_id);
                  $product_name = $item->get_name();
                  $sessions[] = array(
                      'date_time' => new DateTime($session_date_time, new DateTimeZone('Asia/Kolkata')),
                      'mentor_name' => $mentor ? $mentor->display_name : 'Unknown Mentor',
                      'mentor_id' => $mentor_id,
                      'appointment_status' => $appointment_status,
                      'order_id' => $order->get_id(),
                      'product_name' => $product_name,
                      'zoom_link' => $zoom_link,
                      'location' => $location,
                  );
              }
          }
      }
  }

  usort($sessions, function($a, $b) {
      return $a['date_time'] <=> $b['date_time'];
  });

  $today = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
  $future_sessions = array_filter($sessions, function($session) use ($today) {
      return $session['date_time'] > $today;
  });

  $next_session = !empty($future_sessions) ? array_shift($future_sessions) : null;

  $status_colors = [
    'approved'  => 'bg-success text-white',
    'pending'   => 'bg-warning text-dark',
    'cancelled' => 'bg-danger text-white',
    'upcoming'  => 'bg-info text-dark',
    'completed' => 'bg-secondary text-white'
  ];

  ?>

  <div class="row g-4">

    <?php $goals = get_child_goal($current_child_id); ?>
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

    <!-- Next Session -->
    <div class="col-12 col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h4 class="card-title fw-bold" style="color: #114470;">Next Session</h4>
          <?php if ($next_session) : ?>
          <div class="d-flex flex-column flex-sm-row align-items-start pb-3 mb-3">

            <!-- Date Badge -->
            <div class="text-center me-sm-3 mb-3 mb-sm-0">
              <div class="text-white rounded-top px-3 py-1 fw-bold fs-5" style="font-size: 24px; background: #3eaeb2;">
                <?php echo esc_html($next_session['date_time']->format('M')); ?>
              </div>
              <div class="border rounded-bottom px-2 py-1 fw-bold fs-5">
                <?php echo esc_html($next_session['date_time']->format('d')); ?>
              </div>
            </div>

            <!-- Event Details -->
            <div class="flex-grow-1">
              <h5 class="mb-1 fw-bold text-dark"><?php echo esc_html($next_session['product_name']); ?></h5>
              <p class="mb-1 text-muted">By <?php echo esc_html($next_session['mentor_name']); ?></p>
              <p class="mb-1 text-muted">
                <?php echo esc_html($next_session['date_time']->format('D, d M Y - h:i A')); ?>
              </p>
              <p class="mb-1 text-muted">
                Place: <?php echo esc_html($next_session['location']); ?>
              </p>

              <?php
                $status = strtolower($next_session['appointment_status']);
                $badge_class = $status_colors[$status] ?? 'bg-light text-dark'; // fallback
              ?>
              <span class="badge <?php echo $badge_class; ?> mb-3">
                <?php echo ucfirst($next_session['appointment_status']); ?>
              </span>

              <?php if ($next_session['zoom_link']) : ?>
                <div class="mt-2">
                  <a href="<?php echo esc_url($next_session['zoom_link']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                    <i class="fas fa-video me-1"></i>Join Meeting
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <?php else : ?>
            <p class="text-muted text-center">No upcoming sessions scheduled.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Emergency Contacts -->
    <div class="col-12 col-md-6">
      <div class="card shadow-sm bg-light h-100">
        <div class="card-body">
          <h4 class="card-title mb-3 fw-bold text-danger">🚨 Emergency Contacts</h4>
          <div class="row g-3">
            <?php
            $parent_detail = get_user_by('id', $parent_id);
            $phone_number = get_user_meta($parent_id, 'billing_phone', true);

            if (!empty($phone_number )): ?>
            <div class="col-12 col-sm-6">
              <a href="tel:<?php echo $phone_number; ?>" class="btn btn-primary w-100 py-3">
                <i class="fas fa-phone me-2" style="transform: rotate(90deg);"></i>Parent <?php echo $phone_number; ?>
              </a>
            </div>
            <?php endif;

            $field_values = get_field('contact_number', 'option');
            foreach($field_values as $field_value){ ?>
              <div class="col-12 col-sm-6">
                <a href="tel:<?php echo $field_value['number']; ?>" class="btn btn-danger w-100 py-3">
                  <i class="fas fa-phone me-2" style="transform: rotate(90deg);"></i><?php echo $field_value['name']; ?> – <?php echo $field_value['number']; ?>
                </a>
              </div>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Upcoming Sessions -->
    <div class="col-12">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h4 class="card-title mb-3 fw-bold" style="color: #114470;">Upcoming Sessions</h4>

          <?php if (!empty($future_sessions)) : ?>
            <div class="row g-3">
              <?php foreach ($future_sessions as $session) : ?>
                <div class="col-12 col-md-6">
                  <div class="d-flex flex-column flex-sm-row align-items-start border rounded p-3 h-100">
                    <!-- Date Badge -->
                    <div class="text-center me-sm-3 mb-3 mb-sm-0">
                      <div class="text-white rounded-top px-3 py-1 fw-bold fs-5" style="font-size: 24px; background: #3eaeb2;">
                        <?php echo esc_html($session['date_time']->format('M')); ?>
                      </div>
                      <div class="border rounded-bottom px-2 py-1 fw-bold fs-5">
                        <?php echo esc_html($session['date_time']->format('d')); ?>
                      </div>
                    </div>

                    <!-- Event Details -->
                    <div class="flex-grow-1">
                      <h5 class="mb-1 fw-bold text-dark"><?php echo esc_html($session['product_name']); ?></h5>
                      <p class="mb-1 text-muted">By <?php echo esc_html($session['mentor_name']); ?></p>
                      <p class="mb-1 text-muted">
                        <?php echo esc_html($session['date_time']->format('D, d M Y - h:i A')); ?>
                      </p>
                      <p class="mb-1 text-muted">
                        Place: <?php echo esc_html($session['location']); ?>
                      </p>
                      <?php
                        $status = strtolower($session['appointment_status']);
                        $badge_class = $status_colors[$status] ?? 'bg-light text-dark'; // fallback
                      ?>
                      <span class="badge <?php echo $badge_class; ?> mb-3">
                        <?php echo ucfirst($session['appointment_status']); ?>
                      </span>

                      <?php if ($session['zoom_link']) : ?>
                        <div class="mt-2">
                          <a href="<?php echo esc_url($session['zoom_link']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                            <i class="fas fa-video me-1"></i>Join Meeting
                          </a>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else : ?>
            <p class="text-muted text-center">No additional upcoming sessions scheduled.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Chat -->
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="card-title mb-3 fw-bold" style="color: #114470;">💬 Chat with Your Mentor</h4>
          <div class="alert alert-secondary mb-0">
            <?php echo do_shortcode('[user_chat_channels]'); ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php
  $current_user = wp_get_current_user();
  if ( ! is_user_logged_in() || ! is_child_user($current_user) ) {
     echo do_shortcode('[help_form]');
  }
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php get_footer(); ?>
