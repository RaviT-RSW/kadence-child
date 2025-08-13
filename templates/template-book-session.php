<?php
/**
 * Template Name: Book Session
 */
get_header();
?>

<div class="container my-5">
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

  // Fetch children of current parent
  $children = get_users(array(
      'role' => 'child_user',
      'meta_key' => 'assigned_parent_id',
      'meta_value' => $parent_id,
  ));
  ?>
  <div class="row mb-4">
    <div class="col-12">
      <h2 class="mb-1">Book a Session for Your Child</h2>
      <p class="text-muted">Select a child, mentor, session, and date/time to book.</p>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12">
      <form id="bookSessionForm" method="post">
        <div class="mb-3">
          <label for="childSelect" class="form-label">Select Child</label>
          <select class="form-select" id="childSelect" name="childSelect" required>
            <option value="">Select a child</option>
            <?php foreach ($children as $child) : ?>
              <option value="<?php echo esc_attr($child->ID); ?>"><?php echo ucfirst($child->display_name); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="mentorSelect" class="form-label">Select Mentor</label>
          <select class="form-select" id="mentorSelect" name="mentorSelect" required disabled>
            <option value="">Select a child first</option>
          </select>
        </div>

        <div class="mb-3">
          <label for="sessionProduct" class="form-label">Select Session</label>
          <select class="form-select" id="sessionProduct" name="sessionProduct" required disabled>
            <option value="">Select a session</option>
            <?php foreach ($products as $product) : ?>
              <option value="<?php echo esc_attr($product->ID); ?>"><?php echo esc_html($product->post_title); ?> - $<?php echo get_post_meta($product->ID, '_price', true); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Select Date and Time</label>
          <div class="calendar-container">
            <div class="calendar-header">
              <select id="monthSelect"></select>
              <select id="yearSelect"></select>
            </div>
            <div class="calendar-grid" id="calendarDays">
              <div class="day">Mon</div>
              <div class="day">Tue</div>
              <div class="day">Wed</div>
              <div class="day">Thu</div>
              <div class="day">Fri</div>
              <div class="day">Sat</div>
              <div class="day">Sun</div>
            </div>
            <div id="selectedDateText" style="margin-bottom:10px;font-weight:bold;"></div>
            <div class="time-slots" id="timeSlots"></div>
          </div>
          <input type="hidden" id="selectedSlot" name="sessionDateTime" required>
        </div>
              
        <button type="submit" class="btn btn-primary" disabled>Book Session</button>
      </form>
    </div>
  </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let currentMentorId = null;
        let mentorHours = {};
        let bookedSlots = {}; // Initialize as an object

        const childSelect = document.getElementById('childSelect');
        const mentorSelect = document.getElementById('mentorSelect');
        const sessionProduct = document.getElementById('sessionProduct');
        const selectedSlot = document.getElementById('selectedSlot');
        const submitButton = document.querySelector('#bookSessionForm button[type="submit"]');
        const monthSelect = document.getElementById("monthSelect");
        const yearSelect = document.getElementById("yearSelect");
        const calendarDays = document.getElementById("calendarDays");
        const selectedDateText = document.getElementById("selectedDateText");
        const timeSlotsContainer = document.getElementById("timeSlots");

        const months = [
            "January", "February", "March", "April", "May", "June",
            "July", "August", "September", "October", "November", "December"
        ];

        let currentMonth = new Date().getMonth(); // August (7) on 2025-08-13
        let currentYear = new Date().getFullYear(); // 2025
        let selectedDate = null;

        // Populate month and year dropdowns
        function populateMonthYear() {
            monthSelect.innerHTML = months.map((m, i) => `<option value="${i}" ${i === currentMonth ? 'selected' : ''}>${m}</option>`).join("");
            let years = "";
            for (let y = currentYear - 5; y <= currentYear + 5; y++) {
                years += `<option value="${y}" ${y === currentYear ? 'selected' : ''}>${y}</option>`;
            }
            yearSelect.innerHTML = years;
        }

        // Render calendar with disabled dates
        function renderCalendar() {
        calendarDays.querySelectorAll(".date").forEach(el => el.remove());
        const firstDay = new Date(currentYear, currentMonth, 1);
        const startDay = (firstDay.getDay() + 6) % 7; // Make Monday start
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const prevMonthDays = new Date(currentYear, currentMonth, 0).getDate();
        const today = new Date(); // Current date for comparison
        today.setHours(0, 0, 0, 0); // Normalize to midnight for accurate comparison

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
        document.querySelectorAll(".date").forEach(d => d.classList.remove("selected"));
        const dates = document.querySelectorAll(".date");
        dates.forEach(el => {
            if (el.textContent == day && !el.classList.contains("inactive")) {
                el.classList.add("selected");
            }
        });
        selectedDate = new Date(currentYear, currentMonth, day);
        selectedDateText.textContent = selectedDate.toDateString();

        // Use local date in YYYY-MM-DD format
        const fullDateLocal = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        renderTimeSlots(fullDateLocal); // Pass the local date
    }

    // Render time slots with correct booked slot disabling
    function renderTimeSlots(fullDate) {
        timeSlotsContainer.innerHTML = "";
        if (!selectedDate || !currentMentorId || !mentorHours[currentMentorId]) return;

        const dayName = selectedDate.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
        const dayData = mentorHours[currentMentorId][dayName];
        if (!dayData || dayData === null) return;

        const { start_time, end_time } = JSON.parse(dayData);
        let currentTime = new Date(`2000-01-01 ${start_time}`);
        const endTime = new Date(`2000-01-01 ${end_time}`);
        const bookedTimes = bookedSlots[fullDate] || []; // Array of booked times for the date

        while (currentTime < endTime) {
            const slotStart = currentTime.toTimeString().slice(0, 5);
            const slotEnd = new Date(currentTime.getTime() + 60 * 60000).toTimeString().slice(0, 5);
            const slotTime = `${slotStart} - ${slotEnd}`;
            const slotStartFull = new Date(`${fullDate}T${slotStart}:00`);
            const slotEndFull = new Date(slotStartFull.getTime() + 60 * 60000);

            // Check if this slot overlaps with any booked time
            const isBooked = bookedTimes.some(bookedTime => {
                const bookedStartFull = new Date(`${fullDate}T${bookedTime}:00`);
                const bookedEndFull = new Date(bookedStartFull.getTime() + 60 * 60000);
                return slotStartFull < bookedEndFull && slotEndFull > bookedStartFull; // Overlap check
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
                    selectedSlot.value = `${fullDate} ${slotStart}:00`;
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
                // Check if booked_slots exists, default to empty object if undefined
                const slots = data.data && data.data.booked_slots ? data.data.booked_slots : {};
                
                // Initialize an empty object to store processed slots
                bookedSlots = {};

                // Loop through the booked slots object
                Object.keys(slots).forEach(date => {
                    const times = slots[date];
                    bookedSlots[date] = times; // Directly assign the times to the date key
                });

                renderCalendar(); // Re-render calendar with updated bookedSlots

                // Re-render time slots if a date is already selected
                if (selectedDate) {
                    const fullDate = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(selectedDate.getDate()).padStart(2, '0')}`;
                    renderTimeSlots(fullDate);
                }
            } else {
                bookedSlots = {}; // Reset on failure
                renderCalendar();
                showNotification('Failed to load booked slots: ' + (data.data?.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            bookedSlots = {}; // Reset on error
            renderCalendar();
            showNotification('Failed to load booked slots: ' + error.message, 'danger');
        });
    }

    // Update mentor dropdown based on child selection
    childSelect.addEventListener('change', function() {
        const childId = this.value;
        mentorSelect.innerHTML = '<option value="">Loading mentors...</option>';
        mentorSelect.disabled = true;
        sessionProduct.disabled = true;
        selectedSlot.disabled = true;
        submitButton.disabled = true;
        currentMentorId = null;
        mentorHours = {};
        bookedSlots = {};
        timeSlotsContainer.innerHTML = ''; // Clear time slots
        selectedDate = null; // Reset selected date
        selectedDateText.textContent = ''; // Clear selected date text

        if (!childId) {
            mentorSelect.innerHTML = '<option value="">Select a child first</option>';
            calendarDays.innerHTML = '<div class="day">Mon</div><div class="day">Tue</div><div class="day">Wed</div><div class="day">Thu</div><div class="day">Fri</div><div class="day">Sat</div><div class="day">Sun</div>';
            return;
        }

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'get_assigned_mentor',
                child_id: childId,
                nonce: '<?php echo wp_create_nonce('get_assigned_mentor_nonce'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            mentorSelect.innerHTML = '<option value="">Select a mentor</option>';
            if (data.success && data.data.mentor) {
                const mentor = data.data.mentor;
                mentorSelect.innerHTML = `<option value="${mentor.id}">${mentor.name}</option>`;
                mentorSelect.disabled = false;
                sessionProduct.disabled = false;
                selectedSlot.disabled = false;
                currentMentorId = mentor.id;
                mentorHours[currentMentorId] = data.data.working_hours || {};
                fetchBookedSlots(currentMentorId, currentYear, currentMonth + 1);
            } else {
                mentorSelect.innerHTML = '<option value="">No mentor assigned</option>';
                mentorSelect.disabled = true;
                calendarDays.innerHTML = '<div class="day">Mon</div><div class="day">Tue</div><div class="day">Wed</div><div class="day">Thu</div><div class="day">Fri</div><div class="day">Sat</div><div class="day">Sun</div>';
                timeSlotsContainer.innerHTML = '';
            }
        })
        .catch(error => {
            mentorSelect.innerHTML = '<option value="">Error loading mentors</option>';
            showNotification('Failed to load mentor: ' + error.message, 'danger');
        });
    });

    // Update calendar when mentor changes
    mentorSelect.addEventListener('change', function() {
        currentMentorId = this.value;
        sessionProduct.disabled = !currentMentorId;
        selectedSlot.disabled = !currentMentorId;
        submitButton.disabled = true;
        selectedSlot.value = '';
        timeSlotsContainer.innerHTML = '';
        selectedDate = null; // Reset selected date
        selectedDateText.textContent = ''; // Clear selected date text
        if (currentMentorId) {
            fetchBookedSlots(currentMentorId, currentYear, currentMonth + 1);
        } else {
            calendarDays.innerHTML = '<div class="day">Mon</div><div class="day">Tue</div><div class="day">Wed</div><div class="day">Thu</div><div class="day">Fri</div><div class="day">Sat</div><div class="day">Sun</div>';
        }
    });

    // Handle month/year changes
    monthSelect.addEventListener("change", () => {
        currentMonth = parseInt(monthSelect.value);
        if (currentMentorId) {
            fetchBookedSlots(currentMentorId, currentYear, currentMonth + 1);
        } else {
            renderCalendar();
        }
    });

    yearSelect.addEventListener("change", () => {
        currentYear = parseInt(yearSelect.value);
        if (currentMentorId) {
            fetchBookedSlots(currentMentorId, currentYear, currentMonth + 1);
        } else {
            renderCalendar();
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
            if (data.success) {
                window.location.href = '<?php echo wc_get_checkout_url(); ?>';
            } else {
                showNotification('Failed to book session: ' + (data.data?.message || 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
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

    populateMonthYear();
    renderCalendar();
});
</script>

<style>
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
select, button {
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
.form-select:disabled {
    background-color: #e9ecef;
    cursor: not-allowed;
}
button {
  color: black;
  background-color: transparent;
  padding: 5px 10px;
  cursor: pointer;
  outline: none;
}

button:hover {
  background-color: transparent;
  color: black;
}
</style>

<?php get_footer(); ?>