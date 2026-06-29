<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#0f172a">
  <title>Mauri Echo – Antworten</title>
  <link rel="stylesheet" href="style.css">
  <link rel="manifest" href="manifest.json">
</head>
<body>
  <div class="student-view">
    <div class="logo" style="margin:0 auto 18px;">💬</div>
    <h2>Mauri Echo</h2>
    <p id="questionDisplay" class="question-display">Lade Frage...</p>

    <div id="idFormCard" class="card" style="text-align:left;">
      <label for="pinInput">Beitrittscode (4 Ziffern)</label>
      <input type="text" id="pinInput" maxlength="4" inputmode="numeric" placeholder="1234" autocomplete="off">
      <div class="btn-row" style="justify-content:flex-end;">
        <button class="btn btn-primary" id="joinPinBtn">Beitreten</button>
      </div>
      <div style="text-align:center;color:var(--muted);margin-top:14px;font-size:.95rem;">
        Oder nutze direkt den Link/QR-Code.
      </div>
    </div>

    <div id="formCard" class="card hidden" style="text-align:left;">
      <label for="responseInput">Deine anonyme Antwort</label>
      <textarea id="responseInput" maxlength="500" placeholder="Schreib hier deine Antwort..."></textarea>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
        <span id="charCount" style="color:var(--muted);font-size:.9rem;">0 / 500</span>
        <button class="btn btn-primary" id="sendBtn">Absenden</button>
      </div>
    </div>

    <div id="successCard" class="card hidden" style="text-align:center;">
      <div style="font-size:3rem;margin-bottom:10px;">✅</div>
      <p style="font-size:1.2rem;color:var(--success);">Antwort gesendet!</p>
      <p style="color:var(--muted);">Du kannst dieses Fenster jetzt schließen.</p>
    </div>

    <div id="closedCard" class="card hidden" style="text-align:center;">
      <div style="font-size:3rem;margin-bottom:10px;">🔒</div>
      <p style="font-size:1.2rem;color:var(--danger);">Diese Session ist geschlossen.</p>
    </div>
  </div>

  <script>
    const params = new URLSearchParams(location.search);
    let sessionId = params.get('id');
    let currentPin = params.get('pin') || '';
    const API = 'api.php';

    const $ = id => document.getElementById(id);

    async function loadSession() {
      if (!sessionId && !currentPin) {
        $('questionDisplay').textContent = 'Gib den 4-stelligen Beitrittscode ein oder scanne den QR-Code.';
        return;
      }
      let url = `${API}?action=get`;
      if (sessionId) url += `&id=${sessionId}`;
      else url += `&pin=${currentPin}`;
      const res = await fetch(url);
      const data = await res.json();
      renderSession(data);
    }

    function renderSession(data) {
      if (data.error) {
        $('questionDisplay').textContent = data.error;
        $('idFormCard').classList.add('hidden');
        return;
      }
      if (!data.active) {
        $('questionDisplay').textContent = data.question;
        $('idFormCard').classList.add('hidden');
        $('formCard').classList.add('hidden');
        $('closedCard').classList.remove('hidden');
        return;
      }
      $('questionDisplay').textContent = data.question;
      $('idFormCard').classList.add('hidden');
      $('formCard').classList.remove('hidden');
      currentPin = data.pin;
    }

    $('pinInput').addEventListener('input', () => {
      $('pinInput').value = $('pinInput').value.replace(/\D/g, '').slice(0, 4);
      if ($('pinInput').value.length === 4) {
        joinByPin();
      }
    });

    $('joinPinBtn').addEventListener('click', joinByPin);

    async function joinByPin() {
      const pin = $('pinInput').value.trim();
      if (pin.length !== 4) return;
      currentPin = pin;
      const res = await fetch(`${API}?action=get&pin=${pin}`);
      const data = await res.json();
      renderSession(data);
    }

    $('responseInput').addEventListener('input', () => {
      $('charCount').textContent = `${$('responseInput').value.length} / 500`;
    });

    $('sendBtn').addEventListener('click', async () => {
      const text = $('responseInput').value.trim();
      if (!text) return;
      const payload = { text };
      if (sessionId) payload.id = sessionId;
      else payload.pin = currentPin;
      const res = await fetch(`${API}?action=submit`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.error) {
        alert(data.error);
        return;
      }
      $('formCard').classList.add('hidden');
      $('successCard').classList.remove('hidden');
    });

    loadSession();
  </script>
</body>
</html>
