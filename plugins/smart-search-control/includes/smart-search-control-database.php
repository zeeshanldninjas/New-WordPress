<?php
/**
 * Create the Database
*/

if ( ! defined( 'ABSPATH' ) ) exit;
class SMARSECO_Smart_Search_Control_Database {

    /**
     * Holds the class instance.
     * @var Smart_Search_Control_Database|null
     */
    private static $instance = null;

    /**
     * Returns the singleton instance.
     * @return SMARSECO_Smart_Search_Control_Database
     */
    public static function instance() {
        
        if ( is_null( self::$instance ) && ! ( self::$instance instanceof SMARSECO_Smart_Search_Control_Database ) ) {
            self::$instance = new self();
            self::$instance->create_database_table();
        }
        return self::$instance;
    }

    /**
     * Creates the search parameters database table if it doesn't exist.
     */
    public function create_database_table() {

        SMARSECO_Smart_Search_Control::smarseco_smart_search_control_create_table();
    }
}
SMARSECO_Smart_Search_Control_Database::instance();