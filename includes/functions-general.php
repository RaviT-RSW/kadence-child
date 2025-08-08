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