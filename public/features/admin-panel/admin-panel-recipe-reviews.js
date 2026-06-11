(() => {
  const view = document.querySelector("[data-admin-reviews]");
  if (!view) return;

  const loading      = view.querySelector("[data-loading]");
  const error        = view.querySelector("[data-error]");
  const empty        = view.querySelector("[data-empty]");
  const content      = view.querySelector("[data-content]");
  const reviewList   = view.querySelector("[data-review-list]");
  const reviewDetail = view.querySelector("[data-review-detail]");
  const searchInput  = view.querySelector("[data-search]");
  const statusFilter = view.querySelector("[data-filter-status]");
  const logoutBtn    = document.querySelector("[data-admin-logout]");

  let reviews = [];
  let selectedId = null;
  let debounceTimer = null;

  const statusLabels = {
    pending: "Oczekujące",
    changes_requested: "Do poprawek",
    approved: "Zaakceptowane",
    rejected: "Odrzucone",
  };

  function escapeHtml(v) {
    return String(v ?? "").replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;").replaceAll('"',"&quot;");
  }

  function updateStats(stats) {
    view.querySelector("[data-stat-pending]").textContent  = stats.pending ?? 0;
    view.querySelector("[data-stat-changes]").textContent  = stats.changes_requested ?? 0;
    view.querySelector("[data-stat-approved]").textContent = stats.approved ?? 0;
    view.querySelector("[data-stat-rejected]").textContent = stats.rejected ?? 0;
  }

  function renderList(items) {
    if (items.length === 0) {
      reviewList.innerHTML = "";
      empty.hidden = false;
      return;
    }
    empty.hidden = true;
    reviewList.innerHTML = items.map(r => `
      <div class="admin-review-item ${r.id === selectedId ? "is-active" : ""}" data-review-id="${r.id}" role="button" tabindex="0">
        <div class="admin-review-item__title">${escapeHtml(r.title)}</div>
        <div class="admin-review-item__meta">
          <span class="admin-badge admin-badge--${escapeHtml(r.status)}">${escapeHtml(statusLabels[r.status] ?? r.status)}</span>
          ${escapeHtml(r.author)} · ${escapeHtml(r.submittedAt ?? "—")}
        </div>
      </div>
    `).join("");

    reviewList.querySelectorAll("[data-review-id]").forEach(el => {
      el.addEventListener("click", () => selectReview(parseInt(el.dataset.reviewId, 10)));
      el.addEventListener("keydown", e => { if (e.key === "Enter" || e.key === " ") selectReview(parseInt(el.dataset.reviewId, 10)); });
    });
  }

  function selectReview(id) {
    selectedId = id;
    const review = reviews.find(r => r.id === id);
    if (!review) return;

    reviewList.querySelectorAll("[data-review-id]").forEach(el => {
      el.classList.toggle("is-active", parseInt(el.dataset.reviewId, 10) === id);
    });

    renderDetail(review);
  }

  function renderDetail(r) {
    const isEditable = r.status === "pending" || r.status === "changes_requested";

    reviewDetail.innerHTML = `
      <div class="admin-detail-header">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
          <h2>${escapeHtml(r.title)}</h2>
          <span class="admin-badge admin-badge--${escapeHtml(r.status)}">${escapeHtml(statusLabels[r.status] ?? r.status)}</span>
        </div>
        <div style="font-size:0.8125rem;color:#6b7280">
          Autor: ${escapeHtml(r.author)} (${escapeHtml(r.authorEmail)}) · ${escapeHtml(r.submittedAt ?? "—")}
        </div>
      </div>
      <div class="admin-detail-body">
        ${r.summary ? `<p style="color:#374151;margin-bottom:1rem">${escapeHtml(r.summary)}</p>` : ""}
        <div style="display:flex;gap:1rem;flex-wrap:wrap;font-size:0.8125rem;color:#6b7280;margin-bottom:1rem">
          <span>Kategoria: ${escapeHtml(r.category ?? "—")}</span>
          <span>Czas: ${r.prepTimeMinutes} min</span>
          <span>Porcje: ${r.servings}</span>
        </div>
        ${r.reviewNote ? `<p style="background:#fef9c3;padding:0.75rem 1rem;border-radius:8px;font-size:0.8125rem;color:#92400e">Poprzednia notatka: ${escapeHtml(r.reviewNote)}</p>` : ""}
        ${isEditable ? `
          <div style="margin-top:1rem">
            <label style="display:block;font-size:0.8125rem;font-weight:600;color:#374151;margin-bottom:0.35rem" for="review-note-${r.id}">
              Komentarz / powód (wymagany dla poprawek i odrzucenia)
            </label>
            <textarea class="admin-detail-note-field" id="review-note-${r.id}" data-review-note placeholder="Wpisz komentarz lub powód..."></textarea>
          </div>
        ` : ""}
      </div>
      ${isEditable ? `
        <div class="admin-detail-actions">
          <button class="admin-btn admin-btn--success" type="button" data-action="approve">Zatwierdź</button>
          <button class="admin-btn admin-btn--warning" type="button" data-action="request-changes">Poproś o poprawki</button>
          <button class="admin-btn admin-btn--danger" type="button" data-action="reject">Odrzuć</button>
        </div>
      ` : ""}
    `;

    if (!isEditable) return;

    reviewDetail.querySelectorAll("[data-action]").forEach(btn => {
      btn.addEventListener("click", () => handleAction(r.id, btn.dataset.action));
    });
  }

  async function handleAction(recipeId, action) {
    const note = reviewDetail.querySelector("[data-review-note]")?.value.trim() ?? "";

    if ((action === "request-changes" || action === "reject") && !note) {
      window.toast?.error(action === "reject" ? "Powód odrzucenia jest wymagany." : "Komentarz jest wymagany.");
      return;
    }

    const url = `/api/admin/recipes/${recipeId}/${action}`;
    const body = action === "approve" ? {} : (action === "reject" ? { reason: note } : { note });

    try {
      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      if (res.ok) {
        const messages = { approve: "Przepis zatwierdzony.", "request-changes": "Prośba o poprawki wysłana do autora.", reject: "Przepis odrzucony." };
        window.toast?.success(messages[action]);
        selectedId = null;
        reviewDetail.innerHTML = `<div class="admin-split-detail__empty">Wybierz przepis z listy, aby zobaczyć szczegóły.</div>`;
        loadReviews();
      } else {
        const d = await res.json().catch(() => ({}));
        window.toast?.error(d.error ?? "Nie udało się wykonać akcji.");
      }
    } catch {
      window.toast?.error("Nie udało się wykonać akcji.");
    }
  }

  async function loadReviews() {
    const query  = searchInput?.value.trim() ?? "";
    const status = statusFilter?.value ?? "all";

    const params = new URLSearchParams();
    if (query) params.set("query", query);
    if (status !== "all") params.set("status", status);

    loading.hidden = false;
    error.hidden   = true;
    content.hidden = true;

    try {
      const res = await fetch(`/api/admin/recipe-reviews?${params}`);
      if (!res.ok) throw new Error();
      const data = await res.json();
      loading.hidden = true;
      content.hidden = false;
      reviews = data.reviews ?? [];
      updateStats(data.stats ?? {});
      renderList(reviews);
    } catch {
      loading.hidden = true;
      error.hidden   = false;
      window.toast?.error("Nie udało się załadować kolejki weryfikacji.");
    }
  }

  searchInput?.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(loadReviews, 400);
  });
  statusFilter?.addEventListener("change", loadReviews);

  logoutBtn?.addEventListener("click", async () => {
    await fetch("/api/admin/logout", { method: "POST" });
    window.location.href = "/admin-panel/login";
  });

  loadReviews();
})();
