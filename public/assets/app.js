document.addEventListener('change', (event) => {
  if (!event.target.matches('[data-check-all]')) return;
  document.querySelectorAll('input[name="ids[]"]').forEach((input) => {
    input.checked = event.target.checked;
  });
  updateSelectedCount();
  refreshActorCampaignSelection();
});

function updateSelectedCount() {
  const count = document.querySelectorAll('input[name="ids[]"]:checked').length;
  document.querySelectorAll('[data-selected-count]').forEach((node) => {
    node.textContent = `${count} seleccionado${count === 1 ? '' : 's'}`;
  });
}

function refreshActorCampaignSelection() {
  const selected = [...document.querySelectorAll('#actor-selection-form input[name="ids[]"]:checked')];
  document.querySelectorAll('[data-requires-selection]').forEach((button) => {
    button.disabled = selected.length === 0;
    button.title = selected.length ? '' : 'Selecciona al menos un actor';
  });
  const holder = document.getElementById('mass-send-selected-ids');
  if (!holder) return;
  holder.replaceChildren(...selected.map((checkbox) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'ids[]';
    input.value = checkbox.value;
    return input;
  }));
}

document.addEventListener('change', (event) => {
  if (!event.target.matches('input[name="ids[]"]')) return;
  updateSelectedCount();
  refreshActorCampaignSelection();
});

document.addEventListener('click', (event) => {
  if (event.target.matches('[data-select-visible]')) {
    document.querySelectorAll('input[name="ids[]"]').forEach((input) => {
      input.checked = true;
    });
    updateSelectedCount();
    refreshActorCampaignSelection();
  }

  if (event.target.matches('[data-clear-selection]')) {
    document.querySelectorAll('input[name="ids[]"], [data-check-all]').forEach((input) => {
      input.checked = false;
    });
    updateSelectedCount();
    refreshActorCampaignSelection();
  }
});

document.addEventListener('click', (event) => {
  const channelLink = event.target.closest('[data-set-channel]');
  if (channelLink) {
    const select = document.querySelector('[data-channel-select]');
    if (select) select.value = channelLink.dataset.setChannel || 'email';
  }

  const campaignButton = event.target.closest('[data-campaign-channel]');
  if (campaignButton) {
    event.preventDefault();
    if (campaignButton.disabled) return;
    refreshActorCampaignSelection();
    const select = document.querySelector('#mass-send-form [data-channel-select]');
    if (select) {
      select.value = campaignButton.dataset.campaignChannel || 'email';
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }
    const modal = document.getElementById(campaignButton.dataset.modalOpen || 'mass-send-modal');
    if (modal) {
      modal.hidden = false;
      document.body.classList.add('modal-open');
    }
    return;
  }

  const opener = event.target.closest('[data-modal-open]');
  if (opener) {
    const id = opener.dataset.modalOpen || '';
    const modal = id ? document.getElementById(id) : null;
    if (modal) {
      event.preventDefault();
      modal.hidden = false;
      document.body.classList.add('modal-open');
    }
    return;
  }

  if (event.target.closest('[data-modal-close]')) {
    const modal = event.target.closest('.modal');
    if (modal) {
      closeModal(modal);
    }
    return;
  }

  // Click en el backdrop (fuera del .modal-panel) cierra
  const shell = event.target.closest('.modal');
  const inPanel = event.target.closest('.modal-panel');
  if (shell && !inPanel) {
    closeModal(shell);
    return;
  }

  const editBtn = event.target.closest('[data-open-actor]');
  if (editBtn) {
    event.preventDefault();
    openActorModal(editBtn.dataset.openActor, editBtn.dataset.actorType || '');
  }

  const singleSendButton = event.target.closest('[data-open-single-send]');
  if (singleSendButton) {
    event.preventDefault();
    const actorInput = document.getElementById('single-send-actor-id');
    const channelSelect = document.getElementById('single-send-channel');
    const subtitle = document.getElementById('single-send-subtitle');
    const modal = document.getElementById('single-send-modal');
    if (actorInput) actorInput.value = singleSendButton.dataset.openSingleSend || '';
    if (channelSelect) {
      channelSelect.value = singleSendButton.dataset.sendChannel || 'email';
      channelSelect.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (subtitle) subtitle.textContent = `Configura el envío para ${singleSendButton.dataset.actorName || 'el actor'}.`;
    if (modal) {
      modal.hidden = false;
      document.body.classList.add('modal-open');
    }
  }
});

async function openActorModal(id, type) {
  const modal = document.querySelector('[data-actor-modal]');
  if (!modal) return;
  const content = modal.querySelector('[data-actor-modal-content]');
  if (!content) return;

  // Mover el modal a body para evitar problemas de overflow/positioning
  if (modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }

  content.innerHTML = '<div class="modal-loading">Cargando actor...</div>';
  modal.hidden = false;
  document.body.classList.add('modal-open');

  try {
    const params = new URLSearchParams({ action: 'actor-modal', id: String(id) });
    if (type) params.set('type', type);
    const response = await fetch('?' + params.toString(), {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
      credentials: 'same-origin',
    });
    if (!response.ok) throw new Error('HTTP ' + response.status);
    content.innerHTML = await response.text();
    const firstInput = content.querySelector('input:not([type="hidden"])');
    if (firstInput) firstInput.focus();
  } catch (error) {
    content.innerHTML = '<div class="empty-state"><h2>No se pudo cargar el actor</h2><p>Intenta nuevamente en unos segundos.</p></div>';
  }
}

function closeModal(modal) {
  if (!modal) return;
  modal.hidden = true;
  document.body.classList.toggle('modal-open', Boolean(document.querySelector('.modal:not([hidden])')));
}

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return;
  closeModal(document.querySelector('.modal:not([hidden])'));
});

