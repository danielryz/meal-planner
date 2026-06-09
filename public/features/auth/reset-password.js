(() => {
  const form = document.querySelector("[data-reset-form]");
  if (!form) return;

  document.querySelectorAll("[data-password-toggle]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const targetId = btn.dataset.passwordToggle;
      const input = document.getElementById(targetId);
      if (!input) return;
      input.type = input.type === "password" ? "text" : "password";
      btn.setAttribute("aria-label", input.type === "password" ? "Pokaż hasło" : "Ukryj hasło");
    });
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const password = form.querySelector('[name="password"]')?.value ?? "";
    const confirm  = form.querySelector('[name="passwordConfirm"]')?.value ?? "";

    const passErr = form.querySelector('[data-error-for="reset-password"]');
    const confErr = form.querySelector('[data-error-for="reset-password-confirm"]');
    if (passErr) passErr.textContent = "";
    if (confErr) confErr.textContent = "";

    if (!/^(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}$/.test(password)) {
      if (passErr) passErr.textContent = "Hasło musi mieć min. 8 znaków, 1 dużą literę i 1 znak specjalny.";
      return;
    }

    if (password !== confirm) {
      if (confErr) confErr.textContent = "Hasła nie są zgodne.";
      return;
    }

    const btn = form.querySelector("[data-submit-btn]");
    btn.disabled = true;

    try {
      const res = await fetch("/api/auth/reset-password", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams(new FormData(form)).toString(),
      });

      const data = await res.json();

      if (res.ok) {
        window.toast?.success("Hasło zostało zmienione. Możesz się zalogować.");
        setTimeout(() => { window.location.href = "/login"; }, 1200);
      } else if (data.code === "TOKEN_INVALID") {
        form.innerHTML = `
          <div class="auth-status-icon auth-status-icon--error" aria-hidden="true" style="margin: 0 auto 16px;">✗</div>
          <p style="color:#475569;">Link resetowania jest nieważny lub wygasł.<br/>Złóż nową prośbę na stronie logowania.</p>
          <a class="button auth-primary-button" href="/forgot-password" style="margin-top:16px;">Nowe łącze resetowania</a>
        `;
      } else {
        window.toast?.error(data.error ?? "Wystąpił błąd. Spróbuj ponownie.");
        btn.disabled = false;
      }
    } catch {
      window.toast?.error("Brak połączenia z serwerem. Spróbuj ponownie.");
      btn.disabled = false;
    }
  });
})();
