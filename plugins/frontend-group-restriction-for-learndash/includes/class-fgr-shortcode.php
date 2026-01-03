<?php

/**
 * Shortcode for group restirction
 */
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class FGR_Shorcode
 */
class FGR_Shorcode {

    /**
     * @var self
     */
    private static $instance = null;

    private $no_courses_message_shown = false;
    
    /**
     * Track if any course queries resulted in empty results
     * @var bool
     */
    private $has_empty_course_listings = false;
    
    /**
     * Track if output buffering is active
     * @var bool
     */
    private $output_buffering_active = false;

    /**
     * @since 1.0
     * @return $this
     */
    public static function instance() {

        if ( is_null( self::$instance ) && ! ( self::$instance instanceof FGR_Shorcode ) ) {
            self::$instance = new self;

            self::$instance->hooks();
        }
        
        return self::$instance;
    }

    /**
     * Plugin hooks
     */
    private function hooks() {
        add_shortcode( 'frontend_group_restriction', [ $this, 'fgr_group_leader_access_content' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'fgr_front_enqueue_scripts' ] );
        add_action( 'wp_ajax_fgr_save_group_users_permissions', [ $this, 'save_group_users_permissions' ] );
        add_action( 'learndash_content', [ $this, 'fgr_content_restriction' ], 10, 2 );
        add_action( 'posts_results', [ $this, 'fgr_globally_hide_user_restricted_courses' ], 999, 2 );
        add_action( 'wp', [ $this, 'fgr_redirect_post_url_is_given' ] );
        add_filter( 'the_content', [ $this, 'fgr_apply_fgr_shortcode' ] );
        // add_filter( 'ld_course_list_shortcode_attr_values', [ $this, 'fgr_make_price_type_empty' ], 10, 2 );
        // add_filter( 'ld_course_list', [ $this, 'fgr_ld_course_list' ], 10, 3 );
        add_action( 'wp_ajax_fgr_group_records', [ $this, 'fgr_group_records' ] );
        
        // Universal output buffering solution for empty course listings
        add_action( 'template_redirect', [ $this, 'fgr_start_output_buffering' ], 1 );
        add_action( 'wp_footer', [ $this, 'fgr_end_output_buffering' ], 999 );
    }

    /**
     * save group and course/lessons/topics/quizzes permissions
     */
    public function save_group_users_permissions() {

        global $wpdb;
        
        $type       = sanitize_text_field( $_POST['type'] );
        $users      = isset( $_POST['users'] ) ? $_POST['users'] : array();
        $group_id   = isset( $_POST['group_id'] ) ? sanitize_text_field( $_POST['group_id'] ) : 0;
        $restrictions = isset( $_POST['restrictions'] ) ? $_POST['restrictions'] : [];
        $restrictor_id = get_current_user_id();
        $date = current_time( 'timestamp' );
    
        if( empty( $group_id ) ) {
            echo __( 'Group ID not found', 'frontend-group-restriction-for-LearnDash' );
            exit;
        }
    
        $table_name = $wpdb->prefix . 'fgr_group_permissions';
    
        if (is_null($wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) ) {
            $wpdb->query( "CREATE TABLE $table_name (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `restrictor_id` INT(11) NOT NULL,
                `group_id` INT(11) NOT NULL,
                `post_id` INT(11) NOT NULL,
                `post_type` VARCHAR(50) NOT NULL,
                `user_ids` VARCHAR(255) NOT NULL,
                `type` ENUM('include','exclude') NOT NULL,
                `restriction` VARCHAR(10) NOT NULL,
                `date` VARCHAR(255) NOT NULL
            )" );     
        }
    
        foreach ( $restrictions as $restriction ) {

            $post_id = isset( $restriction['post_id'] ) ? (int) $restriction['post_id'] : 0;
            $post_type = isset( $restriction['post_type'] ) ? sanitize_text_field( $restriction['post_type'] ) : '';
            $is_restrict = isset( $restriction['is_restrict'] ) ? sanitize_text_field( $restriction['is_restrict'] ) : '';
    
            if ( empty( $post_id ) || empty( $post_type ) || $is_restrict === '' ) {
                continue;
            }
    
            $restriction_value = $is_restrict === 'true' ? 'true' : 'false';
            $user_ids = implode( ',', array_map( 'intval', $users ) );
    
            $existing_record = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE restrictor_id = %d AND group_id = %d AND post_id = %d AND post_type = %s",
                    $restrictor_id, $group_id, $post_id, $post_type
                )
            );
    
