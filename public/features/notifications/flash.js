(() => {
  const raw = sessionStorage.getItem('flash');
  if (!raw) return;
  sessionStorage.removeItem('flash');
  try {
    const { type, message } = JSON.parse(raw);
    if (window.toast?.[type]) window.toast[type](message);
  } catch {}
})();
