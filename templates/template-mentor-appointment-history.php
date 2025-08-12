<?php
/**
 * Template Name: Mentor Appointment History
 */
get_header();

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
              <a href="<?php echo home_url('/wp-login.php?action=logout'); ?>" class="btn btn-outline-danger">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php
    get_footer();
    exit;
endif;

// Get current year and month
$current_year = date('Y');
$current_month = date('F'); // Dynamic current month (e.g., September in September 2025)
?>

<style>
    .appointment-history-container {
        max-width: 1300px;
        margin: 0 auto;
        padding: 20px;
        font-family: Arial, sans-serif;
    }
    .filter-bar {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-bottom: 20px;
    }
    .custom-select {
        position: relative;
        display: inline-block;
    }
    .custom-select select {
        appearance: none;
        padding: 8px 32px 8px 12px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 14px;
        background-color: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .custom-select select:hover {
        border-color: #46cdb4;
        box-shadow: 0 2px 8px rgba(70,205,180,0.3);
    }
    .custom-select i {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        pointer-events: none;
        color: #46cdb4;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        display: flex;
        align-items: center;
        gap: 15px;
        transition: transform 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-3px);
    }
    .stat-icon {
        background: #46cdb4;
        color: white;
        border-radius: 50%;
        padding: 15px;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 22px;
    }
    .stat-info h4 {
        margin: 0;
        font-size: 1.1rem;
        color: #333;
    }
    .stat-info p {
        margin: 2px 0 0;
        font-size: 1.4rem;
        font-weight: bold;
        color: #000;
    }
    .table-custom {
        width: 100%;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        border-collapse: collapse;
    }
    .table-custom th {
        background-color: #46cdb4;
        color: #fff;
        padding: 12px;
        text-align: left;
    }
    .table-custom td {
        padding: 12px;
        border-top: 1px solid #eee;
    }
    .btn-action {
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        text-decoration: none;
        color: #fff;
    }
    .btn-info { background: #17a2b8; }
    .btn-primary { background: #007bff; }
    .btn-warning { background: #ffc107; color: #000; }
    .pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 20px;
    }
    .pagination a {
        padding: 8px 14px;
        border-radius: 6px;
        background: #f1f1f1;
        text-decoration: none;
        color: #333;
        font-weight: 500;
        transition: background 0.3s;
    }
    .pagination a.active {
        background: #46cdb4;
        color: white;
    }
    .pagination a:hover {
        background: #ddd;
    }
</style>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>

<div class="appointment-history-container">
    <!-- Filters -->
    <div class="filter-bar">
        <div class="custom-select">
            <select id="yearFilter">
                <?php for ($year = $current_year - 4; $year <= $current_year; $year++) : ?>
                    <option value="<?php echo esc_attr($year); ?>" <?php echo $year == $current_year ? 'selected' : ''; ?>><?php echo esc_html($year); ?></option>
                <?php endfor; ?>
            </select>
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="custom-select">
            <select id="monthFilter">
                <option value="all" <?php echo $current_month == 'all' ? 'selected' : ''; ?>>All Months</option>
                <?php
                $months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                foreach ($months as $month) :
                ?>
                    <option value="<?php echo esc_attr($month); ?>" <?php echo $month == $current_month ? 'selected' : ''; ?>><?php echo esc_html($month); ?></option>
                <?php endforeach; ?>
            </select>
            <i class="fas fa-angle-down"></i>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <div class="stat-info">
                <h4>Hourly Rate</h4>
                <p id="hourlyRate">$0</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info">
                <h4>Total Sessions</h4>
                <p id="totalSessions">0</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-info">
                <h4>Total Hours Worked</h4>
                <p id="totalHours">0 hrs</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <h4>Total Earnings</h4>
                <p id="totalEarnings">$0</p>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Sr. No.</th>
                    <th>Title</th>
                    <th>Attende Name</th>
                    <th>Date & Time</th>
                    <th>Appointment Duration</th>
                    <th>Total Earnings</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="appointmentTableBody">
                <!-- Populated via AJAX -->
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="pagination" id="pagination">
        <!-- Populated via AJAX -->
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const yearFilter = document.getElementById('yearFilter');
    const monthFilter = document.getElementById('monthFilter');
    const appointmentTableBody = document.getElementById('appointmentTableBody');
    const pagination = document.getElementById('pagination');
    let currentPage = 1;

    // Fetch data function
    function fetchAppointmentData(page = 1) {
        const year = yearFilter.value;
        const month = monthFilter.value;

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                action: 'get_mentor_appointment_history',
                year: year,
                month: month,
                page: page,
                nonce: '<?php echo wp_create_nonce('mentor_appointment_history_nonce'); ?>'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update stat cards
                document.getElementById('hourlyRate').textContent = '$' + data.data.hourly_rate;
                document.getElementById('totalSessions').textContent = data.data.total_sessions;
                document.getElementById('totalHours').textContent = data.data.total_hours + ' hrs';
                document.getElementById('totalEarnings').textContent = '$' + data.data.total_earnings;

                // Update table
                appointmentTableBody.innerHTML = '';
                if (data.data.appointments.length === 0) {
                    appointmentTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No appointments found.</td></tr>';
                } else {
                    data.data.appointments.forEach((appointment, index) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${(page - 1) * 10 + index + 1}</td>
                            <td>${appointment.title}</td>
                            <td>${appointment.attende_name}</td>
                            <td>${appointment.date_time}</td>
                            <td>${appointment.duration} mins</td>
                            <td>$${appointment.earnings}</td>
                            <td><span class="badge ${appointment.status === 'finished' ? 'bg-success' : appointment.status === 'approved' ? 'bg-info' : 'bg-danger'} text-light">${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}</span></td>
                            <td><a href="${appointment.view_url}" class="btn-action btn-info">View Details</a></td>
                        `;
                        appointmentTableBody.appendChild(row);
                    });
                }

                // Update pagination
                pagination.innerHTML = '';
                if (data.data.total_pages > 1) {
                    if (page > 1) {
                        pagination.innerHTML += `<a href="#" data-page="${page - 1}">&laquo;</a>`;
                    }
                    for (let i = 1; i <= data.data.total_pages; i++) {
                        pagination.innerHTML += `<a href="#" data-page="${i}" class="${i === page ? 'active' : ''}">${i}</a>`;
                    }
                    if (page < data.data.total_pages) {
                        pagination.innerHTML += `<a href="#" data-page="${page + 1}">&raquo;</a>`;
                    }
                }
            } else {
                showNotification('Failed to load data: ' + (data.data?.message || 'Unknown error'), 'danger');
                appointmentTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Error loading appointments.</td></tr>';
                pagination.innerHTML = '';
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            showNotification('An error occurred while loading data.', 'danger');
            appointmentTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Error loading appointments.</td></tr>';
            pagination.innerHTML = '';
        });
    }

    // Handle filter changes
    yearFilter.addEventListener('change', function() {
        currentPage = 1;
        fetchAppointmentData();
    });
    monthFilter.addEventListener('change', function() {
        currentPage = 1;
        fetchAppointmentData();
    });

    // Handle pagination clicks
    pagination.addEventListener('click', function(e) {
        if (e.target.tagName === 'A') {
            e.preventDefault();
            currentPage = parseInt(e.target.dataset.page);
            fetchAppointmentData(currentPage);
        }
    });

    // Initial data fetch
    fetchAppointmentData();

    // Notification function
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

<?php get_footer(); ?>