document.addEventListener('DOMContentLoaded', () => {
  document.body.classList.remove('page-loading');
  const params = new URLSearchParams(window.location.search);
  const pathPage = window.location.pathname.split('/').filter(Boolean).pop();
  const page = params.get('page') || (pathPage && pathPage !== 'index.php' ? pathPage : 'dashboard');
  document.querySelectorAll('.sidebar nav a').forEach((link) => {
    const linkParams = new URLSearchParams(link.search);
    const linkPathPage = link.pathname.split('/').filter(Boolean).pop();
    const linkPage = linkParams.get('page') || (linkPathPage && linkPathPage !== 'index.php' ? linkPathPage : 'dashboard');
    link.classList.toggle('active', linkPage === page);
  });

  // Mover todos los .modal a document.body para evitar problemas de overflow/positioning
  document.querySelectorAll('.modal').forEach((modal) => {
    if (modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }
  });
});

window.addEventListener('pageshow', () => {
  document.body.classList.remove('page-loading');
  document.querySelectorAll('.is-loading, .is-loading-panel').forEach((node) => {
    node.classList.remove('is-loading', 'is-loading-panel');
    node.removeAttribute('aria-busy');
  });
});

function startPageLoading() {
  document.body.classList.add('page-loading');
  window.clearTimeout(window.__pageLoadingWatchdog);
  window.__pageLoadingWatchdog = window.setTimeout(() => {
    document.body.classList.remove('page-loading');
  }, 20000);
}

function cleanFormUrl(form) {
  const formData = new FormData(form);
  const page = String(formData.get('page') || '').trim();
  const currentParts = window.location.pathname.split('/').filter(Boolean);
  const currentFile = currentParts[currentParts.length - 1] || '';
  const baseParts = currentFile === 'index.php' || currentFile.includes('.')
    ? currentParts.slice(0, -1)
    : currentParts.slice(0, -1);
  const basePath = `/${baseParts.join('/')}`.replace(/\/$/, '');
  const cleanPath = page && page !== 'dashboard'
    ? `${basePath}/${encodeURIComponent(page)}`.replace(/\/{2,}/g, '/')
    : `${basePath || '/'}`;
  const url = new URL(cleanPath, window.location.origin);
  const params = new URLSearchParams();
  formData.forEach((value, key) => {
    const text = String(value).trim();
    if (text === '' || key === 'p') return;
    if (key === 'page') return;
    params.append(key, text);
  });
  url.search = params.toString();
  return url;
}

async function submitAjaxForm(form) {
  const targetSelector = form.dataset.ajaxTarget;
  const target = targetSelector ? document.querySelector(targetSelector) : null;
  if (!target) {
    window.location.href = cleanFormUrl(form).toString();
    return;
  }

  const url = cleanFormUrl(form);
  form.classList.add('is-loading');
  target.classList.add('is-loading-panel');
  target.setAttribute('aria-busy', 'true');

  try {
    const response = await fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    const html = await response.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const next = doc.querySelector(targetSelector);
    if (!next) throw new Error('Panel no encontrado');
    target.replaceWith(next);
    window.history.pushState({}, '', url);
  } catch (_) {
    window.location.href = url.toString();
  } finally {
    form.classList.remove('is-loading');
  }
}

document.addEventListener('change', (event) => {
  const form = event.target.closest('form[data-autosubmit]');
  if (!form || event.target.matches('input[name="search_ref"]')) return;
  window.clearTimeout(form._autosubmitTimer);
  form._autosubmitTimer = window.setTimeout(() => {
    if (form.matches('[data-ajax-form]')) {
      submitAjaxForm(form);
      return;
    }
    window.location.href = cleanFormUrl(form).toString();
  }, 180);
});

document.addEventListener('change', (event) => {
  if (!event.target.matches('[data-template-picker]')) return;
  const option = event.target.selectedOptions[0];
  const form = event.target.closest('form');
  if (!option || !form) return;

  const subject = form.querySelector('input[name="subject"]');
  const message = form.querySelector('textarea[name="message"]');
  if (subject && option.dataset.subject) subject.value = option.dataset.subject;
  if (message && option.dataset.message) message.value = option.dataset.message;
});

