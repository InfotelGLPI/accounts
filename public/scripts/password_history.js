/**
 * Accounts password history — GPL-3.0-or-later.
 * Keys and plaintext stay in the browser; only entry IDs are sent to the audit log.
 */

function accountsDecryptHistoryEntry(ciphertext, verifier, key) {
    try {
        if (!generic_check_hash(verifier, key)) {
            return null;
        }
        return decrypt_cryptogram(ciphertext, key) || null;
    } catch (error) {
        return null;
    }
}

function accountsClearHistoryRow(row) {
    row.find('.history-password').val('').attr('type', 'password');
    row.find('[data-history-action="reveal"], [data-history-action="copy"]').prop('disabled', true);
    row.find('.history-error').text('');
}

$(document).on('input', '.accounts-password-history .history-key', function () {
    accountsClearHistoryRow($(this).closest('[data-history-id]'));
});

$(document).on('input', '#aeskey', function () {
    $('.accounts-password-history [data-history-id]').each(function () {
        accountsClearHistoryRow($(this));
    });
});

$(document).on('click', '.accounts-password-history .history-close', function () {
    var history = $(this).closest('.accounts-password-history')[0];
    history.open = false;
    history.querySelector('.history-panel-summary').focus();
});

$(document).on('click', function (event) {
    $('.accounts-password-history[open]').each(function () {
        if (!this.contains(event.target)) {
            this.open = false;
        }
    });
});

$(document).on('keydown', function (event) {
    if (event.key === 'Escape') {
        $('.accounts-password-history[open]').each(function () {
            this.open = false;
            this.querySelector('.history-panel-summary').focus();
        });
    }
});

$(document).on('click', '.accounts-password-history [data-history-action]', function () {
    var row = $(this).closest('[data-history-id]');
    var history = row.closest('.accounts-password-history');
    var output = row.find('.history-password');
    var action = this.dataset.historyAction;

    if (action === 'reveal') {
        output.attr('type', output.attr('type') === 'password' ? 'text' : 'password');
        return;
    }
    if (action === 'copy') {
        copyDisclosablePasswordFieldToClipboard(output.attr('id'));
        return;
    }

    accountsClearHistoryRow(row);
    var key = row.find('.history-key').val() || $('#aeskey').val();
    var verifier = row.attr('data-verifier');
    var validKey = false;
    try {
        validKey = generic_check_hash(verifier, key);
    } catch (error) {
        // A damaged verifier is treated like an unusable key.
    }
    if (!validKey) {
        row.find('.history-error').text(history.attr('data-wrong-key'));
        return;
    }
    var plaintext = accountsDecryptHistoryEntry(row.attr('data-ciphertext'), verifier, key);
    if (plaintext === null) {
        row.find('.history-error').text(history.attr('data-decryption-failed'));
        return;
    }
    output.val(plaintext);
    row.find('[data-history-action="reveal"], [data-history-action="copy"]').prop('disabled', false);
    $.ajax({
        url: root_accounts_doc + '/ajax/log_decrypt.php',
        type: 'POST',
        data: {idcrypt: history.attr('data-account-id'), from: 'history', history_id: row.attr('data-history-id')}
    });
});

// Native toggle does not bubble: install a capturing listener for dynamically loaded forms.
document.addEventListener('toggle', function (event) {
    if (event.target.open) {
        return;
    }
    if (event.target.matches('.accounts-password-history')) {
        $(event.target).find('[data-history-id]').each(function () {
            accountsClearHistoryRow($(this));
            $(this).find('.history-key').val('');
        });
    } else if (event.target.matches('.accounts-password-history-entry')) {
        accountsClearHistoryRow($(event.target));
        $(event.target).find('.history-key').val('');
    }
}, true);
