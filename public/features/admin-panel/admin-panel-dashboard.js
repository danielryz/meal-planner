(() => {
  const view    = document.querySelector("[data-admin-dashboard]");
  if (!view) return;

  const loading  = view.querySelector("[data-admin-loading]");
  const error    = view.querySelector("[data-admin-error]");
  const content  = view.querySelector("[data-admin-content]");
  const dateSpan = view.querySelector("[data-admin-current-date]");
  const eventsList = view.querySelector("[data-admin-events]");

  const logoutBtn = document.querySelector("[data-admin-logout]");

  if (dateSpan) {
    dateSpan.textContent = new Date().toLocaleDateString("pl-PL", { dateStyle: "long" });
  }

  const eventLabels = {
    login_success:       "Logowanie (sukces)",
    login_failed:        "Logowanie (błąd)",
    registered:          "Rejestracja",
    approved_recipe:     "Zatwierdzono przepis",
    rejected_recipe:     "Odrzucono przepis",
    requested_changes:   "Poproszono o poprawki",
    password_changed:    "Zmiana hasła",
  };

  async function loadStats() {
    try {
      const res = await fetch("/api/admin/stats");
      if (!res.ok) throw new Error();
      const data = await res.json();

      loading.hidden = true;
      content.hidden = false;

      view.querySelector("[data-stat-users-active]").textContent    = data.users.active;
      view.querySelector("[data-stat-users-pending]").textContent   = data.users.pending;
      view.querySelector("[data-stat-users-suspended]").textContent = data.users.suspended;
      view.querySelector("[data-stat-users-total]").textContent     = data.users.total;
      view.querySelector("[data-stat-recipes-public]").textContent  = data.recipes.public;
      view.querySelector("[data-stat-recipes-pending]").textContent = data.recipes.pending;
      view.querySelector("[data-stat-plans]").textContent           = data.activePlans;

      if (data.recentEvents?.length > 0) {
        eventsList.innerHTML = data.recentEvents.map(e => `
          <li class="admin-event-item">
            <span class="admin-event-item__type">${escapeHtml(eventLabels[e.eventType] ?? e.eventType)}</span>
            <span class="admin-event-item__meta">${escapeHtml(e.actorName ?? e.actorEmail ?? "—")} · ${escapeHtml(e.createdAt ?? "")}</span>
          </li>
        `).join("");
      }
    } catch {
      loading.hidden = true;
      error.hidden = false;
      window.toast?.error("Nie udało się załadować statystyk systemu.");
    }
  }

  if (logoutBtn) {
    logoutBtn.addEventListener("click", async () => {
      try {
        await fetch("/api/admin/logout", { method: "POST" });
        window.toast?.info("Wylogowano z panelu administracyjnego.");
        setTimeout(() => { window.location.href = "/admin-panel/login"; }, 600);
      } catch {
        window.toast?.error("Błąd wylogowania.");
      }
    });
  }

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;").replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;").replaceAll('"', "&quot;");
  }

  loadStats();
})();
