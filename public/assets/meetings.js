(() => {
  'use strict';

  const root = document.querySelector('[data-meetings-root]');
  if (!root) return;

  const live = root.querySelector('[data-meetings-live]');
  const token = root.dataset.token || '';
  const statusLabels = {
    pending: 'Pendiente',
    in_progress: 'En proceso',
    completed: 'Realizada',
    not_completed: 'No realizada'
  };

  const announce = (message, error = false) => {
    if (!live) return;
    live.textContent = '';
    window.setTimeout(() => { live.textContent = message; }, 20);
    if (error) window.alert(message);
  };

  root.querySelectorAll('[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm || '¿Confirmar esta acción?')) event.preventDefault();
    });
  });

  let dialogTrigger = null;
  const openDialog = (dialog, trigger) => {
    if (!dialog) return;
    dialogTrigger = trigger || document.activeElement;
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
    window.setTimeout(() => dialog.querySelector('input:not([type="hidden"]), textarea, select, button')?.focus(), 20);
  };
  const closeDialog = (dialog) => {
    if (!dialog) return;
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
    dialogTrigger?.focus?.();
  };

  root.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => openDialog(document.getElementById(button.dataset.dialogOpen), button));
  });
  root.querySelectorAll('.meeting-dialog').forEach((dialog) => {
    dialog.querySelectorAll('[data-dialog-close]').forEach((button) => button.addEventListener('click', () => closeDialog(dialog)));
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) closeDialog(dialog);
    });
  });

  const normalizeSearch = (value) => String(value || '')
    .toLocaleLowerCase('es')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .trim();

  root.querySelectorAll('[data-participant-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-participant-search]');
    const list = picker.querySelector('[data-participant-options]');
    const selected = picker.querySelector('[data-participant-selected]');
    const empty = picker.querySelector('[data-participant-empty]');
    const checkboxes = Array.from(picker.querySelectorAll('[data-participant-checkbox]'));
    if (!search || !list || !selected) return;

    const open = () => {
      root.querySelectorAll('[data-participant-options]').forEach((other) => {
        if (other !== list) other.hidden = true;
      });
      list.hidden = false;
      search.setAttribute('aria-expanded', 'true');
    };
    const close = () => {
      list.hidden = true;
      search.setAttribute('aria-expanded', 'false');
    };
    const renderSelected = () => {
      selected.replaceChildren();
      const checked = checkboxes.filter((checkbox) => checkbox.checked);
      checked.forEach((checkbox) => {
        const chip = document.createElement('span');
        chip.dataset.participantChip = checkbox.value;
        chip.append(document.createTextNode(checkbox.dataset.participantName || 'Funcionario'));
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.dataset.participantRemove = checkbox.value;
        remove.setAttribute('aria-label', `Quitar a ${checkbox.dataset.participantName || 'funcionario'}`);
        remove.textContent = '×';
        chip.append(remove);
        selected.append(chip);
      });
      if (!checked.length) {
        const placeholder = document.createElement('small');
        placeholder.dataset.participantPlaceholder = '';
        placeholder.textContent = 'Ningún participante seleccionado';
        selected.append(placeholder);
      }
    };
    const filter = () => {
      const query = normalizeSearch(search.value);
      let visible = 0;
      picker.querySelectorAll('[data-participant-option]').forEach((option) => {
        const matches = normalizeSearch(option.dataset.search).includes(query);
        option.hidden = !matches;
        if (matches) visible += 1;
      });
      if (empty) {
        empty.hidden = visible > 0;
        empty.textContent = query ? 'No encontramos funcionarios con ese nombre.' : 'No hay funcionarios disponibles.';
      }
      open();
    };

    search.addEventListener('focus', open);
    search.addEventListener('click', open);
    search.addEventListener('input', filter);
    search.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { close(); search.blur(); }
      if (event.key === 'ArrowDown') {
        const available = checkboxes.find((checkbox) => !checkbox.closest('[data-participant-option]').hidden);
        if (available) { event.preventDefault(); available.focus(); }
      }
    });
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', renderSelected));
    selected.addEventListener('click', (event) => {
      const remove = event.target.closest('[data-participant-remove]');
      if (!remove) return;
      const checkbox = checkboxes.find((candidate) => candidate.value === remove.dataset.participantRemove);
      if (checkbox) checkbox.checked = false;
      renderSelected();
      search.focus();
    });
    picker.addEventListener('click', (event) => event.stopPropagation());
    document.addEventListener('click', close);
    renderSelected();
  });

  const itemDialog = document.getElementById('meeting-item-dialog');
  const itemForm = itemDialog?.querySelector('[data-item-form]');
  const sectionConfig = {
    achievement: {
      kicker: 'Logros y trabajo realizado', title: 'Registrar logro', titleLabel: 'Título del logro', contentLabel: 'Resultado alcanzado', help: 'Resume qué se ejecutó y cuál fue el resultado.'
    },
    improvement: {
      kicker: 'Puntos de mejora', title: 'Registrar mejora', titleLabel: 'Título del punto', contentLabel: 'Diagnóstico', help: 'Explica qué debe optimizarse y agrega una observación si aplica.'
    },
    action: {
      kicker: 'Plan de acción', title: 'Registrar acción', titleLabel: 'Descripción de la acción', contentLabel: 'Detalle', help: 'Asigna responsable, fecha y relación con una mejora.'
    }
  };

  const setNamedValue = (form, name, value) => {
    const field = form.elements.namedItem(name);
    if (field) field.value = value == null ? '' : String(value);
  };

  root.querySelectorAll('[data-item-open]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!itemDialog || !itemForm) return;
      let item = {};
      try { item = button.dataset.item ? JSON.parse(button.dataset.item) : {}; } catch (_) { item = {}; }
      const section = button.dataset.section || item.section || 'achievement';
      const config = sectionConfig[section] || sectionConfig.achievement;
      itemForm.reset();
      setNamedValue(itemForm, 'id', item.id || '');
      setNamedValue(itemForm, 'section', section);
      setNamedValue(itemForm, 'title', item.title || '');
      setNamedValue(itemForm, 'content', item.content || '');
      setNamedValue(itemForm, 'observation', item.observation || '');
      setNamedValue(itemForm, 'related_improvement_id', item.related_improvement_id || '');
      const responsibleSelect = itemForm.elements.namedItem('responsible_employee_id');
      if (responsibleSelect && item.responsible_employee_id && !Array.from(responsibleSelect.options).some((option) => option.value === String(item.responsible_employee_id))) {
        const historical = new Option(`${item.responsible_name || 'Funcionario'} · histórico`, String(item.responsible_employee_id), true, true);
        historical.dataset.historical = '1';
        responsibleSelect.add(historical);
      }
      setNamedValue(itemForm, 'responsible_employee_id', item.responsible_employee_id || '');
      setNamedValue(itemForm, 'due_date', item.due_date || '');
      itemForm.querySelector('[data-item-kicker]').textContent = config.kicker;
      itemForm.querySelector('[data-item-title]').textContent = item.id ? `Editar ${config.title.toLowerCase().replace('registrar ', '')}` : config.title;
      itemForm.querySelector('[data-item-help]').textContent = config.help;
      itemForm.querySelector('[data-field-title-label]').textContent = config.titleLabel;
      itemForm.querySelector('[data-field-content-label]').textContent = config.contentLabel;
      itemForm.querySelectorAll('[data-action-field]').forEach((field) => {
        field.hidden = section !== 'action';
        field.querySelectorAll('input, select, textarea').forEach((input) => {
          input.disabled = section !== 'action';
          input.required = section === 'action' && ['responsible_employee_id', 'due_date'].includes(input.name);
        });
      });
      const observation = itemForm.querySelector('[data-field-observation]');
      if (observation) {
        observation.hidden = section === 'achievement';
        observation.querySelector('textarea').disabled = section === 'achievement';
      }
      openDialog(itemDialog, button);
    });
  });

  const updateProgress = (progress) => {
    if (!progress) return;
    root.querySelectorAll('[data-progress-reviewed]').forEach((node) => { node.textContent = `${progress.reviewed}/${progress.total}`; });
    root.querySelectorAll('[data-progress-compliance]').forEach((node) => { node.textContent = `${progress.compliance_percent}%`; });
    root.querySelectorAll('[data-progress-bar]').forEach((node) => { node.style.width = `${progress.review_percent}%`; });
  };

  const setCardStatus = (card, item) => {
    if (!card || !item) return;
    Object.keys(statusLabels).forEach((status) => card.classList.remove(`status-${status}`));
    card.classList.add(`status-${item.status}`);
    card.querySelectorAll('[data-action-label]').forEach((node) => {
      Object.keys(statusLabels).forEach((status) => node.classList.remove(`status-${status}`));
      node.classList.add(`status-${item.status}`);
      node.textContent = statusLabels[item.status] || item.status;
    });
    const failureCopy = card.querySelector('[data-failure-copy]');
    if (failureCopy) {
      const reason = item.failure_reason || '';
      failureCopy.hidden = !reason;
      const target = failureCopy.querySelector('span');
      if (target) target.textContent = reason;
    }
  };

  const updateAction = async (button, status) => {
    const card = button.closest('[data-action-card]');
    const itemId = card?.dataset.itemId;
    if (!itemId) return;
    let reason = '';
    if (status === 'not_completed') {
      reason = card.querySelector('[data-action-reason-text]')?.value.trim() || '';
      if (!reason) {
        announce('Explica por qué la acción no se realizó.', true);
        card.querySelector('[data-action-reason-text]')?.focus();
        return;
      }
    }
    const buttons = card.querySelectorAll('[data-action-status], [data-action-reason-open]');
    buttons.forEach((node) => { node.disabled = true; });
    const formData = new FormData();
    formData.set('action', 'meeting_action_status');
    formData.set('_token', token);
    formData.set('response', 'json');
    formData.set('meeting_id', root.dataset.meetingId || '0');
    formData.set('item_id', itemId);
    formData.set('status', status);
    formData.set('reason', reason);
    try {
      const response = await fetch(window.location.href, {
        method: 'POST', body: formData, credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'No fue posible actualizar la acción.');
      setCardStatus(card, payload.item);
      updateProgress(payload.progress);
      const editor = card.querySelector('[data-action-reason]');
      if (editor) editor.hidden = true;
      announce(`Acción actualizada: ${statusLabels[status] || status}.`);
    } catch (error) {
      announce(error.message || 'No fue posible actualizar la acción.', true);
    } finally {
      buttons.forEach((node) => { node.disabled = false; });
    }
  };

  root.addEventListener('click', (event) => {
    const reasonOpen = event.target.closest('[data-action-reason-open]');
    if (reasonOpen) {
      const editor = reasonOpen.closest('[data-action-card]')?.querySelector('[data-action-reason]');
      if (editor) {
        editor.hidden = false;
        editor.querySelector('textarea')?.focus();
      }
      return;
    }
    const reasonCancel = event.target.closest('[data-action-reason-cancel]');
    if (reasonCancel) {
      const editor = reasonCancel.closest('[data-action-reason]');
      if (editor) editor.hidden = true;
      return;
    }
    const statusButton = event.target.closest('[data-action-status]');
    if (statusButton) updateAction(statusButton, statusButton.dataset.actionStatus || 'pending');
  });

  const steps = Array.from(root.querySelectorAll('[data-guide-step]'));
  if (steps.length) {
    const jumps = Array.from(root.querySelectorAll('[data-guide-jump]'));
    const previous = root.querySelector('[data-guide-prev]');
    const next = root.querySelector('[data-guide-next]');
    const currentLabel = root.querySelector('[data-guide-current]');
    let current = 0;

    const showStep = (index, focus = true) => {
      current = Math.max(0, Math.min(steps.length - 1, index));
      steps.forEach((step, stepIndex) => {
        const active = stepIndex === current;
        step.hidden = !active;
        step.classList.toggle('is-active', active);
      });
      jumps.forEach((button, buttonIndex) => {
        button.classList.toggle('is-active', buttonIndex === current);
        button.setAttribute('aria-current', buttonIndex === current ? 'step' : 'false');
      });
      if (previous) previous.disabled = current === 0;
      if (next) {
        next.disabled = current === steps.length - 1;
        next.textContent = current === steps.length - 2 ? 'Ir a conclusiones' : 'Siguiente';
      }
      if (currentLabel) currentLabel.textContent = String(current + 1);
      if (focus) steps[current].focus({ preventScroll: true });
      steps[current].scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    };

    previous?.addEventListener('click', () => showStep(current - 1));
    next?.addEventListener('click', () => showStep(current + 1));
    jumps.forEach((button) => button.addEventListener('click', () => showStep(Number(button.dataset.guideJump || 0))));
    document.addEventListener('keydown', (event) => {
      if (!root.classList.contains('is-guide-mode') || event.altKey || event.ctrlKey || event.metaKey) return;
      if (event.target.matches('input, textarea, select, button, a')) return;
      if (event.key === 'ArrowRight') { event.preventDefault(); showStep(current + 1); }
      if (event.key === 'ArrowLeft') { event.preventDefault(); showStep(current - 1); }
    });
    showStep(0, false);
  }
})();
