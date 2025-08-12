<?php

// Need help section for child
// 1. Handle form submission
function handle_help_form_submission() {
	if (isset($_POST['help_form_submit']) && wp_verify_nonce($_POST['help_nonce'], 'help_form_action')) {

		if (!is_user_logged_in()) {
			wp_die('You must be logged in to submit help requests.');
		}

		$user_id = get_current_user_id();
		$reason = sanitize_text_field($_POST['reason']);
		$message = sanitize_textarea_field($_POST['message']);

		// Create help request data
		$help_data = array(
			'reason' => $reason,
			'message' => $message,
			'date' => current_time('mysql'),
			'status' => 'pending',
			'user_name' => wp_get_current_user()->display_name,
			'user_email' => wp_get_current_user()->user_email
		);

		// Store in user meta
		$existing_requests = get_user_meta($user_id, 'help_requests', true);
		if (!is_array($existing_requests)) {
			$existing_requests = array();
		}

		$existing_requests[] = $help_data;
		update_user_meta($user_id, 'help_requests', $existing_requests);

		// Also store globally for admin view
		$global_requests = get_option('all_help_requests', array());
		$help_data['user_id'] = $user_id;
		$help_data['id'] = uniqid();
		$global_requests[] = $help_data;
		update_option('all_help_requests', $global_requests);

		// Redirect with success parameter and timestamp
		$redirect_url = add_query_arg(array(
			'help_success' => '1',
			'timestamp' => time()
		), $_SERVER['HTTP_REFERER']);

		wp_redirect($redirect_url);

		exit;
	}
}
add_action('init', 'handle_help_form_submission');

// 2. Display success message
function show_help_form_message() {
	if (isset($_GET['help_success']) && $_GET['help_success'] == '1' && isset($_GET['timestamp'])) {
		$timestamp = intval($_GET['timestamp']);
		$current_time = time();

		// Show message only for 30 seconds after submission
		if (($current_time - $timestamp) < 30) { ?>
			<!-- Success Popup Modal -->
			<div id="help-success-popup" style="
				display: none;
				position: fixed;
				z-index: 9999;
				left: 0;
				top: 0;
				width: 100%;
				height: 100%;
				background-color: rgba(0,0,0,0.5);">
				<div style="
					background-color: #fff;
					margin: 15% auto;
					padding: 20px;
					border-radius: 8px;
					width: 90%;
					max-width: 400px;
					text-align: center;
					box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); ">
					<div style="
						color: #28a745;
						font-size: 48px;
						margin-bottom: 15px;">✓</div>
					<h3 style="
						color: #28a745;
						margin-bottom: 10px;
						font-size: 18px;">Success!</h3>
					<p style="
						color: #333;
						margin-bottom: 20px;
						font-size: 14px;">Your help request has been submitted successfully!</p>
					<button id="close-popup" style="
						background-color: #28a745;
						color: white;
						border: none;
						padding: 10px 20px;
						border-radius: 4px;
						cursor: pointer;
						font-size: 14px;
					">OK</button>
				</div>
			</div>

			<script type="text/javascript">
				jQuery(document).ready(function($) {
					// Show the popup
					$('#help-success-popup').fadeIn('fast');

					// Close popup when OK button is clicked
					$('#close-popup').click(function() {
						$('#help-success-popup').fadeOut('fast');
						cleanUrl();
					});

					// Close popup when clicking outside the modal
					$('#help-success-popup').click(function(e) {
						if (e.target.id === 'help-success-popup') {
							$(this).fadeOut('fast');
							cleanUrl();
						}
					});

					// Auto-close popup after 10 seconds
					setTimeout(function() {
						$('#help-success-popup').fadeOut('slow', function() {
							cleanUrl();
						});
					}, 10000);

					// Function to clean URL
					function cleanUrl() {
						if (window.history && window.history.replaceState) {
							var url = new URL(window.location);
							url.searchParams.delete('help_success');
							url.searchParams.delete('timestamp');
							window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
						}
					}

					// Handle ESC key to close popup
					$(document).keyup(function(e) {
						if (e.keyCode === 27) { // ESC key
							$('#help-success-popup').fadeOut('fast');
							cleanUrl();
						}
					});
				});
			</script>

			<?php
		}
	}
}

