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

  let history = [];
  let busy    = false;

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

  function escapeHtml(str) {
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

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

  function removeLoading() {
    widget.querySelector('[data-loading]')?.remove();
  }

  function renderHistory() {
    messages.innerHTML = '';
    if (history.length === 0) {
      messages.innerHTML = '<div class="ai-chat__welcome"><p>Cześć! Jestem kulinarnym asystentem AI. Zapytaj mnie o przepisy, planowanie posiłków lub wartości odżywcze.</p></div>';
      return;
    }
    history.forEach(msg => appendBubble(msg.role, msg.content));
  }

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

  function openPanel() {
    panel.hidden = false;
    toggleBtn.setAttribute('aria-expanded', 'true');
    renderHistory();
    input.focus();
  }

  function closePanel() {
    panel.hidden = true;
    toggleBtn.setAttribute('aria-expanded', 'false');
  }

  function clearChat() {
    history = [];
    saveHistory();
    renderHistory();
  }

  toggleBtn.addEventListener('click', () => {
    panel.hidden ? openPanel() : closePanel();
  });

  closeBtn.addEventListener('click', closePanel);
  clearBtn.addEventListener('click', clearChat);
  sendBtn.addEventListener('click', send);

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

  loadHistory();
})();
