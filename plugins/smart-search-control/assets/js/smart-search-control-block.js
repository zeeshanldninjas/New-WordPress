( function ( blocks, blockEditor, element, components ) {

    const el = element.createElement;
    const { InspectorControls } = blockEditor;
    const { PanelBody, TextControl, CheckboxControl } = components;

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
            }
        },

        edit: function ( props ) {
            const { attributes, setAttributes } = props;
            const { placeholder, cssId, cssClass, postTypes } = attributes;
            
            // Get dynamic post types from PHP (via wp_localize_script)
            const availablePostTypes = window.smarsecoBlockData && window.smarsecoBlockData.availablePostTypes 
                ? window.smarsecoBlockData.availablePostTypes 
                : [
                    { value: 'post', label: 'Posts' },
                    { value: 'page', label: 'Pages' }
                ];
            const isLoading = false;

            // Handle post type checkbox change
            const handlePostTypeChange = function(value, checked) {
                const newPostTypes = postTypes.slice();
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

            const allSelected = availablePostTypes.length > 0 && postTypes.length === availablePostTypes.length;

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
                            checked: postTypes.indexOf(postType.value) !== -1,
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
