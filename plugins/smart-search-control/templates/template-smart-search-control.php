<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div id="<?php echo esc_attr( ( $css_id ) ); ?>" class="smarseco-default-search-container parent-container <?php echo esc_attr( ( $class ) );?>">

    <div class="smarseco-default-search-bar-container">
            <form  action="<?php echo esc_url( $url ); ?>" method="GET" class="smarseco-default-search-bar ssc-search-form" id="smarseco-search-form">
                
                <input type="text" name="query" class="smarseco-default-search-input search-query"
                value="<?php echo !empty( $search_query ) ? esc_attr( $search_query ) :  '' ?>"
                placeholder="<?php echo !empty( $placeholder ) ? esc_attr( $placeholder ) : esc_attr( __( 'Search...', 'smart-search-control' ) ); ?>" aria-label="Search">

            <!-- Hidden Post Type Input -->
            <input type="hidden" name="smartsearch" value="<?php echo esc_attr( $ssc_id); ?>">
            <input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'ssc_search_nonce' ) ); ?>">

            <?php

            /* Pass gutenberg selected post type */
            $block_post_types_value = '';
            if ( isset( $block_posts_types ) && is_array( $block_posts_types ) && ! empty( $block_posts_types ) ) {
                $block_post_types_value = implode(
                    ',',
                    array_map( 'sanitize_text_field', $block_posts_types )
                );
            }  elseif ( isset( $_GET['block_post_types'] ) && ! empty( $_GET['block_post_types'] ) ) {
                $block_post_types_value = sanitize_text_field(
                    wp_unslash( $_GET['block_post_types'] )
                );
            }

            if( !empty( $block_post_types_value ) ){
                ?>
                <input type="hidden" name="block_post_types" value="<?php echo esc_attr( $block_post_types_value ); ?>">
                <?php
            }

            ?>

            <!-- Search Button -->
            <button type="submit" class="smarseco-default-search-btn search-btn "> 
                <span class="smarseco-default-search-icon">
                    <span class="dashicons dashicons-search"></span>
                </span>
            </button>
        </form>

    </div>
    <!-- Search Suggestions Dropdown -->
    <div class="search-suggestions"></div>
</div>
