# TODO - Skyrun Training

## KRITISCH

- [ ] **Passwörter rotieren** — Bei Goneo ändern: DB-Passwort, SMTP-Passwort (`skyrun@mein-computerfreund.de`), dann `./rotate_credentials.sh` ausführen.
- [ ] **`create_backup.php` auf Server löschen** — Alte Datei mit hardcoded Token. Wird nicht mehr genutzt, per .htaccess blockiert aber sollte weg.

## MITTEL

- [ ] **Race Condition bei Registrierung** — Zwei gleichzeitige Anmeldungen können Teilnehmerlimit überschreiten. Fix: Transaction mit `SELECT ... FOR UPDATE`.
- [ ] **PII in Error-Logs reduzieren** — Jeder API-Call loggt komplette POST-Daten inkl. Namen, E-Mails, Telefon. Sensible Felder maskieren.

## NIEDRIG

- [ ] **Import-Daten-Validierung** — `importData` validiert weder Datumsformat noch registrationTime aus JSON.
- [ ] **GitHub Pages deaktivieren** — `nixblick.github.io/skyrun/` ist aktiv (nur statisch), könnte verwirrend sein.
