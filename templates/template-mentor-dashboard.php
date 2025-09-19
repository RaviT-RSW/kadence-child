<?php
/**
 * Template Name: Mentor Dashboard
 */
get_header();

// Fetch unique mentees from wp_assigned_mentees
global $wpdb;
$current_mentor = wp_get_current_user();
$mentor_id = $current_mentor->ID;

$mentees = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT DISTINCT child_id, (SELECT display_name FROM {$wpdb->users} WHERE ID = child_id) as child_name
         FROM {$wpdb->prefix}assigned_mentees
         WHERE mentor_id = %d",
        $mentor_id
    )
);

// Fetch mentor working hours for reschedule functionality
$hours = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mentor_working_hours WHERE mentor_id = %d", $mentor_id)
);
$mentor_hours = $hours ? [
    'monday' => $hours->monday,
    'tuesday' => $hours->tuesday,
    'wednesday' => $hours->wednesday,
    'thursday' => $hours->thursday,
    'friday' => $hours->friday,
    'saturday' => $hours->saturday,
    'sunday' => $hours->sunday,
] : [];

// Parse mentor hours into objects
foreach ($mentor_hours as $day => $dayData) {
    $mentor_hours[$day] = $dayData ? json_decode($dayData, true) : ['off' => true, 'slots' => []];
}
?>

<div class="container">
  <!-- Welcome Section -->
  <div class="mb-4">
    <h2 class="mb-1">Welcome, <span style="color: #114470;"><?php echo wp_get_current_user()->display_name; ?></span></h2>
    <p class="text-muted">Manage your mentoring sessions, track progress, and connect with your mentees.</p>
  </div>

  <?php
  $sessions = array();
  $all_orders = wc_get_orders(array(
      'limit' => -1,
      'status' => array('wc-processing', 'wc-on-hold', 'wc-completed', 'wc-pending'),
      'meta_query' => array(
          'relation' => 'OR',
          array(
              'key' => 'is_monthly_invoice',
              'value' => '1',
              'compare' => '!=', // Orders where is_monthly_invoice is not 1
          ),
          array(
              'key' => 'is_monthly_invoice',
              'compare' => 'NOT EXISTS', // Orders where is_monthly_invoice is not set
          ),
      ),
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
          <h4 class="card-title mb-2 fw-bold" style="color: #114470;">Next Session</h4>
          <?php if ($next_session) : ?>

          <div class="d-flex align-items-start pb-2">

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

              <span class="badge <?php echo $badge_class; ?> mb-2">
                <?php echo ucfirst($next_session['appointment_status']); ?>
              </span>

              <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                  <div class="btn-group" role="group">
                    <?php if ($next_session['appointment_status'] === 'pending') : ?>
                      <button type="button" class="btn btn-success btn-sm approve-appoinment-btn" data-item-id="<?php echo esc_attr($next_session['item_id']); ?>" data-order-id="<?php echo esc_attr($next_session['order_id']); ?>"> Approve </button>
                      <button type="button" class="btn btn-warning btn-sm reschedule-btn" data-bs-toggle="modal" data-bs-target="#rescheduleModal" data-session-date-time="<?php echo esc_attr($next_session['date_time']->format('Y-m-d H:i')); ?>" data-item-id="<?php echo esc_attr($next_session['item_id']); ?>" data-order-id="<?php echo esc_attr($next_session['order_id']); ?>">
                        Reschedule
                      </button>
                      <button type="button" class="btn btn-danger btn-sm cancel-btn" data-item-id="<?php echo esc_attr($next_session['item_id']); ?>" data-order-id="<?php echo esc_attr($next_session['order_id']); ?>"> Cancel </button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(add_query_arg(array(
                          'order_id' => $next_session['order_id'],
                          'item_id' => $next_session['item_id']
                      ), site_url('/appointment-details/'))); ?>"
                       class="btn btn-secondary btn-sm view-btn">
                      View
                    </a>
                  </div>

                  <?php if ($next_session['zoom_link']) : ?>
                    <a href="<?php echo esc_url($next_session['zoom_link']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                      <i class="fas fa-video me-1"></i>Join Meeting
                    </a>
                  <?php endif; ?>
                </div>
              </div>
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
        'number'     => 3
      ));
    ?>
    <div class="col-md-6">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <h4 class="card-title mb-3 fw-bold" style="color: #114470;">Assigned Mentees</h4>
          <div class="table-responsive">
            <table class="table table-striped align-middle">
              <tbody>
                <?php if (!empty($mentees)) : ?>
                  <?php foreach ($mentees as $mentee) : ?>
                    <tr>
                      <td class="d-flex align-items-center">
                        <div class="rounded-circle me-2" style="width: 40px; height: 40px; overflow: hidden; display: inline-block;">
                          <?php echo get_avatar($mentee->ID, 40); ?>
                        </div>
                        <span><?php echo esc_html(ucfirst($mentee->display_name)); ?></span>
                      </td>
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
                  <span class="badge <?php echo $badge_class; ?> mb-3">
                    <?php echo ucfirst($session['appointment_status']); ?>
                  </span>

                  <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                      <div class="btn-group" role="group">
                        <?php if ($session['appointment_status'] === 'pending') : ?>
                          <button type="button" class="btn btn-success btn-sm approve-appoinment-btn" data-item-id="<?php echo esc_attr($session['item_id']); ?>" data-order-id="<?php echo esc_attr($session['order_id']); ?>"> Approve </button>
                          <button type="button" class="btn btn-warning btn-sm reschedule-btn" data-bs-toggle="modal" data-bs-target="#rescheduleModal" data-session-date-time="<?php echo esc_attr($session['date_time']->format('Y-m-d H:i')); ?>" data-item-id="<?php echo esc_attr($session['item_id']); ?>" data-order-id="<?php echo esc_attr($session['order_id']); ?>">
                            Reschedule
                          </button>
                          <button type="button" class="btn btn-danger btn-sm cancel-btn" data-item-id="<?php echo esc_attr($session['item_id']); ?>" data-order-id="<?php echo esc_attr($session['order_id']); ?>"> Cancel </button>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(add_query_arg(array(
                              'order_id' => $session['order_id'],
                              'item_id' => $session['item_id']
                          ), site_url('/appointment-details/'))); ?>"
                           class="btn btn-secondary btn-sm view-btn">
                          View
                        </a>
                      </div>

                      <?php if ($session['zoom_link']) : ?>
                        <a href="<?php echo esc_url($session['zoom_link']); ?>"
                           class="btn btn-sm btn-outline-primary" target="_blank">
                          <i class="fas fa-video me-1"></i>Join Meeting
                        </a>
                      <?php endif; ?>

                    </div>
                  </div>
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

