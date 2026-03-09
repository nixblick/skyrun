# TODO - Skyrun Training

## KRITISCH - Sicherheit

- [ ] **Credentials aus Git-Historie entfernen** — `config.php` enthält DB-Passwort und SMTP-Passwort im Klartext und wurde committed. Passwörter rotieren (DB + SMTP), dann `config.php` aus Historie entfernen (`git filter-branch` oder `bfg`)
- [ ] **Debug-Modus in `api/index.php` abschalten** — Zeile 11-13 setzt `display_errors=1` fest und überschreibt Config. Zeigt Nutzern interne Pfade und Fehler
- [ ] **Doppelte API zusammenführen** — `api.php` (monolithisch) und `api/` (modular) existieren parallel. Fehleranfällig, Features divergieren bereits (z.B. `run_frequency`, CAPTCHA, Training-Dates-Endpoints). Eine Version wählen, andere löschen
- [ ] **CSRF-Schutz für Admin-Aktionen** — Keine CSRF-Tokens vorhanden. Jede Website kann Admin-Aktionen im Browser eines eingeloggten Admins auslösen
- [ ] **CORS einschränken** — `Access-Control-Allow-Origin: *` erlaubt jeder Website API-Zugriff. Auf eigene Domain beschränken

## BUGS

- [ ] **`removeParticipant`: Keine E-Mail bei automatischer Hochstufung** — Wenn Teilnehmer entfernt wird und Wartelisten-Einträge automatisch nachrücken, wird keine Benachrichtigungs-E-Mail gesendet (im Gegensatz zu `promoteFromWaitlist`)
- [ ] **`api/registration.php`: CAPTCHA und Honeypot fehlen** — Die modulare Version hat keinen Spam-Schutz. Falls diese API genutzt wird, ist Registrierung ungeschützt
- [ ] **`api/index.php`: Training-Dates-Endpoints fehlen im Router** — `getTrainingDates`, `addTrainingDate`, `updateTrainingDate`, `deleteTrainingDate` werden nicht geroutet
- [ ] **`$date` bei Registrierung nicht format-validiert** — Nur `empty()`-Check, kein Regex wie bei `addTrainingDate`
- [ ] **Backup-Script: `training_dates`-Tabelle fehlt** — `create_backup.php` sichert nur `registrations`, `users`, `config`, `stations`
- [ ] **Backup-Workflow: Namens-Mismatch** — Workflow ruft `backup_db.php` auf, Datei heißt aber `create_backup.php`

## VERBESSERUNGEN

- [ ] **`$time` in E-Mail-Templates escapen** — `htmlspecialchars()` für `$time` in `mail-functions.php` und `api/mail.php`
- [ ] **Inkonsistente HTML-Entities in E-Mail** — Trianon: rohes `ö` (`Höhenmeter`), Messeturm: `H&ouml;henmeter`. Vereinheitlichen
- [ ] **Unbenutzte Variable `$stations`** — In `api.php:140` und `api/registration.php:15` deklariert, nie verwendet
- [ ] **Backup-Workflow verbessern** — Aktuell fragil (PHP-Datei hochladen, 30s warten, hoffen dass sie lief). Besser: SSH-Kommando direkt ausführen oder Backup lokal via GitHub Action mit DB-Zugang
- [ ] **`config.php` durch `.env`-basierte Konfiguration ersetzen** — Saubere Trennung von Code und Konfiguration
- [ ] **Rate Limiting für Registrierung** — Aktuell nur CAPTCHA, kein IP-basiertes Rate Limiting
- [ ] **Admin-Session Timeout** — Kein automatischer Logout nach Inaktivität

## ERLEDIGT

_Hier erledigte Punkte eintragen mit Datum_
