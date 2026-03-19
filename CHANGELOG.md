# Changelog - Skyrun Training

Alle relevanten Änderungen am Projekt werden hier dokumentiert.
Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [3.5.1] - 2026-03-19

### Hinzugefügt
- **Datenschutzerklärung: Admin-Log dokumentiert** (`datenschutz.html`) — Neuer Abschnitt 8 beschreibt das Admin-Aktivitätsprotokoll: was geloggt wird, dass keine Besucherdaten betroffen sind, Rechtsgrundlage Art. 6 Abs. 1 lit. f DSGVO.
- **GitHub-Link im Footer** (`index.html`) — Link auf https://github.com/nixblick

## [3.5.0] - 2026-03-19

### Hinzugefügt
- **Admin-Log** (`api.php`, `js/admin.js`, `index.html`) — Neuer Tab "Log" im Admin-Panel. Loggt Login, Logout, Session-Ablauf, CSRF-Fehler, Termin-Erstellung/-Löschung und DB-Fehler mit Level (Info/Warnung/Fehler), Zeitstempel und Details. Filterbar nach Level. Max 500 Einträge, auto-cleanup. DB-Tabelle `admin_log` wird per Auto-Migration erstellt.
- **DB-Fehlercode in Fehlermeldung** — Bei fehlgeschlagenem INSERT wird jetzt der MySQL-Fehlercode angezeigt (z.B. "Fehler beim Hinzufügen (DB-1205)") statt generischer Meldung. Hilft bei Diagnose von Table-Locks durch Hoster-Backups.

## [3.4.0] - 2026-03-19

### Hinzugefügt
- **Session-Timer mit Warnung** (`js/admin.js`) — Nach 25 Min Inaktivität erscheint ein oranges Banner "Session läuft in 5 Minuten ab!" mit Button "Session verlängern". Bei jeder Admin-Aktion wird der Timer automatisch zurückgesetzt. Nach 30 Min ohne Aktivität: automatischer Logout mit klarer Meldung.
- **Smoke-Test-Endpoint** (`api.php: smokeTest`) — Automatisierter Test erstellt Termine (Messeturm + Trianon), liest sie zurück, löscht sie. Nutzt BACKUP_TOKEN zur Authentifizierung, läuft im Backup-Workflow vor dem Backup.
- **Smoke-Test in Backup-Workflow** (`.github/workflows/create_backup.yml`) — Smoke-Test läuft als eigener Job vor dem Backup. Schlägt der Test fehl, wird kein Backup erstellt → frühzeitige Fehlererkennung.

### Geändert
- **UNIQUE-Constraint erweitert** (`training_dates`) — Von `UNIQUE(date)` auf `UNIQUE(date, building)`. Erlaubt Messeturm + Trianon am selben Tag. Auto-Migration beim nächsten API-Call, kein manuelles SQL nötig.

### Gefixt
- **Session-Ablauf zeigt jetzt klare Fehlermeldung** (`js/admin.js`) — Bisher bekamen Admins bei abgelaufener Session nur "Fehler beim Hinzufügen" (oder ähnlich generisch) bei allen Aktionen. Jetzt wird bei 401/403 klar "Session abgelaufen — bitte neu anmelden." angezeigt und der Logout automatisch ausgeführt. Betrifft alle Admin-Funktionen.

## [3.3.8] - 2026-03-15

### Gefixt
- **PHPMailer SMTP reaktiviert** (`mail-functions.php`, `api/mail.php`) — Mails werden jetzt via smtp.goneo.de:587 (STARTTLS) mit Auth versendet statt via PHP `mail()`. Behebt Bounces von GMX/IONOS/1und1 wegen fehlender SPF-Autorisierung des Servers.

## [3.3.7] - 2026-03-14

