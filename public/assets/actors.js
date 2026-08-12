(() => {
  const SMS_PROVIDER_PREFIX = 'SKC SuCasa Inmobiliaria ';
  const SMS_MAX_LENGTH = 160;
  const SMS_MESSAGE_MAX_LENGTH = SMS_MAX_LENGTH - SMS_PROVIDER_PREFIX.length;
  const selected = () => [...document.querySelectorAll('#actor-selection-form input[name="ids[]"]:checked')];
  const rowCheckboxes = () => [...document.querySelectorAll('#actor-selection-form input[name="ids[]"]')];

  function openModal(modal) {
    if (!modal) return;
    if (modal.parentElement !== document.body) document.body.appendChild(modal);
    modal.hidden = false;
    document.body.classList.add('modal-open');
  }

  function refreshSelection() {
    const count = selected().length;
    document.querySelectorAll('[data-requires-selection]').forEach((button) => {
      button.disabled = count === 0;
      button.title = count ? '' : 'Selecciona al menos un actor';
    });
    const holder = document.getElementById('mass-send-selected-ids');
    if (!holder) return;
    holder.replaceChildren(...selected().map((checkbox) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'ids[]';
      input.value = checkbox.value;
      return input;
    }));
  }

  function updateSmsCounter(form) {
    if (!form) return;
    const channel = form.querySelector('[data-channel-select]')?.value || 'email';
    const message = form.querySelector('textarea[name="message"]');
    const counter = form.querySelector('[data-sms-counter]');
    const prefix = form.querySelector('[data-sms-prefix]');
    const messageBox = form.querySelector('[data-sms-message-box]');
    const submit = form.querySelector('button.primary');
    if (!message || !counter) return;

    const isSms = channel === 'sms';
    const length = message.value.length;
    const effectiveLength = isSms ? length + SMS_PROVIDER_PREFIX.length : length;
    counter.hidden = !isSms;
    counter.textContent = `${effectiveLength}/${SMS_MAX_LENGTH} caracteres`;
    counter.classList.toggle('is-over-limit', isSms && effectiveLength > SMS_MAX_LENGTH);
    if (prefix) prefix.hidden = !isSms;
    if (messageBox) messageBox.classList.toggle('is-sms', isSms);
    if (isSms) {
      message.setAttribute('maxlength', String(SMS_MESSAGE_MAX_LENGTH));
    } else {
      message.removeAttribute('maxlength');
    }
    message.toggleAttribute('aria-invalid', isSms && effectiveLength > SMS_MAX_LENGTH);
    if (submit) submit.disabled = isSms && effectiveLength > SMS_MAX_LENGTH;
  }

  function updateChannelState(form) {
    if (!form) return;
    const select = form.querySelector('[data-channel-select]');
    const channel = select?.value || 'email';
    const email = channel === 'email';
    const subject = form.querySelector('input[name="subject"]');
    const subjectWrap = form.querySelector('[data-subject-wrap]');
    const attachment = form.querySelector('input[name="attachment"]');
    const attachmentWrap = form.querySelector('[data-attachment-wrap]');

    if (subject) {
      subject.disabled = !email;
      if (!email) subject.value = '';
    }
    if (subjectWrap) subjectWrap.hidden = !email;
    if (attachment) attachment.disabled = !email;
    if (attachmentWrap) attachmentWrap.hidden = !email;

    const templatePicker = form.querySelector('[data-template-picker]');
    form.querySelectorAll('[data-template-picker] option').forEach((option) => {
      if (!option.value) return;
      const templateChannel = option.dataset.templateChannel === 'wsp' ? 'whatsapp' : (option.dataset.templateChannel || 'email');
      const visible = templateChannel === channel;
      option.hidden = !visible;
      option.disabled = !visible;
    });
    if (templatePicker?.selectedOptions[0]?.disabled) {
      templatePicker.value = '';
    }
    updateSmsCounter(form);
  }

  document.addEventListener('change', (event) => {
    if (event.target.matches('[data-check-all]')) {
      rowCheckboxes().forEach((input) => {
        input.checked = event.target.checked;
      });
      refreshSelection();
    }

    if (event.target.matches('#actor-selection-form input[name="ids[]"]')) refreshSelection();

    if (event.target.matches('[data-campaign-picker]')) {
      const form = event.target.closest('form');
      const input = form?.querySelector('input[name="campaign_tag"]');
      if (input) input.value = event.target.value || '';
    }

    if (event.target.matches('[data-template-picker]')) {
      const option = event.target.selectedOptions[0];
      const form = event.target.closest('form');
      if (!option || !form) return;
      const subject = form.querySelector('input[name="subject"]');
      const message = form.querySelector('textarea[name="message"]');
      if (subject && option.dataset.subject) subject.value = option.dataset.subject;
      if (message && option.dataset.message) message.value = option.dataset.message;
      updateSmsCounter(form);
    }

    if (event.target.matches('[data-channel-select]')) {
      const form = event.target.closest('form');
      updateChannelState(form);
    }
  });

  document.addEventListener('input', (event) => {
    if (event.target.matches('textarea[name="message"]')) {
      updateSmsCounter(event.target.closest('form'));
    }
  });

  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.querySelector('[name="action"]')?.value !== 'send_campaign') return;
    const channel = form.querySelector('[data-channel-select]')?.value || 'email';
    const message = form.querySelector('textarea[name="message"]')?.value || '';
    if (channel === 'sms' && message.length + SMS_PROVIDER_PREFIX.length > SMS_MAX_LENGTH) {
      event.preventDefault();
      updateSmsCounter(form);
      form.querySelector('textarea[name="message"]')?.focus();
    }
  });

  document.addEventListener('click', async (event) => {
    const selectVisible = event.target.closest('[data-select-visible]');
    if (selectVisible) {
      event.preventDefault();
      rowCheckboxes().forEach((input) => {
        input.checked = true;
      });
      document.querySelectorAll('[data-check-all]').forEach((input) => {
        input.checked = true;
      });
      refreshSelection();
      return;
    }

    const clearSelection = event.target.closest('[data-clear-selection]');
    if (clearSelection) {
      event.preventDefault();
      document.querySelectorAll('#actor-selection-form input[name="ids[]"], [data-check-all]').forEach((input) => {
        input.checked = false;
      });
      refreshSelection();
      return;
    }

    const campaignButton = event.target.closest('[data-campaign-channel]');
    if (campaignButton) {
      event.preventDefault();
      if (campaignButton.disabled) return;
      refreshSelection();
      const channel = campaignButton.dataset.campaignChannel || 'email';
      const select = document.querySelector('#mass-send-form [data-channel-select]');
      if (select) {
        select.value = channel;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
      openModal(document.getElementById(campaignButton.dataset.modalOpen || 'mass-send-modal'));
      return;
    }

    const send = event.target.closest('[data-open-single-send]');
    if (send) {
      event.preventDefault();
      const modal = document.getElementById('single-send-modal');
      const actorInput = document.getElementById('single-send-actor-id');
      const channelSelect = document.getElementById('single-send-channel');
      const subtitle = document.getElementById('single-send-subtitle');
      const form = modal?.querySelector('form');
      if (actorInput) actorInput.value = send.dataset.openSingleSend || '';
      if (form) {
        const campaignInput = form.querySelector('input[name="campaign_tag"]');
        const campaignPicker = form.querySelector('[data-campaign-picker]');
        const templatePicker = form.querySelector('[data-template-picker]');
        const subject = form.querySelector('input[name="subject"]');
        const message = form.querySelector('textarea[name="message"]');
        if (campaignInput) campaignInput.value = '';
        if (campaignPicker) campaignPicker.value = '';
        if (templatePicker) templatePicker.value = '';
        if (subject) subject.value = '';
        if (message) message.value = '';
      }
      if (channelSelect) {
        channelSelect.value = send.dataset.sendChannel || 'email';
        channelSelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
      if (subtitle) subtitle.textContent = `Configura el envío para ${send.dataset.actorName || 'el actor'}.`;
      openModal(modal);
      return;
    }

    if (event.target.closest('[data-open-my-deliveries]')) {
      const modal = document.getElementById('my-deliveries-modal');
      const content = document.getElementById('my-deliveries-content');
      modal.hidden = false;
      document.body.classList.add('modal-open');
      content.textContent = 'Cargando…';
      try {
        const response = await fetch('?action=my-deliveries', { credentials: 'same-origin' });
        content.innerHTML = await response.text();
      } catch (_) {
        content.innerHTML = '<div class="empty-state"><h3>No fue posible consultar tus envíos</h3><p>Intenta nuevamente.</p></div>';
      }
    }
  });

  function enhanceActorForm(modal) {
    const form = modal.querySelector('#actor-form');
    if (!form || form.dataset.enhanced === '1') return;
    form.dataset.enhanced = '1';
    const total = modal.querySelector('[name="total_familia"]');
    const ranges = ['infancia_familia', 'ninez_familia', 'pubertad_familia', 'adolescencia_familia', 'juventud_familia', 'adulto_familia', 'adulto_mayor_familia', 'ancianidad_familia'];
    const updateTotal = () => {
      if (total) total.value = ranges.reduce((sum, name) => sum + Number(modal.querySelector(`[name="${name}"]`)?.value || 0), 0);
    };
    ranges.forEach((name) => modal.querySelector(`[name="${name}"]`)?.addEventListener('input', updateTotal));
    updateTotal();

    const previewButton = form.querySelector('[data-preview-actor]');
    const backButton = form.querySelector('[data-back-to-edit]');
    const previewPanel = form.querySelector('[data-actor-preview]');
    const editActions = form.querySelector('[data-edit-actions]');
    const confirmActions = form.querySelector('[data-confirm-actions]');
    const confirmButton = confirmActions?.querySelector('button[type="submit"]');
    const tokenInput = form.querySelector('[name="preview_token"]');

    const addTextRow = (container, className, title, before, after) => {
      const row = document.createElement('div');
      row.className = className;
      const heading = document.createElement('strong');
      heading.textContent = title;
      row.appendChild(heading);
      if (before !== undefined) {
        const values = document.createElement('span');
        const oldValue = document.createElement('del');
        const newValue = document.createElement('ins');
        oldValue.textContent = before || 'Vacío';
        newValue.textContent = after || 'Vacío';
        values.append(oldValue, document.createTextNode(' → '), newValue);
        row.appendChild(values);
      }
      container.appendChild(row);
    };

    const showEdit = () => {
      form.classList.remove('is-reviewing');
      if (previewPanel) previewPanel.hidden = true;
      if (editActions) editActions.hidden = false;
      if (confirmActions) confirmActions.hidden = true;
      if (tokenInput) tokenInput.value = '';
      previewButton?.focus();
    };

    previewButton?.addEventListener('click', async () => {
      if (!form.reportValidity()) return;
      previewButton.disabled = true;
      previewButton.textContent = 'Revisando…';
      form.setAttribute('aria-busy', 'true');
      const data = new FormData(form);
      data.set('action', 'preview_actor_update');
      try {
        const response = await fetch(window.location.href, {
          method: 'POST',
          body: data,
          credentials: 'same-origin',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'No fue posible revisar los cambios.');

        const changes = form.querySelector('[data-preview-changes]');
        const impact = form.querySelector('[data-preview-impact]');
        const warnings = form.querySelector('[data-preview-warnings]');
        const message = form.querySelector('[data-preview-message]');
        changes?.replaceChildren();
        impact?.replaceChildren();
        warnings?.replaceChildren();
        if (message) message.textContent = payload.changes.length
          ? `${payload.changes.length} campo(s) cambiarán.`
          : 'No se detectaron cambios.';
        payload.changes.forEach((change) => addTextRow(changes, 'actor-diff-row', change.label, change.before, change.after));
        payload.impact.forEach((item) => {
          const detail = `${item.active} activo(s)${item.excluded ? ` · ${item.excluded} histórico(s) sin modificar` : ''}`;
          addTextRow(impact, 'actor-impact-row', item.label, undefined, undefined);
          impact.lastElementChild?.appendChild(Object.assign(document.createElement('span'), { textContent: detail }));
        });
        payload.warnings.forEach((warning) => {
          const item = document.createElement('p');
          item.textContent = warning;
          warnings?.appendChild(item);
        });
        if (tokenInput) tokenInput.value = payload.preview_token || '';
        if (confirmButton) confirmButton.disabled = payload.changes.length === 0;
        form.classList.add('is-reviewing');
        if (previewPanel) previewPanel.hidden = false;
        if (editActions) editActions.hidden = true;
        if (confirmActions) confirmActions.hidden = false;
        previewPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        backButton?.focus();
      } catch (error) {
        const message = error instanceof Error ? error.message : 'No fue posible revisar los cambios.';
        window.alert(message);
      } finally {
        previewButton.disabled = false;
        previewButton.textContent = 'Revisar cambios';
        form.removeAttribute('aria-busy');
      }
    });

    backButton?.addEventListener('click', showEdit);
    form.addEventListener('submit', (event) => {
      if (!tokenInput?.value) {
        event.preventDefault();
        window.alert('Primero debes revisar los cambios.');
        previewButton?.focus();
        return;
      }
      if (confirmButton) {
        confirmButton.disabled = true;
        confirmButton.textContent = 'Actualizando…';
      }
      form.setAttribute('aria-busy', 'true');
    });
  }

  const rangeInputs = [...document.querySelectorAll('[data-date-range-filter]')];
  if (rangeInputs.length && !window.__dateRangePickerGlobal) {
    window.__dateRangePickerGlobal = true;
    const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const dayNames = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do'];
    let activeInput = null;
    let selectedStart = null;
    let selectedEnd = null;
    let visibleMonth = new Date();

    const picker = document.createElement('div');
    picker.className = 'date-range-picker';
    picker.hidden = true;
    picker.innerHTML = '<div class="date-range-head"><button type="button" data-range-prev aria-label="Mes anterior">&lsaquo;</button><strong data-range-title></strong><button type="button" data-range-next aria-label="Mes siguiente">&rsaquo;</button></div><div class="date-range-weekdays"></div><div class="date-range-days"></div><div class="date-range-actions"><button type="button" class="button slim ghost" data-range-clear>Limpiar</button><button type="button" class="primary slim" data-range-apply>Aplicar</button></div>';
    document.body.appendChild(picker);

    const parseDates = (value) => {
      const matches = String(value || '').match(/\d{4}-\d{2}-\d{2}/g) || [];
      return matches.map((date) => {
        const [year, month, day] = date.split('-').map(Number);
        return new Date(year, month - 1, day);
      }).filter((date) => !Number.isNaN(date.getTime()));
    };
    const dateKey = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    const sameDay = (a, b) => a && b && dateKey(a) === dateKey(b);
    const between = (date, start, end) => start && end && date > start && date < end;
    const fillInput = () => {
      if (!activeInput || !selectedStart) return;
      activeInput.value = selectedEnd ? `${dateKey(selectedStart)} - ${dateKey(selectedEnd)}` : dateKey(selectedStart);
    };
    const submitChange = () => {
      activeInput?.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const positionPicker = () => {
      if (!activeInput) return;
      const rect = activeInput.getBoundingClientRect();
      const maxLeft = Math.max(12, window.innerWidth - 326);
      picker.style.left = `${Math.min(maxLeft, Math.max(12, rect.left)) + window.scrollX}px`;
      picker.style.top = `${rect.bottom + 8 + window.scrollY}px`;
    };
    const renderPicker = () => {
      picker.querySelector('[data-range-title]').textContent = `${monthNames[visibleMonth.getMonth()]} ${visibleMonth.getFullYear()}`;
      picker.querySelector('.date-range-weekdays').innerHTML = dayNames.map((day) => `<span>${day}</span>`).join('');
      const first = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), 1);
      const firstOffset = (first.getDay() + 6) % 7;
      const daysInMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 0).getDate();
      const cells = [];
      for (let i = 0; i < firstOffset; i += 1) cells.push('<span></span>');
      for (let day = 1; day <= daysInMonth; day += 1) {
        const date = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), day);
        const classes = [
          sameDay(date, selectedStart) ? 'is-start' : '',
          sameDay(date, selectedEnd) ? 'is-end' : '',
          between(date, selectedStart, selectedEnd) ? 'is-between' : '',
          sameDay(date, new Date()) ? 'is-today' : '',
        ].filter(Boolean).join(' ');
        cells.push(`<button type="button" class="${classes}" data-range-day="${dateKey(date)}">${day}</button>`);
      }
      picker.querySelector('.date-range-days').innerHTML = cells.join('');
    };
    const openPicker = (input) => {
      activeInput = input;
      const dates = parseDates(input.value);
      selectedStart = dates[0] || null;
      selectedEnd = dates[1] || null;
      visibleMonth = new Date((selectedStart || new Date()).getFullYear(), (selectedStart || new Date()).getMonth(), 1);
      picker.hidden = false;
      renderPicker();
      positionPicker();
    };
    const closePicker = () => {
      picker.hidden = true;
      activeInput = null;
    };

    rangeInputs.forEach((input) => {
      input.addEventListener('focus', () => openPicker(input));
      input.addEventListener('click', () => openPicker(input));
    });

    picker.addEventListener('click', (event) => {
      const prev = event.target.closest('[data-range-prev]');
      const next = event.target.closest('[data-range-next]');
      const day = event.target.closest('[data-range-day]');
      if (prev || next) {
        visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + (prev ? -1 : 1), 1);
        renderPicker();
        return;
      }
      if (day) {
        const [year, month, date] = day.dataset.rangeDay.split('-').map(Number);
        const picked = new Date(year, month - 1, date);
        if (!selectedStart || selectedEnd) {
          selectedStart = picked;
          selectedEnd = null;
        } else if (picked < selectedStart) {
          selectedEnd = selectedStart;
          selectedStart = picked;
        } else {
          selectedEnd = picked;
        }
        fillInput();
        renderPicker();
        if (selectedEnd) {
          submitChange();
          closePicker();
        }
        return;
      }
      if (event.target.closest('[data-range-clear]')) {
        if (activeInput) {
          activeInput.value = '';
          submitChange();
        }
        closePicker();
        return;
      }
      if (event.target.closest('[data-range-apply]')) {
        fillInput();
        submitChange();
        closePicker();
      }
    });

    document.addEventListener('click', (event) => {
      if (picker.hidden || event.target.closest('.date-range-picker') || event.target.closest('[data-date-range-filter]')) return;
      closePicker();
    });
    window.addEventListener('resize', positionPicker);
    window.addEventListener('scroll', positionPicker, true);
  }

  const actorContent = document.querySelector('[data-actor-modal-content]');
  if (actorContent) new MutationObserver(() => enhanceActorForm(actorContent)).observe(actorContent, { childList: true });
  document.querySelectorAll('[data-channel-select]').forEach((select) => select.dispatchEvent(new Event('change', { bubbles: true })));
  refreshSelection();
})();
