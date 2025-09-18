<?php
/**
 * User and Mentor Management (Admin Interface) - Enhanced with Profile Picture Support
 *
 * Manages users and child-mentor assignments in a single admin menu.
 * 
 * Features:
 * - Manage Users: List, filter, add, edit, delete users
 * - Profile Picture Upload/Edit functionality
 * - Child-Mentor assignments: Assign mentors to unassigned child users and view/search/paginate/unassign
 * - Stores mentor assignments in `assigned_mentor_id` user meta
 * - Stores parent assignments in `assigned_parent_id` user meta
 * - Stores mentor hourly rate in `mentor_hourly_rate` user meta
 * - Stores custom profile pictures in `custom_profile_picture` user meta
 * - Generate and download monthly invoices as PDF
 * - Send invoice PDF to parent's email
 * - Generate and download monthly invoices as PDF
 * - Send invoice PDF to parent's email
 *
 * Notes:
 * - For admin users only (`manage_options`)
 * - Uses `mentor_user`, `parent_user`, and `child_user` roles
 */

// Add admin menu
add_action('admin_menu', 'urmentor_add_user_management_menu');
function urmentor_add_user_management_menu() {
    add_menu_page(
        'Manage Users',
        'Manage Users',
        'manage_options',
        'urmentor-manage-users',
        'urmentor_manage_users_page',
        'dashicons-admin-users',
        4
    );
    
    add_submenu_page(
        'urmentor-manage-users',
        'Add New User',
        'Add New User',
        'manage_options',
        'urmentor-add-user',
        'urmentor_add_user_page'
    );

    add_submenu_page(
        'urmentor-manage-users',
        'Child-Mentor Assignments',
        'Child-Mentor Assignments',
        'manage_options',
        'urmentor-child-mentor-assignments',
        'urmentor_child_mentor_assignments_page'
    );

    // ✅ New submenu for See User Chat
    add_submenu_page(
        'urmentor-manage-users',
        'See User Chat',
        'See User Chat',
        'manage_options',
        'urmentor-see-user-chat',
        'urmentor_see_user_chat_page'
    );

    add_submenu_page(
        'urmentor-manage-users',
        'Monthly Invoices',
        'Monthly Invoices',
        'manage_options',
        'urmentor-monthly-invoices',
        'urmentor_monthly_invoices_page'
    );
}

// Callback function for the new submenu page
function urmentor_see_user_chat_page() {
    echo '<div class="wrap"><h1>See User Chat</h1>';
    echo do_shortcode('[user_chat_channels]');
    echo '</div>';
}


/**
 * Get User Profile Picture URL
 */
function urmentor_get_profile_picture($user_id, $size = 'thumbnail') {
    $custom_avatar_id = get_user_meta($user_id, 'custom_profile_picture', true);

    if ($custom_avatar_id) {
        $avatar_url = wp_get_attachment_image_url($custom_avatar_id, $size);
        if ($avatar_url) {
            return $avatar_url;
        }
    }

    // Fallback to Gravatar
    $user = get_userdata($user_id);
    if ($user) {
        return get_avatar_url($user->user_email, array('size' => 150));
    }

    return '';
}

/**
 * Main User Management Page
 */
function urmentor_manage_users_page() {
    // Handle user deletion
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
        if (current_user_can('delete_users') && wp_verify_nonce($_GET['_wpnonce'], 'delete-user_' . $user_id)) {
            wp_delete_user($user_id);
            echo '<div class="notice notice-success is-dismissible"><p>User deleted successfully.</p></div>';
        }
    }

    // Check if viewing mentor calendar
    if (isset($_GET['action']) && $_GET['action'] === 'view_calendar' && isset($_GET['user_id'])) {
        urmentor_mentor_calendar_page(intval($_GET['user_id']));
        return;
    }

    // Get filter parameter
    $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
    
    // Build user query based on filter
    $user_args = array('number' => -1);
    
    switch ($filter) {
        case 'mentors':
            $user_args['role'] = 'mentor_user';
            break;
        case 'parents':
            $user_args['role'] = 'parent_user';
            break;
        case 'children':
            $user_args['role'] = 'child_user';
            break;
        case 'all':
        default:
            // Get all users
            break;
    }
    
    $users = get_users($user_args);
    
    ?>
    <div class="wrap">
        <h1>Manage Users 
            <a href="<?php echo admin_url('admin.php?page=urmentor-add-user'); ?>" class="page-title-action">Add New</a>
        </h1>
        
        <!-- Filter Navigation -->
        <ul class="subsubsub">
            <li class="all">
                <a href="<?php echo admin_url('admin.php?page=urmentor-manage-users&filter=all'); ?>" 
                   class="<?php echo $filter === 'all' ? 'current' : ''; ?>">
                   All <span class="count">(<?php echo count(get_users()); ?>)</span>
                </a> |
            </li>
            <li class="mentors">
                <a href="<?php echo admin_url('admin.php?page=urmentor-manage-users&filter=mentors'); ?>" 
                   class="<?php echo $filter === 'mentors' ? 'current' : ''; ?>">
                   Mentors <span class="count">(<?php echo count(get_users(array('role' => 'mentor_user'))); ?>)</span>
                </a> |
            </li>
            <li class="parents">
                <a href="<?php echo admin_url('admin.php?page=urmentor-manage-users&filter=parents'); ?>" 
                   class="<?php echo $filter === 'parents' ? 'current' : ''; ?>">
                   Parents <span class="count">(<?php echo count(get_users(array('role' => 'parent_user'))); ?>)</span>
                </a> |
            </li>
            <li class="children">
                <a href="<?php echo admin_url('admin.php?page=urmentor-manage-users&filter=children'); ?>" 
                   class="<?php echo $filter === 'children' ? 'current' : ''; ?>">
                   Children <span class="count">(<?php echo count(get_users(array('role' => 'child_user'))); ?>)</span>
                </a>
            </li>
        </ul>
        
        <!-- Users Table -->
        <table class="wp-list-table widefat fixed striped users">
            <thead>
                <tr>
                    <th scope="col" id="username" class="manage-column column-username column-primary">Username</th>
                    <th scope="col" id="name" class="manage-column column-name">Name</th>
                    <th scope="col" id="email" class="manage-column column-email">Email</th>
                    <th scope="col" id="role" class="manage-column column-role">Role</th>
                    <th scope="col" id="parent" class="manage-column column-parent">Parent</th>
                    <th scope="col" id="mentor" class="manage-column column-mentor">Mentor</th>
                    <th scope="col" id="hourly_rate" class="manage-column column-hourly_rate">Hourly Rate</th>
                </tr>
            </thead>
            <tbody id="the-list">
                <?php if (empty($users)): ?>
                    <tr class="no-items">
                        <td class="colspanchange" colspan="7">No users found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $user_roles = $user->roles;
                        $primary_role = !empty($user_roles) ? $user_roles[0] : 'No role';
                        $assigned_parent_id = get_user_meta($user->ID, 'assigned_parent_id', true);
                        $parent_name = '';
                        if ($assigned_parent_id) {
                            $parent = get_userdata($assigned_parent_id);
                            $parent_name = $parent ? $parent->display_name : 'Unknown';
                        }
                        $assigned_mentor_id = get_user_meta($user->ID, 'assigned_mentor_id', true);
                        $mentor_name = '';
                        if ($assigned_mentor_id) {
                            $mentor = get_userdata($assigned_mentor_id);
                            $mentor_name = $mentor ? $mentor->display_name : 'Unknown';
                        }
                        $hourly_rate = get_user_meta($user->ID, 'mentor_hourly_rate', true);
                        $profile_picture_url = urmentor_get_profile_picture($user->ID);
                        ?>
                        <tr>
                            <td class="username column-username has-row-actions column-primary" data-colname="Username">
                                <img src="<?php echo esc_url($profile_picture_url); ?>" alt="<?php echo esc_attr($user->display_name); ?>" class="avatar avatar-32 photo" height="32" width="32" style="border-radius: 50%;" />
                                <strong><?php echo esc_html($user->user_login); ?></strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=urmentor-add-user&action=edit&user_id=' . $user->ID); ?>">Edit</a> | 
                                    </span>
                                    <?php if ($primary_role === 'mentor_user'): ?>
                                        <span class="view_calendar">
                                            <a href="<?php echo admin_url('admin.php?page=urmentor-manage-users&action=view_calendar&user_id=' . $user->ID); ?>">View Calendar</a> | 
                                        </span>
                                    <?php endif; ?>
                                    <?php if (current_user_can('delete_users') && $user->ID !== get_current_user_id()): ?>
                                    <span class="delete">
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=urmentor-manage-users&action=delete&user_id=' . $user->ID), 'delete-user_' . $user->ID); ?>" 
                                           onclick="return confirm('Are you sure you want to delete this user?');" class="submitdelete">Delete</a>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="name column-name" data-colname="Name">
                                <?php echo esc_html($user->display_name); ?>
                            </td>
                            <td class="email column-email" data-colname="Email">
                                <a href="mailto:<?php echo esc_attr($user->user_email); ?>">
                                    <?php echo esc_html($user->user_email); ?>
                                </a>
                            </td>
                            <td class="role column-role" data-colname="Role">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $primary_role))); ?>
                            </td>
                            <td class="parent column-parent" data-colname="Parent">
                                <?php echo esc_html($parent_name); ?>
                            </td>
                            <td class="mentor column-mentor" data-colname="Mentor">
                                <?php echo esc_html($mentor_name); ?>
                            </td>
                            <td class="hourly_rate column-hourly_rate" data-colname="Hourly Rate">
                                <?php echo $hourly_rate ? '$' . number_format($hourly_rate, 2) : '—'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Mentor Calendar Page
 */
