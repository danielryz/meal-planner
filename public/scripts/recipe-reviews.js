(() => {
  const view = document.querySelector("[data-review-view]");
  const reviewsUrl = "/public/mock/recipe_reviews_mock.json";

  if (!view) {
    return;
  }

  const loadingState = view.querySelector("[data-review-loading]");
  const errorState = view.querySelector("[data-review-error]");
  const content = view.querySelector("[data-review-content]");
  const list = view.querySelector("[data-review-list]");
  const emptyState = view.querySelector("[data-review-empty]");
  const searchInput = view.querySelector("[data-review-search]");
  const statusFilter = view.querySelector("[data-review-status-filter]");
  const detail = view.querySelector("[data-review-detail]");
  const pendingCount = view.querySelector("[data-review-pending-count]");
  const changesCount = view.querySelector("[data-review-changes-count]");
  const approvedCount = view.querySelector("[data-review-approved-count]");
  const rejectedCount = view.querySelector("[data-review-rejected-count]");
  let reviews = [];
  let selectedReviewId = null;

  const statusLabels = {
    pending: "Oczekuje",
    changes_requested: "Do poprawek",
    approved: "Zaakceptowany",
    rejected: "Odrzucony",
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

  function getFilteredReviews() {
    const query = normalize(searchInput?.value ?? "");
    const status = statusFilter?.value ?? "all";

    return reviews.filter((review) => {
      const matchesQuery = !query || normalize(`${review.title} ${review.author} ${review.authorEmail}`).includes(query);
      const matchesStatus = status === "all" || review.status === status;

      return matchesQuery && matchesStatus;
    });
  }

  function updateStats() {
    pendingCount.textContent = reviews.filter((review) => review.status === "pending").length;
    changesCount.textContent = reviews.filter((review) => review.status === "changes_requested").length;
    approvedCount.textContent = reviews.filter((review) => review.status === "approved").length;
    rejectedCount.textContent = reviews.filter((review) => review.status === "rejected").length;
  }

  function createReviewCard(review) {
    const isActive = review.id === selectedReviewId ? "is-active" : "";

    return `
      <article class="recipe-review-card ${isActive}" data-review-id="${escapeHtml(review.id)}">
        <button type="button" data-select-review>
          <span class="recipe-review-card__status recipe-review-card__status--${escapeHtml(review.status)}">${escapeHtml(statusLabels[review.status])}</span>
          <strong>${escapeHtml(review.title)}</strong>
          <small>${escapeHtml(review.author)} · ${escapeHtml(review.submittedAt)}</small>
          <span>${escapeHtml(review.summary)}</span>
        </button>
      </article>
    `;
  }

  function renderList() {
    const filteredReviews = getFilteredReviews();

    if (!selectedReviewId && filteredReviews.length > 0) {
      selectedReviewId = filteredReviews[0].id;
    }

    list.innerHTML = filteredReviews.map(createReviewCard).join("");
    emptyState.hidden = filteredReviews.length > 0;
    updateStats();
    renderDetail();
  }

  function renderDetail() {
    const review = reviews.find((item) => item.id === selectedReviewId);

    if (!review) {
      detail.innerHTML = `
        <div class="recipe-reviews-detail__empty" data-review-detail-empty>
          <span class="recipe-reviews-kicker">Podgląd</span>
          <h2>Wybierz przepis</h2>
          <p>Po wybraniu zgłoszenia zobaczysz składniki, opis, notatkę autora oraz akcje moderacyjne.</p>
        </div>
      `;
      return;
    }

    const actionDisabled = review.status === "approved" || review.status === "rejected" ? "disabled" : "";

    detail.innerHTML = `
      <div class="recipe-reviews-detail__header">
        <span class="recipe-review-card__status recipe-review-card__status--${escapeHtml(review.status)}">${escapeHtml(statusLabels[review.status])}</span>
        <h2>${escapeHtml(review.title)}</h2>
        <p>${escapeHtml(review.summary)}</p>
      </div>

      <dl class="recipe-reviews-meta">
        <div>
          <dt>Autor</dt>
          <dd>${escapeHtml(review.author)}</dd>
        </div>
        <div>
          <dt>E-mail</dt>
          <dd>${escapeHtml(review.authorEmail)}</dd>
        </div>
        <div>
          <dt>Kategoria</dt>
          <dd>${escapeHtml(review.category)}</dd>
        </div>
        <div>
          <dt>Czas</dt>
          <dd>${escapeHtml(review.prepTimeMinutes)} min</dd>
        </div>
        <div>
          <dt>Porcje</dt>
          <dd>${escapeHtml(review.servings)}</dd>
        </div>
        <div>
          <dt>Poziom</dt>
          <dd>${escapeHtml(review.difficulty)}</dd>
        </div>
      </dl>

      <section class="recipe-reviews-ingredients" aria-labelledby="review-ingredients-title">
        <h3 id="review-ingredients-title">Składniki</h3>
        <ul>
          ${review.ingredients.map((ingredient) => `<li>${escapeHtml(ingredient)}</li>`).join("")}
        </ul>
      </section>

      <label class="recipe-reviews-note">
        <span>Powód decyzji lub prośba o poprawki</span>
        <textarea rows="4" data-review-reason>${escapeHtml(review.reviewNote)}</textarea>
      </label>

      <p class="recipe-reviews-message" data-review-message hidden></p>

      <div class="recipe-reviews-actions">
        <button class="button" type="button" data-review-action="approved" ${actionDisabled}>Akceptuj publikację</button>
        <button class="button button-secondary" type="button" data-review-action="changes_requested" ${actionDisabled}>Poproś o poprawki</button>
        <button class="button button-secondary recipe-reviews-actions__danger" type="button" data-review-action="rejected" ${actionDisabled}>Odrzuć</button>
      </div>
    `;
  }

  function setReviewStatus(status, reason) {
    const review = reviews.find((item) => item.id === selectedReviewId);

    if (!review) {
      return;
    }

    review.status = status;
    review.reviewNote = reason || review.reviewNote;
    renderList();

    const message = detail.querySelector("[data-review-message]");
    if (message) {
      message.textContent = status === "approved" ? "Przepis zaakceptowany lokalnie." : "Decyzja zapisana lokalnie.";
      message.hidden = false;
    }
  }

  async function loadReviews() {
    try {
      const response = await fetch(reviewsUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      reviews = data.reviews;
      loadingState.hidden = true;
      errorState.hidden = true;
      content.hidden = false;
      renderList();
    } catch (error) {
      loadingState.hidden = true;
      errorState.hidden = false;
      content.hidden = true;
    }
  }

  searchInput?.addEventListener("input", () => {
    selectedReviewId = null;
    renderList();
  });

  statusFilter?.addEventListener("change", () => {
    selectedReviewId = null;
    renderList();
  });

  list.addEventListener("click", (event) => {
    const selectButton = event.target.closest("[data-select-review]");

    if (!selectButton) {
      return;
    }

    selectedReviewId = Number(selectButton.closest("[data-review-id]")?.dataset.reviewId);
    renderList();
  });

  detail.addEventListener("click", (event) => {
    const actionButton = event.target.closest("[data-review-action]");

    if (!actionButton) {
      return;
    }

    const reason = detail.querySelector("[data-review-reason]")?.value.trim() ?? "";
    setReviewStatus(actionButton.dataset.reviewAction, reason);
  });

  loadReviews();
})();
