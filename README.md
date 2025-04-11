# Skyrun Anmeldesystem (PHP/MySQL)

Dieses System verwaltet Anmeldungen für den wöchentlichen Skyrun im MesseTurm Frankfurt mit konfigurierbarer Teilnehmerzahl und Warteliste.

## Funktionen

- **Anmeldung**: Name, E-Mail, optional Telefon, Personenanzahl und Datum.
- **Konfiguration**: Maximale Teilnehmerzahl (Standard: 25), Wochentag und Uhrzeit des Laufs.
- **Warteliste**: Automatisch bei Überbuchung (optional wählbar); automatische Hochstufung bei freien Plätzen.
- **Admin-Bereich**: Login-geschützt; Verwaltung von Teilnehmern/Warteliste, Entfernen/Hochstufen, Export (CSV/JSON), Import (JSON), Einstellungen (Max. Teilnehmer, Lauftag/-zeit), Passwortänderung.
- **Design**: Responsiv für Desktop und Mobile.

## Dateien

- `index.html`: Frontend-Hauptseite.
- `styles.css`: CSS-Styling.
- `server-script.js`: JavaScript für Frontend-Logik und API-Aufrufe.
- `api.php`: PHP-Backend für Datenverarbeitung und DB-Interaktion.
- `config.php`: Datenbankzugang und Konfiguration.
- `skyrun_db.sql`: SQL zur Erstellung der DB-Struktur (`registrations`, `users`, `config`).
- `.htaccess`: Schützt `config.php` vor direktem Zugriff.
- `README.md`: Diese Dokumentation.
- `test.php`: Überbleibsel, nicht Teil der Kernanwendung.

## Voraussetzungen

- Webserver mit PHP ≥ 7.4 (für `??`-Operator, `password_hash` etc.).
- MySQL/MariaDB-Datenbank.
- Schreibrechte für Fehlerlogging (falls in `config.php` aktiviert).

## Installation

1. **Datenbank einrichten**:
   - Neue Datenbank erstellen oder bestehende nutzen.
   - `skyrun_db.sql` importieren (erstellt Tabellen und Standardwerte).

2. **Konfiguration anpassen**:
   - `config.php` öffnen und Datenbankdetails (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`) eintragen.
   - `TIMEZONE` prüfen/anpassen (z.B. `Europe/Berlin`).

3. **Admin-Benutzer anlegen**:
   - **Via PHP CLI**: `php -r "echo password_hash('DEIN_PASSWORT', PASSWORD_DEFAULT);"` → Hash kopieren.
   - **SQL ausführen**: `INSERT INTO users (username, password_hash) VALUES ('admin', 'HASH_HIER');`.
   - Alternativ: Online PHP Sandbox nutzen (z.B. phpfiddle.org).

4. **Dateien hochladen**:
   - Alle Dateien (außer `test.php`) in ein Webserver-Verzeichnis laden.

5. **Berechtigungen setzen**:
   - Leserechte für Webserver auf alle Dateien.
   - Schreibrechte für Fehlerlog-Verzeichnis (falls `ENABLE_ERROR_LOGGING = true`).
   - `.htaccess` aktiviert? (Apache: `AllowOverride All`).

6. **Starten**:
   - `index.html` im Browser aufrufen.

## Zugangsdaten und Konfiguration

- **Datenbank**: In `config.php` definiert.
- **Admin**:
  - Benutzername: Z.B. `admin` (via `users`-Tabelle).
  - Passwort: Bei Installation gesetzt, änderbar im Admin-Bereich.
- **System** (in `config`-Tabelle, via Admin-Bereich editierbar):
  - `max_participants`: Standard 25.
  - `run_day`: 0-6 (Sonntag-Samstag, Standard: 4 = Donnerstag).
  - `run_time`: HH:MM (Standard: `19:00`).

## Benutzeranleitung

### Teilnehmer
- Formular ausfüllen, Datum wählen, „Anmelden“ klicken → Bestätigung/Wartelisten-Info.

### Administratoren
1. Footer-Link „Admin-Bereich“ klicken.
2. Mit Benutzername/Passwort einloggen.
3. Tabs:
   - **Teilnehmer**: Liste anzeigen/entfernen.
   - **Warteliste**: Hochstufen/entfernen.
   - **Exportieren**: CSV (pro Datum), JSON (alles), Import (JSON).
   - **Einstellungen**: Max. Teilnehmer, Lauftag/-zeit, Passwort ändern.

## Fehlerbehebung

### Datenbankverbindung fehlt
- `config.php` prüfen (Host, User, Passwort, DB-Name).
- DB-Benutzerrechte checken (SELECT, INSERT, UPDATE, DELETE).

### Admin-Login fehlgeschlagen
- `users`-Tabelle prüfen (Benutzer vorhanden?).
- Passwort korrekt? (Hash neu generieren: `UPDATE users SET password_hash = 'NEUER_HASH' WHERE username = 'admin';`).

### Termine/Statistik falsch
- Browser-Cache leeren (Strg+Shift+R).
- `run_day`/`run_time` im Admin-Bereich prüfen.
- `TIMEZONE` in `config.php` mit Serverzeit abgleichen.
- `php_errors.log` checken.

### Admin wird nach Login ausgeloggt
- **Symptom**: Login erfolgreich, Admin-Bereich erscheint kurz, dann Login-Maske zurück.
- **Ursache**: Zweiter API-Aufruf (z.B. `getParticipants`) scheitert bei Authentifizierung → `handleLogout()` wird aufgerufen.
- **Debugging**:
  1. Browser-Konsole (F12 → Console) prüfen:
     - Logs von `handleAdminLogin` und `updateParticipantsList`.
     - Werden `tempAdminUser`/`tempAdminPass` korrekt gesendet?
  2. Server-Logs (`php_errors.log`) checken:
     - `POST`-Daten bei `adminLogin` vs. `getParticipants` vergleichen.
  3. Test mit einfachem Passwort (z.B. `admin123`), falls Sonderzeichen Probleme machen.
- **Mögliche Fixes**:
  - Sicherstellen, dass `tempAdminUser`/`tempAdminPass` nicht überschrieben werden.
  - API-Logik prüfen: Warum schlägt `verifyAdminLogin` beim zweiten Aufruf fehl?

## Technische Details

- **Frontend**: HTML5, CSS3, JavaScript (ES6+, Fetch API).
- **Backend**: PHP ≥ 7.4, MySQL/MariaDB.
- **Authentifizierung**: Passwort-Hash (BCRYPT) in `users`-Tabelle, keine Sessions/Tokens (unsicher ohne HTTPS!).
- **Konfiguration**: DB-Zugang in `config.php`, Lauf-Parameter in `config`-Tabelle.

## Sicherheitshinweise

- **HTTPS zwingend**: Passwörter werden im JS temporär gehalten → MITM-Risiko ohne SSL.
- **Starkes Passwort**: Regelmäßig ändern.
- **Dateischutz**: `config.php` via `.htaccess` oder Serverkonfig sichern.
- **Validierung**: Serverseitig in `api.php` vorhanden, ggf. erweitern.

## Lizenz

MIT Lizenz