// 3. Shortcode for the help form
function help_form_shortcode($atts) {
	if (!is_user_logged_in()) {
		return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">login</a> to submit help requests.</p>';
	}

	ob_start();
	show_help_form_message();
	?>

	<!-- Floating Help Button -->
	<div id="helpButton">Help</div>

	<!-- Floating Help Form Panel -->
	<div id="helpFormPanel">
		<form method="POST" id="helpForm">
			<?php wp_nonce_field('help_form_action', 'help_nonce'); ?>
			<input type="hidden" name="reason" id="selectedReason" required>

			<h3>Need Help?</h3>

			<div class="help-reasons-list">
				<div class="help-reason-card" data-reason="upset">
					<div class="help-icon">😢</div>
					<div class="help-text">I feel upset and want to talk</div>
				</div>
				<div class="help-reason-card" data-reason="confused">
					<div class="help-icon">❓</div>
					<div class="help-text">I don't understand something</div>
				</div>
				<div class="help-reason-card" data-reason="incident">
					<div class="help-icon">⚠️</div>
					<div class="help-text">Something bad happened</div>
				</div>
				<div class="help-reason-card" data-reason="other">
					<div class="help-icon">💭</div>
					<div class="help-text">Different Reason</div>
				</div>
			</div>

			<div class="mb-3">
				<textarea id="help_message" name="message" class="form-control" rows="2" placeholder="Tell us more..."></textarea>
			</div>

			<button type="submit" name="help_form_submit" class="btn btn-warning" id="submitBtn" disabled>Submit to Admin</button>
		</form>
	</div>

	<script>
	document.addEventListener('DOMContentLoaded', function() {
		const helpButton = document.getElementById('helpButton');
		const helpFormPanel = document.getElementById('helpFormPanel');
		const reasonCards = document.querySelectorAll('.help-reason-card');
		const selectedReasonInput = document.getElementById('selectedReason');
		const submitBtn = document.getElementById('submitBtn');

		// Toggle help panel with animation
		helpButton.addEventListener('click', function() {
			helpFormPanel.classList.toggle('open');
		});

		// Select reason
		reasonCards.forEach(card => {
			card.addEventListener('click', function() {
				reasonCards.forEach(c => c.classList.remove('selected'));
				this.classList.add('selected');
				selectedReasonInput.value = this.getAttribute('data-reason');
				submitBtn.disabled = false;
			});
		});
	});
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode('help_form', 'help_form_shortcode');

// 4. Add admin menu page
function add_help_requests_admin_menu() {
	add_menu_page(
		'Help Requests',
		'Help Requests',
		'manage_options',
		'help-requests',
		'display_help_requests_page',
		'dashicons-sos',
		30
	);
}
add_action('admin_menu', 'add_help_requests_admin_menu');

// 5. Display help requests in admin
function display_help_requests_page() {
	// Handle status updates
	if (isset($_POST['update_status']) && isset($_POST['request_id'])) {
		$request_id = sanitize_text_field($_POST['request_id']);
		$new_status = sanitize_text_field($_POST['status']);

		$requests = get_option('all_help_requests', array());
		foreach ($requests as &$request) {
			if ($request['id'] == $request_id) {
				$request['status'] = $new_status;
				break;
			}
		}
		update_option('all_help_requests', $requests);
		echo '<div class="notice notice-success"><p>Status updated successfully!</p></div>';
	}

	// Handle delete request
	if (isset($_POST['delete_request']) && isset($_POST['request_id'])) {
		$request_id = sanitize_text_field($_POST['request_id']);
		$requests = get_option('all_help_requests', array());
		$deleted_request = null;

		// Find the request being deleted for the confirmation message
		foreach ($requests as $request) {
			if ($request['id'] === $request_id) {
				$deleted_request = $request;
				break;
			}
		}

		$requests = array_filter($requests, function($request) use ($request_id) {
			return $request['id'] !== $request_id;
		});

		update_option('all_help_requests', array_values($requests));

		// Redirect with delete success parameter
		$redirect_url = add_query_arg(array(
			'delete_success' => '1',
			'deleted_user' => urlencode($deleted_request['user_name']),
			'timestamp' => time()
		), $_SERVER['REQUEST_URI']);

		wp_redirect($redirect_url);
		exit;
	}

	$requests = get_option('all_help_requests', array());
	$requests = array_reverse($requests); // Show newest first

	// Show delete success popup
	show_delete_success_popup();
	?>
	<div class="wrap">
		<h1>Help Requests</h1>

		<!-- Filter Form -->
		<form method="GET" style="margin-bottom: 15px; float: right;">
			<input type="hidden" name="page" value="help-requests">

			<!-- Name Filter -->
			<input type="text" name="filter_name" placeholder="Search by name" value="<?php echo esc_attr($_GET['filter_name'] ?? ''); ?>">

			<!-- Status Filter -->
			<select name="filter_status" >
				<option value="">All Status</option>
				<option value="pending" <?php selected($_GET['filter_status'] ?? '', 'pending'); ?>>Pending</option>
				<option value="in_progress" <?php selected($_GET['filter_status'] ?? '', 'in_progress'); ?>>In Progress</option>
				<option value="resolved" <?php selected($_GET['filter_status'] ?? '', 'resolved'); ?>>Resolved</option>
			</select>

			<!-- Reason Filter -->
			<select name="filter_reason" >
				<option value="">All Reasons</option>
				<option value="upset" <?php selected($_GET['filter_reason'] ?? '', 'upset'); ?>>Feels upset</option>
				<option value="confused" <?php selected($_GET['filter_reason'] ?? '', 'confused'); ?>>Confused</option>
				<option value="incident" <?php selected($_GET['filter_reason'] ?? '', 'incident'); ?>>Bad incident</option>
				<option value="other" <?php selected($_GET['filter_reason'] ?? '', 'other'); ?>>Other reason</option>
			</select>

			<button type="submit" class="button">Filter</button>
			<a href="<?php echo admin_url('admin.php?page=help-requests'); ?>" class="button">Reset</a>
		</form>

		<?php
		$requests = get_option('all_help_requests', array());

		// Sort so that "pending" comes first
		usort($requests, function($a, $b) {
		    // Ensure 'status' key exists
		    $statusPending = isset($a['status']) ? strtolower($a['status']) : '';
		    $statusNonePending = isset($b['status']) ? strtolower($b['status']) : '';

		    // If both have same status, keep original order
		    if ($statusPending === $statusNonePending) {
		        return 0;
		    }

		    // Pending first
		    if ($statusPending === 'pending') {
		        return -1;
		    }
		    if ($statusNonePending === 'pending') {
		        return 1;
		    }

		    // Otherwise sort alphabetically (optional)
		    return strcmp($statusPending, $statusNonePending);
		});


		// Apply filters
		if (!empty($_GET['filter_name'])) {
			$name_filter = strtolower(sanitize_text_field($_GET['filter_name']));
			$requests = array_filter($requests, function($r) use ($name_filter) {
				return strpos(strtolower($r['user_name']), $name_filter) !== false;
			});
		}

		if (!empty($_GET['filter_status'])) {
			$status_filter = sanitize_text_field($_GET['filter_status']);
			$requests = array_filter($requests, function($r) use ($status_filter) {
				return $r['status'] === $status_filter;
			});
		}

		if (!empty($_GET['filter_reason'])) {
			$reason_filter = sanitize_text_field($_GET['filter_reason']);
			$requests = array_filter($requests, function($r) use ($reason_filter) {
				return $r['reason'] === $reason_filter;
			});
		}

		?>

		<form method="POST" id="help-requests-form">
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th>User</th>
						<th>Reason</th>
						<th>Message</th>
						<th>Status</th>
						<th>Date</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!empty($requests)):
					foreach ($requests as $request): ?>
					<tr>
						<td>
							<span style="font-weight: bold;"><?php echo esc_html($request['user_name']); ?></span><br>
							<small><?php echo esc_html($request['user_email']); ?></small>
						</td>
						<td>
							<?php 
								$reasons = array(
									'upset' => 'Feels upset',
									'confused' => 'Confused',
									'incident' => 'Bad incident',
									'other' => 'Other reason'
								);
								echo $reasons[$request['reason']] ?? $request['reason'];
								?>
						</td>
						<td><?php echo esc_html($request['message'] ?: 'No message provided'); ?></td>
						<td>
							<span class="status-badge status-<?php echo $request['status']; ?>">
								<?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
							</span>
						</td>
						<td>
							<?php echo human_time_diff( strtotime( $request['date'] ), current_time( 'timestamp' ) ) . ' ago'; ?>
						</td>
						<td class="actions-column">
							<form method="POST" style="display: inline;" class="status-form">
								<input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
								<select name="status" onchange="this.form.submit()" class="small-select">
									<option value="pending" <?php selected($request['status'], 'pending'); ?>>Pending</option>
									<option value="in_progress" <?php selected($request['status'], 'in_progress'); ?>>In Progress</option>
									<option value="resolved" <?php selected($request['status'], 'resolved'); ?>>Resolved</option>
								</select>
								<input type="hidden" name="update_status" value="1">
							</form>
							<button type="button" class="delete-btn" onclick="showDeleteConfirmation('<?php echo esc_js($request['id']); ?>', '<?php echo esc_js($request['user_name']); ?>')"> 🗑️ Delete </button>
						</td>
					</tr>
					<?php endforeach;
					else: ?>
						<tr>
							<td colspan="6" style="text-align:center;">No help requests found</td>
						</tr>
					<?php endif; ?>
				</tbody>

            </table>
        </form>
    </div>

    <!-- Delete Confirmation Popup -->
    <div id="delete-confirmation-popup" style="
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5); ">
        <div style="
            background-color: #fff;
            margin: 15% auto;
            padding: 25px;
            border-radius: 8px;
            width: 90%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        ">
            <div style="
                color: #dc3545;
                font-size: 48px;
                margin-bottom: 15px;
            ">⚠️</div>
            <h3 style="
                color: #dc3545;
                margin-bottom: 15px;
                font-size: 20px;
                font-weight: 600;
            ">Delete Help Request</h3>
            <p style="
                color: #333;
                margin-bottom: 10px;
                font-size: 15px;
                line-height: 1.5;
            ">Are you sure you want to delete the help request from:</p>
            <p style="
                color: #333;
                margin-bottom: 20px;
                font-size: 16px;
                font-weight: 600;
            " id="delete-user-name">User Name</p>
            <p style="
                color: #666;
                margin-bottom: 25px;
                font-size: 13px;
                font-style: italic;
            ">This action cannot be undone.</p>

            <div style="display: flex; gap: 10px; justify-content: center;">
                <button id="cancel-delete" style="
                    background-color: #6c757d;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 14px;
                    min-width: 80px;
                ">Cancel</button>

                <button id="confirm-delete" style="
                    background-color: #dc3545;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 14px;
                    min-width: 80px;
                ">Delete</button>
            </div>
        </div>
    </div>

    <!-- Hidden form for actual deletion -->
    <form method="POST" id="hidden-delete-form" style="display: none;">
        <input type="hidden" name="request_id" id="delete-request-id">
        <input type="hidden" name="delete_request" value="1">
    </form>

    <script type="text/javascript">
    let currentDeleteId = null;

    function showDeleteConfirmation(requestId, userName) {
        currentDeleteId = requestId;
        document.getElementById('delete-user-name').textContent = userName;
        document.getElementById('delete-confirmation-popup').style.display = 'block';
    }

    // Close popup functions
    function closeDeletePopup() {
        document.getElementById('delete-confirmation-popup').style.display = 'none';
        currentDeleteId = null;
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Cancel button
        document.getElementById('cancel-delete').addEventListener('click', closeDeletePopup);

        // Confirm delete button
        document.getElementById('confirm-delete').addEventListener('click', function() {
            if (currentDeleteId) {
                document.getElementById('delete-request-id').value = currentDeleteId;
                document.getElementById('hidden-delete-form').submit();
            }
        });

        // Close when clicking outside popup
        document.getElementById('delete-confirmation-popup').addEventListener('click', function(e) {
            if (e.target.id === 'delete-confirmation-popup') {
                closeDeletePopup();
            }
        });

        // Close with ESC key
        document.addEventListener('keyup', function(e) {
            if (e.keyCode === 27) { // ESC key
                closeDeletePopup();
            }
        });
    });
    </script>
    <?php
}

