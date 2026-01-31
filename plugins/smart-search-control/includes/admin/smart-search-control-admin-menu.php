<?php
/**
 * Admin Setting Menu
 */

if ( !defined( 'ABSPATH' ) ) exit;
class SMARSECO_Smart_Search_Control_Admin_Menu {

    /**
     * instance
     * @var 
     */
    private static $instance = null;

    /**
     * screen_id
     */
    private $screen_id = '';

    /**
     * Define instance
     * @return SMARSECO_Smart_Search_Control_Admin_Menu
     */
    public static function instance() {

        if ( is_null( self::$instance ) && ! ( self::$instance instanceof SMARSECO_Smart_Search_Control_Admin_Menu ) ) {
            self::$instance = new self();
            self::$instance->smarseco_hooks();
        }
        return self::$instance;
    }

    /**
     * hooks
     */
    private function smarseco_hooks() {

        add_action( 'admin_menu', [ $this, 'smarseco_add_admin_page' ] );
        add_action( 'current_screen', [ $this, 'smarseco_setup_screen_id' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'smarseco_smart_search_control_admin_assets' ] );
        add_action( 'wp_ajax_smarseco_smart_search_control_setting', [ $this, 'smarseco_smart_search_control_setting_add' ] );
        add_action( 'wp_ajax_smarseco_smart_search_control_setting_delete', [ $this, 'smarseco_smart_search_control_setting_delete' ] );
        add_action( 'wp_ajax_smarseco_smart_search_control_setting_edit', [ $this, 'smarseco_smart_search_control_setting_edit' ] );
        add_action( 'wp_ajax_smarseco_create_database_table', [ $this, 'smarseco_create_database_table' ] );
        add_action( 'wp_ajax_smarseco_get_categories_tags', [ $this, 'smarseco_get_categories_tags' ] );
        add_action( 'admin_notices', [ $this, 'smarseco_display_admin_notice' ] ); 
    }

    /**
     * display admin notice if no page is selected
     */
    public function smarseco_display_admin_notice() {

        $fallback_page_id = get_option( 'smart_search_control_result_page' );

        if ( empty( $fallback_page_id ) ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php echo __( 'Smart Search Control:', 'smart-search-control' ); ?></strong>
                    <?php echo __( 'Please select a result page in the plugin settings.', 'smart-search-control' ); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Setup screen ID when the current screen is available.
     */
    public function smarseco_setup_screen_id() {

        $screen = get_current_screen();
        if ( $screen ) {
            $this->screen_id = $screen->id;
        }
    }

    /**
     * Show Admin Notice if Table Does Not Exist
     */
    public function smarseco_get_admin_notice() {

        if ($this->screen_id != 'toplevel_page_smart_search_control'){
            return;
        }
        
        $table_exists = SMARSECO_Smart_Search_Control::smarseco_smart_search_control_create_table() ;

        if ( !$table_exists ) {

            ob_start(); 
            ?>
            <div class="admin-msg">
                <p><?php echo esc_html__( 'The table for Smart Search Control does not exist. Click here to ' , 'smart-search-control' ); ?>
                    <strong class="create-table">
                        <a href="#">
                            <?php echo esc_html__( 'Create Table ' , 'smart-search-control' ) ; ?>
                        </a>
                    </strong>
                    <span id="database-loader" class="loading" style="display: none;"></span>
                </p>
            </div>
            <?php
            return ob_get_clean();
        }
        return '';
    }
    
    /**
     * add_admin_page
     */
    public function smarseco_add_admin_page() {

        add_menu_page(
            __( 'Smart Search Control' , 'smart-search-control' ),
            __( 'Smart Search Control' , 'smart-search-control' ),
            'manage_options',
            'smart_search_control',
            [ $this, 'smarseco_render_admin_page' ],
            'dashicons-search',
            80
        );
    }

    /**
     * render_admin_page
     */
    public function smarseco_render_admin_page() {

        global $wpdb;

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html( __( 'You do not have permission to access this page.' , 'smart-search-control' ) ) );
        }

        if ($this->screen_id != 'toplevel_page_smart_search_control'){
            return;
        }

        $table_name     = $wpdb->prefix . 'smart_search_control_parameters';
        $admin_notice   = SMARSECO_Smart_Search_Control_Admin_Menu::instance()->smarseco_get_admin_notice();
        $items_per_page = 10;

        if ( isset( $_GET['paged'] ) && isset( $_GET['nonce'] ) ) {
            $ssc_pagination_nonce = sanitize_text_field( wp_unslash( $_GET['nonce'] ) );
            if ( wp_verify_nonce( $ssc_pagination_nonce, 'ssc_admin_pagination' ) ) {
                $page = absint( $_GET['paged'] );
            } else {
                $page = 1;
            }
        } else {
            $page = 1;
        }
        $offset         = ( $page - 1 ) * $items_per_page;
        $search_entries = [];
        $total_pages    = 1;

        if ( empty( $admin_notice ) ) {
            $total_items    = $this->smarseco_get_total_items( $table_name );
            $total_pages    = ceil( $total_items / $items_per_page );
            $smarseco_search_entries = $this->smarseco_get_search_entries( $table_name, $items_per_page, $offset );
            $args = [
                'public'             => true,
                'publicly_queryable' => true,
            ];
            $visible_post_types = get_post_types( $args, 'objects' );
            if ( ! array_key_exists( 'page', $visible_post_types ) ) {
                $visible_post_types['page'] = get_post_type_object( 'page' );
            }
            $post_types = apply_filters( 'smarseco_visible_post_types', $visible_post_types );
        }
        $template_path = SMARSECO_TEMPLATES_DIR . 'admin/template-smart-search-control-admin-page.php';
        include $template_path;
    }

