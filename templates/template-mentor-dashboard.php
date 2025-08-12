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

<div class="container my-5">
  <!-- Welcome Section -->
  <div class="mb-4">
    <h2 class="mb-1">Welcome, <span class="text-primary"><?php echo wp_get_current_user()->display_name; ?></span></h2>
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
    <div class="stat-item">
      <div class="stat-number"><?php echo $total_sessions; ?></div>
      <div class="stat-label">Total Sessions</div>
    </div>
    <div class="stat-item">
      <div class="stat-number"><?php echo $approved_sessions; ?></div>
      <div class="stat-label">Approved</div>
    </div>
    <div class="stat-item">
      <div class="stat-number"><?php echo $upcoming_sessions; ?></div>
      <div class="stat-label">Upcoming</div>
    </div>
    <div class="stat-item">
      <div class="stat-number"><?php echo $pending_sessions; ?></div>
      <div class="stat-label">Pending</div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <!-- Next Session -->
    <div class="col-12">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h4 class="card-title mb-3 fw-bold text-primary">Next Session</h4>
          <?php if ($next_session) : ?>
            <div class="session-details">
              <div class="row g-3">
                <div class="col-6">
                  <p class="mb-2"><strong>Service:</strong> <span class="text-success fw-medium"><?php echo esc_html($next_session['product_name']); ?></span></p>
                </div>
                <div class="col-6">
                  <p class="mb-2"><strong>Date & Time:</strong> <span class="text-success fw-medium"><?php echo esc_html($next_session['date_time']->format('F d, Y - h:i A')); ?></span></p>
                </div>
                <div class="col-6">
                  <p class="mb-2"><strong>Child:</strong> <span class="text-primary fw-medium"><?php echo esc_html($next_session['child_name']); ?></span></p>
                </div>
                <div class="col-6">
                  <p class="mb-2"><strong>Location:</strong> <span class="text-primary fw-medium"><?php echo esc_html($next_session['location']); ?></span></p>
                </div>
                <div class="col-6">
                  <p class="mb-2"><strong>Status:</strong> <span class="badge bg-info text-dark"><?php echo esc_html($next_session['appointment_status']); ?></span></p>
                </div>
                <div class="col-6">
                  <p class="mb-0"><strong>Order ID:</strong> <span class="text-secondary"><?php echo esc_html($next_session['order_id']); ?></span></p>
                </div>

                <div class="col-6">
                  <div class="btn-group" role="group">
                    <?php if ($next_session['appointment_status'] === 'pending') : ?>
                      
                      <button type="button" class="btn btn-success btn-sm approve-appoinment-btn" data-item-id="<?php echo esc_attr($next_session['item_id']); ?>" data-order-id="<?php echo esc_attr($next_session['order_id']); ?>">
                        Approve
                      </button>

                      <button type="button" class="btn btn-danger btn-sm cancel-btn" data-item-id="<?php echo esc_attr($next_session['item_id']); ?>" data-order-id="<?php echo esc_attr($next_session['order_id']); ?>">
                        Cancel
                      </button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(add_query_arg(array('order_id' => $next_session['order_id'], 'item_id' => $next_session['item_id']), site_url('/appointment-details/'))); ?>" class="btn btn-secondary btn-sm view-btn">View</a>
                  </div>
                </div>

                <div class="col-6">
                  <?php if ($next_session['zoom_link']) : ?>
                    <a href="<?php echo esc_url($next_session['zoom_link']); ?>" class="btn btn-primary me-2" target="_blank">
                      <i class="fas fa-video me-1"></i>Start Session
                    </a>
                  <?php else: ?>
                    <button class="btn btn-secondary me-2" disabled>
                      <i class="fas fa-video me-1"></i>Meeting Link Not Available
                    </button>
                  <?php endif; ?>
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
        <div class="card-body">
          <h4 class="card-title mb-3 fw-bold text-primary">Upcoming Sessions</h4>
          <?php if (!empty($future_sessions)) : ?>
            <div class="session-list">
              <?php foreach ($future_sessions as $session) : ?>
                <div class="session-item mb-3 p-3 border rounded">
                  <div class="row g-3">
                    <div class="col-6">
                      <p class="mb-1"><strong>Service:</strong> <span class="text-success fw-medium"><?php echo esc_html($session['product_name']); ?></span></p>
                    </div>
                    <div class="col-6">
                      <p class="mb-1"><strong>Date & Time:</strong> <span class="text-success fw-medium"><?php echo esc_html($session['date_time']->format('F d, Y - h:i A')); ?></span></p>
                    </div>
                    <div class="col-6">
                      <p class="mb-1"><strong>Child:</strong> <span class="text-primary fw-medium"><?php echo esc_html($session['child_name']); ?></span></p>
                    </div>
                    <div class="col-6">
                      <p class="mb-1"><strong>Location:</strong> <span class="text-primary fw-medium"><?php echo esc_html($session['location']); ?></span></p>
                    </div>
                    <div class="col-6">
                      <p class="mb-0"><strong>Status:</strong> <span class="badge bg-info text-dark"><?php echo esc_html($session['appointment_status']); ?></span></p>
                    </div>
                    <div class="col-6">
                      <p class="mb-0"><strong>Order ID:</strong> <span class="text-secondary"><?php echo esc_html($session['order_id']); ?></span></p>
                    </div>
                    <div class="col-6">
                      <div class="btn-group" role="group">
                        <?php if ($session['appointment_status'] === 'pending') : ?>
                          <button type="button" class="btn btn-success btn-sm approve-appoinment-btn" data-item-id="<?php echo esc_attr($session['item_id']); ?>" data-order-id="<?php echo esc_attr($session['order_id']); ?>">
                            Approve
                          </button>
                          <button type="button" class="btn btn-danger btn-sm cancel-btn" data-item-id="<?php echo esc_attr($session['item_id']); ?>" data-order-id="<?php echo esc_attr($session['order_id']); ?>">
                            Cancel
                          </button>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(add_query_arg(array('order_id' => $session['order_id'], 'item_id' => $session['item_id']), site_url('/appointment-details/'))); ?>" class="btn btn-secondary btn-sm view-btn">View</a>
                      </div>
                    </div>
                    <div class="col-6">
                      <?php if ($session['zoom_link']) : ?>
                        <a href="<?php echo esc_url($session['zoom_link']); ?>" class="btn btn-sm btn-outline-primary mt-2" target="_blank">
                          <i class="fas fa-video me-1"></i>Start Meeting
                        </a>
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

    <!-- Calendar and Mentees Side by Side -->
    <div class="row g-4">
      <!-- Session Schedule Calendar -->
      <div class="col-md-8">
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h4 class="card-title mb-0 fw-bold text-primary">Session Schedule</h4>
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

      <!-- Mentees Table -->
      <?php
        // Fetch mentees where the current user is the assigned mentor
        $mentees = get_users(array(
            'meta_key'   => 'assigned_mentor_id',
            'meta_value' => $mentor_id,
            'fields'     => array('ID', 'display_name'),
        ));

      ?>
      <div class="col-md-4">
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h4 class="card-title mb-3 fw-bold text-primary">Assigned Mentees</h4>
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Mentee Name</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($mentees)) : ?>
                    <?php foreach ($mentees as $mentee) : ?>
                      <tr>
                        <td><?php echo esc_html(ucfirst($mentee->display_name)) . ' (' . esc_html($mentee->ID) . ')'; ?></td>
                        <td>
                          <a href="<?php echo esc_url(add_query_arg('child_id', $mentee->ID, site_url('/appointment-history/'))); ?>" class="btn btn-sm btn-primary">View</a>
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


<?php get_footer(); ?>