<?php
/**
 * Add Admin Menu for User Management
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
        26
    );
    
    add_submenu_page(
        'urmentor-manage-users',
        'Add New User',
        'Add New User',
        'manage_options',
        'urmentor-add-user',
        'urmentor_add_user_page'
    );
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
                    <th scope="col" id="hourly_rate" class="manage-column column-hourly_rate">Hourly Rate</th>
                    <th scope="col" id="actions" class="manage-column column-actions">Actions</th>
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
                        $hourly_rate = get_user_meta($user->ID, 'mentor_hourly_rate', true);
                        ?>
                        <tr>
                            <td class="username column-username has-row-actions column-primary" data-colname="Username">
                                <strong><?php echo esc_html($user->user_login); ?></strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=urmentor-add-user&action=edit&user_id=' . $user->ID); ?>">Edit</a> | 
                                    </span>
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
                            <td class="hourly_rate column-hourly_rate" data-colname="Hourly Rate">
                                <?php echo $hourly_rate ? '$' . number_format($hourly_rate, 2) : '—'; ?>
                            </td>
                            <td class="actions column-actions" data-colname="Actions">
                                <a href="<?php echo admin_url('admin.php?page=urmentor-add-user&action=edit&user_id=' . $user->ID); ?>" class="button button-small button-primary">Edit</a>
                                <?php if (current_user_can('delete_users') && $user->ID !== get_current_user_id()): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=urmentor-manage-users&action=delete&user_id=' . $user->ID), 'delete-user_' . $user->ID); ?>" 
                                       onclick="return confirm('Are you sure you want to delete this user?');" 
                                       class="button button-small" style="color: #d63638; border-color: #d63638;">Delete</a>
                                <?php endif; ?>
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
    
    // Get all parents for dropdown
    $parents = get_users(array('role' => 'parent_user'));
    ?>
    
    <div class="wrap">
        <h1><?php echo $action === 'edit' ? 'Edit User' : 'Add New User'; ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('urmentor_user_form', 'urmentor_user_nonce'); ?>
            
            <table class="form-table" role="presentation">
                <tbody>
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