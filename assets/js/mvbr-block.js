/**
 * Gutenberg block: midvash-bible-references/verse
 *
 * Registers the block editor UI. Rendering is handled server-side (PHP),
 * so save() always returns null. The editor shows a styled preview card.
 *
 * No build step — uses WP global APIs (wp.blocks, wp.element, etc.).
 */
(function (wp) {
    'use strict';

    var el                = wp.element.createElement;
    var __                = wp.i18n.__;
    var registerBlockType = wp.blocks.registerBlockType;
    var TextControl       = wp.components.TextControl;
    var SelectControl     = wp.components.SelectControl;
    var ToggleControl     = wp.components.ToggleControl;
    var PanelBody         = wp.components.PanelBody;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps     = wp.blockEditor.useBlockProps;

    var LOCALES = [
        { value: '',      label: '— ' + __('Plugin setting', 'midvash-bible-references') + ' —' },
        { value: 'pt-br', label: '🇧🇷 Português (Brasil)' },
        { value: 'en',    label: '🇺🇸 English' },
        { value: 'es',    label: '🇪🇸 Español' },
        { value: 'fr',    label: '🇫🇷 Français' },
        { value: 'de',    label: '🇩🇪 Deutsch' },
        { value: 'it',    label: '🇮🇹 Italiano' },
        { value: 'ru',    label: '🇷🇺 Русский' },
        { value: 'ko',    label: '🇰🇷 한국어' },
        { value: 'zh',    label: '🇨🇳 中文' },
    ];

    registerBlockType('midvash-bible-references/verse', {
        title:       __('Bible Verse', 'midvash-bible-references') + ' — Midvash',
        description: __('Display a specific Bible verse with styling and a link to Midvash.', 'midvash-bible-references'),
        category:    'text',
        icon:        'book-alt',
        keywords:    ['bible', 'verse', 'biblia', 'versículo', 'midvash'],

        attributes: {
            reference:      { type: 'string',  default: '' },
            version:        { type: 'string',  default: '' },
            locale:         { type: 'string',  default: '' },
            show_reference: { type: 'boolean', default: true },
            link_verse:     { type: 'boolean', default: true },
        },

        edit: function (props) {
            var attributes   = props.attributes;
            var setAttributes = props.setAttributes;
            var blockProps   = useBlockProps({ className: 'mvbr-verse-editor' });

            var inspector = el(
                InspectorControls,
                { key: 'inspector' },
                el(
                    PanelBody,
                    { title: __('Verse Settings', 'midvash-bible-references'), initialOpen: true },
                    el(TextControl, {
                        label:       __('Bible Reference', 'midvash-bible-references'),
                        help:        __('e.g. John 3:16 · João 3:16 · 요한복음 3:16', 'midvash-bible-references'),
                        value:       attributes.reference,
                        placeholder: 'John 3:16',
                        onChange:    function (v) { setAttributes({ reference: v }); },
                    }),
                    el(TextControl, {
                        label:       __('Version', 'midvash-bible-references'),
                        help:        __('Leave empty to use the plugin default.', 'midvash-bible-references'),
                        value:       attributes.version,
                        placeholder: 'onbv, kjv, lsg…',
                        onChange:    function (v) { setAttributes({ version: v }); },
                    }),
                    el(SelectControl, {
                        label:    __('Language override', 'midvash-bible-references'),
                        value:    attributes.locale,
                        options:  LOCALES,
                        onChange: function (v) { setAttributes({ locale: v }); },
                    }),
                    el(ToggleControl, {
                        label:    __('Show reference (e.g. John 3:16)', 'midvash-bible-references'),
                        checked:  attributes.show_reference,
                        onChange: function (v) { setAttributes({ show_reference: v }); },
                    }),
                    el(ToggleControl, {
                        label:    __('Link reference to Midvash', 'midvash-bible-references'),
                        checked:  attributes.link_verse,
                        onChange: function (v) { setAttributes({ link_verse: v }); },
                    })
                )
            );

            var preview;
            if (attributes.reference) {
                preview = el(
                    'div',
                    { className: 'mvbr-verse-editor__preview' },
                    el('span', { className: 'mvbr-verse-editor__icon' }, '📖'),
                    el('strong', { className: 'mvbr-verse-editor__ref' }, attributes.reference),
                    attributes.version
                        ? el('span', { className: 'mvbr-verse-editor__version' }, attributes.version.toUpperCase())
                        : null,
                    el('em', { className: 'mvbr-verse-editor__note' },
                        __('Verse rendered on the front end via Midvash API.', 'midvash-bible-references')
                    )
                );
            } else {
                preview = el(
                    'div',
                    { className: 'mvbr-verse-editor__empty' },
                    el('span', { className: 'mvbr-verse-editor__icon' }, '📖'),
                    __('Enter a Bible reference in the block settings →', 'midvash-bible-references')
                );
            }

            return [
                inspector,
                el('div', Object.assign({}, blockProps, { key: 'content' }), preview),
            ];
        },

        save: function () {
            // Server-side render — nothing stored in post_content.
            return null;
        },
    });
})(window.wp);
