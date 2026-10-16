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

/* global Wunderbaum */

/**
 * Account type tree of the account list.
 *
 * Served inside an iframe modal, in a page loaded outside of the usual GLPI header: the
 * plugin scripts registered through the ADD_JAVASCRIPT hook are not present there, so
 * account_tree.html.twig pulls this module explicitly and the tree reads its parameters
 * from the data attributes of its container rather than from an inline script block.
 *
 * Built on the Wunderbaum library bundled in the core base.js (same as
 * Glpi\Features\TreeBrowse), so nothing extra is downloaded. Account types are the root
 * level; expanding one lazy-loads its accounts.
 */

/*
 * Tabler glyph map: the core ships Tabler icons, not the Bootstrap icons Wunderbaum
 * defaults to. Nodes that carry their own `icon` class (accounts, "Show all") keep it.
 */
const TABLER_ICONS = {
    error: 'ti ti-alert-triangle',
    loading: 'ti ti-loader-2 wb-spin',
    noData: 'ti ti-mood-empty',
    expanderExpanded: 'ti ti-chevron-down',
    expanderCollapsed: 'ti ti-chevron-right',
    expanderLazy: 'ti ti-chevron-right',
    checkChecked: 'ti ti-square-check',
    checkUnchecked: 'ti ti-square',
    checkUnknown: 'ti ti-square-minus',
    radioChecked: 'ti ti-circle-dot',
    radioUnchecked: 'ti ti-circle',
    radioUnknown: 'ti ti-circle-dot',
    folder: 'ti ti-folder',
    folderOpen: 'ti ti-folder-open',
    folderLazy: 'ti ti-folder',
    doc: 'ti ti-file',
};

/**
 * Open the page a node points to, if it carries one.
 *
 * @param {object} node Wunderbaum node
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
 * @param {object}      tree      Wunderbaum tree
 * @param {HTMLElement} container tree container
 */
const bindFilter = (tree, container) => {
    const search = document.getElementById(container.dataset.searchId);

    if (!search) {
        return;
    }

    search.addEventListener('input', () => {
        const query = search.value.trim();

        if (query.length === 0) {
            tree.clearFilter();
        } else {
            tree.filterNodes(query, {
                mode: 'hide',
                autoExpand: true,
                noData: container.dataset.noDataText,
            });
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

    const tree = new Wunderbaum.Wunderbaum({
        element: container,
        iconMap: TABLER_ICONS,

        // Node titles are plain database values (account type and account names).
        // Wunderbaum writes them with textContent (and escapes them before adding the
        // filter <mark> tags), so a stored payload is never rendered as markup. No
        // `render` callback is set on purpose: TreeBrowse uses one to inject HTML
        // titles, which these nodes must not get.

        // Root level; each account type then lazy-loads its own accounts.
        source: {url: typesUrl, params: {node: '-1'}},
        lazyLoad: (e) => ({url: typesUrl, params: {node: e.node.key}}),

        // Account types carry no URL: a click on one unfolds it instead of leaving the
        // tree. The target URL travels in the node payload, so the server never emits an
        // event handler of its own. Bound to click / Enter rather than `activate`, which
        // also fires while browsing the tree with the arrow keys.
        click: (e) => {
            if (!e.node || e.info.region === 'expander') {
                return;
            }
            if (e.node.isExpandable()) {
                e.node.setExpanded(!e.node.isExpanded());
            } else {
                openNode(e.node);
            }
        },
        keydown: (e) => {
            if (e.eventName === 'Enter' && e.node && !e.node.isExpandable()) {
                openNode(e.node);
                return false;
            }
        },
    });

    bindFilter(tree, container);
};

// Modules are deferred, so the container is already parsed when this runs.
document.querySelectorAll('[data-plugin-accounts-tree]').forEach(initTree);
