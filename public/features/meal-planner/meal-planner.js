(() => {
  const householdInput = document.querySelector("#household-size");
  const householdOutput = document.querySelector('output[for="household-size"]');
  const stepForm = document.querySelector("[data-step-form]");

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
  let currentStep = 0;
  let maxAvailableStep = 0;

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
    nextButton.addEventListener("click", () => {
      if (currentStep < panels.length - 1) {
        const nextStep = currentStep + 1;
        maxAvailableStep = Math.max(maxAvailableStep, nextStep);
        renderStep(nextStep);
      }
    });
  }

  stepForm.addEventListener("submit", (event) => {
    event.preventDefault();
    maxAvailableStep = panels.length - 1;
    renderStep(panels.length - 1);
  });

  renderStep(0);
})();
