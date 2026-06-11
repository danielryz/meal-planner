(() => {
  const view = document.querySelector("[data-admin-users]");
  if (!view) return;

  const loading    = view.querySelector("[data-loading]");
  const error      = view.querySelector("[data-error]");
  const empty      = view.querySelector("[data-empty]");
  const table      = view.querySelector("[data-table]");
  const tbody      = view.querySelector("[data-tbody]");
  const searchInput = view.querySelector("[data-search]");
  const roleFilter  = view.querySelector("[data-filter-role]");
  const statusFilter = view.querySelector("[data-filter-status]");

  const statActive    = view.querySelector("[data-stat-active]");
  const statPending   = view.querySelector("[data-stat-pending]");
  const statSuspended = view.querySelector("[data-stat-suspended]");
  const statTotal     = view.querySelector("[data-stat-total]");

  const openInviteBtn   = view.querySelector("[data-open-invite]");
  const inviteModal     = document.querySelector("[data-invite-modal]");
  const closeInviteBtn  = document.querySelector("[data-close-invite]");
  const submitInviteBtn = document.querySelector("[data-submit-invite]");
  const inviteEmail     = document.querySelector("[data-invite-email]");
  const inviteRole      = document.querySelector("[data-invite-role]");
  const inviteError     = document.querySelector("[data-invite-error]");

  const confirmModal   = document.querySelector("[data-confirm-modal]");
  const confirmTitle   = document.querySelector("[data-confirm-title]");
  const confirmMessage = document.querySelector("[data-confirm-message]");
  const confirmOk      = document.querySelector("[data-confirm-ok]");
  const confirmCancel  = document.querySelector("[data-confirm-cancel]");

  const logoutBtn = document.querySelector("[data-admin-logout]");

  const roleLabels   = { owner: "Właściciel", admin: "Admin", employee: "Pracownik", user: "Użytkownik" };
  const statusLabels = { active: "Aktywny", pending: "Oczekujący", suspended: "Zawieszony" };

  let debounceTimer = null;
  let pendingConfirm = null;

  function escapeHtml(v) {
    return String(v ?? "").replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;").replaceAll('"',"&quot;");
  }

  function updateStats(stats) {
    statActive.textContent    = stats.active ?? "—";
    statPending.textContent   = stats.pending ?? "—";
    statSuspended.textContent = stats.suspended ?? "—";
    statTotal.textContent     = stats.total ?? "—";
  }

  function renderTable(users) {
    if (users.length === 0) {
      table.hidden = true;
      empty.hidden = false;
      return;
    }
    empty.hidden = true;
    table.hidden = false;

    tbody.innerHTML = users.map(u => `
      <tr>
        <td>
          <div class="admin-user-cell">
            <div class="admin-avatar">${escapeHtml(u.initials)}</div>
            <div class="admin-user-cell__info">
              <strong>${escapeHtml(u.name)}</strong>
              <small>${escapeHtml(u.email)}</small>
            </div>
          </div>
        </td>
        <td>
          <select class="admin-role-select" data-role-select data-user-id="${u.id}" data-current-role="${escapeHtml(u.role)}">
            <option value="user" ${u.role==="user"?"selected":""}>Użytkownik</option>
            <option value="employee" ${u.role==="employee"?"selected":""}>Pracownik</option>
            <option value="admin" ${u.role==="admin"?"selected":""}>Admin</option>
            <option value="owner" ${u.role==="owner"?"selected":""}>Właściciel</option>
          </select>
        </td>
        <td><span class="admin-badge admin-badge--${escapeHtml(u.status)}">${escapeHtml(statusLabels[u.status] ?? u.status)}</span></td>
        <td>${escapeHtml(u.createdAt ?? "—")}</td>
        <td>${escapeHtml(u.lastLoginAt ?? "—")}</td>
        <td>
          <div class="admin-actions">
            <a class="admin-btn admin-btn--secondary admin-btn--sm" href="/admin-panel/users/${u.id}">Szczegóły</a>
            ${u.status !== "suspended"
              ? `<button class="admin-btn admin-btn--danger admin-btn--sm" data-suspend-user="${u.id}" data-user-name="${escapeHtml(u.name)}" ${u.role==="owner"?"disabled":""}>Zawieś</button>`
              : `<button class="admin-btn admin-btn--success admin-btn--sm" data-restore-user="${u.id}" data-user-name="${escapeHtml(u.name)}">Odwieś</button>`
            }
          </div>
        </td>
      </tr>
    `).join("");

    tbody.querySelectorAll("[data-role-select]").forEach(sel => {
      sel.addEventListener("change", async () => {
        const userId = sel.dataset.userId;
        const newRole = sel.value;
        try {
          const res = await fetch(`/api/admin/users/${userId}`, {
            method: "PATCH",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ role: newRole }),
          });
          if (res.ok) {
            window.toast?.success("Rola użytkownika zaktualizowana.");
          } else {
            const d = await res.json().catch(() => ({}));
            window.toast?.error(d.error ?? "Nie udało się zmienić roli.");
            sel.value = sel.dataset.currentRole;
          }
        } catch {
          window.toast?.error("Nie udało się zmienić roli.");
          sel.value = sel.dataset.currentRole;
        }
      });
    });

    tbody.querySelectorAll("[data-suspend-user]").forEach(btn => {
      btn.addEventListener("click", () => {
        const userId = btn.dataset.suspendUser;
        const name   = btn.dataset.userName;
        showConfirm(
          "Zawieś konto",
          `Czy na pewno chcesz zawiesić konto użytkownika ${name}?`,
          async () => {
            await updateUserStatus(userId, "inactive");
            loadUsers();
          }
        );
      });
    });

    tbody.querySelectorAll("[data-restore-user]").forEach(btn => {
      btn.addEventListener("click", async () => {
        await updateUserStatus(btn.dataset.restoreUser, "active");
        loadUsers();
      });
    });
  }

  async function updateUserStatus(userId, status) {
    try {
      const res = await fetch(`/api/admin/users/${userId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ status }),
      });
      if (res.ok) {
        window.toast?.success(status === "active" ? "Konto przywrócone." : "Konto zawieszone.");
      } else {
        const d = await res.json().catch(() => ({}));
        window.toast?.error(d.error ?? "Nie udało się zaktualizować statusu.");
      }
    } catch {
      window.toast?.error("Nie udało się zaktualizować statusu.");
    }
  }

  function showConfirm(title, message, onOk) {
    confirmTitle.textContent   = title;
    confirmMessage.textContent = message;
    confirmModal.hidden = false;
    pendingConfirm = onOk;
  }

  function hideConfirm() {
    confirmModal.hidden = true;
    pendingConfirm = null;
  }

  confirmOk?.addEventListener("click", () => {
    if (pendingConfirm) pendingConfirm();
    hideConfirm();
  });
  confirmCancel?.addEventListener("click", hideConfirm);

  openInviteBtn?.addEventListener("click", () => { inviteModal.hidden = false; });
  closeInviteBtn?.addEventListener("click", () => { inviteModal.hidden = true; });

  submitInviteBtn?.addEventListener("click", async () => {
    inviteError.textContent = "";
    const email = inviteEmail?.value.trim() ?? "";
    const role  = inviteRole?.value ?? "user";
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      inviteError.textContent = "Podaj poprawny adres e-mail.";
      return;
    }
    try {
      const res = await fetch("/api/admin/users/invite", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, role }),
      });
      const d = await res.json().catch(() => ({}));
      if (res.ok) {
        window.toast?.success("Zaproszenie zostało wysłane.");
        inviteModal.hidden = true;
        loadUsers();
      } else {
        inviteError.textContent = d.error ?? "Nie udało się wysłać zaproszenia.";
      }
    } catch {
      inviteError.textContent = "Błąd sieci.";
    }
  });

  async function loadUsers() {
    const query  = searchInput?.value.trim() ?? "";
    const role   = roleFilter?.value ?? "";
    const status = statusFilter?.value ?? "";

    const params = new URLSearchParams();
    if (query)  params.set("query", query);
    if (role)   params.set("role", role);
    if (status) params.set("status", status);

    loading.hidden = false;
    error.hidden   = true;
    table.hidden   = true;
    empty.hidden   = true;

    try {
      const res = await fetch(`/api/admin/users?${params}`);
      if (!res.ok) throw new Error();
      const data = await res.json();
      loading.hidden = true;
      updateStats(data.stats ?? {});
      renderTable(data.users ?? []);
    } catch {
      loading.hidden = true;
      error.hidden   = false;
      window.toast?.error("Nie udało się załadować listy użytkowników.");
    }
  }

  searchInput?.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadUsers, 400);
  });

  roleFilter?.addEventListener("change", loadUsers);
  statusFilter?.addEventListener("change", loadUsers);

  logoutBtn?.addEventListener("click", async () => {
    await fetch("/api/admin/logout", { method: "POST" });
    window.location.href = "/admin-panel/login";
  });

  loadUsers();
})();
