<?php
// Register the Add Child Form Shortcode
function urmentor_add_child_shortcode() {
    if (!is_user_logged_in() || !current_user_can('parent_user')) {
        return '<div class="alert alert-danger">You must be logged in as a parent to access this form.</div>';
    }

    ob_start();

    $success = '';
    $error = '';

    if (isset($_POST['add_child_submit'])) {
        $username     = sanitize_user($_POST['child_username']);
        $email        = sanitize_email($_POST['child_email']);
        $password     = $_POST['child_password'];
        $first_name   = sanitize_text_field($_POST['child_first_name']);
        $last_name    = sanitize_text_field($_POST['child_last_name']);
        $current_user = wp_get_current_user();

        if (username_exists($username) || email_exists($email)) {
            $error = 'Username or Email already exists.';
        } elseif (strlen($password) < 4) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $user_id = wp_create_user($username, $password, $email);
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id);
                $user->set_role('child_user');

                update_user_meta($user_id, 'first_name', $first_name);
                update_user_meta($user_id, 'last_name', $last_name);
                update_user_meta($user_id, 'assigned_parent_id', $current_user->ID);

                $success = 'Child account created successfully!';
            } else {
                $error = $user_id->get_error_message();
            }
        }
    }
    ?>

    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo esc_html($success); ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger"><?php echo esc_html($error); ?></div>
                <?php endif; ?>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h4 class="mb-4 text-center">Add New Child</h4>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="child_first_name" class="form-label">First Name</label>
                                <input type="text" name="child_first_name" id="child_first_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="child_last_name" class="form-label">Last Name</label>
                                <input type="text" name="child_last_name" id="child_last_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="child_username" class="form-label">Username</label>
                                <input type="text" name="child_username" id="child_username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="child_email" class="form-label">Email</label>
                                <input type="email" name="child_email" id="child_email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="child_password" class="form-label">Password</label>
                                <input type="password" name="child_password" id="child_password" class="form-control" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="add_child_submit" class="btn btn-primary">Create Child</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('add_child_form', 'urmentor_add_child_shortcode');