document.addEventListener('change', (event) => {
  if (!event.target.matches('[data-campaign-picker]')) return;
  const form = event.target.closest('form');
  const target = form ? form.querySelector('input[name="campaign_tag"]') : null;
  if (target && event.target.value) target.value = event.target.value;
});

function ensureDateRangePicker() {
  if (window.__dateRangePickerGlobal) return;
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
    if (!activeInput || picker.hidden) return;
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
    const anchor = selectedStart || new Date();
    visibleMonth = new Date(anchor.getFullYear(), anchor.getMonth(), 1);
    picker.hidden = false;
    renderPicker();
    positionPicker();
  };
  const closePicker = () => {
    picker.hidden = true;
    activeInput = null;
  };

  document.addEventListener('focusin', (event) => {
    const input = event.target.closest?.('[data-date-range-filter]');
    if (input) openPicker(input);
  });

  document.addEventListener('click', (event) => {
    const input = event.target.closest?.('[data-date-range-filter]');
    if (input) {
      openPicker(input);
      return;
    }

    if (picker.hidden) return;

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
      return;
    }
    if (!event.target.closest('.date-range-picker')) closePicker();
  });

  window.addEventListener('resize', positionPicker);
  window.addEventListener('scroll', positionPicker, true);
}

if (document.querySelector('[data-date-range-filter]')) {
  ensureDateRangePicker();
}

function initializeHtmlCodeEditor() {
  const host = document.querySelector('[data-html-code-editor]');
  const source = document.querySelector('[data-template-content]');
  if (!host || !source || typeof window.ace === 'undefined') return;
  const editor = window.ace.edit(host);
  window.templateCodeEditor = editor;
  editor.setTheme('ace/theme/tomorrow_night_eighties');
  editor.session.setMode('ace/mode/html');
  editor.session.setUseWrapMode(true);
  editor.session.setTabSize(2);
  editor.session.setUseSoftTabs(true);
  editor.setValue(source.value, -1);
  editor.setOptions({
    fontSize: '14px', showPrintMargin: false, displayIndentGuides: true,
    enableBasicAutocompletion: true, enableLiveAutocompletion: true,
    enableSnippets: true, enableEmmet: true, useWorker: true,
  });
  editor.session.on('change', () => {
    source.value = editor.getValue();
    source.dispatchEvent(new Event('input', { bubbles: true }));
  });
  editor.commands.addCommand({
    name: 'saveTemplate', bindKey: { win: 'Ctrl-S', mac: 'Command-S' },
    exec: () => source.form?.requestSubmit(), readOnly: false,
  });
  editor.resize();
}

function refreshTemplateTools() {
  const textarea = document.querySelector('[data-template-content]');
  if (!textarea) return;
  const count = document.querySelector('[data-char-count]');
  if (count) count.textContent = String(textarea.value.length);
  const type = document.querySelector('[data-template-type]')?.value || 'email';
  const guidance = document.querySelector('[data-template-guidance]');
  const limit = document.querySelector('[data-sms-limit]');
  if (limit) limit.hidden = type !== 'sms';
  if (guidance) {
    guidance.textContent = type === 'sms' && textarea.value.length > 160 ? 'El SMS supera el límite de 160 caracteres.' : (type === 'whatsapp' ? 'WhatsApp admite texto y variables; el asunto no se utiliza.' : type === 'sms' ? `${160 - textarea.value.length} caracteres disponibles.` : 'El contenido HTML se mostrará en la vista previa.');
    guidance.classList.toggle('is-error', type === 'sms' && textarea.value.length > 160);
  }
}

function syncPlainTemplateSource() {
  const plain = document.querySelector('[data-plain-template-content]');
  const source = document.querySelector('[data-template-content]');
  if (!plain || !source) return;
  source.value = plain.value;
  source.dispatchEvent(new Event('input', { bubbles: true }));
}

function renderTemplatePreview() {
  const textarea = document.querySelector('[data-template-content]');
  const preview = document.querySelector('[data-template-preview]');
  if (!textarea || !preview) return;

  const sample = {
    '{{nombre}}': 'María García',
    '{{correo}}': 'maria@cliente.com',
    '{{celular}}': '3001234567',
    '{{campana}}': 'Promoción junio',
    '{{link}}': 'https://sucasa.com.co/inmueble-demo',
  };
  let html = textarea.value || 'Sin contenido para previsualizar.';
  Object.entries(sample).forEach(([token, value]) => {
    html = html.split(token).join(value);
  });
  const type = document.querySelector('[data-template-type]')?.value || 'email';
  if (type !== 'email') { preview.textContent = html; return; }
  const documentFragment = new DOMParser().parseFromString(html, 'text/html');
  documentFragment.querySelectorAll('script,iframe,object,embed').forEach((node) => node.remove());
  documentFragment.querySelectorAll('*').forEach((node) => Array.from(node.attributes).forEach((attr) => {
    if (attr.name.toLowerCase().startsWith('on') || /^javascript:/i.test(attr.value)) node.removeAttribute(attr.name);
  }));
  preview.innerHTML = documentFragment.body.innerHTML;
}

