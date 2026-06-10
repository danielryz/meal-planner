(() => {
  const view = document.querySelector("[data-preferences-view]");

  if (!view) {
    return;
  }

  const loadingState   = view.querySelector("[data-preferences-loading]");
  const errorState     = view.querySelector("[data-preferences-error]");
  const content        = view.querySelector("[data-preferences-content]");
  const dietSummaryEl  = view.querySelector("[data-preferences-diet]");
  const summaryEl      = view.querySelector("[data-preferences-summary]");
  const servingsSumEl  = view.querySelector("[data-preferences-servings]");
  const mealsSumEl     = view.querySelector("[data-preferences-meals]");
  const budgetSumEl    = view.querySelector("[data-preferences-budget]");
  const dietOptions    = view.querySelector("[data-diet-options]");
  const allergyOptions = view.querySelector("[data-allergy-options]");
  const cuisineOptions = view.querySelector("[data-cuisine-options]");
  const servingsInput  = view.querySelector("[data-servings-input]");
  const mealsInput     = view.querySelector("[data-meals-input]");
  const budgetInput    = view.querySelector("[data-budget-input]");
  const form           = view.querySelector("[data-preferences-form]");

  const CUISINE_OPTIONS = [
    { code: "polish",    label: "Polska" },
    { code: "italian",   label: "Włoska" },
    { code: "asian",     label: "Azjatycka" },
    { code: "mexican",   label: "Meksykańska" },
    { code: "french",    label: "Francuska" },
    { code: "american",  label: "Amerykańska" },
    { code: "greek",     label: "Grecka" },
    { code: "spanish",   label: "Hiszpańska" },
  ];

  let selected = {
    diet:      null,
    allergies: [],
    cuisines:  [],
    servings:  2,
    meals:     3,
    budget:    null,
  };

  let dietTypes   = [];
  let allergyTypes = [];

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function formatBudget(cents) {
    if (!cents) return "—";
    return `${(cents / 100).toFixed(0)} zł`;
  }

  function getDietLabel() {
    return dietTypes.find((d) => d.code === selected.diet)?.label ?? "Bez ograniczeń";
  }

  function updateSummary() {
    if (dietSummaryEl) dietSummaryEl.textContent = getDietLabel();
    if (summaryEl)     summaryEl.textContent     = selected.allergies.length > 0
      ? `${selected.allergies.length} ${selected.allergies.length === 1 ? "alergen" : "alergenów"} wykluczonych`
      : "Bez wykluczeń";
    if (servingsSumEl) servingsSumEl.textContent = `${selected.servings} porcje`;
    if (mealsSumEl)    mealsSumEl.textContent    = `${selected.meals} dziennie`;
    if (budgetSumEl)   budgetSumEl.textContent   = formatBudget(selected.budget);
  }

  function createDietOption(dt) {
    const checked = selected.diet === dt.code ? "checked" : "";
    return `
      <label class="preferences-diet-option">
        <input type="radio" name="diet" value="${escapeHtml(dt.code)}" ${checked} />
        <span>
          <strong>${escapeHtml(dt.label)}</strong>
        </span>
      </label>
    `;
  }

  function createChip(code, label, name, selectedCodes) {
    const checked = selectedCodes.includes(code) ? "checked" : "";
    return `
      <label class="preferences-chip">
        <input type="checkbox" name="${escapeHtml(name)}" value="${escapeHtml(code)}" ${checked} />
        <span>${escapeHtml(label)}</span>
      </label>
    `;
  }

  function render() {
    if (dietOptions)    dietOptions.innerHTML    = dietTypes.map(createDietOption).join("");
    if (allergyOptions) allergyOptions.innerHTML = allergyTypes.map((a) => createChip(a.code, a.label, "allergies", selected.allergies)).join("");
    if (cuisineOptions) cuisineOptions.innerHTML = CUISINE_OPTIONS.map((c) => createChip(c.code, c.label, "cuisines", selected.cuisines)).join("");
    if (servingsInput)  servingsInput.value      = selected.servings;
    if (mealsInput)     mealsInput.value         = selected.meals;
    if (budgetInput)    budgetInput.value        = selected.budget ? (selected.budget / 100).toFixed(0) : "";
    updateSummary();
  }

  async function loadPreferences() {
    try {
      const [optRes, prefRes] = await Promise.all([
        fetch("/api/settings/preference-options"),
        fetch("/api/settings/preferences"),
      ]);

      if (!optRes.ok) throw new Error(`HTTP ${optRes.status}`);

      const opts = await optRes.json();
      const pref = prefRes.ok ? await prefRes.json() : {};

      dietTypes    = opts.diets    ?? [];
      allergyTypes = opts.allergies ?? [];

      selected = {
        diet:      pref.diet_type   ?? null,
        allergies: pref.allergies   ?? [],
        cuisines:  [],
        servings:  pref.default_servings      ?? 2,
        meals:     pref.meals_per_day         ?? 3,
        budget:    pref.weekly_budget_cents   ?? null,
      };

      render();
      loadingState.hidden = true;
      errorState.hidden   = true;
      content.hidden      = false;
    } catch {
      loadingState.hidden = true;
      errorState.hidden   = false;
      content.hidden      = true;
    }
  }

  form?.addEventListener("change", (event) => {
    const { name, value, checked } = event.target;

    if (name === "diet") {
      selected.diet = value;
    } else if (name === "allergies") {
      if (checked) {
        selected.allergies = [...new Set([...selected.allergies, value])];
      } else {
        selected.allergies = selected.allergies.filter((c) => c !== value);
      }
    } else if (name === "cuisines") {
      if (checked) {
        selected.cuisines = [...new Set([...selected.cuisines, value])];
      } else {
        selected.cuisines = selected.cuisines.filter((c) => c !== value);
      }
    } else if (event.target === servingsInput) {
      selected.servings = parseInt(servingsInput.value, 10) || 2;
    } else if (event.target === mealsInput) {
      selected.meals = parseInt(mealsInput.value, 10) || 3;
    } else if (event.target === budgetInput) {
      const zloty = parseFloat(budgetInput.value);
      selected.budget = isNaN(zloty) || zloty <= 0 ? null : Math.round(zloty * 100);
    }

    updateSummary();
  });

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();

    try {
      const res = await fetch("/api/settings/preferences", {
        method:  "PATCH",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({
          dietType:            selected.diet,
          defaultServings:     selected.servings,
          mealsPerDay:         selected.meals,
          weeklyBudgetCents:   selected.budget,
          dislikedIngredients: null,
          allergies:           selected.allergies,
        }),
      });

      if (res.ok) {
        if (window.toast) window.toast.success("Preferencje żywieniowe zapisane.");
      } else {
        if (window.toast) window.toast.error("Nie udało się zapisać preferencji. Spróbuj ponownie.");
      }
    } catch {
      if (window.toast) window.toast.error("Błąd połączenia z serwerem.");
    }
  });

  loadPreferences();
})();
