<?php
function urm_admin_enqueue_script() {
    wp_enqueue_style( 'urm-full-calendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css');
    wp_enqueue_style( 'urm-custom-admin', get_stylesheet_directory_uri().'/admin/assets/css/style.css');

    wp_enqueue_script( 'urm-full-calendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js');
    //  Tippy.js for tooltips 
    wp_enqueue_script( 'urm-popperjs', 'https://unpkg.com/@popperjs/core@2');
    wp_enqueue_script( 'urm-tippy', 'https://unpkg.com/tippy.js@6');

    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js');
}
add_action('admin_enqueue_scripts', 'urm_admin_enqueue_script');
