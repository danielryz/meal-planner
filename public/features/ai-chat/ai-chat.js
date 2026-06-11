(() => {
  const widget    = document.querySelector('[data-ai-chat]');
  if (!widget) return;

  const toggleBtn  = widget.querySelector('[data-ai-toggle]');
  const panel      = widget.querySelector('[data-ai-panel]');
  const messages   = widget.querySelector('[data-ai-messages]');
  const input      = widget.querySelector('[data-ai-input]');
  const sendBtn    = widget.querySelector('[data-ai-send]');
  const clearBtn   = widget.querySelector('[data-ai-clear]');
  const closeBtn   = widget.querySelector('[data-ai-close]');

  const STORAGE_KEY = 'ai_chat_history';
  const MOBILE_BP   = 640;

  let history  = [];
  let busy     = false;
  let backdrop = null;

  /* ── History ─────────────────────────────────────────── */

  function loadHistory() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      history = raw ? JSON.parse(raw) : [];
    } catch {
      history = [];
    }
  }

  function saveHistory() {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history));
    } catch {}
  }

  /* ── Rendering ───────────────────────────────────────── */

  function appendBubble(role, text) {
    const div = document.createElement('div');
    div.className = `ai-chat__bubble ai-chat__bubble--${role}`;
    div.textContent = text;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
    return div;
  }

  function showLoading() {
    const div = document.createElement('div');
    div.className = 'ai-chat__bubble ai-chat__bubble--loading';
    div.dataset.loading = '';
    div.textContent = 'Myślę…';
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
    return div;
  }

  function welcomeMarkup() {
    return `
      <div class="ai-chat__welcome">
        <p>Cześć! Jestem kulinarnym asystentem AI. Mogę pomóc w tych rzeczach:</p>
        <div class="ai-chat__quick-actions" aria-label="Szybkie akcje asystenta AI">
          <button type="button" data-ai-prompt="Zaproponuj mi prosty posiłek na dziś">
            <strong>Pomysł na posiłek</strong>
            <span>Doradzę, co ugotować dziś.</span>
          </button>
          <button type="button" data-ai-prompt="Znajdź szybki przepis do 30 minut">
            <strong>Szukaj przepisu</strong>
            <span>Sprawdzę przepisy z bazy.</span>
          </button>
          <button type="button" data-ai-prompt="Pokaż moją listę zakupów">
            <strong>Lista zakupów</strong>
            <span>Pokażę aktualne produkty.</span>
          </button>
          <button type="button" data-ai-prompt="Dodaj 500 g pomidorów do listy zakupów">
            <strong>Dodaj produkt</strong>
            <span>Dopiszę rzecz do listy.</span>
          </button>
        </div>
      </div>
    `;
  }

  function renderHistory() {
    messages.innerHTML = '';
    if (history.length === 0) {
      messages.innerHTML = welcomeMarkup();
      return;
    }
    history.forEach(msg => appendBubble(msg.role, msg.content));
  }

  /* ── Backdrop (mobile only) ──────────────────────────── */

  function showBackdrop() {
    if (window.innerWidth > MOBILE_BP) return;
    backdrop = document.createElement('div');
    backdrop.className = 'ai-chat-backdrop';
    backdrop.addEventListener('click', closePanel);
    document.body.appendChild(backdrop);
  }

  function hideBackdrop() {
    backdrop?.remove();
    backdrop = null;
  }

  /* ── Panel open / close ──────────────────────────────── */

  function openPanel() {
    panel.hidden = false;
    toggleBtn.setAttribute('aria-expanded', 'true');
    showBackdrop();
    renderHistory();
    input.focus();
    fetch('/api/ai/warmup', { method: 'POST' }).catch(() => {});
  }

  function closePanel() {
    panel.hidden = true;
    toggleBtn.setAttribute('aria-expanded', 'false');
    hideBackdrop();
  }

  /* ── Send ─────────────────────────────────────────────── */

  async function send() {
    const text = input.value.trim();
    if (!text || busy) return;

    history.push({ role: 'user', content: text });
    appendBubble('user', text);
    input.value = '';
    input.style.height = 'auto';

    busy = true;
    input.disabled = true;
    sendBtn.disabled = true;
    const loader = showLoading();

    try {
      const res = await fetch('/api/ai/chat', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ messages: history }),
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok) {
        throw new Error(data.error || 'Błąd serwera');
      }

      const reply = data.reply ?? '';
      history.push({ role: 'assistant', content: reply });
      saveHistory();
      loader.remove();
      appendBubble('assistant', reply);
    } catch (err) {
      loader.remove();
      window.toast?.error(err.message || 'Nie udało się wysłać wiadomości.');
      history.pop();
    } finally {
      busy = false;
      input.disabled = false;
      sendBtn.disabled = false;
      input.focus();
    }
  }

  /* ── Clear ────────────────────────────────────────────── */

  function clearChat() {
    history = [];
    saveHistory();
    renderHistory();
  }

  /* ── Events ───────────────────────────────────────────── */

  toggleBtn.addEventListener('click', () => {
    panel.hidden ? openPanel() : closePanel();
  });

  closeBtn.addEventListener('click', closePanel);
  clearBtn.addEventListener('click', clearChat);
  sendBtn.addEventListener('click', send);

  messages.addEventListener('click', (event) => {
    const promptBtn = event.target.closest('[data-ai-prompt]');
    if (!promptBtn || busy) return;

    input.value = promptBtn.dataset.aiPrompt ?? '';
    send();
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send();
    }
  });

  input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  });

  /* Close on Escape */
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !panel.hidden) closePanel();
  });

  /* Resize: remove backdrop if switching to desktop */
  window.addEventListener('resize', () => {
    if (window.innerWidth > MOBILE_BP && backdrop) hideBackdrop();
  });

  loadHistory();
})();
