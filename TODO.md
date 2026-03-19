# TODO - Skyrun Training

## Sicherheit

- [ ] **Adresse in datenschutz.html + impressum.html eintragen** — HTML-Kommentare `<!-- ADRESSE EINTRAGEN -->` etc. sind noch im Quelltext sichtbar. Datenschutzerklärung ohne Adresse ist rechtlich unvollständig.
- [ ] **Verbose Logging in Produktion reduzieren** — `api.php:155` loggt "===== API Call gestartet =====" + GET/POST bei jedem Request. Füllt Server-Logs schnell, ggf. bei sensiblen Aktionen sinnvoll aber nicht pauschal für alles.

## KRITISCH

- [ ] **BACKUP_TOKEN synchronisieren** — Backup-Workflow schlägt seit 16.03. fehl (403). Token in GitHub Secret + `.backup_config` stimmt nicht mit `config.php` auf dem Server überein. Fix: Token in `config.php` auf dem Server prüfen, GitHub Secret + `.backup_config` angleichen.
- [ ] **Passwörter rotieren** — Bei Goneo ändern: DB-Passwort, SMTP-Passwort (`skyrun@mein-computerfreund.de`), dann `./rotate_credentials.sh` ausführen.
- [ ] **`create_backup.php` auf Server löschen** — Alte Datei mit hardcoded Token. Wird nicht mehr genutzt, per .htaccess blockiert aber sollte weg.

## Bugs / Funktionale Fehler

- [ ] **Datenschutz Abschnitt 2.3 bedingt korrekt** — "wird automatisch eine Bestätigungs-E-Mail gesendet" gilt nur wenn `MAIL_ENABLED=true` und "BCC geht an Organisator" nur wenn `MAIL_BCC` gesetzt ist. Formulierung ggf. anpassen wenn MAIL dauerhaft aktiv ist.
- [ ] **Datenschutzerklärung prüfen nach v3.4.0-Änderungen** — Folgende Änderungen vom 19.03.2026 könnten Aktualisierung erfordern:
  - **Smoke-Test** schreibt/löscht temporär Testdaten in `training_dates` (keine personenbezogenen Daten, aber Verarbeitungsvorgang)
  - **Session-Status-Endpoint** (`sessionStatus`) — neuer API-Endpunkt, prüft nur Auth-Status, verarbeitet keine neuen Daten
  - **Auto-Migration** ändert DB-Schema (UNIQUE-Constraint) — keine neuen Datenfelder, kein Datenschutz-Einfluss
  - Prüfen ob die technischen Maßnahmen (Session-Timer, Warnbanner) unter "Technische Maßnahmen" in der Datenschutzerklärung erwähnt werden sollten

## Verbesserungen

- [ ] **Datenlöschung + Gipfelbuch entkoppeln** — Datenschutzerklärung verspricht "spätestens 4 Wochen nach Termin". Gipfelbuch liest aktuell aus `registrations` → Konflikt. Lösung in drei Schritten:
  1. **Neue DB-Tabelle `participation_log`** anlegen: `station`, `date`, `building`, `person_count` — keine personenbezogenen Daten.
  2. **Schreibpunkte** in `api.php` ergänzen: bei `register` (nicht waitlisted) + bei `promoteFromWaitlist` → INSERT in `participation_log`. Bei `removeParticipant` → DELETE aus `participation_log`.
  3. **Migration**: bestehende `registrations` WHERE `waitlisted = 0` einmalig in `participation_log` überführen (SQL-Script).
  4. **Gipfelbuch-Query** auf `participation_log` umstellen — zwei Spalten anzeigen: `COUNT(DISTINCT date)` (Termine) + `SUM(person_count)` (Personen gesamt). Tabelle zeigt dann z.B. "12 - FF Bergen | 7 Termine | 23 Personen".
  5. **Cleanup-Action** `cleanupOldRegistrations` in `api.php` + GitHub Action (wöchentlich): `DELETE FROM registrations WHERE date < DATE_SUB(NOW(), INTERVAL 4 WEEK)`.
  6. **Datenschutz.html aktualisieren** — erst nach Schritt 1–5, solange Gipfelbuch noch aus `registrations` liest ist die aktuelle Datenschutzerklärung noch korrekt. Dann zwei Stellen anpassen:
     - **Abschnitt 2, neue Untersektion 2.4 "Gipfelbuch-Log"**: Bei bestätigter Anmeldung (nicht Warteliste) werden anonymisierte Teilnahmedaten gespeichert: Wache, Datum, Gebäude, Personenanzahl — kein Name, keine E-Mail, kein Telefon. Rechtsgrundlage: Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an Veranstaltungsstatistik).
     - **Abschnitt 4 (Speicherdauer)**: Anmeldedaten (mit Personenbezug) werden 4 Wochen nach dem Termin gelöscht. Die anonymisierten Gipfelbuch-Einträge (`participation_log`) werden nicht gelöscht — sie enthalten keine personenbezogenen Daten.
- [ ] **shared/policies Templates befüllen** — datenschutz.html + impressum.html als Vorlagen in `~/GitHub/nixblick/shared/policies/` ablegen (mit `{{PLATZHALTER}}`-Syntax laut README).
- [ ] **Sticky Header** — Header wird beim Scrollen kompakter (kleinere Schrift, weniger Padding). Verbessert Mobile-UX.
- [ ] **Favicon** — SVG-Favicon (Flammen/Treppen-Icon) für Browser-Tab und Homescreen-Bookmark.
- [ ] **`prefers-color-scheme` Support** — Dark Theme automatisch aktivieren wenn das Betriebssystem auf Dark Mode steht (als Default, überschreibbar per Toggle).
