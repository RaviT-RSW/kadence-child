/* Enhanced Mentor Dashboard JavaScript with Availability Management */

// Enhanced Calendar Logic with detailed appointment display
const allAppointments = mentorDashboard.allAppointments;
let currentDate = new Date();
let viewMode = 'month';

// Availability Management Variables
let availabilityModal;
let specialDaysModal;
let currentAvailability = mentorDashboard.availability || {};
let specialDays = [];

function getAppointmentStatusClass(status, bookingStart) {
    const appointmentDate = new Date(bookingStart);
    const now = new Date();
    
    if (appointmentDate < now) {
        return 'past';
    }
    
    return status; // approved, pending, canceled, rejected
}

function formatTime(dateString) {
    return new Date(dateString).toLocaleTimeString([], { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: false 
    });
}

function formatTimeRange(startDate, endDate) {
    const start = formatTime(startDate);
    const end = formatTime(endDate);
    return `${start} - ${end}`;
}

function createAppointmentCard(app, viewMode) {
    const statusClass = getAppointmentStatusClass(app.status, app.bookingStart);
    const timeRange = formatTimeRange(app.bookingStart, app.bookingEnd);
    const startTime = formatTime(app.bookingStart);
    
    if (viewMode === 'week') {
        // Week view - more detailed cards
        return `
            <div class="appointment-card week-view ${statusClass}" 
                 onclick="viewAppointmentDetails(${app.id})" 
                 title="Click for details">
                <div class="appointment-header">
                    <div class="appointment-time">${timeRange}</div>
                    <div class="appointment-status-indicator ${statusClass}"></div>
                </div>
                <div class="appointment-title">${app.service_name || 'Session'}</div>
                <div class="appointment-customer">
                    <i class="fas fa-user"></i>
                    ${app.customer_first_name} ${app.customer_last_name}
                </div>
                <div class="appointment-location">
                    <i class="fas fa-map-marker-alt"></i>
                    ${app.display_location || app.location_name}
                </div>
                ${app.customer_phone ? `
                    <div class="appointment-phone">
                        <i class="fas fa-phone"></i>
                        ${app.customer_phone}
                    </div>
                ` : ''}
            </div>
        `;
    } else {
        // Month view - compact format
        return `
            <div class="appointment-card month-view ${statusClass}" 
                 onclick="viewAppointmentDetails(${app.id})" 
                 title="${app.customer_first_name} ${app.customer_last_name} - ${app.service_name} (${startTime}) - ${app.display_location || 'Virtual'}">
                <div class="appointment-time">${startTime}</div>
                <div class="appointment-summary">
                    <span class="appointment-customer-name">${app.customer_first_name} ${app.customer_last_name}</span>
                </div>
                <div class="appointment-service">${app.service_name}</div>
            </div>
        `;
    }
}

function renderCalendar(mode) {
    viewMode = mode;
    const calendarBody = document.getElementById('calendarBody');
    const calendarTitle = document.getElementById('calendarTitle');
    calendarBody.className = `calendar-body ${mode}-view`;

    if (mode === 'month') {
        renderMonthView();
    } else {
        renderWeekView();
    }

    // Update button states
    document.querySelectorAll('.btn-group button').forEach(btn => {
        btn.classList.toggle('active', btn.textContent.toLowerCase() === mode);
    });
}

