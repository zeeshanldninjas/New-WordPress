(function (blocks, blockEditor, element, components) {

    const el = element.createElement;
    const { InspectorControls } = blockEditor;
    const { PanelBody, TextControl, CheckboxControl, Spinner } = components;
    const { useState, useEffect, useRef } = element;
    const apiFetch = wp.apiFetch;

    /* -------------------------
       Helper: Format Optgroup Label
    ------------------------- */
    const formatTaxLabel = (slug) => {
        if (!slug) return '';

        return slug
            .replace(/[-_]/g, ' ')
            .replace(/\bcateorie\b/i, 'Category') // fix typo
            .replace(/\bcategories\b/i, 'Categories')
            .replace(/\btags\b/i, 'Tags')
            .replace(/\b\w/g, l => l.toUpperCase());
    };

    /* -------------------------
       Select2 Wrapper (Optgroup)
    ------------------------- */
    const Select2Control = ({ label, value, groupedOptions, onChange, multiple }) => {
        const selectRef = useRef();

        // Init Select2
        useEffect(() => {
            if (!selectRef.current) return;

            const $el = jQuery(selectRef.current);

            $el.select2({
                width: '100%',
                placeholder: label
            });

            $el.val(value).trigger('change');

            $el.on('change', function () {
                let val = jQuery(this).val();
                if (multiple && !val) val = [];
                onChange(val || []);
            });

            return () => {
                $el.select2('destroy');
            };
        }, []);

        // Sync value
        useEffect(() => {
            if (!selectRef.current) return;
            jQuery(selectRef.current)
                .val(value)
                .trigger('change.select2');
        }, [value]);

        return el('div', {},
            label && el('label', { style: { marginBottom: '6px', display: 'block' } }, label),

            el(
                'select',
                {
                    ref: selectRef,
                    multiple: multiple,
                    style: { width: '100%' }
                },

                Object.entries(groupedOptions).map(([tax, terms]) =>
                    el(
                        'optgroup',
                        {
                            key: tax,
                            label: formatTaxLabel(tax) // ✅ formatted label
                        },

                        terms.map(term =>
                            el('option', {
                                key: tax + '-' + term.id,
                                value: tax + ':' + term.id
                            }, term.name)
                        )
                    )
                )
            )
        );
    };

    blocks.registerBlockType('smart-search-control/search-block', {

        title: 'Smart Search Control',
        icon: 'search',
        category: 'widgets',

        attributes: {
            placeholder: { type: 'string', default: 'Search...' },
            cssId: { type: 'string', default: '' },
            cssClass: { type: 'string', default: '' },
            postTypes: { type: 'array', default: [] },
            categories: { type: 'object', default: {} },
            tags: { type: 'object', default: {} }
        },

        edit: function (props) {

            const { attributes, setAttributes } = props;
            const { placeholder, cssId, cssClass, postTypes, categories, tags } = attributes;

            const safeCategories = categories || {};
            const safeTags = tags || {};
            const safePostTypes = postTypes || [];

            const availablePostTypes =
                window.smarsecoBlockData?.availablePostTypes || [
                    { value: 'post', label: 'Posts' },
                    { value: 'page', label: 'Pages' }
                ];

            const [availableCategories, setAvailableCategories] = useState({});
            const [availableTags, setAvailableTags] = useState({});
            const [taxonomiesLoading, setTaxonomiesLoading] = useState(false);

            /* -------------------------
               Post Types
            ------------------------- */
            const handlePostTypeChange = (value, checked) => {
                const newTypes = [...safePostTypes];

                if (checked && !newTypes.includes(value))
                    newTypes.push(value);

                if (!checked)
                    setAttributes({ postTypes: newTypes.filter(v => v !== value) });
                else
                    setAttributes({ postTypes: newTypes });
            };

            const handleSelectAll = (checked) => {
                setAttributes({
                    postTypes: checked
                        ? availablePostTypes.map(p => p.value)
                        : []
                });
            };

            const allSelected =
                availablePostTypes.length &&
                safePostTypes.length === availablePostTypes.length;

            /* -------------------------
               Value Helpers
            ------------------------- */
            const getSelectedValues = (obj) => {
                const vals = [];
                Object.entries(obj).forEach(([tax, ids]) => {
                    (ids || []).forEach(id =>
                        vals.push(tax + ':' + id)
                    );
                });
                return vals;
            };

            const parseValues = (values) => {
                const out = {};
                (values || []).forEach(v => {
                    const [tax, id] = v.split(':');
                    if (!out[tax]) out[tax] = [];
                    out[tax].push(parseInt(id));
                });
                return out;
            };

            /* -------------------------
               Load Taxonomies
            ------------------------- */
            useEffect(() => {

                if (!safePostTypes.length) {
                    setAvailableCategories({});
                    setAvailableTags({});
                    return;
                }

                setTaxonomiesLoading(true);

                Promise.all(
                    safePostTypes.map(pt =>
                        apiFetch({
                            path: '/smart-search-control/v1/taxonomies/' + pt
                        })
                    )
                )
                .then(responses => {

                    const cats = {};
                    const tgs = {};

                    responses.forEach(res => {

                        Object.entries(res.categories || {}).forEach(([k,v]) => {
                            cats[k] = (cats[k] || []).concat(v);
                        });

                        Object.entries(res.tags || {}).forEach(([k,v]) => {
                            tgs[k] = (tgs[k] || []).concat(v);
                        });

                    });

                    setAvailableCategories(cats);
                    setAvailableTags(tgs);
                })
                .finally(() => setTaxonomiesLoading(false));

            }, [safePostTypes]);

            /* -------------------------
               UI
            ------------------------- */
            const postTypeElements = [
                el(CheckboxControl,{
                    label:'Select All',
                    checked:allSelected,
                    onChange:handleSelectAll
                }),
                ...availablePostTypes.map(pt =>
                    el(CheckboxControl,{
                        key:pt.value,
                        label:pt.label,
                        checked:safePostTypes.includes(pt.value),
                        onChange:(val)=>handlePostTypeChange(pt.value,val)
                    })
                )
            ];

            const blockPreview = el(
                'div',
                { className:'smarseco-default-search-bar-container' },
                el('input',{
                    type:'text',
                    placeholder:placeholder,
                    disabled:true
                })
            );

            /* -------------------------
               Render
            ------------------------- */
            return el('div',{},
                el(InspectorControls,{},

                    el(PanelBody,{title:'Search Settings'},
                        el(TextControl,{
                            label:'Placeholder Text',
                            value:placeholder,
                            onChange:v=>setAttributes({placeholder:v})
                        })
                    ),

                    el(PanelBody,{title:'Advanced Settings'},
                        el(TextControl,{
                            label:'CSS ID',
                            value:cssId,
                            onChange:v=>setAttributes({cssId:v})
                        }),
                        el(TextControl,{
                            label:'CSS Class',
                            value:cssClass,
                            onChange:v=>setAttributes({cssClass:v})
                        })
                    ),

                    el(PanelBody,{title:'Post Types'},postTypeElements),

                    el(PanelBody,{title:'Categories'},
                        safePostTypes.length===0
                            ? el('p',{},'Select post types first.')
                            : taxonomiesLoading
                                ? el(Spinner)
                                : el(Select2Control,{
                                    label:'Categories',
                                    multiple:true,
                                    value:getSelectedValues(safeCategories),
                                    groupedOptions:availableCategories,
                                    onChange:(vals)=>setAttributes({
                                        categories:parseValues(vals)
                                    })
                                })
                    ),

                    el(PanelBody,{title:'Tags'},
                        safePostTypes.length===0
                            ? el('p',{},'Select post types first.')
                            : taxonomiesLoading
                                ? el(Spinner)
                                : el(Select2Control,{
                                    label:'Tags',
                                    multiple:true,
                                    value:getSelectedValues(safeTags),
                                    groupedOptions:availableTags,
                                    onChange:(vals)=>setAttributes({
                                        tags:parseValues(vals)
                                    })
                                })
                    )
                ),

                blockPreview
            );
        },

        save: () => null
    });

})(
    window.wp.blocks,
    window.wp.blockEditor,
    window.wp.element,
    window.wp.components
);