<?php
// config.php einbinden
require_once 'config.php';

// Session starten
session_start();

// CAPTCHA generieren
function generateCaptcha() {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['captcha_answer'] = $num1 + $num2;
    $_SESSION['captcha_time'] = time();
    return ['num1' => $num1, 'num2' => $num2];
}

// CAPTCHA validieren
function validateCaptcha($answer) {
    if (!isset($_SESSION['captcha_answer']) || !isset($_SESSION['captcha_time'])) {
        return false;
    }

    // CAPTCHA nach 10 Minuten abgelaufen
    if (time() - $_SESSION['captcha_time'] > 600) {
        return false;
    }

    $isValid = (int)$answer === (int)$_SESSION['captcha_answer'];

    // CAPTCHA nach Verwendung löschen
    unset($_SESSION['captcha_answer']);
    unset($_SESSION['captcha_time']);

    return $isValid;
}

// CORS-Header
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// OPTIONS-Anfragen für CORS Preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Datenbankverbindung
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log("Datenbankverbindung fehlgeschlagen: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Serverfehler bei der Datenbankverbindung.']);
    exit;
}
$conn->set_charset("utf8mb4");

// === Hilfsfunktionen ===

function getMaxParticipants() {
    global $conn;
    $defaultMax = 25;
    $stmt = $conn->prepare("SELECT `value` FROM config WHERE `key` = ?");
    $key = 'max_participants';
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['value'] ?? $defaultMax;
}

function countParticipantsForDate($date) {
    global $conn;
    $stmt = $conn->prepare("SELECT COALESCE(SUM(personCount), 0) as total FROM registrations WHERE date = ? AND waitlisted = 0");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    return (int)$result->fetch_assoc()['total'];
}

function countWaitlistedForDate($date) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM registrations WHERE date = ? AND waitlisted = 1");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    return (int)$result->fetch_assoc()['total'];
}

function verifyAdminLogin($username, $password) {
    global $conn;
    if (empty($username) || empty($password)) return false;
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return password_verify($password, $row['password_hash']);
    }
    return false;
}

