(() => {
  const view = document.querySelector("[data-settings-view]");

  if (!view) {
    return;
  }

  const loadingState       = view.querySelector("[data-settings-loading]");
  const errorState         = view.querySelector("[data-settings-error]");
  const content            = view.querySelector("[data-settings-content]");
  const initialsEl         = view.querySelector("[data-settings-initials]");
  const avatarImg          = view.querySelector("[data-settings-avatar-img]");
  const roleEl             = view.querySelector("[data-settings-role]");
  const nameEl             = view.querySelector("[data-settings-name]");
  const emailEl            = view.querySelector("[data-settings-email]");
  const statusEl           = view.querySelector("[data-settings-status]");
  const usernameEl         = view.querySelector("[data-settings-username]");
  const passwordDateEl     = view.querySelector("[data-settings-password-date]");
  const displayNameInput   = view.querySelector("[data-display-name]");
  const usernameInput      = view.querySelector("[data-username]");
  const emailInput         = view.querySelector("[data-email]");
  const currentPasswordInput = view.querySelector("[data-current-password]");
  const newPasswordInput   = view.querySelector("[data-new-password]");
  const repeatPasswordInput = view.querySelector("[data-repeat-password]");
  const displayNameError   = view.querySelector("[data-display-name-error]");
  const usernameError      = view.querySelector("[data-username-error]");
  const emailError         = view.querySelector("[data-email-error]");
  const currentPasswordError = view.querySelector("[data-current-password-error]");
  const newPasswordError   = view.querySelector("[data-new-password-error]");
  const repeatPasswordError = view.querySelector("[data-repeat-password-error]");

  let account = null;

  const roleLabels = {
    admin:    "Admin",
    owner:    "Właściciel",
    employee: "Pracownik",
    user:     "Użytkownik",
  };

  const statusLabels = {
    active:   "Aktywne",
    pending:  "Oczekujące",
    inactive: "Nieaktywne",
  };

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value).trim());
  }

  function setFieldState(input, error, isValid, message) {
    input.classList.toggle("is-invalid", !isValid);
    error.hidden = isValid;
    if (!isValid && message) error.textContent = message;
  }

  function setFieldsFromErrors(fields) {
    if (fields.displayName) setFieldState(displayNameInput, displayNameError, false, fields.displayName);
    if (fields.username)    setFieldState(usernameInput,    usernameError,    false, fields.username);
    if (fields.currentPassword) setFieldState(currentPasswordInput, currentPasswordError, false, fields.currentPassword);
    if (fields.newPassword) setFieldState(newPasswordInput, newPasswordError, false, fields.newPassword);
  }

  function renderAside(data) {
    account = data;

    if (data.avatarUrl && avatarImg) {
      avatarImg.src    = data.avatarUrl;
      avatarImg.hidden = false;
      if (initialsEl) initialsEl.hidden = true;
    } else {
      if (initialsEl) { initialsEl.textContent = data.initials ?? ""; initialsEl.hidden = false; }
      if (avatarImg)  avatarImg.hidden = true;
    }

    if (roleEl)         roleEl.textContent         = roleLabels[data.role] ?? data.role;
    if (nameEl)         nameEl.textContent         = data.name ?? "";
    if (emailEl)        emailEl.textContent        = data.email ?? "";
    if (statusEl)       statusEl.textContent       = statusLabels[data.status] ?? data.status;
    if (usernameEl)     usernameEl.textContent     = `@${data.username}`;
    if (passwordDateEl) passwordDateEl.textContent = data.lastPasswordChange ?? "—";

    if (displayNameInput) displayNameInput.value = data.name ?? "";
    if (usernameInput)    usernameInput.value    = data.username ?? "";
    if (emailInput)       emailInput.value       = data.email ?? "";
  }

  async function loadSettings() {
    try {
      const response = await fetch("/api/settings/account");

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      renderAside(data);
      loadingState.hidden = true;
      errorState.hidden   = true;
      content.hidden      = false;
    } catch {
      loadingState.hidden = true;
      errorState.hidden   = false;
      content.hidden      = true;
    }
  }

  // Avatar upload: update aside when media-upload fires completion
  view.querySelector("[data-media-upload]")?.addEventListener("media-upload:complete", (event) => {
    const { url } = event.detail;
    if (!url) return;
    if (avatarImg) { avatarImg.src = url; avatarImg.hidden = false; }
    if (initialsEl) initialsEl.hidden = true;
  });

  // --- Validation blur handlers ---
  displayNameInput?.addEventListener("blur", () => {
    setFieldState(displayNameInput, displayNameError, displayNameInput.value.trim().length > 0);
  });
  usernameInput?.addEventListener("blur", () => {
    setFieldState(usernameInput, usernameError, usernameInput.value.trim().length >= 3);
  });
  emailInput?.addEventListener("blur", () => {
    setFieldState(emailInput, emailError, !emailInput.value || isValidEmail(emailInput.value));
  });
  newPasswordInput?.addEventListener("blur", () => {
    setFieldState(newPasswordInput, newPasswordError, !newPasswordInput.value || newPasswordInput.value.length >= 8);
  });
  repeatPasswordInput?.addEventListener("blur", () => {
    setFieldState(repeatPasswordInput, repeatPasswordError, !repeatPasswordInput.value || repeatPasswordInput.value === newPasswordInput.value);
  });

  // --- Profile form ---
  view.querySelector('[data-settings-form="profile"]')?.addEventListener("submit", async (event) => {
    event.preventDefault();

    const hasName     = displayNameInput.value.trim().length > 0;
    const hasUsername = usernameInput.value.trim().length >= 3;
    setFieldState(displayNameInput, displayNameError, hasName);
    setFieldState(usernameInput,    usernameError,    hasUsername);
    if (!hasName || !hasUsername) return;

    try {
      const res = await fetch("/api/settings/profile", {
        method:  "PATCH",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
          displayName: displayNameInput.value.trim(),
          username:    usernameInput.value.trim(),
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        if (data.fields) setFieldsFromErrors(data.fields);
        if (window.toast) window.toast.error(data.error ?? "Wystąpił błąd.");
        return;
      }

      account = { ...account, name: displayNameInput.value.trim(), username: usernameInput.value.trim() };
      renderAside(account);
      if (window.toast) window.toast.success("Dane profilu zaktualizowane.");
    } catch {
      if (window.toast) window.toast.error("Błąd połączenia z serwerem.");
    }
  });

  // --- Email form (stub — verification not yet implemented) ---
  view.querySelector('[data-settings-form="email"]')?.addEventListener("submit", (event) => {
    event.preventDefault();

    const validEmail = isValidEmail(emailInput.value);
    setFieldState(emailInput, emailError, validEmail);
    if (!validEmail) { emailInput.focus(); return; }

    if (window.toast) window.toast.info("Weryfikacja e-mail nie jest jeszcze dostępna.");
  });

  // --- Password form ---
  view.querySelector('[data-settings-form="password"]')?.addEventListener("submit", async (event) => {
    event.preventDefault();

    const hasCurrent  = currentPasswordInput.value.length > 0;
    const hasStrong   = newPasswordInput.value.length >= 8;
    const matches     = repeatPasswordInput.value === newPasswordInput.value;
    setFieldState(currentPasswordInput, currentPasswordError, hasCurrent);
    setFieldState(newPasswordInput,     newPasswordError,     hasStrong);
    setFieldState(repeatPasswordInput,  repeatPasswordError,  matches);
    if (!hasCurrent || !hasStrong || !matches) return;

    try {
      const res = await fetch("/api/settings/password-change", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
          currentPassword: currentPasswordInput.value,
          newPassword:     newPasswordInput.value,
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        if (data.fields) setFieldsFromErrors(data.fields);
        if (window.toast) window.toast.error(data.error ?? "Wystąpił błąd.");
        return;
      }

      event.currentTarget.reset();
      if (window.toast) window.toast.success("Hasło zostało zmienione.");
    } catch {
      if (window.toast) window.toast.error("Błąd połączenia z serwerem.");
    }
  });

  loadSettings();
})();
