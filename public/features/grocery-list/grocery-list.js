(() => {
  const view = document.querySelector("[data-grocery-view]");

  if (!view) {
    return;
  }

  const loadingState  = view.querySelector("[data-grocery-loading]");
  const errorState    = view.querySelector("[data-grocery-error]");
  const content       = view.querySelector("[data-grocery-content]");
  const emptyState    = view.querySelector("[data-grocery-empty]");
  const weekLabel     = view.querySelector("[data-grocery-week]");
  const list          = view.querySelector("[data-grocery-list]");
  const searchInput   = view.querySelector("[data-grocery-search]");
  const tabs          = Array.from(view.querySelectorAll("[data-grocery-tab]"));
  const budgetLabel   = view.querySelector("[data-budget-label]");
  const budgetStatus  = view.querySelector("[data-budget-status]");
  const budgetProgress = view.querySelector("[data-budget-progress]");
  const budgetRemaining = view.querySelector("[data-budget-remaining]");
  const budgetPercent = view.querySelector("[data-budget-percent]");
  const boughtSummary = view.querySelector("[data-bought-summary]");
  const totalCost     = view.querySelector("[data-total-cost]");
  const savedCost     = view.querySelector("[data-saved-cost]");
  const addItemButton = view.querySelector("[data-add-item]");
  const exportButton  = view.querySelector("[data-export-list]");
  const exportDropdown = view.querySelector("[data-export-dropdown]");
  const exportPrintBtn = view.querySelector("[data-export-print]");
  const exportShareBtn = view.querySelector("[data-export-share]");

  const addItemDialog = document.querySelector("[data-add-item-dialog]");
  const addItemForm   = addItemDialog?.querySelector("[data-add-item-form]");
  const addItemName   = addItemDialog?.querySelector("[data-item-name]");
  const addItemQty    = addItemDialog?.querySelector("[data-item-qty]");
  const addItemCat    = addItemDialog?.querySelector("[data-item-cat]");
  const addItemError  = addItemDialog?.querySelector("[data-add-error]");
  const addItemSubmit = addItemDialog?.querySelector("[data-add-submit]");

  let groceryData = null;
  let activeTab   = "all";

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function normalize(value) {
    return String(value).trim().toLowerCase();
  }

  function formatMoney(value) {
    return `${Number(value).toFixed(2)} zł`;
  }

  function formatProductsCount(count) {
    if (count === 1) return "1 produkt";
    if (count > 1 && count < 5) return `${count} produkty`;
    return `${count} produktów`;
  }

  function getAllItems() {
    return groceryData.categories.flatMap((category) =>
      category.items.map((item) => ({
        ...item,
        categoryId:    category.id,
        categoryLabel: category.label,
        categoryIcon:  category.icon,
      }))
    );
  }

  function findItemById(itemId) {
    for (const category of groceryData.categories) {
      const item = category.items.find((entry) => entry.id === itemId);
      if (item) return item;
    }
    return null;
  }

  function setButtonFeedback(button, message) {
    const label = button?.querySelector("span");
    if (!button || !label) return;
    const initialText = label.textContent;
    button.classList.add("is-confirmed");
    label.textContent = message;
    window.setTimeout(() => {
      button.classList.remove("is-confirmed");
      label.textContent = initialText;
    }, 1200);
  }

  function getFilteredCategories() {
    const query      = normalize(searchInput?.value ?? "");
    const categories = groceryData.categories.map((category) => ({
      ...category,
      items: category.items.filter((item) => {
        const value = `${item.name} ${item.quantity}`;
        return !query || normalize(value).includes(query);
      }),
    }));

    if (activeTab === "recent") {
      const recentItems = getAllItems()
        .slice(-8)
        .filter((item) => {
          const value = `${item.name} ${item.quantity}`;
          return !query || normalize(value).includes(query);
        });

      return [{ id: "recent", label: "Ostatnio dodane", icon: "calendar.svg", items: recentItems }];
    }

    if (activeTab === "all") {
      return [{ id: "all", label: "Wszystkie produkty", icon: "grocery.svg", items: categories.flatMap((c) => c.items) }];
    }

    return categories.filter((category) => category.items.length > 0);
  }

  function createItem(item) {
    const checkedClass = item.isBought ? " is-bought" : "";
    const checked      = item.isBought ? "checked" : "";
    const qty          = item.quantity ? `<small>${escapeHtml(item.quantity)}</small>` : "";
    const alt          = item.alternative
      ? `<button class="grocery-alternative" type="button" data-alternative="${escapeHtml(item.alternative)}">Zamiennik</button>`
      : "";

    return `
      <article class="grocery-item${checkedClass}" data-grocery-item="${escapeHtml(item.id)}">
        <label class="grocery-item__check">
          <input type="checkbox" ${checked} />
          <span aria-hidden="true"></span>
        </label>
        <div class="grocery-item__content">
          <strong>${escapeHtml(item.name)}</strong>
          ${qty}
        </div>
        ${alt}
        <button class="grocery-item__delete" type="button"
          data-delete-item="${escapeHtml(item.id)}"
          aria-label="Usuń ${escapeHtml(item.name)}">×</button>
      </article>
    `;
  }

  function createCategory(category) {
    return `
      <section class="grocery-category" data-grocery-category="${escapeHtml(category.id)}">
        <header class="grocery-category__header">
          <div>
            <img src="/public/assets/icons/${escapeHtml(category.icon)}" alt="" />
            <h2>${escapeHtml(category.label)}</h2>
          </div>
          <span>${formatProductsCount(category.items.length)}</span>
        </header>
        <div class="grocery-category__items">
          ${category.items.map(createItem).join("")}
        </div>
      </section>
    `;
  }

  function updateSummary() {
    const items      = getAllItems();
    const boughtItems = items.filter((item) => item.isBought);
    const budget     = groceryData.budget;
    const progress   = budget.limit > 0 ? Math.min(100, Math.round((budget.spent / budget.limit) * 100)) : 0;
    const remaining  = Math.max(0, budget.limit - budget.spent);

    if (budgetLabel)    budgetLabel.textContent    = `${formatMoney(budget.spent)} / ${formatMoney(budget.limit)}`;
    if (budgetStatus)   budgetStatus.textContent   = budget.limit === 0 || remaining > 0 ? "W limicie" : "Poza limitem";
    if (budgetProgress) budgetProgress.style.width = `${progress}%`;
    if (budgetRemaining) budgetRemaining.textContent = `Pozostało ${formatMoney(remaining)}`;
    if (budgetPercent)  budgetPercent.textContent   = budget.limit > 0 ? `${progress}% budżetu` : "Brak budżetu";
    if (boughtSummary)  boughtSummary.textContent   = `${boughtItems.length} z ${items.length} produktów kupionych`;
    if (totalCost)      totalCost.textContent       = formatMoney(budget.spent);
    if (savedCost)      savedCost.textContent       = formatMoney(budget.saved);
  }

  function render() {
    const categories = getFilteredCategories();
    const hasItems   = categories.some((category) => category.items.length > 0);

    if (list) list.innerHTML = categories.map(createCategory).join("");
    if (emptyState) emptyState.hidden = hasItems;
    updateSummary();

    tabs.forEach((tab) => {
      const isActive = tab.dataset.groceryTab === activeTab;
      tab.classList.toggle("is-active", isActive);
      tab.setAttribute("aria-pressed", isActive ? "true" : "false");
    });
  }

  async function loadGroceryList() {
    try {
      const response = await fetch("/api/grocery-lists");
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      groceryData = await response.json();
      if (weekLabel) weekLabel.textContent = groceryData.weekLabel;
      if (loadingState) loadingState.hidden = true;
      if (errorState)   errorState.hidden   = true;
      if (content)      content.hidden      = false;
      render();
    } catch {
      if (loadingState) loadingState.hidden = true;
      if (errorState)   errorState.hidden   = false;
      if (content)      content.hidden      = true;
    }
  }

  // ── Tabs ──────────────────────────────────────────────────────────
  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      activeTab = tab.dataset.groceryTab ?? "all";
      render();
    });
  });

  searchInput?.addEventListener("input", render);

  // ── Check / uncheck item ──────────────────────────────────────────
  list?.addEventListener("change", async (event) => {
    const checkbox = event.target.closest('input[type="checkbox"]');
    if (!checkbox) return;

    const itemId = Number(checkbox.closest("[data-grocery-item]")?.dataset.groceryItem);
    const item   = findItemById(itemId);
    if (!item) return;

    item.isBought = checkbox.checked;
    render();

    try {
      await fetch(`/api/grocery-lists/${groceryData.listId}/items/${itemId}`, {
        method:  "PATCH",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ isChecked: checkbox.checked }),
      });
    } catch {
      // Optimistic update retained even if sync fails
    }
  });

  // ── Click delegation: alternative + delete ────────────────────────
  list?.addEventListener("click", async (event) => {
    const altBtn = event.target.closest("[data-alternative]");
    if (altBtn) {
      altBtn.textContent = altBtn.dataset.alternative;
      altBtn.classList.add("is-selected");
      return;
    }

    const deleteBtn = event.target.closest("[data-delete-item]");
    if (deleteBtn && groceryData) {
      const itemId    = Number(deleteBtn.dataset.deleteItem);
      const itemName  = deleteBtn.getAttribute("aria-label")?.replace("Usuń ", "") ?? "";
      deleteBtn.disabled = true;

      try {
        const res = await fetch(`/api/grocery-lists/${groceryData.listId}/items/${itemId}`, { method: "DELETE" });
        if (!res.ok) throw new Error();

        for (const cat of groceryData.categories) {
          cat.items = cat.items.filter((i) => i.id !== itemId);
        }
        render();
        window.toast?.success(`„${itemName}" usunięto z listy.`);
      } catch {
        deleteBtn.disabled = false;
        window.toast?.error("Nie udało się usunąć produktu.");
      }
    }
  });

  // ── Add item dialog ───────────────────────────────────────────────
  addItemButton?.addEventListener("click", () => {
    addItemForm?.reset();
    if (addItemError) addItemError.hidden = true;
    addItemDialog?.showModal();
    addItemName?.focus();
  });

  addItemDialog?.querySelectorAll("[data-close-add-item]").forEach((btn) => {
    btn.addEventListener("click", () => addItemDialog.close());
  });

  addItemDialog?.addEventListener("click", (e) => {
    if (e.target === addItemDialog) addItemDialog.close();
  });

  addItemForm?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const name    = addItemName?.value.trim() ?? "";
    const qty     = addItemQty?.value.trim() || null;
    const catCode = addItemCat?.value || "other";

    if (name.length < 2) {
      if (addItemError) {
        addItemError.textContent = "Podaj nazwę produktu (minimum 2 znaki).";
        addItemError.hidden      = false;
      }
      addItemName?.focus();
      return;
    }

    if (addItemError) addItemError.hidden = true;
    if (addItemSubmit) { addItemSubmit.disabled = true; addItemSubmit.textContent = "Dodaję…"; }

    try {
      const res  = await fetch(`/api/grocery-lists/${groceryData.listId}/items`, {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ name, quantity: qty, categoryCode: catCode }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error ?? "Błąd dodawania.");

      addItemDialog.close();
      await loadGroceryList();
      window.toast?.success(`„${name}" dodano do listy.`);
    } catch (err) {
      if (addItemError) {
        addItemError.textContent = err.message;
        addItemError.hidden      = false;
      }
    } finally {
      if (addItemSubmit) { addItemSubmit.disabled = false; addItemSubmit.textContent = "Dodaj produkt"; }
    }
  });

  // ── Export dropdown ───────────────────────────────────────────────
  exportButton?.addEventListener("click", (e) => {
    e.stopPropagation();
    const open = !exportDropdown?.hidden;
    if (exportDropdown) exportDropdown.hidden = open;
    exportButton.setAttribute("aria-expanded", String(!open));
  });

  document.addEventListener("click", () => {
    if (exportDropdown && !exportDropdown.hidden) {
      exportDropdown.hidden = true;
      exportButton?.setAttribute("aria-expanded", "false");
    }
  });

  exportPrintBtn?.addEventListener("click", () => {
    if (exportDropdown) exportDropdown.hidden = true;
    window.print();
  });

  exportShareBtn?.addEventListener("click", async () => {
    if (exportDropdown) exportDropdown.hidden = true;
    if (navigator.share) {
      try {
        await navigator.share({ title: "Lista zakupów - MealPlanner", url: window.location.href });
      } catch {}
    } else {
      try {
        await navigator.clipboard.writeText(window.location.href);
        window.toast?.success("Link skopiowany do schowka.");
      } catch {
        window.print();
      }
    }
  });

  loadGroceryList();
})();
