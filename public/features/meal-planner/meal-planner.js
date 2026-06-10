(() => {
  const SLOT_ORDER  = ['breakfast', 'lunch', 'dinner', 'snack'];
  const SLOT_LABELS = { breakfast: 'Śniadanie', lunch: 'Obiad', dinner: 'Kolacja', snack: 'Przekąska' };
  const DAY_SHORT   = ['Nd', 'Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob'];
  const PL_MONTHS   = ['stycznia','lutego','marca','kwietnia','maja','czerwca','lipca','sierpnia','września','października','listopada','grudnia'];

  function escapeHtml(v) {
    return String(v ?? '').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
  }

  function getMondayOf(date) {
    const d   = new Date(date);
    const dow = d.getDay();
    d.setDate(d.getDate() - (dow === 0 ? 6 : dow - 1));
    d.setHours(0, 0, 0, 0);
    return d;
  }

  function toDateStr(date) {
    return date.toISOString().slice(0, 10);
  }

  function formatWeekLabel(monday) {
    const sunday = new Date(monday);
    sunday.setDate(sunday.getDate() + 6);
    const startDay = monday.getDate();
    const endDay   = sunday.getDate();
    if (monday.getMonth() === sunday.getMonth()) {
      return `${startDay}–${endDay} ${PL_MONTHS[sunday.getMonth()]} ${sunday.getFullYear()}`;
    }
    return `${startDay} ${PL_MONTHS[monday.getMonth()]} – ${endDay} ${PL_MONTHS[sunday.getMonth()]} ${sunday.getFullYear()}`;
  }

  // ================================================================
  // CALENDAR
  // ================================================================

  const calendarView  = document.querySelector('[data-calendar-view]');
  const calendarGrid  = document.querySelector('[data-calendar-grid]');
  const weekLabel     = document.querySelector('[data-week-label]');
  const prevWeekBtn   = document.querySelector('[data-prev-week]');
  const nextWeekBtn   = document.querySelector('[data-next-week]');
  const noWeekMsg     = document.querySelector('[data-no-plan-week]');
  const setupBanner   = document.querySelector('[data-setup-banner]');
  const todayPanel    = document.querySelector('[data-today-panel]');
  const generateBtn   = document.querySelector('[data-generate-grocery]');
  const editPlanBtn   = document.querySelector('[data-edit-plan]');
  const createWeekBtn = document.querySelector('[data-create-week-plan]');
  const startSetupBtn = document.querySelector('[data-start-setup]');

  const recipePicker  = document.querySelector('[data-recipe-picker]');
  const pickerTitle   = recipePicker?.querySelector('h2');
  const pickerSearch  = recipePicker?.querySelector('[data-picker-search]');
  const pickerResults = recipePicker?.querySelector('[data-picker-results]');
  const closePicker   = recipePicker?.querySelector('[data-close-picker]');

  const wizardView    = document.querySelector('[data-wizard-view]');

  let currentPlanId          = null;
  let currentPlan            = null;
  let currentWeek            = getMondayOf(new Date());
  let pendingSlotId          = null;
  let pendingReplaceRecipeId = null;
  let pickerTimer            = null;

  const activePlanId = Number(calendarView?.dataset.activePlanId) || null;

  if (activePlanId) {
    loadWeek(currentWeek);
  } else {
    if (weekLabel)    weekLabel.textContent = formatWeekLabel(currentWeek);
    if (setupBanner)  setupBanner.hidden    = false;
    if (generateBtn)  generateBtn.hidden    = true;
    if (editPlanBtn)  editPlanBtn.hidden    = true;
  }

  async function loadWeek(monday) {
    currentWeek = monday;
    if (weekLabel) weekLabel.textContent = formatWeekLabel(monday);

    const weekStr   = toDateStr(monday);
    const plansRes  = await fetch('/api/meal-plans');
    if (!plansRes.ok) { window.toast?.error('Nie udało się załadować planów.'); return; }
    const plansData = await plansRes.json();

    const plan = (plansData.plans ?? []).find(p => p.weekStartDate === weekStr);

    if (!plan) {
      currentPlanId = null;
      currentPlan   = null;
      if (calendarGrid)  calendarGrid.innerHTML = '';
      if (noWeekMsg)     noWeekMsg.hidden       = false;
      if (todayPanel)    todayPanel.hidden      = true;
      if (generateBtn)   generateBtn.disabled   = true;
      return;
    }

    if (noWeekMsg)   noWeekMsg.hidden   = true;
    if (generateBtn) generateBtn.disabled = false;

    const detailRes = await fetch(`/api/meal-plans/${plan.id}`);
    if (!detailRes.ok) { window.toast?.error('Nie udało się załadować szczegółów planu.'); return; }

    currentPlan   = await detailRes.json();
    currentPlanId = currentPlan.id;
    renderCalendar(currentPlan);
    renderTodayPanel(currentPlan);
  }

  function renderCalendar(plan) {
    if (!calendarGrid) return;

    const days  = plan.days ?? [];
    const today = toDateStr(new Date());

    const slotTypesSet = new Set();
    days.forEach(d => d.slots?.forEach(s => slotTypesSet.add(s.type)));
    const slotTypes = SLOT_ORDER.filter(t => slotTypesSet.has(t));

    const slotMap = {};
    days.forEach(d => {
      slotMap[d.date] = {};
      (d.slots ?? []).forEach(s => { slotMap[d.date][s.type] = s; });
    });

    const cols = `grid-template-columns: 110px repeat(${days.length}, minmax(0, 1fr))`;

    let html = `<div class="planner-calendar__head" style="${cols}">`;
    html += '<div class="planner-calendar__corner"></div>';
    days.forEach(d => {
      const date    = new Date(d.date + 'T12:00:00');
      const isToday = d.date === today;
      html += `<div class="planner-calendar__day-col${isToday ? ' is-today' : ''}">
        <span class="planner-calendar__day-name">${DAY_SHORT[date.getDay()]}</span>
        <span class="planner-calendar__day-num">${date.getDate()}</span>
      </div>`;
    });
    html += '</div>';

    slotTypes.forEach(type => {
      html += `<div class="planner-calendar__row" style="${cols}">`;
      html += `<div class="planner-calendar__meal-label">${escapeHtml(SLOT_LABELS[type] ?? type)}</div>`;

      days.forEach(d => {
        const slot    = slotMap[d.date]?.[type];
        const isToday = d.date === today;
        html += `<div class="planner-calendar__cell${isToday ? ' is-today' : ''}">`;

        if (slot) {
          (slot.recipes ?? []).forEach(r => {
            html += `<div class="planner-slot-recipe">
              <a class="planner-slot-recipe__title" href="/recipe/${escapeHtml(r.id)}">${escapeHtml(r.title)}</a>
              <div class="planner-slot-recipe__actions">
                <button class="planner-slot-recipe__replace" type="button"
                  data-replace-recipe="${escapeHtml(r.id)}"
                  data-slot-id="${escapeHtml(slot.id)}"
                  aria-label="Zamień ${escapeHtml(r.title)}">↩</button>
                <button class="planner-slot-recipe__remove" type="button"
                  data-remove-recipe="${escapeHtml(r.id)}"
                  data-slot-id="${escapeHtml(slot.id)}"
                  aria-label="Usuń ${escapeHtml(r.title)} z planu">×</button>
              </div>
            </div>`;
          });
          html += `<button class="planner-cell-add" type="button" data-add-to-slot="${escapeHtml(slot.id)}" aria-label="Dodaj przepis do slotu">+</button>`;
        } else {
          html += '<span class="planner-cell-empty">—</span>';
        }

        html += '</div>';
      });

      html += '</div>';
    });

    calendarGrid.innerHTML = html;
  }

  function renderTodayPanel(plan) {
    if (!todayPanel) return;

    const today    = toDateStr(new Date());
    const todayDay = (plan.days ?? []).find(d => d.date === today);

    if (!todayDay) {
      todayPanel.hidden = true;
      return;
    }

    const meals = [];
    SLOT_ORDER.forEach(type => {
      const slot = (todayDay.slots ?? []).find(s => s.type === type);
      if (slot) {
        (slot.recipes ?? []).forEach(r => meals.push({ type, ...r }));
      }
    });

    if (!meals.length) {
      todayPanel.hidden = true;
      return;
    }

    const list = todayPanel.querySelector('[data-today-list]');
    if (list) {
      list.innerHTML = meals.map(m => `
        <li class="planner-today-meal">
          <span class="planner-today-meal__type">${escapeHtml(SLOT_LABELS[m.type] ?? m.type)}</span>
          <a class="planner-today-meal__name" href="/recipe/${escapeHtml(m.id)}">${escapeHtml(m.title)}</a>
          ${m.prepTimeMinutes ? `<span class="planner-today-meal__time">${escapeHtml(String(m.prepTimeMinutes))} min</span>` : ''}
          <a class="button button-secondary planner-today-meal__btn" href="/recipe/${escapeHtml(m.id)}">Przejdź do przepisu</a>
        </li>
      `).join('');
    }

    todayPanel.hidden = false;
  }

  calendarGrid?.addEventListener('click', async (e) => {
    const removeBtn = e.target.closest('[data-remove-recipe]');
    if (removeBtn) {
      const recipeId = removeBtn.dataset.removeRecipe;
      const slotId   = removeBtn.dataset.slotId;
      removeBtn.disabled = true;
      try {
        const res = await fetch(`/api/meal-plans/${currentPlanId}/slots/${slotId}/recipes/${recipeId}`, { method: 'DELETE' });
        if (!res.ok) throw new Error();
        await loadWeek(currentWeek);
        window.toast?.success('Przepis usunięty z planu.');
      } catch {
        removeBtn.disabled = false;
        window.toast?.error('Nie udało się usunąć przepisu.');
      }
      return;
    }

    const replaceBtn = e.target.closest('[data-replace-recipe]');
    if (replaceBtn) {
      pendingSlotId          = replaceBtn.dataset.slotId;
      pendingReplaceRecipeId = replaceBtn.dataset.replaceRecipe;
      openPicker('Zamień przepis');
      return;
    }

    const addBtn = e.target.closest('[data-add-to-slot]');
    if (addBtn) {
      pendingSlotId          = addBtn.dataset.addToSlot;
      pendingReplaceRecipeId = null;
      openPicker('Wybierz przepis');
    }
  });

  function openPicker(title = 'Wybierz przepis') {
    if (pickerTitle)   pickerTitle.textContent = title;
    if (pickerSearch)  pickerSearch.value = '';
    if (pickerResults) pickerResults.innerHTML = '<li class="recipe-picker-empty">Zacznij pisać, aby wyszukać przepis.</li>';
    recipePicker?.showModal();
    pickerSearch?.focus();
  }

  prevWeekBtn?.addEventListener('click', () => {
    const d = new Date(currentWeek);
    d.setDate(d.getDate() - 7);
    loadWeek(d);
  });

  nextWeekBtn?.addEventListener('click', () => {
    const d = new Date(currentWeek);
    d.setDate(d.getDate() + 7);
    loadWeek(d);
  });

  generateBtn?.addEventListener('click', async () => {
    if (!currentPlanId) return;
    generateBtn.disabled = true;
    const orig = generateBtn.textContent;
    generateBtn.textContent = 'Generuję…';
    try {
      const res = await fetch('/api/grocery-lists/generate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ planId: currentPlanId }),
      });
      if (!res.ok) throw new Error();
      const data = await res.json();
      window.toast?.success(`${data.added} składników dodano do listy zakupów.`);
      setTimeout(() => { window.location.href = '/grocery-list'; }, 1200);
    } catch {
      window.toast?.error('Nie udało się wygenerować listy zakupów.');
      generateBtn.disabled = false;
      generateBtn.textContent = orig;
    }
  });

  function showWizard() {
    if (calendarView) calendarView.hidden = true;
    if (wizardView)   wizardView.hidden   = false;
    initWizard();
  }

  editPlanBtn?.addEventListener('click', showWizard);
  startSetupBtn?.addEventListener('click', showWizard);

  createWeekBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    showWizard();
  });

  // ================================================================
  // RECIPE PICKER
  // ================================================================

  closePicker?.addEventListener('click', () => {
    recipePicker?.close();
    pendingReplaceRecipeId = null;
  });
  recipePicker?.addEventListener('click', e => {
    if (e.target === recipePicker) {
      recipePicker.close();
      pendingReplaceRecipeId = null;
    }
  });

  pickerSearch?.addEventListener('input', () => {
    clearTimeout(pickerTimer);
    pickerTimer = setTimeout(() => searchRecipes(pickerSearch.value.trim()), 300);
  });

  async function searchRecipes(q) {
    if (!pickerResults) return;
    pickerResults.innerHTML = '<li class="recipe-picker-loading">Szukam…</li>';
    try {
      const params = new URLSearchParams({ page: '1' });
      if (q) params.set('q', q);
      const res  = await fetch(`/api/recipes?${params}`);
      if (!res.ok) throw new Error();
      const data = await res.json();
      const list = data.recipes ?? [];
      if (!list.length) {
        pickerResults.innerHTML = '<li class="recipe-picker-empty">Brak wyników.</li>';
        return;
      }
      pickerResults.innerHTML = list.map(r =>
        `<li><button type="button" data-pick-recipe="${escapeHtml(r.id)}">${escapeHtml(r.title)}<small>${escapeHtml(String(r.cookingTimeMinutes))} min</small></button></li>`
      ).join('');
    } catch {
      pickerResults.innerHTML = '<li class="recipe-picker-empty">Błąd wyszukiwania.</li>';
    }
  }

  pickerResults?.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-pick-recipe]');
    if (!btn || !pendingSlotId || !currentPlanId) return;

    const recipeId    = btn.dataset.pickRecipe;
    const replaceId   = pendingReplaceRecipeId;
    btn.disabled      = true;

    try {
      if (replaceId) {
        const delRes = await fetch(`/api/meal-plans/${currentPlanId}/slots/${pendingSlotId}/recipes/${replaceId}`, { method: 'DELETE' });
        if (!delRes.ok) throw new Error();
      }

      const res = await fetch(`/api/meal-plans/${currentPlanId}/slots/${pendingSlotId}/recipes`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ recipeId: Number(recipeId), servings: 1 }),
      });
      if (!res.ok) throw new Error();

      pendingReplaceRecipeId = null;
      recipePicker?.close();
      await loadWeek(currentWeek);
      window.toast?.success(replaceId ? 'Przepis zamieniony.' : 'Przepis dodany do planu.');
    } catch {
      btn.disabled = false;
      window.toast?.error(replaceId ? 'Nie udało się zamienić przepisu.' : 'Nie udało się dodać przepisu.');
    }
  });

  // ================================================================
  // WIZARD
  // ================================================================

  function initWizard() {
    const stepForm = document.querySelector('[data-step-form]');
    if (!stepForm || stepForm.__initialized) return;
    stepForm.__initialized = true;

    const householdInput  = stepForm.querySelector('#household-size');
    const householdOutput = stepForm.querySelector('output[for="household-size"]');

    householdInput?.addEventListener('input', () => {
      if (householdOutput) householdOutput.textContent = getPeopleLabel(householdInput.value);
    });

    const panels         = Array.from(stepForm.querySelectorAll('[data-step-panel]'));
    const navButtons     = Array.from(stepForm.querySelectorAll('[data-step-nav]'));
    const prevBtn        = stepForm.querySelector('[data-step-prev]');
    const nextBtn        = stepForm.querySelector('[data-step-next]');
    const stepLabel      = stepForm.querySelector('[data-step-label]');
    const progressLabel  = stepForm.querySelector('[data-progress-label]');
    const progressBar    = stepForm.querySelector('[data-progress-bar]');
    const plannerMessage = stepForm.querySelector('[data-planner-message]');

    let currentStep       = 0;
    let maxAvailableStep  = 0;

    const goalLabels = { save_money: 'Oszczędzanie', eat_healthier: 'Zdrowe jedzenie', reduce_waste: 'Ograniczenie marnowania', meal_prep: 'Planowanie z wyprzedzeniem' };
    const dietLabels = { none: 'Bez preferencji', vegetarian: 'Wegetariańska', vegan: 'Wegańska', gluten_free: 'Bez glutenu', lactose_free: 'Bez laktozy' };
    const dayLabels  = { monday: 'Pon', tuesday: 'Wt', wednesday: 'Śr', thursday: 'Czw', friday: 'Pt', saturday: 'Sob', sunday: 'Nd' };
    const mealLabels = { breakfast: 'Śniadania', lunch: 'Obiady', dinner: 'Kolacje', snacks: 'Przekąski' };

    function getChecked(name) { return Array.from(stepForm.querySelectorAll(`[name="${name}"]:checked`)).map(el => el.value); }
    function getRadio(name)   { return stepForm.querySelector(`[name="${name}"]:checked`)?.value ?? ''; }
    function getInput(id)     { return stepForm.querySelector(`#${id}`)?.value ?? ''; }

    function getMondayStr() {
      const d   = new Date();
      const dow = d.getDay();
      d.setDate(d.getDate() - (dow === 0 ? 6 : dow - 1));
      return d.toISOString().slice(0, 10);
    }

    function updateSummary() {
      const goals        = getChecked('goals[]').map(v => goalLabels[v] ?? v);
      const diet         = getRadio('dietPreference');
      const household    = getInput('household-size');
      const budget       = getInput('weekly-budget');
      const planningDays = getChecked('planningDays[]');
      const mealTypes    = getChecked('mealTypes[]');

      const summaryGoals     = stepForm.querySelector('[data-summary-goals]');
      const summaryDiet      = stepForm.querySelector('[data-summary-diet]');
      const summaryHousehold = stepForm.querySelector('[data-summary-household]');
      const summarySchedule  = stepForm.querySelector('[data-summary-schedule]');

      if (summaryGoals)     summaryGoals.textContent     = goals.length ? goals.join(', ') : 'Brak';
      if (summaryDiet)      summaryDiet.textContent      = dietLabels[diet] ?? 'Bez preferencji';
      if (summaryHousehold) summaryHousehold.textContent = `${getPeopleLabel(household)}, ${budget} PLN`;
      if (summarySchedule)  summarySchedule.textContent  = `${planningDays.map(d => dayLabels[d] ?? d).join(', ')} · ${mealTypes.map(m => mealLabels[m] ?? m).join(', ')}`;
    }

    function renderStep(next) {
      currentStep = Math.min(Math.max(next, 0), maxAvailableStep, panels.length - 1);
      const pct   = Math.round(((currentStep + 1) / panels.length) * 100);

      panels.forEach((p, i) => {
        p.hidden = i !== currentStep;
        p.classList.toggle('is-active', i === currentStep);
      });
      navButtons.forEach((b, i) => {
        const ok = i <= maxAvailableStep;
        b.classList.toggle('is-active', i === currentStep);
        b.setAttribute('aria-current', i === currentStep ? 'step' : 'false');
        b.disabled = !ok;
        b.setAttribute('aria-disabled', ok ? 'false' : 'true');
      });

      if (stepLabel)     stepLabel.textContent     = `Krok ${currentStep + 1} z ${panels.length}`;
      if (progressLabel) progressLabel.textContent = `${pct}% ukończone`;
      if (progressBar)   progressBar.style.width   = `${pct}%`;
      if (prevBtn)       prevBtn.disabled           = currentStep === 0;
      if (nextBtn) {
        nextBtn.type        = 'button';
        nextBtn.textContent = currentStep === panels.length - 1 ? 'Zapisz plan' : 'Kontynuuj';
      }

      if (currentStep === panels.length - 1) updateSummary();
    }

    navButtons.forEach(b => b.addEventListener('click', () => renderStep(Number(b.dataset.stepNav))));
    prevBtn?.addEventListener('click', () => renderStep(currentStep - 1));
    nextBtn?.addEventListener('click', async () => {
      if (currentStep < panels.length - 1) {
        maxAvailableStep = Math.max(maxAvailableStep, currentStep + 1);
        renderStep(currentStep + 1);
        return;
      }
      await savePlan();
    });

    stepForm.addEventListener('submit', e => e.preventDefault());

    async function savePlan() {
      if (nextBtn) { nextBtn.disabled = true; nextBtn.textContent = 'Zapisuję…'; }
      if (plannerMessage) plannerMessage.hidden = true;

      try {
        const res = await fetch('/api/meal-plans', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            weekStartDate: getMondayStr(),
            planningDays:  getChecked('planningDays[]'),
            mealTypes:     getChecked('mealTypes[]'),
          }),
        });
        const data = await res.json();
        if (res.ok) {
          sessionStorage.setItem('flash', JSON.stringify({ type: 'success', message: `Plan „${data.name}" zapisany!` }));
          window.location.reload();
        } else {
          showError(data.error ?? 'Wystąpił błąd.');
        }
      } catch {
        showError('Błąd połączenia z serwerem.');
      } finally {
        if (nextBtn) { nextBtn.disabled = false; nextBtn.textContent = 'Zapisz plan'; }
      }
    }

    function showError(msg) {
      if (!plannerMessage) return;
      plannerMessage.textContent = msg;
      plannerMessage.hidden      = false;
    }

    renderStep(0);
  }

  function getPeopleLabel(value) {
    const n = Number(value);
    if (n === 1) return '1 osoba';
    if (n < 5)   return `${n} osoby`;
    return `${n} osób`;
  }

  initWizard();
})();
