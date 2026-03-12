var check_hash = function (suffix) {

    suffix = suffix || "";

    var good_hash = $("#good_hash" + suffix).val();
    var aeskey = $("#aeskey" + suffix).val();
    var on_change_hash = $("#change_good_hash" + suffix).text();

    var select_encryption_key = false;

    if (on_change_hash != '') {
        select_encryption_key = true;
    }

    if (select_encryption_key) {
        return generic_check_hash(on_change_hash, aeskey);
    } else {
        return generic_check_hash(good_hash, aeskey);
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

function decryptV2(ciphertext, fingerprint) {
    var parts = ciphertext.replace(/^\$/, '').split('$');
    if (parts.length < 3) return '';

    var iv = atob(parts[1]);
    var ct = atob(parts[2]);

    // SHA256 comme en PHP
    var key = CryptoJS.enc.Hex.parse(CryptoJS.SHA256(fingerprint).toString());

    var decrypted = CryptoJS.AES.decrypt(
        { ciphertext: CryptoJS.enc.Latin1.parse(ct) },
        key,
        {
            iv: CryptoJS.enc.Latin1.parse(iv),
            mode: CryptoJS.mode.CTR,
            padding: CryptoJS.pad.NoPadding
        }
    );

    return decrypted.toString(CryptoJS.enc.Utf8);
}


function decrypt_password(root_accounts_doc, suffix) {
    suffix = suffix || "";

    var aeskey = $("#aeskey" + suffix).val();
    var encrypted_password = $("#encrypted_password" + suffix).val();
    var accounts_id = $("#accounts_id" + suffix).val();
    var items_id = $("#items_id" + suffix).val();
    var itemtype = $("#itemtype" + suffix).val();

    var decrypted_password = '';

    if (encrypted_password.startsWith('$v2$')) {
        decrypted_password = decryptV2(encrypted_password, aeskey);
    } else {
        decrypted_password = AESDecryptCtr(
            encrypted_password,
            SHA256(aeskey),
            256
        );
    }

    if ($("#hidden_password" + suffix).length) {
        $("#hidden_password" + suffix).val(decrypted_password);
    }

    var url = root_accounts_doc + '/ajax/log_decrypt.php';

    var idcrypt, from;
    if (document.location.pathname.indexOf('accounts') > 0) {
        idcrypt = $('form#account_form input[name=id]').val();
        from = 'account';
    } else {
        idcrypt = accounts_id;
        from = 'item';
    }

    $.ajax({
        url: url,
        type: 'POST',
        data: { idcrypt, from, items_id, itemtype }
    });

    return decrypted_password;
}

var encrypt_password = function (suffix) {
    suffix = suffix || "";
    var aeskey = $("#aeskey").val();
    // var checkAeskey           = $("#checkaeskey").val();
    var select_encryption_key = false;
    var encrypted_password = '';

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
