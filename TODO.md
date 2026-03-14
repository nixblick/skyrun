# TODO - Skyrun Training

## Sicherheit

- [ ] **Adresse in datenschutz.html + impressum.html eintragen** — HTML-Kommentare `<!-- ADRESSE EINTRAGEN -->` etc. sind noch im Quelltext sichtbar. Datenschutzerklärung ohne Adresse ist rechtlich unvollständig.
- [ ] **Verbose Logging in Produktion reduzieren** — `api.php:155` loggt "===== API Call gestartet =====" + GET/POST bei jedem Request. Füllt Server-Logs schnell, ggf. bei sensiblen Aktionen sinnvoll aber nicht pauschal für alles.

## KRITISCH

- [ ] **PHPMailer via SMTP testen und aktivieren** — PHPMailer (lib/phpmailer/) ist bereits im Repo, SMTP-Config in config.php ist fertig (smtp.goneo.de:465/ssl). mail-functions.php wurde zurückgesetzt auf mail() weil SMTP-Login nicht funktioniert hat. Für Debuggen: `SMTPDebug = SMTP::DEBUG_SERVER` aktivieren und Fehlerlog auf dem Server prüfen (SSH oder Hosting-Panel). Goneo evtl. anderen SMTP-Port testen (587/STARTTLS).

- [ ] **Passwörter rotieren** — Bei Goneo ändern: DB-Passwort, SMTP-Passwort (`skyrun@mein-computerfreund.de`), dann `./rotate_credentials.sh` ausführen.
- [ ] **`create_backup.php` auf Server löschen** — Alte Datei mit hardcoded Token. Wird nicht mehr genutzt, per .htaccess blockiert aber sollte weg.

## Bugs / Funktionale Fehler

- [ ] **Datenschutz Abschnitt 2.3 bedingt korrekt** — "wird automatisch eine Bestätigungs-E-Mail gesendet" gilt nur wenn `MAIL_ENABLED=true` und "BCC geht an Organisator" nur wenn `MAIL_BCC` gesetzt ist. Formulierung ggf. anpassen wenn MAIL dauerhaft aktiv ist.

## Verbesserungen

- [ ] **Automatische Datenlöschung** — Datenschutzerklärung verspricht "spätestens 4 Wochen nach Termin". Cron-Job oder API-Endpoint `cleanupOldRegistrations` implementieren.
- [ ] **shared/policies Templates befüllen** — datenschutz.html + impressum.html als Vorlagen in `~/GitHub/nixblick/shared/policies/` ablegen (mit `{{PLATZHALTER}}`-Syntax laut README).
- [ ] **Sticky Header** — Header wird beim Scrollen kompakter (kleinere Schrift, weniger Padding). Verbessert Mobile-UX.
- [ ] **Favicon** — SVG-Favicon (Flammen/Treppen-Icon) für Browser-Tab und Homescreen-Bookmark.
- [ ] **`prefers-color-scheme` Support** — Dark Theme automatisch aktivieren wenn das Betriebssystem auf Dark Mode steht (als Default, überschreibbar per Toggle).
