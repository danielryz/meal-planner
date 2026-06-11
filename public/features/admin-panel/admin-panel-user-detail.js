(() => {
  const view = document.querySelector("[data-admin-user-detail]");
  if (!view) return;

  const userId = parseInt(view.dataset.userId, 10);
  if (!userId) { window.location.href = "/admin-panel/users"; return; }

  const loading = view.querySelector("[data-loading]");
  const error   = view.querySelector("[data-error]");
  const content = view.querySelector("[data-content]");

  const nameEl        = view.querySelector("[data-user-name]");
  const displayName   = view.querySelector("[data-user-display-name]");
  const emailEl       = view.querySelector("[data-user-email]");
  const roleSelect    = view.querySelector("[data-user-role-select]");
  const statusBadge   = view.querySelector("[data-user-status-badge]");
  const statusText    = view.querySelector("[data-user-status-text]");
  const createdAt     = view.querySelector("[data-user-created-at]");
  const lastLogin     = view.querySelector("[data-user-last-login]");
  const saveRoleBtn   = view.querySelector("[data-save-role]");
  const suspendBtn    = view.querySelector("[data-suspend-btn]");
  const restoreBtn    = view.querySelector("[data-restore-btn]");
  const sendResetBtn  = view.querySelector("[data-send-reset]");
  const activityList  = view.querySelector("[data-activity-list]");

  const confirmModal   = document.querySelector("[data-confirm-modal]");
  const confirmTitle   = document.querySelector("[data-confirm-title]");
  const confirmMessage = document.querySelector("[data-confirm-message]");
  const confirmOk      = document.querySelector("[data-confirm-ok]");
  const confirmCancel  = document.querySelector("[data-confirm-cancel]");
  const logoutBtn = document.querySelector("[data-admin-logout]");

  const statusLabels = { active: "Aktywny", pending: "Oczekujący", suspended: "Zawieszony" };
  const statusClasses = { active: "admin-badge--active", pending: "admin-badge--pending", suspended: "admin-badge--suspended" };
  const eventLabels = {
    login_success: "Zalogowano", login_failed: "Błąd logowania",
    registered: "Rejestracja", password_changed: "Zmiana hasła",
    approved_recipe: "Zatwierdzono przepis", rejected_recipe: "Odrzucono przepis",
  };

  let pendingConfirm = null;
  let currentStatus  = "active";

  function escapeHtml(v) {
    return String(v ?? "").replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;").replaceAll('"',"&quot;");
  }

  function renderUser(user) {
    currentStatus = user.status;

    if (nameEl)      nameEl.textContent      = user.name;
    if (displayName) displayName.textContent = user.name;
    if (emailEl)     emailEl.textContent     = user.email;
    if (createdAt)   createdAt.textContent   = user.createdAt ?? "—";
    if (lastLogin)   lastLogin.textContent   = user.lastLoginAt ?? "—";

    if (roleSelect) roleSelect.value = user.role;

    if (statusBadge) {
      statusBadge.className = `admin-badge ${statusClasses[user.status] ?? ""}`;
      statusBadge.textContent = statusLabels[user.status] ?? user.status;
    }
    if (statusText) statusText.textContent = statusLabels[user.status] ?? user.status;

    for (const [sel, key] of [
      ["[data-stat-recipes]",   "recipes"],
      ["[data-stat-plans]",     "plans"],
      ["[data-stat-favorites]", "favorites"],
      ["[data-stat-reviews]",   "reviews"],
    ]) {
      const el = view.querySelector(sel);
      if (el) el.textContent = user.stats?.[key] ?? 0;
    }

    if (suspendBtn && restoreBtn) {
      suspendBtn.hidden  = user.status === "suspended" || user.role === "owner";
      restoreBtn.hidden  = user.status !== "suspended";
    }

    if (activityList && user.activity?.length > 0) {
      activityList.innerHTML = user.activity.map(e => `
        <li class="admin-activity-item">
          <span class="admin-activity-item__event">${escapeHtml(eventLabels[e.eventType] ?? e.eventType)}</span>
          <span class="admin-activity-item__time">${escapeHtml(e.createdAt ?? "")}</span>
        </li>
      `).join("");
    }
  }

  async function load() {
    try {
      const res = await fetch(`/api/admin/users/${userId}`);
      if (!res.ok) throw new Error();
      const data = await res.json();
      loading.hidden = true;
      content.hidden = false;
      renderUser(data.user);
    } catch {
      loading.hidden = true;
      error.hidden   = false;
    }
  }

  saveRoleBtn?.addEventListener("click", async () => {
    const role = roleSelect?.value;
    if (!role) return;
    try {
      const res = await fetch(`/api/admin/users/${userId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ role }),
      });
      if (res.ok) {
        window.toast?.success("Rola zaktualizowana.");
      } else {
        const d = await res.json().catch(() => ({}));
        window.toast?.error(d.error ?? "Nie udało się zmienić roli.");
      }
    } catch {
      window.toast?.error("Nie udało się zmienić roli.");
    }
  });

  suspendBtn?.addEventListener("click", () => {
    showConfirm("Zawieś konto", "Czy na pewno chcesz zawiesić to konto?", async () => {
      try {
        const res = await fetch(`/api/admin/users/${userId}`, {
          method: "PATCH",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ status: "inactive" }),
        });
        if (res.ok) {
          window.toast?.success("Konto zostało zawieszone.");
          load();
        } else {
          window.toast?.error("Nie udało się zawiesić konta.");
        }
      } catch {
        window.toast?.error("Nie udało się zawiesić konta.");
      }
    });
  });

  restoreBtn?.addEventListener("click", async () => {
    try {
      const res = await fetch(`/api/admin/users/${userId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status: "active" }),
      });
      if (res.ok) {
        window.toast?.success("Konto użytkownika zostało przywrócone.");
        load();
      } else {
        window.toast?.error("Nie udało się przywrócić konta.");
      }
    } catch {
      window.toast?.error("Nie udało się przywrócić konta.");
    }
  });

  sendResetBtn?.addEventListener("click", async () => {
    try {
      const res = await fetch(`/api/admin/users/${userId}/send-password-reset`, { method: "POST" });
      if (res.ok) {
        window.toast?.success("Link do resetowania hasła został wysłany na e-mail użytkownika.");
      } else {
        window.toast?.error("Nie udało się wysłać e-maila.");
      }
    } catch {
      window.toast?.error("Nie udało się wysłać e-maila.");
    }
  });

  function showConfirm(title, message, onOk) {
    if (confirmTitle)   confirmTitle.textContent   = title;
    if (confirmMessage) confirmMessage.textContent = message;
    if (confirmModal)   confirmModal.hidden = false;
    pendingConfirm = onOk;
  }

  confirmOk?.addEventListener("click", () => { if (pendingConfirm) pendingConfirm(); confirmModal.hidden = true; pendingConfirm = null; });
  confirmCancel?.addEventListener("click", () => { confirmModal.hidden = true; pendingConfirm = null; });

  logoutBtn?.addEventListener("click", async () => {
    await fetch("/api/admin/logout", { method: "POST" });
    window.location.href = "/admin-panel/login";
  });

  load();
})();
