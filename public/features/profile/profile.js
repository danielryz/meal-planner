(() => {
  const view = document.querySelector("[data-profile-view]");
  const profileUrl = "/public/features/profile/profile_mock.json";

  if (!view) {
    return;
  }

  const loadingState = view.querySelector("[data-profile-loading]");
  const errorState = view.querySelector("[data-profile-error]");
  const content = view.querySelector("[data-profile-content]");
  const initials = view.querySelector("[data-profile-initials]");
  const role = view.querySelector("[data-profile-role]");
  const name = view.querySelector("[data-profile-name]");
  const summary = view.querySelector("[data-profile-summary]");
  const username = view.querySelector("[data-profile-username]");
  const email = view.querySelector("[data-profile-email]");
  const status = view.querySelector("[data-profile-status]");
  const plans = view.querySelector("[data-profile-plans]");
  const favorites = view.querySelector("[data-profile-favorites]");
  const recipes = view.querySelector("[data-profile-recipes]");
  const details = view.querySelector("[data-profile-details]");
  const activity = view.querySelector("[data-profile-activity]");

  const roleLabels = {
    admin: "Admin",
    owner: "Właściciel",
    employee: "Pracownik",
    user: "Użytkownik",
  };

  const statusLabels = {
    active: "Aktywne konto",
    pending: "Konto oczekuje",
    inactive: "Konto nieaktywne",
  };

  const visibilityLabels = {
    private: "Prywatny",
    public: "Publiczny",
  };

  function escapeHtml(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function createDetail(label, value) {
    return `
      <div>
        <dt>${escapeHtml(label)}</dt>
        <dd>${escapeHtml(value)}</dd>
      </div>
    `;
  }

  function renderDetails(profile) {
    const rows = [
      ["Nazwa użytkownika", `@${profile.username}`],
      ["E-mail", profile.email],
      ["Widoczność profilu", visibilityLabels[profile.visibility] ?? profile.visibility],
      ["Data dołączenia", profile.joinedAt],
      ["Ostatnie logowanie", profile.lastLogin],
      ["Dieta", profile.preferences.diet],
      ["Domyślne porcje", profile.preferences.servings],
      ["Alergie", profile.preferences.allergies],
      ["Powiadomienia", profile.preferences.notificationSummary],
    ];

    details.innerHTML = rows.map(([label, value]) => createDetail(label, value)).join("");
  }

  function renderActivity(items) {
    activity.innerHTML = items
      .map(
        (item) => `
          <li>
            <span aria-hidden="true"></span>
            <div>
              <strong>${escapeHtml(item.title)}</strong>
              <p>${escapeHtml(item.description)}</p>
              <small>${escapeHtml(item.time)}</small>
            </div>
          </li>
        `
      )
      .join("");
  }

  function render(profile) {
    initials.textContent = profile.initials;
    role.textContent = roleLabels[profile.role] ?? profile.role;
    name.textContent = profile.name;
    summary.textContent = profile.summary;
    username.textContent = `@${profile.username}`;
    email.textContent = profile.email;
    status.textContent = statusLabels[profile.status] ?? profile.status;
    plans.textContent = profile.stats.plannedMeals;
    favorites.textContent = profile.stats.favoriteRecipes;
    recipes.textContent = profile.stats.ownRecipes;
    renderDetails(profile);
    renderActivity(profile.activity);
  }

  async function loadProfile() {
    try {
      const response = await fetch(profileUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      render(data.profile);
      loadingState.hidden = true;
      errorState.hidden = true;
      content.hidden = false;
    } catch (error) {
      loadingState.hidden = true;
      errorState.hidden = false;
      content.hidden = true;
    }
  }

  loadProfile();
})();
