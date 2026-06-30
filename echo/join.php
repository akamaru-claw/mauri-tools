<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#1e1a4b">
  <title>Mauri Echo – Antworten</title>
  <link rel="stylesheet" href="style.css?v=2">
  <link rel="manifest" href="manifest.json?v=2">
</head>
<body>
  <div class="soundwave"></div>
  <div class="student-view">
    <div class="logo" aria-hidden="true">
      <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <circle cx="50" cy="50" r="46" fill="#fbbf24"/>
        <path d="M28 50c0-12 9-22 22-22s22 10 22 22-9 22-22 22" fill="none" stroke="#1e1a4b" stroke-width="8" stroke-linecap="round"/>
        <path d="M68 36c8 8 8 28 0 36" fill="none" stroke="#1e1a4b" stroke-width="7" stroke-linecap="round"/>
        <path d="M78 28c12 12 12 42 0 54" fill="none" stroke="#1e1a4b" stroke-width="6" stroke-linecap="round"/>
      </svg>
    </div>
    <h2>Mauri Echo</h2>
    <p id="questionDisplay" class="question-display">Lade Frage...</p>

    <div id="idFormCard" class="card" style="text-align:left;">
      <label>Beitrittscode (4 Ziffern)</label>
      <div class="pin-input-wrap" id="pinWrap">
        <input type="text" inputmode="numeric" maxlength="1" class="pin-digit" data-index="0" aria-label="Ziffer 1">
        <input type="text" inputmode="numeric" maxlength="1" class="pin-digit" data-index="1" aria-label="Ziffer 2">
        <input type="text" inputmode="numeric" maxlength="1" class="pin-digit" data-index="2" aria-label="Ziffer 3">
        <input type="text" inputmode="numeric" maxlength="1" class="pin-digit" data-index="3" aria-label="Ziffer 4">
      </div>
      <input type="hidden" id="pinInput">
      <div class="btn-row" style="justify-content:flex-end;">
        <button class="btn btn-primary" id="joinPinBtn">Beitreten</button>
      </div>
      <p class="pin-hint">Oder nutze direkt den Link/QR-Code von deinem Lehrer.</p>
    </div>

    <div id="formCard" class="card hidden" style="text-align:left;">
      <label for="responseInput">Deine anonyme Antwort</label>
      <textarea id="responseInput" maxlength="500" placeholder="Schreib hier deine Antwort..."></textarea>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;">
        <span id="charCount" style="color:#b8b4e6;font-size:.95rem;">0 / 500</span>
        <button class="btn btn-primary" id="sendBtn">Absenden</button>
      </div>
    </div>

    <div id="successCard" class="card hidden" style="text-align:center;">
      <div class="success-check">
        <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 6L9 17l-5-5"/>
        </svg>
      </div>
      <p style="font-size:1.3rem;color:#86efac;font-weight:800;">Antwort gesendet!</p>
      <p style="color:#b8b4e6;">Du kannst dieses Fenster jetzt schließen.</p>
    </div>

    <div id="closedCard" class="card hidden" style="text-align:center;">
      <svg class="closed-lock" viewBox="0 0 120 120" aria-hidden="true">
        <rect x="30" y="20" width="60" height="80" rx="10" fill="#2d2668" stroke="#e11d48" stroke-width="3"/>
        <circle cx="60" cy="55" r="10" fill="#e11d48"/>
        <path d="M60 65v18" stroke="#e11d48" stroke-width="5" stroke-linecap="round"/>
      </svg>
      <p style="font-size:1.3rem;color:#fda4af;font-weight:800;">Diese Session ist geschlossen.</p>
    </div>

    <p class="helper-text">Deine Antwort ist vollkommen anonym.</p>
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

    // Split pin into digit boxes
    const pinDigits = document.querySelectorAll('.pin-digit');
    const pinHidden = $('pinInput');

    function updateHiddenPin() {
      const pin = Array.from(pinDigits).map(d => d.value).join('');
      pinHidden.value = pin;
      return pin;
    }

    pinDigits.forEach((input, index) => {
      input.addEventListener('input', (e) => {
        input.value = input.value.replace(/\D/g, '').slice(0, 1);
        updateHiddenPin();
        if (input.value && index < pinDigits.length - 1) {
          pinDigits[index + 1].focus();
        }
        if (updateHiddenPin().length === 4) {
          joinByPin();
        }
      });

      input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !input.value && index > 0) {
          pinDigits[index - 1].focus();
        }
        if (e.key === 'ArrowLeft' && index > 0) {
          pinDigits[index - 1].focus();
        }
        if (e.key === 'ArrowRight' && index < pinDigits.length - 1) {
          pinDigits[index + 1].focus();
        }
      });

      input.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 4);
        pasted.split('').forEach((char, i) => {
          if (pinDigits[i]) pinDigits[i].value = char;
        });
        updateHiddenPin();
        const next = pinDigits[Math.min(pasted.length, pinDigits.length - 1)];
        if (next) next.focus();
        if (updateHiddenPin().length === 4) joinByPin();
      });
    });

    $('joinPinBtn').addEventListener('click', joinByPin);

    async function joinByPin() {
      const pin = pinHidden.value.trim();
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
