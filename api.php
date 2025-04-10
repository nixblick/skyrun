<?php
// config.php einbinden (stellt DB-Verbindung her, setzt Zeitzone und Error Handling)
require_once 'config.php';

// CORS-Header für lokale Entwicklung und API-Zugriff
header('Access-Control-Allow-Origin: *'); // Anpassen für Produktion (z.B. auf deine Domain)
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// OPTIONS-Anfragen für CORS Preflight beantworten
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Datenbankverbindung herstellen
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verbindung überprüfen
if ($conn->connect_error) {
    // Logge den Fehler, aber gib keine Details an den Client weiter
    error_log("Datenbankverbindung fehlgeschlagen: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Serverfehler bei der Datenbankverbindung.']);
    exit;
}

// Zeichensatz auf UTF-8 setzen
if (!$conn->set_charset("utf8mb4")) {
     error_log("Fehler beim Laden des Zeichensatzes utf8mb4: " . $conn->error);
     // Optional: Prozess beenden, wenn Zeichensatz kritisch ist
     // echo json_encode(['success' => false, 'message' => 'Serverfehler bei Zeichensatzkodierung.']);
     // exit;
}

// === Hilfsfunktionen ===

/**
 * Holt die maximale Teilnehmerzahl aus der config-Tabelle.
 * @return int Maximale Teilnehmerzahl (Standard: 25).
 */
function getMaxParticipants() {
    global $conn;
    $defaultMax = 25;
    $stmt = $conn->prepare("SELECT `value` FROM config WHERE `key` = ?");
    if (!$stmt) {
        error_log("Prepare failed (getMaxParticipants): (" . $conn->errno . ") " . $conn->error);
        return $defaultMax;
    }
    $key = 'max_participants';
    $stmt->bind_param("s", $key);
    if (!$stmt->execute()) {
        error_log("Execute failed (getMaxParticipants): (" . $stmt->errno . ") " . $stmt->error);
        return $defaultMax;
    }
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return (int)$row['value'];
    }
    return $defaultMax;
}

/**
 * Zählt die angemeldeten Teilnehmer (nicht auf Warteliste) für ein Datum.
 * @param string $date Datum im Format 'YYYY-MM-DD'.
 * @return int Anzahl der Teilnehmer.
 */
function countParticipantsForDate($date) {
    global $conn;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(personCount), 0) as total FROM registrations WHERE date = ? AND waitlisted = 0");
    if (!$stmt) {
        error_log("Prepare failed (countParticipantsForDate): (" . $conn->errno . ") " . $conn->error);
        return 0;
    }
    $stmt->bind_param("s", $date);
    if (!$stmt->execute()) {
        error_log("Execute failed (countParticipantsForDate): (" . $stmt->errno . ") " . $stmt->error);
        return 0;
    }
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return (int)$row['total'];
    }
    return 0;
}

/**
 * Zählt die Teilnehmer auf der Warteliste für ein Datum.
 * @param string $date Datum im Format 'YYYY-MM-DD'.
 * @return int Anzahl der Personen auf der Warteliste.
 */
function countWaitlistedForDate($date) {
    global $conn;
    // Zählt die Anzahl der Einträge (Gruppen) auf der Warteliste
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM registrations WHERE date = ? AND waitlisted = 1");
    if (!$stmt) {
        error_log("Prepare failed (countWaitlistedForDate): (" . $conn->errno . ") " . $conn->error);
        return 0;
    }
    $stmt->bind_param("s", $date);
    if (!$stmt->execute()) {
        error_log("Execute failed (countWaitlistedForDate): (" . $stmt->errno . ") " . $stmt->error);
        return 0;
    }
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return (int)$row['total'];
    }
    return 0;
}

/**
 * Überprüft Admin-Login-Daten gegen die users-Tabelle.
 * @param string $username Eingegebener Benutzername.
 * @param string $password Eingegebenes Passwort.
 * @return bool True, wenn Login gültig, sonst false.
 */
