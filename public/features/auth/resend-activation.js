(() => {
  const btn = document.querySelector('[data-resend-btn]');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    btn.textContent = 'Wysyłanie…';

    try {
      const res = await fetch('/api/auth/resend-activation', { method: 'POST' });
      const data = await res.json();

      if (res.ok) {
        window.toast.success('Link aktywacyjny wysłany. Sprawdź skrzynkę e-mail.');
        btn.textContent = 'Link wysłany';
      } else {
        window.toast.error(data.error ?? 'Wystąpił błąd. Spróbuj ponownie.');
        btn.disabled = false;
        btn.textContent = 'Wyślij ponownie link aktywacyjny';
      }
    } catch {
      window.toast.error('Brak połączenia z serwerem.');
      btn.disabled = false;
      btn.textContent = 'Wyślij ponownie link aktywacyjny';
    }
  });
})();
