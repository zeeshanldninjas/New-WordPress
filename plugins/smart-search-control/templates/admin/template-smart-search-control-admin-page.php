<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap">
    <?php
        echo wp_kses_post( $admin_notice );
    ?>
    <div class="page-header">
    <p class="page-title"><?php echo esc_html__( 'Smart Search Control' , 'smart-search-control' ); ?></p>
    <a href="#" id="openModal" class="new-rec-btn"><?php echo esc_html__( 'Add New Search', 'smart-search-control' ); ?></a>

</div>

<p><?php echo esc_html__( 'Customize the search bar settings to enhance your website’s search functionality.', 'smart-search-control' ); ?></p>

    <table class="search-table">
        <thead>
            <tr>
                <th><?php echo esc_html__( 'Shortcode' , 'smart-search-control' ); ?></th>
                <th><?php echo esc_html__( 'Place Holder' , 'smart-search-control' ); ?></th>
                <th><?php echo esc_html__( 'CSS ID' , 'smart-search-control' ); ?></th>
                <th><?php echo esc_html__( 'CSS Class' , 'smart-search-control' ); ?></th>
                <?php do_action( 'smarseco_column_header_before_action_column' ); ?>
                <th><?php echo esc_html__( 'Action' , 'smart-search-control' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if ( isset( $smarseco_search_entries ) && is_array( $smarseco_search_entries ) && $smarseco_search_entries ) { ?>
                <?php foreach ( $smarseco_search_entries as $smarseco_entry ){
                
                    $smarseco_data = json_decode( $smarseco_entry->data ); ?>
                    
                    <tr class="search-table-data">
                        <strong>
                            <td>[smart_search_control id="<?php echo esc_html( $smarseco_entry->id ); ?>"]</td>
                        </strong>
                        <td><?php echo esc_html( $smarseco_data->place_holder ); ?></td>
                        <td><?php echo esc_html( $smarseco_data->css_id ); ?></td>
                        <td><?php echo esc_html( implode( ', ', explode( ' ', $smarseco_data->class ) ) ); ?></td>
                        <?php do_action( 'smarseco_column_content_before_action_column', $smarseco_data ); ?>
                        <td>
                            <a href="#" data-entry='<?php echo esc_attr(htmlentities( wp_json_encode( $smarseco_entry ?: new stdClass() ), ENT_QUOTES, 'UTF-8' ) ); ?>' class="button edit-setting"><?php echo esc_html__( 'Edit', 'smart-search-control' ); ?></a>
                            <button class="delete-setting " data-id="<?php echo esc_html(  $smarseco_entry->id ); ?>">
                                <span  id="delete-row"><?php echo esc_html__( 'Delete' , 'smart-search-control'); ?>
                                </span>
                                <span id="delete-loader" class="loading" style="display: none;"></span>
                            </button>
                        </td>
                    </tr>
                <?php
                } 
                
            }
            else { ?>
                <tr>
                    <td colspan="5" class="no-search-parm"> <?php echo esc_html__( 'Default Shortcode:', 'smart-search-control' ) . ' [smart_search_control id="0"]'; ?></td>
                </tr>
            <?php } ?>
        </tbody>
        <tfoot>
            <tr>
                <th><?php echo esc_html__( 'Shortcode' , 'smart-search-control' ); ?></th>
                <th><?php echo esc_html__( 'Place Holder' , 'smart-search-control' ); ?></th>
                <th><?php echo esc_html__( 'CSS ID' , 'smart-search-control' ); ?></th>
                <th><?php echo esc_html__( 'CSS Class' , 'smart-search-control' ); ?></th>
                <?php do_action( 'smarseco_column_footer_before_action_column' ); ?>
                <th><?php echo esc_html__( 'Action' , 'smart-search-control' ); ?></th>
            </tr>
        </tfoot>
    </table>

    <!-- Pagination -->
    <div class="tablenav">

        <div class="tablenav-pages pagination">

            <?php if ( $total_pages > 1 ) {

                $smarseco_prev_page = max( 1, $page - 1 );
                $smarseco_next_page = min( $total_pages, $page + 1 );

                $smarseco_nonce = wp_create_nonce( "ssc_admin_pagination" );
                ?>

                <!-- Previous Button -->
                <?php if ( $page > 1 ) { ?>
                    <a class="prev page-numbers" href="<?php echo esc_html( admin_url( 'admin.php?page=smart_search_control&paged=' . $smarseco_prev_page .'$nonce=' .$smarseco_nonce  ) ); ?>"><?php echo esc_html__( '« Prev' , 'smart-search-control' ); ?></a>
                <?php } ?>

                <!-- Numbered Pagination -->
                <?php for ( $smarseco_i = 1; $smarseco_i <= $total_pages; $smarseco_i++ ) { ?>

                    <a class="page-numbers <?php echo ( $smarseco_i == $page ) ? 'current' : ''; ?>" 
                    href="<?php echo esc_html( admin_url( 'admin.php?page=smart_search_control&paged=' . $smarseco_i .'$nonce=' .$smarseco_nonce ) ); ?>">
                    <?php echo esc_html( $smarseco_i ); ?>
                    </a>
                <?php } ?>

                <!-- Next Button -->
                <?php if ( $page < $total_pages ) { ?>
                    <a class="next page-numbers" href="<?php echo esc_html( admin_url( 'admin.php?page=smart_search_control&paged=' . $smarseco_next_page .'$nonce=' .$smarseco_nonce ) ); ?>"><?php echo esc_html__( 'Next »' , 'smart-search-control' ); ?></a>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
</div>

<!-- Modal Structure -->
<div id="searchModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><?php echo esc_html__( 'Smart Search Control Settings' , 'smart-search-control' ); ?></h2>
            <button class="closeModal modal-header-close">×</button>
        </div>
        <hr>
        <?php echo wp_kses_post( $admin_notice ) ;?>
        <div class="modal-body">
            
            <!-- Short code display -->
            <div class="shortcode-container">
                <p class="short-code-copy">
                    <span id="copy-code">
                        <span class="dashicons dashicons-clipboard"></span>
                        <span class="copy-msg" ><?php echo esc_html__( 'Copied!' , 'smart-search-control' ); ?></span>
                    </span> 
                    <span id="shortcode-text">[smart_search_control id="<span class="code-id"></span>"]</span>
                </p>
            </div>

            <form id="searchForm" method="post">

                <div class="form-group">
                    <label for="place_holder"><?php echo esc_html__( 'Placeholder' , 'smart-search-control' ); ?></label>
                    <input type="text" id="place_holder" name="place_holder" value="<?php  echo esc_attr( esc_html__( 'Search...' , 'smart-search-control' ) ); ?>">
                    <span class="inputs-desc"><?php echo esc_html__( 'This text will appear inside the search field as a hint before the user types.' , 'smart-search-control' ); ?></span>
                </div>

                <div class="advance-container" id="advance-toggle">
                    <p><?php echo esc_html__( 'Advanced Settings' , 'smart-search-control' ); ?></p>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </div>

                <div class="advance-container-toggle" id="advance-content">
                    <div class="form-group">
                        <label for="id"><?php echo esc_html__( 'CSS ID' , 'smart-search-control' ); ?></label>
                        <input type="text" id="id" name="id">
                        <span class="inputs-desc"><?php echo esc_html__( 'Optional: Enter a unique ID for this element (for CSS or JavaScript).' , 'smart-search-control' ); ?></span>
                    </div>
                    <div class="form-group">
                        <label for="class"><?php echo esc_html__( 'CSS Class' , 'smart-search-control' ); ?></label>
                        <input type="text" id="class" name="class">
                        <span class="inputs-desc"><?php echo esc_html__( 'Optional: Enter custom CSS classes (separated by spaces).' , 'smart-search-control' ); ?></span>
                    </div>
                </div>
                <?php do_action( 'smarseco_add_fields_to_search_form' ); ?>
                <div class="form-group">
                    <div class="label-container">
                        <label class="post-type-label"><?php echo esc_html__( 'Post Types' , 'smart-search-control' ); ?></label>
                        <label class="select-all-label">
                            <input type="checkbox" id="select-all">
                            <span><?php echo esc_html__( 'Select All' , 'smart-search-control' ); ?></span>
                        </label>
                    </div>
                    <?php if ( !empty( $post_types ) ){ ?>
                    <div class="post-type-options">
                        <?php foreach ( $post_types as $smarseco_key => $post_type ): ?>

                            <label class="checkbox-label">
                                <input type="checkbox" name="post_type[]" value="<?php echo esc_attr( $smarseco_key ); ?>" class="custom-checkbox">
                                <?php echo esc_html( $post_type->label ); ?>
                            </label>

                        <?php endforeach; ?>
                    </div>
                    <?php }
                    else {?>
                        <p><?php echo esc_html__( 'No post types available.' , 'smart-search-control' );
                    }?>
                </div>

                <hr>

                <div class="modal-actions">
                    <button type="button" class="closeModal button"><?php echo esc_html__( 'Cancel' , 'smart-search-control' ); ?></button>
                    <button type="submit" id="submit-btn" class="button button-primary modal-btn">
                        <span class="btn-text"><?php echo esc_html__( 'Save' , 'smart-search-control' ); ?></span>
                        <span id="edit-loader" class="loading" style="display: none;"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
