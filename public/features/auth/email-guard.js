(() => {
  const _fetch = window.fetch.bind(window);

  window.fetch = async function (...args) {
    const res = await _fetch(...args);

    if (res.status === 403) {
      const data = await res.clone().json().catch(() => ({}));
      if (data.code === "EMAIL_NOT_VERIFIED") {
        window.toast?.error(data.error ?? "Potwierdź adres e-mail, żeby korzystać z tej funkcji.");
      }
    }

    return res;
  };
})();