### Gefixt
- **CSRF-Token via GET entfernt** (`api.php`) — Token wurde bisher auch aus `$_GET` akzeptiert, was ihn in Server-Logs, Browser-History und Referrer-Headern exponieren kann. Jetzt ausschließlich via POST.
- **Serverpfad aus Backup-Response entfernt** (`api.php`) — `createBackup` lieferte den absoluten Pfad mit zurück, unnötige Informationsoffenbarung.
- **`promoteFromWaitlist` mit Transaction abgesichert** (`api.php`) — Race Condition geschlossen: Kapazitätsprüfung und UPDATE laufen jetzt in einer Transaction mit `FOR UPDATE`-Sperren, konsistent mit `removeParticipant`. E-Mail-Versand nach Commit außerhalb der Transaction.
- **Analytics Cache-Busting** (`index.html`) — `?v=3` ergänzt, konsistent mit anderen nixblick-Projekten.

## [3.3.6] - 2026-03-14

### Hinzugefügt
- **Datenschutzerklärung** (`datenschutz.html`) — DSGVO-konforme Datenschutzerklärung nach kritischer Review: korrekte Rechtsgrundlage (lit. a, Einwilligung), E-Mail-Versand und BCC dokumentiert, IP-Anonymisierung korrekt differenziert, Analytics-Drittdomain erwähnt, ehrliche Speicherdauer, Widerrufsrecht prominent, Jugendfeuerwehr-Hinweis, § 25 TTDSG für Session-Cookie
- **Impressum** (`impressum.html`) — § 5 TMG, dezent verlinkt im Footer neben Admin-Bereich
- **Footer-Links** in `index.html` — Datenschutz + Impressum

## [3.3.5] - 2026-03-13

### Gefixt
- **Kritischer PHP 8.4 Fix: schließende `?>` Tags entfernt** — `config.php`, `mail-functions.php` und `api.php` hatten abschließende `?>` Tags. PHP gibt danach einen Newline aus, der in PHP 7 vom Output Buffer geschluckt wurde. In PHP 8.4 (kein Output Buffering default) landete dieser Newline vor dem JSON im HTTP-Response — `JSON.parse()` schlägt fehl → "Netzwerkfehler" im Admin-Bereich + CSRF-Token-Probleme.

## [3.3.4] - 2026-03-13

### Geändert
- **PHP 8.4 Session-Fix** — `ini_set()` für Session-Cookie-Parameter durch `session_set_cookie_params()` ersetzt. Behebt Session-Verlust und CSRF-Token-Fehler unter PHP 8.4.
- **MySQLi Exception-Modus deaktiviert** — `mysqli_report(MYSQLI_REPORT_OFF)` explizit gesetzt. PHP 8.1+ aktiviert Exceptions standardmäßig, was zu unbehandelten 500-Fehlern führte (u.a. "Netzwerkfehler" im Admin-Bereich).

## [3.3.3] - 2026-03-13

### Geändert
- **Race Condition Warteliste gefixt** — `removeParticipant` läuft jetzt vollständig in einer MySQL-Transaction mit `FOR UPDATE`-Sperren. Verhindert, dass bei gleichzeitigen Löschungen derselbe Wartelisten-Eintrag doppelt hochgestuft wird. E-Mails werden nach dem Commit außerhalb der Transaction gesendet.
- **PHPMailer zurückgesetzt** — SMTP-Verbindung zu smtp.goneo.de schlug fehl, E-Mail-Versand wieder auf `mail()` zurückgestellt. PHPMailer-Dateien bleiben im Repo (lib/phpmailer/) für späteren Fix.

## [3.3.2] - 2026-03-13

### Geändert
- **PHP 8 Kompatibilität** — `strftime()` und `setlocale()` in `api/utils.php` durch `IntlDateFormatter` ersetzt (beide Funktionen in PHP 8.1 entfernt)

## [3.3.1] - 2026-03-11

### Hinzugefügt
- **Event-Tracking via nixblick Analytics** — Wichtige Aktionen werden als virtuelle Pageviews getrackt: Anmeldung (`/evt/registration`), Warteliste (`/evt/registration-waitlist`), Admin-Login (`/evt/admin-login`), Login-Fehler (`/evt/admin-login-fail`). Kein Backend-Umbau nötig, Events erscheinen direkt im Analytics-Dashboard.

## [3.3.0] - 2026-03-09

