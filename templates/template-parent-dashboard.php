<?php
/**
 * Template Name: Parent Dashboard
 */
get_header();
?>


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

    // Fetch mentor working hours
    global $wpdb;
    $mentors = get_users(array('role__in' => array('mentor_user')));
    $mentor_hours = [];
    foreach ($mentors as $mentor) {
        $mentor_id = $mentor->ID;
        $hours = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}mentor_working_hours WHERE mentor_id = %d", $mentor_id));
        $mentor_hours[$mentor_id] = $hours ? [
            'monday' => $hours->monday,
            'tuesday' => $hours->tuesday,
            'wednesday' => $hours->wednesday,
            'thursday' => $hours->thursday,
            'friday' => $hours->friday,
            'saturday' => $hours->saturday,
            'sunday' => $hours->sunday,
        ] : [];
    }

    if (!empty($children)) :
        foreach ($children as $child) :
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
            <div class="card-body">
              <h4 class="card-title mb-3 fw-bold text-primary">Next Session</h4>
              <?php
              $parent_id = $current_user->ID;
              $args = array('customer_id' => $parent_id);
              $orders = wc_get_orders($args);
              $sessions = array();
              foreach ($orders as $order) {
                  foreach ($order->get_items() as $item_id => $item) {
                      $mentor_id = $item->get_meta('mentor_id');
                      $child_id = $item->get_meta('child_id');
                      $session_date_time = $item->get_meta('session_date_time');
                      $appointment_status = $item->get_meta('appointment_status') ?: 'N/A';
                      $location = $item->get_meta('location') ?: 'online';
                      $zoom_meeting = $item->get_meta('zoom_meeting') ?: '';
                      $zoom_link = '';
                      if ($location == 'online' && !empty($zoom_meeting) && class_exists('Zoom')) {
                          $zoom = new Zoom();
                          $zoom_link = $zoom->getMeetingUrl($zoom_meeting, 'start_url');
                      }
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
                              'item_id' => $item_id,
                              'zoom_link' => $zoom_link,
                          );
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
              $next_status_class = '';
              if ($next_session['appointment_status'] === 'approved') {
                $next_status_class = 'badge bg-success text-light';
              } elseif ($next_session['appointment_status'] === 'cancelled') {
                $next_status_class = 'badge bg-danger text-light';
              } else {
                $next_status_class = 'badge bg-info text-light';
              }
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
                      <p class="mb-2"><strong>Status:</strong> <span class="<?php echo esc_attr($next_status_class); ?>"><?php echo esc_html(ucfirst($next_session['appointment_status'])); ?></span></p>
                    </div>
                    <div class="col-6">
                      <p class="mb-0"><strong>Order ID:</strong> <span class="text-secondary"><?php echo esc_html($next_session['order_id']); ?></span></p>
                    </div>
                  </div>
                  <div class="row mt-3">
                    <div class="col-12">
                      <div class="btn-group" role="group">
                        <?php if ($next_session['appointment_status'] === 'pending') : ?>
                          <button type="button" class="btn btn-warning btn-sm reschedule-btn" data-bs-toggle="modal" data-bs-target="#rescheduleModal" data-mentor-id="<?php echo esc_attr($next_session['mentor_id']); ?>" data-session-date-time="<?php echo esc_attr($next_session['date_time']->format('Y-m-d H:i')); ?>" data-item-id="<?php echo esc_attr($next_session['item_id']); ?>" data-order-id="<?php echo esc_attr($next_session['order_id']); ?>">
                            Reschedule Appointment
                          </button>
                          <button type="button" class="btn btn-danger btn-sm cancel-btn" data-item-id="<?php echo esc_attr($next_session['item_id']); ?>" data-order-id="<?php echo esc_attr($next_session['order_id']); ?>">
                            Cancel Event
                          </button>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(add_query_arg(array('order_id' => $next_session['order_id'], 'item_id' => $next_session['item_id']), site_url('/appointment-details/'))); ?>" class="btn btn-secondary btn-sm view-btn">View</a>
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

        <!-- Upcoming Sessions -->
        <div class="col-12">
          <div class="card shadow-sm mb-4">
            <div class="card-body">
              <h4 class="card-title mb-3 fw-bold text-primary">Upcoming Sessions</h4>
              <?php if (!empty($future_sessions)) : ?>
                <div class="session-list">
                  <?php foreach ($future_sessions as $session) :
                    $future_status_class = '';
                    if ($session['appointment_status'] === 'approved') {
                      $future_status_class = 'badge bg-success text-light';
                    } elseif ($session['appointment_status'] === 'cancelled') {
                      $future_status_class = 'badge bg-danger text-light';
                    } else {
                      $future_status_class = 'badge bg-info text-light';
                    }
                    ?>
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
                          <p class="mb-0"><strong>Status:</strong> <span class="<?php echo esc_attr($future_status_class); ?>"><?php echo esc_html(ucfirst($session['appointment_status'])); ?></span></p>
                        </div>
                        <div class="col-6">
                          <p class="mb-0"><strong>Order ID:</strong> <span class="text-secondary"><?php echo esc_html($session['order_id']); ?></span></p>
                        </div>
                        <div class="col-6">
                          <div class="btn-group" role="group">
                            <?php if ($session['appointment_status'] === 'pending') : ?>
                              <button type="button" class="btn btn-warning btn-sm reschedule-btn" data-bs-toggle="modal" data-bs-target="#rescheduleModal" data-mentor-id="<?php echo esc_attr($session['mentor_id']); ?>" data-session-date-time="<?php echo esc_attr($session['date_time']->format('Y-m-d H:i')); ?>" data-item-id="<?php echo esc_attr($session['item_id']); ?>" data-order-id="<?php echo esc_attr($session['order_id']); ?>">
                                Reschedule Appointment
                              </button>
                              <button type="button" class="btn btn-danger btn-sm cancel-btn" data-item-id="<?php echo esc_attr($session['item_id']); ?>" data-order-id="<?php echo esc_attr($session['order_id']); ?>">
                                Cancel Event
                              </button>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(add_query_arg(array('order_id' => $session['order_id'], 'item_id' => $session['item_id']), site_url('/appointment-details/'))); ?>" class="btn btn-secondary btn-sm view-btn">View</a>
                          </div>
                        </div>
                        <div class="col-6">
                          <?php if ($session['zoom_link']) : ?>
                            <a href="<?php echo esc_url($session['zoom_link']); ?>" class="btn btn-sm btn-outline-primary mt-2" target="_blank">
                              <i class="fas fa-video me-1"></i>Join Meeting
                            </a>
                          <?php else : ?>
                            <button class="btn btn-secondary me-2" disabled>
                              <i class="fas fa-video me-1"></i>Meeting Link Not Available
                            </button>
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

        <!-- Mentors List -->
        <div class="col-12">
          <div class="card shadow-sm mb-4">
            <div class="card-body">
              <h4 class="card-title mb-3 fw-bold text-primary">Mentors</h4>
              <?php if (!empty($children)) : ?>
                <div class="mentor-list">
                  <?php foreach ($children as $child) :
                    $child_id = $child->ID;
                    $mentor_id = get_user_meta($child_id, 'assigned_mentor_id', true);
                    ?>
                    <div class="child-section mb-4">
                      <h5 class="mb-3"><?php echo esc_html($child->display_name); ?> (ID: <?php echo esc_html($child_id); ?>)</h5>
                      <?php if ($mentor_id) :
                        $mentor = get_user_by('id', $mentor_id);
                        if ($mentor) :
                          $mentor_phone = get_user_meta($mentor_id, 'phone', true);
                          ?>
                          <div class="mentor-item mb-3 p-3 border rounded">
                            <div class="row g-3 align-items-center">
                              <div class="col-4">
                                <p class="mb-1"><strong>Mentor:</strong> <span class="text-primary fw-medium"><?php echo esc_html($mentor->display_name); ?> (ID: <?php echo esc_html($mentor_id); ?>)</span></p>
                              </div>
                              <div class="col-4">
                                <p class="mb-1"><strong>Email:</strong> <span class="text-secondary"><?php echo esc_html($mentor->user_email); ?></span></p>
                              </div>
                              <div class="col-4">
                                <?php if ($mentor_phone) : ?>
                                  <a href="https://wa.me/<?php echo esc_attr($mentor_phone); ?>" class="btn btn-sm btn-success" target="_blank">
                                    <i class="fab fa-whatsapp me-1"></i>Contact via WhatsApp
                                  </a>
                                <?php else : ?>
                                  <button class="btn btn-sm btn-secondary" disabled>
                                    <i class="fab fa-whatsapp me-1"></i>No Phone Number
                                  </button>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>
                        <?php else : ?>
                          <p class="text-muted">Mentor not found (ID: <?php echo esc_html($mentor_id); ?>)</p>
                        <?php endif; ?>
                      <?php else : ?>
                        <p class="text-muted">No mentor assigned to <?php echo esc_html($child->display_name); ?>.</p>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else : ?>
                <p class="text-muted text-center">No children assigned to your account.</p>
              <?php endif; ?>
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

  /* Button Group Styling */
  .btn-group .btn {
    margin-right: 0.5rem;
    padding: 0.25rem 1rem;
    font-size: 0.875rem;
    transition: all 0.2s ease;
  }
  .btn-group .btn:last-child {
    margin-right: 0;
  }
  .btn-warning {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #000;
  }
  .btn-warning:hover {
    background-color: #e0a800;
    border-color: #e0a800;
    color: #000;
  }
  .btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
  }
  .btn-danger:hover {
    background-color: #c82333;
    border-color: #c82333;
  }
  .btn-secondary {
    background-color: #6c757d;
    border-color: #6c757d;
  }
  .btn-secondary:hover {
    background-color: #5a6268;
    border-color: #5a6268;
  }

  /* Modal Styling */
  .modal-content {
    border-radius: 0.5rem;
  }

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
  select {
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    cursor: pointer;
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
    pointer-events: none;
  }
  .time-slot.selected {
    background: #3eaeb2;
    color: white;
  }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mentorHours = <?php echo json_encode($mentor_hours); ?> || {};
    let currentMentorId = null;
    let bookedSlots = {};

    const months = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];

    let currentMonth = new Date().getMonth(); // August (7) on 2025-08-18
    let currentYear = new Date().getFullYear(); // 2025
    let selectedDate = null;

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

    // Render calendar with disabled dates
    function renderCalendar() {
        const calendarDays = document.getElementById("rescheduleCalendarDays");
        calendarDays.querySelectorAll(".date").forEach(el => el.remove());
        const firstDay = new Date(currentYear, currentMonth, 1);
        const startDay = (firstDay.getDay() + 6) % 7; // Make Monday start
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const prevMonthDays = new Date(currentYear, currentMonth, 0).getDate();
        const today = new Date();
        today.setHours(0, 0, 0, 0);

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

            // Check if the date is in the past
            const isPastDate = currentDate < today;

            // Highlight today
            if (i === today.getDate() && currentMonth === today.getMonth() && currentYear === today.getFullYear()) {
                date.classList.add("today");
            }

            // Disable past dates or if no mentor availability or fully booked
            if (isPastDate || !currentMentorId) {
                date.classList.add("inactive");
            } else {
                const dayName = new Date(currentYear, currentMonth, i).toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
                const dayData = mentorHours[currentMentorId] && mentorHours[currentMentorId][dayName];
                if (!dayData || (dayData === null) || (bookedSlots[fullDate] && bookedSlots[fullDate].length >= getMaxSlots(dayData))) {
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

    // Get max slots based on working hours
    function getMaxSlots(dayData) {
        if (!dayData) return 0;
        const { start_time, end_time } = JSON.parse(dayData || '{}');
        if (!start_time || !end_time) return 0;
        const start = new Date(`2000-01-01 ${start_time}`);
        const end = new Date(`2000-01-01 ${end_time}`);
        const diffMs = end - start;
        const diffHrs = diffMs / (1000 * 60 * 60);
        return Math.floor(diffHrs);
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
        if (!selectedDate || !currentMentorId || !mentorHours[currentMentorId]) {
            submitButton.disabled = true;
            return;
        }

        const dayName = selectedDate.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
        const dayData = mentorHours[currentMentorId][dayName];
        if (!dayData || dayData === null) {
            submitButton.disabled = true;
            return;
        }

        const { start_time, end_time } = JSON.parse(dayData);
        let currentTime = new Date(`2000-01-01 ${start_time}`);
        const endTime = new Date(`2000-01-01 ${end_time}`);
        const bookedTimes = bookedSlots[fullDate] || [];

        while (currentTime < endTime) {
            const slotStart = currentTime.toTimeString().slice(0, 5);
            const slotEndTime = new Date(currentTime.getTime() + 60 * 60 * 1000);
            const slotEnd = slotEndTime.toTimeString().slice(0, 5);

            if (slotEndTime > endTime) break;

            const slotTime = `${slotStart} - ${slotEnd}`;
            const slotStartFull = new Date(`${fullDate}T${slotStart}:00`);
            const slotEndFull = new Date(slotStartFull.getTime() + 60 * 60 * 1000);

            const isBooked = bookedTimes.some(bookedTime => {
                const bookedStartFull = new Date(`${fullDate}T${bookedTime}:00`);
                const bookedEndFull = new Date(bookedStartFull.getTime() + 60 * 60 * 1000);
                return slotStartFull < bookedEndFull && slotEndFull > bookedStartFull;
            });

            const slot = document.createElement("div");
            slot.className = "time-slot";
            slot.textContent = slotTime;
            if (isBooked) {
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

            currentTime = new Date(currentTime.getTime() + 60 * 60000);
        }
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
            currentMentorId = this.getAttribute('data-mentor-id');
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
            if (currentMentorId) {
                fetchBookedSlots(currentMentorId, currentYear, currentMonth + 1);
            } else {
                document.getElementById("rescheduleCalendarDays").innerHTML = '<div class="day">Mon</div><div class="day">Tue</div><div class="day">Wed</div><div class="day">Thu</div><div class="day">Fri</div><div class="day">Sat</div><div class="day">Sun</div>';
            }
        });
    });

    // Handle month/year changes
    document.getElementById("rescheduleMonthSelect").addEventListener("change", () => {
        currentMonth = parseInt(document.getElementById("rescheduleMonthSelect").value);
        if (currentMentorId) {
            fetchBookedSlots(currentMentorId, currentYear, currentMonth + 1);
        } else {
            renderCalendar();
        }
    });

    document.getElementById("rescheduleYearSelect").addEventListener("change", () => {
        currentYear = parseInt(document.getElementById("rescheduleYearSelect").value);
        if (currentMentorId) {
            fetchBookedSlots(currentMentorId, currentYear, currentMonth + 1);
        } else {
            renderCalendar();
        }
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
                showNotification('Failed to reschedule: ' + data.message, 'danger');
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