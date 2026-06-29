<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#0f172a">
  <title>Mauri Echo – Anonymes Klassenfeedback</title>
  <link rel="stylesheet" href="style.css">
  <link rel="manifest" href="manifest.json">
  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
</head>
<body>
  <div class="app">
    <header>
      <div class="logo">📢</div>
      <div>
        <h1>Mauri Echo</h1>
        <div class="subtitle">Anonymes Klassenfeedback für den Unterricht</div>
      </div>
    </header>

    <section id="createSection" class="card">
      <label>Stelle deine Frage</label>
      <textarea id="questionInput" placeholder="Zum Beispiel: Was fandest du am heutigen Thema am schwierigsten?"></textarea>
      <div class="btn-row">
        <button class="btn btn-primary" id="createBtn">🚀 Session starten</button>
      </div>
    </section>

    <section id="sessionSection" class="card hidden">
      <label>Frage</label>
      <div id="questionDisplay" class="question-display" style="text-align:left;margin-bottom:18px;"></div>

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
          <span>💬</span>
          <span id="responseCount">0 Antworten</span>
        </div>
      </div>

      <div class="btn-row">
        <button class="btn btn-ghost" id="toggleLiveBtn">⏸ Live aus</button>
        <button class="btn btn-success" id="exportBtn">📥 Exportieren</button>
        <button class="btn btn-danger" id="closeBtn">✕ Session schließen</button>
      </div>

      <div id="responsesArea">
        <div class="empty-state">
          <span class="icon">🌊</span>
          Noch keine Echos eingetroffen.
        </div>
      </div>
    </section>
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
      const res = await fetch(`${API}?action=${action}${sessionId && action !== 'create' ? '&id=' + sessionId : ''}`, opts);
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
      QRCode.toCanvas($('qrCanvas'), url, { width: 260, margin: 2, color: { dark: '#0f172a', light: '#ffffff' } });
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
            <span class="icon">🔒</span>
            Live-Anzeige ist aus. Schüler können weiterhin antworten – du siehst sie erst wieder, wenn du Live einschaltest.
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
          <span class="icon">✅</span>
          Session geschlossen. Du kannst die Seite neu laden, um eine neue Session zu starten.
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
        btn.textContent = '⏸ Live aus';
        btn.className = 'btn btn-ghost';
      } else {
        badge.classList.add('off');
        $('statusText').textContent = 'Live-Anzeige aus';
        btn.textContent = '▶ Live an';
        btn.className = 'btn btn-primary';
      }
    }

    function renderResponses(responses) {
      const area = $('responsesArea');
      $('responseCount').textContent = `${responses.length} Antwort${responses.length === 1 ? '' : 'en'}`;
      if (responses.length === 0) {
        area.innerHTML = `
          <div class="empty-state">
            <span class="icon">🌊</span>
            Noch keine Echos eingetroffen.
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