// Function to show delete success popup
function show_delete_success_popup() {
    if (isset($_GET['delete_success']) && $_GET['delete_success'] == '1' && isset($_GET['timestamp'])) {
        $timestamp = intval($_GET['timestamp']);
        $current_time = time();
        $deleted_user = isset($_GET['deleted_user']) ? urldecode($_GET['deleted_user']) : 'User';

        // Show message only for 30 seconds after deletion
        if (($current_time - $timestamp) < 30) {
            ?>
            <!-- Delete Success Popup Modal -->
            <div id="delete-success-popup" style="
                display: none;
                position: fixed;
                z-index: 9999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5); ">
                <div style="
                    background-color: #fff;
                    margin: 15% auto;
                    padding: 25px;
                    border-radius: 8px;
                    width: 90%;
                    max-width: 400px;
                    text-align: center;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                ">
                    <div style="
                        color: #dc3545;
                        font-size: 48px;
                        margin-bottom: 15px;
                    ">🗑️</div>
                    <h3 style="
                        color: #dc3545;
                        margin-bottom: 10px;
                        font-size: 18px;
                    ">Deleted Successfully!</h3>
                    <p style="
                        color: #333;
                        margin-bottom: 5px;
                        font-size: 14px;
                    ">Help request from <strong><?php echo esc_html($deleted_user); ?></strong></p>
                    <p style="
                        color: #333;
                        margin-bottom: 20px;
                        font-size: 14px;
                    ">has been deleted successfully.</p>
                    <button id="close-delete-popup" style="
                        background-color: #dc3545;
                        color: white;
                        border: none;
                        padding: 10px 20px;
                        border-radius: 4px;
                        cursor: pointer;
                        font-size: 14px;
                    ">OK</button>
                </div>
            </div>

            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Show the popup
                    $('#delete-success-popup').fadeIn('fast');

                    // Close popup when OK button is clicked
                    $('#close-delete-popup').click(function() {
                        $('#delete-success-popup').fadeOut('fast');
                        cleanDeleteUrl();
                    });

                    // Close popup when clicking outside the modal
                    $('#delete-success-popup').click(function(e) {
                        if (e.target.id === 'delete-success-popup') {
                            $(this).fadeOut('fast');
                            cleanDeleteUrl();
                        }
                    });

                    // Auto-close popup after 8 seconds
                    setTimeout(function() {
                        $('#delete-success-popup').fadeOut('slow', function() {
                            cleanDeleteUrl();
                        });
                    }, 8000);

                    // Function to clean URL
                    function cleanDeleteUrl() {
                        if (window.history && window.history.replaceState) {
                            var url = new URL(window.location);
                            url.searchParams.delete('delete_success');
                            url.searchParams.delete('deleted_user');
                            url.searchParams.delete('timestamp');
                            window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
                        }
                    }

                    // Handle ESC key to close popup
                    $(document).keyup(function(e) {
                        if (e.keyCode === 27) { // ESC key
                            $('#delete-success-popup').fadeOut('fast');
                            window.location.reload();
                        }
                    });
                });
            </script>
            <?php
        }
    }
}

