(() => {
  const view = document.querySelector("[data-profile-view]");

  if (!view) {
    return;
  }

  const loadingState = view.querySelector("[data-profile-loading]");
  const errorState   = view.querySelector("[data-profile-error]");
  const content      = view.querySelector("[data-profile-content]");
  const initialsEl   = view.querySelector("[data-profile-initials]");
  const avatarImg    = view.querySelector("[data-profile-avatar-img]");
  const roleEl       = view.querySelector("[data-profile-role]");
  const nameEl       = view.querySelector("[data-profile-name]");
  const summaryEl    = view.querySelector("[data-profile-summary]");
  const usernameEl   = view.querySelector("[data-profile-username]");
  const emailEl      = view.querySelector("[data-profile-email]");
  const statusEl     = view.querySelector("[data-profile-status]");
  const plansEl      = view.querySelector("[data-profile-plans]");
  const favoritesEl  = view.querySelector("[data-profile-favorites]");
  const recipesEl    = view.querySelector("[data-profile-recipes]");
  const detailsEl    = view.querySelector("[data-profile-details]");
  const activityEl   = view.querySelector("[data-profile-activity]");

  const roleLabels = {
    admin:    "Admin",
    owner:    "Właściciel",
    employee: "Pracownik",
    user:     "Użytkownik",
  };

  const statusLabels = {
    active:   "Aktywne konto",
    pending:  "Konto oczekuje",
    inactive: "Konto nieaktywne",
  };

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function createDetail(label, value) {
    return `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`;
  }

  function renderDetails(profile) {
    const rows = [
      ["Nazwa użytkownika", `@${profile.username}`],
      ["E-mail", profile.email],
      ["Widoczność profilu", profile.isPublic ? "Publiczny" : "Prywatny"],
      ["Data dołączenia", profile.joinedAt ?? "—"],
      ["Ostatnie logowanie", profile.lastLogin ?? "—"],
    ];

    if (profile.bio) {
      rows.push(["Bio", profile.bio]);
    }

    if (detailsEl) detailsEl.innerHTML = rows.map(([l, v]) => createDetail(l, v)).join("");
  }

  function renderActivity(items) {
    if (!activityEl) return;
    if (!items || items.length === 0) {
      activityEl.innerHTML = `<li class="profile-activity-empty">Brak zarejestrowanej aktywności.</li>`;
      return;
    }
    activityEl.innerHTML = items.map((item) => `
      <li>
        <span aria-hidden="true"></span>
        <div>
          <strong>${escapeHtml(item.title)}</strong>
          <p>${escapeHtml(item.description ?? "")}</p>
          <small>${escapeHtml(item.time ?? "")}</small>
        </div>
      </li>
    `).join("");
  }

  function render(profile) {
    if (profile.avatarUrl && avatarImg) {
      avatarImg.src    = profile.avatarUrl;
      avatarImg.hidden = false;
      if (initialsEl) initialsEl.hidden = true;
    } else {
      if (initialsEl) { initialsEl.textContent = profile.initials ?? ""; initialsEl.hidden = false; }
      if (avatarImg) avatarImg.hidden = true;
    }

    if (roleEl)     roleEl.textContent     = roleLabels[profile.role] ?? profile.role;
    if (nameEl)     nameEl.textContent     = profile.name ?? "";
    if (summaryEl)  summaryEl.textContent  = profile.bio ?? "";
    if (usernameEl) usernameEl.textContent = `@${profile.username}`;
    if (emailEl)    emailEl.textContent    = profile.email ?? "";
    if (statusEl)   statusEl.textContent   = statusLabels[profile.status] ?? profile.status;

    const stats = profile.stats ?? {};
    if (plansEl)     plansEl.textContent     = stats.plannedMeals    ?? 0;
    if (favoritesEl) favoritesEl.textContent = stats.favoriteRecipes ?? 0;
    if (recipesEl)   recipesEl.textContent   = stats.ownRecipes      ?? 0;

    renderDetails(profile);
    renderActivity(profile.activity ?? []);
  }

  async function loadProfile() {
    try {
      const response = await fetch("/api/profile");

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const profile = await response.json();
      render(profile);
      loadingState.hidden = true;
      errorState.hidden   = true;
      content.hidden      = false;
    } catch {
      loadingState.hidden = true;
      errorState.hidden   = false;
      content.hidden      = true;
    }
  }

  loadProfile();
})();
