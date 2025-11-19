# Skyrun Update - Individuelle Trainingstermine

## Was wurde geändert?

- **Termine werden nicht mehr automatisch berechnet** (kein "1. Donnerstag im Monat" mehr)
- **Du legst Termine manuell im Admin-Bereich fest** (mit individueller Uhrzeit pro Termin)
- **Countdown zeigt jetzt das Datum** ("22 Tage bis 11.12." statt nur "22 Tage")
- **Info-Text geändert** zu "Termine siehe Auswahlfeld"

---

## Installation

### 1. Datenbank-Migration (phpMyAdmin)

1. Öffne phpMyAdmin bei goneo https://mysql-w51.ssl.goneo.de/
2. Wähle deine Datenbank aus
3. Klicke auf den Tab **"SQL"**
4. Füge folgenden Code ein und klicke auf **"OK"**:

```sql
-- Neue Tabelle für Trainingstermine erstellen
CREATE TABLE IF NOT EXISTS `training_dates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `time` time NOT NULL DEFAULT '19:00:00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ersten Termin eintragen (11.12.2025)
INSERT INTO `training_dates` (`date`, `time`) VALUES ('2025-12-11', '19:00:00');
```

### 2. Dateien deployen

Die Dateien werden **automatisch beim Push ins Repository** auf das Hosting-Paket kopiert.

```bash
git add .
git commit -m "Individuelle Trainingstermine implementiert"
git push
```

Geänderte Dateien:
- `api.php`
- `index.html`
- `js/main.js`
- `js/admin.js`

---

## Benutzung

### Termine verwalten

1. Gehe auf die Skyrun-Seite
2. Klicke unten auf "Admin-Bereich"
3. Logge dich ein
4. Klicke auf den Tab **"Termine"**

Dort kannst du:
- **Neue Termine hinzufügen** (Datum + Uhrzeit auswählen)
- **Termine löschen** (auf "Löschen" klicken)

### Wichtig

- Vergangene Termine werden automatisch ausgeblendet
- Du solltest immer 3-5 zukünftige Termine eingetragen haben
- Jeder Termin kann eine eigene Uhrzeit haben

---

## Technische Details

### Neue API-Endpunkte

- `getTrainingDates` - Alle zukünftigen Termine abrufen
- `addTrainingDate` - Neuen Termin hinzufügen (Admin)
- `updateTrainingDate` - Termin bearbeiten (Admin)
- `deleteTrainingDate` - Termin löschen (Admin)

### Datenbank-Struktur

Neue Tabelle `training_dates`:
| Spalte | Typ | Beschreibung |
|--------|-----|--------------|
| id | int | Auto-Increment |
| date | date | Datum (YYYY-MM-DD) |
| time | time | Uhrzeit (HH:MM:SS) |
| created_at | timestamp | Erstellungszeitpunkt |

---

## Bei Problemen

Falls nach dem Update keine Termine angezeigt werden:
1. Prüfe in phpMyAdmin ob die Tabelle `training_dates` existiert
2. Prüfe ob mindestens ein Termin eingetragen ist
3. Browser-Cache leeren (Strg+F5)