function renderMonthView() {
    const calendarBody = document.getElementById('calendarBody');
    const calendarTitle = document.getElementById('calendarTitle');
    const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
    const startDay = firstDay.getDay();
    const daysInMonth = lastDay.getDate();
    calendarTitle.textContent = `${currentDate.toLocaleString('default', { month: 'long' })} ${currentDate.getFullYear()}`;

    let html = '';
    // Add day headers
    const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    daysOfWeek.forEach(day => {
        html += `<div class="calendar-day-header">${day}</div>`;
    });

    // Add empty cells for days before the first day of the month
    for (let i = 0; i < startDay; i++) {
        html += `<div class="calendar-day empty-day"></div>`;
    }

    // Add days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = dateStr === new Date().toISOString().split('T')[0];
        const dayAppointments = allAppointments.filter(app => 
            new Date(app.bookingStart).toISOString().split('T')[0] === dateStr
        );

        // Sort appointments by time and limit display in month view
        dayAppointments.sort((a, b) => new Date(a.bookingStart) - new Date(b.bookingStart));
        const displayAppointments = dayAppointments.slice(0, 3); // Show max 3 appointments
        const remainingCount = dayAppointments.length - 3;

        html += `
            <div class="calendar-day month-day ${isToday ? 'today' : ''}">
                <div class="day-number">${day}</div>
                <div class="appointments-container">
                    ${displayAppointments.map(app => createAppointmentCard(app, 'month')).join('')}
                    ${remainingCount > 0 ? `
                        <div class="more-appointments" onclick="showDayAppointments('${dateStr}')">
                            +${remainingCount} more
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    calendarBody.innerHTML = html;
}

