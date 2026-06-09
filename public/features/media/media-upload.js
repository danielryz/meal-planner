(() => {
  const MAX_SIZE_BYTES = 10 * 1024 * 1024;
  const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  function formatBytes(bytes) {
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
  }

  function initWidget(root) {
    const input = root.querySelector('[data-media-input]');
    const zone = root.querySelector('[data-media-zone]');
    const preview = root.querySelector('[data-media-preview]');
    const previewImg = root.querySelector('[data-media-preview-img]');
    const previewName = root.querySelector('[data-media-preview-name]');
    const previewSize = root.querySelector('[data-media-preview-size]');
    const removeBtn = root.querySelector('[data-media-remove]');
    const browseBtn = root.querySelector('[data-media-browse]');
    const errorEl = root.querySelector('[data-media-error]');

    function showError(msg) {
      root.classList.add('media-upload--has-error');
      errorEl.textContent = msg;
      errorEl.hidden = false;
    }

    function clearError() {
      root.classList.remove('media-upload--has-error');
      errorEl.hidden = true;
    }

    function showPreview(file) {
      const url = URL.createObjectURL(file);
      previewImg.src = url;
      previewImg.onload = () => URL.revokeObjectURL(url);
      previewName.textContent = file.name;
      if (previewSize) previewSize.textContent = formatBytes(file.size);
      zone.hidden = true;
      preview.hidden = false;
      root._selectedFile = file;
    }

    function clearPreview() {
      previewImg.src = '';
      zone.hidden = false;
      preview.hidden = true;
      input.value = '';
      root._selectedFile = null;
    }

    function handleFile(file) {
      clearError();

      if (!ALLOWED_TYPES.includes(file.type)) {
        showError('Nieobsługiwany format pliku. Wybierz JPG, PNG lub WebP.');
        return;
      }

      if (file.size > MAX_SIZE_BYTES) {
        showError(`Plik jest za duży (${formatBytes(file.size)}). Maksymalny rozmiar to 10 MB.`);
        return;
      }

      showPreview(file);
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
