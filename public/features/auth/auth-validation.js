(() => {
  const forms = document.querySelectorAll("[data-auth-form]");

  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  const messages = {
    emailRequired: "Podaj adres e-mail.",
    emailInvalid: "Podaj poprawny adres e-mail.",
    passwordRequired: "Podaj hasło.",
    passwordTooShort: "Hasło musi mieć co najmniej 8 znaków.",
    passwordWeak: "Hasło musi mieć min. 8 znaków, 1 dużą literę i 1 znak specjalny.",
    nameRequired: "Podaj imię.",
    nameTooShort: "Imię musi mieć co najmniej 2 znaki.",
    passwordConfirmationRequired: "Powtórz hasło.",
    passwordMismatch: "Hasła muszą być takie same.",
    termsRequired: "Zaakceptuj regulamin i politykę prywatności."
  };

  const strongPasswordPattern = /^(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}$/;

  function getErrorElement(form, input) {
    return form.querySelector(`[data-error-for="${input.id || input.name}"]`);
  }

  function setInputState(form, input, message) {
    const errorElement = getErrorElement(form, input);
    const isValid = message === "";

    if (input.type !== "checkbox") {
      input.classList.toggle("is-invalid", !isValid);
      input.classList.toggle("is-valid", isValid && input.value.trim() !== "");
    }

    input.setAttribute("aria-invalid", String(!isValid));

    if (errorElement) {
      errorElement.textContent = message;
    }

    return isValid;
  }

  function validateInput(form, input) {
    const validationType = input.dataset.validate;
    const value = input.value.trim();

    if (validationType === "email") {
      if (value === "") {
        return setInputState(form, input, messages.emailRequired);
      }

      if (!emailPattern.test(value)) {
        return setInputState(form, input, messages.emailInvalid);
      }
    }

    if (validationType === "password") {
      if (value === "") {
        return setInputState(form, input, messages.passwordRequired);
      }
    }

    if (validationType === "password-strong") {
      if (value === "") {
        return setInputState(form, input, messages.passwordRequired);
      }

      if (!strongPasswordPattern.test(value)) {
        return setInputState(form, input, messages.passwordWeak);
      }
    }

    if (validationType === "name") {
      if (value === "") {
        return setInputState(form, input, messages.nameRequired);
      }

      if (value.length < 2) {
        return setInputState(form, input, messages.nameTooShort);
      }
    }

    if (validationType === "passwordConfirmation") {
      const matchedInput = form.querySelector(`#${input.dataset.match}`);

      if (value === "") {
        return setInputState(form, input, messages.passwordConfirmationRequired);
      }

      if (matchedInput && value !== matchedInput.value) {
        return setInputState(form, input, messages.passwordMismatch);
      }
    }

    if (validationType === "terms" && !input.checked) {
      return setInputState(form, input, messages.termsRequired);
    }

    return setInputState(form, input, "");
  }

  function validateForm(form) {
    const inputs = form.querySelectorAll("[data-validate]");
    return Array.from(inputs).reduce((isFormValid, input) => {
      return validateInput(form, input) && isFormValid;
    }, true);
  }

  forms.forEach((form) => {
    const inputs = form.querySelectorAll("[data-validate]");

    inputs.forEach((input) => {
      if (input.type === "checkbox") {
        input.addEventListener("change", () => {
          validateInput(form, input);
        });
        return;
      }

      input.addEventListener("blur", () => {
        validateInput(form, input);
      });

      input.addEventListener("input", () => {
        const isCurrentlyInvalid = input.getAttribute("aria-invalid") === "true";

        if (isCurrentlyInvalid) {
          validateInput(form, input);
        }

        if (input.dataset.validate === "password") {
          const confirmationInput = form.querySelector("[data-validate='passwordConfirmation']");
          if (
            confirmationInput &&
            confirmationInput.value !== "" &&
            confirmationInput.getAttribute("aria-invalid") === "true"
          ) {
            validateInput(form, confirmationInput);
          }
        }
      });

      if (input.dataset.validate === "password") {
        input.addEventListener("blur", () => {
          const confirmationInput = form.querySelector("[data-validate='passwordConfirmation']");
          if (confirmationInput && confirmationInput.value !== "") {
            validateInput(form, confirmationInput);
          }
        });
      }
    });

    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      if (!validateForm(form)) return;

      const type = form.dataset.authForm;
      const endpoint = `/api/auth/${type}`;
      const successMsg = type === "login"
        ? "Zalogowano pomyślnie."
        : "Konto utworzone! Sprawdź e-mail i potwierdź rejestrację.";
      const submitBtn = form.querySelector('[type="submit"]');

      submitBtn.disabled = true;

      try {
        const res = await fetch(endpoint, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams(new FormData(form)).toString(),
        });

        if (res.ok) {
          sessionStorage.setItem('flash', JSON.stringify({ type: 'success', message: successMsg }));
          window.location.href = "/dashboard";
        } else {
          const data = await res.json().catch(() => ({}));
          window.toast?.error(data.error ?? "Wystąpił błąd. Spróbuj ponownie.");
          submitBtn.disabled = false;
        }
      } catch {
        window.toast?.error("Brak połączenia z serwerem. Spróbuj ponownie.");
        submitBtn.disabled = false;
      }
    });
  });

  document.querySelectorAll("[data-password-toggle]").forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);

    if (!input) {
      return;
    }

    button.addEventListener("click", () => {
      const shouldShowPassword = input.type === "password";
      input.type = shouldShowPassword ? "text" : "password";
      button.setAttribute("aria-label", shouldShowPassword ? "Ukryj hasło" : "Pokaż hasło");
    });
  });
})();
