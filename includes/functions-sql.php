<?php


add_action('wp_loaded', function ()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_goals';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        goal TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
});



function create_urmentor_tables() {
    global $wpdb;

    // Check if the tables already exist to prevent them from being created again
    if($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}appointment_feedback'") !== $wpdb->prefix . 'appointment_feedback') {
        // SQL query to create wp_appointment_feedback table
        $feedback_table_sql = "
            CREATE TABLE {$wpdb->prefix}appointment_feedback (
                feedback_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT(20) UNSIGNED NOT NULL,
                feedback_short_note TEXT,
                feedback_voice_notes VARCHAR(255),
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (feedback_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        // Execute the query to create the table
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($feedback_table_sql);
    }

    // Check if the table wp_appointment_expense exists
    if($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}appointment_expense'") !== $wpdb->prefix . 'appointment_expense') {
        // SQL query to create wp_appointment_expense table
        $expense_table_sql = "
            CREATE TABLE {$wpdb->prefix}appointment_expense (
                expense_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id BIGINT(20) UNSIGNED NOT NULL,
                expense_amount DECIMAL(10,2) NOT NULL,
                expense_description TEXT,
                expense_receipt VARCHAR(255),
                expense_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (expense_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        // Execute the query to create the table
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($expense_table_sql);
    }
}

// Hook into WordPress activation or theme setup to run the function
add_action('after_switch_theme', 'create_urmentor_tables');
