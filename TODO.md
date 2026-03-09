# TODO - Skyrun Training

## KRITISCH - Sicherheit

- [ ] **Credentials aus Git-Historie entfernen** — `config.php` enthält DB-Passwort und SMTP-Passwort im Klartext und wurde committed. Passwörter rotieren (DB + SMTP), dann `config.php` aus Historie entfernen (`git filter-branch` oder `bfg`)
- [ ] **CSRF-Schutz für Admin-Aktionen** — Keine CSRF-Tokens vorhanden. Jede Website kann Admin-Aktionen im Browser eines eingeloggten Admins auslösen

## VERBESSERUNGEN

- [ ] **`config.php` durch `.env`-basierte Konfiguration ersetzen** — Saubere Trennung von Code und Konfiguration
- [ ] **Rate Limiting für Registrierung** — Aktuell nur CAPTCHA, kein IP-basiertes Rate Limiting
- [ ] **Admin-Session Timeout** — Kein automatischer Logout nach Inaktivität
