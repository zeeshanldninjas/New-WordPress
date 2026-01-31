<?php

/**
 * Smart Search Control Main Plugin functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get all visible post types
 */
function smarseco_get_visible_post_types() {

    $args = [
        'public' => true,
        'publicly_queryable' => true,
    ];
    $visible_post_types = get_post_types( $args, 'objects' );
    if (!array_key_exists( 'page', $visible_post_types ) ) {
        $visible_post_types[ 'page' ] = get_post_type_object( 'page' );
    }
    $all_public_post_types = array_keys( $visible_post_types );
    return apply_filters( 'visible_post_types', $all_public_post_types );
}
/**
 * Get categories for specific post types
 * 
 * @param array $post_types Array of post type names
 * @return array Array of category objects
 */
function smarseco_get_categories_for_post_types( $post_types = [] ) {
    
    if ( empty( $post_types ) ) {
        return [];
    }
    
    $categories = [];
    
    foreach ( $post_types as $post_type ) {
        $taxonomies = get_object_taxonomies( $post_type, 'objects' );
        
        foreach ( $taxonomies as $taxonomy ) {
            if ( $taxonomy->hierarchical && $taxonomy->public ) {
                $terms = get_terms( [
                    'taxonomy' => $taxonomy->name,
                    'hide_empty' => false,
                    'number' => 0
                ] );
                
                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    foreach ( $terms as $term ) {
                        $key = $taxonomy->name . ':' . $term->term_id;
                        if ( ! isset( $categories[ $key ] ) ) {
                            $categories[ $key ] = [
                                'id' => $term->term_id,
                                'name' => $term->name,
                                'taxonomy' => $taxonomy->name,
                                'taxonomy_label' => $taxonomy->label,
                                'post_type' => $post_type
                            ];
                        }
                    }
                }
            }
        }
    }
    
    return apply_filters( 'smarseco_categories_for_post_types', $categories, $post_types );
}

/**
 * Get tags for specific post types
 * 
 * @param array $post_types Array of post type names
 * @return array Array of tag objects
 */
function smarseco_get_tags_for_post_types( $post_types = [] ) {
    
    if ( empty( $post_types ) ) {
        return [];
    }
    
    $tags = [];
    
    foreach ( $post_types as $post_type ) {
        $taxonomies = get_object_taxonomies( $post_type, 'objects' );
        
        foreach ( $taxonomies as $taxonomy ) {
            if ( ! $taxonomy->hierarchical && $taxonomy->public ) {
                $terms = get_terms( [
                    'taxonomy' => $taxonomy->name,
                    'hide_empty' => false,
                    'number' => 0
                ] );
                
                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    foreach ( $terms as $term ) {
                        $key = $taxonomy->name . ':' . $term->term_id;
                        if ( ! isset( $tags[ $key ] ) ) {
                            $tags[ $key ] = [
                                'id' => $term->term_id,
                                'name' => $term->name,
                                'taxonomy' => $taxonomy->name,
                                'taxonomy_label' => $taxonomy->label,
                                'post_type' => $post_type
                            ];
                        }
                    }
                }
            }
        }
    }
    
    return apply_filters( 'smarseco_tags_for_post_types', $tags, $post_types );
}
