(() => {
  const view = document.querySelector("[data-notification-view]");

  if (!view) {
    return;
  }

  const loadingState   = view.querySelector("[data-notification-loading]");
  const errorState     = view.querySelector("[data-notification-error]");
  const content        = view.querySelector("[data-notification-content]");
  const emailEl        = view.querySelector("[data-notification-email]");
  const summaryEl      = view.querySelector("[data-notification-summary]");
  const enabledCount   = view.querySelector("[data-notification-enabled-count]");
  const quietHoursEl   = view.querySelector("[data-notification-quiet-hours]");
  const list           = view.querySelector("[data-notification-list]");
  const quietFrom      = view.querySelector("[data-quiet-from]");
  const quietTo        = view.querySelector("[data-quiet-to]");
  const form           = view.querySelector("[data-notification-form]");

  const NOTIFICATION_TYPES = [
    { id: "mealRemindersEmail",    channel: "email", title: "Przypomnienie o planowaniu",       description: "Cotygodniowe przypomnienie o uzupełnieniu planu posiłków.", locked: false },
    { id: "groceryRemindersEmail", channel: "email", title: "Przypomnienie o zakupach",         description: "Powiadomienie gdy lista zakupów jest gotowa do realizacji.", locked: false },
    { id: "recipeReviewApp",       channel: "app",   title: "Zmiana statusu przepisu",          description: "Powiadomienie gdy przepis zostanie zatwierdzony, odrzucony lub wymaga poprawek.", locked: false },
    { id: "teamActivityApp",       channel: "app",   title: "Aktywność zespołu",                description: "Nowe zaproszenia i zmiany ról w Twoim zespole.", locked: false },
    { id: "accountSecurityEmail",  channel: "email", title: "Bezpieczeństwo konta",             description: "Alerty przy zmianie hasła lub adresu e-mail.", locked: true },
  ];

  const CHANNEL_LABELS = { email: "E-mail", app: "Aplikacja" };

  let prefs = {};

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function updateSummary() {
    const active = NOTIFICATION_TYPES.filter((t) => prefs[t.id]).length;
    if (enabledCount) enabledCount.textContent = `${active} z ${NOTIFICATION_TYPES.length}`;
    if (quietHoursEl) quietHoursEl.textContent = `${prefs.quietHoursStart ?? "22:00"} – ${prefs.quietHoursEnd ?? "07:00"}`;
  }

  function createItem(type) {
    const checked  = prefs[type.id] ? "checked" : "";
    const disabled = type.locked ? "disabled" : "";
    const lockedText = type.locked ? `<small>Wymagane dla bezpieczeństwa konta</small>` : "";
    return `
      <article class="notification-card" data-notification-id="${escapeHtml(type.id)}">
        <div>
          <span class="notification-channel">${escapeHtml(CHANNEL_LABELS[type.channel] ?? type.channel)}</span>
          <h3>${escapeHtml(type.title)}</h3>
          <p>${escapeHtml(type.description)}</p>
          ${lockedText}
        </div>
        <label class="notification-toggle">
          <span class="visually-hidden">${escapeHtml(type.title)}</span>
          <input type="checkbox" ${checked} ${disabled} data-notification-toggle />
          <span aria-hidden="true"></span>
        </label>
      </article>
    `;
  }

  function render(accountEmail) {
    if (emailEl) emailEl.textContent = accountEmail ?? "Twój e-mail";
    if (summaryEl) summaryEl.textContent = "Zarządzaj, kiedy i jak MealPlanner wysyła Ci powiadomienia.";
    if (quietFrom) quietFrom.value = prefs.quietHoursStart ?? "22:00";
    if (quietTo)   quietTo.value   = prefs.quietHoursEnd   ?? "07:00";
    if (list) list.innerHTML = NOTIFICATION_TYPES.map(createItem).join("");
    updateSummary();
  }

  async function loadSettings() {
    try {
      const [notifRes, accountRes] = await Promise.all([
        fetch("/api/settings/notifications"),
        fetch("/api/settings/account"),
      ]);

      if (!notifRes.ok) throw new Error(`HTTP ${notifRes.status}`);

      const notifData   = await notifRes.json();
      const accountData = accountRes.ok ? await accountRes.json() : {};

      prefs = {
        mealRemindersEmail:    notifData.meal_reminders_email    ?? true,
        groceryRemindersEmail: notifData.grocery_reminders_email ?? true,
        recipeReviewApp:       notifData.recipe_review_app       ?? true,
        teamActivityApp:       notifData.team_activity_app       ?? false,
        accountSecurityEmail:  notifData.account_security_email  ?? true,
        quietHoursStart:       notifData.quiet_hours_start       ?? "22:00",
        quietHoursEnd:         notifData.quiet_hours_end         ?? "07:00",
      };

      render(accountData.email);
      loadingState.hidden = true;
      errorState.hidden   = true;
      content.hidden      = false;
    } catch {
      loadingState.hidden = true;
      errorState.hidden   = false;
      content.hidden      = true;
    }
  }

  list?.addEventListener("change", (event) => {
    const toggle = event.target.closest("[data-notification-toggle]");
    if (!toggle) return;
    const typeId = toggle.closest("[data-notification-id]")?.dataset.notificationId;
    if (typeId) {
      prefs[typeId] = toggle.checked;
      updateSummary();
    }
  });

  quietFrom?.addEventListener("change", () => { prefs.quietHoursStart = quietFrom.value; updateSummary(); });
  quietTo?.addEventListener("change",   () => { prefs.quietHoursEnd   = quietTo.value;   updateSummary(); });

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (prefs.quietHoursStart === prefs.quietHoursEnd) {
      if (window.toast) window.toast.warning("Ciche godziny obejmują całą dobę.");
    }

    try {
      const res = await fetch("/api/settings/notifications", {
        method:  "PATCH",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
          mealRemindersEmail:    prefs.mealRemindersEmail,
          groceryRemindersEmail: prefs.groceryRemindersEmail,
          recipeReviewApp:       prefs.recipeReviewApp,
          teamActivityApp:       prefs.teamActivityApp,
          accountSecurityEmail:  prefs.accountSecurityEmail,
          quietHoursStart:       prefs.quietHoursStart,
          quietHoursEnd:         prefs.quietHoursEnd,
        }),
      });

      if (res.ok) {
        if (window.toast) window.toast.success("Ustawienia powiadomień zapisane.");
      } else {
        if (window.toast) window.toast.error("Nie udało się zapisać ustawień. Spróbuj ponownie.");
      }
    } catch {
      if (window.toast) window.toast.error("Błąd połączenia z serwerem.");
    }
  });

  loadSettings();
})();
