# Mauri Tools

Sammlung kleiner Web-Tools für den Unterricht, erreichbar unter [https://mauri-tools.de](https://mauri-tools.de).

## Tools

| Tool | Pfad | Beschreibung |
|------|------|--------------|
| **Mauri Echo** | [`echo/`](echo/) | Anonymes Klassenfeedback per QR-Code oder 4-stelliger Pin, Kahoot-style. |
| Video Delay | [`video-delay/`](video-delay/) | Verzögerte Wiedergabe für Sport-Training und Bewegungsanalyse. |
| SportUnterricht Tools | [`sportunterricht-tools/`](sportunterricht-tools/) | Turniere, Stationen, Punktezähler. |
| Pyramidenvolumen | [`pyramide/volumen/`](pyramide/volumen/) | Interaktive Erklärung V = 1/3 · G · h. |

## Mauri Echo

- Lehrer stellt eine Frage und startet eine Session.
- Schüler treten per QR-Code, Link oder 4-stelliger Pin bei.
- Antworten landen anonym in einer Kartenwolke.
- Live-Anzeige lässt sich per Button ein-/ausschalten.
- Export als TXT möglich.

### Technik

- Frontend: HTML, CSS, Vanilla JS
- Backend: PHP + SQLite
- QR-Code: [qrcode.js](https://github.com/soldair/node-qrcode)
- Hosting: lokaler Apache/nginx + Cloudflare auf mauri-tools.de

## Deployment

```bash
cd /home/jordy/.openclaw/workspace/mauri-tools
# Änderungen
sudo cp -r echo/ /var/www/mauri-tools.de/
# Startseite-Kacheln
sudo cp tools.json /var/www/mauri-tools.de/tools.json
```

## Lokale Entwicklung

```bash
cd /home/jordy/.openclaw/workspace/mauri-tools
cd echo/
php -S localhost:8080
```

## Lizenz

MIT – siehe [LICENSE](LICENSE).
