(() => {
  const view = document.querySelector("[data-preferences-view]");
  const preferencesUrl = "/public/mock/preferences_mock.json";

  if (!view) {
    return;
  }

  const loadingState = view.querySelector("[data-preferences-loading]");
  const errorState = view.querySelector("[data-preferences-error]");
  const content = view.querySelector("[data-preferences-content]");
  const dietSummary = view.querySelector("[data-preferences-diet]");
  const summary = view.querySelector("[data-preferences-summary]");
  const servingsSummary = view.querySelector("[data-preferences-servings]");
  const mealsSummary = view.querySelector("[data-preferences-meals]");
  const budgetSummary = view.querySelector("[data-preferences-budget]");
  const dietOptions = view.querySelector("[data-diet-options]");
  const allergyOptions = view.querySelector("[data-allergy-options]");
  const cuisineOptions = view.querySelector("[data-cuisine-options]");
  const servingsInput = view.querySelector("[data-servings-input]");
  const mealsInput = view.querySelector("[data-meals-input]");
  const budgetInput = view.querySelector("[data-budget-input]");
  const form = view.querySelector("[data-preferences-form]");
  const message = view.querySelector("[data-preferences-message]");
  let preferences = null;

  function escapeHtml(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function formatMoney(value) {
    return `${Number(value).toFixed(0)} zł`;
  }

  function getDietLabel() {
    return preferences.options.diets.find((diet) => diet.id === preferences.selected.diet)?.label ?? "Nie wybrano";
  }

  function updateSummary() {
    dietSummary.textContent = getDietLabel();
    summary.textContent = preferences.summary;
    servingsSummary.textContent = `${preferences.selected.servings} porcje`;
    mealsSummary.textContent = `${preferences.selected.mealsPerDay} dziennie`;
    budgetSummary.textContent = formatMoney(preferences.selected.weeklyBudget);
  }

  function createDietOption(option) {
    const checked = preferences.selected.diet === option.id ? "checked" : "";

    return `
      <label class="preferences-diet-option">
        <input type="radio" name="diet" value="${escapeHtml(option.id)}" ${checked} />
        <span>
          <strong>${escapeHtml(option.label)}</strong>
          <small>${escapeHtml(option.description)}</small>
        </span>
      </label>
    `;
  }

  function createChip(option, name, selectedIds) {
    const checked = selectedIds.includes(option.id) ? "checked" : "";

    return `
      <label class="preferences-chip">
        <input type="checkbox" name="${escapeHtml(name)}" value="${escapeHtml(option.id)}" ${checked} />
        <span>${escapeHtml(option.label)}</span>
      </label>
    `;
  }

  function render() {
    dietOptions.innerHTML = preferences.options.diets.map(createDietOption).join("");
    allergyOptions.innerHTML = preferences.options.allergies
      .map((option) => createChip(option, "allergies", preferences.selected.allergies))
      .join("");
    cuisineOptions.innerHTML = preferences.options.cuisines
      .map((option) => createChip(option, "cuisines", preferences.selected.cuisines))
      .join("");
    servingsInput.value = preferences.selected.servings;
    mealsInput.value = preferences.selected.mealsPerDay;
    budgetInput.value = preferences.selected.weeklyBudget;
    updateSummary();
  }

  async function loadPreferences() {
    try {
      const response = await fetch(preferencesUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      preferences = await response.json();
      render();
      loadingState.hidden = true;
      errorState.hidden = true;
      content.hidden = false;
    } catch (error) {
      loadingState.hidden = true;
      errorState.hidden = false;
      content.hidden = true;
    }
  }

  form?.addEventListener("change", (event) => {
    const target = event.target;

    if (target.name === "diet") {
      preferences.selected.diet = target.value;
    }

    if (target.name === "allergies") {
      preferences.selected.allergies = Array.from(form.querySelectorAll('input[name="allergies"]:checked')).map((input) => input.value);
    }

    if (target.name === "cuisines") {
      preferences.selected.cuisines = Array.from(form.querySelectorAll('input[name="cuisines"]:checked')).map((input) => input.value);
    }

    if (target === servingsInput) {
      preferences.selected.servings = Number(servingsInput.value);
    }

    if (target === mealsInput) {
      preferences.selected.mealsPerDay = Number(mealsInput.value);
    }

    if (target === budgetInput) {
      preferences.selected.weeklyBudget = Number(budgetInput.value);
    }

    updateSummary();
  });

  form?.addEventListener("submit", (event) => {
    event.preventDefault();
    message.textContent = "Preferencje zapisane lokalnie.";
    message.hidden = false;
    window.setTimeout(() => {
      message.hidden = true;
    }, 2200);
  });

  loadPreferences();
})();
