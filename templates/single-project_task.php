<?php
get_header();

if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
    echo '<p>' . esc_html__( 'You do not have permission to view this task.', 'ptt' ) . '</p>';
    get_footer();
    return;
}

while ( have_posts() ) : the_post();
    echo '<h1>' . esc_html( get_the_title() ) . '</h1>';
    echo ptt_get_timer_controls_html( get_the_ID() );
endwhile;

get_footer();
