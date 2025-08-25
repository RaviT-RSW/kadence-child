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
 * - Monthly appointments graph with status breakdown
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

    // Prepare monthly appointment data for the chart
    $monthly_appointments = array(
        'total' => array_fill(1, 12, 0),
        'pending' => array_fill(1, 12, 0),
        'approved' => array_fill(1, 12, 0),
        'cancelled' => array_fill(1, 12, 0),
        'finished' => array_fill(1, 12, 0)
    );
    $current_year = date('Y'); // 2025
    foreach ($metrics['sessions'] as $session) {
        $session_month = (int)$session['date_time']->format('m'); // 1-12
        if ($session['date_time']->format('Y') === $current_year) {
            $monthly_appointments['total'][$session_month]++;
            $status = $session['appointment_status'];
            if (isset($monthly_appointments[$status])) {
                $monthly_appointments[$status][$session_month]++;
            }
        }
    }
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

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
        

         <div class="dashboard-stats-container">
            <div class="dashboard-stat-card">
                <div class="stat-content">
                    <div> Sessions by status </div>
                    <div>
                        <?= do_shortcode('[appointment_status_pie_chart session_status_counts=\''.json_encode($metrics['session_status_counts']).'\']');?>

                    </div>
                </div>
            </div>

            <div class="dashboard-stat-card">
                <div class="stat-icon mentor">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="stat-content">
                    <h3><?= wc_price($metrics['total_earnings']) ?></h3>
                    <p> Total Earnings </p>
                </div>
            </div>
        </div>
        <!-- Monthly Appointments Graph -->
        <div class="dashboard-graph-container">
            <h2>Monthly Appointments by Status (<?php echo $current_year; ?>)</h2>
            <canvas id="monthlyAppointmentsChart"></canvas>
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

            // Monthly Appointments Chart
            var ctx = document.getElementById('monthlyAppointmentsChart').getContext('2d');
            var chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [
                        {
                            label: 'Total Booked',
                            data: <?php echo json_encode(array_values($monthly_appointments['total'])); ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)', // Blue
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            hoverBackgroundColor: 'rgba(54, 162, 235, 0.8)',
                        },
                        {
                            label: 'Pending',
                            data: <?php echo json_encode(array_values($monthly_appointments['pending'])); ?>,
                            backgroundColor: 'rgba(241, 196, 15, 0.6)', // Yellow
                            borderColor: 'rgba(241, 196, 15, 1)',
                            borderWidth: 1,
                            hoverBackgroundColor: 'rgba(241, 196, 15, 0.8)',
                        },
                        {
                            label: 'Approved',
                            data: <?php echo json_encode(array_values($monthly_appointments['approved'])); ?>,
                            backgroundColor: 'rgba(46, 204, 113, 0.6)', // Green
                            borderColor: 'rgba(46, 204, 113, 1)',
                            borderWidth: 1,
                            hoverBackgroundColor: 'rgba(46, 204, 113, 0.8)',
                        },
                        {
                            label: 'Cancelled',
                            data: <?php echo json_encode(array_values($monthly_appointments['cancelled'])); ?>,
                            backgroundColor: 'rgba(231, 76, 60, 0.6)', // Red
                            borderColor: 'rgba(231, 76, 60, 1)',
                            borderWidth: 1,
                            hoverBackgroundColor: 'rgba(231, 76, 60, 0.8)',
                        },
                        {
                            label: 'Finished',
                            data: <?php echo json_encode(array_values($monthly_appointments['finished'])); ?>,
                            backgroundColor: 'rgba(155, 89, 182, 0.6)', // Purple
                            borderColor: 'rgba(155, 89, 182, 1)',
                            borderWidth: 1,
                            hoverBackgroundColor: 'rgba(155, 89, 182, 0.8)',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    size: 14
                                },
                                generateLabels: function(chart) {
                                    var data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.datasets.map(function(dataset, i) {
                                            var meta = chart.getDatasetMeta(i);
                                            var style = {
                                                text: dataset.label,
                                                fillStyle: dataset.backgroundColor,
                                                strokeStyle: dataset.borderColor,
                                                lineWidth: dataset.borderWidth,
                                                hidden: isNaN(dataset.data[0]) || meta.hidden,
                                                index: i
                                            };
                                            return {
                                                text: style.text,
                                                fillStyle: style.fillStyle,
                                                strokeStyle: style.strokeStyle,
                                                lineWidth: style.lineWidth,
                                                hidden: style.hidden,
                                                datasetIndex: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            },
                            onClick: function(e, legendItem) {
                                var index = legendItem.datasetIndex;
                                var ci = chart;
                                var meta = ci.getDatasetMeta(index);
                                meta.hidden = meta.hidden === null ? !ci.data.datasets[index].hidden : null;
                                ci.update();
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Months'
                            },
                            ticks: {
                                font: {
                                    size: 12
                                }
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Appointments'
                            },
                            ticks: {
                                stepSize: 1,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>

    <style>
        .dashboard-graph-container {
            margin-bottom: 20px;
        }
        #monthlyAppointmentsChart {
            max-height: 400px;
            margin-top: 10px;
        }
        .dashboard-graph-container {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            color: inherit;
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
        'session_status_counts' => $session_data['session_status_counts'],
        'total_earnings' => $session_data['total_earnings'],
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
    $session_status_counts = array();
    $total_earnings = 0;
    $total_sessions = 0;

    foreach ($orders as $order)
    {
        $total_earnings += $order->get_total();

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

                if (!isset($session_status_counts[$appointment_status])) {
                    $session_status_counts[$appointment_status] = 0; // initialize
                }
                $session_status_counts[$appointment_status]++;

                // Increment total sessions count
                $total_sessions++;
            }
        }
    }

    return array(
        'total_sessions' => $total_sessions,
        'sessions' => $sessions,
        'session_status_counts' => $session_status_counts,
        'total_earnings' => $total_earnings,
    );
}


add_shortcode('appointment_status_pie_chart', 'appointment_status_pie_chart_shortcode');
function appointment_status_pie_chart_shortcode($atts)
{

    // Define defaults
    $default_counts = array(
        'approved'  => 0,
        'pending'   => 0,
        'cancelled' => 0,
        'finished'  => 0,
    );

    $session_status_counts = json_decode($atts['session_status_counts'], true);

    // Merge with defaults → missing keys will be filled with defaults
    $session_status_counts = array_merge($default_counts, (array) $session_status_counts);

    ob_start(); 

    ?>
    
    <canvas id="myPieChart" width="220"></canvas>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var ctx = document.getElementById('myPieChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Approved', 'Pending', 'Finished', 'Cancelled'],
                datasets: [{
                    data: [ 
                        <?= $session_status_counts['approved'] ?>,
                        <?= $session_status_counts['pending'] ?>,
                        <?= $session_status_counts['finished'] ?>,
                        <?= $session_status_counts['cancelled'] ?>,
                     ], // Values for each label
                    backgroundColor: [
                        '#2ECC71',
                        '#F1C40F',
                        '#3498DB',
                        '#E74C3C',
                    ],
                    borderColor: [
                        '#2ECC71',
                        '#F1C40F',
                        '#3498DB',
                        '#E74C3C',
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });
    });
    </script>
    
    <?php
    return ob_get_clean();
}

