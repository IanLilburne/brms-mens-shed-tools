(function () {
  'use strict';
  const root = document.getElementById('shed-kiosk');
  if (!root || !window.shedKioskConfig) return;
  const $ = id => document.getElementById(id);
  const input = $('shed-kiosk-search');
  const results = $('shed-kiosk-results');
  // Explicit grid styling protects the two-column layout against theme rules.
  results.style.setProperty('display', 'grid', 'important');
  results.style.setProperty('grid-template-columns', 'repeat(2,minmax(0,1fr))', 'important');
  const searchArea = $('shed-kiosk-search-area');
  const feedback = $('shed-kiosk-feedback');
  const clock = $('shed-kiosk-clock');
  const activation = $('shed-kiosk-activate');
  const attendance = $('shed-kiosk-attendance');
  const activateButton = $('shed-kiosk-activate-button');
  let cursor = null;
  let searching = 0;
  let searchTimer = null;
  let feedbackTimer = null;
  let busy = false;
  let lastShown = 0;
  let newsSignature = '';
  let urgentSignature = '';

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

  function renderNews(items, id, urgent) {
    const key = JSON.stringify(items);
    if (urgent) {
      if (key === urgentSignature) return;
      urgentSignature = key;
    } else {
      if (key === newsSignature) return;
      newsSignature = key;
    }
    const news = $(id);
    news.hidden = urgent && !items.length;
    if (news.hidden) return;
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
      const mode = data.device_mode || 'unregistered';
      activation.hidden = mode !== 'unregistered';
      attendance.hidden = mode === 'unregistered';
      input.disabled = mode === 'unregistered';
      if (mode === 'test' && cursor === null) show('Development mode: attendance is simulated', '');
      $('shed-kiosk-present').textContent = data.present === null ? '–' : data.present;
      $('shed-kiosk-visits').textContent = data.visits === null ? '–' : data.visits;
      renderNews(data.news || [], 'shed-kiosk-news', false);
      renderNews(data.urgent_news || [], 'shed-kiosk-news-urgent', true);
      // First request establishes a baseline, so old events aren't presented as fresh swipes.
      if (cursor !== null) {
        (data.events || []).forEach(event => {
          if (event.source !== 'touchscreen' && event.id > lastShown) {
            show((event.direction === 'in' ? (event.returning ? 'Good to see you again, ' : 'Welcome, ') : 'Goodbye, ') + event.name + '!', event.direction);
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
  function positionSearch() {
    if (document.activeElement !== input) return;
    document.body.classList.add('shed-searching');
    searchArea.classList.add('keyboard-active');
    // visualViewport is the unobscured area ABOVE the native Android keyboard.
    const vv = window.visualViewport;
    const height = vv ? vv.height : window.innerHeight;
    const top = vv ? vv.offsetTop : 0;
    const left = vv ? vv.offsetLeft : 0;
    const width = vv ? vv.width : window.innerWidth;
    searchArea.style.setProperty('--shed-view-top', Math.round(top) + 'px');
    searchArea.style.setProperty('--shed-view-left', Math.round(left) + 'px');
    searchArea.style.setProperty('--shed-view-width', Math.round(width) + 'px');
    searchArea.style.setProperty('--shed-view-height', Math.round(height) + 'px');
  }
  function exitSearch() {
    searchArea.classList.remove('keyboard-active');
    document.body.classList.remove('shed-searching');
  }
  input.addEventListener('focus', () => {
    positionSearch();
    setTimeout(positionSearch, 250);
    setTimeout(positionSearch, 600);
  });
  input.addEventListener('blur', () => {
    // Delay allows the member button click to complete.
    setTimeout(() => { if (document.activeElement !== input) exitSearch(); }, 200);
  });
  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', positionSearch);
    window.visualViewport.addEventListener('scroll', positionSearch);
  }

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
      positionSearch();
    } catch (error) { if (current === searching) show(error.message, 'error'); }
  }

  async function check(member, action) {
    if (busy) return;
    busy = true;
    const token = (window.crypto && window.crypto.randomUUID)
      ? window.crypto.randomUUID()
      : 'touch-' + Date.now() + '-' + Math.random().toString(36).slice(2);
    try {
      const data = await api('check', {
        method: 'POST',
        body: JSON.stringify({ member_id: member.id, action: action, token: token })
      });
      show((action === 'in' ? (data.returning ? 'Good to see you again, ' : 'Welcome, ') : 'Goodbye, ') + data.name + '!', action);
      input.value = '';
      input.blur();
      exitSearch();
      ++searching;
      clearResults();
      refresh();
    } catch (error) {
      show(error.message, 'error');
      search();
    } finally { busy = false; }
  }

  activateButton.addEventListener('click', async () => {
    activateButton.disabled = true;
    try {
      await api('activate', {method:'POST', body:JSON.stringify({code:$('shed-kiosk-code').value.trim()})});
      $('shed-kiosk-activate-message').textContent = 'Registered. Reloading…';
      location.reload();
    } catch(e) { $('shed-kiosk-activate-message').textContent = e.message; }
    finally { activateButton.disabled = false; }
  });
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
