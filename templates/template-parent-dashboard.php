<?php
/**
 * Template Name: Parent Dashboard
 */
get_header();
?>

<!-- Parent Dashboard Template with Bootstrap 5 and Child Cards -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Hero Section from Kadence -->
<section class="entry-hero page-hero-section entry-hero-layout-standard">
  <div class="entry-hero-container-inner">
    <div class="hero-section-overlay"></div>
    <div class="hero-container site-container">
      <header class="entry-header page-title title-align-inherit title-tablet-align-inherit title-mobile-align-inherit">
        <h1 class="entry-title">Parent Dashboard</h1>
      </header>
    </div>
  </div>
</section>

<div class="container my-5">
  <!-- Welcome Section -->
  <div class="mb-4">
    <h2 class="mb-1">Welcome, <span class="text-primary"><?php echo wp_get_current_user()->display_name; ?></span></h2>
    <p class="text-muted">Manage your child's mentoring sessions, view progress, and stay connected.</p>
  </div>

  <div class="d-flex justify-content-end mb-3">
    <a href="<?php echo esc_url(site_url('/add-child/')); ?>" class="btn btn-outline-success">
      <strong>+ Add Child</strong>
    </a>
  </div>

  <!-- Child Card Selector -->
  <div class="row g-4 mb-4">
    <?php
    $current_user = wp_get_current_user();
    $parent_id = $current_user->ID;

    // Fetch children of current parent
    $children = get_users(array(
        'role'    => 'child_user',
        'meta_key'   => 'assigned_parent_id',
        'meta_value' => $parent_id,
    ));

    if (!empty($children)) :
        foreach ($children as $child) :
            // Optional: Use a custom user meta for profile image, fallback to default
            $profile_image = get_user_meta($child->ID, 'profile_picture_url', true);
            if (!$profile_image) {
                $profile_image = get_stylesheet_directory_uri() . '/assets/images/user_default.png';
            }
            ?>
            <div class="col-md-6">
              <div class="card child-card border-primary shadow-sm h-100">
                <div class="card-body text-center">
                  <img src="<?php echo esc_url($profile_image); ?>" class="rounded-circle mb-3" alt="Child Image" width="100" height="100">
                  <h5 class="card-title"><?php echo esc_html($child->display_name); ?></h5>
                  <a href="<?php echo esc_url(add_query_arg('child_id', $child->ID, site_url('/child-dashboard/'))); ?>" class="btn btn-outline-primary w-100">
                    View <?php echo esc_html($child->display_name); ?>'s Dashboard
                  </a>
                </div>
              </div>
            </div>
        <?php endforeach; ?>

        <!-- Next Session -->
        <div class="col-12">
          <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
              <h4 class="card-title mb-3 fw-bold text-primary">Next Session</h4>
              <?php
              $parent_id = $current_user->ID;

              // Fetch all orders for the current parent
              $args = array(
                  'customer_id' => $parent_id,
              );
              $orders = wc_get_orders($args);

              $sessions = array();
              foreach ($orders as $order) {
                  foreach ($order->get_items() as $item_id => $item) {
                      $mentor_id = $item->get_meta('mentor_id');
                      $child_id = $item->get_meta('child_id');
                      $session_date_time = $item->get_meta('session_date_time');
                      $appointment_status = $item->get_meta('appointment_status') ?: 'N/A';

                      if ($mentor_id && $child_id && $session_date_time) {
                          $mentor = get_user_by('id', $mentor_id);
                          $child = get_user_by('id', $child_id);

                          $sessions[] = array(
                              'date_time' => new DateTime($session_date_time, new DateTimeZone('Asia/Kolkata')),
                              'mentor_name' => $mentor ? $mentor->display_name : 'Unknown Mentor',
                              'mentor_id' => $mentor_id,
                              'child_name' => $child ? $child->display_name : 'Unknown Child',
                              'child_id' => $child_id,
                              'appointment_status' => $appointment_status,
                              'order_id' => $order->get_id(),
                          );
                      }
                  }
              }

              // Sort sessions by date/time
              usort($sessions, function($a, $b) {
                  return $a['date_time'] <=> $b['date_time'];
              });

              // Filter future sessions (after today)
              $today = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
              $future_sessions = array_filter($sessions, function($session) use ($today) {
                  return $session['date_time'] > $today;
              });

              // Get next session (first future session)
              $next_session = !empty($future_sessions) ? array_shift($future_sessions) : null;
              ?>
              <?php if ($next_session) : ?>
                <div class="session-details">
                  <div class="row g-3">
                    <div class="col-6">
                      <p class="mb-2"><strong>Date:</strong> <span class="text-success fw-medium"><?php echo esc_html($next_session['date_time']->format('F d, Y')); ?></span></p>
                    </div>
                    <div class="col-6">
                      <p class="mb-2"><strong>Time:</strong> <span class="text-success fw-medium"><?php echo esc_html($next_session['date_time']->format('h:i A')); ?></span></p>
                    </div>
                    <div class="col-6">
                      <p class="mb-2"><strong>Mentor:</strong> <span class="text-primary fw-medium"><?php echo esc_html($next_session['mentor_name']); ?> (ID: <?php echo esc_html($next_session['mentor_id']); ?>)</span></p>
                    </div>
                    <div class="col-6">
                      <p class="mb-2"><strong>Child:</strong> <span class="text-primary fw-medium"><?php echo esc_html($next_session['child_name']); ?> (ID: <?php echo esc_html($next_session['child_id']); ?>)</span></p>
                    </div>
                    <div class="col-6">
                      <p class="mb-2"><strong>Status:</strong> <span class="badge bg-info text-dark"><?php echo esc_html($next_session['appointment_status']); ?></span></p>
                    </div>
                    <div class="col-6">
                      <p class="mb-0"><strong>Order ID:</strong> <span class="text-secondary"><?php echo esc_html($next_session['order_id']); ?></span></p>
                    </div>
                  </div>
                </div>
              <?php else : ?>
                <p class="text-muted text-center">No upcoming sessions scheduled.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Upcoming Sessions -->
        <div class="col-12">
          <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
              <h4 class="card-title mb-3 fw-bold text-primary">Upcoming Sessions</h4>
              <?php if (!empty($future_sessions)) : ?>
                <div class="session-list">
                  <?php foreach ($future_sessions as $session) : ?>
                    <div class="session-item mb-3 p-3 border rounded">
                      <div class="row g-3">
                        <div class="col-6">
                          <p class="mb-1"><strong>Date:</strong> <span class="text-success fw-medium"><?php echo esc_html($session['date_time']->format('F d, Y')); ?></span></p>
                        </div>
                        <div class="col-6">
                          <p class="mb-1"><strong>Time:</strong> <span class="text-success fw-medium"><?php echo esc_html($session['date_time']->format('h:i A')); ?></span></p>
                        </div>
                        <div class="col-6">
                          <p class="mb-1"><strong>Mentor:</strong> <span class="text-primary fw-medium"><?php echo esc_html($session['mentor_name']); ?> (ID: <?php echo esc_html($session['mentor_id']); ?>)</span></p>
                        </div>
                        <div class="col-6">
                          <p class="mb-1"><strong>Child:</strong> <span class="text-primary fw-medium"><?php echo esc_html($session['child_name']); ?> (ID: <?php echo esc_html($session['child_id']); ?>)</span></p>
                        </div>
                        <div class="col-6">
                          <p class="mb-0"><strong>Status:</strong> <span class="badge bg-info text-dark"><?php echo esc_html(ucfirst($session['appointment_status'])); ?></span></p>
                        </div>
                        <div class="col-6">
                          <p class="mb-0"><strong>Order ID:</strong> <span class="text-secondary"><?php echo esc_html($session['order_id']); ?></span></p>
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

        <?php 
        $mentoring_plan_info = get_field('mentoring_plan_info', 'user_20');
        $mentoring_plan_file = get_field('mentoring_plan_file', 'user_20');
        ?>

        <div class="col-12">
          <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title mb-0 fw-bold text-primary">Mentoring Plan</h4>
                <?php if ($mentoring_plan_file): ?>
                  <a href="<?php echo esc_url($mentoring_plan_file['url']); ?>" 
                     class="btn btn-primary" 
                     target="_blank" 
                     download>
                    Download Plan (PDF)
                  </a>
                <?php endif; ?>
              </div>
              
              <?php if ($mentoring_plan_info): ?>
                <div class="mentoring-plan-info text-muted">
                  <?php echo wp_kses_post($mentoring_plan_info); ?>
                </div>
              <?php else: ?>
                <p class="text-muted">No mentoring plan information available.</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
              <h4 class="card-title mb-3 fw-bold text-primary">Session Feedback</h4>
              <ul class="list-group list-group-flush">
                <li class="list-group-item">July 28 - "Great progress today." <a href="#" class="text-primary">Listen</a></li>
                <li class="list-group-item">July 21 - "Needs support with reading."</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
              <h4 class="card-title mb-3 fw-bold text-primary">Invoices</h4>
              <ul class="list-group list-group-flush">
                <li class="list-group-item">Invoice #1001 <a href="#" class="text-primary">Download PDF</a></li>
                <li class="list-group-item">Invoice #1002 <a href="#" class="text-primary">Download PDF</a></li>
              </ul>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card shadow-sm mb-5">
            <div class="card-body p-4 d-flex flex-wrap gap-3">
              <a href="https://wa.me/MENTORNUMBER" class="btn btn-success">Contact Mentor</a>
              <a href="mailto:admin@example.com" class="btn btn-dark">Contact Admin</a>
            </div>
          </div>
        </div>
    <?php else : ?>
      <div class="col-12">
        <div class="alert alert-warning text-center">
          No children assigned to your account.
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<style>
  .child-card img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    justify-self: center;
    align-self: center;
  }
  .child-card .btn {
    margin-top: 10px;
  }

  /* Session Card Styling */
  .session-details, .session-item {
    background-color: #f8f9fa;
    padding: 1.5rem;
    border-radius: 0.5rem;
    border-left: 4px solid #0d6efd;
  }
  .session-item {
    background-color: #ffffff;
    border-left-color: #6c757d;
  }
  .session-details .row, .session-item .row {
    align-items: center;
  }
  .session-details p, .session-item p {
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
  }
  .session-details .text-success, .session-item .text-success {
    font-weight: 500;
  }
  .session-details .badge, .session-item .badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.85rem;
  }
  .session-list .session-item:last-child {
    margin-bottom: 0;
  }
  .card-title {
    font-size: 1.25rem;
  }
  .mentoring-plan-info {
    font-size: 0.9rem;
    line-height: 1.6;
  }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php get_footer(); ?>