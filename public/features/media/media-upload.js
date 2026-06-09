(() => {
  const MAX_SIZE_BYTES = 10 * 1024 * 1024;
  const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  function formatBytes(bytes) {
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
  }

  function initWidget(root) {
    const input      = root.querySelector('[data-media-input]');
    const zone       = root.querySelector('[data-media-zone]');
    const preview    = root.querySelector('[data-media-preview]');
    const previewImg = root.querySelector('[data-media-preview-img]');
    const previewName = root.querySelector('[data-media-preview-name]');
    const previewSize = root.querySelector('[data-media-preview-size]');
    const removeBtn  = root.querySelector('[data-media-remove]');
    const browseBtn  = root.querySelector('[data-media-browse]');
    const errorEl    = root.querySelector('[data-media-error]');
    const uploadingEl = root.querySelector('[data-media-uploading]');
    const endpoint   = root.dataset.mediaEndpoint || null;

    root._mediaId  = null;
    root._mediaUrl = null;

    function showError(msg) {
      root.classList.add('media-upload--has-error');
      errorEl.textContent = msg;
      errorEl.hidden = false;
    }

    function clearError() {
      root.classList.remove('media-upload--has-error');
      errorEl.hidden = true;
    }

    function setUploading(active) {
      if (uploadingEl) uploadingEl.hidden = !active;
      zone.hidden = active;
    }

    function showPreview(src, name, size) {
      previewImg.src = src;
      previewName.textContent = name;
      if (previewSize) previewSize.textContent = size ? formatBytes(size) : '';
      zone.hidden = true;
      preview.hidden = false;
    }

    function clearPreview() {
      previewImg.src = '';
      zone.hidden = false;
      preview.hidden = true;
      if (uploadingEl) uploadingEl.hidden = true;
      input.value = '';
      root._mediaId  = null;
      root._mediaUrl = null;
    }

    function validateLocally(file) {
      if (!ALLOWED_TYPES.includes(file.type)) {
        showError('Nieobsługiwany format pliku. Wybierz JPG, PNG lub WebP.');
        return false;
      }
      if (file.size > MAX_SIZE_BYTES) {
        showError(`Plik jest za duży (${formatBytes(file.size)}). Maksymalny rozmiar to 10 MB.`);
        return false;
      }
      return true;
    }

    async function handleFile(file) {
      clearError();

      if (!validateLocally(file)) return;

      if (!endpoint) {
        const url = URL.createObjectURL(file);
        showPreview(url, file.name, file.size);
        previewImg.onload = () => URL.revokeObjectURL(url);
        return;
      }

      setUploading(true);

      try {
        const formData = new FormData();
        formData.append('photo', file);

        const res = await fetch(endpoint, { method: 'POST', body: formData });
        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
          throw new Error(data.error || 'Błąd przesyłania pliku.');
        }

        root._mediaId  = data.mediaId ?? null;
        root._mediaUrl = data.url ?? null;

        setUploading(false);
        showPreview(data.url, file.name, file.size);

        if (window.toast) window.toast.success('Plik przesłany pomyślnie.');
      } catch (err) {
        setUploading(false);
        showError(err.message);
      }
    }

    browseBtn.addEventListener('click', () => input.click());
    zone.addEventListener('click', (e) => {
      if (e.target !== browseBtn) input.click();
    });

    input.addEventListener('change', () => {
      if (input.files[0]) handleFile(input.files[0]);
    });

    removeBtn.addEventListener('click', () => {
      clearPreview();
      clearError();
    });

    zone.addEventListener('dragover', (e) => {
      e.preventDefault();
      zone.classList.add('media-upload__zone--dragover');
    });

    zone.addEventListener('dragleave', () => {
      zone.classList.remove('media-upload__zone--dragover');
    });

    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      zone.classList.remove('media-upload__zone--dragover');
      const file = e.dataTransfer.files[0];
      if (file) handleFile(file);
    });
  }

  document.querySelectorAll('[data-media-upload]').forEach(initWidget);
})();
