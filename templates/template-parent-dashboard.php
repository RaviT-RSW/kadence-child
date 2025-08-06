<?php
/**
 * Template Name: Parent Dashboard
 */
get_header();
?>

<!-- Parent Dashboard Template with Bootstrap 5 and Child Cards -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Hero Section from Kadence -->
<section class="entry-hero page-hero-section entry-hero-layout-standard">
  <div class="entry-hero-container-inner">
    <div class="hero-section-overlay"></div>
    <div class="hero-container site-container">
      <header class="entry-header page-title title-align-inherit title-tablet-align-inherit title-mobile-align-inherit">
        <h1 class="entry-title">Parent Dashboard</h1>
      </header>
    </div>
  </div>
</section>
<div class="container my-5">
  <!-- Welcome Section -->
  <div class="mb-4">
    <h2 class="mb-1">Welcome, <span class="text-primary"><?php echo wp_get_current_user()->display_name; ?></span></h2>
    <p class="text-muted">Manage your child's mentoring sessions, view progress, and stay connected.</p>
  </div>

  <div class="d-flex justify-content-end mb-3">
    <a href="<?php echo esc_url(site_url('/add-child/')); ?>" class="btn btn-outline-success">
      <strong>+ Add Child</strong>
    </a>
  </div>

  <!-- Child Card Selector -->
  <div class="row g-4 mb-4">
    <?php
    $current_user = wp_get_current_user();
    $parent_id = $current_user->ID;

    // Fetch children of current parent
    $children = get_users(array(
        'role'    => 'child_user',
        'meta_key'   => 'assigned_parent_id',
        'meta_value' => $parent_id,
    ));

    if (!empty($children)) :
        foreach ($children as $child) :
            // Optional: Use a custom user meta for profile image, fallback to default
            $profile_image = get_user_meta($child->ID, 'profile_picture_url', true);
            if (!$profile_image) {
                $profile_image = get_stylesheet_directory_uri() . '/assets/images/user_default.png';
            }
            ?>
            <div class="col-md-6">
              <div class="card child-card border-primary shadow-sm h-100">
                <div class="card-body text-center">
                  <img src="<?php echo esc_url($profile_image); ?>" class="rounded-circle mb-3" alt="Child Image" width="100" height="100">
                  <h5 class="card-title"><?php echo esc_html($child->display_name); ?></h5>
                  <a href="<?php echo esc_url(add_query_arg('child_id', $child->ID, site_url('/child-dashboard/'))); ?>" class="btn btn-outline-primary w-100">
                    View <?php echo esc_html($child->display_name); ?>'s Dashboard
                  </a>
                </div>
              </div>
            </div>
        <?php endforeach; ?>
          <!-- Dashboard Content Area -->
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h4 class="card-title">Next Session</h4>
            <ul class="list-unstyled">
              <li><strong>Date:</strong> August 5, 2025</li>
              <li><strong>Time:</strong> 4:00 PM</li>
              <li><strong>Location:</strong> Online</li>
              <li><strong>Mentor:</strong> John Smith</li>
            </ul>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h4 class="card-title">Upcoming Sessions</h4>
            <ul class="list-group list-group-flush">
              <li class="list-group-item">Aug 10 - 4:00 PM with John Smith</li>
              <li class="list-group-item">Aug 17 - 4:00 PM with John Smith</li>
            </ul>
          </div>
        </div>

   <?php 
    $mentoring_plan_info = get_field('mentoring_plan_info', 'user_20');
    $mentoring_plan_file = get_field('mentoring_plan_file', 'user_20');
    ?>
    
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="card-title mb-0">Mentoring Plan</h4>
                <?php if ($mentoring_plan_file): ?>
                    <a href="<?php echo esc_url($mentoring_plan_file['url']); ?>" 
                       class="btn btn-primary" 
                       target="_blank" 
                       download>
                        Download Plan (PDF)
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($mentoring_plan_info): ?>
                <div class="mentoring-plan-info">
                    <?php echo wp_kses_post($mentoring_plan_info); ?>
                </div>
            <?php else: ?>
                <p>No mentoring plan information available.</p>
            <?php endif; ?>
        </div>
    </div>

        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h4 class="card-title">Goals for Aryan</h4>
            <ol class="ps-3">
              <li>Improve communication skills</li>
              <li>Attend weekly mentoring sessions</li>
              <li>Complete journal entries</li>
            </ol>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h4 class="card-title">Session Feedback</h4>
            <ul class="list-group list-group-flush">
              <li class="list-group-item">July 28 - "Great progress today." <a href="#">Listen</a></li>
              <li class="list-group-item">July 21 - "Needs support with reading."</li>
            </ul>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h4 class="card-title">Invoices</h4>
            <ul class="list-group list-group-flush">
              <li class="list-group-item">Invoice #1001 <a href="#">Download PDF</a></li>
              <li class="list-group-item">Invoice #1002 <a href="#">Download PDF</a></li>
            </ul>
          </div>
        </div>

        <div class="card shadow-sm mb-5">
          <div class="card-body d-flex flex-wrap gap-3">
            <a href="https://wa.me/MENTORNUMBER" class="btn btn-success">Contact Mentor</a>
            <a href="mailto:admin@example.com" class="btn btn-dark">Contact Admin</a>
          </div>
        </div>
        
    <?php else : ?>
        <div class="col-12">
          <div class="alert alert-warning text-center">
            No children assigned to your account.
          </div>
        </div>
    <?php endif; ?>
  </div>



</div>

<style>
  .child-card img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    justify-self: center;
    align-items: center;

  }
  .child-card .btn {
    margin-top: 10px;
  }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php get_footer(); ?>
