/**
 * live-match.js — Progressive enhancement for the live match view.
 *
 * Handles showing/hiding inline event forms when toggle buttons are clicked.
 * No AJAX, no fetch, no XHR. All actions are full-page POST + redirect.
 *
 * The view already includes inline onclick handlers as a fallback; this script
 * replaces them with delegated event listeners for cleaner separation.
 */

(function () {
  'use strict';

  /**
   * Hide all elements with the class .event-form.
   */
  function hideAllEventForms() {
    document.querySelectorAll('.event-form').forEach(function (el) {
      el.hidden = true;
    });
  }

  /**
   * Show a specific event form by ID.
   * @param {string} formId
   */
  function showEventForm(formId) {
    var form = document.getElementById(formId);
    if (form) {
      form.hidden = false;
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    // ── Toggle buttons ──────────────────────────────────────────────────
    // Each toggle button targets a form via aria-controls="form-<type>"
    var toggleButtons = document.querySelectorAll('[aria-controls^="form-"]');

    toggleButtons.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        var targetId = btn.getAttribute('aria-controls');

        // If the form is already visible, hide it (toggle off)
        var target = document.getElementById(targetId);
        if (target && !target.hidden) {
          hideAllEventForms();
          return;
        }

        hideAllEventForms();
        showEventForm(targetId);

        // Scroll the form into view on small screens
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    });

    // ── Cancel buttons ──────────────────────────────────────────────────
    // Each cancel button is inside an .event-form and hides its parent form
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('button[type="button"]');
      if (!btn) return;

      var form = btn.closest('.event-form');
      if (form && btn.textContent.trim().toLowerCase() === 'cancel') {
        form.hidden = true;
      }
    });
  });
}());
