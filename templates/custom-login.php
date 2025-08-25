<?php
/*
 * Template Name: Custom Login
 * Description: A custom login page with role-based redirection
 */

get_header();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['custom_login_nonce']) && wp_verify_nonce($_POST['custom_login_nonce'], 'custom_login_action')) {
    
    $creds = array(
        'user_login'    => sanitize_text_field($_POST['username']),
        'user_password' => $_POST['password'],
        'remember'      => !empty($_POST['rememberme']),
    );

    $user = wp_signon($creds, false);

    if (!is_wp_error($user)) {

        // Get the user's primary role
        $roles = (array) $user->roles;
        $role = isset($roles[0]) ? $roles[0] : 'none';

        // Role-based redirection map
        $redirect_map = array(
            'child_user'         => home_url('/child-dashboard'),
            'parent_user'  => home_url('/parent-dashboard'),
            'mentor_user'  => home_url('/mentor-dashboard'),
            'administrator'      => admin_url(),
            'none'               => home_url(),
        );

        // Determine redirect target
        $redirect_url = isset($redirect_map[$role]) ? $redirect_map[$role] : home_url();

        // Safely redirect
        wp_safe_redirect($redirect_url);
        exit;

    } else {
        $error_message = $user->get_error_message();
    }
}

// Custom function to replace WordPress lost password URL with custom URL
function custom_lostpassword_url() {
    return home_url('/custom-lost-password');
}

// Filter the error message to use custom lost password URL
if (isset($error_message)) {
    $error_message = str_replace(wp_lostpassword_url(), custom_lostpassword_url(), $error_message);
}
?>
<!-- LOGIN FORM HTML + CSS (updated) -->
<div class="custom-login-container">
    <div class="login-form">
        <!-- Add UrMentor logo -->
        <div class="logo-container">
            <?php
            $custom_logo_id = get_theme_mod('custom_logo');
            if ($custom_logo_id) {
                $logo_url = wp_get_attachment_image_src($custom_logo_id, 'full')[0];
                echo '<img src="' . esc_url($logo_url) . '" alt="' . get_bloginfo('name') . '" class="login-logo">';
            } else {
                echo '<h1 class="site-title">' . get_bloginfo('name') . '</h1>';
            }
            ?>
        </div>
        <h2>Login to Your Account</h2>
        
        <?php if (isset($error_message)) : ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="post" id="custom-login-form">
            <div class="form-group">
                <label for="username">Username or Email</label>
                <input type="text" name="username" id="username" required class="form-control">
            </div>
            <div class="form-group" style="position: relative;">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required class="form-control">
                <!-- Eye icon -->
                <i class="fas fa-solid fa-eye-slash" id="togglePassword" style="position: absolute; right: 15px; top: 45px; cursor: pointer;"></i>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="rememberme" id="rememberme"> Remember Me</label>
            </div>
            <?php wp_nonce_field('custom_login_action', 'custom_login_nonce'); ?>
            <button type="submit" class="login-button">Log In</button>
        </form>
        
        <div class="login-links">
            <a href="<?php echo custom_lostpassword_url(); ?>">Lost your password?</a>
        </div>
    </div>
</div>

<style>
.custom-login-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: #E6F0FA;
}

.login-form {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 86, 166, 0.1);
    width: 100%;
    max-width: 400px;
    text-align: center;
}

.logo-container {
    margin-bottom: 1.5rem;
    text-align: center; /* Center the container contents */
}

.login-logo {
    max-width: 150px;
    height: auto;
    display: block; /* Ensure the image behaves as a block element */
    margin-left: auto; /* Center horizontally */
    margin-right: auto; /* Center horizontally */
}

.login-form h2 {
    text-align: center;
    margin-bottom: 1.5rem;
    color: #0056A6; /* Dark blue from the header */
    font-size: 1.5rem;
    font-weight: 600;
}

.form-group {
    margin-bottom: 1.25rem;
    text-align: left;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #333;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #00C4B4; /* Teal border */
    border-radius: 5px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-control:focus {
    border-color: #0056A6; /* Dark blue focus */
    outline: none;
    box-shadow: 0 0 5px rgba(0, 86, 166, 0.3);
}

.login-button {
    width: 100%;
    padding: 0.75rem;
    background: #00C4B4; /* Teal button */
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s, transform 0.1s;
}

.login-button:hover {
    background: #009D8A; /* Darker teal on hover */
    transform: translateY(-2px);
}

.error-message {
    background: #FFE6E6;
    color: #D32F2F;
    padding: 0.75rem;
    border-radius: 5px;
    margin-bottom: 1rem;
    text-align: center;
}

.login-links {
    margin-top: 1.5rem;
    text-align: center;
}

.login-links a {
    color: #00C4B4; /* Teal links */
    text-decoration: none;
    font-weight: 500;
    margin: 0 0.75rem;
}

.login-links a:hover {
    text-decoration: underline;
    color: #0056A6; /* Dark blue on hover */
}
</style>

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');

    togglePassword.addEventListener('click', function () {
        // Toggle the type attribute
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);

        // Toggle the eye / eye-slash icon
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
</script>
<?php get_footer(); ?>