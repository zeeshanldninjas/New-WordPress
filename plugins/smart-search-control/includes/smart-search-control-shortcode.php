<?php
/**
 * Smart_Search_Control Shortcode
 */

if ( ! defined( 'ABSPATH' ) ) exit;
class SMARSECO_Smart_Search_Control_Short_Code {
        
    /**
     * Summary of instance
     * @var 
     */
    private static $instance = null;

    /**
     * Attributes for the search shortcode.
     */
    private $atts = [];


    /**
     * Summary of visible_post_types
     * @var array
     */
    private static $visible_post_types = [];

    /**
     * Constructor
     */
    public static function instance() {

        if ( is_null( self::$instance ) && ! ( self::$instance instanceof SMARSECO_Smart_Search_Control_Short_Code ) ) {

            self::$instance = new self;
            self::$instance->smarseco_hooks();
        }
        return self::$instance;
    }

    /**
     * All the required hooks are called here.
     */
    private function smarseco_hooks() {

        add_action( 'init', [ $this, 'smarseco_initialize_visible_post_types' ], 100 );
        add_action( 'wp_enqueue_scripts', [ $this, 'smarseco_smart_search_control_assets' ] );
        add_action( 'wp_ajax_smarseco_smart_search_control_suggestion', [ $this, 'smarseco_smart_search_control_suggestion' ] );
        add_action( 'wp_ajax_nopriv_smarseco_smart_search_control_suggestion', [ $this, 'smarseco_smart_search_control_suggestion' ] );
        add_shortcode( 'smart_search_control', [ $this, 'smarseco_render_search_shortcode' ] );
    }

    /**
     * Static method to initialize visible_post_types
     */
    public static function smarseco_initialize_visible_post_types() {

        if ( empty( self::$visible_post_types ) ) {
            
            self::$visible_post_types = smarseco_get_visible_post_types();
        }
    }

    /**
     * Load the Assets
     */
    public function smarseco_smart_search_control_assets() {

        wp_register_style(
            'smart-search-control-style',
            plugin_dir_url(__DIR__) . 'assets/css/smart-search-control-style.css',
            array(), SMARSECO_VERSION, 'all'
        );
        wp_register_script( 'smart-search-control-js', plugin_dir_url(__DIR__) . 'assets/js/smart-search-control-ajax.js', [ 'jquery' ], SMARSECO_VERSION, true );
        wp_localize_script( 'smart-search-control-js', 'SMART_SEARCH_CONTROL', [
            'ajaxurl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'smart_search_control_result_nonce' ),
            'search_msg' => __( 'Searching...' , 'smart-search-control' ),
            'more_msg'   => __( 'See more...', 'smart-search-control' ),
        ]);   
    }

    /**
     * Render the search shortcode
     */
    public function smarseco_render_search_shortcode( $atts ) {

        global $wpdb;

        if ( ! $this->smarseco_table_exists() ) {

            ob_start();
            echo '<h3>' . esc_html( __( 'Table for Smart Search Control does not exist', 'smart-search-control' ) ). '</h3>';
            return ob_get_clean();
        }
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'smart-search-control-style' );
        wp_enqueue_script( 'smart-search-control-js' );
    
        $sanitized_atts = shortcode_atts(
            [
                'id'           => '',
                'css_id'       => '',
                'css_class'    => '',
                'place_holder' => __( 'Search...' , 'smart-search-control' ),
                'post_type'    => [],
            ],
            $atts,
            'smart_search_control'
        );
        $ssc_id =  isset( $sanitized_atts['id'] ) ? $sanitized_atts['id'] : 0;
        $search_query = '';
        if ( filter_var( $ssc_id, FILTER_VALIDATE_INT ) === false || intval( $ssc_id ) < 0 ) {

            return '<p>' . esc_html(__( 'Invalid ID provided.', 'smart-search-control' ) ) . '</p>';
        }

        $all_public_post_types = self::$visible_post_types;
        $posts_types = $all_public_post_types;

        $cache_key = 'ssc_data_' . $ssc_id;
        $result = wp_cache_get( $cache_key, 'smart_search_control' );

        if ( false === $result ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->get_row( $wpdb->prepare( "SELECT data FROM `{$wpdb->prefix}smart_search_control_parameters` WHERE id = %d",$ssc_id  ) );
            wp_cache_set( $cache_key, $result, 'smart_search_control', 12 * HOUR_IN_SECONDS );
        }
            
        $class       = $sanitized_atts[ 'css_class' ];
        $css_id      = $sanitized_atts[ 'css_id' ];
        $placeholder = $sanitized_atts[ 'place_holder' ];
    
        if( ! empty( $result ) && isset( $result->data ) ) {

            $data        = json_decode( $result->data );
            $class       = isset( $data->class ) ? $data->class : $class;
            $css_id      = isset( $data->css_id ) ? $data->css_id : $css_id;
            $placeholder = isset( $data->place_holder ) ? $data->place_holder : $placeholder;
            if ( isset( $data->post_type ) && !empty( $data->post_type ) ) {
                if ( is_array( $data->post_type ) ) {
                    $posts_types = $data->post_type;
                } elseif ( is_string( $data->post_type ) ) {
                    $posts_types = array_map( 'trim', explode( ',', $data->post_type ) );
                } else {
                    $posts_types = $all_public_post_types;
                }
            } else {
                $posts_types = $all_public_post_types;
            }
        } else {
            if ( $ssc_id === '0' ) {
                $posts_types = $all_public_post_types;
            } else {
                return '<p>' . esc_html( __( 'Invalid ID provided.', 'smart-search-control' ) ) . '</p>';
            }
        }

