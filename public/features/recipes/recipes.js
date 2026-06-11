(() => {
  const view = document.querySelector("[data-recipes-view]");
  if (!view) return;

  const grid             = view.querySelector("[data-recipe-grid]");
  const searchInput      = view.querySelector("[data-recipe-search]");
  const clearButton      = view.querySelector("[data-clear-filters]");
  const filterToggle     = view.querySelector("[data-filter-toggle]");
  const filterPanel      = view.querySelector(".recipe-filters");
  const resultCount      = view.querySelector("[data-result-count]");
  const filterSummary    = view.querySelector("[data-filter-summary]");
  const emptyState       = view.querySelector("[data-recipe-empty]");
  const loadMoreBtn      = view.querySelector("[data-load-more]");
  const categoryContainer = view.querySelector("[data-category-buttons]");
  const dietContainer    = view.querySelector("[data-filter-items='diet']");
  const timeContainer    = view.querySelector("[data-filter-items='time']");
  const diffContainer    = view.querySelector("[data-filter-items='difficulty']");
  const favoritesControl = view.querySelector("[data-favorites-control]");
  const loginModal       = document.querySelector("[data-login-modal]");
  const isAuthenticated  = view.dataset.authenticated === 'true';

  loginModal?.addEventListener("click", (e) => {
    if (e.target === loginModal) loginModal.close();
  });

  let filterControls  = [];
  let categoryButtons = [];
  let activeCategory  = "all";
  let currentPage     = 1;
  let totalPages      = 1;
  let filtersRendered = false;
  let debounceTimer   = null;

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
    const detailUrl     = `/recipe/${encodeURIComponent(recipe.id)}`;
    const ratingHtml    = recipe.rating != null
      ? `<strong>${escapeHtml(recipe.rating)}</strong> <small>(${escapeHtml(recipe.reviewCount)} opinii)</small>`
      : `<strong>—</strong>`;

    return `
      <article class="recipe-card" data-recipe-card data-recipe-id="${escapeHtml(recipe.id)}">
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

  function renderFilters(filters) {
    if (filtersRendered) return;
    filtersRendered = true;

    if (categoryContainer) {
      const allBtn = `<button class="recipe-category is-active" type="button" data-category-filter="all" aria-pressed="true">Wszystkie</button>`;
      const catBtns = (filters.categories ?? []).map(cat =>
        `<button class="recipe-category" type="button" data-category-filter="${escapeHtml(cat.id)}" aria-pressed="false">${escapeHtml(cat.label)}</button>`
      ).join("");
      categoryContainer.innerHTML = allBtn + catBtns;
    }

    if (dietContainer) {
      dietContainer.innerHTML = (filters.diets ?? []).map(d => `
        <label class="recipe-check">
          <input type="checkbox" name="diet[]" value="${escapeHtml(d.id)}" data-filter-control />
          <span>${escapeHtml(d.label)}</span>
        </label>`).join("");
    }

    if (timeContainer) {
      timeContainer.innerHTML = (filters.timeBuckets ?? []).map(t => `
        <label class="recipe-radio">
          <input type="radio" name="time" value="${escapeHtml(t.id)}" data-filter-control />
          <img src="/public/assets/icons/clock.svg" alt="" />
          <span>${escapeHtml(t.label)}</span>
        </label>`).join("");
    }

    if (diffContainer) {
      diffContainer.innerHTML = (filters.difficulties ?? []).map(d => `
        <label class="recipe-radio">
          <input type="radio" name="difficulty" value="${escapeHtml(d.id)}" data-filter-control />
          <span>${escapeHtml(d.label)}</span>
        </label>`).join("");
    }

    filterControls  = Array.from(view.querySelectorAll("[data-filter-control]"));
    categoryButtons = Array.from(view.querySelectorAll("[data-category-filter]"));

    filterControls.forEach(c => c.addEventListener("change", () => {
      currentPage = 1;
      loadRecipes();
    }));

    categoryButtons.forEach(btn => {
      btn.addEventListener("click", () => {
        activeCategory = btn.dataset.categoryFilter ?? "all";
        categoryButtons.forEach(b => {
          const isActive = b.dataset.categoryFilter === activeCategory;
          b.classList.toggle("is-active", isActive);
          b.setAttribute("aria-pressed", isActive ? "true" : "false");
        });
        currentPage = 1;
        loadRecipes();
      });
    });
  }

  function buildParams() {
    const params = new URLSearchParams();
    params.set("page", String(currentPage));

    const query = searchInput?.value.trim() ?? "";
    if (query) params.set("q", query);

    if (activeCategory && activeCategory !== "all") params.set("category", activeCategory);
    if (favoritesControl?.checked) params.set("favorites", "1");

    const timeControl = filterControls.find(c => c.name === "time" && c.checked);
    if (timeControl) params.set("time", timeControl.value);

    const diffControl = filterControls.find(c => c.name === "difficulty" && c.checked);
    if (diffControl) params.set("difficulty", diffControl.value);

    filterControls.filter(c => c.name === "diet[]" && c.checked).forEach(c => params.append("diet[]", c.value));

    return params;
  }

  function updateStatus(total) {
    if (resultCount) {
      resultCount.textContent = `${total} ${total === 1 ? "przepis" : total < 5 ? "przepisy" : "przepisów"}`;
    }
    if (filterSummary) {
      const labels = filterControls.filter(c => c.checked).map(c => c.closest("label")?.textContent.trim()).filter(Boolean);
      if (favoritesControl?.checked) labels.push("Ulubione");
      filterSummary.textContent = labels.length ? labels.join(", ") : "Bez aktywnych filtrów";
    }
  }

  async function loadRecipes(append = false) {
    const params = buildParams();

    if (!append) {
      grid.setAttribute("aria-busy", "true");
      grid.style.opacity = "0.5";
    }

    try {
      const res = await fetch(`/api/recipes?${params.toString()}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();

      if (!filtersRendered && data.filters) {
        renderFilters(data.filters);
        if (data.filters.userDietPreference && dietContainer) {
          const cb = dietContainer.querySelector(`[value="${CSS.escape(data.filters.userDietPreference)}"]`);
          if (cb) cb.checked = true;
        }
      }

      const recipes = Array.isArray(data.recipes) ? data.recipes : [];
      const total   = data.total ?? recipes.length;

      if (append) {
        grid.insertAdjacentHTML("beforeend", recipes.map(createRecipeCard).join(""));
      } else {
        grid.innerHTML = recipes.map(createRecipeCard).join("");
      }

      totalPages  = data.pages ?? 1;
      currentPage = data.page ?? currentPage;

      if (loadMoreBtn) loadMoreBtn.hidden = currentPage >= totalPages;

      updateStatus(total);

      if (emptyState) {
        emptyState.hidden = total > 0 || append;
        if (total === 0 && !append) emptyState.textContent = "Nie znaleziono przepisów dla wybranych filtrów.";
      }
    } catch {
      if (!append) grid.innerHTML = "";
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

  searchInput?.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      currentPage = 1;
      loadRecipes();
    }, 400);
  });

  clearButton?.addEventListener("click", () => {
    filterControls.forEach(c => { c.checked = false; });
    if (favoritesControl) favoritesControl.checked = false;
    activeCategory = "all";
    categoryButtons.forEach(b => {
      const isAll = b.dataset.categoryFilter === "all";
      b.classList.toggle("is-active", isAll);
      b.setAttribute("aria-pressed", isAll ? "true" : "false");
    });
    if (searchInput) searchInput.value = "";
    currentPage = 1;
    loadRecipes();
  });

  filterToggle?.addEventListener("click", () => {
    const isOpen = filterPanel?.classList.toggle("is-open") ?? false;
    filterToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
  });

  favoritesControl?.addEventListener("change", () => {
    currentPage = 1;
    loadRecipes();
  });

  loadMoreBtn?.addEventListener("click", () => {
    currentPage++;
    loadRecipes(true);
  });

  grid.addEventListener("click", async (event) => {
    const button = event.target.closest(".recipe-favorite");
    if (!button) return;

    event.preventDefault();
    event.stopPropagation();

    const recipeId = button.closest("[data-recipe-card]")?.dataset.recipeId;
    if (!recipeId) return;

    if (!isAuthenticated) {
      loginModal?.showModal();
      return;
    }

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
