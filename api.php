<?php
// config.php einbinden
require_once 'config.php';

// Session starten
session_start();

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
if (!empty($_GET)) error_log("GET: " . print_r($_GET, true));
if (!empty($_POST)) error_log("POST: " . print_r($_POST, true));

switch ($action) {
    case 'register':
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
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

        $stmt = $conn->prepare("INSERT INTO registrations (name, email, phone, date, waitlisted, registrationTime, personCount) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
        $waitlistedInt = $isWaitlisted ? 1 : 0;
        $stmt->bind_param("ssssii", $name, $email, $phone, $date, $waitlistedInt, $personCount);
        if ($stmt->execute()) {
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

        $stmt = $conn->prepare("SELECT id, name, email, phone, waitlisted, registrationTime, personCount FROM registrations WHERE date = ? ORDER BY registrationTime ASC");
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
            echo json_encode(['success' => $success, 'message' => $success ? 'Hochgestuft.' : 'Fehler beim Hochstufen.']);
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
        $result = $conn->query("SELECT id, name, email, phone, date, waitlisted, registrationTime, personCount FROM registrations ORDER BY date, registrationTime");
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
        $insertStmt = $conn->prepare("INSERT INTO registrations (name, email, phone, date, waitlisted, registrationTime, personCount) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($importedData as $date => $groups) {
            $entries = array_merge($groups['participants'] ?? [], $groups['waitlist'] ?? []);
            foreach ($entries as $entry) {
                if (empty($entry['name']) || empty($entry['email']) || !filter_var($entry['email'], FILTER_VALIDATE_EMAIL) || empty($date)) {
                    $skipCount++;
                    continue;
                }
                $phone = $entry['phone'] ?? '';
                $waitlisted = isset($entry['waitlisted']) ? (int)(bool)$entry['waitlisted'] : 0;
                $personCount = filter_var($entry['personCount'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
                $registrationTime = $entry['registrationTime'] ?? date('Y-m-d H:i:s');

                $checkStmt->bind_param("ss", $entry['email'], $date);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows === 0) {
                    $insertStmt->bind_param("ssssisi", $entry['name'], $entry['email'], $phone, $date, $waitlisted, $registrationTime, $personCount);
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
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $runTime)) $runTime = null;

        if ($maxParticipants === null || $runDay === null || $runTime === null) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Werte.']);
            exit;
        }

        $stmt = $conn->prepare("REPLACE INTO config (`key`, `value`) VALUES (?, ?)");
        $keys = ['max_participants', 'run_day', 'run_time'];
        $values = [$maxParticipants, $runDay, $runTime];
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
        $config += ['max_participants' => 25, 'run_day' => 4, 'run_time' => '19:00'];
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

    default:
        echo json_encode(['success' => false, 'message' => 'Ungültige Aktion.']);
}

// Verbindung schließen
$conn->close();
?>