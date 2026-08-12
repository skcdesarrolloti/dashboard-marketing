(function () {
  'use strict';

  let root = document.querySelector('[data-social-stats-root]');
  if (!root) return;

  let requestController = null;
  let componentController = null;
  let previousModalFocus = null;
  let statusTimer = 0;
  const scrollBehavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';

  const stateFromRoot = () => ({
    platform: root.getAttribute('data-social-platform') || 'instagram',
    from: root.getAttribute('data-social-from') || root.getAttribute('data-social-default-from') || '',
    to: root.getAttribute('data-social-to') || root.getAttribute('data-social-default-to') || ''
  });

  const cleanUrl = () => root.getAttribute('data-social-clean-url') || window.location.pathname;

  const setStatus = (message, tone) => {
    const status = root.querySelector('[data-social-fetch-status]');
    if (!status) return;
    window.clearTimeout(statusTimer);
    status.textContent = message || '';
    status.classList.toggle('is-error', tone === 'error');
    status.classList.toggle('is-success', tone === 'success');
    if (message && tone !== 'error') {
      statusTimer = window.setTimeout(() => {
        status.textContent = '';
        status.classList.remove('is-success');
      }, 3500);
    }
  };

  const setLoading = (loading, message) => {
    root.classList.toggle('is-loading', loading);
    root.setAttribute('aria-busy', loading ? 'true' : 'false');
    if (loading) setStatus(message || 'Actualizando datos…');
  };

  const copyRootState = (incoming) => {
    [
      'data-social-endpoint',
      'data-social-clean-url',
      'data-social-token',
      'data-social-platform',
      'data-social-from',
      'data-social-to',
      'data-social-default-from',
      'data-social-default-to'
    ].forEach((attribute) => {
      const value = incoming.getAttribute(attribute);
      if (value !== null) root.setAttribute(attribute, value);
    });
  };

  const syncParams = (params, summary) => {
    if (!summary) return;
    if (summary.provider === 'youtube') {
      params.set('youtube_synced', '1');
      const youtubeKeys = ['channels', 'daily', 'videos', 'analytics', 'breakdowns'];
      youtubeKeys.forEach((key) => params.set('youtube_' + key, String(Number(summary[key] || 0))));
      params.set('youtube_warnings', String(Array.isArray(summary.warnings) ? summary.warnings.length : Number(summary.warnings || 0)));
      return;
    }
    if (summary.provider === 'tiktok') {
      params.set('tiktok_synced', '1');
      ['accounts', 'videos', 'pages'].forEach((key) => params.set('tiktok_' + key, String(Number(summary[key] || 0))));
      params.set('tiktok_warnings', String(Array.isArray(summary.warnings) ? summary.warnings.length : Number(summary.warnings || 0)));
      return;
    }
    params.set('meta_synced', '1');
    const keys = ['accounts', 'daily', 'posts', 'insights', 'ads', 'audience', 'leads', 'conversations'];
    keys.forEach((key) => params.set('meta_' + key, String(Number(summary[key] || 0))));
    params.set('meta_warnings', String(Array.isArray(summary.warnings) ? summary.warnings.length : Number(summary.warnings || 0)));
  };

  const loadPanel = async (state, options) => {
    const settings = options || {};
    requestController?.abort();
    requestController = new AbortController();
    setLoading(true, settings.loadingMessage || 'Actualizando datos…');

    try {
      const endpoint = new URL(root.getAttribute('data-social-endpoint') || cleanUrl(), window.location.origin);
      const body = new FormData();
      body.set('action', 'social-stats-panel');
      body.set('_token', root.getAttribute('data-social-token') || '');
      body.set('platform', state.platform);
      body.set('from', state.from);
      body.set('to', state.to);
      syncParams(body, settings.syncSummary);

      const response = await fetch(endpoint.toString(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body,
        signal: requestController.signal
      });
      if (!response.ok) throw new Error('El servidor respondió con el código ' + response.status + '.');

      const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
      const incoming = documentFragment.querySelector('[data-social-stats-root]');
      if (!incoming) throw new Error('La sesión pudo haber expirado. Recarga la página e intenta nuevamente.');

      componentController?.abort();
      root.innerHTML = incoming.innerHTML;
      copyRootState(incoming);
      root.classList.remove('has-fetch-error');
      initComponents();
      setLoading(false);
      setStatus(settings.syncSummary ? 'Sincronización completada.' : 'Datos actualizados.', 'success');

      if (settings.focusTab) {
        root.querySelector('[data-social-platform-tab][aria-pressed="true"]')?.focus({ preventScroll: true });
      }
      return true;
    } catch (error) {
      if (error && error.name === 'AbortError') return false;
      setLoading(false);
      root.classList.add('has-fetch-error');
      setStatus((error && error.message) || 'No fue posible actualizar los datos. Intenta nuevamente.', 'error');
      return false;
    }
  };

  const navigate = async (state, focusTab) => {
    if (await loadPanel(state, { focusTab: Boolean(focusTab) })) {
      window.history.pushState({ socialStats: state }, '', cleanUrl());
    }
  };

  const escapeHtml = (value) => String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

  const renderItems = (items) => {
    if (!items || !items.length) {
      return '<article><span>Sin acciones</span><strong>0</strong><small>No hay acciones adicionales para mostrar.</small></article>';
    }
    return items.map((item) => (
      '<article><span>' + escapeHtml(item.label || '') + '</span><strong>' + escapeHtml(item.value || '0') + '</strong>' +
      (item.help ? '<small>' + escapeHtml(item.help) + '</small>' : '') + '</article>'
    )).join('');
  };

  const closeModal = () => {
    const modal = root.querySelector('[data-social-detail-modal]');
    if (!modal || !modal.classList.contains('is-open')) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (previousModalFocus instanceof HTMLElement) previousModalFocus.focus({ preventScroll: true });
    previousModalFocus = null;
  };

  const openModal = (payload, trigger) => {
    const modal = root.querySelector('[data-social-detail-modal]');
    if (!modal) return;
    const title = modal.querySelector('[data-social-detail-title]');
    const subtitle = modal.querySelector('[data-social-detail-subtitle]');
    const image = modal.querySelector('[data-social-detail-image]');
    const top = modal.querySelector('.social-detail-top');
    const description = modal.querySelector('[data-social-detail-description]');
    const notice = modal.querySelector('[data-social-detail-notice]');
    const metrics = modal.querySelector('[data-social-detail-metrics]');
    const actions = modal.querySelector('[data-social-detail-actions]');
    const actionsSection = modal.querySelector('[data-social-detail-actions-section]');
    const link = modal.querySelector('[data-social-detail-link]');
    title.textContent = payload.title || 'Detalle';
    subtitle.textContent = payload.subtitle || '';
    image.src = payload.thumbnail || '';
    image.alt = payload.thumbnail ? (payload.title || 'Detalle del anuncio') : '';
    top.classList.toggle('has-image', Boolean(payload.thumbnail));
    description.textContent = payload.description || '';
    description.hidden = !payload.description;
    notice.textContent = payload.notice || '';
    notice.hidden = !payload.notice;
    metrics.innerHTML = renderItems(payload.metrics || []);
    actions.innerHTML = renderItems(payload.actions || []);
    actionsSection.hidden = !payload.actions?.length;
    let safeLink = '';
    try {
      const parsedLink = new URL(payload.link || '', window.location.origin);
      if (['http:', 'https:'].includes(parsedLink.protocol)) safeLink = parsedLink.toString();
    } catch (_error) {
      safeLink = '';
    }
    link.hidden = !safeLink;
    link.href = safeLink || '#';
    link.textContent = payload.linkLabel || 'Ver publicación';
    previousModalFocus = trigger;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    modal.querySelector('[data-social-detail-close]')?.focus();
  };

  const initComponents = () => {
    componentController?.abort();
    componentController = new AbortController();
    const signal = componentController.signal;

    root.querySelectorAll('[data-social-chart]').forEach((chart) => {
      const tooltip = chart.querySelector('[data-social-tooltip]');
      const nav = chart.closest('.social-trend')?.querySelector('[data-social-chart-nav]');
      const start = nav?.querySelector('[data-chart-start]');
      const prev = nav?.querySelector('[data-chart-prev]');
      const next = nav?.querySelector('[data-chart-next]');
      const end = nav?.querySelector('[data-chart-end]');
      const windowLabel = nav?.querySelector('[data-chart-window]');
      const periodStep = Number(chart.getAttribute('data-period-step') || chart.getAttribute('data-day-step') || 72);
      const unit = chart.getAttribute('data-chart-unit') || 'periodo';
      const unitPlural = chart.getAttribute('data-chart-unit-plural') || 'periodos';
      let dragPointerId = null;
      let dragStartX = 0;
      let dragStartScroll = 0;
      let dragged = false;

      const updateWindow = () => {
        if (!windowLabel) return;
        const totalPeriods = Number(getComputedStyle(chart).getPropertyValue('--periods')) || Number(getComputedStyle(chart).getPropertyValue('--days')) || chart.querySelectorAll('.social-trend-point').length || 1;
        const visiblePeriods = Math.max(1, Math.floor(chart.clientWidth / Math.max(1, periodStep)));
        const startPeriod = Math.min(totalPeriods, Math.max(1, Math.round(chart.scrollLeft / Math.max(1, periodStep)) + 1));
        const endPeriod = Math.min(totalPeriods, startPeriod + visiblePeriods - 1);
        const label = startPeriod === endPeriod
          ? unit + ' ' + startPeriod + ' de ' + totalPeriods
          : unitPlural + ' ' + startPeriod + '–' + endPeriod + ' de ' + totalPeriods;
        windowLabel.textContent = label.charAt(0).toUpperCase() + label.slice(1);
        if (start) start.disabled = chart.scrollLeft <= 2;
        if (prev) prev.disabled = chart.scrollLeft <= 2;
        if (next) next.disabled = chart.scrollLeft + chart.clientWidth >= chart.scrollWidth - 2;
        if (end) end.disabled = chart.scrollLeft + chart.clientWidth >= chart.scrollWidth - 2;
      };

      const scrollChart = (left, timeout) => {
        chart.scrollTo({ left, behavior: scrollBehavior });
        window.setTimeout(updateWindow, timeout);
      };
      const pageStep = () => Math.max(periodStep, chart.clientWidth * .78);
      start?.addEventListener('click', () => scrollChart(0, 260), { signal });
      prev?.addEventListener('click', () => {
        chart.scrollBy({ left: -pageStep(), behavior: scrollBehavior });
        window.setTimeout(updateWindow, 220);
      }, { signal });
      next?.addEventListener('click', () => {
        chart.scrollBy({ left: pageStep(), behavior: scrollBehavior });
        window.setTimeout(updateWindow, 220);
      }, { signal });
      end?.addEventListener('click', () => scrollChart(chart.scrollWidth - chart.clientWidth, 260), { signal });
      chart.addEventListener('scroll', updateWindow, { signal });
      window.addEventListener('resize', updateWindow, { signal });

      chart.tabIndex = 0;
      chart.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight' && event.key !== 'Home' && event.key !== 'End') return;
        event.preventDefault();
        if (event.key === 'Home') scrollChart(0, 260);
        else if (event.key === 'End') scrollChart(chart.scrollWidth - chart.clientWidth, 260);
        else chart.scrollBy({ left: event.key === 'ArrowLeft' ? -pageStep() : pageStep(), behavior: scrollBehavior });
      }, { signal });

      chart.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || event.target.closest('button, a, input, select, textarea')) return;
        dragPointerId = event.pointerId;
        dragStartX = event.clientX;
        dragStartScroll = chart.scrollLeft;
        dragged = false;
        chart.classList.add('is-drag-ready');
        chart.setPointerCapture?.(event.pointerId);
      }, { signal });
      chart.addEventListener('pointermove', (event) => {
        if (dragPointerId !== event.pointerId) return;
        const delta = event.clientX - dragStartX;
        if (!dragged && Math.abs(delta) > 4) {
          dragged = true;
          chart.classList.add('is-dragging');
          tooltip?.classList.remove('is-visible');
        }
        if (!dragged) return;
        event.preventDefault();
        chart.scrollLeft = dragStartScroll - delta;
      }, { signal });
      const stopDragging = (event) => {
        if (dragPointerId !== event.pointerId) return;
        if (chart.hasPointerCapture?.(event.pointerId)) chart.releasePointerCapture(event.pointerId);
        dragPointerId = null;
        chart.classList.remove('is-drag-ready', 'is-dragging');
        updateWindow();
      };
      chart.addEventListener('pointerup', stopDragging, { signal });
      chart.addEventListener('pointercancel', stopDragging, { signal });
      chart.addEventListener('lostpointercapture', () => {
        dragPointerId = null;
        chart.classList.remove('is-drag-ready', 'is-dragging');
      }, { signal });
      chart.addEventListener('dragstart', (event) => event.preventDefault(), { signal });

      if (chart.scrollWidth > chart.clientWidth) chart.scrollLeft = chart.scrollWidth - chart.clientWidth;
      updateWindow();
      if (!tooltip) return;

      const show = (event, point) => {
        if (dragPointerId !== null || chart.classList.contains('is-dragging')) return;
        const html = point.getAttribute('data-tooltip-html') || point.getAttribute('data-tooltip') || '';
        if (!html) return;
        tooltip.innerHTML = html;
        tooltip.classList.add('is-visible');
        const chartRect = chart.getBoundingClientRect();
        const pointRect = point.getBoundingClientRect();
        const clientX = Number.isFinite(event.clientX) ? event.clientX : pointRect.left + (pointRect.width / 2);
        const clientY = Number.isFinite(event.clientY) ? event.clientY : pointRect.top + (pointRect.height / 2);
        const tooltipRect = tooltip.getBoundingClientRect();
        tooltip.style.left = Math.min(Math.max(12, clientX - chartRect.left + chart.scrollLeft + 16), chart.scrollLeft + chart.clientWidth - tooltipRect.width - 12) + 'px';
        tooltip.style.top = Math.min(Math.max(12, clientY - chartRect.top + chart.scrollTop - 18), chart.scrollTop + chart.clientHeight - tooltipRect.height - 12) + 'px';
      };

      chart.querySelectorAll('.social-trend-point').forEach((point) => {
        point.addEventListener('mousemove', (event) => show(event, point), { signal });
        point.addEventListener('mouseenter', (event) => show(event, point), { signal });
        point.addEventListener('mouseleave', () => tooltip.classList.remove('is-visible'), { signal });
        point.addEventListener('focusin', (event) => show(event, point), { signal });
        point.addEventListener('focusout', () => tooltip.classList.remove('is-visible'), { signal });
      });
      chart.addEventListener('scroll', () => tooltip.classList.remove('is-visible'), { signal });
    });
  };

  root.addEventListener('click', (event) => {
    const platformTab = event.target.closest('[data-social-platform-tab]');
    if (platformTab && root.contains(platformTab)) {
      const platform = platformTab.getAttribute('data-social-platform-tab');
      const state = stateFromRoot();
      if (platform && platform !== state.platform) navigate({ ...state, platform }, true);
      return;
    }

    const range = event.target.closest('[data-social-range]');
    if (range && root.contains(range)) {
      const state = stateFromRoot();
      const to = root.getAttribute('data-social-default-to') || state.to;
      let from = root.getAttribute('data-social-default-from') || state.from;
      if (range.getAttribute('data-social-range') === '30') {
        const date = new Date(to + 'T12:00:00');
        date.setDate(date.getDate() - 29);
        from = date.toISOString().slice(0, 10);
      }
      navigate({ ...state, from, to }, false);
      return;
    }

    const detail = event.target.closest('[data-social-ad-detail], [data-social-post-detail]');
    if (detail && root.contains(detail)) {
      try {
        openModal(JSON.parse(detail.getAttribute('data-detail') || '{}'), detail);
      } catch (_error) {
        openModal({ title: 'Detalle no disponible', metrics: [], actions: [] }, detail);
      }
      return;
    }

    if (event.target.closest('[data-social-detail-close]')) closeModal();
  });

  root.addEventListener('toggle', (event) => {
    const opened = event.target;
    if (!(opened instanceof HTMLDetailsElement) || !opened.matches('.social-post-month') || !opened.open) return;
    root.querySelectorAll('.social-post-month[open]').forEach((details) => {
      if (details !== opened) details.open = false;
    });
  }, true);

  root.addEventListener('submit', async (event) => {
    const filterForm = event.target.closest('[data-social-filter-form]');
    if (filterForm) {
      event.preventDefault();
      const fromInput = filterForm.querySelector('[data-social-from]');
      const toInput = filterForm.querySelector('[data-social-to]');
      const from = fromInput?.value || '';
      const to = toInput?.value || '';
      fromInput?.setCustomValidity(from && to && from > to ? 'La fecha inicial no puede ser posterior a la fecha final.' : '');
      if (!filterForm.reportValidity()) return;
      await navigate({ ...stateFromRoot(), from, to }, false);
      return;
    }

    const syncForm = event.target.closest('[data-social-sync-form]');
    if (!syncForm) return;
    event.preventDefault();
    const button = syncForm.querySelector('button[type="submit"]');
    if (!button || button.disabled) return;
    const originalLabel = button.textContent;
    const requestedProvider = syncForm.getAttribute('data-social-provider');
    const provider = ['youtube', 'tiktok'].includes(requestedProvider) ? requestedProvider : 'meta';
    const providerLabel = provider === 'youtube' ? 'YouTube' : (provider === 'tiktok' ? 'TikTok' : 'Meta');
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.textContent = button.getAttribute('data-loading-label') || 'Sincronizando…';
    setStatus('Sincronizando datos con ' + providerLabel + '…');

    try {
      const body = new FormData(syncForm);
      body.set('response', 'json');
      const response = await fetch(cleanUrl(), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok || !result.ok) throw new Error(result.message || 'No fue posible sincronizar ' + providerLabel + '.');
      const refreshed = await loadPanel(stateFromRoot(), { syncSummary: result.summary, loadingMessage: 'Guardando la información sincronizada…' });
      if (!refreshed) {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.textContent = originalLabel;
      }
    } catch (error) {
      button.disabled = false;
      button.removeAttribute('aria-busy');
      button.textContent = originalLabel;
      setStatus((error && error.message) || 'No fue posible sincronizar ' + providerLabel + '.', 'error');
    }
  });

  window.addEventListener('popstate', (event) => {
    if (event.state?.socialStats) loadPanel(event.state.socialStats, { focusTab: true });
  });

  document.addEventListener('keydown', (event) => {
    const modal = root.querySelector('[data-social-detail-modal].is-open');
    if (event.key === 'Escape') {
      closeModal();
      return;
    }
    if (event.key !== 'Tab' || !modal) return;
    const focusable = Array.from(modal.querySelectorAll('button:not([disabled]), a[href]:not([hidden]), [tabindex]:not([tabindex="-1"])'))
      .filter((element) => element instanceof HTMLElement && element.offsetParent !== null);
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  const initialState = stateFromRoot();
  window.history.replaceState({ ...(window.history.state || {}), socialStats: initialState }, '', cleanUrl());
  initComponents();
})();
