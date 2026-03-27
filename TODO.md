# TODO - Skyrun Training

## Sicherheit

### Credentials rotieren (KRITISCH)

Git-History wurde am 19.03.2026 bereinigt. Alte Werte könnten gecacht sein.

- [ ] **Admin-Passwort** — im Admin-Panel → Einstellungen → Passwort ändern
- [ ] **SMTP-Passwort** — bei Goneo für skyrun@mein-computerfreund.de ändern, dann in config.php auf Server aktualisieren
- [ ] **BACKUP_TOKEN** — neuen Token generieren (`openssl rand -base64 48`), dann in 3 Stellen setzen:
  1. `config.php` auf Server (per SFTP)
  2. GitHub Secret `BACKUP_TOKEN` (Settings → Secrets)
  3. Lokale `.backup_config`
- [ ] **SFTP-Passwort** — bei Goneo ändern, dann:
  1. GitHub Secret `FTP_PASSWORD`
  2. Lokale `.backup_config`
- [ ] **Test nach Rotation** — Backup-Workflow manuell triggern (GitHub → Actions → Database Backup → Run workflow)
- [ ] **DSGVO: Betroffene informieren** — SQL-Dump mit echten Namen/E-Mails/Telefon war in öffentlicher Git-History.

### Weitere Sicherheit

- [ ] **Adresse in datenschutz.html + impressum.html eintragen** — HTML-Kommentare `<!-- ADRESSE EINTRAGEN -->` sind noch im Quelltext sichtbar. Datenschutzerklärung ohne Adresse ist rechtlich unvollständig.
- [ ] **`create_backup.php` auf Server löschen** — falls noch vorhanden.
- [ ] **Verbose Logging reduzieren** — api.php loggt bei JEDEM Request "API Call gestartet" + GET/POST. Füllt Server-Logs. Nur bei Fehlern loggen.
- [ ] **Rate-Limiting auf Registrierung** — Aktuell nur CAPTCHA-Schutz, kein IP-basiertes Limit.
- [ ] **CAPTCHA verstärken** — Aktuell nur rand(1,10)+rand(1,10) = 19 mögliche Antworten. Brute-forcebar.
- [ ] **Content-Security-Policy (CSP)** — letzter fehlender großer Security Header. Muss `static.nixblick.de` als erlaubte Script-Quelle enthalten.

## Bugs / Funktionale Fehler

- [ ] **Datenschutz Abschnitt 2.3 bedingt korrekt** — "Bestätigungs-E-Mail wird automatisch gesendet" gilt nur wenn MAIL_ENABLED=true.

## Verbesserungen

- [ ] **Datenlöschung + Gipfelbuch entkoppeln** — Datenschutzerklärung verspricht "spätestens 4 Wochen nach Termin". Gipfelbuch liest aktuell aus registrations → Konflikt. Lösung:
  1. Neue DB-Tabelle `participation_log` (station, date, building, person_count — keine PII)
  2. Schreibpunkte in api.php bei register/promote/remove
  3. Migration bestehender Daten
  4. Gipfelbuch-Query umstellen
  5. Cleanup-Action + GitHub Action (wöchentlich)
  6. Datenschutz.html danach aktualisieren
- [ ] **Repo privat machen?** — Abwägen ob public wirklich nötig ist. Private Repos kosten nichts bei GitHub.
