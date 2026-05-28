(() => {
  const view = document.querySelector("[data-grocery-view]");
  const groceryUrl = "/public/mock/grocery_list_mock.json";

  if (!view) {
    return;
  }

  const loadingState = view.querySelector("[data-grocery-loading]");
  const errorState = view.querySelector("[data-grocery-error]");
  const content = view.querySelector("[data-grocery-content]");
  const emptyState = view.querySelector("[data-grocery-empty]");
  const weekLabel = view.querySelector("[data-grocery-week]");
  const list = view.querySelector("[data-grocery-list]");
  const searchInput = view.querySelector("[data-grocery-search]");
  const tabs = Array.from(view.querySelectorAll("[data-grocery-tab]"));
  const budgetLabel = view.querySelector("[data-budget-label]");
  const budgetStatus = view.querySelector("[data-budget-status]");
  const budgetProgress = view.querySelector("[data-budget-progress]");
  const budgetRemaining = view.querySelector("[data-budget-remaining]");
  const budgetPercent = view.querySelector("[data-budget-percent]");
  const boughtSummary = view.querySelector("[data-bought-summary]");
  const totalCost = view.querySelector("[data-total-cost]");
  const savedCost = view.querySelector("[data-saved-cost]");
  const addItemButton = view.querySelector("[data-add-item]");
  const exportButton = view.querySelector("[data-export-list]");
  let groceryData = null;
  let activeTab = "categorized";

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

  function formatMoney(value) {
    return `${Number(value).toFixed(2)} zł`;
  }

  function formatProductsCount(count) {
    if (count === 1) {
      return "1 produkt";
    }

    if (count > 1 && count < 5) {
      return `${count} produkty`;
    }

    return `${count} produktów`;
  }

  function getAllItems() {
    return groceryData.categories.flatMap((category) =>
      category.items.map((item) => ({
        ...item,
        categoryId: category.id,
        categoryLabel: category.label,
        categoryIcon: category.icon,
      }))
    );
  }

  function findItemById(itemId) {
    for (const category of groceryData.categories) {
      const item = category.items.find((entry) => entry.id === itemId);

      if (item) {
        return item;
      }
    }

    return null;
  }

  function setButtonFeedback(button, message) {
    const label = button?.querySelector("span");

    if (!button || !label) {
      return;
    }

    const initialText = label.textContent;
    button.classList.add("is-confirmed");
    label.textContent = message;

    window.setTimeout(() => {
      button.classList.remove("is-confirmed");
      label.textContent = initialText;
    }, 1200);
  }

  function getFilteredCategories() {
    const query = normalize(searchInput?.value ?? "");
    const categories = groceryData.categories.map((category) => ({
      ...category,
      items: category.items.filter((item) => {
        const value = `${item.name} ${item.quantity} ${item.alternative}`;
        return !query || normalize(value).includes(query);
      }),
    }));

    if (activeTab === "recent") {
      const recentItems = getAllItems().slice(-4).filter((item) => {
        const value = `${item.name} ${item.quantity} ${item.alternative}`;
        return !query || normalize(value).includes(query);
      });

      return [
        {
          id: "recent",
          label: "Ostatnio dodane",
          icon: "calendar.svg",
          items: recentItems,
        },
      ];
    }

    if (activeTab === "all") {
      return [
        {
          id: "all",
          label: "Wszystkie produkty",
          icon: "grocery.svg",
          items: categories.flatMap((category) => category.items),
        },
      ];
    }

    return categories.filter((category) => category.items.length > 0);
  }

  function createItem(item) {
    const checkedClass = item.isBought ? " is-bought" : "";
    const checked = item.isBought ? "checked" : "";

    return `
      <article class="grocery-item${checkedClass}" data-grocery-item="${escapeHtml(item.id)}">
        <label class="grocery-item__check">
          <input type="checkbox" ${checked} />
          <span aria-hidden="true"></span>
        </label>
        <div class="grocery-item__content">
          <strong>${escapeHtml(item.name)}</strong>
          <small>${escapeHtml(item.quantity)} · ok. ${escapeHtml(formatMoney(item.estimatedPrice))}</small>
        </div>
        <button class="grocery-alternative" type="button" data-alternative="${escapeHtml(item.alternative)}">
          Zamiennik
        </button>
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
    const items = getAllItems();
    const boughtItems = items.filter((item) => item.isBought);
    const spent = items.reduce((sum, item) => sum + Number(item.estimatedPrice), 0);
    const budget = groceryData.budget;
    const progress = Math.min(100, Math.round((budget.spent / budget.limit) * 100));
    const remaining = Math.max(0, budget.limit - budget.spent);

    budgetLabel.textContent = `${formatMoney(budget.spent)} / ${formatMoney(budget.limit)}`;
    budgetStatus.textContent = remaining > 0 ? "W limicie" : "Poza limitem";
    budgetProgress.style.width = `${progress}%`;
    budgetRemaining.textContent = `Pozostało ${formatMoney(remaining)}`;
    budgetPercent.textContent = `${progress}% budżetu`;
    boughtSummary.textContent = `${boughtItems.length} z ${items.length} produktów kupionych`;
    totalCost.textContent = formatMoney(spent);
    savedCost.textContent = formatMoney(budget.saved);
  }

  function render() {
    const categories = getFilteredCategories();
    const hasItems = categories.some((category) => category.items.length > 0);

    list.innerHTML = categories.map(createCategory).join("");
    emptyState.hidden = hasItems;
    updateSummary();

    tabs.forEach((tab) => {
      const isActive = tab.dataset.groceryTab === activeTab;
      tab.classList.toggle("is-active", isActive);
      tab.setAttribute("aria-pressed", isActive ? "true" : "false");
    });
  }

  async function loadGroceryList() {
    try {
      const response = await fetch(groceryUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      groceryData = await response.json();
      weekLabel.textContent = groceryData.weekLabel;
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

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      activeTab = tab.dataset.groceryTab ?? "categorized";
      render();
    });
  });

  searchInput?.addEventListener("input", render);

  list.addEventListener("change", (event) => {
    const checkbox = event.target.closest('input[type="checkbox"]');

    if (!checkbox) {
      return;
    }

    const itemId = Number(checkbox.closest("[data-grocery-item]")?.dataset.groceryItem);
    const item = findItemById(itemId);

    if (item) {
      item.isBought = checkbox.checked;
      render();
    }
  });

  list.addEventListener("click", (event) => {
    const button = event.target.closest("[data-alternative]");

    if (!button) {
      return;
    }

    button.textContent = button.dataset.alternative;
    button.classList.add("is-selected");
  });

  addItemButton?.addEventListener("click", () => {
    setButtonFeedback(addItemButton, "Wkrótce");
  });

  exportButton?.addEventListener("click", () => {
    setButtonFeedback(exportButton, "Gotowe");
  });

  loadGroceryList();
})();