// 6. Add admin dashboard widget
function add_help_requests_dashboard_widget() {
    wp_add_dashboard_widget(
        'help_requests_widget',
        'Recent Help Requests',
        'help_requests_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'add_help_requests_dashboard_widget');

function help_requests_dashboard_widget_content() {
    $requests = get_option('all_help_requests', array());
    $recent_requests = array_slice(array_reverse($requests), 0, 5);
    $pending_count = count(array_filter($requests, function($r) { return $r['status'] == 'pending'; }));

    echo '<p><strong>' . $pending_count . '</strong> pending help requests</p>';

    if (empty($recent_requests)) {
        echo '<p>No help requests found.</p>';
        return;
    }

    echo '<ul>';
    foreach ($recent_requests as $request) {
        $status_class = 'status-' . $request['status'];
        echo '<li class="' . $status_class . '">';
        echo '<strong>' . esc_html($request['user_name']) . '</strong> - ';
        echo esc_html($request['reason']) . ' ';
        echo '<small>(' . human_time_diff(strtotime($request['date'])) . ' ago)</small>';
        echo '</li>';
    }
    echo '</ul>';

    echo '<p><a href="' . admin_url('admin.php?page=help-requests') . '">View all requests →</a></p>';

    echo '<style>
    .status-pending { color: #856404; }
    .status-in_progress { color: #004085; }
    .status-resolved { color: #155724; }
    </style>';
}

// 7. Add notification for pending requests in admin bar
function add_help_requests_admin_bar_notification($wp_admin_bar) {
    if (!current_user_can('manage_options')) return;

    $requests = get_option('all_help_requests', array());
    $pending_count = count(array_filter($requests, function($r) { return $r['status'] == 'pending'; }));

    if ($pending_count > 0) {
        $wp_admin_bar->add_node(array(
            'id' => 'help-requests-notification',
            'title' => 'Help (' . $pending_count . ')',
            'href' => admin_url('admin.php?page=help-requests'),
            'meta' => array(
                'class' => 'help-requests-notification'
            )
        ));
    }
}
add_action('admin_bar_menu', 'add_help_requests_admin_bar_notification', 100);

// 8. Add CSS for admin bar notification
function help_requests_admin_styles() {
    echo '<style>
    #wp-admin-bar-help-requests-notification .ab-item {
        background-color: #d32f2f !important;
        color: white !important;
    }
    #wp-admin-bar-help-requests-notification:hover .ab-item {
        background-color: #b71c1c !important;
    }
    </style>';
}

add_action('admin_head', 'help_requests_admin_styles');
add_action('wp_head', 'help_requests_admin_styles');


// Need Help section for child end