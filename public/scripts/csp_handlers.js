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

/* global uncryptpassword, showDisclosablePasswordField, hideDisclosablePasswordField,
   copyDisclosablePasswordFieldToClipboard, checkAsCheckboxes, reloadTab */

// Delegated listeners replacing the inline event handlers that the Content Security Policy of
// GLPI blocks. Bound once, on the document: they also cover the content loaded later over AJAX
// (tabs, modals). No-op on the pages without the attributes below.
//
// - data-accounts-uncrypt-password: decrypts the password of the account form
//   (uncryptpassword(), public/scripts/account.form.js).
// - data-accounts-disclose="<field id>": shows the password field while the button is pressed.
// - data-accounts-copy="<field id>": copies the password field to the clipboard.
// - data-accounts-check-all="<container id>": "check all" box of the massive actions.
// - data-accounts-reload-tab="<query string>": sorting links of templates/datatable.html.twig.

function closestWith(event, attribute) {
    return event.target instanceof Element ? event.target.closest('[' + attribute + ']') : null;
}

document.addEventListener('click', (event) => {
    if (closestWith(event, 'data-accounts-uncrypt-password') !== null) {
        uncryptpassword();
        return;
    }

    const copy = closestWith(event, 'data-accounts-copy');
    if (copy !== null) {
        copyDisclosablePasswordFieldToClipboard(copy.dataset.accountsCopy);
        return;
    }

    const check_all = closestWith(event, 'data-accounts-check-all');
    if (check_all !== null) {
        checkAsCheckboxes(check_all, check_all.dataset.accountsCheckAll, '.massive_action_checkbox');
        return;
    }

    const sort = closestWith(event, 'data-accounts-reload-tab');
    if (sort !== null) {
        event.preventDefault();
        reloadTab(sort.dataset.accountsReloadTab);
    }
});

document.addEventListener('mousedown', (event) => {
    const button = closestWith(event, 'data-accounts-disclose');
    if (button !== null) {
        showDisclosablePasswordField(button.dataset.accountsDisclose);
    }
});

// Released or left: hidden again (the former onmouseup and onmouseout)
['mouseup', 'mouseout'].forEach((type) => {
    document.addEventListener(type, (event) => {
        const button = closestWith(event, 'data-accounts-disclose');
        if (button !== null) {
            hideDisclosablePasswordField(button.dataset.accountsDisclose);
        }
    });
});
