<?php
/**
 * Template Name: Book Session
 */
get_header();
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">

<section class="entry-hero page-hero-section entry-hero-layout-standard">
  <div class="entry-hero-container-inner">
    <div class="hero-section-overlay"></div>
    <div class="hero-container site-container">
      <header class="entry-header page-title title-align-inherit title-tablet-align-inherit title-mobile-align-inherit">
        <h1 class="entry-title">Book Session</h1>
      </header>
    </div>
  </div>
</section>

<div class="container-fluid my-5">
  <?php
  $current_user = wp_get_current_user();
  if (!is_user_logged_in() || !in_array('parent_user', (array)$current_user->roles)) :
  ?>
    <div class="container my-5">
      <div class="row justify-content-center">
        <div class="col-md-8 text-center">
          <div class="card shadow border-0">
            <div class="card-body py-5">
              <div class="mb-4">
                <i class="bi bi-shield-lock-fill text-danger" style="font-size: 4rem;"></i>
              </div>
              <h2 class="text-danger mb-3">Access Denied</h2>
              <p class="text-muted mb-4">You do not have permission to view this page.<br>Please contact support or go back to your dashboard.</p>
              <a href="<?php echo home_url('/'); ?>" class="btn btn-primary me-2">Go to Home</a>
              <?php if (is_user_logged_in()) : ?>
                <a href="<?php echo home_url('/wp-login.php?action=logout'); ?>" class="btn btn-outline-danger">Logout</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
    get_footer();
    exit;
  endif;

  global $wpdb;
  $parent_id = $current_user->ID;

  // Fetch WooCommerce products (sessions)
  $args = array(
      'post_type' => 'product',
      'posts_per_page' => -1,
      'tax_query' => array(
          array(
              'taxonomy' => 'product_type',
              'field'    => 'slug',
              'terms'    => 'simple',
          ),
      ),
  );
  $products = get_posts($args);

  // Fetch mentors with 'mentor_user' role
  $mentors = get_users(array('role__in' => array('mentor_user')));

  // Fetch children of current parent
  $children = get_users(array(
      'role'    => 'child_user',
      'meta_key'   => 'assigned_parent_id',
      'meta_value' => $parent_id,
  ));

  // Fetch mentor working hours
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
  ?>

  <div class="row mb-4">
    <div class="col-12">
      <h2 class="mb-1">Book a Session for Your Child</h2>
      <p class="text-muted">Select a session, mentor, date/time, and child to book.</p>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12">
      <form id="bookSessionForm" method="post">
        <div class="mb-3">
          <label for="sessionProduct" class="form-label">Select Session</label>
          <select class="form-select" id="sessionProduct" name="sessionProduct" required>
            <option value="">Select a session</option>
            <?php foreach ($products as $product) : ?>
              <option value="<?php echo esc_attr($product->ID); ?>"><?php echo esc_html($product->post_title); ?> - $<?php echo get_post_meta($product->ID, '_price', true); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="mentorSelect" class="form-label">Select Mentor</label>
          <select class="form-select" id="mentorSelect" name="mentorSelect" required>
            <option value="">Select a mentor</option>
            <?php foreach ($mentors as $mentor) : ?>
              <option value="<?php echo esc_attr($mentor->ID); ?>"><?php echo esc_html($mentor->display_name); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="sessionDateTime" class="form-label">Select Date and Time</label>
          <input type="text" class="form-control" id="sessionDateTime" name="sessionDateTime" placeholder="Select date and time" required>
        </div>

        <div class="mb-3">
          <label for="childSelect" class="form-label">Select Child</label>
          <select class="form-select" id="childSelect" name="childSelect" required>
            <option value="">Select a child</option>
            <?php foreach ($children as $child) : ?>
              <option value="<?php echo esc_attr($child->ID); ?>"><?php echo esc_html($child->display_name); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-primary">Book Session</button>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mentorHours = <?php echo json_encode($mentor_hours); ?>;
    let currentMentorId = null;

    // Initialize Flatpickr with dynamic disabling
    const fp = flatpickr("#sessionDateTime", {
        enableTime: true,
        minDate: "today",
        dateFormat: "Y-m-d H:i",
        minTime: "00:00",
        maxTime: "23:59",
        disable: [],
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length > 0 && currentMentorId) {
                const day = selectedDates[0].toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
                const mentorData = mentorHours[currentMentorId] && mentorHours[currentMentorId][day];
                if (mentorData) {
                    const data = JSON.parse(mentorData);
                    instance.set("minTime", data.start_time || "00:00");
                    instance.set("maxTime", data.end_time || "23:59");
                } else {
                    instance.set("minTime", "00:00");
                    instance.set("maxTime", "23:59");
                }
            }
        }
    });

    // Update disable dates and times when mentor changes
    document.getElementById('mentorSelect').addEventListener('change', function() {
        currentMentorId = this.value;
        if (currentMentorId) {
            const disableDates = [];
            const today = new Date();
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            const mentorData = mentorHours[currentMentorId] || {};
            console.log('Mentor Data:', mentorData);

            // Start disabling from today
            let currentDate = new Date(today);
            while (currentDate <= new Date(Date.now() + 30 * 24 * 60 * 60 * 1000)) { // 30 days lookahead
                const dayName = currentDate.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
                const dayData = mentorData[dayName];

                // Disable if dayData is null or doesn't contain a valid start_time
                if (dayData === null || (dayData && !JSON.parse(dayData).start_time)) {
                    disableDates.push(new Date(currentDate));
                }

                currentDate.setDate(currentDate.getDate() + 1);
            }

            fp.set("disable", disableDates);
        } else {
            fp.set("disable", []);
        }
    });

    // Handle form submission
    document.getElementById('bookSessionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'add_session_to_cart');
        formData.append('nonce', '<?php echo wp_create_nonce('mentor_dashboard_nonce'); ?>');

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('AJAX Response:', data); // Debug the response
            if (data.success) {
                // Redirect to checkout page
                window.location.href = '<?php echo wc_get_checkout_url(); ?>';
            } else {
                showNotification('Failed to book session: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            showNotification('An error occurred. Please try again.', 'danger');
        });
    });

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 5000);
    }
});
</script>

<style>
.accordion-button {
    font-weight: 500;
}
.accordion-item {
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 10px;
}
.accordion-body {
    padding: 1.5rem;
}
#workingHoursAccordion .row {
    align-items: center;
}
</style>

<?php get_footer(); ?>