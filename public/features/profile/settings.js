(() => {
  const view = document.querySelector("[data-settings-view]");
  const settingsUrl = "/public/features/profile/settings_mock.json";

  if (!view) {
    return;
  }

  const loadingState = view.querySelector("[data-settings-loading]");
  const errorState = view.querySelector("[data-settings-error]");
  const content = view.querySelector("[data-settings-content]");
  const initials = view.querySelector("[data-settings-initials]");
  const role = view.querySelector("[data-settings-role]");
  const name = view.querySelector("[data-settings-name]");
  const email = view.querySelector("[data-settings-email]");
  const status = view.querySelector("[data-settings-status]");
  const username = view.querySelector("[data-settings-username]");
  const passwordDate = view.querySelector("[data-settings-password-date]");
  const displayNameInput = view.querySelector("[data-display-name]");
  const usernameInput = view.querySelector("[data-username]");
  const emailInput = view.querySelector("[data-email]");
  const currentPasswordInput = view.querySelector("[data-current-password]");
  const newPasswordInput = view.querySelector("[data-new-password]");
  const repeatPasswordInput = view.querySelector("[data-repeat-password]");
  const displayNameError = view.querySelector("[data-display-name-error]");
  const usernameError = view.querySelector("[data-username-error]");
  const emailError = view.querySelector("[data-email-error]");
  const currentPasswordError = view.querySelector("[data-current-password-error]");
  const newPasswordError = view.querySelector("[data-new-password-error]");
  const repeatPasswordError = view.querySelector("[data-repeat-password-error]");
  let account = null;

  const roleLabels = {
    admin: "Admin",
    owner: "Właściciel",
    employee: "Pracownik",
    user: "Użytkownik",
  };

  const statusLabels = {
    active: "Aktywne",
    pending: "Oczekujące",
    inactive: "Nieaktywne",
  };

  function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value).trim());
  }

  function setFieldState(input, error, isValid) {
    input.classList.toggle("is-invalid", !isValid);
    error.hidden = isValid;
  }

  function setMessage(form, selector, message) {
    const element = form.querySelector(selector);

    element.textContent = message;
    element.hidden = false;
    window.setTimeout(() => {
      element.hidden = true;
    }, 2200);
  }

  function renderAccount(data) {
    account = data;
    initials.textContent = data.initials;
    role.textContent = roleLabels[data.role] ?? data.role;
    name.textContent = data.name;
    email.textContent = data.email;
    status.textContent = statusLabels[data.status] ?? data.status;
    username.textContent = `@${data.username}`;
    passwordDate.textContent = data.lastPasswordChange;
    displayNameInput.value = data.name;
    usernameInput.value = data.username;
    emailInput.value = data.email;
  }

  async function loadSettings() {
    try {
      const response = await fetch(settingsUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      renderAccount(data.account);
      loadingState.hidden = true;
      errorState.hidden = true;
      content.hidden = false;
    } catch (error) {
      loadingState.hidden = true;
      errorState.hidden = false;
      content.hidden = true;
    }
  }

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
    const isValid = !repeatPasswordInput.value || repeatPasswordInput.value === newPasswordInput.value;
    setFieldState(repeatPasswordInput, repeatPasswordError, isValid);
  });

  view.querySelector('[data-settings-form="profile"]')?.addEventListener("submit", (event) => {
    event.preventDefault();

    const hasName = displayNameInput.value.trim().length > 0;
    const hasUsername = usernameInput.value.trim().length >= 3;
    setFieldState(displayNameInput, displayNameError, hasName);
    setFieldState(usernameInput, usernameError, hasUsername);

    if (!hasName || !hasUsername) {
      return;
    }

    account.name = displayNameInput.value.trim();
    account.username = usernameInput.value.trim();
    renderAccount(account);
    setMessage(event.currentTarget, "[data-profile-message]", "Dane profilu zapisane lokalnie.");
  });

  view.querySelector('[data-settings-form="email"]')?.addEventListener("submit", (event) => {
    event.preventDefault();

    const validEmail = isValidEmail(emailInput.value);
    setFieldState(emailInput, emailError, validEmail);

    if (!validEmail) {
      emailInput.focus();
      return;
    }

    account.email = emailInput.value.trim();
    renderAccount(account);
    setMessage(event.currentTarget, "[data-email-message]", "Wysłano link potwierdzający zmianę e-maila.");
  });

  view.querySelector('[data-settings-form="password"]')?.addEventListener("submit", (event) => {
    event.preventDefault();

    const hasCurrentPassword = currentPasswordInput.value.length > 0;
    const hasStrongPassword = newPasswordInput.value.length >= 8;
    const passwordsMatch = repeatPasswordInput.value === newPasswordInput.value;
    setFieldState(currentPasswordInput, currentPasswordError, hasCurrentPassword);
    setFieldState(newPasswordInput, newPasswordError, hasStrongPassword);
    setFieldState(repeatPasswordInput, repeatPasswordError, passwordsMatch);

    if (!hasCurrentPassword || !hasStrongPassword || !passwordsMatch) {
      return;
    }

    event.currentTarget.reset();
    setMessage(event.currentTarget, "[data-password-message]", "Hasło zostało zmienione lokalnie.");
  });

  loadSettings();
})();
