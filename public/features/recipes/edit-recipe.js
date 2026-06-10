(() => {
  const view = document.querySelector("[data-edit-recipe-view]");

  if (!view) {
    return;
  }

  const recipeId = Number(view.dataset.recipeId) || null;

  const loadingState     = view.querySelector("[data-recipe-form-loading]");
  const errorState       = view.querySelector("[data-recipe-form-error]");
  const form             = view.querySelector("[data-recipe-form]");
  const titleInput       = view.querySelector("[data-title]");
  const descriptionInput = view.querySelector("[data-description]");
  const categorySelect   = view.querySelector("[data-category]");
  const difficultySelect = view.querySelector("[data-difficulty]");
  const prepTimeInput    = view.querySelector("[data-prep-time]");
  const servingsInput    = view.querySelector("[data-servings]");
  const ingredientsList  = view.querySelector("[data-ingredients-list]");
  const stepsList        = view.querySelector("[data-steps-list]");
  const ingredientsCount = view.querySelector("[data-ingredients-count]");
  const stepsCount       = view.querySelector("[data-steps-count]");
  const message          = view.querySelector("[data-recipe-form-message]");
  const titleError       = view.querySelector("[data-title-error]");
  const descriptionError = view.querySelector("[data-description-error]");
  const statusLabel      = view.querySelector("[data-edit-status-label]");
  const submitReviewBtn  = view.querySelector("[data-submit-review]");

  const videoTabs        = view.querySelectorAll("[data-video-tab]");
  const videoUrlPane     = view.querySelector('[data-video-pane="url"]');
  const videoFilePane    = view.querySelector('[data-video-pane="file"]');
  const videoUrlInput    = view.querySelector("[data-video-url-input]");
  const videoEmbed       = view.querySelector("[data-video-embed]");
  const videoIframe      = view.querySelector("[data-video-iframe]");
  const videoUrlClear    = view.querySelector("[data-video-url-clear]");
  const videoFileInput   = view.querySelector("[data-video-file-input]");
  const videoBrowse      = view.querySelector("[data-video-browse]");
  const videoZone        = view.querySelector("[data-video-zone]");
  const videoUploading   = view.querySelector("[data-video-uploading]");
  const videoPreview     = view.querySelector("[data-video-preview]");
  const videoPlayer      = view.querySelector("[data-video-player]");
  const videoPreviewName = view.querySelector("[data-video-preview-name]");
  const videoFileClear   = view.querySelector("[data-video-file-clear]");
  const videoError       = view.querySelector("[data-video-error]");

  let submitMode    = "draft";
  let videoUrl      = null;
  let videoMediaId  = null;
  let videoDebounce = null;

  const STATUS_LABELS = {
    draft:              "Szkic prywatny",
    changes_requested:  "Do poprawy",
    submitted:          "W weryfikacji",
    approved:           "Publiczny",
    rejected:           "Odrzucony",
  };

  const VIDEO_ALLOWED_TYPES = ["video/mp4", "video/quicktime", "video/webm"];
  const VIDEO_MAX_MB = 500;

  if (!recipeId) {
    loadingState.hidden = true;
    errorState.hidden = false;
    return;
  }

  function escapeHtml(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function fillSelect(select, options, selectedValue) {
    select.innerHTML = options
      .map((o) => `<option value="${escapeHtml(o.id)}" ${o.id === selectedValue ? "selected" : ""}>${escapeHtml(o.label)}</option>`)
      .join("");
  }

  function updateCounts() {
    ingredientsCount.textContent = ingredientsList.querySelectorAll("[data-ingredient-row]").length;
    stepsCount.textContent = stepsList.querySelectorAll("[data-step-row]").length;
  }

  function createIngredientRow(name = "", amount = "") {
    const row = document.createElement("div");
    row.className = "add-recipe-repeat-row";
    row.dataset.ingredientRow = "";
    row.innerHTML = `
      <label>
        <span>Składnik</span>
        <input name="ingredientName[]" type="text" value="${escapeHtml(name)}" placeholder="np. pomidory" />
      </label>
      <label>
        <span>Ilość</span>
        <input name="ingredientAmount[]" type="text" value="${escapeHtml(amount)}" placeholder="np. 400 g" />
      </label>
      <button type="button" data-remove-row aria-label="Usuń składnik">
        <img src="/public/assets/icons/x.svg" alt="" />
      </button>
    `;
    ingredientsList.append(row);
    updateCounts();
  }

  function createStepRow(description = "") {
    const row = document.createElement("div");
    row.className = "add-recipe-repeat-row add-recipe-repeat-row--step";
    row.dataset.stepRow = "";
    row.innerHTML = `
      <label>
        <span>Krok</span>
        <textarea name="steps[]" rows="3" placeholder="Opisz krok przygotowania">${escapeHtml(description)}</textarea>
      </label>
      <button type="button" data-remove-row aria-label="Usuń krok">
        <img src="/public/assets/icons/x.svg" alt="" />
      </button>
    `;
    stepsList.append(row);
    updateCounts();
  }

  function setFieldState(input, error, isValid) {
    input.classList.toggle("is-invalid", !isValid);
    error.hidden = isValid;
  }

  function showMessage(text) {
    message.textContent = text;
    message.hidden = false;
    window.setTimeout(() => { message.hidden = true; }, 2400);
  }

  function collectIngredients() {
    return Array.from(ingredientsList.querySelectorAll("[data-ingredient-row]"))
      .map((row) => ({
        name: (row.querySelector("input[name='ingredientName[]']")?.value ?? "").trim(),
        amount: (row.querySelector("input[name='ingredientAmount[]']")?.value ?? "").trim(),
      }))
      .filter((i) => i.name && i.amount);
  }

  function collectSteps() {
    return Array.from(stepsList.querySelectorAll("[data-step-row]"))
      .map((row) => ({
        instruction: (row.querySelector("textarea")?.value ?? "").trim(),
      }))
      .filter((s) => s.instruction);
  }

  // --- Video ---

  function parseVideoUrl(raw) {
    const ytMatch = raw.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
    if (ytMatch) return `https://www.youtube.com/embed/${ytMatch[1]}`;
    const vimeoMatch = raw.match(/vimeo\.com\/(\d+)/);
    if (vimeoMatch) return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
    return null;
  }

  function switchVideoTab(mode) {
    videoTabs.forEach((tab) => {
      const active = tab.dataset.videoTab === mode;
      tab.classList.toggle("is-active", active);
      tab.setAttribute("aria-selected", String(active));
    });
    if (videoUrlPane) videoUrlPane.hidden = mode !== "url";
    if (videoFilePane) videoFilePane.hidden = mode !== "file";
    if (mode === "url") clearVideoFile();
    else clearVideoUrl();
  }

  function clearVideoUrl() {
    videoUrl = null;
    if (videoUrlInput) videoUrlInput.value = "";
    if (videoEmbed) videoEmbed.hidden = true;
    if (videoIframe) videoIframe.src = "";
  }

  function clearVideoFile() {
    videoMediaId = null;
    if (videoFileInput) videoFileInput.value = "";
    if (videoZone) videoZone.hidden = false;
    if (videoPreview) videoPreview.hidden = true;
    if (videoPlayer) videoPlayer.src = "";
    if (videoUploading) videoUploading.hidden = true;
    if (videoError) videoError.hidden = true;
  }

  async function handleVideoFile(file) {
    if (videoError) videoError.hidden = true;
    if (!VIDEO_ALLOWED_TYPES.includes(file.type)) {
      if (videoError) { videoError.textContent = "Nieobsługiwany format pliku. Wybierz MP4, MOV lub WebM."; videoError.hidden = false; }
      return;
    }
    if (file.size > VIDEO_MAX_MB * 1024 * 1024) {
      if (videoError) { videoError.textContent = `Plik jest za duży. Maksymalny rozmiar to ${VIDEO_MAX_MB} MB.`; videoError.hidden = false; }
      return;
    }
    if (videoUploading) videoUploading.hidden = false;
    if (videoZone) videoZone.hidden = true;
    try {
      const fd = new FormData();
      fd.append("video", file);
      const res  = await fetch("/api/media/recipe-videos", { method: "POST", body: fd });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.error || "Nie udało się przesłać wideo.");
      videoMediaId = data.mediaId ?? null;
      if (videoUploading) videoUploading.hidden = true;
      if (videoPlayer) videoPlayer.src = data.url;
      if (videoPreviewName) videoPreviewName.textContent = file.name;
      if (videoPreview) videoPreview.hidden = false;
      if (window.toast) window.toast.success("Wideo przesłane pomyślnie.");
    } catch (err) {
      if (videoUploading) videoUploading.hidden = true;
      if (videoZone) videoZone.hidden = false;
      if (videoError) { videoError.textContent = err.message || "Nie udało się przesłać wideo."; videoError.hidden = false; }
    }
  }

  videoTabs.forEach((tab) => tab.addEventListener("click", () => switchVideoTab(tab.dataset.videoTab)));

  videoUrlInput?.addEventListener("input", () => {
    clearTimeout(videoDebounce);
    videoDebounce = setTimeout(() => {
      const raw = videoUrlInput.value.trim();
      if (!raw) { videoUrl = null; if (videoEmbed) videoEmbed.hidden = true; if (videoIframe) videoIframe.src = ""; return; }
      const embedSrc = parseVideoUrl(raw);
      if (embedSrc) {
        videoUrl = raw;
        if (videoIframe) videoIframe.src = embedSrc;
        if (videoEmbed) videoEmbed.hidden = false;
      } else {
        videoUrl = null;
        if (videoEmbed) videoEmbed.hidden = true;
        if (videoIframe) videoIframe.src = "";
      }
    }, 500);
  });

  videoUrlClear?.addEventListener("click", clearVideoUrl);
  videoBrowse?.addEventListener("click", () => videoFileInput?.click());
  videoZone?.addEventListener("click", (e) => { if (e.target !== videoBrowse) videoFileInput?.click(); });
  videoZone?.addEventListener("dragover", (e) => { e.preventDefault(); videoZone.classList.add("media-upload__zone--dragover"); });
  videoZone?.addEventListener("dragleave", () => videoZone.classList.remove("media-upload__zone--dragover"));
  videoZone?.addEventListener("drop", (e) => { e.preventDefault(); videoZone.classList.remove("media-upload__zone--dragover"); const f = e.dataTransfer.files[0]; if (f) handleVideoFile(f); });
  videoFileInput?.addEventListener("change", () => { if (videoFileInput.files[0]) handleVideoFile(videoFileInput.files[0]); });
  videoFileClear?.addEventListener("click", clearVideoFile);

  // --- Form ---

  async function loadForm() {
    try {
      const [optRes, recipeRes] = await Promise.all([
        fetch("/api/recipes/form-options"),
        fetch(`/api/recipes/${recipeId}`),
      ]);

      if (!optRes.ok || !recipeRes.ok) {
        throw new Error("load failed");
      }

      const opts   = await optRes.json();
      const recipe = await recipeRes.json();

      fillSelect(categorySelect, opts.categories, recipe.category ? opts.categories.find((c) => c.label === recipe.category)?.id ?? "" : "");
      fillSelect(difficultySelect, opts.difficulties, recipe.difficulty ?? "easy");

      titleInput.value       = recipe.title ?? "";
      descriptionInput.value = recipe.description ?? "";
      prepTimeInput.value    = recipe.prepTimeMinutes ?? 30;
      servingsInput.value    = recipe.servings ?? 2;

      if (statusLabel) {
        statusLabel.textContent = STATUS_LABELS[recipe.status] ?? recipe.status;
      }

      if (submitReviewBtn) {
        submitReviewBtn.hidden = !["draft", "changes_requested"].includes(recipe.status ?? "");
      }

      (recipe.ingredients ?? []).forEach((ing) => createIngredientRow(ing.name ?? "", ing.amount ?? ""));
      if ((recipe.ingredients ?? []).length === 0) createIngredientRow();

      (recipe.steps ?? []).forEach((s) => createStepRow(s.instruction ?? ""));
      if ((recipe.steps ?? []).length === 0) createStepRow();

      if (recipe.videoUrl) {
        videoUrl = recipe.videoUrl;
        if (videoUrlInput) videoUrlInput.value = recipe.videoUrl;
        const embedSrc = parseVideoUrl(recipe.videoUrl);
        if (embedSrc && videoIframe && videoEmbed) {
          videoIframe.src = embedSrc;
          videoEmbed.hidden = false;
        }
      }

      loadingState.hidden = true;
      errorState.hidden   = true;
      form.hidden         = false;
    } catch {
      loadingState.hidden = true;
      errorState.hidden   = false;
    }
  }

  view.querySelector("[data-add-ingredient]")?.addEventListener("click", () => createIngredientRow());
  view.querySelector("[data-add-step]")?.addEventListener("click", () => createStepRow());

  form?.addEventListener("click", (event) => {
    const removeButton = event.target.closest("[data-remove-row]");
    const submitButton = event.target.closest("[data-submit-mode]");
    if (removeButton) { removeButton.closest(".add-recipe-repeat-row")?.remove(); updateCounts(); }
    if (submitButton) submitMode = submitButton.dataset.submitMode ?? "draft";
  });

  titleInput?.addEventListener("blur", () => setFieldState(titleInput, titleError, titleInput.value.trim().length > 0));
  descriptionInput?.addEventListener("blur", () => setFieldState(descriptionInput, descriptionError, descriptionInput.value.trim().length >= 20));

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();

    const validTitle       = titleInput.value.trim().length > 0;
    const validDescription = descriptionInput.value.trim().length >= 20;
    setFieldState(titleInput, titleError, validTitle);
    setFieldState(descriptionInput, descriptionError, validDescription);
    if (!validTitle || !validDescription) return;

    const mediaWidget = view.querySelector("[data-media-upload]");
    const payload = {
      title:           titleInput.value.trim(),
      description:     descriptionInput.value.trim(),
      categoryCode:    categorySelect.value,
      difficulty:      difficultySelect.value,
      prepTimeMinutes: parseInt(prepTimeInput.value, 10) || 30,
      servings:        parseInt(servingsInput.value, 10) || 2,
      ingredients:     collectIngredients(),
      steps:           collectSteps(),
      mediaId:         mediaWidget?._mediaId ?? null,
      videoUrl:        videoUrl || null,
      videoMediaId:    videoMediaId || null,
    };

    try {
      const updateRes = await fetch(`/api/recipes/${recipeId}`, {
        method:  "PUT",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify(payload),
      });

      const updateData = await updateRes.json();

      if (!updateRes.ok) {
        showMessage(updateData.error ?? "Wystąpił błąd podczas zapisu.");
        return;
      }

      if (submitMode === "review") {
        const reviewRes = await fetch(`/api/recipes/${recipeId}/submit-for-review`, {
          method:  "POST",
          headers: { "Content-Type": "application/json" },
        });

        if (!reviewRes.ok) {
          showMessage("Przepis zapisany, ale nie udało się wysłać do weryfikacji.");
          return;
        }

        sessionStorage.setItem("flash", JSON.stringify({ type: "success", message: "Przepis zaktualizowany i wysłany do weryfikacji." }));
      } else {
        sessionStorage.setItem("flash", JSON.stringify({ type: "success", message: "Zmiany zapisane." }));
      }

      window.location.href = "/recipe-management";
    } catch {
      showMessage("Błąd połączenia z serwerem.");
    }
  });

  loadForm();
})();
