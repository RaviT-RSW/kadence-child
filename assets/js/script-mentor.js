document.addEventListener('DOMContentLoaded', function() {
    // Get localized data from wp_localize_script
    const mentorData = window.mentorDashboardData || {};
    const sessions = mentorData.sessions || [];
    
    // Initialize calendar only if we have a calendar element
    const calendarEl = document.getElementById('mentorCalendar');
    if (calendarEl && typeof FullCalendar !== 'undefined') {
        initializeCalendar(calendarEl, sessions);
    }
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

// Make showSessionDetails available globally for compatibility
window.showSessionDetails = showSessionDetails;