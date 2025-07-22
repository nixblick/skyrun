# Changelog

Alle wichtigen Änderungen an diesem Projekt werden in dieser Datei dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/),
und dieses Projekt folgt [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-07-22

### Hinzugefügt
- **Monatliche Trainingsfrequenz**: Neues Feature für ersten Donnerstag im Monat
- Frequenz-Auswahl im Admin-Bereich (wöchentlich/monatlich)
- Neue Datenbank-Spalte `run_frequency` in der `config` Tabelle
- Erweiterte Datumsgenerierung für monatliche Termine
- Automatische Berechnung der nächsten 6 ersten Donnerstage

### Geändert
- **Header-Text**: Von "Jeden Donnerstag" zu "Jeden ersten Donnerstag im Monat"
- **Datumsgenerierung**: Intelligente Erkennung zwischen wöchentlichen und monatlichen Terminen
- **Tage-bis-Run-Berechnung**: Korrekte Anzeige für monatliche Abstände
- Admin-Interface um Häufigkeits-Dropdown erweitert

### Technische Details
- `js/main.js`: Erweiterte `generateRunDates()` Funktion
- `js/admin.js`: Unterstützung für `runFrequency` in Settings
- `api.php`: Neue Config-Parameter `run_frequency` in `updateConfig` und `getConfig`
- `index.html`: Neues Dropdown-Feld für Häufigkeits-Auswahl
- Datenbank-Migration für kompatible MySQL-Versionen

### Datenbankänderungen
```sql
ALTER TABLE config ADD COLUMN `run_frequency` VARCHAR(20) DEFAULT 'weekly';
INSERT INTO config (`key`, `value`) VALUES ('run_frequency', 'monthly_first');
```

### Migration
1. SQL-Migration über phpMyAdmin ausgeführt
2. JavaScript- und PHP-Dateien aktualisiert
3. HTML-Template erweitert
4. Admin-Konfiguration auf "monthly_first" umgestellt

---

## [1.0.0] - 2025-01-XX

### Initial Release
- Skyrun Anmeldesystem für wöchentliche Trainings
- Teilnehmerverwaltung mit Warteliste
- Admin-Bereich mit Export/Import-Funktionen
- Wachen-Management für Feuerwehr Frankfurt
- E-Mail-Bestätigungen
- Gipfelbuch (Teilnahme-Statistiken)
- Responsive Design für Desktop und Mobile
- FTPS-Upload via GitHub Actions

### Features
- Anmeldung mit Name, E-Mail, Wache, Personenanzahl
- Automatische Wartelisten-Verwaltung
- CSV/JSON Export und Import
- Passwort-geschützter Admin-Bereich
- Konfigurierbare Teilnehmerzahl
- Backup-Automatisierung
