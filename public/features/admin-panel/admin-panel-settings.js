(() => {
  const view = document.querySelector("[data-admin-settings]");
  if (!view) return;

  const loading  = view.querySelector("[data-loading]");
  const error    = view.querySelector("[data-error]");
  const content  = view.querySelector("[data-content]");
  const logoutBtn = document.querySelector("[data-admin-logout]");

  let settings = {};

  function getField(key) {
    return view.querySelector(`[data-field="${key}"]`);
  }

  function fillForm(data) {
    settings = data;
    for (const [key, val] of Object.entries(data)) {
      if (key === "allowedInviteRoles" && Array.isArray(val)) {
        view.querySelectorAll("[data-role-invite]").forEach(cb => {
          cb.checked = val.includes(cb.dataset.roleInvite);
        });
        continue;
      }
      const el = getField(key);
      if (el) el.value = val;
    }
  }

  function collectGroup(keys) {
    const payload = {};
    for (const key of keys) {
      const el = getField(key);
      if (el) {
        payload[key] = el.type === "number" ? Number(el.value) : el.value;
      }
    }
    return payload;
  }

  function collectRoles() {
    const checked = [];
    view.querySelectorAll("[data-role-invite]:checked").forEach(cb => {
      checked.push(cb.dataset.roleInvite);
    });
    return { allowedInviteRoles: checked };
  }

  async function save(group, keys) {
    const payload = group === "roles" ? collectRoles() : collectGroup(keys);
    try {
      const res = await fetch("/api/admin/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok) {
        const messages = {
          media:   "Ustawienia mediów zapisane.",
          roles:   "Ustawienia ról zapisane.",
          session: "Parametry sesji zapisane.",
          public:  "Dane publiczne aplikacji zaktualizowane.",
        };
        window.toast?.success(messages[group] ?? "Ustawienia zapisane.");
        if (data.settings) fillForm(data.settings);
      } else {
        window.toast?.error(data.error ?? "Nie udało się zapisać ustawień.");
      }
    } catch {
      window.toast?.error("Błąd sieci. Nie udało się zapisać ustawień.");
    }
  }

  view.querySelectorAll("[data-save]").forEach(btn => {
    const group = btn.dataset.save;
    const groupKeys = {
      media:   ["mediaLimitMb", "videoLimitMb"],
      session: ["sessionTtlHours", "rememberMeDays"],
      public:  ["appName", "tagline", "contactEmail"],
    };
    btn.addEventListener("click", () => save(group, groupKeys[group] ?? []));
  });

  logoutBtn?.addEventListener("click", async () => {
    await fetch("/api/admin/logout", { method: "POST" });
    window.location.href = "/admin-panel/login";
  });

  async function loadSettings() {
    try {
      const res = await fetch("/api/admin/settings");
      if (!res.ok) throw new Error();
      const data = await res.json();
      loading.hidden = true;
      content.hidden = false;
      fillForm(data.settings ?? {});
    } catch {
      loading.hidden = true;
      error.hidden   = false;
      window.toast?.error("Nie udało się załadować ustawień aplikacji.");
    }
  }

  loadSettings();
})();