document.addEventListener('input', (event) => {
  if (event.target.matches('[data-plain-template-content]')) {
    syncPlainTemplateSource();
    return;
  }
  if (!event.target.matches('[data-template-content]')) return;
  refreshTemplateTools();
  renderTemplatePreview();
});

document.addEventListener('click', (event) => {
  const tokenButton = event.target.closest('[data-insert-token]');
  if (tokenButton) {
    const textarea = document.querySelector('[data-template-content]');
    if (!textarea) return;
    const token = tokenButton.dataset.insertToken || '';
    const type = document.querySelector('[data-template-type]')?.value || 'email';
    if (type === 'email' && window.templateCodeEditor) {
      window.templateCodeEditor.insert(token);
      window.templateCodeEditor.focus();
      return;
    }
    if (type !== 'email') {
      const plain = document.querySelector('[data-plain-template-content]');
      if (!plain) return;
      const start = plain.selectionStart || 0, end = plain.selectionEnd || 0;
      plain.setRangeText(token, start, end, 'end');
      plain.focus(); syncPlainTemplateSource();
      return;
    }
    const start = textarea.selectionStart || 0;
    const end = textarea.selectionEnd || 0;
    textarea.value = `${textarea.value.slice(0, start)}${token}${textarea.value.slice(end)}`;
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + token.length;
    refreshTemplateTools();
  }

  const wrapButton = event.target.closest('[data-wrap-template]');
  if (wrapButton) {
    const textarea = document.querySelector('[data-template-content]');
    if (!textarea) return;
    const tag = wrapButton.dataset.wrapTemplate;
    if (window.templateCodeEditor) {
      const editor = window.templateCodeEditor;
      const selected = editor.getSelectedText() || (tag === 'a' ? 'Texto del enlace' : 'texto');
      editor.insert((tag === 'a' ? '<a href="https://">' : `<${tag}>`) + selected + `</${tag}>`);
      editor.focus();
      return;
    }
    const start = textarea.selectionStart || 0, end = textarea.selectionEnd || 0;
    const selected = textarea.value.slice(start, end) || (tag === 'a' ? 'Texto del enlace' : 'texto');
    const before = tag === 'a' ? '<a href="https://">' : `<${tag}>`;
    const after = `</${tag}>`;
    textarea.setRangeText(before + selected + after, start, end, 'end');
    textarea.focus(); refreshTemplateTools(); renderTemplatePreview();
  }

  const plainWrapButton = event.target.closest('[data-wrap-plain]');
  if (plainWrapButton) {
    const plain = document.querySelector('[data-plain-template-content]');
    if (!plain) return;
    const marker = plainWrapButton.dataset.wrapPlain || '*';
    const start = plain.selectionStart || 0, end = plain.selectionEnd || 0;
    const selected = plain.value.slice(start, end) || 'texto';
    plain.setRangeText(marker + selected + marker, start, end, 'end');
    plain.focus(); syncPlainTemplateSource();
  }

  const baseButton = event.target.closest('[data-insert-template-base]');
  if (baseButton) {
    const textarea = document.querySelector('[data-template-content]');
    if (!textarea || (textarea.value.trim() && !confirm('¿Reemplazar el contenido actual por esta base?'))) return;
    const bannerUrl = baseButton.dataset.bannerUrl || 'https://sucasainmobiliaria.com.co/wp-content/uploads/jet-form-builder/890b2cf35d4e966d9ecf579e80554389/2026/01/banner-sitio-web-.png';
    const revistaUrl = baseButton.dataset.revistaUrl || '{{link}}';
    const ctaUrl = baseButton.dataset.insertTemplateBase === 'newsletter' ? revistaUrl : '{{link}}';
    const ctaText = baseButton.dataset.insertTemplateBase === 'newsletter' ? 'VER REVISTA' : 'VER MÁS';
    const content = `<tr><td style="padding:40px;text-align:center;color:#334155"><h3 style="color:#061d49;font-size:22px;margin:0 0 20px">Apreciado {{nombre}}</h3><p style="font-size:16px;line-height:1.6;color:#475569;margin:0 0 30px">Escribe aquí el contenido principal de tu comunicación.</p><a href="${ctaUrl}" style="display:inline-block;background:#F59E0B;color:#fff;padding:15px 30px;text-decoration:none;border-radius:6px;font-weight:bold;font-size:16px">${ctaText}</a><div style="height:30px"></div></td></tr>`;
    const footer = `<tr><td style="background:#0f172a;text-align:center;font-size:14px;padding:20px;color:#94a3b8"><p style="margin:0">Una empresa para lograr sus sueños.</p><p style="margin:5px 0 0;font-size:12px">© 2026 Su Casa Inmobiliaria</p></td></tr>`;
    const banner = baseButton.dataset.insertTemplateBase === 'newsletter' ? `<tr><td style="padding:0"><img src="${bannerUrl}" alt="Su Casa Inmobiliaria" style="display:block;width:100%;height:auto"></td></tr>` : '';
    const title = baseButton.dataset.insertTemplateBase === 'newsletter' ? 'Newsletter' : 'Plantilla Corporativa';
    const baseHtml = `<!doctype html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${title}</title></head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,sans-serif">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f8fafc;padding:20px 0"><tr><td align="center">
<table role="presentation" width="700" cellspacing="0" cellpadding="0" border="0" style="max-width:700px;width:100%;background:#fff;margin:30px auto;border-radius:8px;overflow:hidden">${banner}${content}${footer}</table>
</td></tr></table></body></html>`;
    if (window.templateCodeEditor) window.templateCodeEditor.setValue(baseHtml, -1);
    else textarea.value = baseHtml;
    refreshTemplateTools(); renderTemplatePreview();
  }

  if (event.target.closest('[data-format-html]') && window.templateCodeEditor) {
    window.ace.require('ace/ext/beautify').beautify(window.templateCodeEditor.session);
    window.templateCodeEditor.focus();
  }

  if (event.target.matches('[data-preview-template]')) {
    renderTemplatePreview();
  }
});

