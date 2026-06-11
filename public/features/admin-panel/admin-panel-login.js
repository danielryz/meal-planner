(() => {
  const form       = document.querySelector("[data-admin-login-form]");
  const submitBtn  = document.querySelector("[data-admin-login-submit]");
  const errorEmail = document.querySelector("[data-error-email]");
  const errorPwd   = document.querySelector("[data-error-password]");

  if (!form) return;

  function clearErrors() {
    errorEmail.textContent = "";
    errorPwd.textContent = "";
    form.querySelectorAll("input").forEach(i => i.classList.remove("is-invalid"));
  }

  function setError(field, message) {
    const input = form.querySelector(`[name="${field}"]`);
    if (input) input.classList.add("is-invalid");
    if (field === "email") errorEmail.textContent = message;
    if (field === "password") errorPwd.textContent = message;
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors();

    const email    = form.querySelector("[name=email]")?.value.trim() ?? "";
    const password = form.querySelector("[name=password]")?.value ?? "";
    const csrf     = form.querySelector("[name=csrfToken]")?.value ?? "";

    let valid = true;
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setError("email", "Podaj poprawny adres e-mail.");
      valid = false;
    }
    if (!password) {
      setError("password", "Podaj hasło.");
      valid = false;
    }
    if (!valid) return;

    submitBtn.disabled = true;
    submitBtn.textContent = "Logowanie...";

    try {
      const res = await fetch("/api/admin/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password, csrfToken: csrf }),
      });

      const data = await res.json().catch(() => ({}));

      if (res.ok) {
        window.toast?.success("Zalogowano do panelu administracyjnego.");
        setTimeout(() => { window.location.href = "/admin-panel/dashboard"; }, 800);
        return;
      }

      if (res.status === 403) {
        window.toast?.error("Brak uprawnień do panelu administracyjnego.");
      } else if (res.status === 401) {
        window.toast?.error("Niepoprawny e-mail lub hasło.");
      } else {
        window.toast?.error(data.error ?? "Wystąpił błąd logowania.");
      }
    } catch {
      window.toast?.error("Błąd sieci. Spróbuj ponownie.");
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = "Zaloguj się do panelu";
    }
  });
})();
