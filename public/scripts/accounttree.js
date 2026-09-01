/**
 * -------------------------------------------------------------------------
 * accounts plugin for GLPI
 * Copyright (C) 2015-2026 by the accounts Development Team.
 *
 * https://github.com/InfotelGLPI/accounts
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of accounts.
 *
 * accounts is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * accounts is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with accounts. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

/* global $ */

/**
 * Account type tree of the account list.
 *
 * Served inside an iframe modal, in a page loaded outside of the usual GLPI header: the
 * plugin scripts registered through the ADD_JAVASCRIPT hook are not present there, so
 * account_tree.html.twig pulls this module explicitly and the tree reads its parameters
 * from the data attributes of its container rather than from an inline script block.
 *
 * Built on the fancytree widget bundled in the core base.js (same as Glpi\Features\TreeBrowse),
 * so nothing extra is downloaded. Account types are the root level; expanding one lazy-loads
 * its accounts. fancytree is exposed as a jQuery widget only, hence the jQuery calls below --
 * there is no other way to drive the bundled build.
 */

/*
 * Tabler glyph map for fancytree. The `preset` option is mandatory for the glyph extension,
 * but every one of its entries is overridden here: the core ships Tabler icons, not Font
 * Awesome.
 */
const TABLER_GLYPHS = {
    _addClass: 'ti',
    checkbox: 'ti-square',
    checkboxSelected: 'ti-square-check',
    checkboxUnknown: 'ti-square-minus fancytree-helper-indeterminate-cb',
    radio: 'ti-circle',
    radioSelected: 'ti-circle-dot',
    radioUnknown: 'ti-circle-dot',
    dragHelper: 'ti-arrow-right',
    dropMarker: 'ti-arrow-narrow-right',
    error: 'ti-alert-triangle',
    expanderClosed: 'ti-chevron-right',
    expanderLazy: 'ti-chevron-right',
    expanderOpen: 'ti-chevron-down',
    loading: 'ti-loader-2 fancytree-helper-spin',
    nodata: 'ti-mood-empty',
    noExpander: '',
    doc: 'ti-file',
    docOpen: 'ti-file',
    folder: 'ti-folder',
    folderOpen: 'ti-folder-open',
};

/**
 * Open the page a node points to, if it carries one.
 *
 * @param {object} node fancytree node
 */
const openNode = (node) => {
    const url = node.data ? node.data.url : null;

    if (url) {
        window.open(url);
    }
};

/**
 * Wire the filter input attached to a tree, if the template rendered one.
 *
 * @param {HTMLElement} container tree container
 */
const bindFilter = (container) => {
    const search = document.getElementById(container.dataset.searchId);

    if (!search) {
        return;
    }

    search.addEventListener('keyup', () => {
        const tree = $.ui.fancytree.getTree(container);

        if (search.value.length === 0) {
            tree.clearFilter();
        } else {
            tree.filterNodes(search.value);
        }
    });
};

/**
 * Build the tree inside a container.
 *
 * @param {HTMLElement} container tree container carrying the data- parameters
 */
const initTree = (container) => {
    const typesUrl = `${container.dataset.rootDoc}/ajax/accounttreetypes.php`;

    $(container).fancytree({
        extensions: ['filter', 'glyph'],
        autoScroll: true,

        // Account types carry no URL: a click on one unfolds it instead of leaving the tree.
        clickFolderMode: 3,

        glyph: {
            preset: 'awesome4',
            map: TABLER_GLYPHS,
        },

        // Root level; each folder node then lazy-loads its own children.
        source: {
            url: typesUrl,
            data: {node: -1},
            cache: false,
        },
        lazyLoad: (event, data) => {
            data.result = {
                url: typesUrl,
                data: {node: data.node.key},
                cache: false,
            };
        },

        filter: {
            mode: 'hide',
            autoExpand: true,
            nodata: container.dataset.noDataText,
        },

        // The target URL travels in the node payload, so the server never emits an event
        // handler of its own.
        activate: (event, data) => openNode(data.node),
    });

    bindFilter(container);
};

// Modules are deferred, so the container is already parsed when this runs.
document.querySelectorAll('[data-plugin-accounts-tree]').forEach(initTree);