    /**
     * wp_search_admin_assets
     */
    public function smarseco_smart_search_control_admin_assets() {

        if ($this->screen_id != 'toplevel_page_smart_search_control'){
            return;
        }

        // Enqueue Select2
        wp_enqueue_style( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0' );
        wp_enqueue_script( 'select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0', true );
        
        wp_enqueue_style(
            'smart-search-control-admin-style',
            plugin_dir_url(dirname(__DIR__)) . 'assets/css/smart-search-control-admin-style.css',
            array( 'select2' ), SMARSECO_VERSION, 'all'
        );
        wp_enqueue_script( 'smart-search-control-admin-js', plugin_dir_url(dirname(__DIR__)) . 'assets/js/smart-search-control-admin.js', [ 'jquery', 'select2' ], SMARSECO_VERSION, true );
        wp_localize_script( 'smart-search-control-admin-js', 'SMARSECO_SETTING', [

            'ajaxurl'      => admin_url( 'admin-ajax.php' ),
            'nonce_add'    => wp_create_nonce( 'smart_search_control_setting_nonce_add' ),
            'nonce_edit'   => wp_create_nonce( 'smart_search_control_setting_nonce_edit' ),
            'nonce_delete' => wp_create_nonce( 'smart_search_control_setting_nonce_delete' ),
            'nonce_table'  => wp_create_nonce( 'create_database_table_nonce' ),
            'nonce_categories_tags' => wp_create_nonce( 'smart_search_control_categories_tags_nonce' ),
            'error_msg'    => __( 'Something went wrong. Please try again.' , 'smart-search-control' ),
            'confirm_msg'  => __( 'Are you sure you want to delete this search setting?' , 'smart-search-control' ),
        ]);   
    }

    /**
     * Add New Search Setting
     */
    public function smarseco_smart_search_control_setting_add() {

        global $wpdb;

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized request' , 'smart-search-control' ) ] );
        }

        if ( !isset( $_POST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash(  $_POST[ 'nonce' ] ) ), 'smart_search_control_setting_nonce_add' ) )  {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce' , 'smart-search-control' ) ] );
        }

