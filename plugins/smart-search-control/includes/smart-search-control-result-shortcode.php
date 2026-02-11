<?php
/**
 * Smart_Search_Control_Result Shortcode
*/
if ( ! defined( 'ABSPATH' ) ) exit;
class SMARSECO_Smart_Search_Control_Result {
        
    /**
     * Summary of instance
     * @var 
     */
    private static $instance = null;

    /**
     * Summary of instance
     * @return SMARSECO_Smart_Search_Control_Result
     */
    public static function instance() {

        if ( is_null( self::$instance ) && ! ( self::$instance instanceof SMARSECO_Smart_Search_Control_Result) ) {
            self::$instance = new self;
            self::$instance->smarseco_hooks();
        }
        return self::$instance;
    }

    /**
     * hooks
     * @return void
     */
    private function smarseco_hooks() {

        add_action( 'wp_enqueue_scripts', [ $this, 'smarseco_smart_search_control_result_assets' ] );
        add_shortcode( 'smart_search_result', [ $this, 'smarseco_render_smart_search_result_shortcode' ] );
        add_filter( 'the_content', [ $this , 'smarseco_override_selected_page_with_result_shortcode' ] );
    }

    /**
     * smart_search_control_result_assets
     * @return void
     */
    public function smarseco_smart_search_control_result_assets() {

        wp_register_style(
            'smart-search-control-result-style',
            plugin_dir_url(__DIR__) . 'assets/css/smart-search-control-style.css',
            array(), SMARSECO_VERSION, 'all'
            
        );
        wp_register_script( 'smart-search-control-result-js', plugin_dir_url(__DIR__) . 'assets/js/smart-search-control-ajax.js', [ 'jquery' ], SMARSECO_VERSION, true );   
    }

    /**
     * smart_search_result_shortcode
     * @return bool|string
     */
    public function smarseco_render_smart_search_result_shortcode() {

        global $wpdb;
        wp_enqueue_style( 'smart-search-control-result-style' );
        wp_enqueue_script( 'smart-search-control-result-js' );
        if ( ! $this->smarseco_table_exists( ) ) {
            ob_start();
            echo '<h3>' . esc_html__( 'Table for Smart Search Control does not exist', 'smart-search-control' ) . '</h3>';
            return ob_get_clean();
        }

        $all_public_post_types = smarseco_get_visible_post_types();
        $posts_types = $all_public_post_types;

        $categories = [];
        $tags = [];

        if ( isset( $_GET['query'] ) && ! empty( $_GET['query'] ) && isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'ssc_search_nonce' ) ) {

            $search_query = sanitize_text_field( wp_unslash( $_GET['query'] ) );
            $ssc_id = isset( $_GET['smartsearch'] ) ? absint( $_GET['smartsearch'] ) : 0;

            // Check if this is a Gutenberg block search with post types
            if ( isset( $_GET['block_post_types'] ) && ! empty( $_GET['block_post_types'] ) ) {

                $block_post_types = sanitize_text_field( wp_unslash( $_GET['block_post_types'] ) );
                $posts_types = array_map( 'trim', explode( ',', $block_post_types ) );

                // Handle block categories
                $categories = (object) array();
                if ( isset( $_GET['block_categories'] ) && ! empty( $_GET['block_categories'] ) ) {
                    $block_categories_json = sanitize_text_field( wp_unslash( $_GET['block_categories'] ) );
                    $decoded_categories = json_decode( $block_categories_json, true );
                    if ( is_array( $decoded_categories ) ) {
                        $categories = (object) $decoded_categories;
                    }
                }

                // Handle block tags
                $tags = (object) array();
                if ( isset( $_GET['block_tags'] ) && ! empty( $_GET['block_tags'] ) ) {
                    $block_tags_json = sanitize_text_field( wp_unslash( $_GET['block_tags'] ) );
                    $decoded_tags = json_decode( $block_tags_json, true );
                    if ( is_array( $decoded_tags ) ) {
                        $tags = (object) $decoded_tags;
                    }
                }

            } else {

                // Original logic for shortcode-based searches
                $cache_key = 'ssc_data_' . md5( $ssc_id );
                $cache_group = 'smart_search_control';

                $data_cached = wp_cache_get( $cache_key, $cache_group );

                if ( false === $data_cached ) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                    $result = $wpdb->get_row( $wpdb->prepare( "SELECT data FROM `{$wpdb->prefix}smart_search_control_parameters` WHERE id = %d", $ssc_id ) );

                    if ( ! empty( $result ) && isset( $result->data ) ) {
                        wp_cache_set( $cache_key, $result->data, $cache_group, HOUR_IN_SECONDS );
                        $data_cached = $result->data;
                    }
                }

                if ( ! empty( $data_cached ) ) {
                    $data = json_decode( $data_cached );

                    $categories = isset( $data->categories ) ? $data->categories : [];
                    $tags = isset( $data->tags ) ? $data->tags : [];

                    if ( isset( $data->post_type ) ) {
                        if ( is_array( $data->post_type ) ) {
                            $posts_types = $data->post_type;
                        } elseif ( is_string( $data->post_type ) ) {
                            $posts_types = array_map( 'trim', explode( ',', $data->post_type ) );
                        }
                    }
                }
            } // End of else block for shortcode-based searches

