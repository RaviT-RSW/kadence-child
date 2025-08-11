<?php
/*
 * Template Name: Custom Lost Password
 * Description: A custom lost password page
 */

get_header();

$message = '';
$message_type = '';

// Handle lost password form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lost_password_nonce']) && wp_verify_nonce($_POST['lost_password_nonce'], 'lost_password_action')) {
    
    $user_login = sanitize_text_field($_POST['user_login']);
    
    if (empty($user_login)) {
        $message = 'Please enter your username or email address.';
        $message_type = 'error';
    } else {
        // Check if user exists
        if (strpos($user_login, '@')) {
            $user = get_user_by('email', $user_login);
        } else {
            $user = get_user_by('login', $user_login);
        }
        
        if (!$user) {
            $message = 'There is no account with that username or email address.';
            $message_type = 'error';
        } else {
            // Generate password reset key
            $key = get_password_reset_key($user);
            
            if (is_wp_error($key)) {
                $message = 'Unable to generate password reset key. Please try again.';
                $message_type = 'error';
            } else {
                // Send password reset email with custom URL
                $reset_url = home_url('/custom-reset-password') . '?key=' . $key . '&login=' . rawurlencode($user->user_login);
                
                $subject = '[' . get_bloginfo('name') . '] Password Reset';
                $body = "Someone has requested a password reset for the following account:\n\n";
                $body .= "Site Name: " . get_bloginfo('name') . "\n";
                $body .= "Username: " . $user->user_login . "\n\n";
                $body .= "If this was a mistake, just ignore this email and nothing will happen.\n\n";
                $body .= "To reset your password, visit the following address:\n";
                $body .= $reset_url . "\n";
                
                $sent = wp_mail($user->user_email, $subject, $body);
                
                if ($sent) {
                    $message = 'Password reset email has been sent to your email address.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to send password reset email. Please try again.';
                    $message_type = 'error';
                }
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
            </div>
        <?php endif; ?>

        <?php if ($message_type !== 'success') : ?>
        <p class="instruction-text">Enter your username or email address below and we'll send you a link to reset your password.</p>
        
        <form method="post" id="lost-password-form">
            <div class="form-group">
                <label for="user_login">Username or Email Address</label>
                <input type="text" name="user_login" id="user_login" required class="form-control" 
                       value="<?php echo isset($_POST['user_login']) ? esc_attr($_POST['user_login']) : ''; ?>">
            </div>
            
            <?php wp_nonce_field('lost_password_action', 'lost_password_nonce'); ?>
            <button type="submit" class="login-button">Send Reset Email</button>
        </form>
        <?php endif; ?>
        
        <div class="login-links">
            <a href="<?php echo home_url('/custom-login'); ?>">← Back to Login</a>
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