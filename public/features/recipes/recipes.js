(() => {
  const view = document.querySelector("[data-recipes-view]");
  const recipesUrl = "/public/features/recipes/recipes_mock.json";

  if (!view) {
    return;
  }

  const grid = view.querySelector("[data-recipe-grid]");
  const searchInput = view.querySelector("[data-recipe-search]");
  const categoryButtons = Array.from(view.querySelectorAll("[data-category-filter]"));
  const filterControls = Array.from(view.querySelectorAll("[data-filter-control]"));
  const clearButton = view.querySelector("[data-clear-filters]");
  const filterToggle = view.querySelector("[data-filter-toggle]");
  const filterPanel = view.querySelector(".recipe-filters");
  const resultCount = view.querySelector("[data-result-count]");
  const filterSummary = view.querySelector("[data-filter-summary]");
  const emptyState = view.querySelector("[data-recipe-empty]");
  let recipes = [];
  let activeCategory = "all";

  function escapeHtml(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function normalize(value) {
    return String(value).trim().toLowerCase();
  }

  function getActiveValues(name) {
    return filterControls
      .filter((control) => control.name === name && control.checked)
      .map((control) => control.value);
  }

  function getCheckedLabels() {
    return filterControls
      .filter((control) => control.checked)
      .map((control) => control.closest("label")?.textContent.trim())
      .filter(Boolean);
  }

  function getRecipeCategories(recipe) {
    return Array.isArray(recipe.categories) ? recipe.categories : [];
  }

  function getServingLabel(servings) {
    if (servings === 1) {
      return "1 porcja";
    }

    if (servings > 1 && servings < 5) {
      return `${servings} porcje`;
    }

    return `${servings} porcji`;
  }

  function createRecipeCard(recipe) {
    const categories = getRecipeCategories(recipe).join(" ");
    const favoriteClass = recipe.isFavorite ? "is-active" : "";
    const favoriteLabel = recipe.isFavorite ? "Usuń z ulubionych" : "Dodaj do ulubionych";
    const theme = recipe.theme || "green";
    const detailUrl = recipe.url || `/recipe-details?id=${encodeURIComponent(recipe.id)}`;

    return `
      <article
        class="recipe-card"
        data-recipe-card
        data-recipe-id="${escapeHtml(recipe.id)}"
        data-category="${escapeHtml(categories)}"
        data-minutes="${escapeHtml(recipe.cookingTimeMinutes)}"
        data-difficulty="${escapeHtml(recipe.difficulty)}"
        data-title="${escapeHtml(recipe.title)}"
      >
        <div class="recipe-card__media recipe-card__media--${escapeHtml(theme)}">
          <a class="recipe-card__image-link" href="${escapeHtml(detailUrl)}" aria-label="${escapeHtml(recipe.title)}"></a>
          <button class="recipe-favorite ${favoriteClass}" type="button" aria-label="${favoriteLabel}" aria-pressed="${recipe.isFavorite ? "true" : "false"}">♥</button>
          <span class="recipe-card__label">${escapeHtml(recipe.label)}</span>
        </div>

        <div class="recipe-card__body">
          <div class="recipe-rating">
            <span aria-hidden="true">★</span>
            <strong>${escapeHtml(recipe.rating)}</strong>
            <small>(${escapeHtml(recipe.reviewCount)} opinii)</small>
          </div>

          <h3>${escapeHtml(recipe.title)}</h3>

          <div class="recipe-meta">
            <span class="recipe-meta__item">
              <img src="/public/assets/icons/clock.svg" alt="" />
              ${escapeHtml(recipe.cookingTimeMinutes)} min
            </span>
            <span class="recipe-meta__item">
              <img src="/public/assets/icons/cutlery.svg" alt="" />
              ${escapeHtml(getServingLabel(Number(recipe.servings)))}
            </span>
          </div>
        </div>
      </article>
    `;
  }

  function getFilteredRecipes() {
    const query = normalize(searchInput?.value ?? "");
    const activeDiets = getActiveValues("diet[]");
    const activeTimes = getActiveValues("time");
    const activeDifficulties = getActiveValues("difficulty");

    return recipes.filter((recipe) => {
      const title = normalize(recipe.title ?? "");
      const categories = normalize(getRecipeCategories(recipe).join(" "));
      const minutes = Number(recipe.cookingTimeMinutes ?? 0);
      const difficulty = recipe.difficulty ?? "";
      const matchesCategory = activeCategory === "all" || categories.includes(activeCategory);
      const matchesQuery = !query || title.includes(query) || categories.includes(query);
      const matchesDiet = activeDiets.length === 0 || activeDiets.some((diet) => categories.includes(diet));
      const matchesTime = activeTimes.length === 0 || activeTimes.some((time) => minutes <= Number(time));
      const matchesDifficulty = activeDifficulties.length === 0 || activeDifficulties.includes(difficulty);

      return matchesCategory && matchesQuery && matchesDiet && matchesTime && matchesDifficulty;
    });
  }

  function render() {
    const visibleRecipes = getFilteredRecipes();

    grid.innerHTML = visibleRecipes.map(createRecipeCard).join("");

    categoryButtons.forEach((button) => {
      const isActive = button.dataset.categoryFilter === activeCategory;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-pressed", isActive ? "true" : "false");
    });

    if (resultCount) {
      resultCount.textContent = `${visibleRecipes.length} ${visibleRecipes.length === 1 ? "przepis" : "przepisów"}`;
    }

    if (filterSummary) {
      const labels = getCheckedLabels();
      filterSummary.textContent = labels.length ? labels.join(", ") : "Bez aktywnych filtrów";
    }

    if (emptyState) {
      emptyState.hidden = visibleRecipes.length > 0;
      emptyState.textContent = "Nie znaleziono przepisów dla wybranych filtrów.";
    }
  }

  async function loadRecipes() {
    try {
      const response = await fetch(recipesUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      recipes = Array.isArray(data.recipes) ? data.recipes : [];
      render();
    } catch (error) {
      grid.innerHTML = "";
      if (resultCount) {
        resultCount.textContent = "0 przepisów";
      }
      if (filterSummary) {
        filterSummary.textContent = "Błąd ładowania danych";
      }
      if (emptyState) {
        emptyState.hidden = false;
        emptyState.textContent = "Nie udało się załadować przepisów.";
      }
    }
  }

  categoryButtons.forEach((button) => {
    button.addEventListener("click", () => {
      activeCategory = button.dataset.categoryFilter ?? "all";
      render();
    });
  });

  filterControls.forEach((control) => {
    control.addEventListener("change", render);
  });

  searchInput?.addEventListener("input", render);

  clearButton?.addEventListener("click", () => {
    filterControls.forEach((control) => {
      control.checked = false;
    });
    activeCategory = "all";
    if (searchInput) {
      searchInput.value = "";
    }
    render();
  });

  filterToggle?.addEventListener("click", () => {
    const isOpen = filterPanel?.classList.toggle("is-open") ?? false;
    filterToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
  });

  grid.addEventListener("click", (event) => {
    const button = event.target.closest(".recipe-favorite");

    if (!button) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();

    const recipe = recipes.find((item) => String(item.id) === button.closest("[data-recipe-card]")?.dataset.recipeId);

    if (recipe) {
      recipe.isFavorite = !recipe.isFavorite;
      button.classList.toggle("is-active", recipe.isFavorite);
      button.setAttribute("aria-pressed", recipe.isFavorite ? "true" : "false");
      button.setAttribute("aria-label", recipe.isFavorite ? "Usuń z ulubionych" : "Dodaj do ulubionych");
    }
  });

  loadRecipes();
})();