### Geändert
- **Terminübersicht statt Einzelzähler** — Statt einer einzigen Anmeldezahl werden jetzt die nächsten 3 Termine als kompakte Karten angezeigt, jeweils mit Gebäude, Anmeldezahl/Max, Warteliste und Tage-Countdown ("Heute", "Morgen", "in X Tagen"). Sinnvoll bei mehreren Trainings pro Woche.

## [3.2.0] - 2026-03-09

### Hinzugefügt
- **Dark/Light Theme Toggle** — Mond/Sonne-Symbol im Footer zum Umschalten zwischen hellem und dunklem Theme. Auswahl wird in localStorage gespeichert. Alle Farben auf CSS-Variablen umgestellt, harmonisches Dark-Theme mit Blau-Grau-Farbfamilie.

## [3.1.1] - 2026-03-09

### Behoben
- **E-Mail-Zustellung fehlgeschlagen** — Envelope-Sender (`-f`) explizit auf `skyrun@mein-computerfreund.de` gesetzt. Ohne den Parameter nutzte PHP `mail()` den Server-Default (`andre@creavisions.de`), was bei GMX und anderen Providern zum SPF-Reject führte (554 Policy Restriction).

## [3.1.0] - 2026-03-09

### Hinzugefügt
- **CSRF-Schutz** für alle schreibenden Admin-Aktionen (Token-basiert, pro Session)
- **Admin-Session Timeout** nach 30 Minuten Inaktivität (automatischer Logout)
- Gehärtete Session-Cookies (httponly, secure, samesite=Strict, strict_mode)
- `session_regenerate_id()` nach Login gegen Session Fixation
- Timing-sichere Login-Prüfung (verhindert Username-Enumeration)
- Honeypot-Feld ins Formular verschoben (war außerhalb, daher wirkungslos)
- `.htaccess`: Zugriff auf `.sql`, `.log`, versteckte Dateien und `.backup_config` blockiert
- HTTPS-Redirect aktiviert
- Backup-Token wird per POST statt GET gesendet (kein Leak in Logs)
- `personCount` auf max. 10 begrenzt
- Rate Limiting für Admin-Login (max 5 Fehlversuche, dann 60s Sperre)
- E-Mail-Benachrichtigung bei automatischer Hochstufung von der Warteliste (`removeParticipant`)
- Datumsformat-Validierung (`YYYY-MM-DD`) bei Registrierung
- `.htaccess` blockiert Zugriff auf ungenutztes `/api/`-Verzeichnis
- `config.php.example` als Vorlage für neue Installationen
- `rotate_credentials.sh` für automatisierte Passwort-Rotation (lokal, nicht in Git)

### Behoben
- **Race Condition bei Registrierung** — Transaction mit `SELECT ... FOR UPDATE` verhindert parallele Überbuchung
- **PII in Error-Logs maskiert** — Name, E-Mail, Telefon in Request-Logs und allen individuellen `error_log()`-Aufrufen maskiert
- **Import-Daten-Validierung** — Datumsformat (`YYYY-MM-DD`), `registrationTime` und `personCount` (max. 10) werden validiert
- **Import-Transaction abgesichert** — `try/catch` mit `rollback()` bei DB-Fehlern
- **Backup-Token timing-safe verglichen** — `hash_equals()` statt `!==` gegen Timing-Angriffe
- **Dummy-Hash für Timing-Schutz** — Echter bcrypt-Hash statt ungültigem String bei fehlgeschlagenem Login
- **Rate Limiting file-basiert** — Login-Zähler pro IP im Dateisystem statt Session (nicht mehr umgehbar durch Cookie-Löschung)
- **`getConfig` eingeschränkt** — Öffentlich nur noch `max_participants`, alle anderen Config-Werte nur für Admin
- **`station` gegen DB validiert** — Wache wird gegen `stations`-Tabelle geprüft, Fallback auf `50 - Sonstige`
- **`getStats` Datumsvalidierung** — `preg_match` auf `YYYY-MM-DD` wie bei allen anderen Datum-Eingaben
- **Registrierung nur für gültige Zukunftstermine** — Datum muss in `training_dates` existieren und `>= heute` sein

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
