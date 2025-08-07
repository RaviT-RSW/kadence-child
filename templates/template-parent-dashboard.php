<?php
/**
 * Template Name: Parent Dashboard
 */
get_header();
?>

<!-- Parent Dashboard Template with Bootstrap 5 and Child Cards -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">

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
            <div class="card-body">
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

                      $location = $item->get_meta('location') ?: 'online';
                      $zoom_meeting = $item->get_meta('zoom_meeting') ?: '';
                      $zoom_link = '';

                      if($location == 'online' && !empty($zoom_meeting) && class_exists('Zoom'))
                      {
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
              $next_status_class = '';
              if ( $next_session['appointment_status'] === 'approved' ) {
                $next_status_class = 'badge bg-success text-light';
              } elseif ( $next_session['appointment_status'] === 'cancelled' ) {
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
                  <?php 
                      foreach ($future_sessions as $session) :
                        $future_status_class = '';
                          if ( $session['appointment_status'] === 'approved' ) {
                            $future_status_class = 'badge bg-success text-light';
                          } elseif ( $session['appointment_status'] === 'cancelled' ) {
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
                              <i class="fas fa-video me-1"></i>Join Metting
                            </a>
                          <?php else: ?>
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
                    <label for="rescheduleDateTime" class="form-label">Select New Date and Time</label>
                    <input type="text" class="form-control" id="rescheduleDateTime" name="session_date_time" required>
                  </div>
                  <button type="submit" class="btn btn-primary w-100">Save Changes</button>
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
  #rescheduleDateTime {
    padding: 0.5rem;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
  }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mentorHours = <?php echo json_encode($mentor_hours); ?> || {};
    let currentMentorId = null;

    // Initialize Flatpickr for rescheduling
    const rescheduleFp = flatpickr("#rescheduleDateTime", {
        enableTime: true,
        minDate: "today",
        dateFormat: "Y-m-d H:i",
        disable: [],
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length > 0 && currentMentorId) {
                const day = selectedDates[0].toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
                const mentorData = mentorHours[currentMentorId] && mentorHours[currentMentorId][day];
                if (mentorData) {
                    const data = JSON.parse(mentorData || '{}');
                    instance.set("minTime", data.start_time || "00:00");
                    instance.set("maxTime", data.end_time || "23:59");
                } else {
                    instance.set("minTime", "00:00");
                    instance.set("maxTime", "23:59");
                }
            }
        },
        onReady: function(selectedDates, dateStr, instance) {
            if (!currentMentorId) {
                instance.setDate(null);
                disableTimeInputs(true);
            } else {
                const initialDate = instance.input.value ? new Date(instance.input.value) : new Date();
                const day = initialDate.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
                const mentorData = mentorHours[currentMentorId] && mentorHours[currentMentorId][day];
                if (mentorData) {
                    const data = JSON.parse(mentorData || '{}');
                    instance.set("minTime", data.start_time || "00:00");
                    instance.set("maxTime", data.end_time || "23:59");
                }
            }
        }
    });

    // Disable all dates by default in reschedule modal
    const today = new Date();
    const disableAllDates = [];
    let currentDate = new Date(today);
    while (currentDate <= new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)) {
        disableAllDates.push(new Date(currentDate));
        currentDate.setDate(currentDate.getDate() + 1);
    }
    rescheduleFp.set("disable", disableAllDates);

    // Function to disable/enable time inputs in modal
    function disableTimeInputs(disable) {
        const timeContainer = document.querySelector('.flatpickr-time');
        if (timeContainer) {
            const timeInputs = timeContainer.querySelectorAll('input, .flatpickr-am-pm');
            timeInputs.forEach(input => {
                input.disabled = disable;
                input.style.pointerEvents = disable ? 'none' : 'auto';
                input.style.opacity = disable ? '0.5' : '1';
            });
            timeContainer.style.pointerEvents = disable ? 'none' : 'auto';
            timeContainer.style.opacity = disable ? '0.5' : '1';
        }
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

            // Reset and configure Flatpickr based on mentor
            rescheduleFp.setDate(sessionDateTime);
            const disableDates = [];
            if (currentMentorId) {
                const mentorData = mentorHours[currentMentorId] || null;
                if (!mentorData || Object.values(mentorData).every(data => !data || data === null)) {
                    rescheduleFp.set("disable", disableAllDates);
                    disableTimeInputs(true);
                } else {
                    let currentDate = new Date(today);
                    while (currentDate <= new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)) {
                        const dayName = currentDate.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
                        const dayData = mentorData[dayName];
                        if (!dayData || (dayData && !JSON.parse(dayData || '{}').start_time)) {
                            disableDates.push(new Date(currentDate));
                        }
                        currentDate.setDate(currentDate.getDate() + 1);
                    }
                    rescheduleFp.set("disable", disableDates);
                    disableTimeInputs(false);
                }
            } else {
                rescheduleFp.set("disable", disableAllDates);
                disableTimeInputs(true);
            }
            rescheduleFp.redraw(); // Force redraw to ensure UI updates
        });
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

/*    // Handle cancel button
    document.querySelectorAll('.cancel-btn').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            const orderId = this.getAttribute('data-order-id');
            if (confirm('Are you sure you want to cancel this appointment?')) {
                const formData = new FormData();
                formData.append('action', 'cancel_session');
                formData.append('item_id', itemId);
                formData.append('order_id', orderId);
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
                        showNotification('Failed to cancel: ' + data.message, 'danger');
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    showNotification('An error occurred. Please try again.', 'danger');
                });
            }
        });
    });*/

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