function urmentor_mentor_calendar_page($mentor_id) {
    // Get mentor data
    $mentor = get_user_by('id', $mentor_id);
    if (!$mentor || !in_array('mentor_user', $mentor->roles)) {
        echo '<div class="notice notice-error is-dismissible"><p>Invalid mentor selected.</p></div>';
        return;
    }

    // Get sessions for this mentor
    $session_data = urmentor_get_mentor_sessions($mentor_id);
    $calendar_events = array();
    foreach ($session_data['sessions'] as $session) {
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
        <h1><?php echo esc_html($mentor->display_name); ?>'s Calendar</h1>
        <p><a href="<?php echo admin_url('admin.php?page=urmentor-manage-users&filter=mentors'); ?>" class="button button-secondary">Back to Manage Users</a></p>
        
        <!-- Calendar Section -->
        <div class="calendar-section">
            <div id="urmentor-mentor-calendar"></div>
        </div>
    </div>

    <!-- FullCalendar CSS and JS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('urmentor-mentor-calendar');
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

    .calendar-section {
        margin: 30px 0;
        background: #fff;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    #urmentor-mentor-calendar {
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

    @media (max-width: 768px) {
        .fc .fc-toolbar {
            flex-direction: column;
            gap: 10px;
        }
    }
    </style>
    <?php
}

/**
 * Get Sessions for a Specific Mentor
 */
function urmentor_get_mentor_sessions($mentor_id) {
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
            $session_mentor_id = $item->get_meta('mentor_id');
            $child_id = $item->get_meta('child_id');
            $session_date_time = $item->get_meta('session_date_time');
            $appointment_status = $item->get_meta('appointment_status') ?: 'N/A';
            $location = $item->get_meta('location') ?: 'online';
            $zoom_meeting = $item->get_meta('zoom_meeting') ?: '';
            $zoom_link = '';

            // Only include sessions for the specified mentor
            if ($session_mentor_id != $mentor_id) {
                continue;
            }

            // Generate Zoom link if applicable
            if ($location === 'online' && !empty($zoom_meeting) && class_exists('Zoom')) {
                $zoom = new Zoom();
                $zoom_link = $zoom->getMeetingUrl($zoom_meeting, 'start_url');
            }

            // Ensure required metadata exists
            if ($session_mentor_id && $child_id && $session_date_time) {
                $mentor = get_user_by('id', $session_mentor_id);
                $child = get_user_by('id', $child_id);

                // Add session details to the array
                $sessions[] = array(
                    'date_time' => new DateTime($session_date_time, new DateTimeZone('Asia/Kolkata')),
                    'mentor_name' => $mentor ? $mentor->display_name : 'Unknown Mentor',
                    'mentor_id' => $session_mentor_id,
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

/**
 * Handle Profile Picture Upload
 */
function urmentor_handle_profile_picture_upload() {
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $uploadedfile = $_FILES['profile_picture'];

    if (empty($uploadedfile['name'])) {
        return false;
    }

    // Check file type
    $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
    $file_extension = strtolower(pathinfo($uploadedfile['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_types)) {
        return new WP_Error('invalid_file_type', 'Please upload a valid image file (JPG, PNG, or GIF).');
    }

    // Handle upload
    $upload_overrides = array('test_form' => false);
    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        // Insert the attachment
        $attachment = array(
            'guid' => $movefile['url'],
            'post_mime_type' => $movefile['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($uploadedfile['name'])),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $movefile['file']);

        if (!is_wp_error($attach_id)) {
            if (!function_exists('wp_generate_attachment_metadata')) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }
            $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
            wp_update_attachment_metadata($attach_id, $attach_data);

            return $attach_id;
        }
    }

    return new WP_Error('upload_failed', 'Failed to upload profile picture.');
}

/**
 * Add/Edit User Page
 */
function urmentor_add_user_page() {
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'add';
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $user = null;
    
    if ($action === 'edit' && $user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            wp_die('User not found.');
        }
    }
    
    // Handle form submission
    if (isset($_POST['submit'])) {
        $result = urmentor_handle_user_form_submission($action, $user_id);
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error is-dismissible"><p>' . $result->get_error_message() . '</p></div>';
        } else {
            $message = $action === 'edit' ? 'User updated successfully.' : 'User created successfully.';
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
            if ($action === 'add') {
                // Redirect to list page after adding new user
                wp_redirect(admin_url('admin.php?page=urmentor-manage-users'));
                exit;
            }
        }
    }
    
    // Get current values
    $username = $user ? $user->user_login : '';
    $email = $user ? $user->user_email : '';
    $first_name = $user ? $user->first_name : '';
    $last_name = $user ? $user->last_name : '';
    $role = $user ? $user->roles[0] : '';
    $assigned_parent_id = $user ? get_user_meta($user->ID, 'assigned_parent_id', true) : '';
    $hourly_rate = $user ? get_user_meta($user->ID, 'mentor_hourly_rate', true) : '';
    $current_profile_picture = $user ? urmentor_get_profile_picture($user->ID, 'medium') : '';

    // Get all parents for dropdown
    $parents = get_users(array('role' => 'parent_user'));
    ?>

    <div class="wrap">
        <h1><?php echo $action === 'edit' ? 'Edit User' : 'Add New User'; ?></h1>

        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('urmentor_user_form', 'urmentor_user_nonce'); ?>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <!-- Profile Picture -->
                    <tr>
                        <th scope="row"><label for="profile_picture">Profile Picture</label></th>
                        <td>
                            <div id="avatar-wrapper" style="margin-bottom: 10px;">
                                <img id="profilePicPreview"
                                     src="<?php echo esc_url($current_profile_picture ?: get_avatar_url($user_id, ['size' => 150])); ?>"
                                     alt="Profile Picture"
                                     style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; cursor: pointer;" />
                                <p><small>Click image to change</small></p>
                            </div>

                            <!-- Hidden File Input -->
                            <input type="file" name="profile_picture" id="profile_picture" accept="image/*" style="display:none;">
                        </td>

                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const profilePicPreview = document.getElementById('profilePicPreview');
                            const profilePictureInput = document.getElementById('profile_picture');

                            // When clicking on the picture -> open file select
                            profilePicPreview.addEventListener('click', function() {
                                profilePictureInput.click();
                            });

                            // Preview selected image
                            profilePictureInput.addEventListener('change', function(event) {
                                const file = event.target.files[0];
                                if (file) {
                                    const reader = new FileReader();
                                    reader.onload = function(e) {
                                        profilePicPreview.src = e.target.result; // Replace old image
                                    };
                                    reader.readAsDataURL(file);
                                }
                            });
                        });
                        </script>

                    </tr>

                    <?php if ($action === 'add'): ?>
                    <tr>
                        <th scope="row"><label for="username">Username <span class="description">(required)</span></label></th>
                        <td>
                            <input name="username" type="text" id="username" value="<?php echo esc_attr($username); ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <th scope="row"><label for="email">Email <span class="description">(required)</span></label></th>
                        <td>
                            <input name="email" type="email" id="email" value="<?php echo esc_attr($email); ?>" class="regular-text" required />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="first_name">First Name</label></th>
                        <td>
                            <input name="first_name" type="text" id="first_name" value="<?php echo esc_attr($first_name); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="last_name">Last Name</label></th>
                        <td>
                            <input name="last_name" type="text" id="last_name" value="<?php echo esc_attr($last_name); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <?php if ($action === 'add'): ?>
                    <tr>
                        <th scope="row"><label for="password">Password <span class="description">(required)</span></label></th>
                        <td>
                            <input name="password" type="password" id="password" class="regular-text" required />
                            <p class="description">Minimum 6 characters</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <th scope="row"><label for="role">Role</label></th>
                        <td>
                            <select name="role" id="role">
                                <option value="child_user" <?php selected($role, 'child_user'); ?>>Child</option>
                                <option value="parent_user" <?php selected($role, 'parent_user'); ?>>Parent</option>
                                <option value="mentor_user" <?php selected($role, 'mentor_user'); ?>>Mentor</option>
                                <option value="administrator" <?php selected($role, 'administrator'); ?>>Administrator</option>
                            </select>
                        </td>
                    </tr>
                    
                    <!-- Parent Selection (for Child users) -->
                    <tr id="parent-selector-row" style="display:none;">
                        <th scope="row"><label for="child_parent_id">Assign Parent</label></th>
                        <td>
                            <select name="child_parent_id" id="child_parent_id">
                                <option value="">Select Parent</option>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?php echo esc_attr($parent->ID); ?>" 
                                            <?php selected($assigned_parent_id, $parent->ID); ?>>
                                        <?php echo esc_html($parent->display_name . ' (' . $parent->user_email . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Only applicable for Child users.</p>
                        </td>
                    </tr>
                    
                    <!-- Hourly Rate (for Mentor users) -->
                    <tr id="mentor-hourly-rate-row" style="display:none;">
                        <th scope="row"><label for="mentor_hourly_rate">Hourly Rate ($)</label></th>
                        <td>
                            <input type="number" step="0.01" min="0" name="mentor_hourly_rate" id="mentor_hourly_rate" 
                                   value="<?php echo esc_attr($hourly_rate); ?>" class="regular-text" />
                            <p class="description">Only applicable for Mentor users.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php submit_button($action === 'edit' ? 'Update User' : 'Add New User'); ?>
        </form>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('role');
        const parentRow = document.getElementById('parent-selector-row');
        const mentorRow = document.getElementById('mentor-hourly-rate-row');
        
        function toggleFields() {
            const selectedRole = roleSelect.value;
            
            // Show/hide parent selector
            if (selectedRole === 'child_user') {
                parentRow.style.display = 'table-row';
            } else {
                parentRow.style.display = 'none';
            }
            
            // Show/hide hourly rate
            if (selectedRole === 'mentor_user') {
                mentorRow.style.display = 'table-row';
            } else {
                mentorRow.style.display = 'none';
            }
        }

        roleSelect.addEventListener('change', toggleFields);
        toggleFields(); // Initialize on page load
    });
    </script>
    
    <?php
}

/**
 * Handle User Form Submission
 */
function urmentor_handle_user_form_submission($action, $user_id = 0) {
    if (!wp_verify_nonce($_POST['urmentor_user_nonce'], 'urmentor_user_form')) {
        return new WP_Error('invalid_nonce', 'Security check failed.');
    }
    
    $username = sanitize_user($_POST['username']);
    $email = sanitize_email($_POST['email']);
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $role = sanitize_text_field($_POST['role']);
    $password = $_POST['password'];
    $child_parent_id = !empty($_POST['child_parent_id']) ? intval($_POST['child_parent_id']) : '';
    $mentor_hourly_rate = !empty($_POST['mentor_hourly_rate']) ? floatval($_POST['mentor_hourly_rate']) : '';
    
    if ($action === 'add') {
        // Validate required fields
        if (empty($username) || empty($email) || empty($password)) {
            return new WP_Error('missing_fields', 'Please fill in all required fields.');
        }
        
        if (strlen($password) < 6) {
            return new WP_Error('weak_password', 'Password must be at least 6 characters long.');
        }
        
        // Create new user
        $user_data = array(
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => $role
        );
        
        $new_user_id = wp_insert_user($user_data);
        
        if (is_wp_error($new_user_id)) {
            return $new_user_id;
        }
        
        // Handle profile picture upload
        if (!empty($_FILES['profile_picture']['name'])) {
            $upload_result = urmentor_handle_profile_picture_upload();
            if (!is_wp_error($upload_result) && $upload_result) {
                update_user_meta($new_user_id, 'custom_profile_picture', $upload_result);
            }
        }

        // Save meta data
        if ($role === 'child_user' && $child_parent_id) {
            update_user_meta($new_user_id, 'assigned_parent_id', $child_parent_id);
        }
        
        if ($role === 'mentor_user' && $mentor_hourly_rate) {
            update_user_meta($new_user_id, 'mentor_hourly_rate', $mentor_hourly_rate);
        }
        
        return $new_user_id;
        
    } else { // Edit user
        if (!$user_id) {
            return new WP_Error('invalid_user', 'Invalid user ID.');
        }
        
        // Update user data
        $user_data = array(
            'ID' => $user_id,
            'user_email' => $email,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => $role
        );
        
        $result = wp_update_user($user_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Handle profile picture upload
        if (!empty($_FILES['profile_picture']['name'])) {
            $upload_result = urmentor_handle_profile_picture_upload();
            if (!is_wp_error($upload_result) && $upload_result) {
                // Delete old profile picture if exists
                $old_picture_id = get_user_meta($user_id, 'custom_profile_picture', true);
                if ($old_picture_id) {
                    wp_delete_attachment($old_picture_id, true);
                }
                update_user_meta($user_id, 'custom_profile_picture', $upload_result);
            }
        }

        // Update meta data
        if ($role === 'child_user') {
            if ($child_parent_id) {
                update_user_meta($user_id, 'assigned_parent_id', $child_parent_id);
            } else {
                delete_user_meta($user_id, 'assigned_parent_id');
            }
        } else {
            delete_user_meta($user_id, 'assigned_parent_id');
        }
        
        if ($role === 'mentor_user') {
            if ($mentor_hourly_rate) {
                update_user_meta($user_id, 'mentor_hourly_rate', $mentor_hourly_rate);
            } else {
                delete_user_meta($user_id, 'mentor_hourly_rate');
            }
        } else {
            delete_user_meta($user_id, 'mentor_hourly_rate');
        }
        
        return $user_id;
    }
}

/**
 * Mentor-Child Assignments Page
 */
function urmentor_child_mentor_assignments_page() {
    global $wpdb;
    $message = '';

    // Handle mentor assignment form submission
    if (isset($_POST['assign_mentor']) && wp_verify_nonce($_POST['mentor_nonce'], 'assign_mentor')) {
        $child_id = intval($_POST['child_user']);
        $mentor_id = intval($_POST['mentor_user']);

        // Check if child is already assigned
        $existing_mentor = get_user_meta($child_id, 'assigned_mentor_id', true);
        if ($existing_mentor) {
            $message = '<div class="notice notice-error is-dismissible"><p>This child is already assigned to a mentor.</p></div>';
        } else {
            // Update user meta for child
            update_user_meta($child_id, 'assigned_mentor_id', $mentor_id);
            create_wise_chat_user_from_code($child_id, $mentor_id);
            $message = '<div class="notice notice-success is-dismissible"><p>Mentor assigned successfully!</p></div>';
        }
    }

    // Get users for assignment form
    $child_users = get_users(array('role__in' => array('child_user'), 'fields' => array('ID', 'display_name')));
    $mentor_users = get_users(array('role__in' => array('mentor_user'), 'fields' => array('ID', 'display_name')));
    $available_children = array_filter($child_users, function($user) {
        return !get_user_meta($user->ID, 'assigned_mentor_id', true);
    });
    
    // Check for child_id in URL for pre-selection
    $preselected_child_id = isset($_GET['child_id']) ? intval($_GET['child_id']) : 0;
    $is_valid_preselect = false;
    if ($preselected_child_id) {
        // Verify the child_id is valid and has no assigned mentor
        $child_exists = array_filter($available_children, function($user) use ($preselected_child_id) {
            return $user->ID == $preselected_child_id;
        });
        $is_valid_preselect = !empty($child_exists);
    }
    // Handle assigned users list
    $per_page = 10;
    $paged = isset($_GET['paged']) ? max(0, intval($_GET['paged']) - 1) : 0;
    $offset = $paged * $per_page;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Prepare search query
    $search_query = $search ? $wpdb->prepare("AND (u.display_name LIKE %s OR u2.display_name LIKE %s)", "%$search%", "%$search%") : '';

    // Get total items
    $total_items = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->users} u
         JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'assigned_mentor_id'
         JOIN {$wpdb->users} u2 ON um.meta_value = u2.ID
         WHERE 1=1 $search_query"
    );

    // Get assigned users
    $assigned_users = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT u.ID AS child_id, u.display_name AS child_name, u2.display_name AS mentor_name, um.meta_value AS mentor_id
             FROM {$wpdb->users} u
             JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'assigned_mentor_id'
             JOIN {$wpdb->users} u2 ON um.meta_value = u2.ID
             WHERE 1=1 $search_query
             ORDER BY u.display_name ASC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        )
    );

    // Handle unassign action
    if (isset($_GET['unassign']) && wp_verify_nonce($_GET['_wpnonce'], 'unassign_mentor_' . $_GET['unassign'])) {
        $child_id = intval($_GET['unassign']);
        delete_user_meta($child_id, 'assigned_mentor_id');
        wp_redirect(add_query_arg(array('paged' => $paged + 1, 's' => $search), admin_url('admin.php?page=urmentor-child-mentor-assignments')));
        exit;
    }
    ?>
    <div class="wrap">
        <h1>Child-Mentor Assignments</h1>
        <?php echo $message; ?>
        <p id="assign-mentor-button-container" style="<?php echo $is_valid_preselect ? 'display: none;' : ''; ?>">
            <button id="assign-mentor-btn" class="button button-primary">Assign Mentor</button>
        </p>
        <div id="assign-mentor-form" style="margin-bottom: 20px; <?php echo $is_valid_preselect ? '' : 'display: none;'; ?>">
            <form method="post" action="">
                <?php wp_nonce_field('assign_mentor', 'mentor_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="child_user">Child User</label></th>
                        <td>
                            <select name="child_user" id="child_user" class="regular-text" required>
                                <option value="">Select a Child</option>
                                <?php foreach ($available_children as $user) : ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>" <?php echo ($is_valid_preselect && $user->ID == $preselected_child_id) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($user->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="mentor_user">Mentor User</label></th>
                        <td>
                            <select name="mentor_user" id="mentor_user" class="regular-text" required>
                                <option value="">Select a Mentor</option>
                                <?php foreach ($mentor_users as $user) : ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Assign Mentor', 'primary', 'assign_mentor'); ?>
            </form>
        </div>

        <form method="get" action="">
            <input type="hidden" name="page" value="urmentor-child-mentor-assignments">
            <p class="search-box">
                <label class="screen-reader-text" for="s">Search:</label>
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>">
                <?php submit_button('Search', 'button', '', false); ?>
            </p>
        </form>

        <?php if ($assigned_users) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Sr No.</th>
                        <th>Mentor</th>
                        <th>Child</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assigned_users as $index => $user) : ?>
                        <tr>
                            <td><?php echo esc_html($offset + $index + 1); ?></td>
                            <td><?php echo esc_html($user->mentor_name); ?></td>
                            <td><?php echo esc_html($user->child_name); ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(add_query_arg('unassign', $user->child_id, admin_url('admin.php?page=urmentor-child-mentor-assignments')), 'unassign_mentor_' . $user->child_id); ?>" 
                                   class="button unassign-btn" onclick="return confirm('Are you sure you want to unassign this child?');">Unassign</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $total_pages = ceil($total_items / $per_page);
            ?>
            <div class="pagination-container">
                <div class="pagination">
                    <?php
                    echo paginate_links(array(
                        'total' => $total_pages,
                        'current' => $paged + 1,
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '?paged=%#%',
                        'prev_text' => '<i class="dashicons dashicons-arrow-left-alt2"></i>',
                        'next_text' => '<i class="dashicons dashicons-arrow-right-alt2"></i>',
                        'type' => 'list',
                        'end_size' => 1,
                        'mid_size' => 2,
                    ));
                    ?>
                </div>
            </div>
        <?php else : ?>
            <div class="notice notice-info"><p>No assignments found.</p></div>
        <?php endif; ?>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const assignButtonContainer = document.getElementById('assign-mentor-button-container');
        const assignButton = document.getElementById('assign-mentor-btn');
        const assignForm = document.getElementById('assign-mentor-form');
        
        assignButton.addEventListener('click', function() {
            assignForm.style.display = 'block';
            assignButtonContainer.style.display = 'none'; // Hide the initial button when form opens
        });
    });
    </script>
    <style>
        /* Pagination and Button Styles */
        .pagination-container {
            margin-top: 20px;
            display: flex;
            justify-content: center;
        }

        .pagination {
            display: flex;
            gap: 6px;
            list-style: none;
            padding-left: 0;
            align-items: center;
        }

        .pagination li {
            display: inline;
        }

        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            background-color: #f6f7f7;
            border: 1px solid #ccd0d4;
            color: #1d2327;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }

        .pagination a:hover {
            background-color: #0073aa;
            color: #fff;
            border-color: #0073aa;
        }

        .pagination span.current {
            background-color: #0073aa;
            color: #fff;
            font-weight: bold;
            border-color: #0073aa;
        }

        .unassign-btn {
            background-color: red !important;
            color: white !important;
            border: 1px solid white !important;
            border-radius: 5px !important;
        }

        .unassign-btn:hover {
            background-color: white !important;
            color: red !important;
            border: 1px solid red !important;
        }
    </style>
    <?php
}

