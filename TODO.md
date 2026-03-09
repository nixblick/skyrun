# TODO - Skyrun Training

## KRITISCH - Sicherheit

- [ ] **Passwörter rotieren** — Alte Passwörter aus Git-Historie sind kompromittiert. Bei Goneo ändern: DB-Passwort, SMTP-Passwort (`skyrun@mein-computerfreund.de`), dann `./rotate_credentials.sh` ausführen.

## HOCH

- [ ] **Rate Limiting für Admin-Login** — Kein Brute-Force-Schutz. Mindestens IP+Zeit-basierte Verzögerung einbauen.
- [ ] **StrictHostKeyChecking=no in GitHub Workflows** — SFTP-Verbindungen akzeptieren jeden Hostkey (MITM-Risiko). Korrekten Hostkey als Known-Host hinterlegen.

## MITTEL

- [ ] **Race Condition bei Registrierung** — Zwischen Kapazitätsprüfung und INSERT kann Teilnehmerlimit überschritten werden. Fix: Transaction mit `SELECT ... FOR UPDATE`.
- [ ] **`getConfig` ohne Auth** — Jeder kann `api.php?action=getConfig` aufrufen und interne Konfiguration sehen (max_participants, run_day etc.). Auth-Check hinzufügen oder auf öffentlich nötige Werte beschränken.
- [ ] **PII in Error-Logs** — Jeder API-Call loggt komplette GET/POST-Daten inkl. Namen, E-Mails, Telefon. Log-Verbosity reduzieren oder sensible Felder maskieren.
- [ ] **`create_backup.php` auf Server löschen** — Alte Datei mit hardcoded Token. Wird nicht mehr genutzt (createBackup läuft über api.php).

## NIEDRIG

- [ ] **Rate Limiting für Registrierung** — Aktuell nur CAPTCHA, kein IP-basiertes Rate Limiting.
- [ ] **Import-Daten-Validierung schwach** — `importData` validiert weder Datumsformat noch registrationTime aus JSON.
- [ ] **GitHub Pages deaktivieren** — `nixblick.github.io/skyrun/` ist aktiv (nur statisch), könnte verwirrend sein.
