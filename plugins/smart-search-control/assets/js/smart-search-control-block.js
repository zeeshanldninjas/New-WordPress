( function ( blocks, blockEditor, element, components ) {

    const el = element.createElement;
    const { InspectorControls } = blockEditor;
    const { PanelBody, TextControl, CheckboxControl, SelectControl, Spinner } = components;
    const { useState, useEffect } = element;
    const { apiFetch } = wp.apiFetch ? wp : { apiFetch: null };

    blocks.registerBlockType( 'smart-search-control/search-block', {
        title: 'Smart Search Control',
        icon: 'search',
        category: 'widgets',
        keywords: ['search', 'smart', 'filter', 'find'],

        attributes: {
            placeholder: { 
                type: 'string', 
                default: 'Search...' 
            },
            cssId: { 
                type: 'string', 
                default: '' 
            },
            cssClass: { 
                type: 'string', 
                default: '' 
            },
            postTypes: { 
                type: 'array', 
                default: [] 
            },
            categories: { 
                type: 'object', 
                default: {} 
            },
            tags: { 
                type: 'object', 
                default: {} 
            }
        },

        edit: function ( props ) {
            const { attributes, setAttributes } = props;
            const { placeholder, cssId, cssClass, postTypes, categories, tags } = attributes;
            
            // Ensure backward compatibility with existing blocks
            const safeCategories = categories || {};
            const safeTags = tags || {};
            const safePostTypes = postTypes || [];
            
            // Get dynamic post types from PHP (via wp_localize_script)
            const availablePostTypes = window.smarsecoBlockData && window.smarsecoBlockData.availablePostTypes 
                ? window.smarsecoBlockData.availablePostTypes 
                : [
                    { value: 'post', label: 'Posts' },
                    { value: 'page', label: 'Pages' }
                ];
            const isLoading = false;
            
            // State for categories and tags
            const [availableCategories, setAvailableCategories] = useState({});
            const [availableTags, setAvailableTags] = useState({});
            const [taxonomiesLoading, setTaxonomiesLoading] = useState(false);

            // Handle post type checkbox change
            const handlePostTypeChange = function(value, checked) {
                const newPostTypes = safePostTypes.slice();
                if (checked) {
                    if (newPostTypes.indexOf(value) === -1) {
                        newPostTypes.push(value);
                    }
                } else {
                    const index = newPostTypes.indexOf(value);
                    if (index > -1) {
                        newPostTypes.splice(index, 1);
                    }
                }
                setAttributes({ postTypes: newPostTypes });
            };

            // Handle select all post types
            const handleSelectAll = function(checked) {
                if (checked) {
                    const allTypes = [];
                    for (let i = 0; i < availablePostTypes.length; i++) {
                        allTypes.push(availablePostTypes[i].value);
                    }
                    setAttributes({ postTypes: allTypes });
                } else {
                    setAttributes({ postTypes: [] });
                }
            };

            const allSelected = availablePostTypes.length > 0 && safePostTypes.length === availablePostTypes.length;

            // Load taxonomies when post types change
            useEffect(function() {
                if (safePostTypes.length === 0) {
                    setAvailableCategories({});
                    setAvailableTags({});
                    return;
                }

                // Check if apiFetch is available
                if (!apiFetch) {
                    console.warn('wp.apiFetch not available, skipping taxonomy loading');
                    return;
                }

                setTaxonomiesLoading(true);
                
                // Load taxonomies for each selected post type
                const loadPromises = safePostTypes.map(function(postType) {
                    return apiFetch({
                        path: '/smart-search-control/v1/taxonomies/' + postType
                    });
                });

                Promise.all(loadPromises)
                    .then(function(responses) {
                        const mergedCategories = {};
                        const mergedTags = {};
                        
                        responses.forEach(function(response) {
                            // Merge categories
                            Object.keys(response.categories || {}).forEach(function(taxonomy) {
                                if (!mergedCategories[taxonomy]) {
                                    mergedCategories[taxonomy] = [];
                                }
                                mergedCategories[taxonomy] = mergedCategories[taxonomy].concat(response.categories[taxonomy]);
                            });
                            
                            // Merge tags
                            Object.keys(response.tags || {}).forEach(function(taxonomy) {
                                if (!mergedTags[taxonomy]) {
                                    mergedTags[taxonomy] = [];
                                }
                                mergedTags[taxonomy] = mergedTags[taxonomy].concat(response.tags[taxonomy]);
                            });
                        });
                        
                        setAvailableCategories(mergedCategories);
                        setAvailableTags(mergedTags);
                    })
                    .catch(function(error) {
                        console.error('Error loading taxonomies:', error);
                        setAvailableCategories({});
                        setAvailableTags({});
                    })
                    .finally(function() {
                        setTaxonomiesLoading(false);
                    });
            }, [safePostTypes]);

            // Handle category selection
            const handleCategoryChange = function(taxonomy, termIds) {
                const newCategories = Object.assign({}, safeCategories);
                if (termIds.length > 0) {
                    newCategories[taxonomy] = termIds;
                } else {
                    delete newCategories[taxonomy];
                }
                setAttributes({ categories: newCategories });
            };

            // Handle tag selection
            const handleTagChange = function(taxonomy, termIds) {
                const newTags = Object.assign({}, safeTags);
                if (termIds.length > 0) {
                    newTags[taxonomy] = termIds;
                } else {
                    delete newTags[taxonomy];
                }
                setAttributes({ tags: newTags });
            };

            // Create post type controls
            const postTypeElements = [];
            
            if (!isLoading) {
                // Add Select All checkbox
                postTypeElements.push(
                    el( CheckboxControl, {
                        key: 'select-all',
                        label: 'Select All',
                        checked: allSelected,
                        onChange: handleSelectAll
                    })
                );
                
                // Add individual post type checkboxes
                for (let i = 0; i < availablePostTypes.length; i++) {
                    const postType = availablePostTypes[i];
                    postTypeElements.push(
                        el( CheckboxControl, {
                            key: postType.value,
                            label: postType.label,
                            checked: safePostTypes.indexOf(postType.value) !== -1,
                            onChange: function(checked) {
                                handlePostTypeChange(postType.value, checked);
                            }
                        })
                    );
                }
            }

            // Create the block preview (Search Input UI)
            const blockPreview = el(
                'div',
                { className: 'smarseco-default-search-bar-container' },

                el(
                    'form',
                    {
                        className: 'smarseco-default-search-bar ssc-search-form',
                        style: { pointerEvents: 'none' } // prevent submit in editor
                    },

                    el('input', {
                        type: 'text',
                        className: 'smarseco-default-search-input search-query',
                        placeholder: placeholder || 'Search...',
                        disabled: true
                    }),

                    el(
                        'button',
                        {
                            type: 'button',
                            className: 'smarseco-default-search-btn search-btn'
                        },
                        el(
                            'span',
                            { className: 'smarseco-default-search-icon' },
                            el('span', { className: 'dashicons dashicons-search' })
                        )
                    )
                )
            );

            return el( 'div', {},
                // Inspector Controls (Sidebar)
                el( InspectorControls, {},
                    el( PanelBody, { 
                        title: 'Search Settings', 
                        initialOpen: true 
                    },
                        el( TextControl, {
                            label: 'Placeholder Text',
                            value: placeholder,
                            onChange: function(value) {
                                setAttributes({ placeholder: value });
                            },
                            help: 'Text to display in the search input field'
                        })
                    ),

                    el( PanelBody, { 
                        title: 'Advanced Settings', 
                        initialOpen: false 
                    },
                        el( TextControl, {
                            label: 'CSS ID',
                            value: cssId,
                            onChange: function(value) {
                                setAttributes({ cssId: value });
                            },
                            help: 'Optional unique identifier for styling'
                        }),
                        el( TextControl, {
                            label: 'CSS Class',
                            value: cssClass,
                            onChange: function(value) {
                                setAttributes({ cssClass: value });
                            },
                            help: 'Optional CSS classes for styling'
                        })
                    ),

                    el( PanelBody, { 
                        title: 'Post Types', 
                        initialOpen: true 
                    },
                        isLoading ? 
                            el( 'div', { style: { textAlign: 'center', padding: '20px' } },
                                el( Spinner )
                            ) :
                            postTypeElements
                    ),

                    // Categories Panel
                    el( PanelBody, { 
                        title: 'Categories', 
                        initialOpen: false 
                    },
                        safePostTypes.length === 0 ? 
                            el( 'p', { style: { fontStyle: 'italic', color: '#666' } },
                                'Select post types first to enable category filtering.'
                            ) :
                            taxonomiesLoading ?
                                el( 'div', { style: { textAlign: 'center', padding: '20px' } },
                                    el( Spinner )
                                ) :
                                Object.keys(availableCategories).length === 0 ?
                                    el( 'p', { style: { fontStyle: 'italic', color: '#666' } },
                                        'No categories available for selected post types.'
                                    ) :
                                    Object.keys(availableCategories).map(function(taxonomy) {
                                        const taxonomyTerms = availableCategories[taxonomy];
                                        const selectedTerms = safeCategories[taxonomy] || [];
                                        
                                        const options = [{ value: '', label: 'Select categories...' }].concat(
                                            taxonomyTerms.map(function(term) {
                                                return { value: term.id.toString(), label: term.name };
                                            })
                                        );
                                        
                                        return el( SelectControl, {
                                            key: taxonomy,
                                            label: taxonomy.charAt(0).toUpperCase() + taxonomy.slice(1).replace('_', ' '),
                                            multiple: true,
                                            value: selectedTerms.map(function(id) { return id.toString(); }),
                                            options: options,
                                            onChange: function(newValues) {
                                                const termIds = newValues.filter(function(val) { return val !== ''; }).map(function(val) { return parseInt(val); });
                                                handleCategoryChange(taxonomy, termIds);
                                            },
                                            help: 'Select categories to filter search results within this taxonomy.'
                                        });
                                    })
                    ),

                    // Tags Panel
                    el( PanelBody, { 
                        title: 'Tags', 
                        initialOpen: false 
                    },
                        safePostTypes.length === 0 ? 
                            el( 'p', { style: { fontStyle: 'italic', color: '#666' } },
                                'Select post types first to enable tag filtering.'
                            ) :
                            taxonomiesLoading ?
                                el( 'div', { style: { textAlign: 'center', padding: '20px' } },
                                    el( Spinner )
                                ) :
                                Object.keys(availableTags).length === 0 ?
                                    el( 'p', { style: { fontStyle: 'italic', color: '#666' } },
                                        'No tags available for selected post types.'
                                    ) :
                                    Object.keys(availableTags).map(function(taxonomy) {
                                        const taxonomyTerms = availableTags[taxonomy];
                                        const selectedTerms = safeTags[taxonomy] || [];
                                        
                                        const options = [{ value: '', label: 'Select tags...' }].concat(
                                            taxonomyTerms.map(function(term) {
                                                return { value: term.id.toString(), label: term.name };
                                            })
                                        );
                                        
                                        return el( SelectControl, {
                                            key: taxonomy,
                                            label: taxonomy.charAt(0).toUpperCase() + taxonomy.slice(1).replace('_', ' '),
                                            multiple: true,
                                            value: selectedTerms.map(function(id) { return id.toString(); }),
                                            options: options,
                                            onChange: function(newValues) {
                                                const termIds = newValues.filter(function(val) { return val !== ''; }).map(function(val) { return parseInt(val); });
                                                handleTagChange(taxonomy, termIds);
                                            },
                                            help: 'Select tags to filter search results within this taxonomy.'
                                        });
                                    })
                    )
                ),

                // Block Preview
                blockPreview
            );
        },

        save: function () {
            // Return null since we're using server-side rendering
            return null;
        }
    });

} )(
    window.wp.blocks,
    window.wp.blockEditor,
    window.wp.element,
    window.wp.components
);
