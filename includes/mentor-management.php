<?php
/**
 * Mentor Management (Admin Interface)
 *
 * Adds a custom admin menu to manage mentor-child relationships.
 * 
 * Features:
 * - "Assign Mentors" page: Assign mentors to unassigned child users.
 * - "Assigned Users" page: View, search, paginate, and unassign relationships.
 *
 * Notes:
 * - For admin users only (`manage_options`).
 * - Uses `mentor_user` and `child_user` roles.
 * - Stores assignments in `assigned_mentor_id` user meta.
 *
 */

add_action('admin_menu', 'mentor_management_menu');

function mentor_management_menu() {
    // Main menu page (hidden content, only for structure)
    add_menu_page(
        'Mentor Management',
        'Mentor Management',
        'manage_options',
        'mentor-management',
        '__return_empty_string', // Empty callback to avoid default submenu
        'dashicons-groups',
        20
    );

    // Submenu 1: Assign Mentors
    add_submenu_page(
        'mentor-management',
        'Assign Mentors',
        'Assign Mentors',
        'manage_options',
        'assign-mentors',
        'assign_mentors_page'
    );

    // Submenu 2: Assigned Users
    add_submenu_page(
        'mentor-management',
        'Assigned Users',
        'Assigned Users',
        'manage_options',
        'assigned-users',
        'assigned_users_page'
    );

    // Remove the default submenu item (if it appears)
    add_action('admin_menu', 'remove_default_submenu', 999);
}

function remove_default_submenu() {
    remove_submenu_page('mentor-management', 'mentor-management');
}

// Assign Mentors Page
function assign_mentors_page() {
    global $wpdb;
    $message = '';

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

            $message = '<div class="notice notice-success is-dismissible"><p>Mentor assigned successfully!</p></div>';
        }
    }

    // Get users
    $child_users = get_users(array('role__in' => array('child_user'), 'fields' => array('ID', 'display_name')));
    $mentor_users = get_users(array('role__in' => array('mentor_user'), 'fields' => array('ID', 'display_name')));

    // Filter out assigned children
    $available_children = array_filter($child_users, function($user) {
        return !get_user_meta($user->ID, 'assigned_mentor_id', true);
    });
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <?php echo $message; ?>
        <form method="post" action="">
            <?php wp_nonce_field('assign_mentor', 'mentor_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="child_user">Child User</label></th>
                    <td>
                        <select name="child_user" id="child_user" class="regular-text" required>
                            <option value="">Select a Child</option>
                            <?php foreach ($available_children as $user) : ?>
                                <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
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
    <?php
}

// Assigned Users Page
function assigned_users_page() {
    global $wpdb;
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

    if (isset($_GET['unassign']) && wp_verify_nonce($_GET['_wpnonce'], 'unassign_mentor_' . $_GET['unassign'])) {
        $child_id = intval($_GET['unassign']);
        delete_user_meta($child_id, 'assigned_mentor_id');
        wp_redirect(add_query_arg(array('paged' => $paged + 1, 's' => $search), admin_url('admin.php?page=assigned-users')));
        exit;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="get" action="">
            <input type="hidden" name="page" value="assigned-users">
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
                                <a href="<?php echo wp_nonce_url(add_query_arg('unassign', $user->child_id, admin_url('admin.php?page=assigned-users')), 'unassign_mentor_' . $user->child_id); ?>" class="button unassign-btn" onclick="return confirm('Are you sure you want to unassign this child?');">Unassign</a>
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
    <style>
        /* Pagination Styles */
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