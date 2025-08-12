<?php
/**
 * Template Name: User Profile
 */
get_header();
?>


<div class="container-fluid">
  <?php
  $user_id = $current_user->ID;
  $user_info = get_userdata($user_id);
  ?>

  <div class="row mb-4">
    <div class="col-12">
      <h2 class="mb-1">My Profile</h2>
      <p class="text-muted">Update your personal and professional details.</p>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <form id="profileForm">
            <div class="mb-3">
              <label for="firstName" class="form-label">First Name</label>
              <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo esc_attr($user_info->first_name); ?>">
            </div>
            <div class="mb-3">
              <label for="lastName" class="form-label">Last Name</label>
              <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo esc_attr($user_info->last_name); ?>">
            </div>
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" id="email" name="email" value="<?php echo esc_attr($user_info->user_email); ?>" readonly>
            </div>
            <div class="mb-3">
              <label for="phone" class="form-label">Phone</label>
              <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo esc_attr(get_user_meta($user_id, 'phone', true)); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const profileForm = document.getElementById('profileForm');
  profileForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'update_profile');
    formData.append('nonce', '<?php echo wp_create_nonce('mentor_dashboard_nonce'); ?>');
    formData.append('user_id', '<?php echo $user_id; ?>');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showNotification('Profile updated successfully!', 'success');
      } else {
        showNotification('Failed to update profile.', 'danger');
      }
    });
  });

  function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
    notification.innerHTML = `
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 5000);
  }
});
</script>

<?php get_footer(); ?>