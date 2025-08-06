<?php
/**
 * Template Name: Mentor Dashboard
 */
get_header();
?>

<!-- Keep your existing CSS and Bootstrap links -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<!-- Hero Section -->
<section class="entry-hero page-hero-section entry-hero-layout-standard">
  <div class="entry-hero-container-inner">
    <div class="hero-section-overlay"></div>
    <div class="hero-container site-container">
      <header class="entry-header page-title title-align-inherit title-tablet-align-inherit title-mobile-align-inherit">
        <h1 class="entry-title">Mentor Dashboard</h1>
      </header>
    </div>
  </div>
</section>

<div class="container-fluid my-5">
  <!-- Welcome Section -->
  <div class="row mb-4">
    <div class="col-12">
      <h2 class="mb-1">Welcome back, <span class="text-primary"><?php echo wp_get_current_user()->display_name; ?></span></h2>
      <p class="text-muted">Manage your mentoring sessions, track progress, and connect with your mentees.</p>
    </div>
  </div>

  <div class="row g-4">
    <!-- Left Column - Main Dashboard -->
    <div class="col-lg-8">
      <!-- Upcoming Sessions from Amelia -->
      <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Upcoming Sessions</h5>
          </div>
          <div class="card-body">
            <div class="list-group list-group-flush">
                              <div class="list-group-item text-center py-4">
                  <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                  <p class="text-muted mb-0">No upcoming sessions scheduled</p>
                </div>
                          </div>
          </div>
        </div>

      <!-- Amelia Calendar Integration -->
      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="card-title mb-0"><i class="fas fa-calendar me-2"></i>Session Schedule</h5>
          <div class="btn-group" role="group">
            <button type="button" class="btn btn-outline-primary btn-sm active">Week</button>
            <button type="button" class="btn btn-outline-primary btn-sm">Month</button>
          </div>
        </div>
        <div class="card-body">
          <!-- Amelia Calendar Shortcode - this will show only current employee's appointments -->
                  </div>
      </div>
    </div>

    <!-- Right Column - Sidebar -->
    <div class="col-lg-4">
      
      <!-- Connected Customers from Amelia -->
      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="fas fa-users me-2"></i>My Mentees</h5>
            <span class="badge bg-primary">0</span>
        </div>
        <div class="card-body">
            <div class="mentee-list">
                                    <div class="text-center py-3">
                        <i class="fas fa-user-plus fa-2x text-muted mb-2"></i>
                        <p class="text-muted mb-0">No customers assigned yet</p>
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

<!-- Updated Styles -->
<style>
.mentee-item {
  transition: background-color 0.2s;
  padding: 10px;
  border-radius: 8px;
}

.mentee-item:hover {
  background-color: #f8f9fa;
}

.chat-item {
  padding: 8px;
  border-radius: 6px;
  transition: background-color 0.2s;
}

.chat-item:hover {
  background-color: #f8f9fa;
}

.calendar-placeholder {
  min-height: 300px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}

.stat-item h4 {
  font-size: 1.5rem;
  font-weight: 600;
}

.next-session-info p {
  margin-bottom: 0.5rem;
}

.pay-summary .row {
  margin-bottom: 1rem;
}

.session-details p {
  margin-bottom: 0.5rem;
}

/* Ensure smooth modal transitions */
.modal.fade .modal-dialog {
  transition: transform 0.3s ease-out;
}

.modal.show .modal-dialog {
  transform: none;
}

@media (max-width: 768px) {
  .container-fluid {
    padding: 0 15px;
  }
  
  .mentee-item {
    padding: 8px;
  }
  
  .stat-item h4 {
    font-size: 1.2rem;
  }
}

/* Card hover effects */
.card {
  transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}

/* Button animations */
.btn {
  transition: all 0.2s;
}

/* List group item hover */
.list-group-item:hover {
  background-color: #f8f9fa;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Enhanced JavaScript with Amelia integration
document.addEventListener('DOMContentLoaded', function() {
    // Session completion form handling
    const sessionForm = document.getElementById('sessionCompletionForm');
    if (sessionForm) {
        sessionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            showNotification('Session report submitted successfully!', 'success');
        });
    }

    // Ensure only one modal instance is created
    const menteeModal = new bootstrap.Modal(document.getElementById('menteeDetailsModal'), {
        backdrop: 'static',
        keyboard: true
    });

    // Function to show mentee details
    window.showMenteeDetails = function(customerId) {
        // Find customer data
        const customers = <?php echo json_encode($customers); ?>;
        const customer = customers.find(c => c.id == customerId);
        if (!customer) {
            showNotification('Customer not found', 'danger');
            return;
        }

        // Populate modal content
        let html = `
            <div class="row">
                <!-- Profile Info -->
                <div class="col-md-6">
                    <h6><i class="fas fa-user me-2"></i>Profile Information</h6>
                    <p><strong>Email:</strong> ${customer.email || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${customer.phone || 'N/A'}</p>
                    <p><strong>Date of Birth:</strong> ${customer.date_of_birth || 'N/A'}</p>
                </div>
                <!-- Emergency Contacts -->
                <div class="col-md-6">
                    <h6><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h6>
                    <p><strong>Name:</strong> ${customer.emergency_contact_name || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${customer.emergency_contact_phone || 'N/A'}</p>
                </div>
            </div>
            <!-- Mentoring Plan -->
            <div class="mt-4">
                <h6><i class="fas fa-clipboard-list me-2"></i>Mentoring Plan</h6>
                ${customer.plan_name ? `
                    <p><strong>Plan:</strong> ${customer.plan_name}</p>
                    <p><strong>Description:</strong> ${customer.plan_description || 'No description'}</p>
                    <p><strong>Start Date:</strong> ${customer.plan_start_date ? new Date(customer.plan_start_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}</p>
                ` : '<p>No mentoring plan assigned.</p>'}
            </div>
            <!-- Session History -->
            <div class="mt-4">
                <h6><i class="fas fa-history me-2"></i>Session History</h6>
                ${customer.session_history && customer.session_history.length > 0 ? `
                    <ul class="list-group">
                        ${customer.session_history.slice(0, 5).map(session => `
                            <li class="list-group-item">
                                <strong>${session.service_name}</strong><br>
                                <small>
                                    Date: ${new Date(session.date).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true })}<br>
                                    Status: ${session.status}
                                </small>
                            </li>
                        `).join('')}
                    </ul>
                    ${customer.session_history.length > 5 ? `<p class="mt-2">...and ${customer.session_history.length - 5} more sessions.</p>` : ''}
                ` : '<p>No sessions attended yet.</p>'}
            </div>
        `;

        document.getElementById('menteeDetailsContent').innerHTML = html;
        document.getElementById('menteeDetailsModalLabel').innerText = `${customer.firstName} ${customer.lastName} - Details`;
        menteeModal.show();
    };
});

function viewAppointmentDetails(appointmentId) {
    // AJAX call to get appointment details
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
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
</script>

<?php get_footer(); ?>