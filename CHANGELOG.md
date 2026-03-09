# Changelog - Skyrun Training

Alle relevanten Änderungen am Projekt werden hier dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [3.1.0] - 2026-03-09

### Hinzugefügt
- **CSRF-Schutz** für alle schreibenden Admin-Aktionen (Token-basiert, pro Session)
- E-Mail-Benachrichtigung bei automatischer Hochstufung von der Warteliste (`removeParticipant`)
- Datumsformat-Validierung (`YYYY-MM-DD`) bei Registrierung
- `.htaccess` blockiert Zugriff auf ungenutztes `/api/`-Verzeichnis
- `config.php.example` als Vorlage für neue Installationen
- `rotate_credentials.sh` für automatisierte Passwort-Rotation (lokal, nicht in Git)

### Geändert
- CORS auf `https://www.mein-computerfreund.de` eingeschränkt (statt `*`)
- Debug-Modus in `api/index.php` deaktiviert (Fehler nur noch ins Log)
- `$time` in E-Mail-Templates mit `htmlspecialchars()` escaped
- HTML-Entities in E-Mails vereinheitlicht (`H&ouml;henmeter` statt rohes `ö`)
- Backup-Token aus `api.php` in `config.php` ausgelagert (nicht mehr hardcoded)
- Admin-JS nutzt zentrale `adminFormData()`-Funktion für alle API-Aufrufe

### Entfernt
- `config.php` aus Git-Historie entfernt (enthielt Klartext-Passwörter)
- Unbenutzte Variable `$stations` in `api.php` und `api/registration.php`
- Erledigte Punkte aus TODO.md (stehen im CHANGELOG)

## [3.0.0] - 2026-03-09

### Hinzugefügt
- TODO.md mit bekannten Issues aus Code-Review
- CHANGELOG.md zur Dokumentation aller Änderungen
- `createBackup` API-Endpoint in `api.php` — Token- oder Admin-Session-geschützt
- Lokales Backup-Toolkit (`backup_local.sh`): create, download, list, verify, restore-test
- Docker-basierter Restore-Test mit echter MySQL-Datenbank
- Automatische Bereinigung alter Backups (behält die letzten 30)
- `training_dates`-Tabelle wird jetzt mitgesichert
- `DROP TABLE IF EXISTS` vor jedem CREATE im Backup für saubere Wiederherstellung
- Versionsnummer und Credit im Footer (dezent)

### Geändert
- Backup-Workflow komplett überarbeitet: `api.php?action=createBackup` statt PHP-Upload-Hack
- Backup-Workflow läuft wöchentlich (Montag 03:00 MEZ) statt täglich
- .gitignore aufgeräumt und erweitert (backups/, .env, IDE-Ordner, .backup_config)

### Behoben
- Backup-Workflow war seit September 2025 tot (GitHub Cron-Timeout nach 60 Tagen Inaktivität)
- SFTP-Pfade korrigiert (relativ zum Home-Verzeichnis)
- Korrekte Live-URL (www.mein-computerfreund.de)

## [2.0.0] - 2025-05-16

### Geändert
- Modulare API-Struktur: `api.php` aufgeteilt in `api/registration.php`, `api/admin.php`, `api/mail.php`
- Router-basiertes Dispatching über `api/index.php`

## [1.5.0] - 2025-02-17

### Geändert
- Informationstext auf Startseite aktualisiert ("Skyrun Training" statt "Skyrun")
- Hinweis "Start in Einsatzkleidung ist pünktlich um 19 Uhr" ergänzt
- "und/oder" statt "abwechselnd" bei Gebäude-Beschreibung

## [1.4.0] - 2025-02-16

### Hinzugefügt
- Uhrzeit aus `training_dates` in Bestätigungsmails eingebunden
- Pünktlichkeitshinweis in E-Mail-Templates ("Bitte sei pünktlich um X Uhr vor Ort")

### Geändert
- Hochstufungs-E-Mails enthalten jetzt ebenfalls die korrekte Uhrzeit

## [1.3.0] - 2025-02-16

### Geändert
- Building-Cards zeigen Bilder vollständig an (kein Cropping mehr)
- Neue Gebäudebilder (Messeturm, Trianon)

## [1.2.0] - 2025-12-11

### Hinzugefügt
- Zweites Gebäude (Trianon) integriert
- Gebäudespezifische Adressen und Statistiken in E-Mails
- Building-Cards auf der Startseite

## [1.1.0] - 2025-12-11

### Hinzugefügt
- Simple Math CAPTCHA als Spam-Schutz
- Honeypot-Feld gegen Bots
- Trainings-Termine Verwaltung (CRUD)
- Konfigurierbarer Lauftag und -frequenz

## [1.0.0] - 2025-04-11

### Hinzugefügt
- Registrierungssystem mit Warteliste
- Admin-Panel mit Login, Teilnehmerverwaltung, Export/Import
- E-Mail-Bestätigungen (Registrierung, Warteliste, Hochstufung)
- SFTP-Deploy via GitHub Actions
- Automatisches DB-Backup via GitHub Actions
