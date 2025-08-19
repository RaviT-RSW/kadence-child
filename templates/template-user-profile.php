<?php
/**
 * Template Name: User Profile
 */
get_header();

if (!is_user_logged_in()) {
    wp_redirect(wp_login_url());
    exit;
}

$current_user = wp_get_current_user();
$user_id      = $current_user->ID;
$user_info    = get_userdata($user_id);

$profile_pic_id  = get_user_meta($user_id, 'custom_profile_picture', true);



$profile_pic_id  = get_user_meta($user_id, 'custom_profile_picture', true);

if ($profile_pic_id) {
    // Get uploaded profile pic
    $profile_pic_url = wp_get_attachment_url($profile_pic_id);
} else {
    // Fallback to Gravatar, then placeholder
    $profile_pic_url = get_avatar_url($user_id, ['size' => 150]);
    if (!$profile_pic_url) {
        $profile_pic_url = 'https://via.placeholder.com/150';
    }
}


?>

<div class="container-fluid">
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
          <form id="profileForm" enctype="multipart/form-data">
            <!-- Profile Picture -->
            <div class="text-center mb-3">
              <img id="profilePicPreview"
                   src="<?php echo esc_url($profile_pic_url); ?>"
                   alt="Profile Picture"
                   class="rounded-circle border d-block mx-auto"
                   style="width:120px; height:120px; object-fit:cover; cursor:pointer;">
              <input type="file" id="profilePicInput" name="profile_picture" accept="image/*" style="display:none;">
              <p class="text-muted small mt-2">Click image to change</p>
            </div>
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
  document.addEventListener("DOMContentLoaded", function () {
    const profilePic = document.getElementById("profilePicPreview");
    const fileInput  = document.getElementById("profilePicInput");
    const profileForm = document.getElementById("profileForm");

    // Click image → open file input
    profilePic.addEventListener("click", () => fileInput.click());

    // Instant preview
    fileInput.addEventListener("change", (event) => {
      if (event.target.files.length > 0) {
        const reader = new FileReader();
        reader.onload = (e) => {
          profilePic.src = e.target.result;
        };
        reader.readAsDataURL(event.target.files[0]);
      }
    });

    // AJAX submit
    profileForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const formData = new FormData(this);

      formData.append("action", "update_profile");
      formData.append("nonce", "<?php echo wp_create_nonce('mentor_dashboard_nonce'); ?>");
      formData.append("user_id", "<?php echo $user_id; ?>");

      fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
        method: "POST",
        body: formData
      })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          showNotification("Profile updated successfully!", "success");
        } else {
          showNotification(data.data?.message || "Failed to update profile.", "danger");
        }
      });
    });

    function showNotification(message, type = "info") {
      const notification = document.createElement("div");
      notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
      notification.style.cssText = "top: 20px; right: 20px; z-index: 9999; max-width: 300px;";
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