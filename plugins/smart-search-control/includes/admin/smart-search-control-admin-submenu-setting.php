<?php
/**
 * Admin Setting SubMenu
 */

if ( !defined( 'ABSPATH' ) ) exit;
class SMARSECO_Smart_Search_Control_Admin_Submenu_Setting {

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
     * @return  SMARSECO_Smart_Search_Control_Admin_Submenu_Setting
     */
    public static function instance() {

        if ( is_null( self::$instance ) && ! ( self::$instance instanceof  SMARSECO_Smart_Search_Control_Admin_Submenu_Setting ) ) {
            self::$instance = new self();
            self::$instance->smarseco_hooks();
        }
        return self::$instance;
    }

    /**
     * hooks
     */
    private function smarseco_hooks() {

        add_action( 'admin_menu', [ $this, 'smarseco_add_admin_submenu_page' ] );
        add_action( 'current_screen', [ $this, 'smarseco_setup_screen_id' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'smarseco_smart_search_control_admin_assets' ] );
        add_action( 'admin_post_ssc_save_action', [ $this, 'smarseco_save_settings' ] );
        add_action( 'admin_notices', [ $this , 'smarseco_admin_notices' ] );
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
     * add_admin_submenu_page
     */
    public function smarseco_add_admin_submenu_page() {

        add_submenu_page(
            'smart_search_control',
            __( 'Settings', 'smart-search-control' ),
            __( 'Settings', 'smart-search-control' ),
            'manage_options',
            'smart_search_control_settings',
            [ $this, 'smarseco_render_admin_submenu_page']
        );
    }

    /**
     * render_admin_submenu_page
     */
    public function smarseco_render_admin_submenu_page() {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_attr( __( 'You do not have permission to access this page.' , 'smart-search-control' ) ) );
        }

        if ($this->screen_id != 'smart-search-control_page_smart_search_control_settings') {
            return;
        }
        
        $admin_notice = SMARSECO_Smart_Search_Control_Admin_Menu::instance()->smarseco_get_admin_notice();

        /**
         * all pages
         */ 
        $pages = get_pages();

        /**
         * Get selected result page ID from options
         */
        $selected_page = get_option( 'smart_search_control_result_page', '' );
        $template_path = SMARSECO_TEMPLATES_DIR . 'admin/template-smart-search-control-admin-setting-page.php';
        include $template_path;
    }

    /**
     * wp_search_admin_assets
     */
    public function smarseco_smart_search_control_admin_assets() {

        if ($this->screen_id != 'smart-search-control_page_smart_search_control_settings'){
            return;
        }

        wp_enqueue_style(
            'smart-search-control-admin-style',
            plugin_dir_url( dirname(__DIR__) ) . 'assets/css/smart-search-control-admin-style.css',
            array(), SMARSECO_VERSION, 'all'
        );
        wp_enqueue_script( 'smart-search-control-admin-js', plugin_dir_url( dirname(__DIR__) ) . 'assets/js/smart-search-control-admin.js', [ 'jquery' ], SMARSECO_VERSION, true );
    }

    /**
    * Save the selected Page
    */
    public function smarseco_save_settings() {
        
        if ( !isset( $_POST[ 'selected_page' ], $_POST[ 'smart_search_control_nonce' ] ) ) {
            return;
        }
        if ( !wp_verify_nonce( sanitize_text_field( wp_unslash(  $_POST[ 'smart_search_control_nonce' ] ) ), 'smart_search_control_save_page' ) ) {
            return;
        }
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }   

        $selected_page_id = absint( $_POST[ 'selected_page' ] );
        update_option( 'smart_search_control_result_page', $selected_page_id );
            
        if ( isset( $_POST['_wp_http_referer'] ) ) {

            $nonce = wp_create_nonce( 'ssc_nonce' );
            $arrg = [
                "message" => "ssc_updated",
                "nonce"   => $nonce
            ];
            $referer = sanitize_text_field( wp_unslash( $_POST['_wp_http_referer'] ) );
            $redirect_url = esc_url_raw( add_query_arg( $arrg, $referer  ) );
            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    /**
    * Set the Admin Notic
    */
    public function smarseco_admin_notices(){

        if( isset( $_GET[ 'message' ] ) && $_GET[ 'message' ] == 'ssc_updated' && isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'ssc_nonce' ) ) {?>
            <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html( __( 'Result page saved successfully!', 'smart-search-control' ) ); ?></p>
            </div>
            <?php
        }        
    }
}
SMARSECO_Smart_Search_Control_Admin_Submenu_Setting::instance();