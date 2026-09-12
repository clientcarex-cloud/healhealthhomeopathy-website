/* Heal Health Homeopathy — site interactions */
(function () {
  'use strict';

  var $  = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* ---------------------------------------------------------------------
     Mobile navigation
     --------------------------------------------------------------------- */
  var nav = $('#nav');
  var navToggle = $('#navToggle');

  if (nav && navToggle) {
    navToggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', String(open));
      navToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });

    // Close after choosing a destination.
    $$('a', nav).forEach(function (link) {
      link.addEventListener('click', function () {
        nav.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
      });
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.focus();
      }
    });
  }

  /* ---------------------------------------------------------------------
     Sticky header shadow
     --------------------------------------------------------------------- */
  var header = $('#header');
  if (header) {
    var onScroll = function () {
      header.classList.toggle('is-stuck', window.scrollY > 12);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------------------------------------------------------------------
     Scroll reveal
     --------------------------------------------------------------------- */
  var reveals = $$('.reveal');
  if (reveals.length) {
    if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-in');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });

      reveals.forEach(function (el, i) {
        el.style.transitionDelay = (Math.min(i % 4, 3) * 70) + 'ms';
        observer.observe(el);
      });
    } else {
      reveals.forEach(function (el) { el.classList.add('is-in'); });
    }
  }

  /* ---------------------------------------------------------------------
     Booking form
     --------------------------------------------------------------------- */
  var form = $('#bookingForm');
  if (!form) { return; }

  var submitBtn  = $('#submitBtn');
  var btnLabel   = submitBtn ? submitBtn.innerHTML : '';
  var alertBox   = $('#formAlert');
  var alertIcon  = $('#alertIcon');
  var alertTitle = $('#alertTitle');
  var alertText  = $('#alertText');
  var dateInput  = $('#f-date');
  var renderedAt = $('#renderedAt');

  var ICON_OK = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="16 9.5 10.8 15 8 12.3"/></svg>';
  var ICON_ERR = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="7.5" x2="12" y2="13"/><circle cx="12" cy="16.5" r=".9" fill="currentColor"/></svg>';

  // Stamp render time for the server-side time trap.
  if (renderedAt) { renderedAt.value = String(Date.now()); }

  // Preferred date: today .. +6 months, defaulting to tomorrow.
  if (dateInput) {
    var today = new Date();
    var iso = function (d) { return d.toISOString().slice(0, 10); };
    var max = new Date(today.getTime());
    max.setMonth(max.getMonth() + 6);

    dateInput.min = iso(today);
    dateInput.max = iso(max);

    var tomorrow = new Date(today.getTime() + 86400000);
    dateInput.value = iso(tomorrow);
  }

  /* "Book for this" links preselect the matching concern. */
  $$('[data-service]').forEach(function (link) {
    link.addEventListener('click', function () {
      var select = $('#f-service');
      if (select) { select.value = link.getAttribute('data-service'); }
    });
  });

  function clearErrors() {
    $$('.field', form).forEach(function (field) {
      field.classList.remove('has-error');
      var slot = $('.field__error', field);
      if (slot) { slot.textContent = ''; }
    });
  }

  function showFieldError(name, message) {
    var field = $('[data-field="' + name + '"]', form);
    if (!field) { return; }
    field.classList.add('has-error');
    var slot = $('.field__error', field);
    if (slot) { slot.textContent = message; }
  }

  function showAlert(kind, title, text) {
    if (!alertBox) { return; }
    alertBox.className = 'form-alert is-visible form-alert--' + kind;
    alertIcon.innerHTML = kind === 'ok' ? ICON_OK : ICON_ERR;
    alertTitle.textContent = title;
    alertText.textContent = text;
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function hideAlert() {
    if (alertBox) { alertBox.className = 'form-alert'; }
  }

  function setLoading(isLoading) {
    if (!submitBtn) { return; }
    submitBtn.classList.toggle('is-loading', isLoading);
    submitBtn.disabled = isLoading;
    submitBtn.innerHTML = isLoading
      ? '<span class="spinner"></span> Sending your request…'
      : btnLabel;
  }

  /* Client-side checks mirror the server; the server remains authoritative. */
  function validate(data) {
    var errors = {};

    if (!data.name || data.name.trim().length < 2) {
      errors.name = 'Please enter your full name.';
    }

    var digits = (data.phone || '').replace(/\D+/g, '');
    if (digits.length < 10 || digits.length > 15) {
      errors.phone = 'Please enter a valid phone number.';
    }

    if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(data.email)) {
      errors.email = 'Please enter a valid email address.';
    }

    if (!data.service) { errors.service = 'Please choose what you would like help with.'; }
    if (!data.mode)    { errors.mode = 'Please choose a consultation type.'; }
    if (!data.date)    { errors.date = 'Please choose a preferred date.'; }
    if (!data.slot)    { errors.slot = 'Please choose a preferred time slot.'; }

    return errors;
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearErrors();
    hideAlert();

    var consent = $('#f-consent');
    if (consent && !consent.checked) {
      showAlert('error', 'One more thing', 'Please tick the consent box so we may contact you about this appointment.');
      consent.focus();
      return;
    }

    var data = {};
    new FormData(form).forEach(function (value, key) { data[key] = value; });

    var errors = validate(data);
    var names = Object.keys(errors);
    if (names.length) {
      names.forEach(function (name) { showFieldError(name, errors[name]); });
      showAlert('error', 'Please check the highlighted fields', 'A few details are missing or look incorrect.');
      var firstField = $('[data-field="' + names[0] + '"] input, [data-field="' + names[0] + '"] select', form);
      if (firstField) { firstField.focus(); }
      return;
    }

    setLoading(true);

    fetch(form.action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(data)
    })
      .then(function (response) {
        return response.json()
          .catch(function () { throw new Error('Unexpected server response.'); })
          .then(function (payload) { return { status: response.status, payload: payload }; });
      })
      .then(function (result) {
        var payload = result.payload || {};

        if (result.status === 200 && payload.ok) {
          form.reset();
          if (renderedAt) { renderedAt.value = String(Date.now()); }
          if (dateInput) {
            var t = new Date(Date.now() + 86400000);
            dateInput.value = t.toISOString().slice(0, 10);
          }
          showAlert(
            'ok',
            'Request received' + (payload.reference ? ' — ref ' + payload.reference : ''),
            payload.message || 'Thank you. We will call you shortly to confirm your appointment.'
          );
          return;
        }

        if (payload.errors) {
          Object.keys(payload.errors).forEach(function (name) {
            showFieldError(name, payload.errors[name]);
          });
        }

        showAlert('error', 'We could not send that', payload.message || 'Something went wrong. Please try again.');
      })
      .catch(function () {
        showAlert(
          'error',
          'Network problem',
          'We could not reach the server. Please check your connection, or call us on +91 70322 58110.'
        );
      })
      .finally(function () {
        setLoading(false);
      });
  });

  /* Clear a field's error as soon as the visitor edits it. */
  $$('input, select, textarea', form).forEach(function (input) {
    input.addEventListener('input', function () {
      var field = input.closest('.field');
      if (field && field.classList.contains('has-error')) {
        field.classList.remove('has-error');
        var slot = $('.field__error', field);
        if (slot) { slot.textContent = ''; }
      }
    });
  });
})();
