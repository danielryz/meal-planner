(() => {
  const DURATION = 4000;

  const ICONS = {
    success: `<svg class="toast__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M6.5 10.5l2.5 2.5 4.5-4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    error:   `<svg class="toast__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="14" r="0.75" fill="currentColor"/></svg>`,
    warning: `<svg class="toast__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M10 3L18 17H2L10 3Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 8v4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="14" r="0.75" fill="currentColor"/></svg>`,
    info:    `<svg class="toast__icon" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/><path d="M10 9v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="10" cy="6.5" r="0.75" fill="currentColor"/></svg>`,
  };

  function getContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      container.setAttribute('aria-live', 'polite');
      container.setAttribute('aria-atomic', 'false');
      document.body.appendChild(container);
    }
    return container;
  }

  function dismiss(el) {
    el.classList.add('toast--leaving');
    el.addEventListener('animationend', () => el.remove(), { once: true });
  }

  function show(type, message) {
    const container = getContainer();

    const el = document.createElement('div');
    el.className = `toast toast--${type}`;
    el.setAttribute('role', 'status');
    el.innerHTML = `
      ${ICONS[type] ?? ''}
      <div class="toast__body">
        <p class="toast__message">${message}</p>
      </div>
      <button class="toast__close" aria-label="Zamknij powiadomienie">&times;</button>
    `;

    el.querySelector('.toast__close').addEventListener('click', () => dismiss(el));
    container.appendChild(el);

    const timer = setTimeout(() => dismiss(el), DURATION);
    el.addEventListener('mouseenter', () => clearTimeout(timer));
  }

  window.toast = {
    success: (msg) => show('success', msg),
    error:   (msg) => show('error', msg),
    warning: (msg) => show('warning', msg),
    info:    (msg) => show('info', msg),
  };
})();
