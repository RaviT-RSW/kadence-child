document.addEventListener('DOMContentLoaded', function() {
    // Get localized data from wp_localize_script
    const mentorData = window.mentorDashboardData || {};
    const sessions = mentorData.sessions || [];
    
    // Initialize calendar only if we have a calendar element
    const calendarEl = document.getElementById('mentorCalendar');
    if (calendarEl && typeof FullCalendar !== 'undefined') {
        initializeCalendar(calendarEl, sessions);
    }
    
    // Initialize action buttons
    initializeActionButtons();
});

/**
 * Initialize FullCalendar
 */
function initializeCalendar(calendarEl, sessions) {
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },
        height: 600,
        slotMinTime: '08:00:00',
        slotMaxTime: '20:00:00',
        allDaySlot: false,
        nowIndicator: true,
        events: sessions,
        eventClassNames: function(info) {
            const status = info.event.extendedProps.appointment_status.toLowerCase();
            switch(status) {
                case 'approved': return ['session-approved'];
                case 'pending': return ['session-pending'];
                case 'cancelled': return ['session-cancelled'];
                default: return ['session-scheduled'];
            }
        },
        eventClick: function(info) {
            showSessionDetails(info.event);
        },
        eventMouseEnter: function(info) {
            info.el.title = `${info.event.extendedProps.child_name}\n${info.event.extendedProps.product_name}\nStatus: ${info.event.extendedProps.appointment_status}`;
        }
    });
    
    calendar.render();
    
    // Setup calendar view buttons
    setupCalendarViewButtons(calendar);
}

/**
 * Setup calendar view buttons
 */
function setupCalendarViewButtons(calendar) {
    const monthBtn = document.getElementById('calendarMonth');
    const weekBtn = document.getElementById('calendarWeek');
    const dayBtn = document.getElementById('calendarDay');
    
    if (monthBtn) {
        monthBtn.addEventListener('click', function() {
            calendar.changeView('dayGridMonth');
            updateActiveButton(this);
        });
    }
    
    if (weekBtn) {
        weekBtn.addEventListener('click', function() {
            calendar.changeView('timeGridWeek');
            updateActiveButton(this);
        });
    }
    
    if (dayBtn) {
        dayBtn.addEventListener('click', function() {
            calendar.changeView('timeGridDay');
            updateActiveButton(this);
        });
    }
}

/**
 * Update active button state
 */
function updateActiveButton(activeBtn) {
    document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.classList.remove('active');
    });
    activeBtn.classList.add('active');
}

/**
 * Show session details in modal
 */
function showSessionDetails(event) {
    const props = event.extendedProps;
    const startTime = new Date(event.start).toLocaleString('en-IN', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });

    const statusClass = props.appointment_status.toLowerCase() === 'approved' ? 'success' : 
                       (props.appointment_status.toLowerCase() === 'pending' ? 'warning' : 'info');

    const content = `
        <div class="row g-3">
            <div class="col-12">
                <h6 class="text-primary">${props.product_name}</h6>
            </div>
            <div class="col-md-6">
                <strong>Mentee:</strong> ${props.child_name}
            </div>
            <div class="col-md-6">
                <strong>Date & Time:</strong> ${startTime}
            </div>
            <div class="col-md-6">
                <strong>Location:</strong> ${props.location}
            </div>
            <div class="col-md-6">
                <strong>Status:</strong> <span class="badge bg-${statusClass}">${props.appointment_status}</span>
            </div>
            <div class="col-md-6">
                <strong>Order ID:</strong> ${props.order_id}
            </div>
            <div class="col-md-6">
                <strong>Customer ID:</strong> ${props.customer_id}
            </div>
        </div>
    `;

    const contentElement = document.getElementById('sessionDetailsContent');
    if (contentElement) {
        contentElement.innerHTML = content;
        const modal = new bootstrap.Modal(document.getElementById('sessionDetailsModal'));
        modal.show();
    }
}

/**
 * Initialize action buttons (approve, cancel, etc.)
 */
