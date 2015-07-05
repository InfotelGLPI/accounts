var check_hash = function() {
   var good_hash = $("#good_hash").val();
   var aeskey = $("#aeskey").val();
   
   if (aeskey == '' || aeskey == undefined) {
      return false;
   }

   var hash = SHA256(SHA256(aeskey));

   if (hash != good_hash) {
      return false;
   }
   return true;
}

var decrypt_password = function(sufix) {
   sufix = sufix || "";
   var aeskey = $("#aeskey").val();
   var decrypted_password = AESDecryptCtr($("#encrypted_password"+sufix).val(), 
                                             SHA256(aeskey), 
                                             256);
   if ($("#hidden_password"+sufix).length) { //isset ?
      $("#hidden_password"+sufix).val(decrypted_password);
   }

   if (document.location.pathname.indexOf('accounts') > 0) {
      var idcrypt = $('form#account_form input[name=id]').val();
      var url     = '../ajax/log_decrypt.php'
   } else {
      var idcrypt = sufix;
      var url     = '../plugins/accounts/ajax/log_decrypt.php';
   }

   $.ajax({
      'url' : url,
      'type': 'POST',
      'data': {'idcrypt': idcrypt},
   });
   

   return decrypted_password;
}

var encrypt_password = function(sufix) {
   sufix = sufix || "";
   var aeskey = $("#aeskey").val();
   var encrypted_password = AESEncryptCtr($('#hidden_password'+sufix).val(), 
                                          SHA256(aeskey), 
                                          256);
   $('#encrypted_password').val(encrypted_password);
   $('#account_form').submit();
}