/**
 * Override WordPress get_avatar function for custom profile pictures
 * This filter allows custom profile pictures to be used instead of Gravatar
 */
add_filter('get_avatar', 'urmentor_custom_avatar', 10, 6);
function urmentor_custom_avatar($avatar, $id_or_email, $size, $default, $alt, $args) {
    $user = null;

    // Get user from different input types
    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', $id_or_email);
    } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
        $user = get_user_by('id', $id_or_email->user_id);
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
    }

    if (!$user) {
        return $avatar;
    }

    // Get custom profile picture
    $custom_avatar_id = get_user_meta($user->ID, 'custom_profile_picture', true);

    if ($custom_avatar_id) {
        $custom_avatar_url = wp_get_attachment_image_url($custom_avatar_id, array($size, $size));

        if ($custom_avatar_url) {
            $avatar = sprintf(
                '<img alt="%s" src="%s" class="avatar avatar-%d photo" height="%d" width="%d" />',
                esc_attr($alt),
                esc_url($custom_avatar_url),
                esc_attr($size),
                esc_attr($size),
                esc_attr($size)
            );
        }
    }

    return $avatar;
}

add_action('show_user_profile', 'urmentor_user_profile_picture_field', 5);
add_action('edit_user_profile', 'urmentor_user_profile_picture_field', 5);
add_action('user_new_form', 'urmentor_user_profile_picture_field', 5);

