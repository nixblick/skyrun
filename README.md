# Skyrun Registration System (Statische Version)

Dieses System ermöglicht die Verwaltung von Anmeldungen für einen wöchentlichen Skyrun in einem Hochhaus. Es erlaubt maximal 25 Teilnehmer pro Woche und bietet eine Warteliste für zusätzliche Anmeldungen. Diese Version verwendet nur statische Dateien und speichert die Daten lokal im Browser des Benutzers.

## Funktionen

- Anmeldung für den Skyrun mit Name, E-Mail-Adresse und optionaler Telefonnummer
- Verwaltung von maximal 25 Teilnehmern pro Woche
- Automatische Warteliste für zusätzliche Anmeldungen
- Automatische Hochstufung von Personen auf der Warteliste, wenn Plätze frei werden
- Admin-Bereich mit Passwortschutz zum Verwalten von Teilnehmern und Warteliste
- Export- und Import-Funktionen für Teilnehmerdaten
- Responsive Design für Desktop und mobile Geräte

## Verzeichnisstruktur

- `index.html`: Die Hauptseite der Anwendung
- `styles.css`: Stile für die Webseite
- `script.js`: JavaScript zur Verwaltung der Anmeldungen
- `README.md`: Diese Dokumentation

## Installation und Ausführung

1. Laden Sie die Dateien auf Ihren lokalen Computer oder Webserver herunter
2. Öffnen Sie die `index.html`-Datei in Ihrem Webbrowser
3. Bei Verwendung auf einem Webserver sollten alle drei Dateien im selben Verzeichnis liegen

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
2. Geben Sie das Admin-Passwort ein (**skyrun2025**)
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
   - Daten als JSON exportieren (für Backups oder Übertragung auf andere Geräte)
   - Zuvor exportierte JSON-Daten importieren

## Datenspeicherung

Alle Daten werden im localStorage des Browsers gespeichert. Das bedeutet:

- Die Daten bleiben auch nach dem Schließen des Browsers erhalten
- Die Daten sind nur auf dem Gerät verfügbar, auf dem sie eingegeben wurden
- Um die Daten auf ein anderes Gerät zu übertragen, verwenden Sie die Export/Import-Funktionen im Admin-Bereich

## Sicherheitshinweise

- Das Admin-Passwort (**skyrun2025**) ist im Code festgelegt und sollte in einer produktiven Umgebung geändert werden
- Da keine serverseitige Verarbeitung stattfindet, können mehrere Benutzer gleichzeitig Änderungen vornehmen, was zu Inkonsistenzen führen kann
- Für eine produktive Umgebung mit mehreren Administratoren sollte eine Lösung mit einer zentralen Datenbank in Betracht gezogen werden

## Technische Details

- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **Datenspeicherung:** Browser localStorage
- **Kompatibilität:** Unterstützt alle modernen Browser (Chrome, Firefox, Safari, Edge)

## Anpassungsmöglichkeiten

- Das maximale Teilnehmerlimit (25) kann in der Konstante `MAX_PARTICIPANTS` in `script.js` geändert werden
- Der Wochentag (Donnerstag) kann in der Funktion `generateRunDates()` in `script.js` angepasst werden
- Das Admin-Passwort kann in der Konstante `ADMIN_PASSWORD` in `script.js` geändert werden
- Das Farbschema kann in den CSS-Variablen in `styles.css` angepasst werden

## Einschränkungen

- Keine serverseitige Verarbeitung
- Keine Echtzeit-Benachrichtigungen bei Änderungen
- Keine automatischen E-Mail-Benachrichtigungen
- Die Daten sind lokal gespeichert und nicht zentral verwaltet

## Autor

andre@nixblick.de

## Lizenz

Dieses Projekt ist unter der Open Source.