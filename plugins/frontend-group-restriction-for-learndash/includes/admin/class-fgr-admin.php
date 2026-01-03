<?php
/**
 * Admin template for group restriction
 */
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class FGR_Admin
 */
class FGR_Admin {
    
    /**
     * @var self
     */
    private static $instance = null;
    /**
     * @since 1.0
     * @return $this
     */
    public static function instance() {

        if ( is_null( self::$instance ) && ! ( self::$instance instanceof FGR_Admin ) ) {
            self::$instance = new self;

            self::$instance->hooks();
        }
        
        return self::$instance;
    }

    /**
     * Plugin hooks
     */
    private function hooks() {
        
        add_action( 'admin_menu', [ $this, 'fgr_group_restriction_admin_page' ] );
        add_action( 'admin_post_restriction_setting_page', [ $this, 'fgr_save_restriction_msg' ] );
        add_action( 'admin_notices', [ $this, 'fgr_setting_saved_notice' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'fgr_back_end_scripts' ] );
        add_filter( 'learndash_header_tab_menu', [ $this, 'fgr_add_custom_tabs' ], 10, 3 );
        add_filter( 'plugin_action_links_'. FGR_BASE_DIR, [ $this, 'fgr_plugin_setting_links' ] );
        add_action( 'save_post', [ $this, 'fgr_update_post_settings' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_metabox_box_edit_window' ], 10, 2 );
        add_action( 'init', [ $this, 'fgr_create_post_type' ] );
        add_action( 'in_admin_header', [ $this, 'fgr_remove_admin_notices' ], 100 );
        add_action( 'fgr_admin_notices', [ $this, 'fgr_admin_notices' ] );
    }

    /**
     * displaying fgr admin notice
     */
    public function fgr_admin_notices() {

        if ( isset( $_GET['message'] ) && 'fgr_updated' == sanitize_text_field( $_GET['message'] ) ) {

            $class = 'notice is-dismissible notice-success';
            $message = __( 'Settings Update', 'frontend-group-restriction-for-LearnDash' );
            printf( '<div id="message" class="%s"> <p>%s</p></div>', $class, $message );
        }


    }

    /**
     * remove admin notices from setting page
     */
    public function fgr_remove_admin_notices() {

        $screen = get_current_screen();

        if( $screen && $screen->id == 'learndash-lms_page_frontend-group-restriction-for-learndash' ) {

            remove_all_actions( 'admin_notices' );
        }
    }

    /**
     * create a post type for archive page content
     */ 
    public function fgr_create_post_type() {

        register_post_type( 'fgr',
            array(
                'labels' => array(
                    'name' => __( 'Courses', 'frontend-group-restriction-for-LearnDash' ),
                    'singular_name' => __( 'Course', 'frontend-group-restriction-for-LearnDash' )
                ),
            'public'        => true,
            'has_archive'   => true,
            'show_in_menu'  => false
            )
        );
    }

    /**
     * update post restriction
     */
    public function fgr_update_post_settings( $post ) {
        
        $post_arrays = [ 'sfwd-topic', 'sfwd-lessons', 'sfwd-courses', 'sfwd-quiz' ];   

        $post_type = get_post_type( $post );

        if( ! in_array( $post_type, $post_arrays ) ) {
            return false;
        }

        $post_restriction_msg = isset( $_POST['group-leader-restriction'] ) ? wpautop( str_replace('\"', '', $_POST['group-leader-restriction'] ) ) : '';
        $course_group_option = 'on' == isset( $_POST['fgr-course-group-option'] ) ? $_POST['fgr-course-group-option'] : 'off';

        $post_settings = [];
        $post_settings['restriction_message'] = $post_restriction_msg;
        $post_settings['course_group_option'] = $course_group_option;

        update_post_meta( $post, 'fgr_post_settings', $post_settings );

        $course_restricted_group_id = isset( $_POST['course-group-hidden'] ) ? $_POST['course-group-hidden'] : '';

        if( $course_restricted_group_id && is_array( $course_restricted_group_id ) ) {

            foreach( $course_restricted_group_id as $course_restricted_group ) {
                
                $chackbox_val = strstr( $course_restricted_group, '_', true );
                $g_id = substr( $course_restricted_group, strpos( $course_restricted_group, "_" ) + 1 );
                $key = 'fgr_restrict_'.$g_id.'_'.$post;
                
                if( '1' == $chackbox_val ) {
                    update_post_meta( $post, $key , 'true' );
                } else if( '0' == $chackbox_val ) {
                    delete_post_meta( $post, $key );
                }
            }
        }
    }

    /**
     * add plugin setting page link 
     */
    public function fgr_plugin_setting_links( $links ) {

        $settings_link = '<a href="'. admin_url( 'admin.php?page=frontend-group-restriction-for-learndash' ) .'">'. __( 'Settings', 'frontend-group-restriction-for-LearnDash' ) .'</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Add custom tabs in LearnDash admin header
     *
     * @param $header_tabs_data
     * @param $menu_tab_key
     * @param $screen_post_type
     * @return mixed
     */
    public function fgr_add_custom_tabs( $header_tabs_data, $menu_tab_key, $screen_post_type ) {

        $screen = get_current_screen();
        
        if( $screen && 'post' != $screen->base ) {
            return $header_tabs_data;
        }

        if( 'sfwd-topic' != $screen_post_type && 'sfwd-quiz' != $screen_post_type && 'sfwd-lessons' != $screen_post_type  && 'sfwd-courses' != $screen_post_type ) {
            return $header_tabs_data;
        }

        $current_tab = rtrim( str_replace( 'sfwd-', '', $screen_post_type ), 's' );
        $tab_name = LearnDash_Custom_Label::get_label( $current_tab ); 

        $header_tabs_data[] = [ 
            'id'        => 'learndash_restriction_tab', 
            'name'      => __( 'Restriction Tab', 'frontend-group-restriction-for-LearnDash' ),
            'metaboxes' => [ 'fgr-tab' ] 
        ];

        return $header_tabs_data;
    }

    /**
     * add metabox in post restriction tab
     * 
     * @param $post_type
     * @param $post
     */
    public function add_metabox_box_edit_window( $post_type, $post ) {

        if( 'sfwd-courses'      != $post_type
            && 'sfwd-lessons'   != $post_type
            && 'sfwd-topic'     != $post_type 
            && 'sfwd-quiz'      != $post_type 
            && 'groups'         != $post_type ) {

            return false;
            }

        add_meta_box(
            'fgr-tab',
            __( 'Front End Group Leader Restriction', 'frontend-group-restriction-for-LearnDash' ),
            [ $this, 'fgr_tabs_metabox_content' ],
            $post_type,
            'advanced',
            'high'
        );
    }

    /**
     * callback function of fgr tabs
     */
    public function fgr_tabs_metabox_content() {
        
        $post_id = get_the_ID();
        $course_id = learndash_get_course_id();
        $course_groups = learndash_get_course_groups( $course_id );
        $post_type = get_post_type();
        $editor_id = str_replace( 'sfwd-', '', $post_type ). '-restriction';
        $get_post_settings = get_post_meta( $post_id, 'fgr_post_settings', true );
        $restriction_msg = isset( $get_post_settings['restriction_message'] ) ? $get_post_settings['restriction_message'] : '';
        $course_group_option = isset( $get_post_settings['course_group_option'] ) ? $get_post_settings['course_group_option'] : '';
        $course_group_is_checked = false;
        
        if( 'on' == $course_group_option ) {
            $course_group_is_checked = true;
            $display = 'block';
        }

        ?>
        <div class="fgr-wrap-1">
            <!-- Manage course section metabox -->
            <div class="fgr-content-wrap-1">
                <div class="fgr-tab-content">

                    <?php
                    echo FGR_Helper::fgr_get_restriction_message_editor_html( $restriction_msg );
                    if( 'sfwd-courses' == get_post_type() ) {
                    ?>

                    <div class="fgr-group-option-wrap">
                        <div class="fgr-group-option-title">
                            <?php echo __( 'Group Course Restriction:', 'frontend-group-restriction-for-LearnDash' ); 
                            ?>  
                        </div>
                        <div class="fgr-group-option">
                            <label class="fgr-checkbox">
                                <input type="checkbox" name="fgr-course-group-option" class="fgr-course-group" <?php echo checked( $course_group_is_checked, true, true ); ?>>
                                <span class="fgr-checkmark round"></span>
                            </label>
                            <div class="fgr-help-content">
                                <p>
                                    <span class="dashicons dashicons-info-outline"></span>
                                    <i>
                                        <?php echo __( ' Enable this option to select any group you want to apply course restrictions to. ', 'frontend-group-restriction-for-LearnDash' ); ?>
                                    </i>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="fgr-course-groups" style="display: <?php echo $display; ?>;">
                        <?php
                        if( ! empty( $course_groups ) && is_array( $course_groups ) ) {

                            foreach( $course_groups as $course_group ) {
                                
                                $key = 'fgr_restrict_'.$course_group.'_'.$post_id;

                                $is_meta_key_exits = metadata_exists( 'post', $post_id, $key );

                                if( ! $is_meta_key_exits ) {
                                    $key = 'restrict_'.$course_group.'_'.$post_id;    
                                }

                                $get_post_restriction = get_post_meta( $post_id, $key, true );

                                $is_checked = false;

                                if( 'true' == $get_post_restriction ) {
                                    $is_checked = true;
                                }

                                ?>

                                <input type="checkbox" name="course-group[]" value="<?php echo $course_group; ?>" <?php echo checked( $is_checked, true, true ); ?> class='fgr-course-groups-checkbox' data-group_id='<?php echo $course_group; ?>'>
                                <input type="hidden" name="course-group-hidden[]" value="<?php echo $is_checked.'_'.$course_group; ?>" class='fgr-course-groups-checkbox-<?php echo $course_group; ?>'>
                                <?php
                                echo get_the_title( $course_group );
                            }
                        }
                        ?>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue backend scripts
     *
     * @return bool
     */    
    public function fgr_back_end_scripts() {

        /**
         * enqueue admin css
         */
        wp_enqueue_style( 'frg-backend-css', FGR_ASSETS_URL . 'css/backend.css', [], FGR_VERSION, null );

        wp_enqueue_script( 'fgr-backend-js', FGR_ASSETS_URL . 'js/backend.js', [ 'jquery' ], FGR_VERSION, true ); 
    }

    /**
     * Add restriction page in admin panel
     */
    public function fgr_group_restriction_admin_page() {

        /**
         * Register a menu page.
         *
         * @see add_sub_menu_page()
         */
        add_submenu_page(
            'learndash-lms',
            __( 'Group Restriction', 'frontend-group-restriction-for-LearnDash' ),
            __( 'Group Restriction', 'frontend-group-restriction-for-LearnDash' ),
            'manage_options',
            'frontend-group-restriction-for-learndash',
            [ $this,'fgr_restriction_page_content']
        );
    }

    /**
     * Restriction page content
     */
    public function fgr_restriction_page_content() {

        /**
         * create a action to for displying fgr admin notices
         */
        do_action( 'fgr_admin_notices' );
        echo FGR_Helper::fgr_get_restriction_message_html();
    }

    /**
     * Save Restriction page content
     */
    public function fgr_save_restriction_msg() {

        if( isset( $_POST['restriction_submit'] ) &&
            current_user_can( 'manage_options' ) &&
            ! empty( $_POST ) &&
            check_admin_referer( 'fgr_restriction_nonce', 'fgr_restriction_nonce_field' ) ) {

            $get_restriction_msg['default_restriction_msg'] = isset( $_POST['group-leader-restriction'] ) ? wpautop( str_replace('\"', '', $_POST['group-leader-restriction'] ) ) : '';
	        $get_restriction_msg['fgr-group-courses-option'] = ( isset( $_POST['fgr-group-courses-option'] ) && 'on' == $_POST['fgr-group-courses-option'] ) ? $_POST['fgr-group-courses-option'] : 'off';
	        $get_restriction_msg['fgr-hide-restricted-course'] = ( isset( $_POST['fgr-course-remove-option'] ) && 'on' == $_POST['fgr-course-remove-option'] ) ? $_POST['fgr-course-remove-option'] : 'off';
	        $get_restriction_msg['fgr-redirect-url'] = ( 'on' === $get_restriction_msg['fgr-hide-restricted-course'] && isset( $_POST['fgr-redirect-url'] ) ) ? esc_url( $_POST['fgr-redirect-url'] ) : '';
	        $get_restriction_msg['fgr-exclude-url'] = isset( $_POST['fgr-excludes-urls'] ) ? $_POST['fgr-excludes-urls'] : '';
            $get_restriction_msg['page_id'] = isset( $_POST['fgr-shortcode-page'] ) ? $_POST['fgr-shortcode-page'] : 0 ;
            $get_restriction_msg['is-admin-groupleader'] = ( isset( $_POST['fgr-admin-group-option'] ) && 'on' == $_POST['fgr-admin-group-option'] ) ? $_POST['fgr-admin-group-option'] : 'off';
            $archive_page_content = isset( $_POST['course-not-dis-cont'] ) ? $_POST['course-not-dis-cont'] : __( 'All the courses are hidden on this page.', 'frontend-group-restriction-for-LearnDash' );
            $get_restriction_msg['fgr-archive-page-message'] = $archive_page_content;

	        update_option( 'fgr-restriction' , $get_restriction_msg );

            global $wpdb;
            $p_id = $wpdb->get_var( $wpdb->prepare(
                " SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY ID ASC LIMIT 1 ", 
                'fgr',
                'publish'
            ) );

            if ( ! $p_id ) {
                wp_insert_post( [ 
                    'post_content' => $archive_page_content,
                    'post_type'    => 'fgr',
                    'post_status'  => 'publish' 
                ] );
            } else {
                wp_update_post( [ 
                    'ID'           => $p_id, 
                    'post_content' => $archive_page_content, 
                    'post_status'  => 'publish' 
                ] );
            }
        }

        /**
         * Redirects to another page.
         */
        wp_redirect( add_query_arg( 'message', 'fgr_updated', $_POST['_wp_http_referer'] ) );
        exit();
    }

    /**
     * Restriction page notice
     */
    public function fgr_setting_saved_notice() {

        if( isset( $_GET['message'] ) && sanitize_text_field( $_GET['message'] )  == 'fgr_updated' ) {

            $class = 'notice is-dismissible notice-success';
            $message = __( 'Setting Updated', 'frontend-group-restriction-for-LearnDash' );
            printf ( '<div id="message" class="%s"> <p>%s</p></div>', $class, $message );
        }
    }
}

FGR_Admin::instance();