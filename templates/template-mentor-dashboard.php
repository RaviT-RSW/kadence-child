<?php
/**
 * Template Name: Mentor Dashboard
 */
get_header();

// Fetch unique mentees from wp_assigned_mentees
global $wpdb;
$mentees = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT DISTINCT child_id, (SELECT display_name FROM {$wpdb->users} WHERE ID = child_id) as child_name
         FROM {$wpdb->prefix}assigned_mentees
         WHERE mentor_id = %d",
        $mentor_id
    )
);


?>


<div class="container">
  <!-- Welcome Section -->
  <div class="mb-4">
    <h2 class="mb-1">Welcome, <span style="color: #114470;"><?php echo wp_get_current_user()->display_name; ?></span></h2>
    <p class="text-muted">Manage your mentoring sessions, track progress, and connect with your mentees.</p>
  </div>

  <?php
  $current_mentor = wp_get_current_user();
  $mentor_id = $current_mentor->ID;
  $sessions = array();
  $all_orders = wc_get_orders(array(
      'limit' => -1,
      'status' => array('wc-processing', 'wc-on-hold', 'wc-completed'),
  ));

  foreach ($all_orders as $order)
  {
    foreach ($order->get_items() as $item_id => $item)
    {

      $item_mentor_id = $item->get_meta('mentor_id');
      $child_id = $item->get_meta('child_id');
      $session_date_time = $item->get_meta('session_date_time');
      $appointment_status = $item->get_meta('appointment_status') ?: 'N/A';
      $zoom_meeting = $item->get_meta('zoom_meeting') ?: '';
      $location = $item->get_meta('location') ?: 'online';

      $zoom_link = '';

      if($location == 'online' && !empty($zoom_meeting) && class_exists('Zoom'))
      {
        $zoom = new Zoom();
        $zoom_link = $zoom->getMeetingUrl($zoom_meeting, 'start_url');
      }


      if ($item_mentor_id == $mentor_id && $child_id && $session_date_time)
      {
        $child = get_user_by('id', $child_id);
        $product_name = $item->get_name();
        $sessions[] = array(
            'date_time' => new DateTime($session_date_time, new DateTimeZone('Asia/Kolkata')),
            'child_name' => $child ? $child->display_name : 'Unknown Child',
            'child_id' => $child_id,
            'appointment_status' => $appointment_status,
            'order_id' => $order->get_id(),
            'product_name' => $product_name,
            'zoom_link' => $zoom_link,
            'location' => $location,
            'customer_id' => $order->get_customer_id(),
            'item_id' => $item_id,
        );
      }

    }

  }
  
  $today = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
  $future_sessions = array_filter($sessions, function($session) use ($today) {
      return $session['date_time'] > $today;
  });

  $next_session = !empty($future_sessions) ? array_shift($future_sessions) : null;

  // Calculate session statistics
  $total_sessions = count($sessions);
  $approved_sessions = count(array_filter($sessions, function($s) { return strtolower($s['appointment_status']) === 'approved'; }));
  $pending_sessions = count(array_filter($sessions, function($s) { return strtolower($s['appointment_status']) === 'pending'; }));
  $upcoming_sessions = count($future_sessions) + ($next_session ? 1 : 0);
  ?>

  <!-- Session Statistics -->
  <div class="session-stats mb-4">
    <div class="stat-item p-3 shadow-sm rounded bg-light d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="stat-number fs-4 fw-bold text-dark"><?php echo $total_sessions; ?></div>
        <div class="stat-label text-muted">Total Sessions</div>
      </div>
      <div class="stat-icon" style="color: #114470;">
        <i class="fas fa-calendar-alt fa-2x"></i>
      </div>
    </div>
    <div class="stat-item p-3 shadow-sm rounded bg-light d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="stat-number fs-4 fw-bold text-dark"><?php echo $approved_sessions; ?></div>
        <div class="stat-label text-muted">Approved</div>
      </div>
      <div class="stat-icon text-success">
        <i class="fas fa-check-circle fa-2x"></i>
      </div>
    </div>
    <div class="stat-item p-3 shadow-sm rounded bg-light d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="stat-number fs-4 fw-bold text-dark"><?php echo $upcoming_sessions; ?></div>
        <div class="stat-label text-muted">Upcoming</div>
      </div>
      <div class="stat-icon text-info">
        <i class="fas fa-hourglass-half fa-2x"></i>
      </div>
    </div>
    <div class="stat-item p-3 shadow-sm rounded bg-light d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="stat-number fs-4 fw-bold text-dark"><?php echo $pending_sessions; ?></div>
        <div class="stat-label text-muted">Pending</div>
      </div>
      <div class="stat-icon text-warning">
        <i class="fas fa-clock fa-2x"></i>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <!-- Next Session -->
    <div class="col-6">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h4 class="card-title mb-3 fw-bold" style="color: #114470;">Next Session</h4>
          <?php if ($next_session) : ?>

          <div class="d-flex align-items-start pb-2 mb-3">

            <!-- Date Badge -->
            <div class="text-center me-3 mt-4">
              <div class="text-white rounded-top-3 px-3 py-1 fw-bold" style="font-size: 24px; background: #3eaeb2;">
                <?php echo esc_html($next_session['date_time']->format('M')); ?>
              </div>
              <div class="border rounded-bottom px-2 py-1 fw-bold" style="font-size: 24px;">
                <?php echo esc_html($next_session['date_time']->format('d')); ?>
              </div>
            </div>

            <?php
            // Status to Bootstrap color mapping
            $status_colors = [
                'approved'  => 'bg-success text-white',
                'pending'   => 'bg-warning text-dark',
                'cancelled' => 'bg-danger text-white',
                'upcoming'  => 'bg-info text-dark',
                'completed' => 'bg-secondary text-white'
            ];

            // Get the correct badge class
            $status = strtolower($next_session['appointment_status']);
            $badge_class = $status_colors[$status] ?? 'bg-light text-dark';
            ?>

            <!-- Event Details -->
            <div class="flex-grow-1">
              <h5 class="mb-1 fw-bold text-dark"><?php echo esc_html($next_session['product_name']); ?></h5>
              <p class="mb-1 text-muted">For <?php echo esc_html($next_session['child_name']); ?></p>
              <p class="mb-1 text-muted">
                <?php echo esc_html($next_session['date_time']->format('D, d M Y - h:i A')); ?>
              </p>
              <p class="mb-1 text-muted">
                Place: <?php echo esc_html($next_session['location']); ?>
              </p>

              <span class="badge <?php echo $badge_class; ?>">
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

    <!-- Mentees Table -->
    <?php
      // Fetch mentees where the current user is the assigned mentor
      $mentees = get_users(array(
        'meta_key'   => 'assigned_mentor_id',
        'meta_value' => $mentor_id,
        'fields'     => array('ID', 'display_name'),
        'number'     => 4 // Limit to 3 users

      ));

    ?>
    <div class="col-md-6">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h4 class="card-title mb-3 fw-bold" style="color: #114470;">Assigned Mentees</h4>
          <div class="table-responsive">
            <table class="table table-striped">
              
              <tbody>
                <?php if (!empty($mentees)) : ?>
                  <?php foreach ($mentees as $mentee) : ?>
                    <tr>
                      <td><?php echo esc_html(ucfirst($mentee->display_name)); ?></td>
                      <td>
                        <a href="<?php echo esc_url(add_query_arg('child_id', $mentee->ID, site_url('/mentee-appointment-history/'))); ?>" class="btn btn-sm" style="background: #114470;color: #fff;">View</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else : ?>
                  <tr>
                    <td colspan="2" class="text-center text-muted">No mentees assigned.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-6">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h4 class="card-title mb-3 fw-bold" style="color: #114470;">Upcoming Sessions</h4>

          <?php if (!empty($future_sessions)) : ?>
            <?php foreach ($future_sessions as $session) : ?>
              <?php
                $status = strtolower($session['appointment_status']);
                $badge_class = $status_colors[$status] ?? 'bg-light text-dark'; // fallback
              ?>
              <div class="d-flex align-items-start border-bottom pb-3 mb-3">

                <!-- Date Badge -->
                <div class="text-center me-3 mt-4">
                  <div class="text-white rounded-top-3 px-3 py-1 fw-bold" style="font-size: 24px; background: #3eaeb2;">
                    <?php echo esc_html($session['date_time']->format('M')); ?>
                  </div>
                  <div class="border rounded-bottom px-2 py-1 fw-bold" style="font-size: 24px;">
                    <?php echo esc_html($session['date_time']->format('d')); ?>
                  </div>
                </div>

                <!-- Event Details -->
                <div class="flex-grow-1">
                  <h5 class="mb-1 fw-bold text-dark"><?php echo esc_html($session['product_name']); ?></h5>
                  <p class="mb-1 text-muted">For <?php echo esc_html($session['child_name']); ?></p>
                  <p class="mb-1 text-muted">
                    <?php echo esc_html($session['date_time']->format('D, d M Y - h:i A')); ?>
                  </p>
                  <p class="mb-1 text-muted">
                    Place: <?php echo esc_html($session['location']); ?>
                  </p>
                  <span class="badge <?php echo $badge_class; ?>">
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
            <?php endforeach; ?>

          <?php else : ?>
            <p class="text-muted text-center">No additional upcoming sessions scheduled.</p>
          <?php endif; ?>

        </div>
      </div>
    </div>

    <!-- Calendar and Mentees Side by Side -->
    <!-- <div class="row g-4"> -->
      <!-- Session Schedule Calendar -->
      <div class="col-md-6">
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h4 class="card-title mb-0 fw-bold" style="color: #114470;">Session Schedule</h4>
              <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary btn-sm active" id="calendarMonth">Month</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="calendarWeek">Week</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="calendarDay">Day</button>
              </div>
            </div>
            
            <!-- Calendar Legend -->
            <div class="calendar-legend">
              <div class="legend-item">
                <div class="legend-color session-approved"></div>
                <span>Approved</span>
              </div>
              <div class="legend-item">
                <div class="legend-color session-scheduled"></div>
                <span>Scheduled</span>
              </div>
              <div class="legend-item">
                <div class="legend-color session-pending"></div>
                <span>Pending</span>
              </div>
              <div class="legend-item">
                <div class="legend-color session-cancelled"></div>
                <span>Cancelled</span>
              </div>
            </div>
            
            <!-- Calendar Container -->
            <div id="mentorCalendar"></div>
          </div>
        </div>
      </div>


    <!-- </div> -->

    <div class="col-12">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h4 class="card-title mb-3 fw-bold" style="color: #114470;">💬 Chat with Your Child</h4>
          <div class="alert alert-secondary" role="alert">
            <?php echo do_shortcode('[user_chat_channels]'); ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>


<!-- Mentee Details Modal (Single Modal for All Mentees) -->
<div class="modal fade" id="menteeDetailsModal" tabindex="-1" aria-labelledby="menteeDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="menteeDetailsModalLabel">Mentee Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="menteeDetailsContent">
        <!-- Content will be populated dynamically -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Session Details Modal -->
<div class="modal fade" id="sessionDetailsModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Session Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="sessionDetailsContent">
        <!-- Content will be loaded dynamically -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary">Join Session</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>



<?php  get_footer(); ?>