function urmentor_user_profile_picture_field($user) {
    $user_id = is_object($user) ? $user->ID : 0;
    $profile_picture_id = $user_id ? get_user_meta($user_id, 'custom_profile_picture', true) : '';
    $profile_picture_url = $profile_picture_id ? wp_get_attachment_url($profile_picture_id) : '';
    ?>
    <div id="urmentor-profile-picture-section" style="display: none;margin-top: -35px;">
        <!-- <h3>Profile Picture</h3> -->
        <table class="form-table">
            <tr>
                <th></th>
                <td>
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" >
                </td>
            </tr>
        </table>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Profile picture functionality
        const img = document.getElementById("profilePicPreview");
        const fileInput = document.getElementById("profile_picture");
        if(img && fileInput){
            img.addEventListener("click", () => fileInput.click());
            fileInput.addEventListener("change", function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        img.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
        }

        // Positioning logic - move before Account Management
        setTimeout(function() {
            const profileSection = document.getElementById('urmentor-profile-picture-section');
            if (profileSection) {
                let targetElement = null;

                // Look for Account Management section
                const allHeaders = document.querySelectorAll('h1, h2, h3');
                for (let i = 0; i < allHeaders.length; i++) {
                    const headerText = allHeaders[i].textContent.toLowerCase().trim();
                    if (headerText.includes('account management') ||
                        headerText.includes('new password') ||
                        headerText.includes('change password')) {
                        targetElement = allHeaders[i];
                        break;
                    }
                }

                // Fallback: look for password fields
                if (!targetElement) {
                    const passwordInputs = document.querySelectorAll('input[type="password"], #pass1, #pass2, .user-pass1-wrap, .user-pass-wrap');
                    if (passwordInputs.length > 0) {
                        // Find the table containing the password field
                        const passwordTable = passwordInputs[0].closest('table');
                        if (passwordTable) {
                            // Look for the header before this table
                            let prevElement = passwordTable.previousElementSibling;
                            while (prevElement) {
                                if (prevElement.tagName && (prevElement.tagName === 'H2' || prevElement.tagName === 'H3')) {
                                    targetElement = prevElement;
                                    break;
                                }
                                prevElement = prevElement.previousElementSibling;
                            }
                        }
                    }
                }

                // Move the profile section before the target element
                if (targetElement && targetElement.parentNode) {
                    targetElement.parentNode.insertBefore(profileSection, targetElement);
                    profileSection.style.display = 'block';
                } else {
                    // Fallback: show it where it is
                    profileSection.style.display = 'block';
                }
            }
        }, 100); // Small delay to ensure all elements are loaded
    });
    </script>
    <?php
}