            if ( $existing_record ) {
                if ( $existing_record->restriction !== $restriction_value || $existing_record->user_ids !== $user_ids || $existing_record->type !== $type ) {
                    $wpdb->update(
                        $table_name,
                        [
                            'restriction' => $restriction_value,
                            'type' => $type,
                            'user_ids' => $user_ids,
                            'date' => $date
                        ],
                        [
                            'restrictor_id' => $restrictor_id,
                            'group_id' => $group_id,
                            'post_id' => $post_id,
                            'post_type' => $post_type
                        ]
                    );
                }
            } else {
                $wpdb->insert(
                    $table_name,
                    [
                        'restrictor_id' => $restrictor_id,
                        'group_id' => $group_id,
                        'post_id' => $post_id,
                        'post_type' => $post_type,
                        'user_ids' => $user_ids,
                        'type' => $type,
                        'restriction' => $restriction_value,
                        'date' => $date
                    ]
                );
            }
        }
    
        wp_send_json_success( [ 'message' => __( 'Permissions and restrictions updated.', 'frontend-group-restriction-for-LearnDash' ) ] );
        exit;
    }
    

    /**
     * make price type attr empty if all courses are restricted
     */
    public function fgr_make_price_type_empty( $atts, $attr ) {

        $course_count = wp_count_posts( 'sfwd-courses' );
        $restricted_course_id = FGR_HELPER::fgr_get_restricted_course_ids();
        $get_hide_option = get_option( 'fgr-restriction' );

        $group_courses = isset( $get_hide_option['fgr-group-courses-option'] ) ? $get_hide_option['fgr-group-courses-option'] : '';

        if( 'on' == $group_courses ) {

            $restricted_course_id = FGR_Helper::fgr_get_restricted_course_ids_based_on_group_courses( $restricted_course_id );
        }

        $is_hide_option_on = isset( $get_hide_option['fgr-hide-restricted-course'] ) ? $get_hide_option['fgr-hide-restricted-course'] : 'off';

        if( $course_count && isset( $course_count->publish ) && is_array( $restricted_course_id ) && 'on' == $is_hide_option_on ) {

            if( $course_count->publish == count( $restricted_course_id ) ) {

                $atts['price_type'] = '';
            }
        }

        return $atts;
    }

    /**
     * display message on ld course list if all courses are restricted
     */
    public function fgr_ld_course_list( $output, $filter, $atts ) {

        $fgr_options = get_option( 'fgr-restriction' );

        $user_id = get_current_user_id();

        $user_is_capable = FGR_Helper::fgr_get_user_capability( $user_id );

        if( true == $user_is_capable ) {

            $admin_groupleader_rest_opt = FGR_Helper::fgr_get_admin_groupleader_resriction_option();

            if( true == $admin_groupleader_rest_opt ) {
                return $output;
            }
        }

        $excluded_urls = isset( $fgr_options['fgr-exclude-url'] ) ? $fgr_options['fgr-exclude-url'] : [];

        if( ! empty( $excluded_urls ) ) {

            $excluded_urls = array_map( 'trim', array_filter( explode( ',',$excluded_urls ) ) );
        }

        $current_url = get_permalink();

        if( $current_url && $excluded_urls && is_array( $excluded_urls ) ) {
            if( in_array( $current_url, $excluded_urls ) ) {
                return $output;
            }
        }

        $course_count = wp_count_posts( 'sfwd-courses' );
        $restricted_course_id = FGR_HELPER::fgr_get_restricted_course_ids();

        $group_courses = isset( $fgr_options['fgr-group-courses-option'] ) ? $fgr_options['fgr-group-courses-option'] : '';

        if( 'on' == $group_courses ) {

            $restricted_course_id = FGR_Helper::fgr_get_restricted_course_ids_based_on_group_courses( $restricted_course_id );
        }

        $get_hide_option = get_option( 'fgr-restriction' );
        $is_hide_option_on = isset( $get_hide_option['fgr-hide-restricted-course'] ) ? $get_hide_option['fgr-hide-restricted-course'] : 'off';

        if( $course_count && isset( $course_count->publish ) && is_array( $restricted_course_id ) && 'on' == $is_hide_option_on ) {

            if( $course_count->publish == count( $restricted_course_id ) ) {

                $args = [
                    'post_type'     => 'fgr',
                    'post_status'   => 'publish'
                ];

                $post_content = get_posts( $args );
                $error_message = isset( $post_content[0]->post_content ) ? $post_content[0]->post_content : '';
                $output = '<div id="fgr-course-list-shortcode">'.do_shortcode( $error_message ).'</div>';
            }
        }

        return $output;
    }

    /**
     * Apply fgr shortcode if user select a page from setting page
     */
    public function fgr_apply_fgr_shortcode( $content ) {

        $post_type = get_post_type();
        
        if( 'page' != $post_type ) {
            return $content;
        }

        $current_id = get_the_ID();
        $get_page_id = get_option( 'fgr-restriction' );
        $page_id = isset( $get_page_id['page_id'] ) ? $get_page_id['page_id'] : 0;
        
        if( $page_id != $current_id ) {
            return $content;
        }

        if( $page_id ) {
            $content = do_shortcode( '[frontend_group_restriction]' );
        }

        return $content;  
    }

    /**
     * redirect a post if the post is restricted
     */
    public function fgr_redirect_post_url_is_given() {

        $post_id       = get_the_ID();
        $post_type     = get_post_type( $post_id ); 
        $user_id       = get_current_user_id();

        if( is_admin() ) {
            return;
        }
 
        $user_is_capable = FGR_Helper::fgr_get_user_capability( $user_id );

        if( true == $user_is_capable ) {

            $admin_groupleader_rest_opt = FGR_Helper::fgr_get_admin_groupleader_resriction_option();

            if( true == $admin_groupleader_rest_opt ) {
                return;
            }
        }

        if( $post_type == 'sfwd-courses' || 
            $post_type == 'sfwd-lessons' || 
            $post_type == 'sfwd-topic' ||
            $post_type == 'sfwd-quiz' ) { 

            if( FGR_Helper::fgr_display_restriction( $user_id, $post_id ) ) {

                $get_rest_message = get_option( 'fgr-restriction' );

                $hide_restricted_course = isset( $get_rest_message['fgr-hide-restricted-course'] ) ? $get_rest_message['fgr-hide-restricted-course'] : '';
                $redirect_url = isset( $get_rest_message['fgr-redirect-url'] ) ? $get_rest_message['fgr-redirect-url'] : '';

                if( ! empty( $redirect_url ) ) {
                        wp_redirect( $redirect_url );
                        exit();
                }
            }
        }       
    }

	/**
	 * remove course from frontend
	 */
	public function fgr_globally_hide_user_restricted_courses( $posts, $query ) {

        if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return $posts;
        }

        if ( ! is_user_logged_in() || empty( $posts ) ) {
            return $posts;
        }

        if ( $query->is_singular ) {
            return $posts;
        }

        $user_id = get_current_user_id();
        $user_is_capable = FGR_Helper::fgr_get_user_capability( $user_id );
        if( $user_is_capable && FGR_Helper::fgr_get_admin_groupleader_resriction_option() ) {
            return $posts;
        }

        $fgr_options = get_option( 'fgr-restriction' );
        $hide_post = isset( $fgr_options['fgr-hide-restricted-course'] ) ? $fgr_options['fgr-hide-restricted-course'] : 'off';
        
        $excluded_urls = isset( $fgr_options['fgr-exclude-url'] ) ? $fgr_options['fgr-exclude-url'] : [];
        if( ! empty( $excluded_urls ) ) {
            $excluded_urls = array_map( 'trim', array_filter( explode( ',',$excluded_urls ) ) );
        }

		$protocol = ( ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ) || $_SERVER['SERVER_PORT'] == 443 ) ? "https://" : "http://";
		$current_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		if( is_array( $excluded_urls )  && in_array( $current_url, $excluded_urls ) ) {
			return $posts;
		}

        $visible_courses = 0;
        $total_courses_in_query = 0;

        foreach ( $posts as $key => $post ) {
            
            if ( empty( $post->post_type ) || $post->post_type !== 'sfwd-courses' ) {
                continue;
            }

            $total_courses_in_query++;

            if ( 'on' !== $hide_post ) {
                continue;
            }

            if ( FGR_Helper::is_course_hidden_for_user( $post->ID, $user_id ) ) {
                unset( $posts[ $key ] );
            } else {
                $visible_courses++;
            }
        }

        /**
         * Track if this query had courses but all were hidden
         * This will be used by the universal output buffer solution
         */
        if ( $total_courses_in_query > 0 && $visible_courses === 0 ) {
            $this->has_empty_course_listings = true;
        }

        return array_values( $posts );
    }

    /**
     * Disabled course,lesson,topic content
     *
     * @param $content
     * @return mixed|string|void
     */
    public function fgr_content_restriction( $content, $post ) {

        if( ! is_user_logged_in() || is_post_type_archive() ) {
            return $content;
        }
        
        $user_id = get_current_user_id();
        $user_is_capable = FGR_Helper::fgr_get_user_capability( $user_id );

        if( $user_is_capable && FGR_Helper::fgr_get_admin_groupleader_resriction_option() ) {
            return $content;
        }
        
        $post_id = $post->ID;
        $course_id = learndash_get_course_id( $post_id );
        $group_ids = learndash_get_course_groups( $course_id, false );

        if( ! empty( $group_ids ) && is_array( $group_ids ) ) {

            $current_post_id = get_the_ID();
            $get_settings = get_option( 'fgr-restriction' );

            $redirect_url = isset( $get_settings['fgr-redirect-url'] ) ? $get_settings['fgr-redirect-url'] : '';

            foreach( $group_ids as $group_id ) {

                if( !learndash_is_user_in_group( $user_id, $group_id ) ) {
                    continue;
                }                

                $restriction_settings = get_option( 'fgr-restriction' );
                $group_courses_is_enabled = isset( $restriction_settings['fgr-group-courses-option'] ) ? $restriction_settings['fgr-group-courses-option'] : "";

                if( $group_courses_is_enabled == "on" ) {

                    if( $redirect_url ) {
                        wp_redirect( $redirect_url );
                        exit();
                    } else {
                        return do_shortcode( $this->get_restriction_message( $course_id ) );
                    }
                }

                $permissions = $this->get_group_permission_data( $group_id, $current_post_id );
                
                $exclude_users = [];
                $include_users = [];
                $restriction = null;

                foreach( $permissions as $record ) {
                    if( $record->post_id == $current_post_id ) {
                        $restriction = $record->restriction;
                    }
                    if( $record->type == 'exclude' ) {
                        $exclude_users['exclude_user_ids'] = explode( ',', $record->user_ids );
                    }
                    if( $record->type == 'include' ) {

                        $include_users['include_user_ids'] = explode( ',', $record->user_ids );
                    }
                }

                if ( !empty( $exclude_users ) && 'true' == $restriction ) {

                    $exclude_users = isset( $exclude_users['exclude_user_ids'] ) ? $exclude_users['exclude_user_ids'] : [];

                    if( !in_array( $user_id, $exclude_users ) ) {

                        if( $redirect_url ) {
                            wp_redirect( $redirect_url );
                            exit();
                        } else {
                            return do_shortcode( $this->get_restriction_message( $course_id ) );
                        }
                    }
                }

                if ( !empty( $include_users ) && 'true' == $restriction ) {

                    $include_users = isset( $include_users['include_user_ids'] ) ? $include_users['include_user_ids'] : [];
                    if( in_array( $user_id, $include_users ) ) {

                        if( $restriction == "true" ) {
                            if( $redirect_url ) {
                                wp_redirect( $redirect_url );
                                exit();
                            } else {
                                return do_shortcode( $this->get_restriction_message( $course_id ) );
                            }
                        }
                    }
                }
            }
        }
        
        return $content;
    }

    /**
     * Query of the custom table for users, type and restriction
     * @param mixed $group_id
     * @return array|object|null
     */
    public function get_group_permission_data( $group_id, $post_id ) {

        global $wpdb;
        $table_name = $wpdb->prefix . 'fgr_group_permissions';

        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT user_ids, type, post_id, restriction
                FROM $table_name
                WHERE group_id = %d
                AND post_id  = %d
                ",
                $group_id,
                $post_id
            )
        );
    }
    

    /**
     * Shows the restriction message when the course or all course restriction applies
     * @param mixed $post_id
     * @return string
     */
    public function get_restriction_message( $post_id ) {

        $get_post_settings = get_post_meta( $post_id, 'fgr_post_settings', true );
        $restriction_msg = isset( $get_post_settings['restriction_message'] ) ? $get_post_settings['restriction_message'] : '';
    
        if( empty( $restriction_msg ) ) {
            $global_restriction_settings = get_option( 'fgr-restriction', [] );
            $restriction_msg = isset( $global_restriction_settings['default_restriction_msg'] ) ? $global_restriction_settings['default_restriction_msg'] : __( 'Post is restricted', 'frontend-group-restriction-for-LearnDash' );
        }
    
        $user = wp_get_current_user();
        $user_name = $user->display_name;
        $post_name = get_the_title( $post_id );
        $instructor = get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) );
        $group_name = implode( ', ', array_map('get_the_title', learndash_get_course_groups( $post_id ) ) );
        $course_name = get_the_title( learndash_get_course_id( $post_id ) );
    
        $lesson_name = '';
        if ( get_post_type( $post_id ) == 'sfwd-lessons' ) {
            $lesson_name = get_the_title( $post_id );
        }
    
        $restriction_msg = str_replace(
            ['{user_name}', '{post_name}', '{instructor}', '{group_name}', '{course_name}', '{lesson_name}'],
            [$user_name, $post_name, $instructor, $group_name, $course_name, $lesson_name],
            $restriction_msg
        );
    
        return wpautop( $restriction_msg );
    }
    
    

    /**
     * Enqueue front scripts
     *
     * @return bool
     */
    public function fgr_front_enqueue_scripts() {
        
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'fgr-frontend-select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', [], time(), null );
        wp_enqueue_style( 'fgr-frontend-css', FGR_ASSETS_URL . 'css/frontend.css', [], FGR_VERSION, null );
        wp_enqueue_script( 'fgr-frontend-select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', [ 'jquery' ], time(), true );
        wp_enqueue_script( 'fgr-frontend-js', FGR_ASSETS_URL . 'js/frontend.js', [ 'jquery' ], FGR_VERSION, true );
        wp_localize_script( 'fgr-frontend-js', 'FGR', [
            
            'ajaxURL'               => admin_url( 'admin-ajax.php' ),
            'group_records_nonce'   => wp_create_nonce( 'group_item_records_nonce' ),
        ] );
    }

    /**
     * Short code display group leader course,lesson etc for the first group
     * @return bool|false|string|void
     */
    public function fgr_group_leader_access_content() {

        if ( ! is_user_logged_in() ) {
            ?>
            <div class="fgr-error-message">
                <?php
                return '<div class="fgr-error-message">' . __(  'You need to be logged in to access this page', 'frontend-group-restriction-for-LearnDash' ) . '.</div>';
                ?>
            </div>
            <?php
        }

        $user_id = get_current_user_id();
        if ( learndash_is_group_leader_user( $user_id ) == false && !current_user_can( 'manage_options' ) ) {
            ?>
            <div class="fgr-error-message">
                <?php
                return '<div class="fgr-error-message">' . __(  'You need to be a group leader/Administrator to access this page', 'frontend-group-restriction-for-LearnDash' ) . '.</div>';
                ?>
            </div>
            <?php
        }
        
        $all_groups = learndash_get_groups();
        if ( ! $all_groups ) {

            return '<div class="fgr-error-message">' . __(  'You are not authorized to any LearnDash group', 'frontend-group-restriction-for-LearnDash' ) . '.</div>';
        }
        
        if ( is_array( $all_groups ) && ! empty( $all_groups ) ) {
            
            ob_start();
            ?>
            <div id="fgr-wrapper" class="fgr-wrapper">
                <div class="fgr-wrapper-sidebar">
                    <div class="fgr-sidebar-heading">
                        <h3>
                            <?php $group_heading = LearnDash_Custom_Label::get_label( 'group' );
                            echo esc_html( $group_heading ) . 's:' ?>
                        </h3>
                    </div>
                    <div class="fgr-sidebar-list">
                        <?php 
                            foreach( $all_groups as $all_group ) {

                                $group_id = $all_group;
        
                                if ( true == is_object( $all_group ) ) {
                                $group_id = $all_group->ID; 
                                }
                                ?>
                                <ul>
                                    <?php
                                    FGR_Helper::fgr_get_group_list( $group_id );
                                    ?>
                                </ul>
                                <?php
                            }
                        ?>
                    </div>
                </div>
                <div class="fgr-wrapper-child">
                    <?php

                        $first_group = reset( $all_groups ); 
                        $group_id = is_object( $first_group ) ? $first_group->ID : $first_group;
                        FGR_Helper::fgr_get_group_row( $group_id );
                    ?>
                </div>
            </div>
            <?php

                $content = ob_get_contents();
                ob_get_clean();
                return $content;
        }
    }

    /**
     * Fetching group records
     * @return void
     */
    public function fgr_group_records() {

        global $wpdb;
        check_ajax_referer( 'group_item_records_nonce', 'nonce' );

        $group_id = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;

        if ( empty( $group_id ) ) {

            wp_send_json_error( 'Invalid Group ID.' );
            return;
        }

        $table_name = $wpdb->prefix.'fgr_group_permissions';
        $users = FGR_Helper::fgr_get_group_users( $group_id );
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT type, user_ids from $table_name WHERE group_id = %d", $group_id ) );
        $group_heading = LearnDash_Custom_Label::get_label( 'group' );
        $group_title = esc_html( $group_heading ) . ': ' . esc_html( get_the_title( $group_id ) ); 
        
        $type = !empty( $results ) ? $results[0]->type : 'include';
        
        $user_ids = !empty( $results ) ? $results[0]->user_ids : 0;

        $group_courses = FGR_Helper::fgr_get_group_courses( $group_id );
        $courses_data = FGR_HELPER::fgr_get_group_details( $group_id, $group_courses );

        wp_send_json_success(
            [
                'group_id'    => $group_id,
                'group_title' => $group_title,
                'type'        => $type,
                'users'       => array_values( array_filter( array_map( function( $user_id ) {

                    $user_id = (int) $user_id;
                    
                    return [
                        'id'   => $user_id,
                        'name' => FGR_Helper::fgr_get_username_by_user_id( $user_id ),
                    ];

                }, $users ) ) ),
                'user_ids'    => $user_ids,
                'courses'     => $courses_data,
            ]
        );
    }

    /**
     * Start output buffering to capture page content for universal course listing detection
     * This works at the WordPress core level, similar to how posts_results operates universally
     */
    public function fgr_start_output_buffering() {
        
        // Skip if in admin, doing AJAX, or REST request
        if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        // Skip if user is not logged in
        if ( ! is_user_logged_in() ) {
            return;
        }

        // Check if hiding restricted courses is enabled
        $fgr_options = get_option( 'fgr-restriction' );
        $hide_post = isset( $fgr_options['fgr-hide-restricted-course'] ) ? $fgr_options['fgr-hide-restricted-course'] : 'off';
        
        if ( 'on' !== $hide_post ) {
            return;
        }

        // Check if current URL is excluded
        $excluded_urls = isset( $fgr_options['fgr-exclude-url'] ) ? $fgr_options['fgr-exclude-url'] : [];
        if ( ! empty( $excluded_urls ) ) {
            $excluded_urls = array_map( 'trim', array_filter( explode( ',', $excluded_urls ) ) );
            $protocol = ( ( !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ) || $_SERVER['SERVER_PORT'] == 443 ) ? "https://" : "http://";
            $current_url = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            if ( is_array( $excluded_urls ) && in_array( $current_url, $excluded_urls ) ) {
                return;
            }
        }

        // Start output buffering
        ob_start();
        $this->output_buffering_active = true;
    }

    /**
     * End output buffering and process content to inject messages for empty course listings
     * This provides universal coverage for any plugin that generates course listings
     */
    public function fgr_end_output_buffering() {
        
        if ( ! $this->output_buffering_active ) {
            return;
        }

        // Get the buffered content
        $content = ob_get_contents();
        ob_end_clean();

        // Only process if we detected empty course listings
        if ( $this->has_empty_course_listings && ! $this->no_courses_message_shown ) {
            $content = $this->fgr_inject_empty_course_message( $content );
            $this->no_courses_message_shown = true;
        }

        // Output the processed content
        echo $content;
        $this->output_buffering_active = false;
    }

    /**
     * Inject custom message into empty course listing areas
     * Uses universal detection patterns that work regardless of the plugin generating the listings
     * 
     * @param string $content The page content
     * @return string Modified content with injected messages
     */
    private function fgr_inject_empty_course_message( $content ) {
        
        // Get the custom message from settings
        $fgr_options = get_option( 'fgr-restriction' );
        $custom_message = isset( $fgr_options['fgr-archive-page-message'] ) ? $fgr_options['fgr-archive-page-message'] : __( 'All courses are restricted for you.', 'frontend-group-restriction-for-LearnDash' );
        
        if ( empty( $custom_message ) ) {
            $custom_message = __( 'All courses are restricted for you.', 'frontend-group-restriction-for-LearnDash' );
        }

        // Create the message HTML
        $message_html = '<div class="fgr-no-courses-message" style="padding: 20px; margin: 20px 0; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; text-align: center;">' . wpautop( $custom_message ) . '</div>';

        // Universal detection patterns for course listings
        $patterns = [
            // LearnDash course list shortcode containers
            '/<div[^>]*class="[^"]*ld-course-list[^"]*"[^>]*>(\s*)<\/div>/i',
            '/<div[^>]*class="[^"]*learndash-course-list[^"]*"[^>]*>(\s*)<\/div>/i',
            
            // Generic course archive containers
            '/<div[^>]*class="[^"]*courses[^"]*"[^>]*>(\s*)<\/div>/i',
            '/<div[^>]*class="[^"]*course-list[^"]*"[^>]*>(\s*)<\/div>/i',
            '/<div[^>]*class="[^"]*course-grid[^"]*"[^>]*>(\s*)<\/div>/i',
            
            // WordPress archive containers for sfwd-courses
            '/<div[^>]*class="[^"]*post-type-archive-sfwd-courses[^"]*"[^>]*>(\s*)<\/div>/i',
            
            // Generic post listing containers that might be empty
            '/<div[^>]*class="[^"]*posts[^"]*"[^>]*>(\s*)<\/div>/i',
            '/<div[^>]*class="[^"]*entry-content[^"]*"[^>]*>(\s*)<\/div>/i',
            
            // Main content areas that might contain course listings
            '/<main[^>]*class="[^"]*"[^>]*>(\s*)<\/main>/i',
            '/<div[^>]*class="[^"]*content[^"]*"[^>]*>(\s*)<\/div>/i',
        ];

        // Try to inject message into detected empty containers
        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $content ) ) {
                $content = preg_replace( $pattern, '<div$1>' . $message_html . '</div>', $content, 1 );
                break; // Only inject once
            }
        }

        // Fallback: If no empty containers found but we know courses were hidden,
        // try to inject after common course listing indicators
        if ( strpos( $content, 'fgr-no-courses-message' ) === false ) {
            
            $fallback_patterns = [
                // After LearnDash course list shortcode
                '/(\[ld_course_list[^\]]*\])/i',
                
                // After course archive headers
                '/(<h1[^>]*class="[^"]*archive-title[^"]*"[^>]*>.*?<\/h1>)/i',
                '/(<h1[^>]*class="[^"]*page-title[^"]*"[^>]*>.*?<\/h1>)/i',
                
                // After main content opening tags
                '/(<main[^>]*>)/i',
                '/(<div[^>]*class="[^"]*content[^"]*"[^>]*>)/i',
            ];

            foreach ( $fallback_patterns as $pattern ) {
                if ( preg_match( $pattern, $content ) ) {
                    $content = preg_replace( $pattern, '$1' . $message_html, $content, 1 );
                    break;
                }
            }
        }

        return $content;
    }
}

FGR_Shorcode::instance();