            if ( in_array( 'product', $posts_types, true ) && ! in_array( 'product_variation', $posts_types, true ) ) {
                $posts_types[] = 'product_variation';
            }
            $shortcode_content = '[smart_search_control id="' . esc_attr( $ssc_id ) . '"]';

        } else {

            $search_query = null;
            $posts_types = $all_public_post_types;
            $shortcode_content = '[smart_search_control id="0"]';
        }

        if ( is_object( $posts_types ) && isset( $posts_types->name ) ) {
            $posts_types = $posts_types->name;
        }

        if ( is_array( $posts_types ) ) {
            $posts_types = array_map( function( $post_type_object ) {
                return is_object( $post_type_object ) && isset( $post_type_object->name ) ? $post_type_object->name : $post_type_object;
            }, $posts_types );
        }

        $tax_query = [ 'relation' => 'OR' ];

        /**
         * Categories
         */
        if ( ! empty( $categories ) && is_object( $categories ) ) {
            foreach ( $categories as $taxonomy => $term_ids ) {
                if ( ! empty( $term_ids ) && is_array( $term_ids ) ) {
                    $tax_query[] = [
                        'taxonomy' => $taxonomy,
                        'field'    => 'term_id',
                        'terms'    => array_map( 'intval', $term_ids ),
                        'operator' => 'IN',
                    ];
                }
            }
        }

        /**
         * Tags
         */
        if ( ! empty( $tags ) && is_object( $tags ) ) {
            foreach ( $tags as $taxonomy => $term_ids ) {
                if ( ! empty( $term_ids ) && is_array( $term_ids ) ) {
                    $tax_query[] = [
                        'taxonomy' => $taxonomy,
                        'field'    => 'term_id',
                        'terms'    => array_map( 'intval', $term_ids ),
                        'operator' => 'IN',
                    ];
                }
            }
        }

        $args = [
            's' => $search_query,
            'post_type' => $posts_types,
            'posts_per_page' => 6,
            'post_status'    => 'publish',
            'paged' => get_query_var( 'paged', 1 ),
        ];

        if ( count( $tax_query ) > 1 ) {
            $args['tax_query'] = $tax_query;
        }
        
        $query = new WP_Query( $args );

        ob_start();
        $template_path = SMARSECO_TEMPLATES_DIR . 'template-smart-search-control-result.php';
        if ( file_exists( $template_path ) ) {
            include_once $template_path;
        }
        return ob_get_clean();
    }
    
    /**
     * override selected page with result shortcode
     */
    public function smarseco_override_selected_page_with_result_shortcode( $content ) {

        $selected_page_id = get_option( 'smart_search_control_result_page' );

        if ( is_page() && get_the_ID() == $selected_page_id ) {

            $content = do_shortcode( '[smart_search_result]' ).$content ; 
        }
        return $content;
    } 
    
    /**
     * Checks if a table exists in the database.
     */
    public function smarseco_table_exists(  ) {

        $result = SMARSECO_Smart_Search_Control::smarseco_smart_search_control_create_table();
        return $result;
    }
}
SMARSECO_Smart_Search_Control_Result::instance();
