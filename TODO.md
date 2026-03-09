# TODO - Skyrun Training

## KRITISCH - Sicherheit

- [ ] **Passwörter rotieren** — Alte Passwörter aus Git-Historie sind kompromittiert. Bei Goneo ändern: DB-Passwort, SMTP-Passwort (`skyrun@mein-computerfreund.de`), dann `./rotate_credentials.sh` ausführen.

## VERBESSERUNGEN

- [ ] **`config.php` durch `.env`-basierte Konfiguration ersetzen** — Saubere Trennung von Code und Konfiguration
- [ ] **Rate Limiting für Registrierung** — Aktuell nur CAPTCHA, kein IP-basiertes Rate Limiting
- [ ] **Admin-Session Timeout** — Kein automatischer Logout nach Inaktivität
