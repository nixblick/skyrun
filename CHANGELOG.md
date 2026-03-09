# Changelog - Skyrun Training

Alle relevanten Änderungen am Projekt werden hier dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [Unreleased]

### Hinzugefügt
- TODO.md mit bekannten Issues und Verbesserungsvorschlägen
- CHANGELOG.md zur Dokumentation aller Änderungen
- Lokales Backup-Script (`backup_local.sh`) für manuelle DB-Sicherungen

## [1.5.0] - 2025-12-11

### Geändert
- Informationstext auf Startseite aktualisiert ("Skyrun Training" statt "Skyrun")
- Hinweis "Start in Einsatzkleidung ist pünktlich um 19 Uhr" ergänzt
- "und/oder" statt "abwechselnd" bei Gebäude-Beschreibung

## [1.4.0] - 2025-12-11

### Hinzugefügt
- Uhrzeit aus `training_dates` in Bestätigungsmails eingebunden
- Pünktlichkeitshinweis in E-Mail-Templates ("Bitte sei pünktlich um X Uhr vor Ort")
- `$time`-Parameter in `sendRegistrationConfirmation()`

### Geändert
- DB-Query holt jetzt `building` UND `time` aus `training_dates`
- Hochstufungs-E-Mails enthalten jetzt ebenfalls die korrekte Uhrzeit

## [1.3.0] - 2025-12-11

### Geändert
- Building-Cards zeigen Bilder jetzt vollständig an (kein Cropping mehr)
- CSS: `object-fit: cover` und feste Höhe entfernt, `height: auto` gesetzt
- Neue Gebäudebilder aktualisiert (Messeturm, Trianon)

## [1.2.0] - 2025-12-11

### Hinzugefügt
- Zweites Gebäude (Trianon) integriert
- Gebäudespezifische Adressen und Statistiken in E-Mails
- Building-Cards auf der Startseite
- Branding auf "Skyrun Training" umgestellt

## [1.1.0] - 2025-12-11

### Hinzugefügt
- Neuer Trainingstermin: 2025-12-11, 19:00

## [1.0.0] - 2025-12-01

### Hinzugefügt
- Simple Math CAPTCHA als Spam-Schutz
- Modulare API-Struktur unter `/api/` (Router, Registration, Admin, Mail, Auth, Utils)
- Honeypot-Feld gegen Bots
- Gebäudefilter im Gipfelbuch (PeakBook)
- Trainings-Termine Verwaltung (CRUD) im Admin-Bereich
- Konfigurierbarer Lauftag und -frequenz
- Registrierungssystem mit Warteliste
- Admin-Panel mit Login, Teilnehmerverwaltung, Export/Import
- E-Mail-Bestätigungen (Registrierung, Warteliste, Hochstufung)
- SFTP-Deploy via GitHub Actions
- Automatisches DB-Backup via GitHub Actions (täglich 02:00 UTC)
