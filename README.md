# Skyrun Registration System (Serverbasierte Version)

Dieses System ermöglicht die Verwaltung von Anmeldungen für einen wöchentlichen Skyrun in einem Hochhaus. Es erlaubt maximal 25 Teilnehmer pro Woche und bietet eine Warteliste für zusätzliche Anmeldungen. Diese Version verwendet PHP zur serverseitigen Speicherung der Daten in einer JSON-Datei.

## Funktionen

- Anmeldung für den Skyrun mit Name, E-Mail-Adresse und optionaler Telefonnummer
- Verwaltung von maximal 25 Teilnehmern pro Woche
- Automatische Warteliste für zusätzliche Anmeldungen
- Automatische Hochstufung von Personen auf der Warteliste, wenn Plätze frei werden
- Admin-Bereich mit Passwortschutz zum Verwalten von Teilnehmern und Warteliste
- Export- und Import-Funktionen für Teilnehmerdaten
- Responsive Design für Desktop und mobile Geräte
- **Zentrale Datenspeicherung auf dem Server**

## Verzeichnisstruktur

- `index.html`: Die Hauptseite der Anwendung
- `styles.css`: Stile für die Webseite
- `server-script.js`: JavaScript für die Interaktion mit dem PHP-Backend
- `api.php`: PHP-Backend zur Datenverwaltung
- `registrations.json`: Datei zur serverseitigen Speicherung der Anmeldungen (wird automatisch erstellt)
- `README.md`: Diese Dokumentation

## Voraussetzungen

- Ein Webserver mit PHP 7.0 oder höher
- Schreibrechte für das Verzeichnis, in dem die `registrations.json` gespeichert wird

## Installation und Ausführung

1. Laden Sie alle Dateien auf Ihren Webserver hoch
2. Stellen Sie sicher, dass das Verzeichnis, in dem sich die Dateien befinden, Schreibrechte für PHP hat (für die `registrations.json`-Datei)
3. Öffnen Sie die Website in Ihrem Browser

## Wichtige Sicherheitshinweise

- **Das Standard-Admin-Passwort in `api.php` ist "skyrun2025"**. Ändern Sie dieses unbedingt für den produktiven Einsatz!
- Die Datei `registrations.json` sollte nicht direkt über HTTP erreichbar sein. Konfigurieren Sie Ihren Webserver entsprechend oder platzieren Sie die Datei außerhalb des Webroot-Verzeichnisses.

## Benutzeranleitung

### Für Teilnehmer:

1. Öffnen Sie die Webseite
2. Füllen Sie das Anmeldeformular aus (Name, E-Mail, optional Telefonnummer)
3. Wählen Sie ein Datum für Ihren Skyrun
4. Aktivieren Sie die Warteliste-Option, falls Sie auch auf die Warteliste gesetzt werden möchten, wenn alle Plätze belegt sind
5. Klicken Sie auf "Anmelden"
6. Eine Bestätigung erscheint nach erfolgreicher Anmeldung

### Für Administratoren:

1. Klicken Sie auf "Admin-Bereich" im Footer der Seite
2. Geben Sie das Admin-Passwort ein (standardmäßig "skyrun2025", sollte geändert werden)
3. Nach erfolgreicher Anmeldung haben Sie Zugriff auf drei Tabs:
   - **Teilnehmer**: Zeigt alle angemeldeten Teilnehmer für das ausgewählte Datum an
   - **Warteliste**: Zeigt alle Personen auf der Warteliste für das ausgewählte Datum an
   - **Exportieren**: Ermöglicht den Export der Teilnehmerdaten als CSV oder JSON sowie den Import von zuvor exportierten Daten

4. Im Teilnehmer-Tab können Sie:
   - Alle angemeldeten Teilnehmer sehen
   - Teilnehmer entfernen (wodurch automatisch der nächste auf der Warteliste hochgestuft wird)

5. Im Warteliste-Tab können Sie:
   - Alle Personen auf der Warteliste sehen
   - Personen manuell hochstufen
   - Personen von der Warteliste entfernen

6. Im Exportieren-Tab können Sie:
   - Daten als CSV exportieren (für Excel oder andere Tabellenkalkulationen)
   - Daten als JSON exportieren (für Backups)
   - Zuvor exportierte JSON-Daten importieren

## Datenspeicherung

Alle Daten werden in der Datei `registrations.json` auf dem Server gespeichert:

- Die Daten werden zentral verwaltet und sind für alle Benutzer konsistent
- Änderungen sind sofort für alle sichtbar
- Die Datei sollte regelmäßig gesichert werden, um Datenverlust zu vermeiden

## Anpassungen

### Admin-Passwort ändern

Öffnen Sie die Datei `api.php` und ändern Sie die folgende Zeile:

```php
$adminPassword = 'skyrun2025'; // Ändern Sie dies zu einem sicheren Passwort
```

### Maximale Teilnehmerzahl ändern

Die maximale Teilnehmerzahl ist in der Datei `api.php` festgelegt. Suchen Sie nach dem folgenden Code und ändern Sie die Zahl 25:

```php
if ($participantsCount >= 25) {
    // ...
}
```

Ebenso in `server-script.js`:

```javascript
const MAX_PARTICIPANTS = 25;
```

### Wochentag des Runs ändern

Der Wochentag (standardmäßig Donnerstag) kann in der Funktion `generateRunDates()` in `server-script.js` angepasst werden.

## Fehlerbehebung

### Problem: Die Daten werden nicht gespeichert

- Überprüfen Sie, ob PHP Schreibrechte für das Verzeichnis hat
- Stellen Sie sicher, dass genügend Speicherplatz vorhanden ist
- Überprüfen Sie die PHP-Fehlerprotokolle

### Problem: Admin-Login funktioniert nicht

- Überprüfen Sie, ob das richtige Passwort verwendet wird
- Stellen Sie sicher, dass JavaScript im Browser aktiviert ist
- Überprüfen Sie die Browser-Konsole auf Fehler

## Technische Details

- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **Backend:** PHP
- **Datenspeicherung:** JSON-Datei auf dem Server
- **Kommunikation:** Fetch API für AJAX-Anfragen
- **Kompatibilität:** Unterstützt alle modernen Browser (Chrome, Firefox, Safari, Edge)

## Autor

Ihr Name

## Lizenz

Dieses Projekt ist unter der MIT-Lizenz lizenziert.