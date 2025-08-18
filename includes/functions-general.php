<?php
// Hide admin bar for non admin START
add_action('after_setup_theme', 'show_admin_bar_for_admin_only');

function show_admin_bar_for_admin_only() {
    if (!is_user_logged_in()) {
        return;
    }

    $user = wp_get_current_user();

    if (!in_array('administrator', (array) $user->roles)) {
        show_admin_bar(false);
    }
}
// Hide admin bar for non admin END

add_filter('wp_nav_menu_items', 'add_logout_link_to_menu', 10, 2);
function add_logout_link_to_menu($items, $args) {
    $current_user = wp_get_current_user();

    if(is_user_logged_in()){
        $items .= '<li class="menu-item"><a href="' . urmentor_get_dashboard_url() . '"> Dashboard </a></li>';
        $items .= '<li class="menu-item"><a href="' . home_url('/user-profile/') . '"> Profile </a></li>';
        if ( in_array('mentor_user', (array)$current_user->roles) ){
            $items .= '<li class="menu-item"><a href="' . home_url('/working-hours/') . '"> Working Hours </a></li>';
        }
        if ( in_array('parent_user', (array)$current_user->roles) ){
            $items .= '<li class="menu-item"><a href="' . home_url('/book-session/') . '"> Book Session </a></li>';
        }   
    }
    if (is_user_logged_in() && $args->theme_location === 'primary') {
        $items .= '<li class="menu-item"><a href="' . wp_logout_url(home_url()) . '"> Logout </a></li>';
    }
	if (!is_user_logged_in() && $args->theme_location === 'primary') {
        $items .= '<li class="menu-item"><a href="' . home_url('/urmentor-login/') . '"> Login </a></li>';
    }


    return $items;
}


function urmentor_get_dashboard_url()
{
    if ( is_user_logged_in())
    {
     $current_user = wp_get_current_user();
     if(in_array( 'parent_user', (array) $current_user->roles ) )
     {
       return home_url('/parent-dashboard') ; 
     }else if(in_array( 'mentor_user', (array) $current_user->roles ) ){
       return home_url('/mentor-dashboard') ; 
     }else if(in_array( 'child_user', (array) $current_user->roles ) ){
       return home_url('/child-dashboard') ; 
     }
    }
    return home_url();
}

function dd($a, $m="")
{
    echo "<pre>";
    if(!empty($m)){
        echo "<br>".$m."<br>";
    }
    print_r($a);
    echo "</pre>";
    exit;
}

// Function to build email body from template
function build_email_body($template_name, $replacements = []) {
    $base_path = get_stylesheet_directory() . '/assets/email/';
    $header = file_get_contents($base_path . 'header.html');
    $footer = file_get_contents($base_path . 'footer.html');
    $body = file_get_contents($base_path . $template_name);
    
    if (!$body) {
        return '';
    }
    
    $full = $header . $body . $footer;
    
    $defaults = [
        'site_name' => get_bloginfo('name'),
        'site_url'  => site_url(),
        'year'      => date('Y')
    ];
    
    $replacements = array_merge($defaults, $replacements);
    
    foreach ($replacements as $key => $value) {
        $full = str_replace('{' . $key . '}', $value, $full);
    }
    
    return $full;
}


// Invoice PDF customisation

add_filter('wpo_wcpdf_simple_template_default_table_headers', 'remove_change_invoice_column_names', 2, 10);

function remove_change_invoice_column_names($headers, $document)
{
    $headers = array(
        'product'  => __( 'Appoinment', 'woocommerce-pdf-invoices-packing-slips' ),
        'price'    => __( 'Price', 'woocommerce-pdf-invoices-packing-slips' ),
    );

    if ( 'packing-slip' === $document->get_type() ) {
        unset( $headers['price'] );
    }
    return $headers;
}


function urm_date_format($provided_datetime)
{
    $timestamp = strtotime($provided_datetime);

    // Get the admin date format and time format from settings
    $date_format = get_option('date_format');  // e.g., 'Y-m-d'
    $time_format = get_option('time_format');  // e.g., 'H:i:s'

    // Combine both date and time formats
    $admin_datetime_format = $date_format . ' ' . $time_format;

    // Use date_i18n to convert the timestamp into the WordPress format
    return date_i18n($admin_datetime_format, $timestamp);
}

function urm_get_username($user_id ='' )
{
    if(empty($user_id)){ $user_id =  get_current_user_id(); }
    $user_data = get_user_by('id', $user_id);

    if ($user_data) {
        return  ucfirst($user_data->user_login);
    }
    return '';
}


function urm_get_payment_status($order_id)
{
    $order= wc_get_order($order_id);

    $order_status = 'Invalid Order Number';

    if($order){
        $order_status = $order->get_status();
    }

    if($order_status == "processing" || $order_status == "complete") {
        return "Paid";
    }
    return $order_status;
}