document.addEventListener('DOMContentLoaded', () => {
  initializeHtmlCodeEditor();
  const plain = document.querySelector('[data-plain-template-content]');
  const source = document.querySelector('[data-template-content]');
  if (plain && source) plain.value = source.value;
  refreshTemplateTools();
  renderTemplatePreview();
});

document.addEventListener('change', (event) => {
  if (event.target.matches('[data-token-select]')) {
    const token = event.target.value;
    if (!token) return;
    const type = document.querySelector('[data-template-type]')?.value || 'email';
    if (type === 'email' && window.templateCodeEditor) {
      window.templateCodeEditor.insert(token);
      window.templateCodeEditor.focus();
    } else {
      const plain = document.querySelector('[data-plain-template-content]');
      if (plain) {
        plain.setRangeText(token, plain.selectionStart || 0, plain.selectionEnd || 0, 'end');
        plain.focus(); syncPlainTemplateSource();
      }
    }
    event.target.value = '';
    return;
  }
  if (!event.target.matches('[data-template-type]')) return;
  const email = event.target.value === 'email';
  const source = document.querySelector('[data-template-content]');
  const plain = document.querySelector('[data-plain-template-content]');
  const emailEditor = document.querySelector('[data-email-editor]');
  const plainEditor = document.querySelector('[data-plain-editor]');
  const channelBadge = document.querySelector('[data-current-template-channel]');
  document.querySelectorAll('[data-email-only]').forEach((el) => { el.hidden = !email; el.querySelectorAll('input').forEach((input) => input.disabled = !email); });
  if (emailEditor) emailEditor.hidden = !email;
  if (plainEditor) plainEditor.hidden = email;
  document.querySelectorAll('[data-whatsapp-only]').forEach((el) => { el.hidden = event.target.value !== 'whatsapp'; });
  if (channelBadge) channelBadge.textContent = event.target.value.toUpperCase();
  if (email) {
    if (window.templateCodeEditor && source && window.templateCodeEditor.getValue() !== source.value) window.templateCodeEditor.setValue(source.value, -1);
    window.setTimeout(() => window.templateCodeEditor?.resize(), 0);
  } else if (plain && source) {
    plain.value = source.value;
    plain.maxLength = event.target.value === 'sms' ? 160 : -1;
    plain.placeholder = event.target.value === 'sms' ? 'Escribe el SMS (máximo 160 caracteres)' : 'Escribe el mensaje de WhatsApp en texto plano';
  }
  refreshTemplateTools(); renderTemplatePreview();
});

document.addEventListener('submit', (event) => {
  if (!event.target.matches('.template-editor')) return;
  const type = document.querySelector('[data-template-type]')?.value || 'email';
  if (type !== 'email') syncPlainTemplateSource();
});

document.addEventListener('DOMContentLoaded', () => {
  const type = document.querySelector('[data-template-type]');
  if (type) type.dispatchEvent(new Event('change', { bubbles: true }));
});

document.addEventListener('click', (event) => {
  const tab = event.target.closest('[data-analytics-tab]');
  if (!tab) return;

  const tabs = tab.closest('[data-analytics-tabs]');
  const panes = document.querySelector('[data-analytics-panes]');
  if (!tabs || !panes) return;

  event.preventDefault();
  const next = tab.dataset.analyticsTab;
  tabs.querySelectorAll('[data-analytics-tab]').forEach((item) => {
    item.classList.toggle('active', item === tab);
  });
  panes.querySelectorAll('[data-analytics-pane]').forEach((pane) => {
    pane.classList.toggle('active', pane.dataset.analyticsPane === next);
  });

  const hiddenTab = document.querySelector('form.filter-panel input[name="tab"]');
  if (hiddenTab) hiddenTab.value = next;

  const url = new URL(tab.href, window.location.href);
  window.history.replaceState({}, '', url);
});