        $table_name = $wpdb->prefix . 'smart_search_control_parameters';
        $place_holder = !empty( $_POST[ 'place_holder' ] ) ? sanitize_text_field(  wp_unslash( $_POST[ 'place_holder' ] ) ) : '';
        $css_id       = !empty( $_POST[ 'css_id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'css_id' ] ) ) : '';
        $class        = !empty( $_POST[ 'class' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'class' ] ) ) : '';
        $post_types   = isset( $_POST[ 'post_type' ] ) && !empty(  $_POST[ 'post_type' ] ) 
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ 'post_type' ] ) ) 
            : get_post_types( [ 'public' => true ], 'names' );
        $categories   = isset( $_POST[ 'categories' ] ) && !empty( $_POST[ 'categories' ] ) 
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ 'categories' ] ) ) 
            : [];
        $tags         = isset( $_POST[ 'tags' ] ) && !empty( $_POST[ 'tags' ] ) 
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ 'tags' ] ) ) 
            : [];
        $data = json_encode([
            'place_holder' => $place_holder,
            'css_id'       => $css_id,
            'class'        => $class,
            'post_type'    => $post_types,
            'categories'   => $categories,
            'tags'         => $tags
        ]);
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert( $table_name,[ 'data' => $data ], [ '%s' ] );
        if ( !empty( $result ) ) {
            wp_cache_delete( 'ssc_table_exists_' . md5( $table_name ), 'smart_search_control' );
            wp_cache_delete( 'ssc_total_items_count', 'smart_search_control' );
            wp_cache_delete( "ssc_entries_page_1", 'smart_search_control' );
            wp_cache_delete( 'ssc_all_settings', 'smart_search_control' );
        }
        if ( empty( $result ) ) {

            $notice = [
                'message' => __( 'Failed to save search settings. Please try again.' , 'smart-search-control' ),
                'type'    => 'error'
            ];
            wp_send_json_success( $notice );
        }
        $notice = [
            'message' => __( 'Search settings saved successfully!' , 'smart-search-control' ),
            'type'    => 'success'
        ];
        wp_send_json_success( $notice );
    }

    /**
     * Edit Search Setting
     */
    public function smarseco_smart_search_control_setting_edit() {

        global $wpdb;

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized request' , 'smart-search-control' ) ] );
        }

        if ( !isset( $_POST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'nonce' ] ) ), 'smart_search_control_setting_nonce_edit' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce' , 'smart-search-control' ) ] );
        }

        $table_name = $wpdb->prefix . 'smart_search_control_parameters';
        
        $id = isset( $_POST[ 'id' ] ) ? intval( $_POST[ 'id' ] ) : 0;

        if ( $id === 0 ) {
            wp_send_json_error( [ 'message' => __( 'Invalid ID' , 'smart-search-control' ) ] );
        }

        $place_holder = !empty( $_POST[ 'place_holder' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'place_holder' ] ) ) : __( 'Search...' , 'smart-search-control' );
        $css_id       = !empty( $_POST[ 'css_id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'css_id' ] ) ) : '';
        $class        = !empty( $_POST[ 'class' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'class' ] ) ) : '';
        $post_types   = isset( $_POST[ 'post_type' ] ) && !empty( $_POST[ 'post_type' ] ) 
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ 'post_type' ] ) ) 
            : get_post_types( [ 'public' => true ], 'names' );
        $categories   = isset( $_POST[ 'categories' ] ) && !empty( $_POST[ 'categories' ] ) 
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ 'categories' ] ) ) 
            : [];
        $tags         = isset( $_POST[ 'tags' ] ) && !empty( $_POST[ 'tags' ] ) 
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ 'tags' ] ) ) 
            : [];

        $data = json_encode([
            'place_holder' => $place_holder,
            'css_id'       => $css_id,
            'class'        => $class,
            'post_type'    => $post_types,
            'categories'   => $categories,
            'tags'         => $tags
        ]);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result =  $wpdb->update(
            $table_name,
            [ 'data' => $data ],  
            [ 'id' => $id ],      
            [ '%s' ],              
            [ '%d' ]
            
        );

        if ( !empty( $result) ) {
            wp_cache_delete( 'ssc_table_exists_' . md5( $table_name ), 'smart_search_control' );
            wp_cache_delete( 'ssc_total_items_count', 'smart_search_control' );
            wp_cache_delete( "ssc_entries_page_1", 'smart_search_control' );
            wp_cache_delete( 'ssc_all_settings', 'smart_search_control' );
        }

        if ( $result === false ) {
            wp_send_json_success( [
                'message' => __( 'Failed to update data. Please try again.', 'smart-search-control' ),
                'type'    => 'error'
            ] );
            return;
        }

        wp_send_json_success( [
            'message' => __( 'Search settings updated successfully!', 'smart-search-control' ),
            'type'    => 'success'
        ] );
    }

    /**
     * Delete Search Setting
     */
    public function smarseco_smart_search_control_setting_delete() {
        
        global $wpdb;

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized request' , 'smart-search-control' ) ] );
        }

        if ( !isset( $_POST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'nonce' ] ) ), 'smart_search_control_setting_nonce_delete' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce' , 'smart-search-control' ) ] );
        }

        $table_name = $wpdb->prefix . 'smart_search_control_parameters';
        
        $id = isset( $_POST[ 'id' ] ) ? intval( $_POST[ 'id' ] ) : 0;

        if ( $id === 0 ) {
            wp_send_json_error( [ 'message' => __( 'Invalid ID' , 'smart-search-control' ) ] );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result =  $wpdb->delete( $table_name, [ 'id' => $id ], [ '%d' ] ) ;
        if ( !empty( $result) ) {
            wp_cache_delete( 'ssc_table_exists_' . md5( $table_name ), 'smart_search_control' );
            wp_cache_delete( 'ssc_total_items_count', 'smart_search_control' );
            wp_cache_delete( "ssc_entries_page_1", 'smart_search_control' );
            wp_cache_delete( 'ssc_all_settings', 'smart_search_control' );
        }

        if ( ! $result ) {
            wp_send_json_error( [
                'message' => __( 'Failed to delete search setting. Please try again.', 'smart-search-control' ),
                'type'    => 'error'
            ] );
            return;
        }
    
        wp_send_json_success( [
            'message' => __( 'Search setting deleted successfully!', 'smart-search-control' ),
            'type'    => 'success'
        ] );
    }

    /**
    * Get paginated search entries from the table with caching.
    */
    public function smarseco_get_search_entries( $table_name, $items_per_page, $offset ) {

        global $wpdb;
        // Allow only this specific table for safety
        $allowed_table = $wpdb->prefix . 'smart_search_control_parameters';
        if ( $table_name !== $allowed_table ) {
            return [];
        }

        $cache_key = 'ssc_data_' . md5( $table_name . '_' . $items_per_page . '_' . $offset );
        $entries = wp_cache_get( $cache_key );

        if ( false === $entries ) {

            $table_name = $wpdb->prefix . 'smart_search_control_parameters';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $entries = $wpdb->get_results( $wpdb->prepare ( "SELECT id, data FROM `{$wpdb->prefix}smart_search_control_parameters` LIMIT %d OFFSET %d",  [ $items_per_page, $offset ] ) );
            wp_cache_set( $cache_key, $entries, '', 300 );
        }
        return $entries;
    }

    /**
     * Get total items from the table with caching.
     */
    public function smarseco_get_total_items( $table_name ) {

        global $wpdb;
        $allowed_table = $wpdb->prefix . 'smart_search_control_parameters';
        if ( $table_name !== $allowed_table ) {
            return 0;
        }

        $cache_key = 'ssc_total_count_' . md5( $table_name );
        $total_items = wp_cache_get( $cache_key );

        if ( false === $total_items ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $total_items = (int) ($wpdb->get_var("SELECT COUNT(id) FROM {$wpdb->prefix}smart_search_control_parameters") ?? 0);
            wp_cache_set( $cache_key, $total_items, '', 300 );
        }
        return $total_items;
    }

    /**
     * create_database_table on click
     */
    public function smarseco_create_database_table() {
        
        if ( !isset( $_POST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'nonce' ] ) ), 'create_database_table_nonce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce' , 'smart-search-control' ) ] );
        }
        SMARSECO_Smart_Search_Control::smarseco_smart_search_control_create_table();
        wp_send_json_success( [
            'message' => __( 'Table for Smart Search Control created successfully!', 'smart-search-control' ),
            'type'    => 'success'
        ] );
    }

    /**
     * Get categories and tags for selected post types via AJAX
     */
    public function smarseco_get_categories_tags() {
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Unauthorized request', 'smart-search-control' ) ] );
        }

        if ( !isset( $_POST[ 'nonce' ] ) || !wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'nonce' ] ) ), 'smart_search_control_categories_tags_nonce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid nonce', 'smart-search-control' ) ] );
        }

        $post_types = isset( $_POST[ 'post_types' ] ) && !empty( $_POST[ 'post_types' ] ) 
            ? array_map( 'sanitize_text_field', wp_unslash( $_POST[ 'post_types' ] ) ) 
            : [];

        if ( empty( $post_types ) ) {
            wp_send_json_success( [
                'categories' => [],
                'tags' => []
            ] );
        }

        $categories = smarseco_get_categories_for_post_types( $post_types );
        $tags = smarseco_get_tags_for_post_types( $post_types );

        wp_send_json_success( [
            'categories' => $categories,
            'tags' => $tags
        ] );
    }
}
SMARSECO_Smart_Search_Control_Admin_Menu::instance();
