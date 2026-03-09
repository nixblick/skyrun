# TODO - Skyrun Training

## KRITISCH - Sicherheit

- [ ] **Passwörter rotieren** — Alte Passwörter aus Git-Historie sind kompromittiert. Bei Goneo ändern: DB-Passwort, SMTP-Passwort (`skyrun@mein-computerfreund.de`), dann in `config.php` auf dem Server aktualisieren. Backup-Token in GitHub Secret (`BACKUP_TOKEN`) und `config.php` erneuern.
- [ ] **CSRF-Schutz für Admin-Aktionen** — Keine CSRF-Tokens vorhanden. Jede Website kann Admin-Aktionen im Browser eines eingeloggten Admins auslösen

## VERBESSERUNGEN

- [ ] **`config.php` durch `.env`-basierte Konfiguration ersetzen** — Saubere Trennung von Code und Konfiguration
- [ ] **Rate Limiting für Registrierung** — Aktuell nur CAPTCHA, kein IP-basiertes Rate Limiting
- [ ] **Admin-Session Timeout** — Kein automatischer Logout nach Inaktivität
