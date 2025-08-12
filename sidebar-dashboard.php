
<style type="text/css">

body {
    margin: 0;
    font-family: 'Inter', sans-serif;
    background-color: #f9f9fc;
}

#app-wrapper {
    display: flex;
}

/* Sidebar */
.sidebar {
    width: 220px;
    background-color: #fff;
    border-right: 1px solid #eee;
    display: flex;
    flex-direction: column;
}

.sidebar .logo {
    text-align: center;
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.sidebar-nav {
    background: #3eaeb2;
    flex: 1;
}

.sidebar-nav ul {
    list-style: none;
    padding: 0;
    margin: 0;
}


.sidebar-nav a {
    display: block;
    padding: 10px 20px;
    color: #FFF;
    text-decoration: none;
    font-weight: 500;
    transition: background 0.3s;
}

.sidebar-nav a:hover {
    background-color: #114470;
}
.sidebar-nav li.active{
    background-color: #114470;
}

/* Main Content */
.main-content {
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* Topbar */
.topbar {
    height: 73px;
    background-color: #fff;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1rem;
}

.search-box input {
    padding: 8px 12px;
    border-radius: 6px;
    border: 1px solid #ddd;
}

.user-info img {
    border-radius: 50%;
}

    
</style>

<div id="app-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <a href="<?php echo esc_url(home_url('/')); ?>">
                <?php
                if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<h1>' . get_bloginfo('name') . '</h1>';
                }
                ?>
            </a>
        </div>
        <nav class="sidebar-nav">
            <?php

                global $post;
                // $post_slug = $post->post_name;
                $post_id = $post->ID; 

                if(is_mentor_user()) {
                    $menu_name = 'mentor'; // The menu's name in WP admin
                }else if(is_child_user()) {
                    $menu_name = 'child'; // The menu's name in WP admin
                }else if(is_parent_user()) {
                    $menu_name = 'parent'; // The menu's name in WP admin
                }

                $menu = wp_get_nav_menu_object($menu_name);

                if ($menu) {
                    $menu_items = wp_get_nav_menu_items($menu->term_id);
                    if ($menu_items) {
                        echo '<ul class="sidebar-menu">';
                        foreach ($menu_items as $item) {
                            // echo "<pre>";print_r($item);echo "</pre>";
                            // $active_class = ($item->post_name == $post_slug)? 'active' : '';
                            $active_class = ($item->object_id == $post_id) ? 'active' : '';

                            echo '<li class="'.$active_class.'"><a href="' . esc_url($item->url) . '">' . esc_html($item->title) . '</a></li>';
                        }

                        if (is_user_logged_in()) {
                            echo '<li class="menu-item"><a href="' . wp_logout_url(home_url()) . '"> Logout </a></li>';
                        } else {
                        
                            echo '<li class="menu-item"><a href="' . home_url('/urmentor-login/') . '"> Login </a></li>';
                        }
                        echo '</ul>';
                    }
                } 
                ?>

        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="search-box">
                <!-- <input type="text" placeholder="Search"> -->
            </div>

            <?php
            if(isset($_GET['child_id']))
            {

                $user_id = $_GET['child_id']; // Replace 1 with the actual user ID
                $user = get_user_by('id', $user_id);
                if ($user) {
                    echo '<div>';
                        $username = $user->user_login;
                        echo '<h2> '.ucfirst($username).'\'s Progerss </h2>';
                    echo '</div>';
                } 
            }
             ?>

            <div class="user-info">
                <?php
                $current_user = wp_get_current_user();
                echo get_avatar($current_user->ID, 40);
                ?>
            </div>
        </header>

        <!-- Page Content -->
        <div class="">
        
