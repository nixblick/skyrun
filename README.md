# Skyrun Training Anmeldesystem (PHP/MySQL)
Dieses System verwaltet Anmeldungen für den wöchentlichen Skyrun im MesseTurm Frankfurt mit konfigurierbarer Teilnehmerzahl und Warteliste.

## Funktionen
- **Anmeldung**: Name, E-Mail, optional Telefon, Wache, Personenanzahl und Datum.
- **Wachenauswahl**: Dynamisch aus der Datenbank geladene Liste von Feuerwehrwachen.
- **Konfiguration**: Maximale Teilnehmerzahl (Standard: 25), Wochentag und Uhrzeit des Laufs.
- **Warteliste**: Automatisch bei Überbuchung (optional wählbar); automatische Hochstufung bei freien Plätzen.
- **Admin-Bereich**: Login-geschützt; Verwaltung von Teilnehmern/Warteliste, Entfernen/Hochstufen, Export (CSV/JSON), Import (JSON), Einstellungen (Max. Teilnehmer, Lauftag/-zeit), Passwortänderung.
- **Gipfelbuch**: Statistik der Teilnahmen pro Wache.
- **Design**: Responsiv für Desktop und Mobile.

## Dateien
- `index.html`: Frontend-Hauptseite.
- `styles.css`: CSS-Styling.
- `server-script.js`: JavaScript für Frontend-Logik und API-Aufrufe.
- `api.php`: PHP-Backend für Datenverarbeitung und DB-Interaktion.
- `config.php`: Datenbankzugang und Konfiguration.
- `skyrun_db.sql`: SQL zur Erstellung der DB-Struktur (`registrations`, `users`, `config`, `stations`).
- `.htaccess`: Schützt `config.php` vor direktem Zugriff.
- `README.md`: Diese Dokumentation.

## Voraussetzungen
- Webserver mit PHP ≥ 7.4 (für `??`-Operator, `password_hash` etc.).
- MySQL/MariaDB-Datenbank.
- Schreibrechte für Fehlerlogging (falls in `config.php` aktiviert).

## Wachenverwaltung
Die Feuerwehrwachen werden in der Tabelle `stations` gespeichert und dynamisch geladen:
- **Struktur**:
  - `id`: Automatisch generierte ID.
  - `code`: Kürzel der Wache (z.B. "1", "42", "OF").
  - `name`: Bezeichnung/Standort (z.B. "Eckenheim", "FF Niederrad").
  - `type`: Typ der Wache (BF, FF, Sonstige).
  - `sort_order`: Sortierreihenfolge für die Anzeige.
- **Hinzufügen neuer Wachen**:
  ```sql
  INSERT INTO stations (code, name, type, sort_order) VALUES 
  ('CODE', 'NAME', 'TYPE', POSITION);
  ```
  TYPE muss einer der Werte 'BF', 'FF' oder 'Sonstige' sein.

## Benutzeranleitung
### Teilnehmer
- Formular ausfüllen, Wache und Datum wählen, „Anmelden" klicken → Bestätigung/Wartelisten-Info.
### Administratoren
1. Footer-Link „Admin-Bereich" klicken.
2. Mit Benutzername/Passwort einloggen.
3. Tabs:
   - **Teilnehmer**: Liste anzeigen/entfernen.
   - **Warteliste**: Hochstufen/entfernen.
   - **Exportieren**: CSV (pro Datum), JSON (alles), Import (JSON).
   - **Gipfelbuch**: Statistik der Teilnahmen pro Wache.
   - **Einstellungen**: Max. Teilnehmer, Lauftag/-zeit, Passwort ändern.

## Technische Details
- **Frontend**: HTML5, CSS3, JavaScript (ES6+, Fetch API).
- **Backend**: PHP ≥ 7.4, MySQL/MariaDB.
- **Authentifizierung**: Passwort-Hash (BCRYPT) in `users`-Tabelle, PHP-Sessions.
- **Konfiguration**: DB-Zugang in `config.php`, Lauf-Parameter in `config`-Tabelle.
- **Sicherheit**: 
  - HTTPS empfohlen, besonders für den Admin-Bereich
  - Dateischutz für `config.php` via `.htaccess`
  - Serverseitige Validierung in `api.php`

## Zugangsdaten und Konfiguration
- **Datenbank**: In `config.php` definiert.
- **Admin**:
  - Benutzername: Z.B. `admin` (via `users`-Tabelle).
  - Passwort: Bei Installation gesetzt, änderbar im Admin-Bereich.
- **System** (in `config`-Tabelle, via Admin-Bereich editierbar):
  - `max_participants`: Standard 25.
  - `run_day`: 0-6 (Sonntag-Samstag, Standard: 4 = Donnerstag).
  - `run_time`: HH:MM (Standard: `19:00`).

## Automatisierter FTPS-Upload
Der Code wird automatisch bei jedem Push auf den `main`-Branch über GitHub Actions auf den Webserver hochgeladen. Dafür wird ein FTPS-Upload mit `sftp` und `sshpass` genutzt. Um sensible Dateien und Entwicklungsspezifisches auszuschließen, wird vor dem Upload ein temporäres Verzeichnis erstellt, das nur die benötigten Dateien enthält.

### Details zum Workflow
- **Workflow-Datei**: `.github/workflows/deploy.yml`
- **Funktion**:
  - Checkt den Code aus dem Repository aus.
  - Erstellt eine Exclude-Liste (`exclude.txt`) für Dateien/Ordner wie `.git/`, `.github/`, `*.sql`, `*.code-workspace`, `config.php`, `*.log` und `php_errors.log`.
  - Kopiert alle nicht ausgeschlossenen Dateien mit `rsync` in ein temporäres Verzeichnis (`temp_deploy`).
  - Lädt die Dateien aus `temp_deploy` per FTPS (Port 2222) in den Server-Pfad `/htdocs/html/skyrun/`.
- **Konfiguration**:
  - Secrets (`FTP_USERNAME`, `FTP_PASSWORD`, `FTP_SERVER`) müssen in den GitHub Repository Settings definiert sein.
  - Die Exclude-Liste kann in der Workflow-Datei angepasst werden.
- **Hinweis**: Der Workflow entfernt keine Dateien auf dem Server, die lokal nicht mehr existieren. Für solche Szenarien kann der Workflow erweitert werden.

## Lizenz
MIT License

Copyright (c) 2025 nixblick

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
