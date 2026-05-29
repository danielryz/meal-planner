(() => {
  const view = document.querySelector("[data-add-recipe-view]");
  const formUrl = "/public/features/recipes/add_recipe_form_mock.json";

  if (!view) {
    return;
  }

  const loadingState = view.querySelector("[data-recipe-form-loading]");
  const errorState = view.querySelector("[data-recipe-form-error]");
  const form = view.querySelector("[data-recipe-form]");
  const titleInput = view.querySelector("[data-title]");
  const descriptionInput = view.querySelector("[data-description]");
  const categorySelect = view.querySelector("[data-category]");
  const difficultySelect = view.querySelector("[data-difficulty]");
  const prepTimeInput = view.querySelector("[data-prep-time]");
  const servingsInput = view.querySelector("[data-servings]");
  const ingredientsList = view.querySelector("[data-ingredients-list]");
  const stepsList = view.querySelector("[data-steps-list]");
  const ingredientsCount = view.querySelector("[data-ingredients-count]");
  const stepsCount = view.querySelector("[data-steps-count]");
  const message = view.querySelector("[data-recipe-form-message]");
  const titleError = view.querySelector("[data-title-error]");
  const descriptionError = view.querySelector("[data-description-error]");
  let submitMode = "draft";

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
      .map((option) => `<option value="${escapeHtml(option.id)}" ${option.id === selectedValue ? "selected" : ""}>${escapeHtml(option.label)}</option>`)
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
    window.setTimeout(() => {
      message.hidden = true;
    }, 2400);
  }

  async function loadForm() {
    try {
      const response = await fetch(formUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      fillSelect(categorySelect, data.categories, data.defaults.category);
      fillSelect(difficultySelect, data.difficulties, data.defaults.difficulty);
      prepTimeInput.value = data.defaults.prepTimeMinutes;
      servingsInput.value = data.defaults.servings;
      data.defaults.ingredients.forEach((ingredient) => createIngredientRow(ingredient.name, ingredient.amount));
      data.defaults.steps.forEach(createStepRow);
      loadingState.hidden = true;
      errorState.hidden = true;
      form.hidden = false;
    } catch (error) {
      loadingState.hidden = true;
      errorState.hidden = false;
      form.hidden = true;
    }
  }

  view.querySelector("[data-add-ingredient]")?.addEventListener("click", () => createIngredientRow());
  view.querySelector("[data-add-step]")?.addEventListener("click", () => createStepRow());

  form?.addEventListener("click", (event) => {
    const removeButton = event.target.closest("[data-remove-row]");
    const submitButton = event.target.closest("[data-submit-mode]");

    if (removeButton) {
      removeButton.closest(".add-recipe-repeat-row")?.remove();
      updateCounts();
    }

    if (submitButton) {
      submitMode = submitButton.dataset.submitMode ?? "draft";
    }
  });

  titleInput?.addEventListener("blur", () => {
    setFieldState(titleInput, titleError, titleInput.value.trim().length > 0);
  });

  descriptionInput?.addEventListener("blur", () => {
    setFieldState(descriptionInput, descriptionError, descriptionInput.value.trim().length >= 20);
  });

  form?.addEventListener("submit", (event) => {
    event.preventDefault();
    const validTitle = titleInput.value.trim().length > 0;
    const validDescription = descriptionInput.value.trim().length >= 20;
    setFieldState(titleInput, titleError, validTitle);
    setFieldState(descriptionInput, descriptionError, validDescription);

    if (!validTitle || !validDescription) {
      return;
    }

    showMessage(submitMode === "review" ? "Przepis wysłany lokalnie do weryfikacji." : "Szkic przepisu zapisany lokalnie.");
  });

  loadForm();
})();
