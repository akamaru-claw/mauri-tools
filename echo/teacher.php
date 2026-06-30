<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#1e1a4b">
  <title>Mauri Echo – Lehrer-Ansicht</title>
  <link rel="stylesheet" href="style.css?v=3">
  <link rel="manifest" href="manifest.json?v=3">
  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
</head>
<body>
  <div class="soundwave"></div>
  <div class="app">
    <header>
      <div class="logo" aria-hidden="true">
        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
          <circle cx="50" cy="50" r="46" fill="#fbbf24"/>
          <path d="M28 50c0-12 9-22 22-22s22 10 22 22-9 22-22 22" fill="none" stroke="#1e1a4b" stroke-width="8" stroke-linecap="round"/>
          <path d="M68 36c8 8 8 28 0 36" fill="none" stroke="#1e1a4b" stroke-width="7" stroke-linecap="round"/>
          <path d="M78 28c12 12 12 42 0 54" fill="none" stroke="#1e1a4b" stroke-width="6" stroke-linecap="round"/>
        </svg>
      </div>
      <div>
        <h1>Mauri Echo</h1>
        <div class="subtitle">Lehrer-Ansicht</div>
      </div>
      <a href="index.php" class="btn btn-ghost back-link">← Start</a>
    </header>

    <section id="createSection" class="card">
      <label>Stelle deine Frage</label>
      <textarea id="questionInput" placeholder="Zum Beispiel: Was fandest du am heutigen Thema am schwierigsten?"></textarea>
      <div class="btn-row">
        <button class="btn btn-primary" id="createBtn">Session starten</button>
      </div>
    </section>

    <section id="sessionSection" class="card hidden">
      <label>Frage</label>
      <div id="questionDisplay" class="question-display" style="text-align:left;margin-bottom:20px;"></div>

      <div class="join-info">
        <div class="pin-box">
          <div class="label">Beitrittscode</div>
          <div id="pinDisplay" class="pin-big">----</div>
        </div>
        <div class="qr-box">
          <canvas id="qrCanvas"></canvas>
          <div class="url-box">
            <input type="text" id="joinUrl" readonly>
            <button class="btn btn-ghost" id="copyBtn">Kopieren</button>
          </div>
        </div>
      </div>

      <div class="toolbar">
        <div id="statusBadge" class="status-badge">
          <span class="status-dot"></span>
          <span id="statusText">Live-Anzeige an</span>
        </div>
        <div class="count-pill">
          <span id="responseCount">0 Antworten</span>
        </div>
      </div>

      <div class="btn-row">
        <button class="btn btn-ghost" id="toggleLiveBtn">Live aus</button>
        <button class="btn btn-success" id="exportBtn">Exportieren</button>
        <button class="btn btn-danger" id="closeBtn">Session schließen</button>
      </div>

      <div id="responsesArea">
        <div class="empty-state">
          <svg class="icon" viewBox="0 0 120 120" aria-hidden="true">
            <circle cx="60" cy="60" r="52" fill="#2d2668" stroke="#fbbf24" stroke-width="3"/>
            <path d="M36 60c0-14 10-24 24-24s24 10 24 24-10 24-24 24" fill="none" stroke="#fbbf24" stroke-width="6" stroke-linecap="round"/>
            <path d="M76 44c8 8 8 28 0 36" fill="none" stroke="#fbbf24" stroke-width="5" stroke-linecap="round"/>
            <path d="M88 34c12 12 12 42 0 54" fill="none" stroke="#fbbf24" stroke-width="4" stroke-linecap="round"/>
          </svg>
          <div class="title">Noch keine Echos eingetroffen</div>
          <p>Teile den Pin oder QR-Code. Sobald die ersten Antworten eingehen, erscheinen sie hier.</p>
        </div>
      </div>
    </section>

    <div class="footer-note">
      Offline: Session-Daten bleiben lokal auf diesem Gerät. Keine Cloud.
    </div>
  </div>

  <script>
    const API = 'api.php';
    let sessionId = null;
    let pollTimer = null;
    let live = true;

    const $ = id => document.getElementById(id);

    async function api(action, method = 'GET', body = null) {
      const opts = { method };
      if (body) {
        opts.headers = { 'Content-Type': 'application/json' };
        opts.body = JSON.stringify(body);
      }
      let url = `${API}?action=${action}`;
      if (sessionId && action !== 'create') url += `&id=${sessionId}`;
      const res = await fetch(url, opts);
      return res.json();
    }

    $('createBtn').addEventListener('click', async () => {
      const q = $('questionInput').value.trim();
      if (!q) return;
      const data = await api('create', 'POST', { question: q });
      if (data.error) { alert(data.error); return; }
      sessionId = data.id;
      $('createSection').classList.add('hidden');
      $('sessionSection').classList.remove('hidden');
      $('questionDisplay').textContent = q;
      $('pinDisplay').textContent = data.pin;
      const url = `${location.origin}${location.pathname.replace('index.php','')}join.php?id=${sessionId}`;
      $('joinUrl').value = url;
      QRCode.toCanvas($('qrCanvas'), url, { width: 240, margin: 2, color: { dark: '#1e1a4b', light: '#ffffff' } });
      startPolling();
    });

    $('copyBtn').addEventListener('click', () => {
      navigator.clipboard.writeText($('joinUrl').value);
      $('copyBtn').textContent = 'Kopiert!';
      setTimeout(() => $('copyBtn').textContent = 'Kopieren', 1500);
    });

    $('toggleLiveBtn').addEventListener('click', async () => {
      live = !live;
      await api('toggle', 'POST', { live: live ? 1 : 0 });
      updateLiveUI();
      if (live) {
        fetchSession();
      } else {
        $('responsesArea').innerHTML = `
          <div class="empty-state">
            <svg class="icon" viewBox="0 0 120 120" aria-hidden="true">
              <rect x="30" y="20" width="60" height="80" rx="10" fill="#2d2668" stroke="#fbbf24" stroke-width="3"/>
              <circle cx="60" cy="55" r="10" fill="#fbbf24"/>
              <path d="M60 65v18" stroke="#fbbf24" stroke-width="5" stroke-linecap="round"/>
            </svg>
            <div class="title">Live-Anzeige ist aus</div>
            <p>Schüler können weiterhin anonym antworten. Du siehst die Echos erst wieder, sobald du Live einschaltest.</p>
          </div>
        `;
      }
    });

    $('closeBtn').addEventListener('click', async () => {
      if (!confirm('Session wirklich schließen? Neue Antworten sind dann nicht mehr möglich.')) return;
      await api('close', 'POST');
      stopPolling();
      $('responsesArea').innerHTML = `
        <div class="empty-state">
          <div class="success-check">
            <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 6L9 17l-5-5"/>
            </svg>
          </div>
          <div class="title">Session geschlossen</div>
          <p>Du kannst die Seite neu laden, um eine neue Session zu starten.</p>
        </div>
      `;
      $('toggleLiveBtn').classList.add('hidden');
      $('closeBtn').classList.add('hidden');
    });

    $('exportBtn').addEventListener('click', () => {
      window.open(`${API}?action=export&id=${sessionId}`);
    });

    function updateLiveUI() {
      const badge = $('statusBadge');
      const btn = $('toggleLiveBtn');
      if (live) {
        badge.classList.remove('off');
        $('statusText').textContent = 'Live-Anzeige an';
        btn.textContent = 'Live aus';
        btn.className = 'btn btn-ghost';
      } else {
        badge.classList.add('off');
        $('statusText').textContent = 'Live-Anzeige aus';
        btn.textContent = 'Live an';
        btn.className = 'btn btn-primary';
      }
    }

    function renderResponses(responses) {
      const area = $('responsesArea');
      $('responseCount').textContent = `${responses.length} Antwort${responses.length === 1 ? '' : 'en'}`;
      if (responses.length === 0) {
        area.innerHTML = `
          <div class="empty-state">
            <svg class="icon" viewBox="0 0 120 120" aria-hidden="true">
              <circle cx="60" cy="60" r="52" fill="#2d2668" stroke="#fbbf24" stroke-width="3"/>
              <path d="M36 60c0-14 10-24 24-24s24 10 24 24-10 24-24 24" fill="none" stroke="#fbbf24" stroke-width="6" stroke-linecap="round"/>
              <path d="M76 44c8 8 8 28 0 36" fill="none" stroke="#fbbf24" stroke-width="5" stroke-linecap="round"/>
              <path d="M88 34c12 12 12 42 0 54" fill="none" stroke="#fbbf24" stroke-width="4" stroke-linecap="round"/>
            </svg>
            <div class="title">Noch keine Echos eingetroffen</div>
            <p>Teile den Pin oder QR-Code. Sobald die ersten Antworten eingehen, erscheinen sie hier.</p>
          </div>
        `;
        return;
      }
      area.innerHTML = '<div class="responses-grid">' + responses.map(r =>
        `<div class="response-card">${escapeHtml(r.text)}</div>`
      ).join('') + '</div>';
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    async function fetchSession() {
      if (!sessionId || !live) return;
      const data = await api('get');
      if (data.error) return;
      live = data.live;
      updateLiveUI();
      renderResponses(data.responses || []);
    }

    function startPolling() {
      stopPolling();
      pollTimer = setInterval(fetchSession, 3000);
    }

    function stopPolling() {
      if (pollTimer) clearInterval(pollTimer);
    }
  </script>
</body>
</html>
