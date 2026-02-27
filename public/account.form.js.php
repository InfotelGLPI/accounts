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
   }
};

var uncryptpassword = function (suffix) {

    if (!check_hash()) {
        var value = document.getElementById('wrong_key_locale').value;
        document.getElementById('wrong_key_locale_div').innerHTML = value;
    } else {
        document.getElementById('wrong_key_locale_div').innerHTML = '';
        decrypt_password(root_accounts_doc);
    }
};
