<?php
/*
 * Template Name: Custom Reset Password
 * Description: A custom password reset page
 */

get_header();

$message = '';
$message_type = '';
$show_form = true;

// Get the key and login from URL parameters
$key = isset($_GET['key']) ? $_GET['key'] : '';
$login = isset($_GET['login']) ? $_GET['login'] : '';

if (empty($key) || empty($login)) {
    $message = 'Invalid password reset link. Please request a new one.';
    $message_type = 'error';
    $show_form = false;
} else {
    // Verify the key
    $user = check_password_reset_key($key, $login);
    
    if (is_wp_error($user)) {
        $message = 'Invalid or expired password reset link. Please request a new one.';
        $message_type = 'error';
        $show_form = false;
    } else {
        // Handle password reset form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password_nonce']) && wp_verify_nonce($_POST['reset_password_nonce'], 'reset_password_action')) {
            
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];
            
            if (empty($password)) {
                $message = 'Please enter a new password.';
                $message_type = 'error';
            }elseif ($password !== $password_confirm) {
                $message = 'Passwords do not match.';
                $message_type = 'error';
            } else {
                // Reset the password
                wp_set_password($password, $user->ID);
                
                // Log the user in automatically
                wp_clear_auth_cookie();
                wp_set_auth_cookie($user->ID);
                
                $message = 'Your password has been successfully reset and you are now logged in.';
                $message_type = 'success';
                $show_form = false;
                
                // Redirect after 3 seconds
                echo '<meta http-equiv="refresh" content="3;url=' . home_url('/custom-login') . '">';
            }
        }
    }
}
?>

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
        <h2>Reset Your Password</h2>
        
        <?php if (!empty($message)) : ?>
            <div class="message <?php echo $message_type; ?>-message">
                <?php echo esc_html($message); ?>
                <?php if ($message_type === 'success') : ?>
                    <p><small>Redirecting to login page in 3 seconds...</small></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($show_form) : ?>
        <p class="instruction-text">Enter your new password below.</p>
        
        <form method="post" id="reset-password-form">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" name="password" id="password" required class="form-control">
                <small class="help-text">Password must be at least 8 characters long</small>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Confirm New Password</label>
                <input type="password" name="password_confirm" id="password_confirm" required class="form-control">
            </div>
            
            <!-- Password strength indicator -->
            <div class="password-strength">
                <div id="password-strength-meter"></div>
                <span id="password-strength-text"></span>
            </div>
            
            <?php wp_nonce_field('reset_password_action', 'reset_password_nonce'); ?>
            <button type="submit" class="login-button">Reset Password</button>
        </form>
        <?php endif; ?>
        
        <div class="login-links">
            <a href="<?php echo home_url('/custom-login'); ?>">← Back to Login</a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordField = document.getElementById('password');
    const confirmField = document.getElementById('password_confirm');
    const strengthMeter = document.getElementById('password-strength-meter');
    const strengthText = document.getElementById('password-strength-text');
    
    if (passwordField) {
        passwordField.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let strengthLabel = '';
            let strengthColor = '';
            
            // Length check
            if (password.length >= 8) strength += 25;
            if (password.length >= 12) strength += 10;
            
            // Character variety checks
            if (/[a-z]/.test(password)) strength += 15;
            if (/[A-Z]/.test(password)) strength += 15;
            if (/[0-9]/.test(password)) strength += 15;
            if (/[^A-Za-z0-9]/.test(password)) strength += 20;
            
            // Set strength label and color
            if (strength < 30) {
                strengthLabel = 'Weak';
                strengthColor = '#ff4444';
            } else if (strength < 60) {
                strengthLabel = 'Fair';
                strengthColor = '#ffbb33';
            } else if (strength < 80) {
                strengthLabel = 'Good';
                strengthColor = '#00C851';
            } else {
                strengthLabel = 'Strong';
                strengthColor = '#007E33';
            }
            
            strengthMeter.style.width = strength + '%';
            strengthMeter.style.backgroundColor = strengthColor;
            strengthText.textContent = strengthLabel;
            strengthText.style.color = strengthColor;
        });
        
        // Password confirmation validation
        confirmField.addEventListener('input', function() {
            if (this.value !== passwordField.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    }
});
</script>

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
    text-align: center;
}

.login-logo {
    max-width: 150px;
    height: auto;
    display: block;
    margin-left: auto;
    margin-right: auto;
}

.login-form h2 {
    text-align: center;
    margin-bottom: 1.5rem;
    color: #0056A6;
    font-size: 1.5rem;
    font-weight: 600;
}

.instruction-text {
    text-align: center;
    color: #666;
    margin-bottom: 1.5rem;
    line-height: 1.5;
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
    border: 1px solid #00C4B4;
    border-radius: 5px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

.form-control:focus {
    border-color: #0056A6;
    outline: none;
    box-shadow: 0 0 5px rgba(0, 86, 166, 0.3);
}

.help-text {
    font-size: 0.85rem;
    color: #666;
    margin-top: 0.25rem;
    display: block;
}

.password-strength {
    margin-bottom: 1rem;
    text-align: left;
}

.password-strength #password-strength-meter {
    height: 4px;
    background: #ddd;
    border-radius: 2px;
    transition: all 0.3s;
    margin-bottom: 0.5rem;
}

.password-strength #password-strength-text {
    font-size: 0.9rem;
    font-weight: 500;
}

.login-button {
    width: 100%;
    padding: 0.75rem;
    background: #00C4B4;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s, transform 0.1s;
    margin-bottom: 1rem;
}

.login-button:hover {
    background: #009D8A;
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

.success-message {
    background: #E8F5E8;
    color: #2E7D32;
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
    color: #00C4B4;
    text-decoration: none;
    font-weight: 500;
    margin: 0 0.75rem;
}

.login-links a:hover {
    text-decoration: underline;
    color: #0056A6;
}
</style>

<?php get_footer(); ?>