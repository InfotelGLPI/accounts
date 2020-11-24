var check_hash = function () {

    var good_hash        = $("#good_hash").val();
    var aeskey           = $("#aeskey").val();
    var on_change_hash   = $("#change_good_hash").text();
    var checkAeskey      = $("#checkaeskey").val();

    var select_encryption_key = false;

    if (checkAeskey != '') {
        select_encryption_key = true;
    }

    if (select_encryption_key) {
        return generic_check_hash(on_change_hash, checkAeskey)
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


var decrypt_password = function (sufix) {
    sufix = sufix || "";

    var aeskey = $("#aeskey").val();
    var decrypted_password = AESDecryptCtr($("#encrypted_password" + sufix).val(),
        SHA256(aeskey),
        256);
    if ($("#hidden_password" + sufix).length) { //isset ?
        $("#hidden_password" + sufix).val(decrypted_password);
    }

    if (document.location.pathname.indexOf('accounts') > 0) {
        var idcrypt = $('form#account_form input[name=id]').val();
        var url = '../ajax/log_decrypt.php'
    } else {
        var idcrypt = sufix;
        var url = '../plugins/accounts/ajax/log_decrypt.php';
    }

    $.ajax({
        'url': url,
        'type': 'POST',
        'data': {'idcrypt': idcrypt}
    });

    return decrypted_password;
};

var encrypt_password = function (sufix) {
    sufix                     = sufix || "";
    var aeskey                = $("#aeskey").val();
    var checkAeskey           = $("#checkaeskey").val();
    var select_encryption_key = false;
    var encrypted_password    = '';

    if (checkAeskey != '') {
        select_encryption_key = true;
    }

    console.log(select_encryption_key)


    if (select_encryption_key) {
        encrypted_password = AESEncryptCtr($('#hidden_password' + sufix).val(),
            SHA256(checkAeskey),
            256);
    } else {
        encrypted_password = AESEncryptCtr($('#hidden_password' + sufix).val(),
            SHA256(aeskey),
            256);
    }

    $('#encrypted_password').val(encrypted_password);
    $('#account_form').submit();
};
