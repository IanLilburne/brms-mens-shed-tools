(function () {
  'use strict';
  const root = document.getElementById('shed-kiosk');
  if (!root || !window.shedKioskConfig) return;
  const $ = id => document.getElementById(id);
  const input = $('shed-kiosk-search');
  const results = $('shed-kiosk-results');
  const feedback = $('shed-kiosk-feedback');
  const clock = $('shed-kiosk-clock');
  let cursor = null;
  let searching = 0;
  let searchTimer = null;
  let feedbackTimer = null;
  let busy = false;
  let lastShown = 0;
  let newsSignature = '';

  async function api(path, options) {
    const response = await fetch(shedKioskConfig.base + path, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { 'X-WP-Nonce': shedKioskConfig.nonce, 'Content-Type': 'application/json' },
      ...options
    });
    let body;
    try { body = await response.json(); } catch (e) { body = {}; }
    if (!response.ok) throw new Error(body.message || 'Request failed. Try again.');
    return body;
  }

  function show(message, type) {
    feedback.textContent = message;
    feedback.className = 'shed-kiosk-feedback ' + (type || '');
    clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => {
      feedback.textContent = 'Ready for your card or name';
      feedback.className = 'shed-kiosk-feedback';
    }, 5000);
  }

  function renderNews(items) {
    const key = JSON.stringify(items);
    if (key === newsSignature) return;
    newsSignature = key;
    const news = $('shed-kiosk-news');
    news.textContent = '';
    const span = document.createElement('span');
    span.className = 'shed-kiosk-news-text';
    span.textContent = items.length
      ? items.map(item => item.message).join('     ✦     ')
      : 'Welcome to Brundall Men\'s Shed';
    news.appendChild(span);
  }

  async function refresh() {
    try {
      const after = cursor === null ? '' : '?after=' + encodeURIComponent(cursor);
      const data = await api('status' + after);
      $('shed-kiosk-connection').hidden = true;
      $('shed-kiosk-present').textContent = data.present;
      $('shed-kiosk-visits').textContent = data.visits;
      renderNews(data.news || []);
      // First request establishes a baseline, so old events aren't presented as fresh swipes.
      if (cursor !== null) {
        (data.events || []).forEach(event => {
          if (event.source !== 'touchscreen' && event.id > lastShown) {
            show((event.direction === 'in' ? 'Welcome, ' : 'Goodbye, ') + event.name + '!', event.direction);
          }
          lastShown = Math.max(lastShown, event.id);
        });
      }
      cursor = Math.max(cursor || 0, data.latest_id || 0);
    } catch (error) {
      $('shed-kiosk-connection').hidden = false;
    }
  }

  function clearResults() { results.replaceChildren(); }

  async function search() {
    const query = input.value.trim();
    const current = ++searching;
    clearResults();
    if (query.length < 2) return;
    try {
      const data = await api('search?q=' + encodeURIComponent(query));
      if (current !== searching) return;
      if (!data.members.length) {
        const empty = document.createElement('p');
        empty.textContent = 'No matching members';
        results.appendChild(empty);
        return;
      }
      data.members.forEach(member => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'shed-kiosk-member';
        const name = document.createElement('span');
        name.textContent = member.name;
        const status = document.createElement('span');
        status.className = 'shed-kiosk-status';
        const action = member.in ? 'out' : 'in';
        status.textContent = member.in ? 'Sign out' : 'Sign in';
        button.append(name, status);
        button.addEventListener('click', () => check(member, action));
        results.appendChild(button);
      });
    } catch (error) { if (current === searching) show(error.message, 'error'); }
  }

  async function check(member, action) {
    if (busy) return;
    const verb = action === 'in' ? 'IN' : 'OUT';
    if (!window.confirm('Sign ' + member.name + ' ' + verb + '?')) return;
    busy = true;
    const token = (window.crypto && window.crypto.randomUUID)
      ? window.crypto.randomUUID()
      : 'touch-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    try {
      const data = await api('check', {
        method: 'POST',
        body: JSON.stringify({ member_id: member.id, action: action, token: token })
      });
      show((action === 'in' ? 'Welcome, ' : 'Goodbye, ') + data.name + '!', action);
      input.value = '';
      ++searching;
      clearResults();
      refresh();
    } catch (error) {
      show(error.message, 'error');
      search();
    } finally { busy = false; }
  }

  input.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(search, 180);
  });
  function tick() {
    clock.textContent = new Intl.DateTimeFormat('en-GB', {
      weekday: 'short', hour: '2-digit', minute: '2-digit'
    }).format(new Date());
  }
  tick();
  setInterval(tick, 10000);
  refresh();
  setInterval(refresh, 2000);
})();
