<?php
/**
 * Register custom user roles: Child, Parent, Mentor
 */

function urmentor_register_custom_roles() {
    // Child Role
    add_role(
        'child_user',
        __('Child'),
        array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        )
    );

    // Parent Role
    add_role(
        'parent_user',
        __('Parent'),
        array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        )
    );

    // Mentor Role
    add_role(
        'mentor_user',
        __('Mentor'),
        array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
        )
    );
}
add_action('init', 'urmentor_register_custom_roles');