document.addEventListener('submit', async (event) => {
  const form = event.target.closest('[data-compress-banner-form]');
  if (!form || form.dataset.compressing === '1') return;
  const input = form.querySelector('[data-compress-banner-input]');
  const file = input?.files?.[0];
  if (!file || !file.type.startsWith('image/')) return;

  event.preventDefault();
  try {
    const image = new Image();
    const url = URL.createObjectURL(file);
    await new Promise((resolve, reject) => {
      image.onload = resolve;
      image.onerror = reject;
      image.src = url;
    });
    const scale = Math.min(1, 1600 / image.width);
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(image.width * scale));
    canvas.height = Math.max(1, Math.round(image.height * scale));
    canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
    URL.revokeObjectURL(url);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.78));
    if (blob && blob.size < file.size) {
      const compressed = new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
      const transfer = new DataTransfer();
      transfer.items.add(compressed);
      input.files = transfer.files;
    }
  } catch (error) {
    console.warn('No fue posible comprimir el banner antes de subirlo.', error);
  }
  form.dataset.compressing = '1';
  form.requestSubmit();
});

document.addEventListener('submit', (event) => {
  // Los módulos con formularios dinámicos (por ejemplo, Redes sociales)
  // cancelan el envío y resuelven la actualización con fetch.
  if (event.defaultPrevented) return;
  const form = event.target;
  if (form.matches('[data-campaign-analytics-form]')) return;
  if (form.method && form.method.toLowerCase() === 'get') {
    event.preventDefault();
    if (form.matches('[data-ajax-form]')) {
      submitAjaxForm(form);
      return;
    }
    window.location.href = cleanFormUrl(form).toString();
    return;
  }

  form.classList.add('is-loading');
  startPageLoading();
});

function campaignAnalyticsEscape(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));
}

function campaignAnalyticsNumber(value) {
  return Number(value || 0).toLocaleString('es-CO');
}

function campaignAnalyticsPercent(value, total) {
  const number = Number(value || 0);
  const base = Number(total || 0);
  return base > 0 ? Math.round((number / base) * 100) : 0;
}

function campaignAnalyticsRows(rows, columns, emptyText) {
  if (!Array.isArray(rows) || rows.length === 0) {
    return `<div class="campaign-analytics-empty slim"><strong>${campaignAnalyticsEscape(emptyText)}</strong></div>`;
  }
  const header = columns.map((column) => `<th>${campaignAnalyticsEscape(column.label)}</th>`).join('');
  const body = rows.map((row) => `<tr>${columns.map((column) => {
    const value = row[column.key] ?? '';
    const formatted = column.numeric ? campaignAnalyticsNumber(value) : campaignAnalyticsEscape(value || '-');
    return `<td>${formatted}</td>`;
  }).join('')}</tr>`).join('');
  return `<div class="table-wrap campaign-analytics-table"><table><thead><tr>${header}</tr></thead><tbody>${body}</tbody></table></div>`;
}

function renderCampaignAnalyticsResult(payload, target) {
  const data = payload?.data || {};
  const filters = payload?.filters || {};
  const totals = data.totals || {};
  const hits = Number(totals.hits || 0);
  const unique = Number(totals.unique_ips || 0);
  const views = Number(totals.views || 0);
  const events = Number(totals.events || 0);
  const daily = Array.isArray(data.daily) ? data.daily : [];
  const maxDaily = Math.max(1, ...daily.map((row) => Number(row.hits || 0)));
  const dailyBars = daily.length
    ? daily.map((row) => {
      const height = Math.max(5, Math.round((Number(row.hits || 0) / maxDaily) * 100));
      return `<span style="--height:${height}%"><i></i><b>${campaignAnalyticsEscape(row.day || '')}</b><em>${campaignAnalyticsNumber(row.hits)}</em></span>`;
    }).join('')
    : '<div class="campaign-analytics-empty slim"><strong>Sin tendencia para estos filtros.</strong></div>';
  const filterLine = [
    filters.campaign || 'Sin campaña',
    filters.channel || 'Sin canal',
    filters.source || 'Todas las fuentes',
    filters.medium || 'Medio según canal',
  ].map(campaignAnalyticsEscape).join(' · ');

  target.innerHTML = `
    <div class="campaign-analytics-summary">
      <div class="campaign-analytics-context">
        <span>${filterLine}</span>
        <strong>${campaignAnalyticsEscape(totals.first_visit || '-')} / ${campaignAnalyticsEscape(totals.last_visit || '-')}</strong>
      </div>
      <div class="campaign-analytics-metrics">
        <article><span>Total hits</span><strong>${campaignAnalyticsNumber(hits)}</strong></article>
        <article><span>Usuarios únicos</span><strong>${campaignAnalyticsNumber(unique)}</strong></article>
        <article><span>Vistas</span><strong>${campaignAnalyticsNumber(views)}</strong></article>
        <article><span>Eventos</span><strong>${campaignAnalyticsNumber(events)}</strong></article>
        <article><span>Unicidad</span><strong>${campaignAnalyticsPercent(unique, hits)}%</strong></article>
      </div>
      <section class="campaign-analytics-trend" aria-label="Tendencia de hits">
        ${dailyBars}
      </section>
      <div class="campaign-analytics-grid">
        <section>
          <h4>Fuente y medio</h4>
          ${campaignAnalyticsRows(data.sources, [
            { key: 'source', label: 'Fuente' },
            { key: 'medium', label: 'Medio' },
            { key: 'hits', label: 'Hits', numeric: true },
            { key: 'unique_ips', label: 'Únicos', numeric: true },
          ], 'Sin fuentes para estos filtros.')}
        </section>
        <section>
          <h4>Campañas rastreadas</h4>
          ${campaignAnalyticsRows(data.campaigns, [
            { key: 'camp_slug', label: 'Campaña' },
            { key: 'hits', label: 'Hits', numeric: true },
            { key: 'unique_ips', label: 'Únicos', numeric: true },
            { key: 'last_visit', label: 'Última visita' },
          ], 'Sin campañas para estos filtros.')}
        </section>
      </div>
    </div>
  `;
}

