(() => {
  const view = document.querySelector("[data-recipe-management-view]");
  const managementUrl = "/public/features/recipes/recipe_management_mock.json";

  if (!view) {
    return;
  }

  const loadingState = view.querySelector("[data-management-loading]");
  const errorState = view.querySelector("[data-management-error]");
  const content = view.querySelector("[data-management-content]");
  const list = view.querySelector("[data-management-list]");
  const emptyState = view.querySelector("[data-management-empty]");
  const message = view.querySelector("[data-management-message]");
  const searchInput = view.querySelector("[data-management-search]");
  const statusFilter = view.querySelector("[data-management-status-filter]");
  const draftCount = view.querySelector("[data-management-draft-count]");
  const submittedCount = view.querySelector("[data-management-submitted-count]");
  const approvedCount = view.querySelector("[data-management-approved-count]");
  const reworkCount = view.querySelector("[data-management-rework-count]");
  let recipes = [];

  const statusLabels = {
    draft: "Szkic",
    submitted: "W weryfikacji",
    changes_requested: "Do poprawy",
    approved: "Publiczny",
    rejected: "Odrzucony",
  };

  const visibilityLabels = {
    private: "Prywatny",
    public: "Publiczny",
  };

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

  function getFilteredRecipes() {
    const query = normalize(searchInput?.value ?? "");
    const status = statusFilter?.value ?? "all";

    return recipes.filter((recipe) => {
      const matchesQuery = !query || normalize(`${recipe.title} ${recipe.category}`).includes(query);
      const matchesStatus = status === "all" || recipe.status === status;

      return matchesQuery && matchesStatus;
    });
  }

  function updateStats() {
    draftCount.textContent = recipes.filter((recipe) => recipe.status === "draft").length;
    submittedCount.textContent = recipes.filter((recipe) => recipe.status === "submitted").length;
    approvedCount.textContent = recipes.filter((recipe) => recipe.status === "approved").length;
    reworkCount.textContent = recipes.filter((recipe) => recipe.status === "changes_requested").length;
  }

  function createActions(recipe) {
    const actions = [`<a href="${escapeHtml(recipe.url)}">Podgląd</a>`];

    if (recipe.status === "draft" || recipe.status === "changes_requested" || recipe.status === "rejected") {
      actions.push(`<button type="button" data-management-action="edit">Edytuj</button>`);
      actions.push(`<button type="button" data-management-action="submit">Wyślij</button>`);
    }

    if (recipe.status === "draft") {
      actions.push(`<button type="button" data-management-action="delete">Usuń</button>`);
    }

    return actions.join("");
  }

  function createRecipeRow(recipe) {
    const submittedText = recipe.submittedAt || "Nie wysłano";
    const reason = recipe.reviewReason ? `<small>${escapeHtml(recipe.reviewReason)}</small>` : "";

    return `
      <article class="recipe-management-row" role="row" data-recipe-id="${escapeHtml(recipe.id)}">
        <div class="recipe-management-title" role="cell">
          <strong>${escapeHtml(recipe.title)}</strong>
          <small>${escapeHtml(recipe.category)}</small>
          ${reason}
        </div>
        <span class="recipe-management-status recipe-management-status--${escapeHtml(recipe.status)}" role="cell">${escapeHtml(statusLabels[recipe.status])}</span>
        <span class="recipe-management-date" role="cell">${escapeHtml(recipe.updatedAt)}</span>
        <span class="recipe-management-visibility" role="cell">
          <strong>${escapeHtml(visibilityLabels[recipe.visibility])}</strong>
          <small>${escapeHtml(submittedText)}</small>
        </span>
        <div class="recipe-management-actions" role="cell">
          ${createActions(recipe)}
        </div>
      </article>
    `;
  }

  function render() {
    const filteredRecipes = getFilteredRecipes();

    list.innerHTML = filteredRecipes.map(createRecipeRow).join("");
    emptyState.hidden = filteredRecipes.length > 0;
    updateStats();
  }

  function findRecipeByTarget(target) {
    const recipeId = Number(target.closest("[data-recipe-id]")?.dataset.recipeId);

    return recipes.find((recipe) => recipe.id === recipeId) ?? null;
  }

  function showMessage(text) {
    message.textContent = text;
    message.hidden = false;
    window.setTimeout(() => {
      message.hidden = true;
    }, 2200);
  }

  async function loadRecipes() {
    try {
      const response = await fetch(managementUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      recipes = data.recipes;
      loadingState.hidden = true;
      errorState.hidden = true;
      content.hidden = false;
      render();
    } catch (error) {
      loadingState.hidden = true;
      errorState.hidden = false;
      content.hidden = true;
    }
  }

  searchInput?.addEventListener("input", render);
  statusFilter?.addEventListener("change", render);

  list.addEventListener("click", (event) => {
    const actionButton = event.target.closest("[data-management-action]");

    if (!actionButton) {
      return;
    }

    const recipe = findRecipeByTarget(actionButton);

    if (!recipe) {
      return;
    }

    if (actionButton.dataset.managementAction === "submit") {
      recipe.status = "submitted";
      recipe.visibility = "private";
      recipe.submittedAt = "Wysłano teraz";
      recipe.reviewReason = "";
      render();
      showMessage("Przepis wysłany lokalnie do weryfikacji.");
    }

    if (actionButton.dataset.managementAction === "edit") {
      showMessage("Edycja przepisu zostanie podłączona do formularza backendowego.");
    }

    if (actionButton.dataset.managementAction === "delete") {
      recipes = recipes.filter((item) => item.id !== recipe.id);
      render();
      showMessage("Szkic usunięty lokalnie.");
    }
  });

  loadRecipes();
})();