function isAdminAuthenticated() {
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

// === Hauptlogik ===

$action = $_POST['action'] ?? $_GET['action'] ?? '';

error_log("===== API Call gestartet =====");
error_log("Aktion: '$action'");

// GET-Parameter loggen
if (!empty($_GET)) {
    $safeGet = $_GET;
    error_log("GET: " . print_r($safeGet, true));
}

// POST-Parameter loggen, aber sensible Daten maskieren
if (!empty($_POST)) {
    $safePost = $_POST;
    // Sensible Felder maskieren
    $sensitiveFields = ['password', 'currentPassword', 'newPassword', 'confirmPassword', 'MAIL_PASSWORD'];
    foreach ($sensitiveFields as $field) {
        if (isset($safePost[$field])) {
            $safePost[$field] = '********';
        }
    }
    error_log("POST: " . print_r($safePost, true));
}

switch ($action) {
    case 'getCaptcha':
        $captcha = generateCaptcha();
        echo json_encode(['success' => true, 'captcha' => $captcha]);
        break;

    case 'getStations':
        $result = $conn->query("SELECT id, code, name, type FROM stations ORDER BY type, sort_order");
        $stations = [];
        $groupedStations = ['BF' => [], 'FF' => [], 'Sonstige' => []];
        
        while ($row = $result->fetch_assoc()) {
            $groupedStations[$row['type']][] = $row;
        }
        
        echo json_encode([
            'success' => true, 
            'stations' => $groupedStations
        ]);
        break;

    case 'register':
        // Honeypot-Check
        if (!empty($_POST['website'])) {
            error_log("Spam erkannt: website-Feld gefüllt von IP: " . $_SERVER['REMOTE_ADDR']);
            echo json_encode(['success' => false, 'message' => 'Registrierung fehlgeschlagen.']);
            exit;
        }

        // CAPTCHA-Validierung
        $captchaAnswer = trim($_POST['captcha'] ?? '');
        if (!validateCaptcha($captchaAnswer)) {
            echo json_encode(['success' => false, 'message' => 'CAPTCHA falsch oder abgelaufen.']);
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $station = trim($_POST['station'] ?? '50 - Sonstige'); // Default Wache
        $date = trim($_POST['date'] ?? '');
        $acceptWaitlist = filter_var($_POST['acceptWaitlist'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $personCount = filter_var($_POST['personCount'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($date) || $personCount === false) {
            echo json_encode(['success' => false, 'message' => 'Pflichtfelder fehlen oder ungültig.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id FROM registrations WHERE email = ? AND date = ?");
        $stmt->bind_param("ss", $email, $date);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'E-Mail bereits für dieses Datum registriert.']);
            exit;
        }
        $stmt->close();

        $maxParticipants = getMaxParticipants();
        $participantsCount = countParticipantsForDate($date);
        $isWaitlisted = ($participantsCount + $personCount) > $maxParticipants;

        if ($isWaitlisted && !$acceptWaitlist) {
            echo json_encode(['success' => false, 'message' => "Nur noch " . ($maxParticipants - $participantsCount) . " Plätze frei."]);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO registrations (name, email, phone, station, date, waitlisted, registrationTime, personCount) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
        $waitlistedInt = $isWaitlisted ? 1 : 0;
        $stmt->bind_param("sssssii", $name, $email, $phone, $station, $date, $waitlistedInt, $personCount);
        
        if ($stmt->execute()) {
            // E-Mail-Bestätigung senden, wenn aktiviert
            if (defined('MAIL_ENABLED') && MAIL_ENABLED) {
                sendRegistrationConfirmation($email, $name, $date, $personCount, $isWaitlisted, $station);
                
                // Mail-Versuch loggen
                error_log("Bestätigungs-E-Mail gesendet an: $email für Datum: $date");
            }
            
            echo json_encode(['success' => true, 'message' => $isWaitlisted ? 'Auf Warteliste gesetzt.' : 'Anmeldung erfolgreich.', 'isWaitlisted' => $isWaitlisted]);
        } else {
            error_log("INSERT Fehler: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Registrierungsfehler.']);
        }
        $stmt->close();
        break;

    case 'getStats':
        $date = trim($_GET['date'] ?? '');
        if (empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Kein Datum angegeben']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'participants' => countParticipantsForDate($date),
            'waitlist' => countWaitlistedForDate($date),
            'maxParticipants' => getMaxParticipants()
        ]);
        break;

    case 'adminLogin':
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (verifyAdminLogin($username, $password)) {
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_username'] = $username; // Optional für spätere Nutzung
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ungültige Zugangsdaten.']);
        }
        break;

    case 'adminLogout':
        session_unset();
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    case 'getParticipants':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        $date = trim($_POST['date'] ?? '');
        if (empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Kein Datum angegeben.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, name, email, phone, station, waitlisted, registrationTime, personCount FROM registrations WHERE date = ? ORDER BY registrationTime ASC");
        $stmt->bind_param("s", $date);
        $stmt->execute();
        $result = $stmt->get_result();

        $participants = [];
        $waitlist = [];
        while ($row = $result->fetch_assoc()) {
            $row['waitlisted'] = (bool)$row['waitlisted'];
            if ($row['waitlisted']) $waitlist[] = $row;
            else $participants[] = $row;
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
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $date = trim($_POST['date'] ?? '');
        if ($id <= 0 || empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Parameter.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT waitlisted, personCount FROM registrations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $removed = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$removed) {
            echo json_encode(['success' => false, 'message' => 'Teilnehmer nicht gefunden.']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM registrations WHERE id = ?");
        $stmt->bind_param("i", $id);
        $deleted = $stmt->execute();
        $stmt->close();

        if ($deleted && !$removed['waitlisted']) {
            $maxParticipants = getMaxParticipants();
            $currentParticipants = countParticipantsForDate($date);
            $availableSpots = $maxParticipants - $currentParticipants;

            if ($availableSpots > 0) {
                $stmt = $conn->prepare("SELECT id, personCount FROM registrations WHERE date = ? AND waitlisted = 1 ORDER BY registrationTime ASC");
                $stmt->bind_param("s", $date);
                $stmt->execute();
                $waitlistResult = $stmt->get_result();

                while ($availableSpots > 0 && $waitlistEntry = $waitlistResult->fetch_assoc()) {
                    if ($waitlistEntry['personCount'] <= $availableSpots) {
                        $updateStmt = $conn->prepare("UPDATE registrations SET waitlisted = 0 WHERE id = ?");
                        $updateStmt->bind_param("i", $waitlistEntry['id']);
                        $updateStmt->execute();
                        $availableSpots -= $waitlistEntry['personCount'];
                        $updateStmt->close();
                    } else {
                        break;
                    }
                }
                $stmt->close();
            }
        }

        echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Teilnehmer entfernt.' : 'Fehler beim Entfernen.']);
        break;

    case 'promoteFromWaitlist':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $date = trim($_POST['date'] ?? '');
        if ($id <= 0 || empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Parameter.']);
            exit;
        }

        $stmt = $conn->prepare("SELECT personCount FROM registrations WHERE id = ? AND waitlisted = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $promoteInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$promoteInfo) {
            echo json_encode(['success' => false, 'message' => 'Nicht auf Warteliste.']);
            exit;
        }

        $personCountToPromote = $promoteInfo['personCount'];
        $maxParticipants = getMaxParticipants();
        $currentParticipants = countParticipantsForDate($date);

        if (($currentParticipants + $personCountToPromote) <= $maxParticipants) {
            $stmt = $conn->prepare("UPDATE registrations SET waitlisted = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            $stmt->close();
            
            if ($success) {
                // Teilnehmer über Hochstufung informieren
                if (defined('MAIL_ENABLED') && MAIL_ENABLED) {
                    // Zuerst Details des Teilnehmers abrufen
                    $detailsStmt = $conn->prepare("SELECT name, email, station, personCount FROM registrations WHERE id = ?");
                    $detailsStmt->bind_param("i", $id);
                    $detailsStmt->execute();
                    $details = $detailsStmt->get_result()->fetch_assoc();
                    $detailsStmt->close();
                    
                    if ($details) {
                        sendRegistrationConfirmation($details['email'], $details['name'], $date, $details['personCount'], false, $details['station']);
                        error_log("Hochstufungs-E-Mail gesendet an: {$details['email']} für Datum: $date");
                    }
                }
                
                echo json_encode(['success' => $success, 'message' => 'Hochgestuft.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Fehler beim Hochstufen.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Nicht genug Plätze.']);
        }
        break;

    case 'exportData':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        $result = $conn->query("SELECT id, name, email, phone, station, date, waitlisted, registrationTime, personCount FROM registrations ORDER BY date, registrationTime");
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $dateKey = $row['date'];
            if (!isset($data[$dateKey])) $data[$dateKey] = ['participants' => [], 'waitlist' => []];
            $row['waitlisted'] = (bool)$row['waitlisted'];
            if ($row['waitlisted']) $data[$dateKey]['waitlist'][] = $row;
            else $data[$dateKey]['participants'][] = $row;
        }
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    case 'importData':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        $jsonData = $_POST['data'] ?? '';
        if (empty($jsonData)) {
            echo json_encode(['success' => false, 'message' => 'Keine Daten.']);
            exit;
        }

        $importedData = json_decode($jsonData, true);
        if ($importedData === null || !is_array($importedData)) {
            echo json_encode(['success' => false, 'message' => 'Ungültiges JSON.']);
            exit;
        }

        $conn->begin_transaction();
        $importCount = 0;
        $skipCount = 0;

        $checkStmt = $conn->prepare("SELECT id FROM registrations WHERE email = ? AND date = ?");
        $insertStmt = $conn->prepare("INSERT INTO registrations (name, email, phone, station, date, waitlisted, registrationTime, personCount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($importedData as $date => $groups) {
            $entries = array_merge($groups['participants'] ?? [], $groups['waitlist'] ?? []);
            foreach ($entries as $entry) {
                if (empty($entry['name']) || empty($entry['email']) || !filter_var($entry['email'], FILTER_VALIDATE_EMAIL) || empty($date)) {
                    $skipCount++;
                    continue;
                }
                $phone = $entry['phone'] ?? '';
                $station = $entry['station'] ?? '50 - Sonstige';
                $waitlisted = isset($entry['waitlisted']) ? (int)(bool)$entry['waitlisted'] : 0;
                $personCount = filter_var($entry['personCount'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
                $registrationTime = $entry['registrationTime'] ?? date('Y-m-d H:i:s');

                $checkStmt->bind_param("ss", $entry['email'], $date);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows === 0) {
                    $insertStmt->bind_param("sssssisi", $entry['name'], $entry['email'], $phone, $station, $date, $waitlisted, $registrationTime, $personCount);
                    if ($insertStmt->execute()) $importCount++;
                    else $skipCount++;
                } else {
                    $skipCount++;
                }
            }
        }

        $checkStmt->close();
        $insertStmt->close();
        $conn->commit();
        echo json_encode(['success' => true, 'message' => "$importCount importiert, $skipCount übersprungen."]);
        break;

    case 'updateConfig':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        $maxParticipants = filter_var($_POST['maxParticipants'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $runDay = filter_var($_POST['runDay'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 6]]);
        $runTime = trim($_POST['runTime'] ?? '');
        $runFrequency = trim($_POST['runFrequency'] ?? 'weekly'); // Neu
        
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $runTime)) $runTime = null;
        if (!in_array($runFrequency, ['weekly', 'monthly_first'])) $runFrequency = 'weekly';

        if ($maxParticipants === null || $runDay === null || $runTime === null) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Werte.']);
            exit;
        }

        $stmt = $conn->prepare("REPLACE INTO config (`key`, `value`) VALUES (?, ?)");
        $keys = ['max_participants', 'run_day', 'run_time', 'run_frequency'];
        $values = [$maxParticipants, $runDay, $runTime, $runFrequency];
        foreach ($keys as $i => $key) {
            $stmt->bind_param("ss", $key, $values[$i]);
            $stmt->execute();
        }
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Konfiguration aktualisiert.']);
        break;

    case 'getConfig':
        $result = $conn->query("SELECT `key`, `value` FROM config");
        $config = [];
        while ($row = $result->fetch_assoc()) {
            $config[$row['key']] = ($row['key'] === 'max_participants' || $row['key'] === 'run_day') ? (int)$row['value'] : $row['value'];
        }
        // Default-Werte setzen
        $config += [
            'max_participants' => 25, 
            'run_day' => 4, 
            'run_time' => '19:00',
            'run_frequency' => 'weekly'  // Neuer Default
        ];
        echo json_encode(['success' => true, 'config' => $config]);
        break;

    case 'changePassword':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        $username = $_SESSION['admin_username'];
        $currentPassword = trim($_POST['currentPassword'] ?? '');
        $newPassword = trim($_POST['newPassword'] ?? '');

        if (empty($currentPassword) || empty($newPassword)) {
            echo json_encode(['success' => false, 'message' => 'Alle Felder ausfüllen.']);
            exit;
        }

        if (!verifyAdminLogin($username, $currentPassword)) {
            echo json_encode(['success' => false, 'message' => 'Aktuelles Passwort falsch.']);
            exit;
        }

        $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
        $stmt->bind_param("ss", $newHashedPassword, $username);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => $success, 'message' => $success ? 'Passwort geändert.' : 'Fehler beim Ändern.']);
        break;

    case 'getPeakBook':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        // Zähle eindeutige Wachen pro Datum (nur Teilnehmer, keine Warteliste)
        $stmt = $conn->prepare("
            SELECT station, COUNT(DISTINCT date) as participation_count
            FROM registrations
            WHERE waitlisted = 0
            GROUP BY station
            ORDER BY participation_count DESC, station ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $peakBook = [];
        while ($row = $result->fetch_assoc()) {
            $peakBook[] = [
                'station' => $row['station'],
                'count' => (int)$row['participation_count']
            ];
        }
        $stmt->close();
        echo json_encode(['success' => true, 'peakBook' => $peakBook]);
        break;

    case 'getTrainingDates':
        // Alle zukünftigen Termine abrufen (öffentlich)
        $stmt = $conn->prepare("SELECT id, date, TIME_FORMAT(time, '%H:%i') as time FROM training_dates WHERE date >= CURDATE() ORDER BY date ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        $dates = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = [
                'id' => (int)$row['id'],
                'date' => $row['date'],
                'time' => $row['time']
            ];
        }
        $stmt->close();
        echo json_encode(['success' => true, 'dates' => $dates]);
        break;

    case 'addTrainingDate':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '19:00');

        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Ungültiges Datum.']);
            exit;
        }
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Uhrzeit.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO training_dates (date, time) VALUES (?, ?)");
        $stmt->bind_param("ss", $date, $time);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Termin hinzugefügt.', 'id' => $stmt->insert_id]);
        } else {
            if ($conn->errno == 1062) {
                echo json_encode(['success' => false, 'message' => 'Dieses Datum existiert bereits.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Fehler beim Hinzufügen.']);
            }
        }
        $stmt->close();
        break;

    case 'updateTrainingDate':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $date = trim($_POST['date'] ?? '');
        $time = trim($_POST['time'] ?? '19:00');

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Ungültige ID.']);
            exit;
        }
        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Ungültiges Datum.']);
            exit;
        }
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Uhrzeit.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE training_dates SET date = ?, time = ? WHERE id = ?");
        $stmt->bind_param("ssi", $date, $time, $id);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $success, 'message' => $success ? 'Termin aktualisiert.' : 'Fehler beim Aktualisieren.']);
        break;

    case 'deleteTrainingDate':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Ungültige ID.']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM training_dates WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $success, 'message' => $success ? 'Termin gelöscht.' : 'Fehler beim Löschen.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ungültige Aktion.']);
}

// Verbindung schließen
$conn->close();
?>
