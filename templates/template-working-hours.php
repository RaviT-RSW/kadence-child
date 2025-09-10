<?php
/**
 * Template Name: Working Hours
 */
get_header();
?>
<div class="container-fluid">
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
  $off_data = [];
  if ($working_hours) {
      $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
      foreach ($days as $day) {
          $day_lower = strtolower($day);
          $json = $working_hours->$day_lower;
          $data = $json ? json_decode($json, true) : null;
          if ($data && isset($data['off'])) {
              $off_data[$day] = $data['off'];
              $hours_data[$day] = $data['slots'] ?? [];
          } else {
              $hours_data[$day] = $data ?? [];
              $off_data[$day] = empty($hours_data[$day]);
          }
      }
  }
  ?>

  <div class="row mb-4">
    <div class="col-12">
      <h2 class="mb-1">Set Your Working Hours</h2>
      <p class="text-muted">Configure your availability with multiple time slots per day.</p>
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
                <div class="form-check mt-3">
                  <input class="form-check-input" type="checkbox" id="dayOff<?php echo $index; ?>" name="dayOff<?php echo $index; ?>" <?php echo !empty($off_data[$day]) ? 'checked' : ''; ?>>
                  <label class="form-check-label" for="dayOff<?php echo $index; ?>">Mark as Day Off</label>
                </div>
                <div class="time-slots" id="timeSlots<?php echo $index; ?>">
                  <?php
                  $slots = $hours_data[$day] ?: [[]];
                  foreach ($slots as $slot_index => $slot) :
                  ?>
                    <div class="row g-3 time-slot mb-3" data-day="<?php echo $day; ?>" data-slot-index="<?php echo $slot_index; ?>">
                      <div class="col-md-5">
                        <label for="startTime<?php echo $index; ?>_<?php echo $slot_index; ?>" class="form-label">Start Time</label>
                        <input type="time" class="form-control start-time" id="startTime<?php echo $index; ?>_<?php echo $slot_index; ?>" name="startTime<?php echo $index; ?>_<?php echo $slot_index; ?>" value="<?php echo isset($slot['start_time']) ? esc_attr($slot['start_time']) : ''; ?>">
                      </div>
                      <div class="col-md-5">
                        <label for="endTime<?php echo $index; ?>_<?php echo $slot_index; ?>" class="form-label">End Time</label>
                        <input type="time" class="form-control end-time" id="endTime<?php echo $index; ?>_<?php echo $slot_index; ?>" name="endTime<?php echo $index; ?>_<?php echo $slot_index; ?>" value="<?php echo isset($slot['end_time']) ? esc_attr($slot['end_time']) : ''; ?>">
                      </div>
                      <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger remove-slot">Remove</button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-outline-primary mt-2 add-slot" data-day-index="<?php echo $index; ?>">Add Time Slot</button>
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
        const timeSlotsContainer = document.getElementById(`timeSlots${index}`);
        const dayOffCheckbox = document.getElementById(`dayOff${index}`);
        const addSlotButton = timeSlotsContainer.parentElement.querySelector('.add-slot');

        // Sync checkbox with time slots
        function toggleTimeSlots() {
            const inputs = timeSlotsContainer.querySelectorAll('input');
            const removeButtons = timeSlotsContainer.querySelectorAll('.remove-slot');
            if (dayOffCheckbox.checked) {
                inputs.forEach(input => input.disabled = true);
                removeButtons.forEach(btn => btn.disabled = true);
                addSlotButton.disabled = true;
            } else {
                inputs.forEach(input => input.disabled = false);
                removeButtons.forEach(btn => btn.disabled = false);
                addSlotButton.disabled = false;
            }
        }

        dayOffCheckbox.addEventListener('change', toggleTimeSlots);
        toggleTimeSlots();

        // Add new time slot
        addSlotButton.addEventListener('click', function() {
            if (dayOffCheckbox.checked) return;
            const slotIndex = timeSlotsContainer.querySelectorAll('.time-slot').length;
            const newSlot = document.createElement('div');
            newSlot.className = 'row g-3 time-slot mb-3';
            newSlot.dataset.day = day;
            newSlot.dataset.slotIndex = slotIndex;
            newSlot.innerHTML = `
                <div class="col-md-5">
                    <label for="startTime${index}_${slotIndex}" class="form-label">Start Time</label>
                    <input type="time" class="form-control start-time" id="startTime${index}_${slotIndex}" name="startTime${index}_${slotIndex}">
                </div>
                <div class="col-md-5">
                    <label for="endTime${index}_${slotIndex}" class="form-label">End Time</label>
                    <input type="time" class="form-control end-time" id="endTime${index}_${slotIndex}" name="endTime${index}_${slotIndex}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger remove-slot">Remove</button>
                </div>
            `;
            timeSlotsContainer.appendChild(newSlot);
            newSlot.querySelector('.remove-slot').addEventListener('click', function() {
                newSlot.remove();
            });
        });

        // Remove time slot
        timeSlotsContainer.querySelectorAll('.remove-slot').forEach(button => {
            button.addEventListener('click', function() {
                if (!dayOffCheckbox.checked) {
                    button.closest('.time-slot').remove();
                }
            });
        });
    });

    // Helper functions for validation
    function timeToMinutes(t) {
        if (!t) return 0;
        const [h, m] = t.split(':').map(Number);
        return h * 60 + m;
    }

    function checkOverlaps(slots) {
        const validSlots = slots.filter(s => s.start_time && s.end_time && timeToMinutes(s.start_time) < timeToMinutes(s.end_time));
        if (validSlots.length === 0) return true;
        validSlots.sort((a, b) => timeToMinutes(a.start_time) - timeToMinutes(b.start_time));
        for (let i = 1; i < validSlots.length; i++) {
            if (timeToMinutes(validSlots[i - 1].end_time) > timeToMinutes(validSlots[i].start_time)) {
                return false;
            }
        }
        return true;
    }

    // Save working hours
    const saveButton = document.getElementById('saveWorkingHours');
    saveButton.addEventListener('click', function() {
        let valid = true;
        const workingHours = {};
        days.forEach((day, index) => {
            const timeSlots = document.getElementById(`timeSlots${index}`).querySelectorAll('.time-slot');
            const dayOff = document.getElementById(`dayOff${index}`).checked;
            const slots = Array.from(timeSlots).map((slot, slotIndex) => ({
                start_time: slot.querySelector(`#startTime${index}_${slotIndex}`).value,
                end_time: slot.querySelector(`#endTime${index}_${slotIndex}`).value
            }));
            if (!dayOff) {
                // Validate slots
                for (let s of slots) {
                    if ((s.start_time && !s.end_time) || (!s.start_time && s.end_time)) {
                        valid = false;
                        showNotification(`Incomplete time slot on ${day}`, 'danger');
                    } else if (s.start_time && s.end_time && timeToMinutes(s.start_time) >= timeToMinutes(s.end_time)) {
                        valid = false;
                        showNotification(`Invalid time slot on ${day}: start time must be before end time`, 'danger');
                    }
                }
                if (!checkOverlaps(slots)) {
                    valid = false;
                    showNotification(`Overlapping time slots on ${day}`, 'danger');
                }
            }
            workingHours[day] = {
                off: dayOff,
                slots: slots.filter(s => s.start_time && s.end_time) // Filter out incomplete slots
            };
        });

        if (!valid) return;

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
.time-slot {
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}
</style>

<?php get_footer(); ?>