document.addEventListener('submit', async (event) => {
  const form = event.target.closest('[data-campaign-analytics-form]');
  if (!form) return;

  event.preventDefault();
  if (!form.reportValidity()) return;

  const workspace = form.closest('.campaign-analytics-workspace');
  const result = workspace?.querySelector('[data-campaign-analytics-result]');
  const button = form.querySelector('button[type="submit"], button:not([type])');
  if (!result) return;

  form.classList.add('is-loading');
  if (button) button.disabled = true;
  result.innerHTML = '<div class="campaign-analytics-loading"><span class="loader-ring"></span><strong>Consultando analítica...</strong></div>';

  try {
    const params = new URLSearchParams(new FormData(form));
    const url = new URL(window.location.href);
    url.search = params.toString();
    const response = await fetch(url.toString(), {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      credentials: 'same-origin',
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.message || 'No fue posible cargar la analítica.');
    renderCampaignAnalyticsResult(payload, result);
  } catch (error) {
    result.innerHTML = `<div class="campaign-analytics-empty is-error"><strong>${campaignAnalyticsEscape(error.message || 'No fue posible cargar la analítica.')}</strong><span>Revisa los filtros e intenta nuevamente.</span></div>`;
  } finally {
    form.classList.remove('is-loading');
    if (button) button.disabled = false;
  }
});

document.addEventListener('click', (event) => {
  const link = event.target.closest('a[href]');
  if (!link || link.target === '_blank' || link.hasAttribute('download') || link.matches('[data-analytics-tab]')) return;
  if (link.closest('[data-campaign-history] .pagination')) return;
  const href = link.getAttribute('href') || '';
  if (href === '' || href.startsWith('#') || href.startsWith('javascript:')) return;
  const url = new URL(link.href, window.location.href);
  if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) return;
  startPageLoading();
});

document.addEventListener('click', (event) => {
  const link = event.target.closest('[data-campaign-history] .pagination a[href]');
  if (!link) return;

  event.preventDefault();
  const target = document.querySelector('[data-campaign-history]');
  if (!target) return;
  target.classList.add('is-loading-panel');
  target.setAttribute('aria-busy', 'true');

  fetch(link.href, {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  })
    .then((response) => response.text())
    .then((html) => {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const next = doc.querySelector('[data-campaign-history]');
      if (!next) throw new Error('Panel no encontrado');
      target.replaceWith(next);
      window.history.pushState({}, '', link.href);
    })
    .catch(() => {
      window.location.href = link.href;
    });
});

const UTM_DEFAULTS = {
  medium: ['WhatsApp', 'Correo Electronico', 'Redes Sociales', 'SMS', 'Publicidad Pagada', 'QR Code', 'Email', 'Display', 'Orgánico', 'Referido'],
  source: ['Facebook', 'Instagram', 'TikTok', 'Google', 'Base de Datos', 'Estado WhatsApp', 'Landing Page', 'Portal Web', 'YouTube', 'LinkedIn'],
};

function analyticsUtmOptions(type) {
  const options = window.GDAUtmOptions && Array.isArray(window.GDAUtmOptions[type])
    ? window.GDAUtmOptions[type]
    : [];
  return options.map((item) => String(item || '').trim()).filter(Boolean);
}

function utmSlug(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
}

function utmOptionStorageKey(type) {
  return `dashboardMarketing.utm.${type}`;
}

function readUtmOptions(type) {
  const defaults = [...(UTM_DEFAULTS[type] || []), ...analyticsUtmOptions(type)];
  let saved = [];
  try {
    saved = JSON.parse(localStorage.getItem(utmOptionStorageKey(type)) || '[]');
  } catch (_) {
    saved = [];
  }
  return [...new Set([...defaults, ...saved].map((item) => String(item || '').trim()).filter(Boolean))];
}

