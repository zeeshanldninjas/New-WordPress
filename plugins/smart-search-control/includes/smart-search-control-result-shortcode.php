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

        if ( isset( $_GET['query'] ) && ! empty( $_GET['query'] ) && isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'ssc_search_nonce' ) ) {

            $search_query = sanitize_text_field( wp_unslash( $_GET['query'] ) );
            $ssc_id = isset( $_GET['smartsearch'] ) ? absint( $_GET['smartsearch'] ) : 0;
            
            // Get categories and tags from URL parameters
            $url_categories = isset( $_GET['categories'] ) && is_array( $_GET['categories'] ) 
                ? array_map( 'sanitize_text_field', wp_unslash( $_GET['categories'] ) ) 
                : [];
            $url_tags = isset( $_GET['tags'] ) && is_array( $_GET['tags'] ) 
                ? array_map( 'sanitize_text_field', wp_unslash( $_GET['tags'] ) ) 
                : [];

            // Check if this is a Gutenberg block search with post types
            if ( isset( $_GET['block_post_types'] ) && ! empty( $_GET['block_post_types'] ) ) {

                $block_post_types = sanitize_text_field( wp_unslash( $_GET['block_post_types'] ) );
                $posts_types = array_map( 'trim', explode( ',', $block_post_types ) );

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

                    if ( isset( $data->post_type ) ) {
                        if ( is_array( $data->post_type ) ) {
                            $posts_types = $data->post_type;
                        } elseif ( is_string( $data->post_type ) ) {
                            $posts_types = array_map( 'trim', explode( ',', $data->post_type ) );
                        }
                    }
                    
                    // Extract categories and tags from stored configuration (new grouped format)
                    $stored_categories = isset( $data->categories ) ? $data->categories : [];
                    $stored_tags = isset( $data->tags ) ? $data->tags : [];
                    
                    // Convert objects to arrays if needed
                    $stored_categories = is_object( $stored_categories ) ? (array) $stored_categories : ( is_array( $stored_categories ) ? $stored_categories : [] );
                    $stored_tags = is_object( $stored_tags ) ? (array) $stored_tags : ( is_array( $stored_tags ) ? $stored_tags : [] );
                } else {
                    $stored_categories = [];
                    $stored_tags = [];
                }
            } // End of else block for shortcode-based searches

            if ( in_array( 'product', $posts_types, true ) && ! in_array( 'product_variation', $posts_types, true ) ) {
                $posts_types[] = 'product_variation';
            }
            
            // Merge URL parameters with stored configuration (URL parameters take precedence)
            $final_categories = ! empty( $url_categories ) ? $url_categories : ( isset( $stored_categories ) ? $stored_categories : [] );
            $final_tags = ! empty( $url_tags ) ? $url_tags : ( isset( $stored_tags ) ? $stored_tags : [] );
            
            $shortcode_content = '[smart_search_control id="' . esc_attr( $ssc_id ) . '"]';

        } else {

            $search_query = null;
            $posts_types = $all_public_post_types;
            $final_categories = [];
            $final_tags = [];
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

        $args = [
            's' => $search_query,
            'post_type' => $posts_types,
            'posts_per_page' => 6,
            'post_status'    => 'publish',
            'paged' => get_query_var( 'paged', 1 ),
        ];
        
        // Add taxonomy filtering if categories or tags are selected
        $tax_query = $this->smarseco_build_tax_query( $final_categories, $final_tags );
        if ( ! empty( $tax_query ) ) {
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
    
    /**
     * Build taxonomy query for categories and tags filtering
     * 
     * @param array $categories Array of grouped categories: ['taxonomy' => [term_ids]]
     * @param array $tags Array of grouped tags: ['taxonomy' => [term_ids]]
     * @return array Tax query array for WP_Query
     */
    private function smarseco_build_tax_query( $categories = [], $tags = [] ) {
        
        $tax_query = [];
        
        if ( empty( $categories ) && empty( $tags ) ) {
            return $tax_query;
        }
        
        // Combine all taxonomies from categories and tags
        $all_taxonomies = [];
        
        // Process categories (already grouped by taxonomy)
        if ( ! empty( $categories ) && is_array( $categories ) ) {
            foreach ( $categories as $taxonomy => $term_ids ) {
                $taxonomy = sanitize_text_field( $taxonomy );
                if ( taxonomy_exists( $taxonomy ) && ! empty( $term_ids ) && is_array( $term_ids ) ) {
                    $valid_term_ids = array_filter( array_map( 'intval', $term_ids ), function( $id ) {
                        return $id > 0;
                    });
                    
                    if ( ! empty( $valid_term_ids ) ) {
                        if ( ! isset( $all_taxonomies[ $taxonomy ] ) ) {
                            $all_taxonomies[ $taxonomy ] = [];
                        }
                        $all_taxonomies[ $taxonomy ] = array_merge( $all_taxonomies[ $taxonomy ], $valid_term_ids );
                    }
                }
            }
        }
        
        // Process tags (already grouped by taxonomy)
        if ( ! empty( $tags ) && is_array( $tags ) ) {
            foreach ( $tags as $taxonomy => $term_ids ) {
                $taxonomy = sanitize_text_field( $taxonomy );
                if ( taxonomy_exists( $taxonomy ) && ! empty( $term_ids ) && is_array( $term_ids ) ) {
                    $valid_term_ids = array_filter( array_map( 'intval', $term_ids ), function( $id ) {
                        return $id > 0;
                    });
                    
                    if ( ! empty( $valid_term_ids ) ) {
                        if ( ! isset( $all_taxonomies[ $taxonomy ] ) ) {
                            $all_taxonomies[ $taxonomy ] = [];
                        }
                        $all_taxonomies[ $taxonomy ] = array_merge( $all_taxonomies[ $taxonomy ], $valid_term_ids );
                    }
                }
            }
        }
        
        // Build tax_query
        if ( ! empty( $all_taxonomies ) ) {
            $tax_query['relation'] = 'AND'; // Posts must match all selected taxonomies
            
            foreach ( $all_taxonomies as $taxonomy => $term_ids ) {
                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => array_unique( $term_ids ),
                    'operator' => 'IN' // Posts can match any of the selected terms within this taxonomy
                ];
            }
        }
        
        return $tax_query;
    }
}
SMARSECO_Smart_Search_Control_Result::instance();
