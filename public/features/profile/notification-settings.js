(() => {
  const view = document.querySelector("[data-notification-view]");
  const settingsUrl = "/public/features/profile/notification_settings_mock.json";

  if (!view) {
    return;
  }

  const loadingState = view.querySelector("[data-notification-loading]");
  const errorState = view.querySelector("[data-notification-error]");
  const content = view.querySelector("[data-notification-content]");
  const email = view.querySelector("[data-notification-email]");
  const summary = view.querySelector("[data-notification-summary]");
  const enabledCount = view.querySelector("[data-notification-enabled-count]");
  const quietHours = view.querySelector("[data-notification-quiet-hours]");
  const list = view.querySelector("[data-notification-list]");
  const quietFrom = view.querySelector("[data-quiet-from]");
  const quietTo = view.querySelector("[data-quiet-to]");
  const form = view.querySelector("[data-notification-form]");
  const message = view.querySelector("[data-notification-message]");
  let settings = null;

  const channelLabels = {
    email: "E-mail",
    app: "Aplikacja",
  };

  function escapeHtml(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function updateSummary() {
    const active = settings.notifications.filter((item) => item.enabled).length;
    enabledCount.textContent = `${active} z ${settings.notifications.length}`;
    quietHours.textContent = `${settings.quietHours.from} - ${settings.quietHours.to}`;
  }

  function createNotificationItem(item) {
    const checked = item.enabled ? "checked" : "";
    const disabled = item.locked ? "disabled" : "";
    const lockedText = item.locked ? "<small>Wymagane dla bezpieczeństwa konta</small>" : "";

    return `
      <article class="notification-card" data-notification-id="${escapeHtml(item.id)}">
        <div>
          <span class="notification-channel">${escapeHtml(channelLabels[item.channel] ?? item.channel)}</span>
          <h3>${escapeHtml(item.title)}</h3>
          <p>${escapeHtml(item.description)}</p>
          ${lockedText}
        </div>
        <label class="notification-toggle">
          <span class="visually-hidden">${escapeHtml(item.title)}</span>
          <input type="checkbox" ${checked} ${disabled} data-notification-toggle />
          <span aria-hidden="true"></span>
        </label>
      </article>
    `;
  }

  function render() {
    email.textContent = settings.email;
    summary.textContent = settings.summary;
    quietFrom.value = settings.quietHours.from;
    quietTo.value = settings.quietHours.to;
    list.innerHTML = settings.notifications.map(createNotificationItem).join("");
    updateSummary();
  }

  async function loadSettings() {
    try {
      const response = await fetch(settingsUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      settings = await response.json();
      render();
      loadingState.hidden = true;
      errorState.hidden = true;
      content.hidden = false;
    } catch (error) {
      loadingState.hidden = true;
      errorState.hidden = false;
      content.hidden = true;
    }
  }

  list.addEventListener("change", (event) => {
    const toggle = event.target.closest("[data-notification-toggle]");

    if (!toggle) {
      return;
    }

    const itemId = toggle.closest("[data-notification-id]")?.dataset.notificationId;
    const item = settings.notifications.find((entry) => entry.id === itemId);

    if (item) {
      item.enabled = toggle.checked;
      updateSummary();
    }
  });

  quietFrom?.addEventListener("change", () => {
    settings.quietHours.from = quietFrom.value;
    updateSummary();
  });

  quietTo?.addEventListener("change", () => {
    settings.quietHours.to = quietTo.value;
    updateSummary();
  });

  form?.addEventListener("submit", (event) => {
    event.preventDefault();
    message.textContent = "Ustawienia powiadomień zapisane lokalnie.";
    message.hidden = false;
    window.setTimeout(() => {
      message.hidden = true;
    }, 2200);
  });

  loadSettings();
})();