/**
 * Ensure form supports file upload
 */
add_action('user_edit_form_tag', function() {
    echo ' enctype="multipart/form-data"';
});
add_action('user_new_form_tag', function() {
    echo ' enctype="multipart/form-data"';
});

/**
 * Save profile picture
 */
add_action('personal_options_update', 'urmentor_save_profile_picture');
add_action('edit_user_profile_update', 'urmentor_save_profile_picture');
add_action('user_register', 'urmentor_save_profile_picture');

function urmentor_save_profile_picture($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }
    if (!empty($_FILES['profile_picture']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $attachment_id = media_handle_upload('profile_picture', 0);
        if (!is_wp_error($attachment_id)) {
            // remove old pic
            $old = get_user_meta($user_id, 'custom_profile_picture', true);
            if ($old && $old != $attachment_id) {
                wp_delete_attachment($old, true);
            }
            update_user_meta($user_id, 'custom_profile_picture', $attachment_id);
        }
    }
}
/**
 * Send Invoice Email with PDF Attachment and Payment Link
 * Updated to use email template with build_email_body function and update child order statuses
 */
function urmentor_send_invoice_email($parent_id, $year, $month, $appointments, $master_order_id = null) {
    $parent = get_user_by('id', $parent_id);
    if (!$parent) {
        return new WP_Error('invalid_parent', 'Invalid parent user.');
    }

    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    $parent_name = !empty($parent->display_name) ? $parent->display_name : ($parent->user_nicename ?: $parent->user_login);
    $filename = sanitize_file_name($parent_name) . '_' . strtolower($month_name) . '_' . $year . '.pdf';

    // Generate PDF
    $tcpdf_path = ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php';
    if (!file_exists($tcpdf_path)) {
        return new WP_Error('tcpdf_missing', 'TCPDF library not found.');
    }

    require_once $tcpdf_path;
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('UrMentor');
    $pdf->SetAuthor('UrMentor');
    $pdf->SetTitle('Invoice_' . $parent_name . '_' . $month_name . '_' . $year);
    $pdf->SetMargins(15, 20, 15);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10, '', true);

    // Generate HTML for PDF using the fixed function
    $html = urmentor_generate_pdf_html($parent_id, $year, $month, $appointments);
    $pdf->writeHTML($html, true, false, true, false, '');

    // Save PDF to temporary file
    $temp_dir = sys_get_temp_dir();
    $pdf_path = $temp_dir . '/' . $filename;
    $pdf->Output($pdf_path, 'F');

    // Get payment link and order details
    $payment_section = '';
    if ($master_order_id) {
        $master_order = wc_get_order($master_order_id);
        if ($master_order) {
            $payment_link = $master_order->get_checkout_payment_url();
            $order_total = $master_order->get_formatted_order_total();
            $payment_section = '
                <div class="payment-section">
                    <h3>Invoice Details</h3>
                    <p><strong>Total Amount:</strong> ' . $order_total . '</p>
                    <a href="' . esc_url($payment_link) . '" class="payment-link">Pay Now</a>
                    <p><small>You can pay securely using your preferred payment method.</small></p>
                </div>';

            // Hook to update child order statuses when master order is paid
            add_action('woocommerce_order_status_changed', function($order_id, $old_status, $new_status) use ($master_order_id, $appointments) {
                if ($order_id == $master_order_id && $new_status == 'processing') { // Assuming 'processing' means paid
                    foreach ($appointments['appointments'] as $appt) {
                        $child_order = wc_get_order($appt['order_id']);
                        if ($child_order && $child_order->get_status() !== 'completed') { // Avoid overriding completed statuses
                            $child_order->update_status('processing', 'Updated due to master order payment.');
                        }
                    }
                }
            }, 10, 3);
        }
    }

    // Prepare email replacements
    $replacements = [
        'parent_name' => esc_html($parent_name),
        'month_name' => esc_html($month_name),
        'year' => $year,
        'payment_section' => $payment_section
    ];

    // Build email body using template
    $body = build_email_body('invoice-email-template.html', $replacements);
    if (empty($body)) {
        return new WP_Error('template_failed', 'Failed to load email template.');
    }

    // Prepare email
    $to = $parent->user_email;
    $subject = 'UrMentor Monthly Invoice - ' . $month_name . ' ' . $year;
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: UrMentor <admin@urmentor.com>'
    );
    $attachments = array($pdf_path);

    // Send email
    $result = wp_mail($to, $subject, $body, $headers, $attachments);

    // Clean up temporary file
    if (file_exists($pdf_path)) {
        unlink($pdf_path);
    }

    return $result ? true : new WP_Error('email_failed', 'Failed to send invoice email.');
}

/**
 * Get Monthly Appointments for a Parent's Children
 * Updated to exclude a specific order ID (e.g., the master order) to prevent duplicates
 */
function urmentor_get_parent_monthly_appointments($parent_id, $year, $month, $exclude_order_id = null) {
    $children_ids = get_users(array(
        'role' => 'child_user',
        'meta_key' => 'assigned_parent_id',
        'meta_value' => $parent_id,
        'fields' => 'ID'
    ));
    $start_date = new DateTime("$year-$month-01 00:00:00", new DateTimeZone('Asia/Kolkata'));
    $end_date = clone $start_date;
    $end_date->modify('last day of this month')->setTime(23, 59, 59);

    $args = array(
        'status' => array('wc-processing', 'wc-on-hold', 'wc-pending'),
        'limit' => -1,
    );
    
    // Exclude the master order if provided
    if ($exclude_order_id) {
        $args['exclude'] = array($exclude_order_id);
    }

    $orders = wc_get_orders($args);

    $appointments = array();
    $total = 0;

    foreach ($orders as $order) {
        foreach ($order->get_items() as $item_id => $item) {
            $child_id = $item->get_meta('child_id');
            $session_date_time_str = $item->get_meta('session_date_time');
            $appointment_status = $item->get_meta('appointment_status');

            // Skip if child_id is invalid, session_date_time is empty, or appointment_status is 'cancelled'
            if (!in_array($child_id, $children_ids) || empty($session_date_time_str) || $appointment_status === 'cancelled') {
                continue;
            }

            $session_date_time = new DateTime($session_date_time_str, new DateTimeZone('Asia/Kolkata'));

            if ($session_date_time >= $start_date && $session_date_time <= $end_date) {
                $mentor_id = $item->get_meta('mentor_id');
                $mentor = get_user_by('id', $mentor_id);
                $child = get_user_by('id', $child_id);
                $price = $item->get_total();

                $appointments[] = array(
                    'date_time' => $session_date_time->format('Y-m-d H:i:s'),
                    'mentor_name' => $mentor ? $mentor->display_name : 'Unknown',
                    'child_name' => $child ? $child->display_name : 'Unknown',
                    'status' => $appointment_status ?: 'N/A',
                    'price' => $price,
                    'order_id' => $order->get_id(),
                    'item_id' => $item_id,
                );

                $total += $price;
            }
        }
    }

    return array(
        'appointments' => $appointments,
        'total' => $total,
    );
}

