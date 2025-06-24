<?php
use Glpi\Event;
include('../../../inc/includes.php');
header('Content-Type: text/javascript');

$root_url = PLUGIN_ACCOUNTS_WEBDIR;
?>

var root_accounts_doc = "<?php echo $root_url; ?>";

$(document).ready(function () {

    // decrypt button (user manually input key)
    $(document).on("click", "#decrypte_link", function (event) {
        event.preventDefault();

          if (!check_hash()) {
              alert($("#wrong_key_locale").val());
          } else {
              decrypt_password(root_accounts_doc);

              var input = $('#hidden_password');
              if (input.attr("type") == "password") {
                  input.attr("type", "text");
              } else {
                  input.attr("type", "password");
              }

              // find the good element
              var target = $('.account_to_clipboard_wrapper');
              //if (target.attr('class') === 'account_to_clipboard_wrapper') {
                  target = target.find('*');
              //}

              // copy text
              target.select();
              var succeed;
              try {
                  succeed = document.execCommand("copy");
              } catch (e) {
                  succeed = false;
              }
              target.blur();

              // indicate success
              if (succeed) {
                  $('.account_to_clipboard_wrapper.copied').removeClass('copied');
                  target.parent('.account_to_clipboard_wrapper').addClass('copied');
              } else {
                  target.parent('.account_to_clipboard_wrapper').addClass('copyfail');
              }

              var input = $('#hidden_password');
              if (input.attr("type") == "password") {
                  input.attr("type", "text");
              } else {
                  input.attr("type", "password");
              }
          }
        });
    });

// auto decrypt (aes key saved in db)
var auto_decrypt = function (sufix) {

    sufix = sufix || "";
   if (!check_hash()) {
       $("#hidden_password" + sufix).val($("#wrong_key_locale").val());
   } else {
       decrypt_password(root_accounts_doc, sufix);
   }
};

