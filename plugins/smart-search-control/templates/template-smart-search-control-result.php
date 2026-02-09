<?php

if ( !defined( 'ABSPATH' ) ) exit;
?>
<div class="smart-search-control-result-header">
    <div class="ssc-header-inner-wrapper">
        <?php if( $search_query != null ){ ?>
            <h2 class="result-header"><?php echo esc_attr( __( 'Search Results for:', 'smart-search-control' ) ) . ' ' . esc_html( $search_query ); ?></h2>
        <?php
        }

        $selected_page_id = get_option( 'smart_search_control_result_page' );
        if( $selected_page_id ) {
            ?>
            <div class="result-header">
                <?php echo do_shortcode( $shortcode_content ); ?>
            </div>
            <?php
        }
        ?>
    </div>
</div>
    <?php 
    if ( $query->have_posts() && $search_query != null ) {
        ?>
        <div class="ssc-view-option-wrapper">
            <button class="ssc-button ssc-list-btn ssc-btn-background">
                <span class="dashicons dashicons-menu"></span>
                <?php echo esc_html__( 'list', 'smart-search-control' ); ?>
            </button>
            <button class="ssc-button ssc-grid-btn">
                <span class="dashicons dashicons-grid-view"></span>
                <?php echo esc_html__( 'Grid', 'smart-search-control' ); ?>
            </button>
        </div>
        <?php
    }
    ?>

    <div class="ssc-custom-search-results">

    <?php
    if ( $query->have_posts() && $search_query != null ) {

        while ( $query->have_posts() ) {
            $query->the_post();
            ?>
            <div class="ssc-search-result-item ssc-list-view">

                <div class="ssc-post-featured-wrapper ssc-responsive-width">
                    <a href="<?php the_permalink(); ?>">
                        <?php
                        if ( has_post_thumbnail() ) {
                            the_post_thumbnail( 'thumbnail' );
                        } else {
                            $smarseco_result_img =  plugin_dir_url(__DIR__) . 'assets/default-img/no-feature-image.jpg' ;
                            echo '<img src="' . esc_url( $smarseco_result_img ). '"
                                    alt="' . esc_attr__( 'Default placeholder image', 'smart-search-control' ) . '">';
                            
                        }
                        ?>
                    </a>
                </div>
                <div class="ssc-post-content-wrapper">
                    <h2 class="search-title">
                        <a href="<?php the_permalink(); ?>">
                            <?php the_title(); ?>
                        </a>
                    </h2>

                    <div class="search-excerpt">
                        <?php echo esc_html( wp_trim_words( get_the_excerpt(), 25, '...' ) ); ?>
                    </div>
                </div>
            </div>
            <?php
        
    }
    echo'</div>';
    } 
    else {
        ?>
            <div class="smart-search-control-no-result">
                <p><?php echo esc_attr( __( 'No Result Found ', 'smart-search-control' ) ); ?></p>
            </div>
        
    <?php }
    ?>

<!-- Pagination -->
<?php

if ( $query->max_num_pages > 1 && $query->have_posts() && $search_query != null ) {
    ?>

    <div class="pagination">
        <?php
        echo wp_kses_post( paginate_links( [
            'total' => $query->max_num_pages,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
        ] ) );
        ?>
    </div>

    <?php
}

wp_reset_postdata();
?>