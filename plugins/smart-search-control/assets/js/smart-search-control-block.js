( function ( blocks, blockEditor, element, components ) {

    const el = element.createElement;
    const { InspectorControls } = blockEditor;
    const { PanelBody, TextControl, CheckboxControl, SelectControl, Spinner } = components;
    const { useState, useEffect, useRef } = element;
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
            
            // Refs for Select2 elements
            const categoriesSelectRef = useRef(null);
            const tagsSelectRef = useRef(null);

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

            // Helper function to create options with optgroups for categories
            const getCategoriesOptions = function() {
                const options = [];
                Object.keys(availableCategories).forEach(function(taxonomy) {
                    const taxonomyTerms = availableCategories[taxonomy];
                    const taxonomyLabel = taxonomy.charAt(0).toUpperCase() + taxonomy.slice(1).replace('_', ' ');
                    
                    // Add taxonomy header (disabled option)
                    options.push({
                        value: '',
                        label: '--- ' + taxonomyLabel + ' ---',
                        disabled: true
                    });
                    
                    // Add terms for this taxonomy
                    taxonomyTerms.forEach(function(term) {
                        options.push({
                            value: taxonomy + ':' + term.id,
                            label: '  ' + term.name
                        });
                    });
                });
                return options;
            };

            // Helper function to create options with optgroups for tags
            const getTagsOptions = function() {
                const options = [];
                Object.keys(availableTags).forEach(function(taxonomy) {
                    const taxonomyTerms = availableTags[taxonomy];
                    const taxonomyLabel = taxonomy.charAt(0).toUpperCase() + taxonomy.slice(1).replace('_', ' ');
                    
                    // Add taxonomy header (disabled option)
                    options.push({
                        value: '',
                        label: '--- ' + taxonomyLabel + ' ---',
                        disabled: true
                    });
                    
                    // Add terms for this taxonomy
                    taxonomyTerms.forEach(function(term) {
                        options.push({
                            value: taxonomy + ':' + term.id,
                            label: '  ' + term.name
                        });
                    });
                });
                return options;
            };

            // Helper function to get selected values for categories
            const getCategoriesSelectedValues = function() {
                const selectedValues = [];
                Object.keys(safeCategories).forEach(function(taxonomy) {
                    const termIds = safeCategories[taxonomy] || [];
                    termIds.forEach(function(termId) {
                        selectedValues.push(taxonomy + ':' + termId);
                    });
                });
                return selectedValues;
            };

            // Helper function to get selected values for tags
            const getTagsSelectedValues = function() {
                const selectedValues = [];
                Object.keys(safeTags).forEach(function(taxonomy) {
                    const termIds = safeTags[taxonomy] || [];
                    termIds.forEach(function(termId) {
                        selectedValues.push(taxonomy + ':' + termId);
                    });
                });
                return selectedValues;
            };

            // Handle categories change
            const handleCategoriesChange = function(newValues) {
                const newCategories = {};
                newValues.forEach(function(value) {
                    const parts = value.split(':');
                    if (parts.length === 2) {
                        const taxonomy = parts[0];
                        const termId = parseInt(parts[1]);
                        if (!newCategories[taxonomy]) {
                            newCategories[taxonomy] = [];
                        }
                        newCategories[taxonomy].push(termId);
                    }
                });
                setAttributes({ categories: newCategories });
            };

            // Handle tags change
            const handleTagsChange = function(newValues) {
                const newTags = {};
                newValues.forEach(function(value) {
                    const parts = value.split(':');
                    if (parts.length === 2) {
                        const taxonomy = parts[0];
                        const termId = parseInt(parts[1]);
                        if (!newTags[taxonomy]) {
                            newTags[taxonomy] = [];
                        }
                        newTags[taxonomy].push(termId);
                    }
                });
                setAttributes({ tags: newTags });
            };

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

            // Select2 initialization and management
            const initializeSelect2 = function() {
                if (window.jQuery && window.jQuery.fn.select2) {
                    const $ = window.jQuery;
                    
                    // Initialize Categories Select2
                    if (categoriesSelectRef.current && !$(categoriesSelectRef.current).hasClass('select2-hidden-accessible')) {
                        $(categoriesSelectRef.current).select2({
                            placeholder: "Select categories",
                            allowClear: true,
                            width: '100%'
                        });
                        
                        // Handle Select2 change events
                        $(categoriesSelectRef.current).on('change', function() {
                            const selectedValues = $(this).val() || [];
                            handleCategoriesChange(selectedValues);
                        });
                    }
                    
                    // Initialize Tags Select2
                    if (tagsSelectRef.current && !$(tagsSelectRef.current).hasClass('select2-hidden-accessible')) {
                        $(tagsSelectRef.current).select2({
                            placeholder: "Select tags",
                            allowClear: true,
                            width: '100%'
                        });
                        
                        // Handle Select2 change events
                        $(tagsSelectRef.current).on('change', function() {
                            const selectedValues = $(this).val() || [];
                            handleTagsChange(selectedValues);
                        });
                    }
                }
            };
            
            const destroySelect2 = function() {
                if (window.jQuery && window.jQuery.fn.select2) {
                    const $ = window.jQuery;
                    
                    if (categoriesSelectRef.current && $(categoriesSelectRef.current).hasClass('select2-hidden-accessible')) {
                        $(categoriesSelectRef.current).select2('destroy');
                    }
                    
                    if (tagsSelectRef.current && $(tagsSelectRef.current).hasClass('select2-hidden-accessible')) {
                        $(tagsSelectRef.current).select2('destroy');
                    }
                }
            };
            
            // Initialize Select2 when available options change
            useEffect(function() {
                // Small delay to ensure DOM is ready
                const timer = setTimeout(function() {
                    initializeSelect2();
                }, 100);
                
                return function() {
                    clearTimeout(timer);
                };
            }, [availableCategories, availableTags]);
            
            // Cleanup Select2 on unmount
            useEffect(function() {
                return function() {
                    destroySelect2();
                };
            }, []);

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
                                    el( 'div', {},
                                        el( 'label', { 
                                            style: { 
                                                display: 'block', 
                                                marginBottom: '8px', 
                                                fontSize: '11px', 
                                                fontWeight: '500', 
                                                lineHeight: '1.4', 
                                                textTransform: 'uppercase', 
                                                color: '#1e1e1e' 
                                            } 
                                        }, 'Categories'),
                                        el( 'select', {
                                            ref: categoriesSelectRef,
                                            multiple: true,
                                            style: { width: '100%', minHeight: '36px' },
                                            value: getCategoriesSelectedValues()
                                        }, getCategoriesOptions().map(function(option) {
                                            if (option.label && option.label.startsWith('--- ')) {
                                                // This is a taxonomy header
                                                return el( 'optgroup', {
                                                    key: option.value,
                                                    label: option.label.replace('--- ', '').replace(' ---', '')
                                                });
                                            } else {
                                                // This is a regular option
                                                return el( 'option', {
                                                    key: option.value,
                                                    value: option.value
                                                }, option.label);
                                            }
                                        })),
                                        el( 'p', { 
                                            style: { 
                                                fontSize: '12px', 
                                                fontStyle: 'normal', 
                                                color: '#757575', 
                                                margin: '8px 0 0' 
                                            } 
                                        }, 'Select categories to filter search results. Available after selecting post types.')
                                    )
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
                                    el( 'div', {},
                                        el( 'label', { 
                                            style: { 
                                                display: 'block', 
                                                marginBottom: '8px', 
                                                fontSize: '11px', 
                                                fontWeight: '500', 
                                                lineHeight: '1.4', 
                                                textTransform: 'uppercase', 
                                                color: '#1e1e1e' 
                                            } 
                                        }, 'Tags'),
                                        el( 'select', {
                                            ref: tagsSelectRef,
                                            multiple: true,
                                            style: { width: '100%', minHeight: '36px' },
                                            value: getTagsSelectedValues()
                                        }, getTagsOptions().map(function(option) {
                                            if (option.label && option.label.startsWith('--- ')) {
                                                // This is a taxonomy header
                                                return el( 'optgroup', {
                                                    key: option.value,
                                                    label: option.label.replace('--- ', '').replace(' ---', '')
                                                });
                                            } else {
                                                // This is a regular option
                                                return el( 'option', {
                                                    key: option.value,
                                                    value: option.value
                                                }, option.label);
                                            }
                                        })),
                                        el( 'p', { 
                                            style: { 
                                                fontSize: '12px', 
                                                fontStyle: 'normal', 
                                                color: '#757575', 
                                                margin: '8px 0 0' 
                                            } 
                                        }, 'Select tags to filter search results. Available after selecting post types.')
                                    )
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
