(() => {
  const householdInput = document.querySelector("#household-size");
  const householdOutput = document.querySelector('output[for="household-size"]');
  const stepForm = document.querySelector("[data-step-form]");

  const goalLabels = {
    save_money: "Oszczędzanie",
    eat_healthier: "Zdrowe jedzenie",
    reduce_waste: "Ograniczenie marnowania",
    meal_prep: "Planowanie z wyprzedzeniem",
  };

  const dietLabels = {
    none: "Bez preferencji",
    vegetarian: "Wegetariańska",
    vegan: "Wegańska",
    gluten_free: "Bez glutenu",
    lactose_free: "Bez laktozy",
  };

  const dayLabels = {
    monday: "Pon",
    tuesday: "Wt",
    wednesday: "Śr",
    thursday: "Czw",
    friday: "Pt",
    saturday: "Sob",
    sunday: "Nd",
  };

  const mealLabels = {
    breakfast: "Śniadania",
    lunch: "Obiady",
    dinner: "Kolacje",
    snacks: "Przekąski",
  };

  function getPeopleLabel(value) {
    const count = Number(value);

    if (count === 1) {
      return "1 osoba";
    }

    if (count >= 5) {
      return `${count} osób`;
    }

    return `${count} osoby`;
  }

  if (householdInput && householdOutput) {
    householdInput.addEventListener("input", () => {
      householdOutput.textContent = getPeopleLabel(householdInput.value);
    });
  }

  if (!stepForm) {
    return;
  }

  const panels = Array.from(stepForm.querySelectorAll("[data-step-panel]"));
  const navButtons = Array.from(stepForm.querySelectorAll("[data-step-nav]"));
  const previousButton = stepForm.querySelector("[data-step-prev]");
  const nextButton = stepForm.querySelector("[data-step-next]");
  const stepLabel = stepForm.querySelector("[data-step-label]");
  const progressLabel = stepForm.querySelector("[data-progress-label]");
  const progressBar = stepForm.querySelector("[data-progress-bar]");
  const plannerMessage = stepForm.querySelector("[data-planner-message]");
  let currentStep = 0;
  let maxAvailableStep = 0;

  function getCheckedValues(name) {
    return Array.from(stepForm.querySelectorAll(`[name="${name}"]:checked`)).map((el) => el.value);
  }

  function getRadioValue(name) {
    return stepForm.querySelector(`[name="${name}"]:checked`)?.value ?? "";
  }

  function getInputValue(id) {
    return stepForm.querySelector(`#${id}`)?.value ?? "";
  }

  function getCurrentWeekMonday() {
    const today = new Date();
    const dayOfWeek = today.getDay();
    const daysToSubtract = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
    const monday = new Date(today);
    monday.setDate(today.getDate() - daysToSubtract);
    return monday.toISOString().slice(0, 10);
  }

  function updateSummary() {
    const goals = getCheckedValues("goals[]").map((v) => goalLabels[v] ?? v);
    const diet = getRadioValue("dietPreference");
    const household = getInputValue("household-size");
    const budget = getInputValue("weekly-budget");
    const planningDays = getCheckedValues("planningDays[]");
    const mealTypes = getCheckedValues("mealTypes[]");

    const summaryGoals = stepForm.querySelector("[data-summary-goals]");
    const summaryDiet = stepForm.querySelector("[data-summary-diet]");
    const summaryHousehold = stepForm.querySelector("[data-summary-household]");
    const summarySchedule = stepForm.querySelector("[data-summary-schedule]");

    if (summaryGoals) {
      summaryGoals.textContent = goals.length > 0 ? goals.join(", ") : "Brak";
    }

    if (summaryDiet) {
      summaryDiet.textContent = dietLabels[diet] ?? "Bez preferencji";
    }

    if (summaryHousehold) {
      summaryHousehold.textContent = `${getPeopleLabel(household)}, ${budget} PLN`;
    }

    if (summarySchedule) {
      const dayNames = planningDays.map((d) => dayLabels[d] ?? d).join(", ");
      const mealNames = mealTypes.map((m) => mealLabels[m] ?? m).join(", ");
      summarySchedule.textContent = `${dayNames} · ${mealNames}`;
    }
  }

  function renderStep(nextStep) {
    currentStep = Math.min(Math.max(nextStep, 0), maxAvailableStep, panels.length - 1);
    const progress = Math.round(((currentStep + 1) / panels.length) * 100);

    panels.forEach((panel, index) => {
      panel.hidden = index !== currentStep;
      panel.classList.toggle("is-active", index === currentStep);
    });

    navButtons.forEach((button, index) => {
      const isAvailable = index <= maxAvailableStep;
      button.classList.toggle("is-active", index === currentStep);
      button.setAttribute("aria-current", index === currentStep ? "step" : "false");
      button.disabled = !isAvailable;
      button.setAttribute("aria-disabled", isAvailable ? "false" : "true");
    });

    if (stepLabel) {
      stepLabel.textContent = `Krok ${currentStep + 1} z ${panels.length}`;
    }

    if (progressLabel) {
      progressLabel.textContent = `${progress}% ukończone`;
    }

    if (progressBar) {
      progressBar.style.width = `${progress}%`;
    }

    if (previousButton) {
      previousButton.disabled = currentStep === 0;
    }

    if (nextButton) {
      nextButton.type = "button";
      nextButton.textContent = currentStep === panels.length - 1 ? "Zapisz plan" : "Kontynuuj";
    }

    if (currentStep === panels.length - 1) {
      updateSummary();
    }
  }

  navButtons.forEach((button) => {
    button.addEventListener("click", () => {
      renderStep(Number(button.dataset.stepNav));
    });
  });

  if (previousButton) {
    previousButton.addEventListener("click", () => {
      renderStep(currentStep - 1);
    });
  }

  if (nextButton) {
    nextButton.addEventListener("click", async () => {
      if (currentStep < panels.length - 1) {
        const nextStep = currentStep + 1;
        maxAvailableStep = Math.max(maxAvailableStep, nextStep);
        renderStep(nextStep);
        return;
      }

      await savePlan();
    });
  }

  stepForm.addEventListener("submit", (event) => {
    event.preventDefault();
  });

  async function savePlan() {
    if (nextButton) {
      nextButton.disabled = true;
      nextButton.textContent = "Zapisuję…";
    }

    if (plannerMessage) {
      plannerMessage.hidden = true;
    }

    const payload = {
      weekStartDate: getCurrentWeekMonday(),
      planningDays: getCheckedValues("planningDays[]"),
      mealTypes: getCheckedValues("mealTypes[]"),
    };

    try {
      const res = await fetch("/api/meal-plans", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (res.ok) {
        showSuccess(data.name ?? "Plan zapisany");
      } else {
        showError(data.error ?? "Wystąpił błąd.");
      }
    } catch {
      showError("Błąd połączenia z serwerem.");
    } finally {
      if (nextButton) {
        nextButton.disabled = false;
        nextButton.textContent = "Zapisz plan";
      }
    }
  }

  function showSuccess(planName) {
    stepForm.innerHTML = `
      <div class="planner-card is-active" style="text-align:center; padding: 3rem 2rem;">
        <h2>Plan gotowy!</h2>
        <p>${planName} został zapisany.</p>
        <a class="button" href="/recipes" style="margin-top:1.5rem; display:inline-block;">Przeglądaj przepisy</a>
      </div>
    `;
  }

  function showError(message) {
    if (!plannerMessage) {
      return;
    }

    plannerMessage.textContent = message;
    plannerMessage.hidden = false;
  }

  renderStep(0);
})();