/**
 * Updated Monthly Invoices Page Handler - Modified to exclude master order when fetching appointments
 */
function urmentor_monthly_invoices_page() {
    $message = '';
    $invoice_html = '';
    $current_year = date('Y');
    $current_month = date('n');
    $years = range($current_year - 5, $current_year + 1);
    $months = array(
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    );
    $parents = get_users(array('role' => 'parent_user'));

    // Preselect values
    $selected_year = isset($_GET['invoice_year']) ? intval($_GET['invoice_year']) : $current_year;
    $selected_month = isset($_GET['invoice_month']) ? intval($_GET['invoice_month']) : $current_month;
    $selected_parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : (!empty($parents) ? $parents[0]->ID : 0);
    $master_order_id = null; // Store master order ID for email

    ob_start(); // Start output buffering

    // Handle form submission for generating invoice
    if (isset($_POST['generate_invoice']) && wp_verify_nonce($_POST['invoice_nonce'], 'generate_invoice')) {
        $selected_year = intval($_POST['invoice_year']);
        $selected_month = intval($_POST['invoice_month']);
        $selected_parent_id = intval($_POST['parent_id']);
        
        $children = get_users(array(
            'role' => 'child_user',
            'meta_key' => 'assigned_parent_id',
            'meta_value' => $selected_parent_id,
            'fields' => 'ID'
        ));
        
        if (empty($children)) {
            $message = '<div class="notice notice-error is-dismissible"><p>No children assigned to this parent.</p></div>';
        } else {
            // Check for existing master order first to determine exclusion
            $existing_order = urmentor_get_existing_master_order($selected_parent_id, $selected_year, $selected_month);
            $exclude_order_id = $existing_order ? $existing_order->get_id() : null;
            
            $appointments = urmentor_get_parent_monthly_appointments($selected_parent_id, $selected_year, $selected_month, $exclude_order_id);
            
            if (empty($appointments['appointments'])) {
                $message = '<div class="notice notice-info is-dismissible"><p>No appointments found for this month.</p></div>';
            } else {
                
                if ($existing_order) {
                    $master_order_id = urmentor_update_master_order($existing_order, $selected_parent_id, $selected_year, $selected_month, $appointments);
                    $message = '<div class="notice notice-success is-dismissible"><p>Master order updated successfully! Order ID: ' . $master_order_id . '</p></div>';
                } else {
                    $master_order_id = urmentor_create_master_order($selected_parent_id, $selected_year, $selected_month, $appointments);
                    if (is_wp_error($master_order_id)) {
                        $message = '<div class="notice notice-error is-dismissible"><p>Error creating master order: ' . $master_order_id->get_error_message() . '</p></div>';
                        $master_order_id = null;
                    } else {
                        $message = '<div class="notice notice-success is-dismissible"><p>Master order created successfully! Order ID: ' . $master_order_id . '</p></div>';
                    }
                }

                if ($master_order_id) {
                    $invoice_html = urmentor_generate_invoice_html($selected_parent_id, $selected_year, $selected_month, $appointments);
                }
            }
        }
    }

    // Handle PDF download with improved generation
    if (isset($_POST['download_invoice']) && wp_verify_nonce($_POST['download_nonce'], 'download_invoice')) {
        $selected_year = intval($_POST['invoice_year']);
        $selected_month = intval($_POST['invoice_month']);
        $selected_parent_id = intval($_POST['parent_id']);
    
        // Check for existing master order to exclude
        $existing_order = urmentor_get_existing_master_order($selected_parent_id, $selected_year, $selected_month);
        $exclude_order_id = $existing_order ? $existing_order->get_id() : null;
    
        $appointments = urmentor_get_parent_monthly_appointments($selected_parent_id, $selected_year, $selected_month, $exclude_order_id);
    
        if (empty($appointments['appointments'])) {
            $message = '<div class="notice notice-error is-dismissible"><p>No appointments found to generate PDF.</p></div>';
        } else {
            $tcpdf_path = ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php';
            if (file_exists($tcpdf_path)) {
                require_once $tcpdf_path;
    
                // Get parent data
                $parent = get_user_by('id', $selected_parent_id);
                $month_name = date('F', mktime(0, 0, 0, $selected_month, 1));
                $parent_name = !empty($parent->display_name) ? $parent->display_name : ($parent->user_nicename ?: $parent->user_login);
                $filename = sanitize_file_name($parent_name) . '_' . strtolower($month_name) . '_' . $selected_year . '.pdf';
    
                // Clear output buffers
                ob_clean();
                @ob_end_clean();
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
    
                // Create PDF with better settings
                $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
                $pdf->SetCreator('UrMentor');
                $pdf->SetAuthor('UrMentor');
                $pdf->SetTitle('Invoice_' . $parent_name . '_' . $month_name . '_' . $selected_year);
                $pdf->SetMargins(15, 20, 15);
                $pdf->SetAutoPageBreak(TRUE, 20);
                $pdf->AddPage();
                
                // Use a better font for currency support
                $pdf->SetFont('helvetica', '', 10, '', true);
    
                // Generate HTML with proper styling for PDF
                $html = urmentor_generate_pdf_html($selected_parent_id, $selected_year, $selected_month, $appointments);
    
                $pdf->writeHTML($html, true, false, true, false, '');
    
                // Set headers
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Cache-Control: private, max-age=0, must-revalidate');
                header('Pragma: public');
    
                $pdf->Output($filename, 'D');
                exit;
            } else {
                $message = '<div class="notice notice-error is-dismissible"><p>PDF generation library (TCPDF) is not installed.</p></div>';
            }
        }
    }

    // Handle Send Invoice Email (updated to include master order ID and exclude for appointments)
    if (isset($_POST['send_invoice']) && wp_verify_nonce($_POST['send_nonce'], 'send_invoice')) {
        $selected_year = intval($_POST['invoice_year']);
        $selected_month = intval($_POST['invoice_month']);
        $selected_parent_id = intval($_POST['parent_id']);
    
        // Check for existing master order first
        $existing_order = urmentor_get_existing_master_order($selected_parent_id, $selected_year, $selected_month);
        $exclude_order_id = $existing_order ? $existing_order->get_id() : null;
    
        $appointments = urmentor_get_parent_monthly_appointments($selected_parent_id, $selected_year, $selected_month, $exclude_order_id);
    
        if (empty($appointments['appointments'])) {
            $message = '<div class="notice notice-error is-dismissible"><p>No appointments found to send invoice.</p></div>';
        } else {
            // Get or create master order for payment link
            if ($existing_order) {
                $master_order_id = $existing_order->get_id();
                // Optionally update the master order with current appointments
                // urmentor_update_master_order($existing_order, $selected_parent_id, $selected_year, $selected_month, $appointments);
            } else {
                $master_order_id = urmentor_create_master_order($selected_parent_id, $selected_year, $selected_month, $appointments);
                if (is_wp_error($master_order_id)) {
                    $message = '<div class="notice notice-error is-dismissible"><p>Error creating order for payment: ' . $master_order_id->get_error_message() . '</p></div>';
                    $master_order_id = null;
                }
            }
            
            // Send email with payment link
            $result = urmentor_send_invoice_email($selected_parent_id, $selected_year, $selected_month, $appointments, $master_order_id);
            if (is_wp_error($result)) {
                $message = '<div class="notice notice-error is-dismissible"><p>Error sending invoice: ' . $result->get_error_message() . '</p></div>';
            } else {
                $payment_info = $master_order_id ? ' with payment link' : '';
                $message = '<div class="notice notice-success is-dismissible"><p>Invoice sent successfully to parent' . $payment_info . '!</p></div>';
            }
        }
    }

    // Output the page content
    ?>
    <style>
        .monthly-invoice-table {
            border-collapse: collapse;
        }
        .monthly-invoice-table th,
        .monthly-invoice-table td {
            width: 200px;
            text-align: left;
        }
        .monthly-invoice-table select {
            width: 200px;
            margin-right: 10px;
        }
        
        /* Enhanced Preview Styles */
        .invoice-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 20px 0;
            border-bottom: 2px solid #000;
            margin-bottom: 20px;
        }
        .logo-placeholder {
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #666;
        }
        .company-details {
            text-align: right;
            line-height: 1.6;
        }
        .invoice-title {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            margin: 30px 0;
            color: #333;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        .invoice-table th, 
        .invoice-table td {
            padding: 12px 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .invoice-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        .invoice-table .price-column {
            text-align: right;
            width: 120px;
        }
        .invoice-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .total-row td {
            background-color: #e9ecef !important;
            font-weight: bold;
            font-size: 16px;
        }
        
        /* Payment link styling */
        .payment-info {
            background-color: #e7f3ff;
            border: 1px solid #0073aa;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .payment-link {
            display: inline-block;
            background-color: #0073aa;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        .payment-link:hover {
            background-color: #005a87;
            color: white;
        }
    </style>
    <div class="wrap">
        <h1>Generate Monthly Invoice</h1>
        <?php echo $message; ?>
        <form method="post" action="">
            <?php wp_nonce_field('generate_invoice', 'invoice_nonce'); ?>
            <table class="monthly-invoice-table">
                <tr>
                    <th><label for="invoice_year">Year</label></th>
                    <th><label for="invoice_month">Month</label></th>
                    <th><label for="parent_id">Parent</label></th>
                </tr>
                <tr>
                    <td>
                        <select name="invoice_year" id="invoice_year" required>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo esc_attr($year); ?>" <?php selected($selected_year, $year); ?>><?php echo esc_html($year); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="invoice_month" id="invoice_month" required>
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?php echo esc_attr($num); ?>" <?php selected($selected_month, $num); ?>><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <select name="parent_id" id="parent_id" required>
                            <option value="">Select Parent</option>
                            <?php foreach ($parents as $parent): ?>
                                <option value="<?php echo esc_attr($parent->ID); ?>" <?php selected($selected_parent_id, $parent->ID); ?>>
                                    <?php echo esc_html($parent->display_name . ' (' . $parent->user_email . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button('Generate Invoice', 'primary', 'generate_invoice'); ?>
        </form>

        <?php if ($invoice_html): ?>
            <div class="invoice-preview" style="margin-top: 40px;">
                <?php echo $invoice_html; ?>
                
                <div style="margin-top: 20px; text-align: center;">
                    <form method="post" action="" style="display: inline-block; margin: 0 10px;">
                        <?php wp_nonce_field('download_invoice', 'download_nonce'); ?>
                        <input type="hidden" name="invoice_year" value="<?php echo esc_attr($selected_year); ?>">
                        <input type="hidden" name="invoice_month" value="<?php echo esc_attr($selected_month); ?>">
                        <input type="hidden" name="parent_id" value="<?php echo esc_attr($selected_parent_id); ?>">
                        <?php submit_button('Download Invoice as PDF', 'secondary', 'download_invoice'); ?>
                    </form>
                    <form method="post" action="" style="display: inline-block; margin: 0 10px;">
                        <?php wp_nonce_field('send_invoice', 'send_nonce'); ?>
                        <input type="hidden" name="invoice_year" value="<?php echo esc_attr($selected_year); ?>">
                        <input type="hidden" name="invoice_month" value="<?php echo esc_attr($selected_month); ?>">
                        <input type="hidden" name="parent_id" value="<?php echo esc_attr($selected_parent_id); ?>">
                        <?php submit_button('Send Invoice with Payment Link', 'secondary', 'send_invoice'); ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    ob_end_flush(); // End buffering and flush output
}

/**
 * Generate PDF-specific HTML with proper styling
 */
function urmentor_generate_pdf_html($parent_id, $year, $month, $appointments_data) {
    $parent = get_user_by('id', $parent_id);
    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    $appointments = $appointments_data['appointments'];
    $total = $appointments_data['total'];

    $html = '
    <style>
        body {
            font-family: helvetica, arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .header-table td {
            padding: 0;
            vertical-align: top;
            border: none;
        }
        .logo-cell {
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #666;
        }
        .logo-cell img {
            max-width: 120px;
            height: 80px;
        }
        .details-cell {
            width: 50%;
            text-align: right;
            font-size: 11px;
            line-height: 1.5;
        }
        .separator-line {
            width: 100%;
            height: 2px;
            background-color: #000;
            margin: 15px 0;
        }
        .invoice-title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin: 20px 0;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 11px;
        }
        .invoice-table th {
            background-color: #f8f9fa;
            color: #333;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        .invoice-table td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .invoice-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .price-column {
            text-align: right;
            width: 100px;
        }
        .total-row {
            background-color: #e9ecef !important;
            font-weight: bold;
            font-size: 12px;
        }
        .total-row td {
            border: 1px solid #ddd !important;
            padding: 12px 8px;
        }
    </style>
    
    <div class="invoice-container">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    <img src="http://localhost/urmentor-pwa/wp-content/uploads/2025/08/URMENTOR-WP-LOGO-1.png" alt="UrMentor Logo" />
                </td>
                <td class="details-cell">
                    <strong>Parent:</strong> ' . esc_html($parent->display_name . ' (' . $parent->user_email . ')') . '<br/>
                    <strong>Invoice Date:</strong> ' . esc_html($month_name . ' ' . $year) . '
                </td>
            </tr>
        </table>
        
        <div class="separator-line"></div>
        
        <h1 class="invoice-title">Monthly Invoice</h1>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Appointment Time</th>
                    <th>Child</th>
                    <th>Mentor</th>
                    <th>Status</th>
                    <th class="price-column">Price</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($appointments as $appt) {
            $date_time = new DateTime($appt['date_time'], new DateTimeZone('Asia/Kolkata'));
            $formatted_datetime = $date_time->format('j F Y, h:i A'); 
            $formatted_price = number_format($appt['price'], 2) . ' AED';
            
            $html .= '<tr>
                <td>' . esc_html($formatted_datetime) . '</td>
                <td>' . esc_html($appt['child_name']) . '</td>
                <td>' . esc_html($appt['mentor_name']) . '</td>
                <td>' . esc_html(ucfirst($appt['status'])) . '</td>
                <td class="price-column">' . esc_html($formatted_price) . '</td>
            </tr>';
        }

    $formatted_total = number_format($total, 2) . ' AED';
    $html .= '<tr class="total-row">
            <td colspan="4"><strong>Total</strong></td>
            <td class="price-column"><strong>' . esc_html($formatted_total) . '</strong></td>
        </tr>
            </tbody>
        </table>
    </div>';

    return $html;
}

/**
 * Check for existing master order
 */
function urmentor_get_existing_master_order($parent_id, $year, $month) {
    $args = array(
        'limit' => 1,
        'customer_id' => $parent_id,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'is_monthly_invoice',
                'value' => true,
                'compare' => '='
            ),
            array(
                'key' => 'invoice_month',
                'value' => sprintf('%04d-%02d', $year, $month),
                'compare' => '='
            )
        )
    );

    $orders = wc_get_orders($args);
    return !empty($orders) ? $orders[0] : false;
}

