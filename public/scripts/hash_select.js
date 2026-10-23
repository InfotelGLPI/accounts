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


/* global getAjaxCsrfToken */

/**
 * "Display report" button of templates/hash_select_accounts.html.twig: posts the key typed by the
 * user to ajax/viewaccountslist.php and shows the returned list. Decryption then happens in
 * public/scripts/report.js, the key never leaves the browser in clear anywhere else.
 */
function injectHtml(container, html) {
    container.innerHTML = html;
    // innerHTML does not run scripts: re-create them so the core widgets (select2, datatable) initialize.
    container.querySelectorAll('script').forEach((old) => {
        const script = document.createElement('script');
        [...old.attributes].forEach((attr) => script.setAttribute(attr.name, attr.value));
        script.textContent = old.textContent;
        old.replaceWith(script);
    });
}

async function showAccountsList(button) {
    const key = document.getElementById(button.getAttribute('data-accounts-key-input'))?.value ?? '';
    if (key === '') {
        window.alert(button.getAttribute('data-accounts-empty-message'));
        return;
    }

    const body = new FormData();
    body.append('id', button.getAttribute('data-accounts-hash-id'));
    body.append('key', key);

    const response = await fetch(button.getAttribute('data-accounts-url'), {
        method: 'POST',
        body: body,
        headers: {
            'X-Glpi-Csrf-Token': getAjaxCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const container = document.getElementById(button.getAttribute('data-accounts-target'));
    if (response.ok && container !== null) {
        injectHtml(container, await response.text());
    }
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-accounts-show-list]');
    if (button !== null) {
        showAccountsList(button);
    }
});
