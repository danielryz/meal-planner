(() => {
  const view = document.querySelector("[data-recipes-view]");
  if (!view) return;

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

  let activeCategory = "all";
  let debounceTimer = null;

  function escapeHtml(value) {
    return String(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function getServingLabel(servings) {
    if (servings === 1) return "1 porcja";
    if (servings > 1 && servings < 5) return `${servings} porcje`;
    return `${servings} porcji`;
  }

  function createRecipeCard(recipe) {
    const favoriteClass = recipe.isFavorite ? "is-active" : "";
    const favoriteLabel = recipe.isFavorite ? "Usuń z ulubionych" : "Dodaj do ulubionych";
    const detailUrl = `/recipe-details?id=${encodeURIComponent(recipe.id)}`;
    const ratingHtml = recipe.rating != null
      ? `<strong>${escapeHtml(recipe.rating)}</strong> <small>(${escapeHtml(recipe.reviewCount)} opinii)</small>`
      : `<strong>—</strong>`;

    return `
      <article
        class="recipe-card"
        data-recipe-card
        data-recipe-id="${escapeHtml(recipe.id)}"
      >
        <div class="recipe-card__media recipe-card__media--green">
          <a class="recipe-card__image-link" href="${escapeHtml(detailUrl)}" aria-label="${escapeHtml(recipe.title)}"></a>
          <button class="recipe-favorite ${favoriteClass}" type="button" aria-label="${favoriteLabel}" aria-pressed="${recipe.isFavorite ? "true" : "false"}">♥</button>
          <span class="recipe-card__label">${escapeHtml(recipe.category ?? "")}</span>
        </div>

        <div class="recipe-card__body">
          <div class="recipe-rating">
            <span aria-hidden="true">★</span>
            ${ratingHtml}
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

  function buildParams() {
    const params = new URLSearchParams();

    const query = searchInput?.value.trim() ?? "";
    if (query) params.set("q", query);

    if (activeCategory && activeCategory !== "all") {
      params.set("category", activeCategory);
    }

    const timeControl = filterControls.find((c) => c.name === "time" && c.checked);
    if (timeControl) params.set("time", timeControl.value);

    const difficultyControl = filterControls.find((c) => c.name === "difficulty" && c.checked);
    if (difficultyControl) params.set("difficulty", difficultyControl.value);

    filterControls
      .filter((c) => c.name === "diet[]" && c.checked)
      .forEach((c) => params.append("diet[]", c.value));

    return params;
  }

  function getCheckedLabels() {
    return filterControls
      .filter((c) => c.checked)
      .map((c) => c.closest("label")?.textContent.trim())
      .filter(Boolean);
  }

  function renderRecipes(recipes) {
    grid.innerHTML = recipes.map(createRecipeCard).join("");

    const n = recipes.length;
    if (resultCount) {
      resultCount.textContent = `${n} ${n === 1 ? "przepis" : "przepisów"}`;
    }

    if (filterSummary) {
      const labels = getCheckedLabels();
      filterSummary.textContent = labels.length ? labels.join(", ") : "Bez aktywnych filtrów";
    }

    if (emptyState) {
      emptyState.hidden = n > 0;
      if (n === 0) emptyState.textContent = "Nie znaleziono przepisów dla wybranych filtrów.";
    }
  }

  async function loadRecipes() {
    const params = buildParams();
    grid.setAttribute("aria-busy", "true");
    grid.style.opacity = "0.5";

    try {
      const res = await fetch(`/api/recipes?${params.toString()}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      renderRecipes(Array.isArray(data.recipes) ? data.recipes : []);
    } catch {
      renderRecipes([]);
      if (emptyState) {
        emptyState.hidden = false;
        emptyState.textContent = "Nie udało się załadować przepisów.";
      }
      window.toast?.error("Nie udało się załadować przepisów.");
    } finally {
      grid.setAttribute("aria-busy", "false");
      grid.style.opacity = "";
    }
  }

  categoryButtons.forEach((button) => {
    button.addEventListener("click", () => {
      activeCategory = button.dataset.categoryFilter ?? "all";
      categoryButtons.forEach((b) => {
        const isActive = b.dataset.categoryFilter === activeCategory;
        b.classList.toggle("is-active", isActive);
        b.setAttribute("aria-pressed", isActive ? "true" : "false");
      });
      loadRecipes();
    });
  });

  filterControls.forEach((control) => {
    control.addEventListener("change", loadRecipes);
  });

  searchInput?.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadRecipes, 400);
  });

  clearButton?.addEventListener("click", () => {
    filterControls.forEach((c) => { c.checked = false; });
    activeCategory = "all";
    categoryButtons.forEach((b) => {
      const isAll = b.dataset.categoryFilter === "all";
      b.classList.toggle("is-active", isAll);
      b.setAttribute("aria-pressed", isAll ? "true" : "false");
    });
    if (searchInput) searchInput.value = "";
    loadRecipes();
  });

  filterToggle?.addEventListener("click", () => {
    const isOpen = filterPanel?.classList.toggle("is-open") ?? false;
    filterToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
  });

  grid.addEventListener("click", async (event) => {
    const button = event.target.closest(".recipe-favorite");
    if (!button) return;

    event.preventDefault();
    event.stopPropagation();

    const recipeId = button.closest("[data-recipe-card]")?.dataset.recipeId;
    if (!recipeId) return;

    const isNowFavorite = !button.classList.contains("is-active");
    button.classList.toggle("is-active", isNowFavorite);
    button.setAttribute("aria-pressed", isNowFavorite ? "true" : "false");
    button.setAttribute("aria-label", isNowFavorite ? "Usuń z ulubionych" : "Dodaj do ulubionych");

    try {
      const res = await fetch(`/api/recipes/${recipeId}/favorite`, { method: "POST" });
      if (!res.ok) throw new Error("failed");
      const data = await res.json();
      button.classList.toggle("is-active", data.isFavorite);
      button.setAttribute("aria-pressed", data.isFavorite ? "true" : "false");
      button.setAttribute("aria-label", data.isFavorite ? "Usuń z ulubionych" : "Dodaj do ulubionych");
    } catch {
      button.classList.toggle("is-active", !isNowFavorite);
      button.setAttribute("aria-pressed", !isNowFavorite ? "true" : "false");
      button.setAttribute("aria-label", !isNowFavorite ? "Usuń z ulubionych" : "Dodaj do ulubionych");
      window.toast?.error("Nie udało się zaktualizować ulubionych.");
    }
  });

  loadRecipes();
})();