function verifyAdminLogin($username, $password) {
    global $conn;
    if (empty($username) || empty($password)) {
        return false;
    }

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE username = ?");
    if (!$stmt) {
        error_log("Prepare failed (verifyAdminLogin): (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("s", $username);
    if (!$stmt->execute()) {
        error_log("Execute failed (verifyAdminLogin): (" . $stmt->errno . ") " . $stmt->error);
        return false;
    }
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $hashedPassword = $row['password_hash'];
        return password_verify($password, $hashedPassword);
    }
    return false;
}


// === Hauptlogik ===

// Aktion aus POST oder GET holen
$action = $_POST['action'] ?? $_GET['action'] ?? '';


// ******************************************
// *** NEUES LOGGING ZUR FEHLERSUCHE HIER ***
// ******************************************
error_log("===== API Call gestartet =====");
error_log("Empfangene Aktion (aus GET/POST): '" . $action . "'");
if (!empty($_GET)) {
    error_log("GET Parameter: " . print_r($_GET, true));
}
if (!empty($_POST)) {
    error_log("POST Parameter: " . print_r($_POST, true));
}
// ******************************************
// *** ENDE LOGGING ***
// ******************************************



switch ($action) {
    case 'register':
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $acceptWaitlist = filter_var($_POST['acceptWaitlist'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $personCount = filter_var($_POST['personCount'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        // Validierung
        if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($date) || $personCount === false) {
            echo json_encode(['success' => false, 'message' => 'Bitte alle Pflichtfelder korrekt ausfüllen (Name, gültige E-Mail, Datum, Personenanzahl >= 1).']);
            exit;
        }

        // Prüfen, ob E-Mail bereits registriert ist für dieses Datum
        $stmt = $conn->prepare("SELECT id FROM registrations WHERE email = ? AND date = ?");
        $stmt->bind_param("ss", $email, $date);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Diese E-Mail-Adresse ist für dieses Datum bereits registriert.']);
            exit;
        }
        $stmt->close(); // Wichtig: Statement schließen

        $maxParticipants = getMaxParticipants();
        $participantsCount = countParticipantsForDate($date);
        $isWaitlisted = ($participantsCount + $personCount) > $maxParticipants;

        if ($isWaitlisted && !$acceptWaitlist) {
            echo json_encode([
                'success' => false,
                'message' => "Für dieses Datum sind leider nur noch " . ($maxParticipants - $participantsCount) . " Plätze frei (Ihre Anfrage: $personCount). Bitte wählen Sie ein anderes Datum oder aktivieren Sie die Warteliste-Option."
            ]);
            exit;
        }

        // Neue Registrierung hinzufügen
        $stmt = $conn->prepare("INSERT INTO registrations (name, email, phone, date, waitlisted, registrationTime, personCount) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $waitlistedInt = $isWaitlisted ? 1 : 0; // Boolean in Integer umwandeln für DB
        $stmt->bind_param("ssssii", $name, $email, $phone, $date, $waitlistedInt, $personCount);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => $isWaitlisted ? 'Sie wurden erfolgreich auf die Warteliste gesetzt.' : 'Ihre Anmeldung wurde erfolgreich registriert.',
                'isWaitlisted' => $isWaitlisted
            ]);
        } else {
             error_log("Fehler bei der Registrierung (INSERT): (" . $stmt->errno . ") " . $stmt->error);
             echo json_encode(['success' => false, 'message' => 'Fehler bei der Registrierung. Bitte versuchen Sie es später erneut.']);
        }
        $stmt->close();
        break;

    case 'getStats':
        $date = trim($_GET['date'] ?? '');
        if (empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Kein Datum angegeben']);
            exit;
        }

        $participantsCount = countParticipantsForDate($date);
        $waitlistCount = countWaitlistedForDate($date); // Anzahl der Einträge auf der Warteliste
        $maxParticipants = getMaxParticipants();

        echo json_encode([
            'success' => true,
            'participants' => $participantsCount,
            'waitlist' => $waitlistCount,
            'maxParticipants' => $maxParticipants
        ]);
        break;

    case 'adminLogin':
        // **Verwendet jetzt die users Tabelle**
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (verifyAdminLogin($username, $password)) {
            echo json_encode(['success' => true]);
        } else {
            // Generische Fehlermeldung aus Sicherheitsgründen
            echo json_encode(['success' => false, 'message' => 'Ungültiger Benutzername oder Passwort.']);
        }
        break;

    case 'getParticipants':
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $date = trim($_POST['date'] ?? '');

        if (!verifyAdminLogin($username, $password)) {
             echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
             exit;
        }

        if (empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Kein Datum angegeben.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, name, email, phone, waitlisted, registrationTime, personCount FROM registrations WHERE date = ? ORDER BY registrationTime ASC");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();

        $participants = [];
        $waitlist = [];
        while ($row = $result->fetch_assoc()) {
            // Boolean für JSON umwandeln
            $row['waitlisted'] = (bool)$row['waitlisted'];
            if ($row['waitlisted']) {
                $waitlist[] = $row;
            } else {
                $participants[] = $row;
            }
        }
        $stmt->close();

        echo json_encode([
            'success' => true,
            'participants' => $participants,
            'waitlist' => $waitlist,
            'maxParticipants' => getMaxParticipants()
        ]);
        break;

    case 'removeParticipant':
         $username = trim($_POST['username'] ?? '');
         $password = trim($_POST['password'] ?? '');
         $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
         $date = trim($_POST['date'] ?? ''); // Datum wird benötigt, um Nachrücker für den richtigen Tag zu ermitteln

        if (!verifyAdminLogin($username, $password)) {
             echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
             exit;
        }

        if ($id === false || $id <= 0 || empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige oder fehlende Parameter (ID, Datum).']);
            exit;
        }

        // Infos über den zu löschenden Teilnehmer holen (war er auf Warteliste?)
        $stmt = $conn->prepare("SELECT waitlisted, personCount FROM registrations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $removedParticipantInfo = $result->fetch_assoc();
        $stmt->close();

        if (!$removedParticipantInfo) {
             echo json_encode(['success' => false, 'message' => 'Teilnehmer nicht gefunden.']);
             exit;
        }

        $wasWaitlisted = (bool)$removedParticipantInfo['waitlisted'];

        // Teilnehmer löschen
        $stmt = $conn->prepare("DELETE FROM registrations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $deleted = $stmt->execute();
        $stmt->close();

        if (!$deleted) {
             error_log("Fehler beim Löschen (DELETE): ID $id");
             echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen des Teilnehmers.']);
             exit;
        }

        // --- Nachrückverfahren starten, wenn ein regulärer Teilnehmer gelöscht wurde ---
        if (!$wasWaitlisted) {
            $maxParticipants = getMaxParticipants();
            $currentParticipants = countParticipantsForDate($date);
            $availableSpots = $maxParticipants - $currentParticipants;

            if ($availableSpots > 0) {
                // Hole potenzielle Nachrücker von der Warteliste für dieses Datum
                 $stmt = $conn->prepare("SELECT id, personCount FROM registrations WHERE date = ? AND waitlisted = 1 ORDER BY registrationTime ASC");
                 $stmt->bind_param("s", $date);
                 $stmt->execute();
                 $waitlistResult = $stmt->get_result();

                 while ($availableSpots > 0 && $waitlistEntry = $waitlistResult->fetch_assoc()) {
                     if ($waitlistEntry['personCount'] <= $availableSpots) {
                         // Hochstufen
                         $updateStmt = $conn->prepare("UPDATE registrations SET waitlisted = 0 WHERE id = ?");
                         $updateStmt->bind_param("i", $waitlistEntry['id']);
                         if ($updateStmt->execute()) {
                              $availableSpots -= $waitlistEntry['personCount'];
                              // Optional: Benachrichtigung für hochgestuften Teilnehmer
                              error_log("Teilnehmer ID " . $waitlistEntry['id'] . " für Datum $date hochgestuft.");
                         } else {
                              error_log("Fehler beim Hochstufen von ID " . $waitlistEntry['id'] . ": (" . $updateStmt->errno . ") " . $updateStmt->error);
                         }
                         $updateStmt->close();
                     } else {
                         // Passt nicht mehr in die Lücke, Schleife beenden
                         break;
                     }
                 }
                 $stmt->close();
            }
        }

        echo json_encode(['success' => true, 'message' => 'Teilnehmer erfolgreich entfernt.']);
        break;

    case 'promoteFromWaitlist':
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $date = trim($_POST['date'] ?? '');

        if (!verifyAdminLogin($username, $password)) {
             echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
             exit;
        }

        if ($id === false || $id <= 0 || empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige oder fehlende Parameter (ID, Datum).']);
            exit;
        }

        // Infos über den hochzustufenden Teilnehmer holen (Anzahl Personen)
        $stmt = $conn->prepare("SELECT personCount FROM registrations WHERE id = ? AND waitlisted = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $promoteInfo = $result->fetch_assoc();
        $stmt->close();

        if (!$promoteInfo) {
             echo json_encode(['success' => false, 'message' => 'Teilnehmer nicht auf der Warteliste gefunden oder bereits Teilnehmer.']);
             exit;
        }
        $personCountToPromote = $promoteInfo['personCount'];

        // Prüfen, ob genug Plätze frei sind
        $maxParticipants = getMaxParticipants();
        $currentParticipants = countParticipantsForDate($date);

        if (($currentParticipants + $personCountToPromote) <= $maxParticipants) {
            // Hochstufen
            $stmt = $conn->prepare("UPDATE registrations SET waitlisted = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                 echo json_encode(['success' => true, 'message' => 'Teilnehmer erfolgreich hochgestuft.']);
                 error_log("Teilnehmer ID $id für Datum $date manuell hochgestuft.");
            } else {
                 error_log("Fehler beim manuellen Hochstufen von ID $id: (" . $stmt->errno . ") " . $stmt->error);
                 echo json_encode(['success' => false, 'message' => 'Fehler beim Hochstufen.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Nicht genügend freie Plätze (' . ($maxParticipants - $currentParticipants) . ' verfügbar) zum Hochstufen von ' . $personCountToPromote . ' Person(en).']);
        }
        break;

    case 'exportData': // Exportiert ALLE Registrierungen
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!verifyAdminLogin($username, $password)) {
             echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
             exit;
        }

        $result = $conn->query("SELECT id, name, email, phone, date, waitlisted, registrationTime, personCount FROM registrations ORDER BY date, registrationTime");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $dateKey = $row['date'];
            if (!isset($data[$dateKey])) {
                $data[$dateKey] = ['participants' => [], 'waitlist' => []];
            }
            $row['waitlisted'] = (bool)$row['waitlisted']; // Boolean für JSON
            if ($row['waitlisted']) {
                $data[$dateKey]['waitlist'][] = $row;
            } else {
                 $data[$dateKey]['participants'][] = $row;
            }
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'importData':
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $jsonData = $_POST['data'] ?? '';

        if (!verifyAdminLogin($username, $password)) {
             echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
             exit;
        }

        if (empty($jsonData)) {
            echo json_encode(['success' => false, 'message' => 'Keine Daten zum Importieren übermittelt.']);
            exit;
        }

        $importedData = json_decode($jsonData, true);

        if ($importedData === null || !is_array($importedData)) {
            echo json_encode(['success' => false, 'message' => 'Ungültiges JSON-Format oder leere Daten.']);
            exit;
        }

        $conn->begin_transaction();
        $importCount = 0;
        $skipCount = 0;

        try {
            // Vorbereiten der Statements außerhalb der Schleife
            $checkStmt = $conn->prepare("SELECT id FROM registrations WHERE email = ? AND date = ?");
            $insertStmt = $conn->prepare("INSERT INTO registrations (name, email, phone, date, waitlisted, registrationTime, personCount) VALUES (?, ?, ?, ?, ?, ?, ?)");

            // Iteriere durch die Datenstruktur (Daten sind nach Datum gruppiert)
            foreach ($importedData as $date => $groups) {
                // Kombiniere Teilnehmer und Warteliste für einfachere Iteration
                $entries = array_merge($groups['participants'] ?? [], $groups['waitlist'] ?? []);

                foreach ($entries as $entry) {
                     // Validierung grundlegender Felder
                     if (empty($entry['name']) || empty($entry['email']) || !filter_var($entry['email'], FILTER_VALIDATE_EMAIL) || empty($date)) {
                         error_log("Import übersprungen: Ungültiger Eintrag - " . json_encode($entry));
                         $skipCount++;
                         continue;
                     }

                     // Standardwerte setzen, falls Felder fehlen
                     $phone = $entry['phone'] ?? '';
                     $waitlisted = isset($entry['waitlisted']) ? (int)(bool)$entry['waitlisted'] : 0; // Sicherstellen, dass es 0 oder 1 ist
                     $personCount = filter_var($entry['personCount'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                     if ($personCount === false) $personCount = 1;
                     $registrationTime = $entry['registrationTime'] ?? date('Y-m-d H:i:s');
                     // Einfache Validierung des Zeitformats (optional, aber empfohlen)
                     $d = DateTime::createFromFormat('Y-m-d H:i:s', $registrationTime);
                     if (!$d || $d->format('Y-m-d H:i:s') !== $registrationTime) {
                         $registrationTime = date('Y-m-d H:i:s'); // Fallback auf aktuelle Zeit
                     }

                    // Prüfen, ob dieser Eintrag (E-Mail + Datum) bereits existiert
                    $checkStmt->bind_param("ss", $entry['email'], $date);
                    $checkStmt->execute();
                    if ($checkStmt->get_result()->num_rows === 0) {
                        // Nicht vorhanden -> Einfügen
                         $insertStmt->bind_param("ssssisi", $entry['name'], $entry['email'], $phone, $date, $waitlisted, $registrationTime, $personCount);
                         if ($insertStmt->execute()) {
                             $importCount++;
                         } else {
                              error_log("Importfehler (INSERT): (" . $insertStmt->errno . ") " . $insertStmt->error . " - Data: " . json_encode($entry));
                              // Entscheiden, ob die Transaktion abgebrochen werden soll
                              // throw new Exception("Fehler beim Einfügen eines Datensatzes.");
                              $skipCount++; // Zähle als übersprungen
                         }
                    } else {
                        // Bereits vorhanden -> Überspringen
                        $skipCount++;
                    }
                }
            }

            $checkStmt->close();
            $insertStmt->close();

            $conn->commit();
            echo json_encode(['success' => true, 'message' => "$importCount Datensätze erfolgreich importiert, $skipCount übersprungen (bereits vorhanden oder ungültig)."]);

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Import fehlgeschlagen (Transaktion Rollback): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Fehler beim Importieren: ' . $e->getMessage()]);
        }
        break;

    case 'updateConfig':
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!verifyAdminLogin($username, $password)) {
             echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
             exit;
        }

        // Parameter validieren
        $maxParticipants = filter_var($_POST['maxParticipants'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $runDay = filter_var($_POST['runDay'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 6]]);
        $runTime = trim($_POST['runTime'] ?? '');
        // Einfache Zeitvalidierung (HH:MM)
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $runTime)) {
            $runTime = null; // Ungültige Zeit
        }

        if ($maxParticipants === null || $runDay === null || $runTime === null) {
             echo json_encode(['success' => false, 'message' => 'Ungültige Konfigurationswerte übermittelt.']);
             exit;
        }

        // REPLACE INTO ist praktisch: Fügt ein oder aktualisiert, wenn Key existiert.
        $stmt = $conn->prepare("REPLACE INTO config (`key`, `value`) VALUES (?, ?)");
        $keyMax = 'max_participants';
        $keyDay = 'run_day';
        $keyTime = 'run_time';

        $stmt->bind_param("ss", $keyMax, $maxParticipants);
        $stmt->execute();
        $stmt->bind_param("ss", $keyDay, $runDay);
        $stmt->execute();
        $stmt->bind_param("ss", $keyTime, $runTime);
        $stmt->execute();

        if ($stmt->error) {
            error_log("Fehler beim Aktualisieren der Konfiguration: (" . $stmt->errno . ") " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern der Konfiguration.']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Konfiguration erfolgreich aktualisiert.']);
        }
        $stmt->close();
        break;

    case 'getConfig':
        // Kein Login erforderlich, da diese Infos auch im Frontend angezeigt werden (indirekt über getStats)
        $result = $conn->query("SELECT `key`, `value` FROM config");
        $config = [];
        while ($row = $result->fetch_assoc()) {
            // Konvertiere numerische Werte für JSON
            if ($row['key'] === 'max_participants' || $row['key'] === 'run_day') {
                $config[$row['key']] = (int)$row['value'];
            } else {
                 $config[$row['key']] = $row['value'];
            }
        }
        // Standardwerte hinzufügen, falls nicht in DB
        if (!isset($config['max_participants'])) $config['max_participants'] = 25;
        if (!isset($config['run_day'])) $config['run_day'] = 4; // Donnerstag
        if (!isset($config['run_time'])) $config['run_time'] = '19:00';

        echo json_encode(['success' => true, 'config' => $config]);
        break;

    case 'changePassword':
        $username = trim($_POST['username'] ?? '');
        $currentPassword = trim($_POST['currentPassword'] ?? '');
        $newPassword = trim($_POST['newPassword'] ?? '');

        if (empty($username) || empty($currentPassword) || empty($newPassword)) {
            echo json_encode(['success' => false, 'message' => 'Alle Felder müssen ausgefüllt sein.']);
            exit;
        }

        // Überprüfe das *aktuelle* Passwort
        if (!verifyAdminLogin($username, $currentPassword)) {
            echo json_encode(['success' => false, 'message' => 'Aktuelles Passwort ist falsch.']);
            exit;
        }

        // Hash das *neue* Passwort
        // Verwende die Standard-Optionen von PHP, die sicher sind
        $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        if ($newHashedPassword === false) {
             error_log("Fehler beim Hashen des neuen Passworts für User: $username");
             echo json_encode(['success' => false, 'message' => 'Fehler beim Verarbeiten des neuen Passworts.']);
             exit;
        }

        // Speichere den neuen Hash in der users Tabelle
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
        $stmt->bind_param("ss", $newHashedPassword, $username);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Passwort erfolgreich geändert.']);
        } else {
             error_log("Fehler beim Ändern des Passworts in DB für User $username: (" . $stmt->errno . ") " . $stmt->error);
             echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern des neuen Passworts.']);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ungültige oder fehlende Aktion.']);
}

// Datenbankverbindung schließen
$conn->close();

?>