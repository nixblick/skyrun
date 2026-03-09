# TODO - Skyrun Training

## KRITISCH

- [ ] **Passwörter rotieren** — Bei Goneo ändern: DB-Passwort, SMTP-Passwort (`skyrun@mein-computerfreund.de`), dann `./rotate_credentials.sh` ausführen.
- [ ] **`create_backup.php` auf Server löschen** — Alte Datei mit hardcoded Token. Wird nicht mehr genutzt, per .htaccess blockiert aber sollte weg.

## FEATURES

- [x] **Dark/Light Theme Toggle** — Umgesetzt in v3.2.0
- [x] **Countdown + getrennte Counter pro Termin** — Umgesetzt in v3.3.0. Zeigt die nächsten 3 Termine als Karten mit Gebäude, Anmeldezahl, Warteliste und Tage-Countdown.
- [ ] **Sticky Header** — Header wird beim Scrollen kompakter (kleinere Schrift, weniger Padding). Verbessert Mobile-UX.
- [ ] **Favicon** — SVG-Favicon (Flammen/Treppen-Icon) für Browser-Tab und Homescreen-Bookmark.
- [ ] **`prefers-color-scheme` Support** — Dark Theme automatisch aktivieren wenn das Betriebssystem auf Dark Mode steht (als Default, überschreibbar per Toggle).
