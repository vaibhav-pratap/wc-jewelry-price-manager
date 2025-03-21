/**
 * WooCommerce Jewelry Price Manager - Admin JavaScript
 * Integrates jewelry settings into the Gutenberg product editor.
 */
(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { createElement, useEffect } = wp.element;
    const { PanelBody, CheckboxControl, TextControl, SelectControl } = wp.components;
    const { withSelect, withDispatch } = wp.data;
    const { compose } = wp.compose;

    // Register a custom panel in the WooCommerce product editor
    const JewelrySettingsPanel = compose(
        withSelect((select) => {
            const { getEditedPostAttribute, getPostType } = select('core/editor');
            return {
                meta: getEditedPostAttribute('meta') || {},
                postType: getPostType(),
            };
        }),
        withDispatch((dispatch) => {
            const { editPost } = dispatch('core/editor');
            return {
                updateMeta: (meta) => editPost({ meta }),
            };
        })
    )((props) => {
        const { meta, postType, updateMeta } = props;

        // Only show for products
        if (postType !== 'product') return null;

        // Fetch materials via REST API (assumes endpoint is set up)
        useEffect(() => {
            if (!window.wc_jpm_materials) {
                fetch(wc_jpm_vars.rest_url + 'wc-jpm/v1/materials', {
                    headers: { 'X-WP-Nonce': wc_jpm_vars.nonce }
                })
                    .then(response => response.json())
                    .then(data => window.wc_jpm_materials = data)
                    .catch(error => console.error('Error fetching materials:', error));
            }
        }, []);

        const materials = window.wc_jpm_materials || [];

        return createElement(
            PanelBody,
            { title: 'Jewelry Settings', initialOpen: false },
            [
                createElement(CheckboxControl, {
                    label: 'Is Jewelry Product',
                    checked: meta._is_jewelry === 'yes',
                    onChange: (value) => updateMeta({ ...meta, _is_jewelry: value ? 'yes' : 'no' }),
                }),
                ...materials.map(material => [
                    createElement(TextControl, {
                        label: `${material.name.charAt(0).toUpperCase() + material.name.slice(1)} Weight (${material.unit})`,
                        type: 'number',
                        step: '0.01',
                        min: '0',
                        value: meta[`_material_${material.id}_weight`] || '',
                        onChange: (value) => updateMeta({ ...meta, [`_material_${material.id}_weight`]: value }),
                    }),
                    createElement(SelectControl, {
                        label: `${material.name.charAt(0).toUpperCase() + material.name.slice(1)} Purity`,
                        options: [
                            { label: 'Select Purity', value: '' },
                            ...Object.entries(material.purity_options).map(([key, value]) => ({ label: value, value: key }))
                        ],
                        value: meta[`_material_${material.id}_purity`] || '',
                        onChange: (value) => updateMeta({ ...meta, [`_material_${material.id}_purity`]: value }),
                    })
                ])
            ]
        );
    });

    // Register the block if needed (optional, here for future expansion)
    registerBlockType('wc-jpm/jewelry-settings', {
        title: 'Jewelry Settings',
        icon: 'hammer',
        category: 'woocommerce',
        edit: () => createElement(JewelrySettingsPanel),
        save: () => null, // No frontend output, just meta
    });

})(window.wp);