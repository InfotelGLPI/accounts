var check_hash = function (suffix) {

    suffix = suffix || "";

    var good_hash        = $("#good_hash" + suffix).val();
    var aeskey           = $("#aeskey" + suffix).val();
    var on_change_hash   = $("#change_good_hash" + suffix).text();

    var select_encryption_key = false;

    if (on_change_hash != '') {
        select_encryption_key = true;
    }

    if (select_encryption_key) {
        return generic_check_hash(on_change_hash, aeskey)
    } else {
        return generic_check_hash(good_hash, aeskey)
    }
};



var generic_check_hash = function (good_hash, aeskey) {

    if (aeskey == '' || aeskey == undefined) {
        return false;
    }

    var hash = SHA256(SHA256(aeskey));

    if (hash != good_hash) {
        return false;
    }
    return true;
};

var decrypt_password = function (root_accounts_doc, suffix) {
    suffix = suffix || "";

    var aeskey = $("#aeskey" + suffix).val();
    var encrypted_password = $("#encrypted_password" + suffix).val();

    var decrypted_password = AESDecryptCtr(
        encrypted_password,
        SHA256(aeskey),
        256
    );

    if ($("#hidden_password" + suffix).length) { //isset ?
        $("#hidden_password" + suffix).val(decrypted_password);
    }

    if (document.location.pathname.indexOf('accounts') > 0) {
        var idcrypt = $('form#account_form input[name=id]').val();
        var url = '../ajax/log_decrypt.php'
    } else {
        var idcrypt = suffix;
        var url = root_accounts_doc + '/ajax/log_decrypt.php';
    }

    $.ajax({
        'url': url,
        'type': 'POST',
        'data': {'idcrypt': idcrypt}
    });

    return decrypted_password;
};

var encrypt_password = function (suffix) {
    suffix                     = suffix || "";
    var aeskey                = $("#aeskey").val();
    // var checkAeskey           = $("#checkaeskey").val();
    var select_encryption_key = false;
    var encrypted_password    = '';

    // if (checkAeskey != '') {
    //     select_encryption_key = true;
    // }

    // if (select_encryption_key) {
    //     encrypted_password = AESEncryptCtr($('#hidden_password' + suffix).val(),
    //         SHA256(checkAeskey),
    //         256);
    // } else {
        encrypted_password = AESEncryptCtr($('#hidden_password' + suffix).val(),
            SHA256(aeskey),
            256);
    // }

    $('#encrypted_password').val(encrypted_password);
    $('#account_form').submit();
};
