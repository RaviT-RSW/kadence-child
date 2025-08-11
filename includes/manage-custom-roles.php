<?php
/**
 * Register custom user roles: Child, Parent, Mentor
 */

function urmentor_register_custom_roles() {
    // Child Role
    add_role(
        'child_user',
        __('Child'),
        array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        )
    );

    // Parent Role
    add_role(
        'parent_user',
        __('Parent'),
        array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        )
    );

    // Mentor Role
    add_role(
        'mentor_user',
        __('Mentor'),
        array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        )
    );
}
add_action('init', 'urmentor_register_custom_roles');


// 2. Add Parent Field on "Add New User" and "Edit User"
add_action('user_new_form', 'add_child_parent_field'); // add user
add_action('edit_user_profile', 'add_child_parent_field'); // edit user

function add_child_parent_field($user) {
    // Get current assigned parent ID (only on edit)
    $assigned_parent = is_object($user) ? get_user_meta($user->ID, 'assigned_parent_id', true) : '';

    // Get users with role parent_user
    $parents = get_users(array('role' => 'parent_user'));
    ?>
    <table class="form-table">
        <tr id="parent-selector-row" style="display:none;">
            <th><label for="child_parent">Assign Parent</label></th>
            <td>
                <select name="child_parent_id" id="child_parent">
                    <option value="">Select Parent</option>
                    <?php foreach ($parents as $parent): ?>
                        <option value="<?php echo esc_attr($parent->ID); ?>"
                            <?php selected($assigned_parent, $parent->ID); ?>>
                            <?php echo esc_html($parent->display_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">This appears only if the user role is "Child User".</p>
            </td>
        </tr>
    </table>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const roleSelect = document.getElementById('role');
        const parentRow = document.getElementById('parent-selector-row');

        function toggleParentField() {
            if (roleSelect && roleSelect.value === 'child_user') {
                parentRow.style.display = '';
            } else {
                parentRow.style.display = 'none';
            }
        }

        if (roleSelect) {
            roleSelect.addEventListener('change', toggleParentField);
            toggleParentField();
        }
    });
    </script>
    <?php
}

// 3. Save parent ID when adding new user
add_action('user_register', 'save_child_parent_meta');
function save_child_parent_meta($user_id) {
    if (isset($_POST['role']) && $_POST['role'] === 'child_user') {
        $parent_id = !empty($_POST['child_parent_id']) ? intval($_POST['child_parent_id']) : '';
        update_user_meta($user_id, 'assigned_parent_id', $parent_id);
    }
}

// 4. Save parent ID when editing a user
add_action('edit_user_profile_update', 'save_child_parent_meta_on_edit');
function save_child_parent_meta_on_edit($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;

    $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';
    if ($role === 'child_user') {
        $parent_id = !empty($_POST['child_parent_id']) ? intval($_POST['child_parent_id']) : '';
        update_user_meta($user_id, 'assigned_parent_id', $parent_id);
    } else {
        delete_user_meta($user_id, 'assigned_parent_id'); // clean up if role changed
    }
}


// Common functions to check role

// If no user ID given, use current logged-in user
function is_parent_user( $user_id = null )
{
    $user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();

    if ( empty( $user->roles ) ) {
        return false;
    }

    return in_array( 'parent_user', (array) $user->roles, true );
}

function is_child_user( $user_id = null )
{
    $user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();

    if ( empty( $user->roles ) ) {
        return false;
    }

    return in_array( 'child_user', (array) $user->roles, true );
}

function is_mentor_user( $user_id = null )
{
    $user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();

    if ( empty( $user->roles ) ) {
        return false;
    }

    return in_array( 'mentor_user', (array) $user->roles, true );
}

function is_admin_user( $user_id = null ) {
    $user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();

    if ( empty( $user->roles ) ) {
        return false;
    }

    return in_array( 'administrator', (array) $user->roles, true );
}

/**
 * Universal Role-Based Access Control System
 * Add this to your manage-custom-roles.php file
 */

/**
 * Define page access rules
 * Add your pages and their allowed roles here
 */
function get_page_access_rules() {
    return array(
        // Dashboard pages
        'child-dashboard' => array('child_user'),
        'parent-dashboard' => array('parent_user'),
        'mentor-dashboard' => array('mentor_user'),
        
        // Other pages - add as needed
        'user-profile' => array('child_user', 'parent_user', 'mentor_user'),
        'book-session' => array('parent_user'),
        'working-hours' => array('mentor_user'),
        
        // Add more pages here as needed
        // 'page-slug' => array('role1', 'role2'),
    );
}

/**
 * Check if current user has access to a specific page
 */
function user_has_page_access($page_slug, $child_id = null) {
    if (!is_user_logged_in()) {
        return false;
    }

    $access_rules = get_page_access_rules();
    
    // If page not in rules, allow access (or change this to restrict)
    if (!isset($access_rules[$page_slug])) {
        return true; // Change to false if you want to restrict unknown pages
    }

    $allowed_roles = $access_rules[$page_slug];
    $current_user = wp_get_current_user();

    // Check if user has any of the allowed roles
    foreach ($allowed_roles as $role) {
        if (in_array($role, (array) $current_user->roles, true)) {
            return true;
        }
    }

    // Special case: Allow parents to access their children's dashboards
    if ($page_slug === 'child-dashboard' && is_parent_user() && $child_id) {
        $assigned_parent_id = get_user_meta($child_id, 'assigned_parent_id', true);
        if ($assigned_parent_id == $current_user->ID) {
            return true;
        }
    }

    return false;
}

/** 
 * Automatic role-based access control hook
 * This runs on every page load and checks access automatically
 */
function auto_check_page_access() {
    // Skip admin pages, login pages, AJAX requests, and home page
    if (is_admin() || wp_doing_ajax() || is_404() || is_home() || is_front_page()) {
        return;
    }

    // Skip login and registration pages
    if (is_page('login') || is_page('register') || is_page('wp-login')) {
        return;
    }

    global $post;
    
    // Skip if no post object
    if (!$post) {
        return;
    }

    $page_slug = $post->post_name;
    $child_id = isset($_GET['child_id']) ? intval($_GET['child_id']) : null;

    // Get access rules
    $access_rules = get_page_access_rules();
    
    // Only check access for pages that are in our rules (protected pages)
    if (!isset($access_rules[$page_slug])) {
        return; // Not a protected page, allow access
    }

    // Prevent redirect loop - don't redirect if we're already on home page
    $current_url = home_url(add_query_arg(array(), $GLOBALS['wp']->request));
    if ($current_url === home_url() || $current_url === home_url('/')) {
        return;
    }

    // Check if user is logged in for protected pages
    if (!is_user_logged_in()) {
        wp_redirect(wp_login_url(get_permalink()));
        exit;
    }

    // Check role-based access
    if (!user_has_page_access($page_slug, $child_id)) {
        wp_redirect(home_url());
        exit;
    }
}

// Hook the automatic access control to template_redirect
add_action('template_redirect', 'auto_check_page_access');