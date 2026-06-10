(() => {
  const view = document.querySelector("[data-recipe-management-view]");

  if (!view) {
    return;
  }

  const loadingState   = view.querySelector("[data-management-loading]");
  const errorState     = view.querySelector("[data-management-error]");
  const content        = view.querySelector("[data-management-content]");
  const list           = view.querySelector("[data-management-list]");
  const emptyState     = view.querySelector("[data-management-empty]");
  const message        = view.querySelector("[data-management-message]");
  const searchInput    = view.querySelector("[data-management-search]");
  const statusFilter   = view.querySelector("[data-management-status-filter]");
  const draftCount     = view.querySelector("[data-management-draft-count]");
  const submittedCount = view.querySelector("[data-management-submitted-count]");
  const approvedCount  = view.querySelector("[data-management-approved-count]");
  const reworkCount    = view.querySelector("[data-management-rework-count]");

  const deleteDialog  = document.querySelector("[data-delete-dialog]");
  const deleteConfirm = document.querySelector("[data-delete-confirm]");
  const deleteCancel  = document.querySelector("[data-delete-cancel]");
  const commentDialog = document.querySelector("[data-comment-dialog]");
  const commentText   = document.querySelector("[data-comment-text]");
  const commentClose  = document.querySelector("[data-comment-close]");

  let recipes          = [];
  let pendingDeleteId  = null;

  const statusLabels = {
    draft:             "Szkic",
    submitted:         "W weryfikacji",
    changes_requested: "Do poprawy",
    approved:          "Publiczny",
    rejected:          "Odrzucony",
  };

  const visibilityLabels = {
    private: "Prywatny",
    public:  "Publiczny",
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
    const query  = normalize(searchInput?.value ?? "");
    const status = statusFilter?.value ?? "all";

    return recipes.filter((recipe) => {
      const matchesQuery  = !query || normalize(`${recipe.title} ${recipe.category}`).includes(query);
      const matchesStatus = status === "all" || recipe.status === status;
      return matchesQuery && matchesStatus;
    });
  }

  function updateStats() {
    draftCount.textContent     = recipes.filter((r) => r.status === "draft").length;
    submittedCount.textContent = recipes.filter((r) => r.status === "submitted").length;
    approvedCount.textContent  = recipes.filter((r) => r.status === "approved").length;
    reworkCount.textContent    = recipes.filter((r) => r.status === "changes_requested").length;
  }

  function createActions(recipe) {
    const actions = [
      `<a href="${escapeHtml(recipe.url)}">Podgląd</a>`,
    ];

    if (recipe.status === "draft" || recipe.status === "changes_requested") {
      actions.push(`<a href="/edit-recipe/${escapeHtml(recipe.id)}">Edytuj</a>`);
    }

    if (recipe.status === "draft" || recipe.status === "changes_requested" || recipe.status === "rejected") {
      actions.push(`<button type="button" data-management-action="submit">Wyślij</button>`);
    }

    if (recipe.status === "changes_requested" && recipe.reviewReason) {
      actions.push(`<button type="button" data-management-action="comment">Komentarz</button>`);
    }

    if (recipe.status === "draft") {
      actions.push(`<button type="button" data-management-action="delete">Usuń</button>`);
    }

    return actions.join("");
  }

  function createRecipeRow(recipe) {
    const submittedText = recipe.submittedAt || "Nie wysłano";

    return `
      <article class="recipe-management-row" role="row" data-recipe-id="${escapeHtml(recipe.id)}">
        <div class="recipe-management-title" role="cell">
          <a class="recipe-management-title__link" href="${escapeHtml(recipe.url)}">${escapeHtml(recipe.title)}</a>
          <small>${escapeHtml(recipe.category)}</small>
        </div>
        <span class="recipe-management-status recipe-management-status--${escapeHtml(recipe.status)}" role="cell">${escapeHtml(statusLabels[recipe.status] ?? recipe.status)}</span>
        <span class="recipe-management-date" role="cell">${escapeHtml(recipe.updatedAt ?? "")}</span>
        <span class="recipe-management-visibility" role="cell">
          <strong>${escapeHtml(visibilityLabels[recipe.visibility] ?? recipe.visibility)}</strong>
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
    list.innerHTML        = filteredRecipes.map(createRecipeRow).join("");
    emptyState.hidden     = filteredRecipes.length > 0;
    updateStats();
  }

  function findRecipeByTarget(target) {
    const recipeId = Number(target.closest("[data-recipe-id]")?.dataset.recipeId);
    return recipes.find((r) => r.id === recipeId) ?? null;
  }

  function showMessage(text) {
    message.textContent = text;
    message.hidden      = false;
    window.setTimeout(() => { message.hidden = true; }, 2200);
  }

  // Dialog helpers
  function openDeleteDialog(recipe) {
    pendingDeleteId = recipe.id;
    deleteDialog?.showModal();
  }

  function openCommentDialog(recipe) {
    if (commentText) commentText.textContent = recipe.reviewReason ?? "";
    commentDialog?.showModal();
  }

  deleteConfirm?.addEventListener("click", async () => {
    deleteDialog?.close();
    const id = pendingDeleteId;
    pendingDeleteId = null;
    if (!id) return;

    try {
      const res = await fetch(`/api/recipes/${id}`, { method: "DELETE" });
      if (res.ok) {
        recipes = recipes.filter((r) => r.id !== id);
        render();
        if (window.toast) window.toast.success("Przepis usunięty.");
        else showMessage("Przepis usunięty.");
      } else {
        const data = await res.json();
        showMessage(data.error ?? "Wystąpił błąd.");
      }
    } catch {
      showMessage("Błąd połączenia z serwerem.");
    }
  });

  deleteCancel?.addEventListener("click", () => {
    deleteDialog?.close();
    pendingDeleteId = null;
  });

  deleteDialog?.addEventListener("click", (e) => {
    if (e.target === deleteDialog) { deleteDialog.close(); pendingDeleteId = null; }
  });

  commentClose?.addEventListener("click", () => commentDialog?.close());
  commentDialog?.addEventListener("click", (e) => { if (e.target === commentDialog) commentDialog.close(); });

  async function loadRecipes() {
    try {
      const response = await fetch("/api/my-recipes");

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data     = await response.json();
      recipes        = data.recipes;
      loadingState.hidden = true;
      errorState.hidden   = true;
      content.hidden      = false;
      render();
    } catch {
      loadingState.hidden = true;
      errorState.hidden   = false;
      content.hidden      = true;
    }
  }

  searchInput?.addEventListener("input", render);
  statusFilter?.addEventListener("change", render);

  list.addEventListener("click", async (event) => {
    const actionButton = event.target.closest("[data-management-action]");
    if (!actionButton) return;

    const recipe = findRecipeByTarget(actionButton);
    if (!recipe) return;

    const action = actionButton.dataset.managementAction;

    if (action === "submit") {
      try {
        const res = await fetch(`/api/recipes/${recipe.id}/submit-for-review`, {
          method:  "POST",
          headers: { "Content-Type": "application/json" },
        });

        if (res.ok) {
          recipe.status      = "submitted";
          recipe.submittedAt = new Date().toISOString().slice(0, 10);
          recipe.reviewReason = "";
          render();
          if (window.toast) window.toast.success("Przepis wysłany do weryfikacji.");
          else showMessage("Przepis wysłany do weryfikacji.");
        } else {
          const data = await res.json();
          showMessage(data.error ?? "Wystąpił błąd.");
        }
      } catch {
        showMessage("Błąd połączenia z serwerem.");
      }
    }

    if (action === "delete") {
      openDeleteDialog(recipe);
    }

    if (action === "comment") {
      openCommentDialog(recipe);
    }
  });

  loadRecipes();
})();
