<?php
/**
 * Manage Hourly Rate for Mentor Users.
 */

add_action('user_new_form', 'urmentor_add_mentor_hourly_rate_field');
add_action('edit_user_profile', 'urmentor_add_mentor_hourly_rate_field');

function urmentor_add_mentor_hourly_rate_field($user) {
    $hourly_rate = is_object($user) ? get_user_meta($user->ID, 'mentor_hourly_rate', true) : '';
    ?>
    <table class="form-table">
        <tr id="mentor-hourly-rate-row" style="display: none;">
            <th><label for="mentor_hourly_rate">Hourly Rate ($)</label></th>
            <td>
                <input type="number" step="0.01" name="mentor_hourly_rate" id="mentor_hourly_rate" value="<?php echo esc_attr($hourly_rate); ?>" class="regular-text" />
                <p class="description">Only applicable for Mentor user role.</p>
            </td>
        </tr>
    </table>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const roleSelect = document.getElementById('role');
        const mentorRow = document.getElementById('mentor-hourly-rate-row');

        function toggleMentorField() {
            if (roleSelect && roleSelect.value === 'mentor_user') {
                mentorRow.style.display = '';
            } else {
                mentorRow.style.display = 'none';
            }
        }

        if (roleSelect) {
            roleSelect.addEventListener('change', toggleMentorField);
            toggleMentorField();
        }
    });
    </script>
    <?php
}

add_action('user_register', 'urmentor_save_mentor_hourly_rate');
add_action('edit_user_profile_update', 'urmentor_save_mentor_hourly_rate');

function urmentor_save_mentor_hourly_rate($user_id) {
    if (!current_user_can('edit_user', $user_id)) return;

    $role = isset($_POST['role']) ? sanitize_text_field($_POST['role']) : '';

    if ($role === 'mentor_user') {
        if (isset($_POST['mentor_hourly_rate'])) {
            $hourly_rate = floatval($_POST['mentor_hourly_rate']);
            update_user_meta($user_id, 'mentor_hourly_rate', $hourly_rate);
        }
    } else {
        delete_user_meta($user_id, 'mentor_hourly_rate');
    }
}

// Add "Hourly Rate" column to user list
add_filter('manage_users_columns', 'urmentor_add_hourly_rate_column');
function urmentor_add_hourly_rate_column($columns) {
    $columns['mentor_hourly_rate'] = 'Hourly Rate ($)';
    return $columns;
}

// Show Hourly Rate in the new column
add_filter('manage_users_custom_column', 'urmentor_show_hourly_rate_column', 10, 3);
function urmentor_show_hourly_rate_column($value, $column_name, $user_id) {
    if ($column_name === 'mentor_hourly_rate') {
        $rate = get_user_meta($user_id, 'mentor_hourly_rate', true);
        return $rate ? '$' . number_format($rate, 2) : '—';
    }
    return $value;
}