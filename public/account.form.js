var root_accounts_doc = CFG_GLPI.root_doc + '/plugins/accounts';

var auto_decrypt = function (suffix) {
    suffix = suffix || "";
    if (!check_hash()) {
        $("#hidden_password" + suffix).val($("#wrong_key_locale").val());
    } else {
        decrypt_password(root_accounts_doc, suffix);
    }
};

var uncryptpassword = async function (suffix) {
    if (!check_hash()) {
        var value = document.getElementById('wrong_key_locale').value;
        document.getElementById('wrong_key_locale_div').textContent = value;
    } else {
        document.getElementById('wrong_key_locale_div').textContent = '';
        await decrypt_password(root_accounts_doc, suffix);
    }
};

var checkInputIfNewEncryptionKey = function (newValue, currentValue) {
    if (currentValue > 0 && newValue != currentValue) {
        $('#alertfootprint').show();
    }
};
