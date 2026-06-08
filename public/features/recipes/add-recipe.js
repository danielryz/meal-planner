(() => {
  const view = document.querySelector("[data-add-recipe-view]");

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

  async function loadForm() {
    try {
      const response = await fetch("/api/recipes/form-options");

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      fillSelect(categorySelect, data.categories, "dinner");
      fillSelect(difficultySelect, data.difficulties, "easy");
      prepTimeInput.value = 30;
      servingsInput.value = 2;
      createIngredientRow();
      createStepRow();
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

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();

    const validTitle = titleInput.value.trim().length > 0;
    const validDescription = descriptionInput.value.trim().length >= 20;
    setFieldState(titleInput, titleError, validTitle);
    setFieldState(descriptionInput, descriptionError, validDescription);

    if (!validTitle || !validDescription) {
      return;
    }

    const payload = {
      title: titleInput.value.trim(),
      description: descriptionInput.value.trim(),
      categoryCode: categorySelect.value,
      difficulty: difficultySelect.value,
      prepTimeMinutes: parseInt(prepTimeInput.value, 10) || 30,
      servings: parseInt(servingsInput.value, 10) || 2,
      ingredients: collectIngredients(),
      steps: collectSteps(),
    };

    try {
      const draftRes = await fetch("/api/recipes/drafts", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const draftData = await draftRes.json();

      if (!draftRes.ok) {
        showMessage(draftData.error ?? "Wystąpił błąd podczas zapisu.");
        return;
      }

      if (submitMode === "review") {
        await fetch(`/api/recipes/${draftData.recipeId}/submit-for-review`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
        });
        showMessage("Przepis wysłany do weryfikacji.");
      } else {
        showMessage("Szkic przepisu zapisany.");
      }
    } catch {
      showMessage("Błąd połączenia z serwerem.");
    }
  });

  loadForm();
})();
