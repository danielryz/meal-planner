(() => {
  const view = document.querySelector("[data-users-view]");
  const usersUrl = "/public/features/users/users_mock.json";

  if (!view) {
    return;
  }

  const loadingState = view.querySelector("[data-users-loading]");
  const errorState = view.querySelector("[data-users-error]");
  const content = view.querySelector("[data-users-content]");
  const list = view.querySelector("[data-users-list]");
  const emptyState = view.querySelector("[data-users-empty]");
  const searchInput = view.querySelector("[data-users-search]");
  const roleFilter = view.querySelector("[data-users-role-filter]");
  const activeCount = view.querySelector("[data-users-active-count]");
  const pendingCount = view.querySelector("[data-users-pending-count]");
  const ownerCount = view.querySelector("[data-users-owner-count]");
  const inviteForm = view.querySelector("[data-invite-form]");
  const inviteEmail = view.querySelector("[data-invite-email]");
  const inviteRole = view.querySelector("[data-invite-role]");
  const inviteError = view.querySelector("[data-invite-error]");
  const focusInviteButton = view.querySelector("[data-focus-invite]");
  let users = [];

  const roleLabels = {
    owner: "Właściciel",
    employee: "Pracownik",
    user: "Użytkownik",
  };

  const statusLabels = {
    active: "Aktywny",
    pending: "Oczekuje",
    inactive: "Nieaktywny",
  };

  function escapeHtml(value) {
    return String(value)
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
    const query = normalize(searchInput?.value ?? "");
    const role = roleFilter?.value ?? "all";

    return users.filter((user) => {
      const matchesQuery = !query || normalize(`${user.name} ${user.email}`).includes(query);
      const matchesRole = role === "all" || user.role === role;

      return matchesQuery && matchesRole;
    });
  }

  function updateStats() {
    activeCount.textContent = users.filter((user) => user.status === "active").length;
    pendingCount.textContent = users.filter((user) => user.status === "pending").length;
    ownerCount.textContent = users.filter((user) => user.role === "owner").length;
  }

  function createUserRow(user) {
    const isOwner = user.role === "owner";
    const ownerDisabled = isOwner ? "disabled" : "";

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
              .map(([value, label]) => `<option value="${value}" ${user.role === value ? "selected" : ""}>${label}</option>`)
              .join("")}
          </select>
        </label>
        <span class="users-status users-status--${escapeHtml(user.status)}" role="cell">${escapeHtml(statusLabels[user.status])}</span>
        <span class="users-last-active" role="cell">${escapeHtml(user.lastActive)}</span>
        <div class="users-actions" role="cell">
          <button type="button" data-resend-invite ${user.status === "pending" ? "" : "disabled"}>Ponów</button>
          <button type="button" data-toggle-status ${isOwner ? "disabled" : ""}>${user.status === "inactive" ? "Aktywuj" : "Zablokuj"}</button>
        </div>
      </article>
    `;
  }

  function render() {
    const filteredUsers = getFilteredUsers();

    list.innerHTML = filteredUsers.map(createUserRow).join("");
    emptyState.hidden = filteredUsers.length > 0;
    updateStats();
  }

  function findUserByRow(target) {
    const row = target.closest("[data-user-id]");
    const userId = Number(row?.dataset.userId);

    return users.find((user) => user.id === userId) ?? null;
  }

  async function loadUsers() {
    try {
      const response = await fetch(usersUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      users = data.users;
      loadingState.hidden = true;
      errorState.hidden = true;
      content.hidden = false;
      render();
    } catch (error) {
      loadingState.hidden = true;
      errorState.hidden = false;
      content.hidden = true;
    }
  }

  searchInput?.addEventListener("input", render);
  roleFilter?.addEventListener("change", render);

  focusInviteButton?.addEventListener("click", () => {
    inviteEmail?.focus();
  });

  inviteEmail?.addEventListener("blur", () => {
    inviteError.hidden = !inviteEmail.value || isValidEmail(inviteEmail.value);
  });

  inviteForm?.addEventListener("submit", (event) => {
    event.preventDefault();

    if (!isValidEmail(inviteEmail.value)) {
      inviteError.hidden = false;
      inviteEmail.focus();
      return;
    }

    const email = inviteEmail.value.trim();
    users.unshift({
      id: Date.now(),
      name: "Nowa osoba",
      email,
      role: inviteRole.value,
      status: "pending",
      lastActive: "Zaproszenie wysłane",
      initials: email.slice(0, 2).toUpperCase(),
    });

    inviteForm.reset();
    inviteError.hidden = true;
    render();
  });

  list.addEventListener("change", (event) => {
    const select = event.target.closest("[data-role-select]");

    if (!select) {
      return;
    }

    const user = findUserByRow(select);

    if (user) {
      user.role = select.value;
      render();
    }
  });

  list.addEventListener("click", (event) => {
    const statusButton = event.target.closest("[data-toggle-status]");
    const inviteButton = event.target.closest("[data-resend-invite]");

    if (statusButton) {
      const user = findUserByRow(statusButton);

      if (user) {
        user.status = user.status === "inactive" ? "active" : "inactive";
        user.lastActive = user.status === "inactive" ? "Dostęp zablokowany" : "Aktywowano teraz";
        render();
      }
    }

    if (inviteButton) {
      inviteButton.textContent = "Wysłano";
      inviteButton.disabled = true;
    }
  });

  loadUsers();
})();
