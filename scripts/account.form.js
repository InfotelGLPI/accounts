$(document).ready(function() {

   // decrypt button (user manually input key)
   $(document).on("click", "#decrypte_link", function(event) {
      event.preventDefault();

      if (!check_hash()) {
         alert($("#wrong_key_locale").val());
      } else {
         decrypt_password();
      }
   });
});

// auto decrypt (aes key saved in db)
var auto_decrypt = function(sufix) {
   sufix = sufix || "";
   if (!check_hash()) {
     $("#hidden_password"+sufix).val($("#wrong_key_locale").val());
   } else {
      decrypt_password(sufix);
   }
}  
