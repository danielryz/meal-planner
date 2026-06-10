(() => {
  const view = document.querySelector("[data-recipe-details-view]");
  if (!view) return;

  const loadingState     = view.querySelector("[data-recipe-loading]");
  const errorState       = view.querySelector("[data-recipe-error]");
  const content          = view.querySelector("[data-recipe-content]");
  const labelEl          = view.querySelector("[data-recipe-label]");
  const titleEl          = view.querySelector("[data-recipe-title]");
  const descriptionEl    = view.querySelector("[data-recipe-description]");
  const servingsEl       = view.querySelector("[data-recipe-servings]");
  const prepTimeEl       = view.querySelector("[data-recipe-prep-time]");
  const cookTimeEl       = view.querySelector("[data-recipe-cook-time]");
  const caloriesEl       = view.querySelector("[data-recipe-calories]");
  const ingredientsCount = view.querySelector("[data-ingredients-count]");
  const ingredientsList  = view.querySelector("[data-ingredients-list]");
  const nutritionList    = view.querySelector("[data-nutrition-list]");
  const chefTip          = view.querySelector("[data-chef-tip]");
  const tipSection       = view.querySelector("[data-tip-section]");
  const stepsList        = view.querySelector("[data-steps-list]");
  const relatedSection   = view.querySelector("[data-related-section]");
  const relatedList      = view.querySelector("[data-related-list]");
  const decreaseBtn      = view.querySelector("[data-servings-decrease]");
  const increaseBtn      = view.querySelector("[data-servings-increase]");
  const favoriteBtn      = view.querySelector("[data-favorite-btn]");
  const addToPlanBtn     = view.querySelector("[data-add-to-plan]");
  const addToGroceryBtn  = view.querySelector("[data-add-to-grocery]");

  const planDialog       = document.querySelector("[data-plan-dialog]");
  const planDay          = planDialog?.querySelector("[data-plan-day]");
  const planMealType     = planDialog?.querySelector("[data-plan-meal-type]");
  const planConfirmBtn   = planDialog?.querySelector("[data-plan-confirm]");

  const isAuthenticated  = view.dataset.authenticated === 'true';

  let recipeId        = null;
  let recipe          = null;
  let baseServings    = 1;
  let currentServings = 1;
  let groceryListId   = null;

  const NUTRITION_LABELS = {
    calories: 'Kalorie', protein: 'Białko', fat: 'Tłuszcz',
    carbohydrates: 'Węglowodany', fiber: 'Błonnik',
  };
  const NUTRITION_UNITS = {
    calories: 'kcal', protein: 'g', fat: 'g', carbohydrates: 'g', fiber: 'g',
  };

  const DAY_NAMES = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

  function escapeHtml(value) {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function getServingLabel(n) {
    if (n === 1) return '1 porcja';
    if (n < 5)   return `${n} porcje`;
    return `${n} porcji`;
  }

  function scaleAmount(amount, ratio) {
    if (ratio === 1 || !amount) return amount;
    return amount.replace(/\d+(?:[.,]\d+)?/g, (match) => {
      const n = parseFloat(match.replace(',', '.')) * ratio;
      return n % 1 === 0 ? String(n) : n.toFixed(1).replace('.', ',');
    });
  }

  function getRecipeId() {
    const parts = window.location.pathname.split('/').filter(Boolean);
    if (parts[0] === 'recipe' && Number(parts[1]) > 0) return Number(parts[1]);
    return Number(new URLSearchParams(window.location.search).get('id') || 0);
  }

  function renderServings() {
    if (servingsEl) servingsEl.textContent = getServingLabel(currentServings);
    if (decreaseBtn) decreaseBtn.disabled = currentServings <= 1;
  }

  function renderIngredients() {
    const ratio = currentServings / baseServings;
    if (!ingredientsList) return;

    ingredientsList.innerHTML = (recipe.ingredients ?? []).map(ing => {
      const scaled = scaleAmount(ing.amount ?? '', ratio);
      return `
        <li>
          <span aria-hidden="true"></span>
          <strong>${escapeHtml(ing.name)}</strong>
          <small>${escapeHtml(scaled)}</small>
          <button
            class="recipe-ingredient-add"
            type="button"
            aria-label="Dodaj ${escapeHtml(ing.name)} do listy zakupów"
            data-add-ingredient="${escapeHtml(ing.name)}"
            data-ingredient-amount="${escapeHtml(ing.amount ?? '')}">+</button>
        </li>`;
    }).join('');

    if (ingredientsCount) {
      const n = (recipe.ingredients ?? []).length;
      ingredientsCount.textContent = `${n} składników`;
    }
  }

  function renderNutrition(nutrition) {
    if (!nutritionList || !nutrition) return;
    nutritionList.innerHTML = Object.entries(nutrition)
      .filter(([, v]) => v != null)
      .map(([k, v]) => `
        <div>
          <dt>${escapeHtml(NUTRITION_LABELS[k] ?? k)}</dt>
          <dd>${escapeHtml(String(v))} ${escapeHtml(NUTRITION_UNITS[k] ?? '')}</dd>
        </div>`)
      .join('');
  }

  function renderSteps(steps) {
    if (!stepsList) return;
    stepsList.innerHTML = (steps ?? [])
      .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
      .map((step, i) => `
        <li>
          <span>${i + 1}</span>
          <div><p>${escapeHtml(step.instruction ?? '')}</p></div>
        </li>`)
      .join('');
  }

  function renderRelated(related) {
    if (!relatedSection || !relatedList || !related?.length) return;

    relatedList.innerHTML = related.map(r => `
      <article class="recipe-card">
        <div class="recipe-card__media recipe-card__media--green">
          <a class="recipe-card__image-link" href="/recipe/${escapeHtml(r.id)}" aria-label="${escapeHtml(r.title)}"></a>
          <span class="recipe-card__label">${escapeHtml(r.category ?? '')}</span>
        </div>
        <div class="recipe-card__body">
          <h3><a href="/recipe/${escapeHtml(r.id)}">${escapeHtml(r.title)}</a></h3>
          <div class="recipe-meta">
            <span class="recipe-meta__item">
              <img src="/public/assets/icons/clock.svg" alt="" />
              ${escapeHtml(String(r.prepTimeMinutes))} min
            </span>
            <span class="recipe-meta__item">
              <img src="/public/assets/icons/cutlery.svg" alt="" />
              ${escapeHtml(getServingLabel(r.servings))}
            </span>
          </div>
        </div>
      </article>`).join('');

    relatedSection.hidden = false;
  }

  function renderRecipe(data) {
    recipe          = data;
    baseServings    = data.servings || 1;
    currentServings = baseServings;

    if (labelEl)       labelEl.textContent       = data.category ?? '';
    if (titleEl)       titleEl.textContent        = data.title ?? '';
    if (descriptionEl) descriptionEl.textContent  = data.description ?? '';
    if (prepTimeEl)    prepTimeEl.textContent      = data.prepTimeMinutes ? `${data.prepTimeMinutes} min` : '—';
    if (cookTimeEl)    cookTimeEl.textContent      = data.cookTimeMinutes ? `${data.cookTimeMinutes} min` : '—';
    if (caloriesEl)    caloriesEl.textContent      = data.nutrition?.calories ? `${data.nutrition.calories} kcal` : '—';

    if (favoriteBtn) {
      favoriteBtn.classList.toggle('is-active', !!data.isFavorite);
      favoriteBtn.setAttribute('aria-pressed', data.isFavorite ? 'true' : 'false');
      favoriteBtn.setAttribute('aria-label', data.isFavorite ? 'Usuń z ulubionych' : 'Dodaj do ulubionych');
    }

    renderServings();
    renderIngredients();
    renderNutrition(data.nutrition);
    renderSteps(data.steps);
    renderRelated(data.related);

    if (data.tip && chefTip && tipSection) {
      chefTip.textContent = data.tip;
      tipSection.hidden = false;
    }

    if (loadingState) loadingState.hidden = true;
    if (errorState)   errorState.hidden   = true;
    if (content)      content.hidden      = false;
  }

  async function getGroceryListId() {
    if (groceryListId) return groceryListId;
    const res = await fetch('/api/grocery-lists');
    if (!res.ok) throw new Error('no list');
    const data = await res.json();
    groceryListId = data.listId;
    return groceryListId;
  }

  async function addToGroceryList(name, quantity) {
    const listId = await getGroceryListId();
    const res = await fetch(`/api/grocery-lists/${listId}/items`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, quantity: quantity || null }),
    });
    if (!res.ok) throw new Error('failed');
    return res.json();
  }

  async function loadRecipe() {
    recipeId = getRecipeId();
    if (!recipeId) {
      if (loadingState) loadingState.hidden = true;
      if (errorState)   errorState.hidden   = false;
      return;
    }
    try {
      const res = await fetch(`/api/recipes/${recipeId}`);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      renderRecipe(data);
    } catch {
      if (loadingState) loadingState.hidden = true;
      if (errorState)   errorState.hidden   = false;
      if (content)      content.hidden      = true;
      window.toast?.error('Nie udało się załadować przepisu.');
    }
  }

  decreaseBtn?.addEventListener('click', () => {
    currentServings = Math.max(1, currentServings - 1);
    renderServings();
    if (recipe) renderIngredients();
  });

  increaseBtn?.addEventListener('click', () => {
    currentServings++;
    renderServings();
    if (recipe) renderIngredients();
  });

  favoriteBtn?.addEventListener('click', async () => {
    if (!recipe) return;

    const wasFavorite = favoriteBtn.classList.contains('is-active');
    favoriteBtn.classList.toggle('is-active', !wasFavorite);
    favoriteBtn.setAttribute('aria-pressed', !wasFavorite ? 'true' : 'false');

    try {
      const res = await fetch(`/api/recipes/${recipeId}/favorite`, { method: 'POST' });
      if (!res.ok) throw new Error('failed');
      const data = await res.json();
      favoriteBtn.classList.toggle('is-active', data.isFavorite);
      favoriteBtn.setAttribute('aria-pressed', data.isFavorite ? 'true' : 'false');
      favoriteBtn.setAttribute('aria-label', data.isFavorite ? 'Usuń z ulubionych' : 'Dodaj do ulubionych');
      window.toast?.[data.isFavorite ? 'success' : 'info'](
        data.isFavorite ? 'Dodano do ulubionych.' : 'Usunięto z ulubionych.'
      );
    } catch {
      favoriteBtn.classList.toggle('is-active', wasFavorite);
      favoriteBtn.setAttribute('aria-pressed', wasFavorite ? 'true' : 'false');
      window.toast?.error('Nie udało się zaktualizować ulubionych.');
    }
  });

  addToGroceryBtn?.addEventListener('click', async () => {
    if (!recipe?.ingredients?.length) return;
    const ratio = currentServings / baseServings;

    addToGroceryBtn.disabled = true;
    try {
      const listId = await getGroceryListId();
      let added = 0;
      for (const ing of recipe.ingredients) {
        const qty = scaleAmount(ing.amount ?? '', ratio);
        const res = await fetch(`/api/grocery-lists/${listId}/items`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ name: ing.name, quantity: qty || null }),
        });
        if (res.ok) added++;
      }
      window.toast?.success(`${added} składników dodano do listy zakupów.`);
    } catch {
      window.toast?.error('Nie udało się dodać składników do listy.');
    } finally {
      addToGroceryBtn.disabled = false;
    }
  });

  ingredientsList?.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-add-ingredient]');
    if (!btn) return;
    const name = btn.dataset.addIngredient;
    const ratio = currentServings / baseServings;
    const qty   = scaleAmount(btn.dataset.ingredientAmount, ratio);
    btn.disabled = true;
    try {
      await addToGroceryList(name, qty);
      window.toast?.success(`${name} dodano do listy zakupów.`);
    } catch {
      window.toast?.error('Nie udało się dodać do listy zakupów.');
    } finally {
      btn.disabled = false;
    }
  });

  addToPlanBtn?.addEventListener('click', () => {
    planDialog?.showModal();
  });

  planConfirmBtn?.addEventListener('click', async () => {
    if (!recipe || !planDay || !planMealType) return;

    const selectedDay      = planDay.value;
    const selectedMealType = planMealType.value;

    planConfirmBtn.disabled = true;

    try {
      const plansRes = await fetch('/api/meal-plans');
      if (!plansRes.ok) throw new Error('plans_fetch');
      const plansData = await plansRes.json();

      const activePlan = (plansData.plans ?? []).find(p => p.status === 'active');
      if (!activePlan) {
        planDialog?.close();
        window.toast?.warning('Brak aktywnego planu posiłków. Utwórz plan na stronie planera.');
        return;
      }

      const planRes = await fetch(`/api/meal-plans/${activePlan.id}`);
      if (!planRes.ok) throw new Error('plan_fetch');
      const planData = await planRes.json();

      const matchingDay = (planData.days ?? []).find(day => {
        const weekday = DAY_NAMES[new Date(day.date + 'T12:00:00').getDay()];
        return weekday === selectedDay;
      });

      if (!matchingDay) {
        planDialog?.close();
        window.toast?.warning('Wybrany dzień nie istnieje w aktywnym planie.');
        return;
      }

      const matchingSlot = (matchingDay.slots ?? []).find(s => s.type === selectedMealType);

      if (!matchingSlot) {
        planDialog?.close();
        window.toast?.warning('Wybrany typ posiłku nie istnieje w planie. Edytuj plan i dodaj odpowiednią porę.');
        return;
      }

      const addRes = await fetch(`/api/meal-plans/${activePlan.id}/slots/${matchingSlot.id}/recipes`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ recipeId, servings: currentServings }),
      });

      if (!addRes.ok) throw new Error('add_failed');

      planDialog?.close();
      window.toast?.success('Przepis dodany do planu posiłków.');
    } catch {
      planDialog?.close();
      window.toast?.error('Nie udało się dodać przepisu do planu.');
    } finally {
      if (planConfirmBtn) planConfirmBtn.disabled = false;
    }
  });

  planDialog?.addEventListener('click', (e) => {
    if (e.target === planDialog) planDialog.close();
  });

  loadRecipe();
})();
