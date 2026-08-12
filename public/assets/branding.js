(function () {
  'use strict';

  const modal = document.querySelector('[data-branding-modal]');
  const form = document.querySelector('[data-branding-form]');
  let restoreFocus = null;
  let submitting = false;
  let initialState = '';

  function serializeForm() {
    if (!form) return '';
    const data = [];
    form.querySelectorAll('input, select, textarea').forEach((field) => {
      if (!field.name || field.name === '_token' || field.name === 'action' || field.type === 'file') return;
      if ((field.type === 'checkbox' || field.type === 'radio') && !field.checked) return;
      data.push([field.name, field.value]);
    });
    return JSON.stringify(data);
  }

  function openModal(trigger) {
    if (!modal) return;
    restoreFocus = trigger || document.activeElement;
    modal.hidden = false;
    document.body.classList.add('branding-modal-open');
    requestAnimationFrame(() => {
      modal.classList.add('is-open');
      const initial = modal.querySelector('[data-branding-initial-focus]');
      if (initial) initial.focus();
    });
    initialState = serializeForm();
  }

  function closeModal(force) {
    if (!modal) return;
    const isDirty = form && serializeForm() !== initialState;
    if (!force && !submitting && isDirty && !window.confirm('Hay cambios sin guardar. ¿Quieres cerrar el formulario?')) return;
    modal.classList.remove('is-open');
    document.body.classList.remove('branding-modal-open');
    window.setTimeout(() => {
      modal.hidden = true;
      if (restoreFocus && typeof restoreFocus.focus === 'function') restoreFocus.focus();
    }, 180);
  }

  document.querySelectorAll('[data-open-branding-modal]').forEach((button) => {
    button.addEventListener('click', () => openModal(button));
  });

  document.querySelectorAll('[data-close-branding-modal]').forEach((button) => {
    button.addEventListener('click', () => closeModal(false));
  });

  if (modal && !modal.hidden) openModal(null);

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal && !modal.hidden) closeModal(false);
  });

  document.querySelectorAll('[data-confirm-branding]').forEach((archiveForm) => {
    archiveForm.addEventListener('submit', (event) => {
      const message = archiveForm.getAttribute('data-confirm-branding') || '¿Continuar?';
      if (!window.confirm(message)) event.preventDefault();
    });
  });

  const fileInput = document.querySelector('[data-branding-file]');
  const fileName = document.querySelector('[data-branding-file-name]');
  if (fileInput && fileName) {
    fileInput.addEventListener('change', () => {
      const file = fileInput.files && fileInput.files[0];
      if (!file) return;
      if (file.size > 50 * 1024 * 1024) {
        fileInput.value = '';
        fileName.textContent = 'El archivo supera el máximo de 50 MB.';
        fileName.classList.add('is-error');
        fileInput.focus();
        return;
      }
      fileName.classList.remove('is-error');
      fileName.textContent = file.name + ' · ' + (file.size / 1048576).toFixed(1) + ' MB';
    });
  }

  const compliance = document.querySelector('[data-branding-compliance]');
  const complianceOutput = document.querySelector('[data-branding-compliance-output]');
  if (compliance && complianceOutput) {
    compliance.addEventListener('input', () => {
      complianceOutput.value = compliance.value + '%';
      complianceOutput.textContent = compliance.value + '%';
    });
  }

  if (form) {
    form.addEventListener('submit', (event) => {
      const checkedChannels = form.querySelectorAll('input[name="channels[]"]:checked');
      if (checkedChannels.length === 0) {
        event.preventDefault();
        const firstChannel = form.querySelector('input[name="channels[]"]');
        if (firstChannel) firstChannel.focus();
        window.alert('Selecciona al menos un canal de uso.');
        return;
      }
      submitting = true;
      const submit = form.querySelector('[data-branding-submit]');
      if (submit) {
        submit.disabled = true;
        submit.textContent = 'Guardando pieza…';
      }
    });
  }

  const postLinkModal = document.querySelector('[data-post-link-modal]');
  const postLinkForm = document.querySelector('[data-post-link-form]');
  const postLinkSearch = document.querySelector('[data-post-link-search]');
  const postLinkPlatform = document.querySelector('[data-post-link-platform]');
  const postOptions = Array.from(document.querySelectorAll('[data-post-option]'));
  const postCheckboxes = Array.from(document.querySelectorAll('[data-post-checkbox]'));
  let postLinkRestoreFocus = null;
  let activeAssetId = 0;
  let initialPostSelection = '';
  let postLinkSubmitting = false;

  function selectedPostIds() {
    return postCheckboxes
      .filter((checkbox) => checkbox.checked && !checkbox.disabled)
      .map((checkbox) => Number(checkbox.value))
      .sort((a, b) => a - b)
      .join(',');
  }

  function updatePostLinkCount() {
    const selected = postCheckboxes.filter((checkbox) => checkbox.checked && !checkbox.disabled).length;
    const output = document.querySelector('[data-post-link-selected-count]');
    if (output) output.textContent = String(selected);
  }

  function filterPostOptions() {
    const query = (postLinkSearch ? postLinkSearch.value : '').trim().toLocaleLowerCase('es');
    const platform = postLinkPlatform ? postLinkPlatform.value : '';
    let visible = 0;
    postOptions.forEach((option) => {
      const matchesQuery = query === '' || option.textContent.toLocaleLowerCase('es').includes(query);
      const matchesPlatform = platform === '' || option.dataset.platform === platform;
      option.hidden = !(matchesQuery && matchesPlatform);
      if (!option.hidden) visible += 1;
    });
    const count = document.querySelector('[data-post-link-visible-count]');
    const empty = document.querySelector('[data-post-link-empty]');
    if (count) count.textContent = visible + (visible === 1 ? ' mostrada' : ' mostradas');
    if (empty) empty.hidden = visible !== 0;
  }

  function openPostLinkModal(trigger) {
    if (!postLinkModal || !postLinkForm || !trigger) return;
    activeAssetId = Number(trigger.dataset.assetId || 0);
    if (!activeAssetId) return;
    postLinkRestoreFocus = trigger;
    const selectedIds = new Set(JSON.parse(trigger.dataset.linkedPostIds || '[]').map(Number));
    const idInput = postLinkForm.querySelector('[data-post-link-asset-id]');
    const assetName = postLinkModal.querySelector('[data-post-link-asset-name]');
    if (idInput) idInput.value = String(activeAssetId);
    if (assetName) assetName.textContent = trigger.dataset.assetTitle || 'esta pieza';

    postCheckboxes.forEach((checkbox) => {
      const ownerId = Number(checkbox.dataset.linkedAssetId || 0);
      const belongsElsewhere = ownerId > 0 && ownerId !== activeAssetId;
      const option = checkbox.closest('[data-post-option]');
      const owner = option ? option.querySelector('[data-post-owner]') : null;
      checkbox.disabled = belongsElsewhere;
      checkbox.checked = !belongsElsewhere && selectedIds.has(Number(checkbox.value));
      if (option) option.classList.toggle('is-unavailable', belongsElsewhere);
      if (owner) {
        owner.textContent = belongsElsewhere
          ? 'Ya vinculada a “' + (checkbox.dataset.linkedAssetTitle || 'otra pieza') + '”'
          : (checkbox.checked ? 'Vinculada actualmente a esta pieza' : '');
      }
    });

    if (postLinkSearch) postLinkSearch.value = '';
    if (postLinkPlatform) postLinkPlatform.value = '';
    filterPostOptions();
    updatePostLinkCount();
    initialPostSelection = selectedPostIds();
    postLinkModal.hidden = false;
    document.body.classList.add('branding-modal-open');
    requestAnimationFrame(() => {
      postLinkModal.classList.add('is-open');
      if (postLinkSearch) postLinkSearch.focus();
    });
  }

  function closePostLinkModal(force) {
    if (!postLinkModal || postLinkModal.hidden) return;
    const changed = selectedPostIds() !== initialPostSelection;
    if (!force && !postLinkSubmitting && changed && !window.confirm('Hay cambios de vínculos sin guardar. ¿Quieres cerrar el selector?')) return;
    postLinkModal.classList.remove('is-open');
    document.body.classList.remove('branding-modal-open');
    window.setTimeout(() => {
      postLinkModal.hidden = true;
      if (postLinkRestoreFocus && typeof postLinkRestoreFocus.focus === 'function') postLinkRestoreFocus.focus();
    }, 180);
  }

  document.querySelectorAll('[data-open-post-link]').forEach((button) => {
    button.addEventListener('click', () => openPostLinkModal(button));
  });

  document.querySelectorAll('[data-close-post-link]').forEach((button) => {
    button.addEventListener('click', () => closePostLinkModal(false));
  });

  postCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', () => {
    const owner = checkbox.closest('[data-post-option]')?.querySelector('[data-post-owner]');
    if (owner && Number(checkbox.dataset.linkedAssetId || 0) === activeAssetId) {
      owner.textContent = checkbox.checked ? 'Vinculada actualmente a esta pieza' : '';
    }
    updatePostLinkCount();
  }));

  if (postLinkSearch) postLinkSearch.addEventListener('input', filterPostOptions);
  if (postLinkPlatform) postLinkPlatform.addEventListener('change', filterPostOptions);

  if (postLinkForm) {
    postLinkForm.addEventListener('submit', () => {
      postLinkSubmitting = true;
      const submit = postLinkForm.querySelector('[data-post-link-submit]');
      if (submit) {
        submit.disabled = true;
        submit.textContent = 'Guardando vínculos…';
      }
    });
  }

  document.addEventListener('keydown', (event) => {
    if (!postLinkModal || postLinkModal.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closePostLinkModal(false);
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = Array.from(postLinkModal.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled])'))
      .filter((element) => !element.closest('[hidden]'));
    if (focusable.length === 0) return;
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

  if (postLinkModal) {
    const autoOpenAsset = Number(postLinkModal.dataset.autoOpenAsset || 0);
    if (autoOpenAsset > 0) {
      const trigger = Array.from(document.querySelectorAll('[data-open-post-link]'))
        .find((button) => Number(button.dataset.assetId || 0) === autoOpenAsset);
      if (trigger) openPostLinkModal(trigger);
    }
  }

  const detailModal = document.querySelector('[data-branding-detail-modal]');
  const detailContent = detailModal?.querySelector('[data-branding-detail-content]');
  const detailTitle = detailModal?.querySelector('[data-branding-detail-title]');
  const detailFile = detailModal?.querySelector('[data-branding-detail-file]');
  let detailRestoreFocus = null;

  function hydrateDetailMedia() {
    if (!detailContent) return;
    detailContent.querySelectorAll('[data-branding-detail-src]').forEach((element) => {
      const source = element.dataset.brandingDetailSrc || '';
      if (!source) return;
      element.setAttribute('src', source);
      if (element instanceof HTMLVideoElement) element.load();
    });
  }

  function openDetailModal(trigger) {
    if (!detailModal || !detailContent || !trigger) return;
    const templateId = trigger.dataset.detailTemplate || '';
    const template = templateId ? document.getElementById(templateId) : null;
    if (!(template instanceof HTMLTemplateElement)) return;

    detailRestoreFocus = trigger;
    if (detailTitle) detailTitle.textContent = trigger.dataset.detailTitle || 'Pieza de branding';
    if (detailFile) {
      detailFile.textContent = (trigger.dataset.detailFile || 'Arte final') + ' · vista e información completa';
    }
    detailContent.replaceChildren(template.content.cloneNode(true));
    hydrateDetailMedia();
    detailModal.hidden = false;
    document.body.classList.add('branding-modal-open');
    requestAnimationFrame(() => {
      detailModal.classList.add('is-open');
      detailModal.querySelector('[data-close-branding-detail]')?.focus();
    });
  }

  function closeDetailModal() {
    if (!detailModal || detailModal.hidden) return;
    detailContent?.querySelectorAll('video').forEach((video) => video.pause());
    detailModal.classList.remove('is-open');
    document.body.classList.remove('branding-modal-open');
    window.setTimeout(() => {
      detailModal.hidden = true;
      detailContent?.replaceChildren();
      if (detailRestoreFocus && typeof detailRestoreFocus.focus === 'function') detailRestoreFocus.focus();
    }, 180);
  }

  document.querySelectorAll('[data-open-branding-detail]').forEach((button) => {
    button.addEventListener('click', () => openDetailModal(button));
  });

  detailModal?.querySelectorAll('[data-close-branding-detail]').forEach((button) => {
    button.addEventListener('click', closeDetailModal);
  });

  document.addEventListener('keydown', (event) => {
    if (!detailModal || detailModal.hidden) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeDetailModal();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = Array.from(detailModal.querySelectorAll('button:not([disabled]), a[href], iframe, video[controls]'))
      .filter((element) => !element.closest('[hidden]'));
    if (focusable.length === 0) return;
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
})();
