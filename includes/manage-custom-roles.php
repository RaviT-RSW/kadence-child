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