/**
 * Filter Payment Methods for Monthly Invoice Orders
 * Excludes Cash on Delivery (COD) for orders created through monthly invoice system
 */
add_filter('woocommerce_available_payment_gateways', 'urmentor_filter_payment_methods_for_invoice_orders');
function urmentor_filter_payment_methods_for_invoice_orders($available_gateways) {
    // Only filter on checkout page and for specific orders
    if (!is_admin() && is_wc_endpoint_url('order-pay')) {
        // Get the order ID from the URL
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order = wc_get_order($order_id);
            
            if ($order) {
                // Check if this is a monthly invoice order
                $is_monthly_invoice = $order->get_meta('is_monthly_invoice', true);
                
                if ($is_monthly_invoice) {
                    // Remove Cash on Delivery payment method
                    unset($available_gateways['cod']);
                    
                    // Optionally, you can also remove other specific payment methods
                    // unset($available_gateways['bacs']); // Bank transfer
                    // unset($available_gateways['cheque']); // Check payments
                }
            }
        }
    }
    
    return $available_gateways;
}

/**
 * Updated Create Master Order Function - Add invoice flag
 */
function urmentor_create_master_order($parent_id, $year, $month, $appointments_data) {
    $parent = get_user_by('id', $parent_id);
    if (!$parent) {
        return new WP_Error('invalid_parent', 'Invalid parent user.');
    }

    $appointments = $appointments_data['appointments'];
    $months = array(
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    );

    // Create new order
    $order = wc_create_order(array(
        'customer_id' => $parent_id,
    ));

    // Add individual order items from appointments
    foreach ($appointments as $appt) {
        $original_order = wc_get_order($appt['order_id']);
        if (!$original_order) {
            continue;
        }
        $item = $original_order->get_item($appt['item_id']);
        if (!$item) {
            continue;
        }

        $new_item = new WC_Order_Item_Product();
        $new_item->set_name($item->get_name());
        $new_item->set_quantity($item->get_quantity());
        $new_item->set_subtotal($item->get_subtotal());
        $new_item->set_total($item->get_total());
        $new_item->set_product_id($item->get_product_id());
        $new_item->set_variation_id($item->get_variation_id());
        foreach ($item->get_meta_data() as $meta) {
            $new_item->add_meta_data($meta->key, $meta->value);
        }
        $order->add_item($new_item);
    }

    // Set billing details from parent
    $order->set_billing_first_name($parent->first_name);
    $order->set_billing_last_name($parent->last_name);
    $order->set_billing_email($parent->user_email);

    // Add meta data - IMPORTANT: Mark as monthly invoice order
    $order->update_meta_data('invoice_month', sprintf('%04d-%02d', $year, $month));
    $order->update_meta_data('parent_id', $parent_id);
    $order->update_meta_data('is_monthly_invoice', true); // This flag will be used to filter payment methods

    // Store linked appointments
    $linked_items = array();
    foreach ($appointments as $appt) {
        $linked_items[] = array(
            'order_id' => $appt['order_id'],
            'item_id' => $appt['item_id'],
        );
    }
    $order->update_meta_data('linked_appointments', $linked_items);

    $order->calculate_totals();
    $order->update_status('pending', 'Monthly invoice generated.');

    return $order->get_id();
}

