<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap">

    <?php echo wp_kses_post( $admin_notice ); ?>
    <h2 class="short-code-result_page-title"><?php echo esc_html( __( 'Result Page Settings', 'smart-search-control' ) ); ?></h2>

    <?php do_action( 'smarseco_settings_before_content' ); ?>

    <div class="short-code-result-row">
        <h3 class="shortcode-label"><?php echo esc_html( __( 'Default ShortCode', 'smart-search-control' ) ); ?></h3>

        <div class="short-code-result-container">
            <div class="shortcode-container short-code-result-wrapper">
                <p class="short-code-copy">
                    <span id="copy-code">
                        <span class="dashicons dashicons-clipboard"></span>
                        <span class="copy-msg"><?php echo esc_html( __( 'Copied!', 'smart-search-control' ) ); ?></span>
                    </span>
                    <span id="shortcode-text">[smart_search_control id="0"]</span>
                </p>
            </div>
            <p class="short-code-result-desc">
                <?php echo esc_html( __( 'Shortcode to display search form and results', 'smart-search-control' ) ); ?></p>
        </div>
    </div>

    <?php do_action( 'smarseco_settings_before_form_content' ); ?>

    <form method="post" action="<?php echo esc_attr( admin_url( 'admin-post.php' ) ); ?>">
        <div class="short-code-result-row">
                <h3 class="shortcode-label"><?php echo esc_html( __( 'Result page', 'smart-search-control' ) ); ?></h3>
            <div class="select-page-container">
                <select name="selected_page" id="selected_page">
                    <option value=""><?php echo esc_attr( __( 'Select result page', 'smart-search-control' ) ); ?></option>
                    <?php
                        foreach ( $pages as $page ) {
                            $smarseco_selected = ( $selected_page == $page->ID ) ? 'selected' : '';
                            echo '<option value="' . esc_attr( $page->ID ) . '" ' . esc_html( $smarseco_selected ). '>' . esc_html( $page->post_title ) . '(ID: ' . esc_html( $page->ID ) . ')' . '</option>';
                        }
                    ?>
                </select>
                <p class="short-code-result-desc">
                <?php echo esc_html( __( 'Page where search results will be displayed', 'smart-search-control' ) ); ?></p>
            </div>
        </div>

        <?php do_action( 'smarseco_settings_form_content' ); ?>

        <input type="hidden" name="action" value="ssc_save_action">
        
        <?php wp_nonce_field( 'smart_search_control_save_page', 'smart_search_control_nonce' ); ?>
        <div class="smart_search_result_btn">
            <input type="submit" value="<?php echo esc_attr( __( 'Save changes', 'smart-search-control' ) ); ?>"
                class="button button-primary" />
        </div>
    </form>
</div>