(() => {
  const view = document.querySelector("[data-users-view]");

  if (!view) {
    return;
  }

  const loadingState        = view.querySelector("[data-users-loading]");
  const errorState          = view.querySelector("[data-users-error]");
  const content             = view.querySelector("[data-users-content]");
  const list                = view.querySelector("[data-users-list]");
  const emptyState          = view.querySelector("[data-users-empty]");
  const searchInput         = view.querySelector("[data-users-search]");
  const roleFilter          = view.querySelector("[data-users-role-filter]");
  const activeCount         = view.querySelector("[data-users-active-count]");
  const pendingCount        = view.querySelector("[data-users-pending-count]");
  const ownerCount          = view.querySelector("[data-users-owner-count]");
  const inviteForm          = view.querySelector("[data-invite-form]");
  const inviteEmail         = view.querySelector("[data-invite-email]");
  const inviteRole          = view.querySelector("[data-invite-role]");
  const inviteError         = view.querySelector("[data-invite-error]");
  const focusInviteButton   = view.querySelector("[data-focus-invite]");

  let users                 = [];
  let committedSearchQuery  = "";

  const roleLabels = {
    owner:    "Właściciel",
    employee: "Pracownik",
    user:     "Użytkownik",
  };

  const statusLabels = {
    active:   "Aktywny",
    pending:  "Oczekuje",
    inactive: "Nieaktywny",
  };

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function normalize(value) {
    return String(value).trim().toLowerCase();
  }

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value).trim());
  }

  function getFilteredUsers() {
    const query = normalize(committedSearchQuery);
    const role  = roleFilter?.value ?? "all";

    return users.filter((user) => {
      const matchesQuery = !query || normalize(`${user.name} ${user.email}`).includes(query);
      const matchesRole  = role === "all" || user.role === role;
      return matchesQuery && matchesRole;
    });
  }

  function updateStats() {
    if (activeCount)  activeCount.textContent  = users.filter((u) => u.status === "active").length;
    if (pendingCount) pendingCount.textContent = users.filter((u) => u.status === "pending").length;
    if (ownerCount)   ownerCount.textContent   = users.filter((u) => u.role === "owner").length;
  }

  function createUserRow(user) {
    const isOwner       = user.role === "owner";
    const ownerDisabled = isOwner ? "disabled" : "";
    const toggleLabel   = user.status === "inactive" ? "Aktywuj" : "Zablokuj";

    return `
      <article class="users-row" role="row" data-user-id="${escapeHtml(user.id)}">
        <div class="users-person" role="cell">
          <span class="users-avatar" aria-hidden="true">${escapeHtml(user.initials)}</span>
          <span>
            <strong>${escapeHtml(user.name)}</strong>
            <small>${escapeHtml(user.email)}</small>
          </span>
        </div>
        <label class="users-role-control" role="cell">
          <span class="visually-hidden">Rola użytkownika ${escapeHtml(user.name)}</span>
          <select data-role-select ${ownerDisabled}>
            ${Object.entries(roleLabels)
              .map(([value, label]) => `<option value="${escapeHtml(value)}" ${user.role === value ? "selected" : ""}>${escapeHtml(label)}</option>`)
              .join("")}
          </select>
        </label>
        <span class="users-status users-status--${escapeHtml(user.status)}" role="cell">${escapeHtml(statusLabels[user.status] ?? user.status)}</span>
        <span class="users-last-active" role="cell">${escapeHtml(user.lastActive ?? "—")}</span>
        <div class="users-actions" role="cell">
          <button type="button" data-resend-invite ${user.status === "pending" ? "" : "disabled"}>Ponów</button>
          <button type="button" data-toggle-status ${isOwner ? "disabled" : ""}>${escapeHtml(toggleLabel)}</button>
        </div>
      </article>
    `;
  }

  function render() {
    const filteredUsers = getFilteredUsers();
    list.innerHTML      = filteredUsers.map(createUserRow).join("");
    emptyState.hidden   = filteredUsers.length > 0;
    updateStats();
  }

  function findUserByRow(target) {
    const userId = Number(target.closest("[data-user-id]")?.dataset.userId);
    return users.find((u) => u.id === userId) ?? null;
  }

  async function loadUsers() {
    try {
      const response = await fetch("/api/users");

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data         = await response.json();
      users              = data.users;
      loadingState.hidden = true;
      errorState.hidden   = true;
      content.hidden      = false;
      render();
    } catch {
      loadingState.hidden = true;
      errorState.hidden   = false;
      content.hidden      = true;
    }
  }

  searchInput?.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      committedSearchQuery = searchInput.value;
      render();
    }
  });

  roleFilter?.addEventListener("change", render);

  focusInviteButton?.addEventListener("click", () => inviteEmail?.focus());

  inviteEmail?.addEventListener("blur", () => {
    if (inviteError) inviteError.hidden = !inviteEmail.value || isValidEmail(inviteEmail.value);
  });

  inviteForm?.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!isValidEmail(inviteEmail.value)) {
      if (inviteError) inviteError.hidden = false;
      inviteEmail.focus();
      return;
    }

    try {
      const res = await fetch("/api/users/invitations", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ email: inviteEmail.value.trim(), role: inviteRole.value }),
      });

      if (res.ok) {
        inviteForm.reset();
        if (inviteError) inviteError.hidden = true;
        if (window.toast) window.toast.success("Zaproszenie wysłane.");
        await loadUsers();
      } else {
        const data = await res.json().catch(() => ({}));
        if (window.toast) window.toast.error(data.error ?? "Nie udało się wysłać zaproszenia.");
      }
    } catch {
      if (window.toast) window.toast.error("Błąd połączenia z serwerem.");
    }
  });

  list.addEventListener("change", async (event) => {
    const select = event.target.closest("[data-role-select]");
    if (!select) return;

    const user = findUserByRow(select);
    if (!user) return;

    const newRole    = select.value;
    const prevRole   = user.role;

    try {
      const res = await fetch(`/api/users/${user.id}/role`, {
        method:  "PATCH",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ role: newRole }),
      });

      if (res.ok) {
        user.role = newRole;
        render();
        if (window.toast) window.toast.success("Rola zaktualizowana.");
      } else {
        const data = await res.json().catch(() => ({}));
        select.value = prevRole;
        if (window.toast) window.toast.error(data.error ?? "Nie udało się zmienić roli.");
      }
    } catch {
      select.value = prevRole;
      if (window.toast) window.toast.error("Błąd połączenia z serwerem.");
    }
  });

  list.addEventListener("click", async (event) => {
    const statusButton = event.target.closest("[data-toggle-status]");
    const inviteButton = event.target.closest("[data-resend-invite]");

    if (statusButton) {
      const user = findUserByRow(statusButton);
      if (!user) return;

      const newStatus = user.status === "inactive" ? "active" : "inactive";

      try {
        const res = await fetch(`/api/users/${user.id}/status`, {
          method:  "PATCH",
          headers: { "Content-Type": "application/json" },
          body:    JSON.stringify({ status: newStatus }),
        });

        if (res.ok) {
          user.status     = newStatus;
          user.lastActive = newStatus === "inactive" ? "Dostęp zablokowany" : "Aktywowano";
          render();
          if (window.toast) window.toast.success(newStatus === "inactive" ? "Konto zablokowane." : "Konto aktywowane.");
        } else {
          const data = await res.json().catch(() => ({}));
          if (window.toast) window.toast.error(data.error ?? "Nie udało się zmienić statusu.");
        }
      } catch {
        if (window.toast) window.toast.error("Błąd połączenia z serwerem.");
      }
    }

    if (inviteButton) {
      const user = findUserByRow(inviteButton);
      if (!user) return;

      try {
        const res = await fetch("/api/users/invitations", {
          method:  "POST",
          headers: { "Content-Type": "application/json" },
          body:    JSON.stringify({ email: user.email, role: user.role }),
        });

        if (res.ok) {
          inviteButton.textContent = "Wysłano";
          inviteButton.disabled    = true;
          if (window.toast) window.toast.success("Zaproszenie ponowione.");
        } else {
          const data = await res.json().catch(() => ({}));
          if (window.toast) window.toast.error(data.error ?? "Nie udało się ponowić zaproszenia.");
        }
      } catch {
        if (window.toast) window.toast.error("Błąd połączenia z serwerem.");
      }
    }
  });

  loadUsers();
})();