        if ( in_array( 'product', $posts_types, true ) && ! in_array( 'product_variation', $posts_types, true ) ) {
            $posts_types[] = 'product_variation';
        }
        
        $url = '';
        $fallback_page_id = get_option( 'smart_search_control_result_page' );

        $url = get_permalink( $fallback_page_id );

        if ( isset( $_GET[ 'query' ] ) && isset( $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ 'nonce' ] ) ) , 'ssc_search_nonce') )  {
            $search_query = sanitize_text_field( wp_unslash( $_GET[ 'query' ] ) );
        }

        ob_start();
        $template_path = SMARSECO_TEMPLATES_DIR . 'template-smart-search-control.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
        return ob_get_clean();
    }
    
    /**
     * Summary of search_suggestion
     */
    public function smarseco_smart_search_control_suggestion() {

        global $wpdb;
        if ( ! isset( $_POST['nonce'] ) || ! check_ajax_referer( 'smart_search_control_result_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => __( 'Nonce verification failed', 'smart-search-control' ) ] );
        }
        if ( empty( $_POST['search_query'] ) ) {
            wp_send_json_error( [ 'message' => __( 'Search query is empty.', 'smart-search-control' ) ] );
        }
        $search_query = sanitize_text_field( wp_unslash( $_POST['search_query'] ) );
        $ssc_id = isset( $_POST['ssc_id'] ) 
            ? intval( $_POST['ssc_id'] )
            : 0;

        $categories = [];
        $tags = [];

        // Check if this is a Gutenberg block search with post types
        if ( isset( $_POST['block_post_types'] ) && ! empty( $_POST['block_post_types'] ) ) {

            $block_post_types = sanitize_text_field( wp_unslash( $_POST['block_post_types'] ) );
            $posts_types = array_map( 'trim', explode( ',', $block_post_types ) );
            
        } else {
            // Original logic for shortcode-based searches
            if ( ! $this->smarseco_table_exists(  ) ) {
                ob_start();
                echo '<h3>' . esc_html( __( 'Table for Smart Search Control does not exist', 'smart-search-control' ) ) . '</h3>';
                return ob_get_clean();
            }

            $cache_key = 'ssc_data_' . $ssc_id;
            $cache_group = 'smart_search_control';
            $result = wp_cache_get( $cache_key, $cache_group );
            if ( false === $result ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
                $result = $wpdb->get_row( $wpdb->prepare( "SELECT data FROM `{$wpdb->prefix}smart_search_control_parameters` WHERE id = %d",  $ssc_id  ) );
                wp_cache_set( $cache_key, $result, $cache_group, 12 * HOUR_IN_SECONDS );
            }

            $all_public_post_types = self::$visible_post_types;
            $posts_types = $all_public_post_types;

            if ( ! empty( $result ) && isset( $result->data ) ) {
                $data = json_decode( $result->data );

                $categories = isset( $data->categories ) ? $data->categories : [];
                $tags = isset( $data->tags ) ? $data->tags : [];

                if ( isset( $data->post_type ) && ! empty( $data->post_type ) ) {
                    if ( is_array( $data->post_type ) ) {
                        $posts_types = $data->post_type;
                    } elseif ( is_string( $data->post_type ) ) {
                        $posts_types = array_map( 'trim', explode( ',', $data->post_type ) );
                    } else {
                        $posts_types = $all_public_post_types;
                    }
                } else {
                    $posts_types = $all_public_post_types;
                }
            } else {
                $posts_types = $all_public_post_types;
            }
        } // End of else block for shortcode-based searches
        
        if ( in_array( 'product', $posts_types, true ) && ! in_array( 'product_variation', $posts_types, true ) ) {
            $posts_types[] = 'product_variation';
        }

        $post_per_page = apply_filters( 'smarseco_search_suggestion_per_page', 10 );
        
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
            's'             => $search_query,
            'post_type'     => $posts_types,
            'posts_per_page'=> $post_per_page,
            'post_status'   => 'publish',
        ];

        if ( count( $tax_query ) > 1 ) {
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query( $args );
        $posts = [];
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $posts[] = [
                    'id'        => get_the_ID(),
                    'title'     => get_the_title(),
                    'permalink' => get_permalink(),
                ];
            }
            wp_reset_postdata();
        }
        if ( empty( $posts ) ) {
            wp_send_json_error( [ 'message' => __( 'No results found.', 'smart-search-control' ) ] );
        }
        
        foreach ( $posts as &$post ) {
            $decoded_title = html_entity_decode( $post['title'], ENT_QUOTES | ENT_HTML5 );
            $parts = preg_split( '/\s*[-–—]\s*/u', $decoded_title );
            $post['title'] = implode( ' ', $parts );
        }
        unset( $post );
        wp_send_json_success( [ 'search_results' => $posts ] );
        wp_die();
    }

    /**
     * Checks if a table exists in the database.
     */
    public function smarseco_table_exists( ) {

        $exists = SMARSECO_Smart_Search_Control::smarseco_smart_search_control_create_table();
        return $exists;
    }
}
SMARSECO_Smart_Search_Control_Short_Code::instance();
