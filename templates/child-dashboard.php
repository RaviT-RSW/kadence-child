<?php
/**
 * Template Name: Child Dashboard
 */
get_header();

?>

<!-- Mentor Dashboard Template with Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<!-- Hero Section -->
<section class="entry-hero page-hero-section entry-hero-layout-standard">
  <div class="entry-hero-container-inner">
    <div class="hero-section-overlay"></div>
    <div class="hero-container site-container">
      <header class="entry-header page-title title-align-inherit title-tablet-align-inherit title-mobile-align-inherit">
        <h1 class="entry-title">Child Dashboard</h1>
      </header>
    </div>
  </div>
</section>

<div class="container py-4">
<!-- Dashboard Welcome -->
<?php
// Default to current logged-in user
$user = wp_get_current_user();

// If ?child_id is in the URL and it's a valid user ID, use that instead
if (isset($_GET['child_id']) && is_numeric($_GET['child_id'])) {
    $child_id = intval($_GET['child_id']);
    $child_user = get_user_by('id', $child_id);
    
    if ($child_user instanceof WP_User) {
        $user = $child_user;
    }
}

$display_name = esc_html($user->display_name);
?>

<!-- Dashboard Welcome -->
<div class="alert alert-primary rounded-4 shadow-sm">
  <h4 class="mb-1">Welcome back, <?php echo $display_name; ?></h4>
  <p class="mb-0">Here’s what’s coming up for you.</p>
</div>

  <!-- Target Goals -->
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card border-success shadow-sm">
        <div class="card-body">
          <h5 class="card-title">🎯 Goal 1</h5>
          <p class="card-text">[Goal description here]</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-success shadow-sm">
        <div class="card-body">
          <h5 class="card-title">🎯 Goal 2</h5>
          <p class="card-text">[Goal description here]</p>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-success shadow-sm">
        <div class="card-body">
          <h5 class="card-title">🎯 Goal 3</h5>
          <p class="card-text">[Goal description here]</p>
        </div>
      </div>
    </div>
  </div>


  
  <!-- Next Session -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5 class="card-title">📅 Next Session</h5>
      <p class="mb-1">Service: <strong>Math Tutoring</strong></p>
      <p class="mb-1">Time: <strong>15 Aug 2025, 10:00 AM</strong></p>
      <p class="mb-1"><i class="fas fa-user me-2"></i>Mentor: <strong>John Doe</strong></p>
      <p class="mb-1"><i class="fas fa-map-marker-alt me-2"></i>Location: <strong>Room 102</strong></p>

      <p class="mb-1"><i class="fas fa-video me-2"></i>Zoom Meeting</p>
      <a href="https://zoom.us/j/1234567890" class="btn btn-sm btn-primary mt-2" target="_blank">Join Zoom Session</a>
    </div>
  </div>

  <!-- Upcoming Sessions -->
  <div class="card mb-4 shadow-sm">
    <div class="card-body">
      <h5 class="card-title">🗓️ Upcoming Sessions</h5>
      <ul class="list-group list-group-flush">
        <li class="list-group-item">18 Aug 2025, 02:00 PM</li>
        <li class="list-group-item">22 Aug 2025, 11:00 AM</li>
        <li class="list-group-item">25 Aug 2025, 09:30 AM</li>
      </ul>
    </div>
  </div>



<!-- Chat (Placeholder for Wise Chat Pro) -->
<div class="card mb-4 shadow-sm">
  <div class="card-body">
    <h5 class="card-title">💬 Chat with Your Mentor</h5>
    <!-- Wise Chat Placeholder -->
    <div class="alert alert-secondary" role="alert">
      Chat interface will appear here.
    </div>
  </div>
</div>

  <!-- Emergency Contact -->
<div class="card mb-4 shadow-sm bg-light">
  <div class="card-body">
    <h5 class="card-title text-danger">🚨 Emergency Contacts</h5>
    <ul class="list-group">
      <li class="list-group-item"><a href="tel:999" class="text-danger text-decoration-none">📞 Police – 999</a></li>
      <li class="list-group-item"><a href="tel:998" class="text-danger text-decoration-none">📞 Ambulance – 998</a></li>
      <li class="list-group-item"><a href="tel:997" class="text-danger text-decoration-none">📞 Fire – 997</a></li>
      <li class="list-group-item"><a href="tel:+11234567890" class="text-primary text-decoration-none">📞 Parent – +1 123 456 7890</a></li>
    </ul>
  </div>
</div>


<!-- Help Button -->
<div class="card mb-4 shadow-sm">
  <div class="card-body">
    <h5 class="card-title">❓ Need Help?</h5>
    <form action="#" method="POST">
      <div class="mb-2">
        <label for="help_reason" class="form-label">Select a reason</label>
        <select id="help_reason" name="reason" class="form-select">
          <option>I feel upset and want to talk</option>
          <option>I don’t understand something</option>
          <option>Something bad happened</option>
          <option>Different Reason</option>
        </select>
      </div>
      <div class="mb-2">
        <label for="help_message" class="form-label">Short message</label>
        <textarea id="help_message" name="message" class="form-control" rows="2" placeholder="Tell us more if you want to..."></textarea>
      </div>
      <button type="submit" class="btn btn-warning">Submit to Admin</button>
    </form>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php get_footer(); ?>