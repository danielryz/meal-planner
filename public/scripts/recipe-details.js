(() => {
  const view = document.querySelector("[data-recipe-details-view]");
  const detailsUrl = "/public/mock/recipe_details_mock.json";

  if (!view) {
    return;
  }

  const loadingState = view.querySelector("[data-recipe-loading]");
  const errorState = view.querySelector("[data-recipe-error]");
  const content = view.querySelector("[data-recipe-content]");
  const hero = view.querySelector("[data-recipe-hero]");
  const label = view.querySelector("[data-recipe-label]");
  const title = view.querySelector("[data-recipe-title]");
  const description = view.querySelector("[data-recipe-description]");
  const servings = view.querySelector("[data-recipe-servings]");
  const prepTime = view.querySelector("[data-recipe-prep-time]");
  const cookTime = view.querySelector("[data-recipe-cook-time]");
  const calories = view.querySelector("[data-recipe-calories]");
  const ingredientsCount = view.querySelector("[data-ingredients-count]");
  const ingredientsList = view.querySelector("[data-ingredients-list]");
  const nutritionList = view.querySelector("[data-nutrition-list]");
  const chefTip = view.querySelector("[data-chef-tip]");
  const stepsList = view.querySelector("[data-steps-list]");
  const relatedList = view.querySelector("[data-related-list]");
  const decreaseButton = view.querySelector("[data-servings-decrease]");
  const increaseButton = view.querySelector("[data-servings-increase]");
  const addToPlanButton = view.querySelector("[data-add-to-plan]");
  const addToGroceryButton = view.querySelector("[data-add-to-grocery]");
  let currentRecipe = null;
  let currentServings = 1;

  function escapeHtml(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function getRecipeId() {
    const params = new URLSearchParams(window.location.search);
    return Number(params.get("id") || 1);
  }

  function getServingLabel(value) {
    if (value === 1) {
      return "1 porcja";
    }

    if (value > 1 && value < 5) {
      return `${value} porcje`;
    }

    return `${value} porcji`;
  }

  function setActionFeedback(button, text) {
    const label = button.querySelector("span");
    const previousText = label?.textContent ?? button.textContent.trim();

    if (label) {
      label.textContent = text;
    }
    button.classList.add("is-confirmed");

    window.setTimeout(() => {
      button.classList.remove("is-confirmed");
      if (label) {
        label.textContent = previousText;
      }
    }, 1600);
  }

  function renderServings() {
    servings.textContent = getServingLabel(currentServings);
    decreaseButton.disabled = currentServings <= 1;
  }

  function renderIngredients(recipe) {
    ingredientsCount.textContent = `${recipe.ingredients.length} składników`;
    ingredientsList.innerHTML = recipe.ingredients
      .map((ingredient) => `
        <li>
          <span aria-hidden="true"></span>
          <strong>${escapeHtml(ingredient.name)}</strong>
          <small>${escapeHtml(ingredient.amount)}</small>
        </li>
      `)
      .join("");
  }

  function renderSteps(recipe) {
    stepsList.innerHTML = recipe.steps
      .map((step, index) => `
        <li>
          <span>${index + 1}</span>
          <div>
            <h3>${escapeHtml(step.title)}</h3>
            <p>${escapeHtml(step.description)}</p>
          </div>
        </li>
      `)
      .join("");
  }

  function renderNutrition(recipe) {
    nutritionList.innerHTML = Object.entries(recipe.nutrition)
      .map(([name, value]) => `
        <div>
          <dt>${escapeHtml(name)}</dt>
          <dd>${escapeHtml(value)}</dd>
        </div>
      `)
      .join("");
  }

  function renderRelated(recipe, fallbackRelated) {
    const relatedItems = fallbackRelated.filter((item) => recipe.related.includes(item.id)).slice(0, 3);

    relatedList.innerHTML = relatedItems
      .map((item) => `
        <a class="recipe-related-card" href="${escapeHtml(item.url)}">
          <span class="recipe-related-card__visual recipe-detail-visual--${escapeHtml(item.theme)}"></span>
          <strong>${escapeHtml(item.title)}</strong>
          <small>${escapeHtml(item.minutes)} min · ${escapeHtml(item.calories)} kcal</small>
        </a>
      `)
      .join("");
  }

  function renderRecipe(recipe, fallbackRelated) {
    currentRecipe = recipe;
    currentServings = recipe.servings;

    hero.className = `recipe-detail-hero recipe-detail-visual--${escapeHtml(recipe.theme)}`;
    label.textContent = recipe.label;
    title.textContent = recipe.title;
    description.textContent = recipe.description;
    prepTime.textContent = `${recipe.prepTimeMinutes} min`;
    cookTime.textContent = `${recipe.cookTimeMinutes} min`;
    calories.textContent = `${recipe.calories} kcal`;
    chefTip.textContent = recipe.chefTip;

    renderServings();
    renderIngredients(recipe);
    renderSteps(recipe);
    renderNutrition(recipe);
    renderRelated(recipe, fallbackRelated);

    loadingState.hidden = true;
    errorState.hidden = true;
    content.hidden = false;
  }

  async function loadRecipe() {
    try {
      const response = await fetch(detailsUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      const recipes = Array.isArray(data.recipes) ? data.recipes : [];
      const fallbackRelated = Array.isArray(data.fallbackRelated) ? data.fallbackRelated : [];
      const recipe = recipes.find((item) => item.id === getRecipeId()) ?? recipes[0];

      if (!recipe) {
        throw new Error("Recipe not found");
      }

      renderRecipe(recipe, fallbackRelated);
    } catch (error) {
      loadingState.hidden = true;
      errorState.hidden = false;
      content.hidden = true;
    }
  }

  decreaseButton.addEventListener("click", () => {
    currentServings = Math.max(1, currentServings - 1);
    renderServings();
  });

  increaseButton.addEventListener("click", () => {
    currentServings += 1;
    renderServings();
  });

  addToPlanButton.addEventListener("click", () => {
    if (currentRecipe) {
      setActionFeedback(addToPlanButton, "Dodano do planu");
    }
  });

  addToGroceryButton.addEventListener("click", () => {
    if (currentRecipe) {
      setActionFeedback(addToGroceryButton, "Dodano do listy");
    }
  });

  loadRecipe();
})();
