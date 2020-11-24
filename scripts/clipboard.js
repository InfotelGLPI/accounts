/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2018 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */
$(function() {
   // set a function to track drag hover event
   $(document).on("click", ".account_to_clipboard_wrapper", function(event) {
      event.preventDefault();
      var input = $('#hidden_password');
      if (input.attr("type") == "password") {
         input.attr("type", "text");
      } else {
         input.attr("type", "password");
      }

      // find the good element
      var target = $('.account_to_clipboard_wrapper');
      if (target.attr('class') === 'account_to_clipboard_wrapper') {
         target = target.find('*');
      }

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

   });
});