/**
 * Updated Update Master Order Function - Ensure invoice flag is set
 */
function urmentor_update_master_order($order, $parent_id, $year, $month, $appointments_data) {
    $parent = get_user_by('id', $parent_id);
    if (!$parent) {
        return new WP_Error('invalid_parent', 'Invalid parent user.');
    }

    $appointments = $appointments_data['appointments'];

    // Remove existing items
    foreach ($order->get_items() as $item_id => $item) {
        $order->remove_item($item_id);
    }

    // Add individual order items from appointments
    foreach ($appointments as $appt) {
        $original_order = wc_get_order($appt['order_id']);
        if (!$original_order) {
            continue;
        }
        $item = $original_order->get_item($appt['item_id']);
        if (!$item) {
            continue;
        }

        $new_item = new WC_Order_Item_Product();
        $new_item->set_name($item->get_name());
        $new_item->set_quantity($item->get_quantity());
        $new_item->set_subtotal($item->get_subtotal());
        $new_item->set_total($item->get_total());
        $new_item->set_product_id($item->get_product_id());
        $new_item->set_variation_id($item->get_variation_id());
        foreach ($item->get_meta_data() as $meta) {
            $new_item->add_meta_data($meta->key, $meta->value);
        }
        $order->add_item($new_item);
    }

    // Update billing details
    $order->set_billing_first_name($parent->first_name);
    $order->set_billing_last_name($parent->last_name);
    $order->set_billing_email($parent->user_email);

    // Update meta data - ENSURE the invoice flag is set
    $order->update_meta_data('invoice_month', sprintf('%04d-%02d', $year, $month));
    $order->update_meta_data('parent_id', $parent_id);
    $order->update_meta_data('is_monthly_invoice', true); // Ensure this is set for payment method filtering

    // Store linked appointments
    $linked_items = array();
    foreach ($appointments as $appt) {
        $linked_items[] = array(
            'order_id' => $appt['order_id'],
            'item_id' => $appt['item_id'],
        );
    }
    $order->update_meta_data('linked_appointments', $linked_items);

    $order->calculate_totals();
    $order->update_status('pending', 'Monthly invoice updated.');

    return $order->get_id();
}

/**
 * Optional: Add custom notice on checkout for monthly invoice orders
 */
add_action('woocommerce_before_checkout_form', 'urmentor_monthly_invoice_checkout_notice');
function urmentor_monthly_invoice_checkout_notice() {
    if (is_wc_endpoint_url('order-pay')) {
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order = wc_get_order($order_id);
            
            if ($order && $order->get_meta('is_monthly_invoice', true)) {
                $invoice_month = $order->get_meta('invoice_month', true);
                $formatted_month = $invoice_month ? date('F Y', strtotime($invoice_month . '-01')) : 'Unknown';
                
                echo '<div class="woocommerce-info" style="margin-bottom: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #0073aa;">';
                echo '<strong>Monthly Invoice Payment</strong><br>';
                echo 'You are paying for your UrMentor monthly invoice for ' . esc_html($formatted_month) . '.<br>';
                echo '<em>Note: Cash on Delivery is not available for invoice payments. Please choose from the available online payment methods below.</em>';
                echo '</div>';
            }
        }
    }
}

/**
 * Optional: Customize checkout page title for monthly invoice orders
 */
add_filter('woocommerce_endpoint_order-pay_title', 'urmentor_customize_invoice_payment_title');
function urmentor_customize_invoice_payment_title($title) {
    global $wp;
    if (isset($wp->query_vars['order-pay'])) {
        $order_id = absint($wp->query_vars['order-pay']);
        $order = wc_get_order($order_id);
        
        if ($order && $order->get_meta('is_monthly_invoice', true)) {
            $invoice_month = $order->get_meta('invoice_month', true);
            $formatted_month = $invoice_month ? date('F Y', strtotime($invoice_month . '-01')) : '';
            return 'Pay Monthly Invoice' . ($formatted_month ? ' - ' . $formatted_month : '');
        }
    }
    return $title;
}

/**
 * Optional: Add specific styling for monthly invoice checkout page
 */
add_action('wp_head', 'urmentor_invoice_checkout_styles');
function urmentor_invoice_checkout_styles() {
    if (is_wc_endpoint_url('order-pay')) {
        global $wp;
        if (isset($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order = wc_get_order($order_id);
            
            if ($order && $order->get_meta('is_monthly_invoice', true)) {
                ?>
                <style>
                    /* Add any custom styles here if needed */
                </style>
                <?php
            }
        }
    }
}

/**
 * Generate Invoice HTML
 */
function urmentor_generate_invoice_html($parent_id, $year, $month, $appointments_data, $for_pdf = false) {
    $parent = get_user_by('id', $parent_id);
    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    $appointments = $appointments_data['appointments'];
    $total = $appointments_data['total'];

    // Currency symbol handling
    $currency_symbol = 'AED'; // or 'د.إ' if you prefer Arabic
    
    ob_start();
    ?>
    <div class="invoice-container">
        <div class="invoice-header">
            <div class="logo-section">
                <div class="logo-placeholder">
                    <img src="http://localhost/urmentor-pwa/wp-content/uploads/2025/08/URMENTOR-WP-LOGO-1.png" alt="UR Mentor Logo" />
                </div>
            </div>
            <div class="company-details">
                <p><strong>Parent:</strong> <?php echo esc_html($parent->display_name . ' (' . $parent->user_email . ')'); ?></p>
                <p><strong>Invoice Date:</strong> <?php echo esc_html($month_name . ' ' . $year); ?></p>
            </div>
        </div>
        <h1 class="invoice-title">Monthly Invoice</h1>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Appointment Time</th>
                    <th>Child</th>
                    <th>Mentor</th>
                    <th>Status</th>
                    <th class="price-column">Price</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($appointments as $appt): ?>
                <tr>
                    <td><?php 
                        $date_time = new DateTime($appt['date_time'], new DateTimeZone('Asia/Kolkata'));
                        // Format the date and time (e.g., 'F j, Y, h:i A')
                        echo esc_html($date_time->format('j F Y, h:i A')); 
                    ?></td>
                    <td><?php echo esc_html($appt['child_name']); ?></td>
                    <td><?php echo esc_html($appt['mentor_name']); ?></td>
                    <td><?php echo esc_html(ucfirst($appt['status'])); ?></td>
                    <td class="price-column"><?php echo esc_html(number_format($appt['price'], 2) . ' ' . $currency_symbol); ?></td>
                </tr>
            <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4"><strong>Total</strong></td>
                    <td class="price-column"><strong><?php echo esc_html(number_format($total, 2) . ' ' . $currency_symbol); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
?>