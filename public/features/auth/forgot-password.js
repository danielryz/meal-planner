(() => {
  const form = document.querySelector("[data-forgot-form]");
  if (!form) return;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const emailInput = form.querySelector('[name="email"]');
    const email = emailInput?.value.trim() ?? "";

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      emailInput?.closest(".form-field")?.querySelector("[data-error-for]")
        ?.setAttribute("aria-live", "polite");
      const err = form.querySelector('[data-error-for="forgot-email"]');
      if (err) err.textContent = "Podaj poprawny adres e-mail.";
      return;
    }

    const btn = form.querySelector("[data-submit-btn]");
    btn.disabled = true;

    try {
      const res = await fetch("/api/auth/forgot-password", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams(new FormData(form)).toString(),
      });

      if (res.ok) {
        form.innerHTML = `
          <div class="auth-status-icon auth-status-icon--success" aria-hidden="true" style="margin: 0 auto 16px;">✓</div>
          <p style="color:#475569;">Jeśli konto istnieje, link do resetowania hasła został wysłany na <strong>${email}</strong>. Sprawdź skrzynkę e-mail.</p>
        `;
      } else {
        const data = await res.json();
        window.toast?.error(data.error ?? "Wystąpił błąd. Spróbuj ponownie.");
        btn.disabled = false;
      }
    } catch {
      window.toast?.error("Brak połączenia z serwerem. Spróbuj ponownie.");
      btn.disabled = false;
    }
  });
})();
