(() => {
  const form       = document.querySelector("[data-support-form]");
  const formLabel  = form?.querySelector("[data-form-label]");
  const formEmail  = form?.querySelector("[data-form-email]");
  const formError  = form?.querySelector("[data-form-error]");
  const formSubmit = form?.querySelector("[data-form-submit]");
  const formClose  = form?.querySelector("[data-form-close]");

  if (!form) return;

  let currentAmountGrosh = 0;
  let currentLabel       = "";

  document.querySelectorAll("[data-pay-btn]").forEach((btn) => {
    btn.addEventListener("click", () => {
      currentAmountGrosh  = parseInt(btn.dataset.payBtn, 10);
      currentLabel        = btn.dataset.payLabel ?? "";

      if (formLabel) formLabel.textContent = currentLabel;
      if (formError) formError.hidden = true;
      if (formEmail) formEmail.value = "";

      form.hidden = false;
      formEmail?.focus();
    });
  });

  formClose?.addEventListener("click", () => {
    form.hidden = true;
  });

  formSubmit?.addEventListener("click", async () => {
    const email = formEmail?.value.trim() ?? "";

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      if (formError) {
        formError.textContent = "Podaj poprawny adres e-mail.";
        formError.hidden = false;
      }
      formEmail?.focus();
      return;
    }

    if (formError) formError.hidden = true;
    if (formSubmit) {
      formSubmit.disabled = true;
      formSubmit.querySelector("span").textContent = "Przekierowuję…";
    }

    try {
      const res  = await fetch("/api/payments/create", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
          amountGrosh:  currentAmountGrosh,
          description:  currentLabel,
          email,
        }),
      });
      const data = await res.json();

      if (!res.ok || !data.redirectUrl) {
        throw new Error(data.error ?? "Nie udało się utworzyć płatności.");
      }

      window.location.href = data.redirectUrl;
    } catch (err) {
      if (formError) {
        formError.textContent = err.message;
        formError.hidden = false;
      }
      if (formSubmit) {
        formSubmit.disabled = false;
        formSubmit.querySelector("span").textContent = "Przejdź do płatności";
      }
    }
  });
})();