function initializeActionButtons() {
    // Approve appointment buttons
    document.querySelectorAll('.approve-appoinment-btn').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            const orderId = this.getAttribute('data-order-id');
            
            if (confirm('Are you sure you want to approve this appointment?')) {
                approveAppointment(itemId, orderId, this);
            }
        });
    });
    
    // Cancel appointment buttons
    document.querySelectorAll('.cancel-btn').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            const orderId = this.getAttribute('data-order-id');
            
            if (confirm('Are you sure you want to cancel this appointment?')) {
                cancelAppointment(itemId, orderId, this);
            }
        });
    });
}

/**
 * Approve appointment via AJAX
 */
function approveAppointment(itemId, orderId, buttonElement) {
    const mentorData = window.mentorDashboardData || {};
    
    // Show loading state
    const originalText = buttonElement.textContent;
    buttonElement.textContent = 'Processing...';
    buttonElement.disabled = true;
    
    // Prepare AJAX data
    const formData = new FormData();
    formData.append('action', 'approve_appointment');
    formData.append('item_id', itemId);
    formData.append('order_id', orderId);
    formData.append('nonce', mentorData.nonce || '');
    
    // Make AJAX request
    fetch(mentorData.ajax_url || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI on success
            buttonElement.textContent = 'Approved';
            buttonElement.classList.remove('btn-success');
            buttonElement.classList.add('btn-secondary');
            
            // Hide cancel button if exists
            const cancelBtn = buttonElement.parentElement.querySelector('.cancel-btn');
            if (cancelBtn) {
                cancelBtn.style.display = 'none';
            }
            
            // Show success message
            showMessage('Appointment approved successfully!', 'success');
            
            // Optionally reload the page after a delay
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            // Handle error
            buttonElement.textContent = originalText;
            buttonElement.disabled = false;
            showMessage(data.data || 'Failed to approve appointment.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        buttonElement.textContent = originalText;
        buttonElement.disabled = false;
        showMessage('An error occurred. Please try again.', 'error');
    });
}

/**
 * Cancel appointment via AJAX
 */
function cancelAppointment(itemId, orderId, buttonElement) {
    const mentorData = window.mentorDashboardData || {};
    
    // Show loading state
    const originalText = buttonElement.textContent;
    buttonElement.textContent = 'Processing...';
    buttonElement.disabled = true;
    
    // Prepare AJAX data
    const formData = new FormData();
    formData.append('action', 'cancel_appointment');
    formData.append('item_id', itemId);
    formData.append('order_id', orderId);
    formData.append('nonce', mentorData.nonce || '');
    
    // Make AJAX request
    fetch(mentorData.ajax_url || '/wp-admin/admin-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI on success
            buttonElement.textContent = 'Cancelled';
            buttonElement.classList.remove('btn-danger');
            buttonElement.classList.add('btn-secondary');
            
            // Hide approve button if exists
            const approveBtn = buttonElement.parentElement.querySelector('.approve-appoinment-btn');
            if (approveBtn) {
                approveBtn.style.display = 'none';
            }
            
            // Show success message
            showMessage('Appointment cancelled successfully!', 'success');
            
            // Optionally reload the page after a delay
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            // Handle error
            buttonElement.textContent = originalText;
            buttonElement.disabled = false;
            showMessage(data.data || 'Failed to cancel appointment.', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        buttonElement.textContent = originalText;
        buttonElement.disabled = false;
        showMessage('An error occurred. Please try again.', 'error');
    });
}

/**
 * Show message to user
 */
function showMessage(message, type = 'info') {
    // Create alert element
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 'alert-info';
    
    const alertElement = document.createElement('div');
    alertElement.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    alertElement.style.top = '20px';
    alertElement.style.right = '20px';
    alertElement.style.zIndex = '9999';
    alertElement.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Add to document
    document.body.appendChild(alertElement);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertElement.parentElement) {
            alertElement.remove();
        }
    }, 5000);
}

// Make showSessionDetails available globally for compatibility
window.showSessionDetails = showSessionDetails;