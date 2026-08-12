function plannerModal() {
  return document.getElementById('planner-post-modal');
}

function focusPlannerModalDate() {
  const modal = plannerModal();
  if (!modal || modal.hidden) return;
  window.setTimeout(() => modal.querySelector('input[name="date"]')?.focus(), 80);
}

function openPlannerModal() {
  const modal = plannerModal();
  if (!modal) return;
  modal.hidden = false;
  document.body.classList.add('modal-open');
  focusPlannerModalDate();
}

function resetPlannerForm(date, time) {
  const modal = plannerModal();
  const form = modal?.querySelector('.planner-form');
  if (!form) return;

  const keepOwner = form.elements.owner?.value || '';
  form.reset();
  if (form.elements.id) form.elements.id.value = '';
  if (form.elements.date) form.elements.date.value = date || new Date().toISOString().slice(0, 10);
  if (form.elements.time) form.elements.time.value = time || '09:00';
  if (form.elements.status) form.elements.status.value = 'planned';
  if (form.elements.channel) form.elements.channel.value = 'instagram';
  if (form.elements.type) form.elements.type.value = 'post';
  if (form.elements.owner) form.elements.owner.value = keepOwner;
  ['campaign', 'title', 'copy', 'asset_url', 'post_url', 'notes'].forEach((name) => {
    if (form.elements[name]) form.elements[name].value = '';
  });
  ['reach', 'views', 'interactions'].forEach((name) => {
    if (form.elements[name]) form.elements[name].value = '0';
  });

  const title = modal.querySelector('#planner-post-title');
  if (title) title.textContent = 'Nueva publicación';
  modal.querySelector('.planner-delete-form')?.setAttribute('hidden', '');
}

function setPlannerView(view) {
  document.querySelectorAll('[data-planner-tab]').forEach((button) => {
    button.classList.toggle('active', button.dataset.plannerTab === view);
  });
  document.querySelectorAll('[data-planner-view]').forEach((panel) => {
    const active = panel.dataset.plannerView === view;
    panel.hidden = !active;
    panel.classList.toggle('active', active);
  });
  window.localStorage?.setItem('planner:view', view);
}

document.addEventListener('click', (event) => {
  const opener = event.target.closest('[data-modal-open="planner-post-modal"]');
  if (opener) {
    resetPlannerForm();
    focusPlannerModalDate();
    return;
  }

  const tab = event.target.closest('[data-planner-tab]');
  if (tab) {
    setPlannerView(tab.dataset.plannerTab || 'calendar');
    return;
  }

  const bestToggle = event.target.closest('[data-best-times-toggle]');
  if (bestToggle) {
    const menu = document.querySelector('[data-best-times-menu]');
    if (!menu) return;
    const nextHidden = !menu.hidden;
    menu.hidden = nextHidden;
    bestToggle.setAttribute('aria-expanded', String(!nextHidden));
    return;
  }

  const bestChannel = event.target.closest('[data-best-channel]');
  if (bestChannel) {
    const calendar = document.querySelector('.planner-week-calendar');
    const menu = document.querySelector('[data-best-times-menu]');
    const label = document.querySelector('[data-best-times-toggle]');
    const channel = bestChannel.dataset.bestChannel || '';
    calendar?.classList.toggle('best-times-off', channel === '');
    calendar?.classList.toggle('hide-percent', channel === '' || !document.querySelector('[data-best-percent]')?.checked);
    if (label) label.textContent = channel ? `Mejores horas: ${bestChannel.textContent.trim()}` : 'Ver mejores horas';
    if (menu) menu.hidden = true;
    return;
  }

  if (!event.target.closest('.planner-best-times')) {
    const menu = document.querySelector('[data-best-times-menu]');
    const label = document.querySelector('[data-best-times-toggle]');
    if (menu) menu.hidden = true;
    if (label) label.setAttribute('aria-expanded', 'false');
  }

  const slot = event.target.closest('[data-planner-slot]');
  if (slot) {
    const day = slot.closest('[data-planner-date]');
    resetPlannerForm(day?.dataset.plannerDate || '', slot.dataset.time || '09:00');
    openPlannerModal();
  }
});

document.addEventListener('change', (event) => {
  const percentToggle = event.target.closest('[data-best-percent]');
  if (!percentToggle) return;
  document.querySelector('.planner-week-calendar')?.classList.toggle('hide-percent', !percentToggle.checked);
});

document.addEventListener('DOMContentLoaded', () => {
  const savedView = window.localStorage?.getItem('planner:view');
  if (savedView && document.querySelector(`[data-planner-view="${savedView}"]`)) {
    setPlannerView(savedView);
  }

  const modal = plannerModal();
  if (!modal || modal.hidden) return;
  document.body.classList.add('modal-open');
  focusPlannerModalDate();
});
