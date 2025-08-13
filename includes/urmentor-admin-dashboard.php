<?php
/**
 * UrMentor Admin Dashboard
 *
 * Provides a comprehensive dashboard overview with key metrics:
 * - Total sessions
 * - Active mentors and mentees
 * - Bookings overview
 * - Payment volume
 * - Calendar view
 * 
 * Features:
 * - Real-time statistics
 * - Visual charts and graphs
 * - Quick action buttons
 * - Recent activities
 * - Common calendar for all sessions
 */

// Add admin menu
add_action('admin_menu', 'urmentor_add_dashboard_menu');
function urmentor_add_dashboard_menu() {
    add_menu_page(
        'UrMentor Dashboard',
        'UrMentor Dashboard',
        'manage_options',
        'urmentor-dashboard',
        'urmentor_dashboard_page',
        'dashicons-chart-area',
        2 // Position it high in the menu
    );
}

/**
 * Main Dashboard Page
 */
function urmentor_dashboard_page() {
    // Get dashboard metrics
    $metrics = urmentor_get_dashboard_metrics();
    
    // Prepare session data for FullCalendar
    $calendar_events = array();
    foreach ($metrics['sessions'] as $session) {
        $calendar_events[] = array(
            'title' => esc_js($session['child_name'] . ' with ' . $session['mentor_name']),
            'start' => $session['date_time']->format('Y-m-d\TH:i:s'),
            'extendedProps' => array(
                'mentor_name' => esc_js($session['mentor_name']),
                'child_name' => esc_js($session['child_name']),
                'appointment_status' => esc_js($session['appointment_status']),
                'zoom_link' => esc_url($session['zoom_link']),
                'order_id' => $session['order_id'],
                'item_id' => $session['item_id'],
            ),
            'backgroundColor' => $session['appointment_status'] === 'completed' ? '#00a32a' : ($session['appointment_status'] === 'cancelled' ? '#d63638' : '#0073aa'),
            'borderColor' => $session['appointment_status'] === 'completed' ? '#00a32a' : ($session['appointment_status'] === 'cancelled' ? '#d63638' : '#0073aa'),
        );
    }
    ?>
    <div class="wrap urmentor-dashboard">
        <h1>UrMentor Dashboard</h1>
        
        <!-- Dashboard Statistics Cards -->
        <div class="dashboard-stats-container">
            <div class="dashboard-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-video-alt3"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($metrics['total_sessions']); ?></h3>
                    <p>Total Sessions</p>
                </div>
            </div>
            
            <a href="<?php echo admin_url('admin.php?page=urmentor-manage-users&filter=mentors'); ?>" class="dashboard-stat-card">
                <div class="stat-icon mentor">
                    <span class="dashicons dashicons-businessperson"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($metrics['active_mentors']); ?></h3>
                    <p>Active Mentors</p>
                </div>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=urmentor-manage-users&filter=children'); ?>" class="dashboard-stat-card">
                <div class="stat-icon mentee">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($metrics['active_mentees']); ?></h3>
                    <p>Active Mentees</p>
                </div>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=urmentor-manage-users&filter=parents'); ?>" class="dashboard-stat-card">
                <div class="stat-icon parent">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($metrics['active_parents']); ?></h3>
                    <p>Active Parents</p>
                </div>
            </a>
        </div>
        
        <!-- Quick Actions -->
        <div class="dashboard-quick-actions">
            <h2>Quick Actions</h2>
            <div class="quick-action-buttons">
                <a href="<?php echo admin_url('admin.php?page=urmentor-add-user'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    Add New User
                </a>
                <a href="<?php echo admin_url('admin.php?page=urmentor-manage-users'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-groups"></span>
                    Manage Users
                </a>
                <a href="<?php echo admin_url('admin.php?page=urmentor-child-mentor-assignments'); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-networking"></span>
                    Manage Assignments
                </a>
            </div>
        </div>

        <!-- Calendar Section -->
        <div class="calendar-section">
            <h2>Sessions Calendar</h2>
            <div id="urmentor-calendar"></div>
        </div>
    </div>

    <!-- FullCalendar CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('urmentor-calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: <?php echo json_encode($calendar_events); ?>,
                eventDidMount: function(info) {
                    // Add tooltip with session details
                    var tooltipContent = `
                        <strong>Mentee:</strong> ${info.event.extendedProps.child_name}<br>
                        <strong>Mentor:</strong> ${info.event.extendedProps.mentor_name}<br>
                        <strong>Status:</strong> ${info.event.extendedProps.appointment_status}<br>
                        <strong>Order ID:</strong> ${info.event.extendedProps.order_id}<br>
                        ${info.event.extendedProps.zoom_link ? '<a href="' + info.event.extendedProps.zoom_link + '" target="_blank">Join Zoom</a>' : ''}
                    `;
                    tippy(info.el, {
                        content: tooltipContent,
                        allowHTML: true,
                        theme: 'light-border',
                    });
                },
                height: '700px',
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                }
            });
            calendar.render();
        });
    </script>

    <!-- Tippy.js for tooltips -->
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>

    <style>
    .urmentor-dashboard {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    /* Dashboard Statistics Cards */
    .dashboard-stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin: 20px 0 30px;
    }

    .dashboard-stat-card {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        display: flex;
        align-items: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        text-decoration: none;
        color: inherit;
    }

    .dashboard-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .stat-icon {
        font-size: 40px;
        margin-right: 15px;
        padding: 15px;
        border-radius: 50%;
        background: #0073aa;
        color: white;
    }

    .stat-icon.mentor { background: #00a32a; }
    .stat-icon.parent { background: #f56500; }
    .stat-icon.mentee { background: #d63638; }
    .stat-icon.booking { background: #ff8c00; }
    .stat-icon.payment { background: #7c3aed; }
    .stat-icon.revenue { background: #059669; }

    .stat-content h3 {
        font-size: 28px;
        font-weight: bold;
        margin: 0;
        color: #1d2327;
    }

    .stat-content p {
        margin: 5px 0;
        color: #646970;
        font-weight: 500;
    }

    .stat-change {
        font-size: 12px;
        padding: 2px 6px;
        border-radius: 12px;
        font-weight: 500;
    }

    .stat-change.positive {
        background: #d1e7dd;
        color: #0f5132;
    }

    .stat-change.negative {
        background: #f8d7da;
        color: #842029;
    }

    .stat-change.neutral {
        background: #e2e3e5;
        color: #41464b;
    }

    /* Quick Actions */
    .dashboard-quick-actions {
        margin: 30px 0;
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    .quick-action-buttons {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .quick-action-buttons .button {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
    }

    /* Calendar Section */
    .calendar-section {
        margin: 30px 0;
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    #urmentor-calendar {
        max-width: 100%;
        margin-top: 20px;
    }

    /* FullCalendar Custom Styles */
    .fc {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }

    .fc .fc-button {
        background: #0073aa;
        border: none;
        color: white;
        border-radius: 4px;
        padding: 6px 12px;
    }

    .fc .fc-button:hover {
        background: #005f87;
    }

    .fc .fc-button.fc-button-primary {
        background: #0073aa;
    }

    .fc .fc-button.fc-button-primary:hover {
        background: #005f87;
    }

    .fc .fc-toolbar-title {
        font-size: 1.5em;
        color: #1d2327;
    }

    .fc .fc-daygrid-day-number {
        color: #1d2327;
    }

    .fc .fc-daygrid-day.fc-day-today {
        background-color: #e6f3fa;
    }

    .fc .fc-event {
        border-radius: 4px;
        font-size: 0.9em;
        padding: 2px 4px;
    }

    /* Tooltip Styles */
    .tippy-box[data-theme~='light-border'] {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        color: #1d2327;
        font-size: 14px;
    }

    .tippy-box[data-theme~='light-border'] .tippy-content {
        padding: 10px;
    }

    @media (max-width: 1200px) {
        .dashboard-content-grid {
            grid-template-columns: 1fr;
        }
        
        .chart-section,
        .sessions-section {
            grid-column: 1 / 2;
        }
    }

    @media (max-width: 768px) {
        .dashboard-stats-container {
            grid-template-columns: 1fr;
        }

        .quick-action-buttons {
            flex-direction: column;
        }

        .fc .fc-toolbar {
            flex-direction: column;
            gap: 10px;
        }
    }
    </style>
    <?php
}

/**
 * Get Dashboard Metrics
 */
function urmentor_get_dashboard_metrics() {
    global $wpdb;
    
    // Get active mentors, mentees, and parents from users
    $active_mentors = count(get_users(array('role' => 'mentor_user')));
    $active_mentees = count(get_users(array('role' => 'child_user')));
    $active_parents = count(get_users(array('role' => 'parent_user')));
    
    // Get total sessions from WooCommerce orders
    $session_data = urmentor_get_total_sessions();
    
    return array(
        'total_sessions' => $session_data['total_sessions'],
        'active_mentors' => $active_mentors,
        'active_mentees' => $active_mentees,
        'active_parents' => $active_parents,
        'sessions' => $session_data['sessions'], // Include session details for calendar
    );
}

/**
 * Get Total Sessions for All Users
 */
function urmentor_get_total_sessions() {
    global $wpdb;

    // Fetch all WooCommerce orders with status 'completed', 'processing', or 'on-hold'
    $args = array(
        'status' => array('wc-completed', 'wc-processing', 'wc-on-hold'),
        'limit' => -1, // Retrieve all orders
    );
    $orders = wc_get_orders($args);

    $sessions = array();
    $total_sessions = 0;

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item_id => $item) {
            // Retrieve session-related metadata
            $mentor_id = $item->get_meta('mentor_id');
            $child_id = $item->get_meta('child_id');
            $session_date_time = $item->get_meta('session_date_time');
            $appointment_status = $item->get_meta('appointment_status') ?: 'N/A';
            $location = $item->get_meta('location') ?: 'online';
            $zoom_meeting = $item->get_meta('zoom_meeting') ?: '';
            $zoom_link = '';

            // Generate Zoom link if applicable
            if ($location === 'online' && !empty($zoom_meeting) && class_exists('Zoom')) {
                $zoom = new Zoom();
                $zoom_link = $zoom->getMeetingUrl($zoom_meeting, 'start_url');
            }

            // Ensure required metadata exists
            if ($mentor_id && $child_id && $session_date_time) {
                $mentor = get_user_by('id', $mentor_id);
                $child = get_user_by('id', $child_id);

                // Add session details to the array
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

                // Increment total sessions count
                $total_sessions++;
            }
        }
    }

    return array(
        'total_sessions' => $total_sessions,
        'sessions' => $sessions,
    );
}
?>