<!-- Reschedule Modal -->
<div class="modal fade" id="rescheduleModal" tabindex="-1" aria-labelledby="rescheduleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="rescheduleModalLabel">Reschedule Appointment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="rescheduleForm">
          <input type="hidden" id="rescheduleItemId" name="item_id">
          <input type="hidden" id="rescheduleOrderId" name="order_id">
          <div class="mb-3">
            <div class="calendar-container">
              <div class="calendar-header">
                <select id="rescheduleMonthSelect"></select>
                <select id="rescheduleYearSelect"></select>
              </div>
              <div class="calendar-grid" id="rescheduleCalendarDays">
                <div class="day">Mon</div>
                <div class="day">Tue</div>
                <div class="day">Wed</div>
                <div class="day">Thu</div>
                <div class="day">Fri</div>
                <div class="day">Sat</div>
                <div class="day">Sun</div>
              </div>
              <div id="rescheduleSelectedDateText" style="margin-bottom:10px;font-weight:bold;"></div>
              <div class="time-slots" id="rescheduleTimeSlots"></div>
            </div>
            <input type="hidden" id="rescheduleSelectedSlot" name="session_date_time" required>
          </div>
          <button type="submit" class="btn btn-primary w-100" disabled>Save Changes</button>
        </form>
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

<style>
  /* Calendar Styling */
  .calendar-container {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    max-width: 600px;
    width: 100%;
  }
  .calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
  }
  .calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-bottom: 20px;
  }
  .day {
    text-align: center;
    padding: 8px 0;
    font-weight: bold;
    color: #666;
  }
  .date {
    text-align: center;
    padding: 8px 0;
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid transparent;
  }
  .date:hover {
    background: #f0e6dd;
  }
  .date.inactive {
    color: #ccc;
    pointer-events: none;
  }
  .date.selected {
    background: #3eaeb2;
    color: white;
  }
  .date.today {
    border: 1px solid #3eaeb2;
  }
  .time-slots {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
  }
  .time-slot {
    text-align: center;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    background: #fff;
    font-size: 14px;
  }
  .time-slot:hover {
    background: #f0e6dd;
  }
  .time-slot.inactive {
    color: #ccc;
    background: #f8f9fa;
    pointer-events: none;
  }
  .time-slot.selected {
    background: #3eaeb2;
    color: white;
  }

  /* Button styling */
  .btn-group .btn {
    margin-right: 0.25rem;
    padding: 0.25rem 0.75rem;
    font-size: 0.875rem;
  }
  .btn-group .btn:last-child {
    margin-right: 0;
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mentorHours = <?php echo json_encode($mentor_hours); ?> || {};
    let bookedSlots = {};

    const months = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();
    let selectedDate = null;
    const now = new Date();
    const mentorId = <?php echo $mentor_id; ?>;

    // Populate month and year dropdowns in modal
    function populateMonthYear() {
        const monthSelect = document.getElementById("rescheduleMonthSelect");
        const yearSelect = document.getElementById("rescheduleYearSelect");
        monthSelect.innerHTML = months.map((m, i) => `<option value="${i}" ${i === currentMonth ? 'selected' : ''}>${m}</option>`).join("");
        let years = "";
        for (let y = currentYear - 5; y <= currentYear + 5; y++) {
            years += `<option value="${y}" ${y === currentYear ? 'selected' : ''}>${y}</option>`;
        }
        yearSelect.innerHTML = years;
    }

    // Get max slots based on multiple time ranges
    function getMaxSlots(dayData) {
        if (!dayData || !dayData.slots || !Array.isArray(dayData.slots) || dayData.slots.length === 0) return 0;
        let totalHours = 0;
        dayData.slots.forEach(slot => {
            if (slot.start_time && slot.end_time) {
                const start = new Date(`2000-01-01 ${slot.start_time}`);
                const end = new Date(`2000-01-01 ${slot.end_time}`);
                const diffMs = end - start;
                totalHours += diffMs / (1000 * 60 * 60);
            }
        });
        return Math.floor(totalHours);
    }

    // Render calendar with disabled dates
    function renderCalendar() {
        const calendarDays = document.getElementById("rescheduleCalendarDays");
        calendarDays.querySelectorAll(".date").forEach(el => el.remove());
        const firstDay = new Date(currentYear, currentMonth, 1);
        const startDay = (firstDay.getDay() + 6) % 7; // Make Monday start
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const prevMonthDays = new Date(currentYear, currentMonth, 0).getDate();

        // Prev month dates (always inactive)
        for (let i = startDay; i > 0; i--) {
            const date = document.createElement("div");
            date.className = "date inactive";
            date.textContent = prevMonthDays - i + 1;
            calendarDays.appendChild(date);
        }

        // Current month dates
        for (let i = 1; i <= daysInMonth; i++) {
            const date = document.createElement("div");
            const fullDate = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            const currentDate = new Date(currentYear, currentMonth, i);
            date.className = "date";
            date.textContent = i;

            // Check if the date is in the past or today past current time
            const isPastDate = currentDate < now.setHours(0, 0, 0, 0) || 
                              (currentDate.toDateString() === now.toDateString() && new Date(fullDate) < now);

            // Highlight today
            if (i === now.getDate() && currentMonth === now.getMonth() && currentYear === now.getFullYear()) {
                date.classList.add("today");
            }

            // Disable past dates or if no mentor availability or fully booked
            if (isPastDate) {
                date.classList.add("inactive");
            } else {
                const dayName = currentDate.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
                const dayData = mentorHours[dayName];
                const isDayOff = dayData && dayData.off;
                const slots = dayData && dayData.slots ? dayData.slots : [];
                if (isDayOff || slots.length === 0 || (bookedSlots[fullDate] && bookedSlots[fullDate].length >= getMaxSlots(dayData))) {
                    date.classList.add("inactive");
                }
            }

            // Only add click event if the date is not inactive
            if (!date.classList.contains("inactive")) {
                date.addEventListener("click", () => selectDate(i, fullDate));
            }
            calendarDays.appendChild(date);
        }

        // Next month dates (always inactive)
        const totalCells = startDay + daysInMonth;
        const nextDays = (7 - (totalCells % 7)) % 7;
        for (let i = 1; i <= nextDays; i++) {
            const date = document.createElement("div");
            date.className = "date inactive";
            date.textContent = i;
            calendarDays.appendChild(date);
        }
    }

    // Select date and render time slots
    function selectDate(day, fullDate) {
        const calendarDays = document.getElementById("rescheduleCalendarDays");
        document.querySelectorAll(".date").forEach(d => d.classList.remove("selected"));
        const dates = calendarDays.querySelectorAll(".date");
        dates.forEach(el => {
            if (el.textContent == day && !el.classList.contains("inactive")) {
                el.classList.add("selected");
            }
        });
        selectedDate = new Date(currentYear, currentMonth, day);
        document.getElementById("rescheduleSelectedDateText").textContent = selectedDate.toDateString();
        renderTimeSlots(fullDate);
    }

    // Render time slots with correct booked slot disabling
    function renderTimeSlots(fullDate) {
        const timeSlotsContainer = document.getElementById("rescheduleTimeSlots");
        const submitButton = document.querySelector('#rescheduleForm button[type="submit"]');
        timeSlotsContainer.innerHTML = "";
        if (!selectedDate || !mentorHours) {
            submitButton.disabled = true;
            return;
        }

        const dayName = selectedDate.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
        const dayData = mentorHours[dayName];
        if (!dayData || dayData.off || !dayData.slots || dayData.slots.length === 0) {
            submitButton.disabled = true;
            return;
        }

        const bookedTimes = bookedSlots[fullDate] || [];
        const currentDateTime = new Date(fullDate);

        dayData.slots.forEach(slot => {
            if (!slot.start_time || !slot.end_time) return;
            let currentTime = new Date(`2000-01-01 ${slot.start_time}`);
            const endTime = new Date(`2000-01-01 ${slot.end_time}`);

            while (currentTime < endTime) {
                const slotStart = currentTime.toTimeString().slice(0, 5);
                const slotEndTime = new Date(currentTime.getTime() + 60 * 60 * 1000);
                const slotEnd = slotEndTime.toTimeString().slice(0, 5);

                if (slotEndTime > endTime) break;

                const slotTime = `${slotStart} - ${slotEnd}`;
                const slotStartFull = new Date(`${fullDate}T${slotStart}:00`);
                const slotEndFull = new Date(slotStartFull.getTime() + 60 * 60 * 1000);

                // Check if slot is in the past for today
                const isPast = currentDateTime.toDateString() === now.toDateString() && slotStartFull < now;

                // Check if the slot is booked
                const isBooked = bookedTimes.some(bookedTime => {
                    const bookedStartFull = new Date(`${fullDate}T${bookedTime}:00`);
                    const bookedEndFull = new Date(bookedStartFull.getTime() + 60 * 60 * 1000);
                    return slotStartFull < bookedEndFull && slotEndFull > bookedStartFull;
                });

                const slot = document.createElement("div");
                slot.className = "time-slot";
                slot.textContent = slotTime;
                if (isPast || isBooked) {
                    slot.classList.add("inactive");
                } else {
                    slot.addEventListener("click", () => {
                        document.querySelectorAll(".time-slot").forEach(ts => ts.classList.remove("selected"));
                        slot.classList.add("selected");
                        document.getElementById("rescheduleSelectedSlot").value = `${fullDate} ${slotStart}:00`;
                        submitButton.disabled = false;
                    });
                }
                timeSlotsContainer.appendChild(slot);

                currentTime = new Date(currentTime.getTime() + 60 * 60 * 1000);
            }
        });
    }

    // Fetch booked slots for the month
    function fetchBookedSlots(mentorId, year, month) {
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'get_mentor_booked_slots',
                mentor_id: mentorId,
                year: year,
                month: month,
                nonce: '<?php echo wp_create_nonce('mentor_dashboard_nonce'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bookedSlots = data.data.booked_slots || {};
                renderCalendar();
                if (selectedDate) {
                    const fullDate = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(selectedDate.getDate()).padStart(2, '0')}`;
                    renderTimeSlots(fullDate);
                }
            } else {
                bookedSlots = {};
                renderCalendar();
                showNotification('Failed to load booked slots: ' + (data.data?.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            bookedSlots = {};
            renderCalendar();
            showNotification('Failed to load booked slots: ' + error.message, 'danger');
        });
    }

    // Handle reschedule modal show
    document.querySelectorAll('.reschedule-btn').forEach(button => {
        button.addEventListener('click', function() {
            const sessionDateTime = this.getAttribute('data-session-date-time');
            const itemId = this.getAttribute('data-item-id');
            const orderId = this.getAttribute('data-order-id');

            document.getElementById('rescheduleItemId').value = itemId;
            document.getElementById('rescheduleOrderId').value = orderId;

            // Reset and configure calendar
            selectedDate = null;
            document.getElementById("rescheduleSelectedDateText").textContent = '';
            document.getElementById("rescheduleSelectedSlot").value = '';
            document.querySelector('#rescheduleForm button[type="submit"]').disabled = true;

            populateMonthYear();
            fetchBookedSlots(mentorId, currentYear, currentMonth + 1);
        });
    });

    // Handle month/year changes
    document.getElementById("rescheduleMonthSelect").addEventListener("change", () => {
        currentMonth = parseInt(document.getElementById("rescheduleMonthSelect").value);
        fetchBookedSlots(mentorId, currentYear, currentMonth + 1);
    });

    document.getElementById("rescheduleYearSelect").addEventListener("change", () => {
        currentYear = parseInt(document.getElementById("rescheduleYearSelect").value);
        fetchBookedSlots(mentorId, currentYear, currentMonth + 1);
    });

    // Handle reschedule form submission
    document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'reschedule_session');
        formData.append('nonce', '<?php echo wp_create_nonce('mentor_dashboard_nonce'); ?>');

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showNotification('Failed to reschedule: ' + (data.data?.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            showNotification('An error occurred. Please try again.', 'danger');
        });
    });

    // Notification function
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 5000);
    }
});
</script>

<?php get_footer(); ?>