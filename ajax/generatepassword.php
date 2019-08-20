<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 accounts plugin for GLPI
 Copyright (C) 2009-2016 by the accounts Development Team.

 https://github.com/InfotelGLPI/accounts
 -------------------------------------------------------------------------

 LICENSE

 This file is part of accounts.

 accounts is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 accounts is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with accounts. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */


$AJAX_INCLUDE = 1;

include('../../../inc/includes.php');

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

Session::checkLoginUser();

if (isset($_POST['password'])) {

  echo Html::scriptBlock("
  
         function randomInt(n) {
            var x = Math.floor(Math.random() * n);
            if (x < 0 || x >= n)
               throw \"Arithmetic exception\";
            return x;
         }
         
         
         var CHARACTERS = [
         [ 'Numbers', '0123456789'],
         [ 'Lowercase', 'abcdefghijklmnopqrstuvwxyz'],
         [ 'Uppercase', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'],
         [ 'Special', '!\"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~'],
      ];
            var allChars = '';
            var j = 0;
      CHARACTERS.forEach(function(entry, i) {
         if (document.getElementById('char-' + i).checked){
               j = i + 1;
               allChars += entry[1];
            }
      });
      if(j==0){
         alert('".__("Select at least on checkbox","accounts")."');
      }
      
      length = parseInt(document.getElementById('length').value, 10);
      var chars = [];
      for (var i = 0; i < allChars.length; i++) {
         var c = allChars.charCodeAt(i);
         if (c < 0xD800 || c >= 0xE000) { 
            var s = allChars.charAt(i);
            if (chars.indexOf(s) == -1)
               chars.push(s);
            continue;
         }
         if (0xD800 <= c && c < 0xDC00 && i + 1 < allChars.length) { 
            var d = allChars.charCodeAt(i + 1);
            if (0xDC00 <= d && d < 0xE000) {  
               var s = allChars.substring(i, i + 2);
               i++;
               if (chars.indexOf(s) == -1)
                  chars.push(s);
               continue;
            }
         }

      }
        var result = '';
         
         for (var i = 0; i < length; i++)
            result += chars[randomInt(chars.length)];
         
         
      $('#hidden_password').val(result);
     
   ");
}

