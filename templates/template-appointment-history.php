<?php
/**
 * Template Name: Appointment History
 */
get_header();

// Get the child_id and mentor_id from the query parameter or current user
$child_id = isset($_GET['child_id']) ? intval($_GET['child_id']) : 0;
$current_mentor = wp_get_current_user();
$mentor_id = $current_mentor->ID;

if (!$child_id || !$mentor_id) {
    wp_redirect(home_url());
    exit;
}

// Fetch mentee details
$mentee = get_user_by('id', $child_id);
$mentee_name = $mentee ? $mentee->display_name : 'Unknown Mentee';

// Fetch records from wp_assigned_mentees for the specific child and mentor
global $wpdb;
$records = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}assigned_mentees WHERE child_id = %d AND mentor_id = %d ORDER BY created_at DESC",
        $child_id,
        $mentor_id
    )
);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
    .appointment-history-container {
        max-width: 1300px;
        margin: 0 auto;
        padding: 20px;
    }
    .header-section {
        background: #007bff;
        color: #ffffff;
        padding: 10px 20px;
        border-radius: 5px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .header-title {
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
    }
    .header-subtitle {
        font-size: 1rem;
        font-weight: 400;
        margin: 0;
    }
    .card {
        border: none;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }
    .table-custom {
        background-color: #ffffff;
        border-radius: 0 0 10px 10px;
    }
    .table-custom th {
        background-color: #46cdb4;
        color: #ffffff;
        font-weight: 600;
        padding: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .table-custom td {
        padding: 12px;
        vertical-align: middle;
        border-top: 1px solid #e9ecef;
    }
    .btn-view-details {
        background-color: #28a745;
        color: #ffffff;
        border: none;
        padding: 8px 15px;
        border-radius: 5px;
        font-weight: 500;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .btn-view-details:hover {
        background-color: #218838;
        transform: translateY(-2px);
        color: #ffffff;
    }
    .no-records {
        text-align: center;
        color: #6c757d;
        padding: 40px;
        font-size: 1.1rem;
        background-color: #f8f9fa;
        border-radius: 10px;
    }
    @media (max-width: 768px) {
        .header-title {
            font-size: 1.25rem;
        }
        .header-subtitle {
            font-size: 0.9rem;
        }
        .table-custom th,
        .table-custom td {
            padding: 8px;
        }
    }
</style>

<section class="entry-hero page-hero-section entry-hero-layout-standard">
    <div class="entry-hero-container-inner">
        <div class="hero-section-overlay"></div>
        <div class="hero-container site-container">
            <header class="entry-header page-title title-align-inherit title-tablet-align-inherit title-mobile-align-inherit">
                <h1 class="entry-title">Appointment History</h1>
            </header>
        </div>
    </div>
</section>

<div class="appointment-history-container my-5">
    <div class="header-section">
        <div>
            <div class="header-title">Appointment History</div>
            <div class="header-subtitle">For <span class="fw-bold"><?php echo ucfirst($mentee_name); ?></span></div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if ($records) : ?>
                <div class="table-responsive">
                    <table class="table table-custom table-hover">
                        <thead>
                            <tr>
                                <th>Mentee Name</th>
                                <th>Appointment Title</th>
                                <th>Order Amount</th>
                                <th>Appointment Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record) :
                                // Get the order
                                $order = wc_get_order($record->order_id);
                                if ($order) {
                                    // Get the first item (assuming one item per order for simplicity)
                                    $items = $order->get_items();
                                    $item = reset($items);
                                    $product_name = $item ? $item->get_name() : 'Unknown Product';
                                    $order_amount = $order->get_total();
                                    $session_date_time = $item->get_meta('session_date_time');
                                    $session_date_time_obj = new DateTime($session_date_time, new DateTimeZone('Asia/Kolkata'));
                                } else {
                                    $product_name = 'Order Not Found';
                                    $order_amount = 'N/A';
                                    $session_date_time_obj = new DateTime($record->created_at, new DateTimeZone('Asia/Kolkata'));
                                }
                            ?>
                            <tr>
                                <td><?php echo ucfirst($mentee_name); ?></td>
                                <td><?php echo esc_html($product_name); ?></td>
                                <td>$<?php echo esc_html(number_format($order_amount, 2)); ?></td>
                                <td><?php echo esc_html($session_date_time_obj->format('F d, Y - h:i A')); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(array('order_id' => $record->order_id, 'item_id' => $item ? $item->get_id() : 0), site_url('/appointment-details/'))); ?>" class="btn btn-view-details">View Details</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="no-records">
                    <p>No appointment history available for <?php echo esc_html($mentee_name); ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php get_footer(); ?>