function saveUtmOption(type, value) {
  const clean = String(value || '').trim();
  if (!clean) return;
  const options = readUtmOptions(type);
  if (!options.some((option) => option.toLowerCase() === clean.toLowerCase())) {
    options.push(clean);
  }
  const defaults = [...(UTM_DEFAULTS[type] || []), ...analyticsUtmOptions(type)];
  const custom = options.filter((option) => !defaults.some((base) => base.toLowerCase() === option.toLowerCase()));
  localStorage.setItem(utmOptionStorageKey(type), JSON.stringify(custom));
}

function renderUtmOptions() {
  Object.keys(UTM_DEFAULTS).forEach((type) => {
    const select = document.getElementById(`utm-${type}`);
    if (!select) return;
    const selected = select.value || (type === 'medium' ? 'WhatsApp' : 'Facebook');
    select.replaceChildren(...readUtmOptions(type).map((value) => {
      const option = document.createElement('option');
      option.value = value;
      option.textContent = value;
      return option;
    }));
    if ([...select.options].some((option) => option.value === selected)) {
      select.value = selected;
    }
  });
}

function currentUtmCampaignSlug() {
  const base = String(document.getElementById('utm-campaign')?.value || '').trim();
  return base;
}

function updateUtmPreview() {
  const preview = document.querySelector('[data-utm-preview]');
  const next = currentUtmCampaignSlug();
  if (preview) preview.textContent = next || 'campana';
}

function initializeUtmBuilder() {
  if (!document.querySelector('[data-utm-builder]')) return;
  renderUtmOptions();
  updateUtmPreview(true);
}

document.addEventListener('DOMContentLoaded', initializeUtmBuilder);

document.addEventListener('input', (event) => {
  if (event.target.matches('#utm-campaign')) {
    updateUtmPreview();
  }
});

document.addEventListener('change', (event) => {
  if (event.target.matches('#utm-campaign')) {
    updateUtmPreview();
  }
});

document.addEventListener('click', (event) => {
  const addButton = event.target.closest('[data-add-utm-option]');
  if (addButton) {
    const type = addButton.dataset.addUtmOption;
    const row = document.querySelector(`[data-utm-new-option="${type}"]`);
    const input = document.getElementById(`utm-${type}-new`);
    if (row) {
      row.hidden = !row.hidden;
      if (!row.hidden) input?.focus();
    }
    return;
  }

  const saveButton = event.target.closest('[data-save-utm-option]');
  if (saveButton) {
    const type = saveButton.dataset.saveUtmOption;
    const input = document.getElementById(`utm-${type}-new`);
    const value = input?.value.trim() || '';
    const message = document.getElementById('utm-message');
    if (!value) {
      if (message) {
        message.textContent = 'Escribe una opción antes de guardarla.';
        message.className = 'form-message is-error';
      }
      return;
    }
    saveUtmOption(type, value);
    renderUtmOptions();
    const select = document.getElementById(`utm-${type}`);
    if (select) select.value = value;
    if (input) input.value = '';
    const row = document.querySelector(`[data-utm-new-option="${type}"]`);
    if (row) row.hidden = true;
    if (message) {
      message.textContent = `${value} guardado para próximos enlaces.`;
      message.className = 'form-message is-success';
    }
    return;
  }

  if (event.target.matches('[data-reset-utm]')) {
    ['utm-url', 'utm-campaign', 'utm-result'].forEach((id) => {
      const input = document.getElementById(id);
      if (input) input.value = '';
    });
    const message = document.getElementById('utm-message');
    if (message) {
      message.textContent = '';
      message.className = 'form-message';
    }
    updateUtmPreview(true);
  }
});

document.addEventListener('click', async (event) => {
  if (!event.target.matches('[data-generate-utm]')) return;

  let url = document.getElementById('utm-url')?.value.trim() || '';
  const medium = document.getElementById('utm-medium')?.value.trim() || '';
  const source = document.getElementById('utm-source')?.value.trim() || '';
  const campaign = currentUtmCampaignSlug();
  const result = document.getElementById('utm-result');
  const message = document.getElementById('utm-message');

  if (!result) return;
  if (!url) {
    result.value = '';
    if (message) {
      message.textContent = 'Ingresa una URL destino.';
      message.className = 'form-message is-error';
    }
    return;
  }

  if (!/^https?:\/\//i.test(url)) {
    url = `https://${url}`;
  }

  try {
    const parsedUrl = new URL(url);
    const params = parsedUrl.searchParams;
    if (source) params.set('utm_source', source);
    if (medium) params.set('utm_medium', medium);
    if (campaign) params.set('utm_campaign', campaign);

    result.value = parsedUrl.toString();
    saveUtmOption('medium', medium);
    saveUtmOption('source', source);
    renderUtmOptions();
    result.focus();
    result.select();
    try {
      await navigator.clipboard.writeText(result.value);
      if (message) {
        message.textContent = 'Enlace copiado al portapapeles.';
        message.className = 'form-message is-success';
      }
    } catch (_) {
      if (message) {
        message.textContent = 'Enlace generado. Copialo desde el campo.';
        message.className = 'form-message is-success';
      }
    }
  } catch (_) {
    if (message) {
      message.textContent = 'Revisa la URL destino.';
      message.className = 'form-message is-error';
    }
  }
});
