<?php
/**
 * Plugin Name: Frontend Group Restriction for LearnDash
 * Description: This add-on is designed to allow admin and group leaders to restrict courses, lessons, topics and quizzes with custom restriction message for their assigned groups.
 * Version: 1.0
 * Author: LDninjas
 * Plugin URI: https://ldninjas.com/front-end-group-restriction-for-learndash/
 * Author URI: https://ldninjas.com
 * Text Domain: frontend-group-restriction-for-LearnDash
 */

if( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class Front_End_Group_Restriction
 */
class Front_End_Group_Restriction {

    const VERSION = '1.0';
    
    /**
     * @var self
     */
    private static $instance = null;

    /**
     * @since 1.0
     * @return $this
     */
    public static function instance() {

        if ( is_null( self::$instance ) && ! ( self::$instance instanceof Front_End_Group_Restriction ) ) {
            self::$instance = new self;

            self::$instance->setup_constants();
            self::$instance->includes();
            self::$instance->enable_freemius();
        }
        
        return self::$instance;
    }

    /**
     *  create a function enable freemius 
     */
    public function enable_freemius() {

        // if ( ! function_exists( 'fgrfl_fs' ) ) {
        //     function fgrfl_fs() {
        //         global $fgrfl_fs;

        //         if ( ! isset( $fgrfl_fs ) ) {
        //             require_once dirname(__FILE__) . '/freemius/start.php';

        //             $fgrfl_fs = fs_dynamic_init( array(
        //                 'id'                  => '9079',
        //                 'slug'                => 'frontend-group-restriction-for-learndash',
        //                 'type'                => 'plugin',
        //                 'public_key'          => 'pk_d0d3250e59db341f5d7ebc426adfd',
        //                 'is_premium'          => true,
        //                 'is_premium_only'     => true,
        //                 'has_addons'          => false,
        //                 'has_paid_plans'      => true,
        //                 'is_org_compliant'    => false,
        //                 'menu'                => array(
        //                     'slug'           => 'frontend-group-restriction-for-learndash',
        //                     'support'        => false,
        //                     'parent'         => array(
        //                         'slug' => 'learndash-lms',
        //                     ),
        //                 ),
        //             ) );
        //         }

        //         return $fgrfl_fs;
        //     }

        //     fgrfl_fs();
        //     do_action( 'fgrfl_fs_loaded' );
        // }
    } 

    /**
     * Plugin Constants
    */
    private function setup_constants() {

        /**
         * Directory
        */
        define( 'FGR_DIR', plugin_dir_path ( __FILE__ ) );
        define( 'FGR_DIR_FILE', FGR_DIR . basename ( __FILE__ ) );
        define( 'FGR_INCLUDES_DIR', trailingslashit ( FGR_DIR . 'includes' ) );
        define( 'FGR_TEMPLATES_DIR', trailingslashit ( FGR_DIR . 'templates' ) );
        define( 'FGR_BASE_DIR', plugin_basename(__FILE__));

        /**
         * URLs
        */
        define( 'FGR_URL', trailingslashit ( plugins_url ( '', __FILE__ ) ) );
        define( 'FGR_ASSETS_URL', trailingslashit ( FGR_URL . 'assets' ) );

        /**
         * plugin version
         */
        define( 'FGR_VERSION', self::VERSION );

        /**
         * Text Domain
         */
        define( 'FGR_TEXT_DOMAIN', 'frontend-group-restriction-for-LearnDash' );
    }

    /**
     * Plugin includes
     */
    private function includes() {

        if( file_exists( FGR_INCLUDES_DIR . 'admin/class-fgr-admin.php' ) ) {

            require FGR_INCLUDES_DIR . 'admin/class-fgr-admin.php';
        }

        if( FGR_INCLUDES_DIR . 'class-fgr-helper.php' ) {

            require FGR_INCLUDES_DIR . 'class-fgr-helper.php';
        }

        if( file_exists( FGR_INCLUDES_DIR . 'class-fgr-shortcode.php' ) ) {

            require FGR_INCLUDES_DIR . 'class-fgr-shortcode.php';
        }
    }
}

/**
 * Display admin notifications if dependency not found.
 */
function fgr_ready() {

    if( ! is_admin() ) {
        return;
    }

    if( ! class_exists( 'SFWD_LMS' ) ) {
        deactivate_plugins ( plugin_basename ( __FILE__ ), true );
        $class = 'notice is-dismissible error';
        $message = __( 'Frontend group restriction for LearnDash add-on requires LearnDash to be activated.', 'frontend-group-restriction-for-LearnDash' );
        printf ( '<div id="message" class="%s"> <p>%s</p></div>', $class, $message );
    }
}

/**
 * @return bool
 */
function FGR() {

    if ( ! class_exists( 'SFWD_LMS' ) ) {
        add_action( 'admin_notices', 'fgr_ready' );
        return false;
    }

    return Front_End_Group_Restriction::instance();
}
add_action( 'plugins_loaded', 'FGR' );

/**
 * @return bool
 */
function activation() {

	global $wpdb;
	$table_name = $wpdb->prefix.'fgr_group_permissions';

    if( is_null( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) ) {
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
}
register_activation_hook( __FILE__, 'activation' );
