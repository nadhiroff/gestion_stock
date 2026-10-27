document.addEventListener('DOMContentLoaded', () => {
  // SweetAlert2 data-confirm handler (fallback to native if Swal absent)
  document.body.addEventListener('click', async (e) => {
    const a = e.target.closest('a[data-confirm], button[data-confirm]');
    if (!a) return;
    // Skip special buttons that implement their own flow
    if (a.tagName.toLowerCase() === 'button' && a.dataset.action === 'remove-row') return;
    const msg = a.getAttribute('data-confirm') || 'Êtes-vous sûr ?';
    if (window.Swal) {
      e.preventDefault();
      const res = await Swal.fire({
        title: 'Confirmation',
        text: msg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
      });
      if (res.isConfirmed) {
        const form = a.form || a.closest('form');
        if (form) form.submit();
        else window.location.href = a.getAttribute('href');
      }
    } else {
      // No SweetAlert available; do not intercept (no legacy confirm)
      // Allow default navigation or form submission
    }
  });

  // auto-dismiss alerts (guard if Bootstrap not available)
  try {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach((el) => {
      setTimeout(() => {
        if (!window.bootstrap || !bootstrap.Alert) return;
        if (el.classList.contains('show')) {
          const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
          bsAlert.close();
        }
      }, 4000);
    });
  } catch (_) { /* ignore to avoid blocking rest of scripts */ }

  // password toggle (any input with data-toggle-password)
  document.querySelectorAll('[data-toggle-password]').forEach((toggle) => {
    const targetSelector = toggle.getAttribute('data-toggle-password');
    if (!targetSelector) return;

    // Support multiple selectors (comma-separated)
    const selectors = targetSelector.split(',').map(s => s.trim());

    function setupToggle() {
      const inputs = selectors.flatMap(selector => Array.from(document.querySelectorAll(selector)));

      console.log('Password toggle found:', toggle.id, 'selectors:', selectors, 'inputs found:', inputs.length);

      if (!inputs.length) {
        console.log('No inputs found for selector:', targetSelector, 'retrying...');
        setTimeout(setupToggle, 100); // Retry after 100ms
        return;
      }

      toggle.addEventListener('change', () => {
        const isChecked = toggle.checked;
        console.log('Toggle changed:', isChecked, 'inputs:', inputs.length);
        inputs.forEach(input => {
          input.type = isChecked ? 'text' : 'password';
          console.log('Changed input:', input.id, 'to type:', input.type);
        });
      });
    }

    setupToggle();
  });

  // Specific logout button behavior (SweetAlert2), if present
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async (e) => {
      if (!window.Swal) return; // global handler will still work
      e.preventDefault();
      const res = await Swal.fire({
        title: 'Confirmation',
        text: 'Voulez-vous vraiment vous déconnecter ? ',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d10024',
        cancelButtonColor: '#0b0b0b',
        confirmButtonText: 'Oui, déconnecter',
        cancelButtonText: 'Annuler'
      });
      if (res.isConfirmed) {
        window.location.href = logoutBtn.href; // conserve la route logout.php
      }
    });
  }

  // Global date constraints: block future dates and enforce end >= start
  (function enforceDateConstraints() {
    const today = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const y = today.getFullYear();
    const m = pad(today.getMonth() + 1);
    const d = pad(today.getDate());
    const todayStr = `${y}-${m}-${d}`;

    // Apply max=today to native date controls
    const dateSelectors = 'input[type="date"], input[type="datetime-local"]';
    document.querySelectorAll(dateSelectors).forEach((inp) => {
      // Allow opt-out per field
      if (inp.hasAttribute('data-allow-future')) return;
      // Only set max if not already stricter
      try {
        const currentMax = inp.getAttribute('max');
        if (!currentMax || currentMax > todayStr) inp.setAttribute('max', todayStr);
      } catch (_) {}
    });

    // Helper: link start/end fields within same form
    function notifyInvalidRange() {
      const msg = 'La date de fin ne doit pas être antérieure à la date de début.';
      if (window.Swal) {
        Swal.fire({ icon: 'error', title: 'Période invalide', text: msg, confirmButtonColor: '#d10024' });
      } else {
        alert(msg);
      }
    }

    function linkRange(startEl, endEl) {
      if (!startEl || !endEl) return;
      // Initialize end min
      if (startEl.value) endEl.min = startEl.value;
      // Keep constraints in sync
      startEl.addEventListener('change', () => {
        endEl.min = startEl.value || '';
        if (endEl.value && startEl.value && endEl.value < startEl.value) {
          endEl.value = startEl.value;
          endEl.setCustomValidity('La date de fin ne doit pas être antérieure à la date de début.');
          notifyInvalidRange();
          setTimeout(()=>{ endEl.reportValidity(); endEl.focus(); }, 0);
        }
      });
      endEl.addEventListener('change', () => {
        if (startEl.value && endEl.value < startEl.value) {
          endEl.value = startEl.value;
          endEl.setCustomValidity('La date de fin ne doit pas être antérieure à la date de début.');
          notifyInvalidRange();
          setTimeout(()=>{ endEl.reportValidity(); endEl.focus(); }, 0);
        }
        else { endEl.setCustomValidity(''); }
      });
    }

    // Strategy A: explicit data attributes
    document.querySelectorAll('input[type="date"][data-range="start"], input[type="datetime-local"][data-range="start"]').forEach((startEl) => {
      const form = startEl.form || document;
      const endEl = form.querySelector('input[type="date"][data-range="end"], input[type="datetime-local"][data-range="end"]');
      if (endEl) linkRange(startEl, endEl);
    });

    // Strategy B: common name patterns (debut/fin, start/end, from/to)
    const patternStart = /(date_?debut|debut|start|date_start|from|date_from)$/i;
    const patternEnd = /(date_?fin|fin|end|date_end|to|date_to)$/i;
    document.querySelectorAll(dateSelectors).forEach((el) => {
      const name = el.getAttribute('name') || el.id || '';
      const form = el.form || document;
      if (patternStart.test(name)) {
        const endEl = Array.from(form.querySelectorAll(dateSelectors)).find((cand) => {
          const n = cand.getAttribute('name') || cand.id || '';
          return patternEnd.test(n);
        });
        if (endEl) linkRange(el, endEl);
      }
    });

    // Prevent invalid form submission (handles pages that auto-submit on change)
    document.querySelectorAll('form').forEach((form)=>{
      form.addEventListener('submit', (e) => {
        const inputs = Array.from(form.querySelectorAll(dateSelectors));
        if (inputs.length < 2) return; // need a pair
        const startEl = inputs.find(i => /(date_?debut|debut|start|date_start|from|date_from)$/i.test(i.name || i.id) || i.dataset.range === 'start');
        const endEl = inputs.find(i => /(date_?fin|fin|end|date_end|to|date_to)$/i.test(i.name || i.id) || i.dataset.range === 'end');
        if (startEl && endEl) {
          const s = startEl.value;
          const d = endEl.value;
          if (s && d && d < s) {
            e.preventDefault();
            notifyInvalidRange();
            endEl.setCustomValidity('La date de fin ne doit pas être antérieure à la date de début.');
            endEl.reportValidity();
            endEl.focus();
          } else {
            endEl.setCustomValidity('');
          }
        }
      });
    });
  })();

  // Session persistence for period filters (start/end)
  (function persistPeriodFilters(){
    try {
      const form = document.getElementById('periodForm');
      if (!form) return;
      const startEl = form.querySelector('input[name="start"]');
      const endEl = form.querySelector('input[name="end"]');
      if (!startEl || !endEl) return;
      // Namespace keys by range-name if provided, otherwise by current path
      const group = (startEl.dataset.rangeName || endEl.dataset.rangeName || form.dataset.rangeName || window.location.pathname || 'default').replace(/\s+/g,'-');
      const keyStart = `period:${group}:start`;
      const keyEnd = `period:${group}:end`;
      const qs = new URLSearchParams(window.location.search);

      // Restore if URL has no explicit period
      const hasQsPeriod = qs.has('start') || qs.has('end');
      const savedStart = sessionStorage.getItem(keyStart);
      const savedEnd = sessionStorage.getItem(keyEnd);
      if (!hasQsPeriod && savedStart && savedEnd) {
        // Only submit if different to avoid loops
        const needSubmit = (startEl.value !== savedStart) || (endEl.value !== savedEnd);
        startEl.value = savedStart;
        endEl.value = savedEnd;
        if (needSubmit) {
          form.submit();
          return; // prevent further handlers
        }
      }

      // Save on change + auto-submit when both dates present and valid
      const save = () => {
        if (startEl.value) sessionStorage.setItem(keyStart, startEl.value);
        if (endEl.value) sessionStorage.setItem(keyEnd, endEl.value);
      };
      const submitIfReady = (() => {
        let t;
        return () => {
          clearTimeout(t);
          t = setTimeout(() => {
            const s = startEl.value;
            const d = endEl.value;
            if (s && d && d >= s) {
              const current = new URLSearchParams(window.location.search);
              if (current.get('start') === s && current.get('end') === d) return;
              form.submit();
            }
          }, 200);
        };
      })();
      const onChange = () => { save(); submitIfReady(); };
      startEl.addEventListener('change', onChange);
      endEl.addEventListener('change', onChange);

      // Clear on logout confirmation
      const logoutBtn = document.getElementById('logoutBtn');
      if (logoutBtn) {
        const clearPeriod = () => {
          try { sessionStorage.removeItem(keyStart); sessionStorage.removeItem(keyEnd); } catch(_) {}
        };
        logoutBtn.addEventListener('click', () => { clearPeriod(); });
      }
    } catch (_) { /* no-op */ }
  })();

  // Count-up animation for KPI numbers on public page
  (function countUpKpis(){
    const els = document.querySelectorAll('.countup[data-countto]');
    if (!els.length) return;

    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function easeOutCubic(t){ return 1 - Math.pow(1 - t, 3); }

    function animateCount(el){
      const target = parseFloat(el.getAttribute('data-countto')) || 0;
      // Allow per-element duration override via data-duration (ms)
      const customDur = parseInt(el.getAttribute('data-duration'), 10);
      // Default: 10 seconds; can be overridden per element with data-duration (ms)
      let duration = prefersReduced ? 0 : (!isNaN(customDur) ? customDur : 10000); // ms
      const startTime = performance.now();

      if (duration === 0){ el.textContent = Math.round(target).toLocaleString(); return; }

      function frame(now){
        const elapsed = now - startTime;
        const t = Math.min(1, elapsed / duration);
        const eased = easeOutCubic(t);
        const current = Math.round(eased * target);
        el.textContent = current.toLocaleString();
        if (t < 1) requestAnimationFrame(frame);
        else el.textContent = Math.round(target).toLocaleString();
      }
      requestAnimationFrame(frame);
    }

    const inViewport = (el) => {
      const r = el.getBoundingClientRect();
      const vh = window.innerHeight || document.documentElement.clientHeight;
      const vw = window.innerWidth || document.documentElement.clientWidth;
      return (
        r.bottom >= 0 && r.right >= 0 && r.top <= vh && r.left <= vw
      );
    };

    const trigger = (el) => {
      if (el.dataset.counted === '1') return;
      animateCount(el);
      el.dataset.counted = '1';
    };

    const tryStartVisible = () => {
      els.forEach(el => { if (inViewport(el)) trigger(el); });
    };

    if ('IntersectionObserver' in window) {
      const obs = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
          if (entry.isIntersecting){
            const el = entry.target;
            trigger(el);
            observer.unobserve(el);
          }
        });
      }, { threshold: 0.0, rootMargin: '0px 0px -10% 0px' });
      els.forEach(el => obs.observe(el));
      // Immediate check on load in case already visible
      tryStartVisible();
    } else {
      // Fallback: check on load and on scroll/resize
      tryStartVisible();
      let ticking = false;
      const onScroll = () => {
        if (ticking) return; ticking = true;
        requestAnimationFrame(() => { tryStartVisible(); ticking = false; });
      };
      window.addEventListener('scroll', onScroll, { passive: true });
      window.addEventListener('resize', onScroll);
    }

    // Last-resort safety: start any remaining after a short delay
    setTimeout(() => { els.forEach(trigger); }, 1500);
  })();

  // Live update of abonnement counters on internal dashboard (no refresh)
  (function liveAboCounters(){
    const elTotal = document.getElementById('abo-total');
    const elActifs = document.getElementById('abo-actifs');
    const elSuspendus = document.getElementById('abo-suspendus');
    const elResilies = document.getElementById('abo-resilies');
    if (!elTotal && !elActifs && !elSuspendus && !elResilies) return; // not on dashboard

    // Derive BASE_URL from the script src ending with /assets/js/app.js
    let baseURL = '';
    try {
      const scripts = Array.from(document.getElementsByTagName('script'));
      const me = scripts.find(s => /\/assets\/js\/app\.js(\?.*)?$/.test(s.src));
      if (me) {
        const i = me.src.indexOf('/assets/');
        baseURL = me.src.substring(0, i);
      } else {
        baseURL = window.location.origin; // fallback
      }
    } catch(_) { baseURL = window.location.origin; }

    const endpoint = baseURL + '/app/abonnements/stats.php';

    let timer = null;
    let inFlight = false;
    const intervalMs = 30000; // 30s

    async function tick(){
      if (inFlight || document.hidden) return;
      inFlight = true;
      try {
        const res = await fetch(endpoint, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (json && json.ok && json.data) {
          const d = json.data;
          if (elTotal)     elTotal.textContent = (d.total ?? '').toLocaleString?.() || d.total;
          if (elActifs)    elActifs.textContent = (d.actifs ?? '').toLocaleString?.() || d.actifs;
          if (elSuspendus) elSuspendus.textContent = (d.suspendus ?? '').toLocaleString?.() || d.suspendus;
          if (elResilies)  elResilies.textContent = (d.resilies ?? '').toLocaleString?.() || d.resilies;
        }
      } catch(_) { /* silent fail to avoid UI noise */ }
      finally { inFlight = false; }
    }

    function start(){
      if (timer) return;
      tick();
      timer = setInterval(tick, intervalMs);
    }
    function stop(){ if (timer) { clearInterval(timer); timer = null; } }

    document.addEventListener('visibilitychange', () => {
      if (document.hidden) stop(); else start();
    });
    window.addEventListener('focus', tick);

    start();
  })();

  // Note: client-side export pre-check removed to avoid duplicate logic.
});
