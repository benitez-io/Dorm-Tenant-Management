/**
 * assets/js/validation.js
 * Client-side validation only. The server re-validates everything —
 * never trust the browser alone for data that ends up in the database.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // ---- Bootstrap's standard "needs-validation" pattern ---------------
    document.querySelectorAll('.needs-validation').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });

  });
})();
