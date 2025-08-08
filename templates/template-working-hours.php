<?php
/**
 * Template Name: Working Hours
 */
get_header();
?>
<div class="container-fluid my-5">
  <?php
  $current_user = wp_get_current_user();
  if (!is_user_logged_in() || !in_array('mentor_user', (array)$current_user->roles)) :
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
  $mentor_id = $current_user->ID;
  $table_name = $wpdb->prefix . 'mentor_working_hours';
  $working_hours = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE mentor_id = %d", $mentor_id));
  $hours_data = [];
  if ($working_hours) {
      $hours_data = [
          'Monday' => $working_hours->monday,
          'Tuesday' => $working_hours->tuesday,
          'Wednesday' => $working_hours->wednesday,
          'Thursday' => $working_hours->thursday,
          'Friday' => $working_hours->friday,
          'Saturday' => $working_hours->saturday,
          'Sunday' => $working_hours->sunday,
      ];
  }
  ?>

  <div class="row mb-4">
    <div class="col-12">
      <h2 class="mb-1">Set Your Working Hours</h2>
      <p class="text-muted">Configure your availability day by day.</p>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12">
      <div class="accordion" id="workingHoursAccordion">
        <?php
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($days as $index => $day) :
        ?>
          <div class="accordion-item">
            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="true" aria-controls="collapse<?php echo $index; ?>">
                <?php echo $day; ?>
              </button>
            </h2>
            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#workingHoursAccordion">
              <div class="accordion-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label for="startTime<?php echo $index; ?>" class="form-label">Start Time</label>
                    <input type="time" class="form-control" id="startTime<?php echo $index; ?>" name="startTime<?php echo $index; ?>">
                  </div>
                  <div class="col-md-6">
                    <label for="endTime<?php echo $index; ?>" class="form-label">End Time</label>
                    <input type="time" class="form-control" id="endTime<?php echo $index; ?>" name="endTime<?php echo $index; ?>">
                  </div>
                  <div class="col-12">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="dayOff<?php echo $index; ?>" name="dayOff<?php echo $index; ?>">
                      <label class="form-check-label" for="dayOff<?php echo $index; ?>">Mark as Day Off</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-primary mt-3" id="saveWorkingHours">Save Working Hours</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const hoursData = <?php echo json_encode($hours_data); ?> || {};

    // Populate form fields on page load
    days.forEach((day, index) => {
        const startInput = document.getElementById(`startTime${index}`);
        const endInput = document.getElementById(`endTime${index}`);
        const dayOffCheckbox = document.getElementById(`dayOff${index}`);

        const dayData = hoursData[day] ? JSON.parse(hoursData[day]) : null;
        if (dayData && dayData.start_time && dayData.end_time) {
            startInput.value = dayData.start_time || '';
            endInput.value = dayData.end_time || '';
            dayOffCheckbox.checked = false;
        } else {
            startInput.value = '';
            endInput.value = '';
            dayOffCheckbox.checked = true;
        }

        // Sync checkbox with time inputs
        dayOffCheckbox.addEventListener('change', function() {
            if (this.checked) {
                startInput.value = '';
                endInput.value = '';
                startInput.disabled = true;
                endInput.disabled = true;
            } else {
                startInput.disabled = false;
                endInput.disabled = false;
            }
        });

        // Disable inputs initially if day is off
        if (dayOffCheckbox.checked) {
            startInput.disabled = true;
            endInput.disabled = true;
        }
    });

    const saveButton = document.getElementById('saveWorkingHours');
    saveButton.addEventListener('click', function() {
        const workingHours = {};
        days.forEach((day, index) => {
            const startTime = document.getElementById(`startTime${index}`).value;
            const endTime = document.getElementById(`endTime${index}`).value;
            const dayOff = document.getElementById(`dayOff${index}`).checked;
            workingHours[day] = {
                startTime: dayOff ? '' : startTime,
                endTime: dayOff ? '' : endTime,
                dayOff
            };
        });

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=save_working_hours&nonce=<?php echo wp_create_nonce('mentor_dashboard_nonce'); ?>&data=${encodeURIComponent(JSON.stringify(workingHours))}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Working hours saved successfully!', 'success');
            } else {
                showNotification('Failed to save working hours.', 'danger');
            }
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