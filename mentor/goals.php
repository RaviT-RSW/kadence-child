<?php

/**
 * Handle Insert/Update for goals
 */
add_action('init', function () {
    if (isset($_POST['submit_goals'])) {
        if (!isset($_POST['user_goals_nonce']) || !wp_verify_nonce($_POST['user_goals_nonce'], 'save_user_goals')) {
            wp_die('Security check failed.');
        }

        if (!is_user_logged_in()) {
            wp_die('You must be logged in.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'user_goals';
        $user_id =  isset($_GET['child_id']) ? $_GET['child_id']: get_current_user_id();

        $goal_ids = array_map('intval', $_POST['goal_ids']);
        $goals = array_map('sanitize_text_field', $_POST['goals']);

        foreach ($goals as $index => $goal) {
            $goal_id = $goal_ids[$index];

            if (!empty($goal_id)) {
                // Update existing goal
                $wpdb->update(
                    $table_name,
                    [
                        'goal' => $goal,
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $goal_id, 'user_id' => $user_id]
                );
            } else {
                // Insert new goal
                if (!empty($goal)) {
                    $wpdb->insert(
                        $table_name,
                        [
                            'user_id' => $user_id,
                            'goal' => $goal,
                            'status' => 'pending',
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql'),
                        ]
                    );
                }
            }
        }

        // Redirect to avoid resubmission
        wp_redirect(add_query_arg('goals_saved', '1', wp_get_referer()));
        exit;
    }
});


/**
 * Shortcode to display the modal and form
 */

add_shortcode('mentor_set_goals_form', function () {
    if (!is_user_logged_in()) {
        return ;//'<p>You must be logged in to set goals.</p>';
    }
	
	global $wpdb;

	// Fetch existing goals for the current user
    $existing_goals = $wpdb->get_results(
        $wpdb->prepare("SELECT id, goal FROM ".CHILD_GOAL_TABLE." WHERE user_id = %d ORDER BY id ASC LIMIT 3", $_GET['child_id']),
        ARRAY_A
    );

    // Ensure we always have 3 slots
    for ($i = count($existing_goals); $i < 3; $i++) {
        $existing_goals[] = ['id' => '', 'goal' => ''];
    }

    ob_start();
    ?>
<!-- Trigger button -->
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#goalsModal">
        <?php echo !empty($existing_goals[0]['goal']) ? 'Update Goals' : 'Set Goals'; ?>
    </button>

    <!-- Bootstrap Modal -->
    <div class="modal fade" id="goalsModal" tabindex="-1" aria-labelledby="goalsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="goalsModalLabel">
                            <?php echo !empty($existing_goals[0]['goal']) ? 'Update Your Goals' : 'Enter Your 3 Goals'; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php wp_nonce_field('save_user_goals', 'user_goals_nonce'); ?>
                        <?php foreach ($existing_goals as $i => $goal): ?>
                            <input type="hidden" name="goal_ids[]" value="<?php echo esc_attr($goal['id']); ?>">
                            <div class="mb-3">
                                <label for="goal<?php echo $i+1; ?>" class="form-label">Goal <?php echo $i+1; ?></label>
                                <input type="text" class="form-control" name="goals[]" id="goal<?php echo $i+1; ?>"
                                       value="<?php echo esc_attr($goal['goal']); ?>" required>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="submit_goals" class="btn btn-primary">
                            <?php echo !empty($existing_goals[0]['goal']) ? 'Update Goals' : 'Save Goals'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
});


function get_child_goal($child_id = '')
{
	if(empty($child_id)) {
		$child_id = get_current_user_id();
	}

    $user_id = intval($child_id);

	global $wpdb;

    // Get 3 latest goals for this user
    $goals = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT goal FROM ".CHILD_GOAL_TABLE." WHERE user_id = %d ORDER BY id ASC LIMIT 3",
            $user_id
        )
    );

    // Ensure we always have 3 entries (empty if missing)
    for ($i = count($goals); $i < 3; $i++) {
        $goals[] = '';
    }
    return $goals;
}