function renderWeekView() {
    const calendarBody = document.getElementById('calendarBody');
    const calendarTitle = document.getElementById('calendarTitle');
    const startOfWeek = new Date(currentDate);
    startOfWeek.setDate(currentDate.getDate() - currentDate.getDay());
    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);
    calendarTitle.textContent = `${startOfWeek.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${endOfWeek.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;

    let html = '';
    const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    // Add day headers with dates
    daysOfWeek.forEach((day, index) => {
        const currentDay = new Date(startOfWeek);
        currentDay.setDate(startOfWeek.getDate() + index);
        const isToday = currentDay.toISOString().split('T')[0] === new Date().toISOString().split('T')[0];
        
        html += `
            <div class="calendar-day-header week-header ${isToday ? 'today-header' : ''}">
                <div class="day-name">${day}</div>
                <div class="day-date">${currentDay.getDate()}</div>
            </div>
        `;
    });

    // Add day columns
    for (let i = 0; i < 7; i++) {
        const currentDay = new Date(startOfWeek);
        currentDay.setDate(startOfWeek.getDate() + i);
        const dateStr = currentDay.toISOString().split('T')[0];
        const isToday = dateStr === new Date().toISOString().split('T')[0];
        const dayAppointments = allAppointments.filter(app => 
            new Date(app.bookingStart).toISOString().split('T')[0] === dateStr
        );

        // Sort appointments by time
        dayAppointments.sort((a, b) => new Date(a.bookingStart) - new Date(b.bookingStart));

        html += `
            <div class="calendar-day week-day ${isToday ? 'today' : ''}">
                <div class="week-appointments-container">
                    ${dayAppointments.map(app => createAppointmentCard(app, 'week')).join('')}
                </div>
            </div>
        `;
    }

    calendarBody.innerHTML = html;
}

function prevPeriod() {
    if (viewMode === 'month') {
        currentDate.setMonth(currentDate.getMonth() - 1);
    } else {
        currentDate.setDate(currentDate.getDate() - 7);
    }
    renderCalendar(viewMode);
}

function nextPeriod() {
    if (viewMode === 'month') {
        currentDate.setMonth(currentDate.getMonth() + 1);
    } else {
        currentDate.setDate(currentDate.getDate() + 7);
    }
    renderCalendar(viewMode);
}

// Function to show all appointments for a specific day
function showDayAppointments(dateStr) {
    const dayAppointments = allAppointments.filter(app => 
        new Date(app.bookingStart).toISOString().split('T')[0] === dateStr
    );
    
    if (dayAppointments.length === 0) return;
    
    const date = new Date(dateStr);
    const formattedDate = date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    let modalContent = `
        <div class="day-appointments-modal">
            <h6 class="mb-3">${formattedDate}</h6>
            <div class="list-group">
    `;
    
    dayAppointments.forEach(app => {
        const statusClass = getAppointmentStatusClass(app.status, app.bookingStart);
        const timeRange = formatTimeRange(app.bookingStart, app.bookingEnd);
        const location = app.display_location || app.location_name;
        
        modalContent += `
            <div class="list-group-item d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <h6 class="mb-1">${app.service_name}</h6>
                    <p class="mb-1"><strong>${app.customer_first_name} ${app.customer_last_name}</strong></p>
                    <small class="text-muted d-block">${timeRange}</small>
                    <small class="text-muted d-block">
                        <i class="fas fa-map-marker-alt me-1"></i>${location}
                    </small>
                    ${app.customer_phone ? `
                        <small class="text-muted d-block">
                            <i class="fas fa-phone me-1"></i>${app.customer_phone}
                        </small>
                    ` : ''}
                </div>
                <span class="badge bg-${statusClass === 'approved' ? 'success' : statusClass === 'pending' ? 'secondary' : 'danger'}">
                    ${app.status}
                </span>
            </div>
        `;
    });
    
    modalContent += `
            </div>
        </div>
    `;
    
    // Show in existing session details modal
    document.getElementById('sessionDetailsContent').innerHTML = modalContent;
    document.getElementById('sessionDetailsModal').querySelector('.modal-title').textContent = 'Day Schedule';
    new bootstrap.Modal(document.getElementById('sessionDetailsModal')).show();
}

// Initialize calendar when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {

    // Attach event listeners to calendar buttons
    document.getElementById('monthViewBtn').addEventListener('click', () => renderCalendar('month'));
    document.getElementById('weekViewBtn').addEventListener('click', () => renderCalendar('week'));
    document.getElementById('prevPeriodBtn').addEventListener('click', prevPeriod);
    document.getElementById('nextPeriodBtn').addEventListener('click', nextPeriod);

    // Initial renders
    renderCalendar('month');
    
    // Initialize Bootstrap modal for mentee details
    const menteeModalElement = document.getElementById('menteeDetailsModal');
    if (menteeModalElement) {
        menteeModal = new bootstrap.Modal(menteeModalElement, {
            backdrop: 'static',
            keyboard: true
        });
    }
});

function viewAppointmentDetails(appointmentId) {
    fetch(mentorDashboard.ajaxUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_appointment_details&appointment_id=' + appointmentId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('sessionDetailsContent').innerHTML = data.data.html;
            new bootstrap.Modal(document.getElementById('sessionDetailsModal')).show();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error loading appointment details', 'danger');
    });
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Global variables
let menteeModal;

// Custom dropdown functionality
function toggleDropdown(index) {
    // Close all other dropdowns
    document.querySelectorAll('.dropdown-menu-custom').forEach(function(menu, i) {
        if (i !== index) {
            menu.classList.remove('show');
        }
    });
    
    // Toggle current dropdown
    const dropdown = document.getElementById('dropdown-' + index);
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.mentee-dropdown')) {
        document.querySelectorAll('.dropdown-menu-custom').forEach(function(menu) {
            menu.classList.remove('show');
        });
    }
});

// Prevent dropdown from closing when clicking inside
document.querySelectorAll('.dropdown-menu-custom').forEach(function(menu) {
    menu.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});

// Function to show mentee details with modal
function showMenteeDetails(customerId) {
    // Close all dropdowns first
    document.querySelectorAll('.dropdown-menu-custom').forEach(function(menu) {
        menu.classList.remove('show');
    });

    // Check if we have customers data from localized script
    if (typeof mentorDashboard === 'undefined' || !mentorDashboard.customers) {
        showNotification('Customer data not available', 'danger');
        return;
    }

    const customers = mentorDashboard.customers;
    const customer = customers.find(c => c.id == customerId);
    
    if (!customer) {
        showNotification('Customer not found', 'danger');
        return;
    }

    // Generate modal content HTML
    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-user me-2"></i>Profile Information</h6>
                <p><strong>Email:</strong> ${customer.email || 'N/A'}</p>
                <p><strong>Phone:</strong> ${customer.phone || 'N/A'}</p>
                <p><strong>Date of Birth:</strong> ${customer.date_of_birth || 'N/A'}</p>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h6>
                <p><strong>Name:</strong> ${customer.emergency_contact_name || 'N/A'}</p>
                <p><strong>Phone:</strong> ${customer.emergency_contact_phone || 'N/A'}</p>
            </div>
        </div>
        <div class="mt-4">
            <h6><i class="fas fa-clipboard-list me-2"></i>Mentoring Plan</h6>
            ${customer.plan_name ? `
                <p><strong>Plan:</strong> ${customer.plan_name}</p>
                <p><strong>Description:</strong> ${customer.plan_description || 'No description'}</p>
                <p><strong>Start Date:</strong> ${customer.plan_start_date ? new Date(customer.plan_start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}</p>
            ` : '<p>No mentoring plan assigned.</p>'}
        </div>
        <div class="mt-4">
            <h6><i class="fas fa-history me-2"></i>Session History</h6>
            ${customer.session_history && customer.session_history.length > 0 ? `
                <div class="list-group">
                    ${customer.session_history.slice(0, 10).map(session => {
                        const sessionDate = new Date(session.date);
                        const endDate = new Date(session.end_date);
                        const isValidDate = !isNaN(sessionDate.getTime());
                        const statusClass = `session-status-${session.status}`;
                        
                        return `
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">${session.service_name}</h6>
                                    <small class="${statusClass}">
                                        ${(() => {
                                            const sessionDate = new Date(session.date);
                                            const sessionEndDate = new Date(session.end_date || session.date);
                                            const now = new Date();
                                            
                                            if (session.status === 'approved' && sessionEndDate < now) {
                                                return 'COMPLETED';
                                            } else if (session.status === 'approved' && sessionDate > now) {
                                                return 'SCHEDULED';
                                            } else if (session.status === 'pending') {
                                                return 'PENDING';
                                            } else {
                                                return session.status.toUpperCase();
                                            }
                                        })()}
                                    </small>
                                </div>
                                <p class="mb-1">
                                    <i class="fas fa-calendar me-1"></i>
                                    ${isValidDate ? sessionDate.toLocaleDateString('en-US', { 
                                        weekday: 'short', 
                                        month: 'short', 
                                        day: 'numeric', 
                                        year: 'numeric' 
                                    }) : 'Invalid Date'}
                                </p>
                                <small>
                                    <i class="fas fa-clock me-1"></i>
                                    ${isValidDate ? sessionDate.toLocaleTimeString('en-US', { 
                                        hour: 'numeric', 
                                        minute: '2-digit', 
                                        hour12: true 
                                    }) : 'Invalid Time'} - 
                                    ${!isNaN(endDate.getTime()) ? endDate.toLocaleTimeString('en-US', { 
                                        hour: 'numeric', 
                                        minute: '2-digit', 
                                        hour12: true 
                                    }) : 'Invalid End Time'}
                                </small>
                            </div>
                        `;
                    }).join('')}
                </div>
                ${customer.session_history.length > 10 ? `<p class="mt-2 text-muted">...and ${customer.session_history.length - 10} more sessions.</p>` : ''}
                <div class="mt-3">
                    <p class="mb-1"><strong>Total Sessions:</strong> ${customer.session_history.length}</p>
                    <p class="mb-1"><strong>Completed Sessions:</strong> ${customer.session_history.filter(s => {
                        const sessionDate = new Date(s.end_date || s.date);
                        const now = new Date();
                        return s.status === 'approved' && sessionDate < now;
                    }).length}</p>
                    <p class="mb-0"><strong>Upcoming Sessions:</strong> ${customer.session_history.filter(s => {
                        const sessionDate = new Date(s.date);
                        const now = new Date();
                        return (s.status === 'pending' || s.status === 'approved') && sessionDate > now;
                    }).length}</p>
                </div>
            ` : '<p>No sessions attended yet.</p>'}
        </div>
    `;

    // Update modal content and title
    document.getElementById('menteeDetailsContent').innerHTML = html;
    document.getElementById('menteeDetailsModalLabel').innerText = `${customer.firstName} ${customer.lastName} - Details`;
    
    // Show the modal
    if (menteeModal) {
        menteeModal.show();
    }
}

/*mentor js end*/