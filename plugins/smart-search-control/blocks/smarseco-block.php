<?php

/**
 * Smart Search Control Gutenberg Block
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Class SMARSECO_Gutenberg_Block
 */
class SMARSECO_Gutenberg_Block {
    
    private static $instance = null;
    
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor function
     */
    private function __construct() {

        add_action( 'init', [ $this, 'register_block' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'ssc_gutenberg_block_enqueue_scripts' ] );
    }
    
    /**
     * Enqueue scripts for Gutenberg block editor
     */
    public function ssc_gutenberg_block_enqueue_scripts() {

        wp_enqueue_style(
            'smarseco-gutenberg-block-admin',
            SMARSECO_ASSETS_URL . 'css/smart-search-control-block.css',
            [],
            SMARSECO_VERSION,
            'all'
        );
    }

    /**
     * Register Smart Search Control Gutenberg Block
     */
    public function register_block() {

        wp_register_script(
            'smart-search-control-gutenberg-block',
            SMARSECO_URL . 'assets/js/smart-search-control-block.js',
            array( 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-block-editor' ),
            SMARSECO_VERSION
        );
        
        wp_localize_script(
            'smart-search-control-gutenberg-block',
            'smarsecoBlockData',
            array( 'availablePostTypes' => $this->get_post_types() )
        );
        
        register_block_type( 'smart-search-control/search-block', array(
            'editor_script' => 'smart-search-control-gutenberg-block',
            'render_callback' => array( $this, 'ssc_render_block' ),
            'attributes' => array(
                'placeholder' => array( 'type' => 'string', 'default' => 'Search...' ),
                'cssId' => array( 'type' => 'string', 'default' => '' ),
                'cssClass' => array( 'type' => 'string', 'default' => '' ),
                'postTypes' => array( 'type' => 'array', 'default' => array() )
            )
        ) );
    }
    
    /**
     * Get available post types for the block
     * @return array
     */
    private function get_post_types() {

        $post_types = array();
        
        $post_types_data = smarseco_get_visible_post_types();
        if ( is_array( $post_types_data ) ) {
            foreach ( $post_types_data as $post_type ) {
                $post_types[] = array( 'value' => $post_type, 'label' => $post_type );
            }
        }
        
        return $post_types;
    }
    
    /**
     * Render the block on the front end
     * @param array $attributes
     * @return string
     */
    public function ssc_render_block( $attributes ) {

        $placeholder = isset( $attributes['placeholder'] ) ? $attributes['placeholder'] : 'Search...';
        $css_id = isset( $attributes['cssId'] ) ? $attributes['cssId'] : '';
        $css_class = isset( $attributes['cssClass'] ) ? $attributes['cssClass'] : '';
        $post_types = isset( $attributes['postTypes'] ) ? $attributes['postTypes'] : array();
        
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_style( 'smart-search-control-style' );
        wp_enqueue_script( 'smart-search-control-js' );
        
        if ( empty( $post_types ) ) {
            $post_types = smarseco_get_visible_post_types();
        }
        
        if ( in_array( 'product', $post_types, true ) && ! in_array( 'product_variation', $post_types, true ) ) {
            $post_types[] = 'product_variation';
        }
        
        $url = get_permalink( get_option( 'smart_search_control_result_page' ) );
        
        $search_query = '';
        if ( isset( $_GET['query'] ) && isset( $_GET['nonce'] ) && 
             wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'ssc_search_nonce' ) ) {
            $search_query = sanitize_text_field( wp_unslash( $_GET['query'] ) );
        }
        
        $ssc_id = 'block_' . uniqid();
        $class = $css_class;
        $block_posts_types = $post_types;
        
        ob_start();
        $template_path = SMARSECO_TEMPLATES_DIR . 'template-smart-search-control.php';
        if ( file_exists( $template_path ) ) {
            include $template_path;
        }
        return ob_get_clean();
    }
}

SMARSECO_Gutenberg_Block::instance();