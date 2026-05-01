<?php

header('Content-Type: text/javascript');

$root_url = PLUGIN_ACCOUNTS_WEBDIR;
?>

var root_accounts_doc = "<?php echo $root_url; ?>";

// auto decrypt (aes key saved in db)
var auto_decrypt = function (suffix) {

    suffix = suffix || "";
   if (!check_hash()) {
       $("#hidden_password" + suffix).val($("#wrong_key_locale").val());
   } else {
       decrypt_password(root_accounts_doc, suffix);
       decrypt_totp_secret(suffix);
   }
};

var uncryptpassword = async function(suffix) {

    if (!check_hash()) {
        var value = document.getElementById('wrong_key_locale').value;
        document.getElementById('wrong_key_locale_div').innerHTML = value;
    } else {
        document.getElementById('wrong_key_locale_div').innerHTML = '';
        await decrypt_password(root_accounts_doc, suffix);
        decrypt_totp_secret(suffix);
    }
};

var decrypt_totp_secret = function (suffix) {
    suffix = suffix || "";
    var aeskey = $("#aeskey" + suffix).val();
    var encrypted = $("#encrypted_totp_secret" + suffix).val();
    if (!encrypted || !aeskey) return;

    var decrypted = '';
    if (encrypted.startsWith('$v2$')) {
        decrypted = decryptV2(encrypted, aeskey);
    } else if (typeof AESDecryptCtr === 'function') {
        decrypted = AESDecryptCtr(encrypted, SHA256(aeskey), 256);
    } else {
        decrypted = encrypted;
    }

    if ($("#hidden_totp_secret" + suffix).length) {
        $("#hidden_totp_secret" + suffix).val(decrypted);
    }
};

var checkInputIfNewEncryptionKey  = function (newValue, currentValue) {
    if (currentValue > 0 && newValue != currentValue) {
        $('#alertfootprint').show();
    }
};

