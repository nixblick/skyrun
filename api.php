<?php
// config.php einbinden
require_once 'config.php';

// Session härten und starten (PHP 8.4 kompatibel)
session_set_cookie_params([
    'lifetime'  => 0,
    'path'      => '/',
    'secure'    => true,
    'httponly'  => true,
    'samesite'  => 'Strict'
]);
ini_set('session.use_strict_mode', 1);
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
header('Access-Control-Allow-Origin: https://www.mein-computerfreund.de');
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
// PHP 8.1+ wirft MySQLi-Exceptions standardmäßig — explizit deaktivieren
mysqli_report(MYSQLI_REPORT_OFF);

// === Auto-Migrationen ===
// UNIQUE(date) → UNIQUE(date, building) — erlaubt Messeturm + Trianon am selben Tag
$indexCheck = $conn->query("SHOW INDEX FROM training_dates WHERE Key_name = 'unique_date'");
if ($indexCheck && $indexCheck->num_rows > 0) {
    $conn->query("ALTER TABLE training_dates DROP INDEX unique_date, ADD UNIQUE KEY unique_date_building (date, building)");
    error_log("Migration: unique_date → unique_date_building ausgeführt");
}

// === Hilfsfunktionen ===

function generateCsrfToken() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function validateCsrfToken() {
    $token = $_POST['csrf_token'] ?? '';
    return !empty($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrf() {
    if (!validateCsrfToken()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF-Token.']);
        exit;
    }
}

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
    // Dummy-Verify gegen Timing-Angriffe (Username-Enumeration verhindern)
    password_verify($password, '$2y$10$pXuKixXIATPuzG4dxZJNa.CZsmcUqPVyXPyMTDpzD5e7V5H2l3cSW');
    return false;
}

function isAdminAuthenticated() {
    if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
        return false;
    }

    // Session-Timeout: 30 Minuten Inaktivität
    $timeout = 30 * 60;
    if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity']) > $timeout) {
        // Session abgelaufen — aufräumen
        unset($_SESSION['admin_authenticated'], $_SESSION['admin_username'], $_SESSION['admin_last_activity'], $_SESSION['csrf_token']);
        return false;
    }

    // Letzte Aktivität aktualisieren
    $_SESSION['admin_last_activity'] = time();
    return true;
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
    $sensitiveFields = ['password', 'currentPassword', 'newPassword', 'confirmPassword', 'MAIL_PASSWORD', 'name', 'email', 'phone'];
    foreach ($sensitiveFields as $field) {
        if (isset($safePost[$field]) && !empty($safePost[$field])) {
            $val = $safePost[$field];
            if ($field === 'email') {
                // user@example.com → u***@e***.com
                $parts = explode('@', $val);
                $safePost[$field] = substr($parts[0], 0, 1) . '***@' . (isset($parts[1]) ? substr($parts[1], 0, 1) . '***' : '***');
            } elseif (in_array($field, ['name', 'phone'])) {
                $safePost[$field] = substr($val, 0, 2) . '***';
            } else {
                $safePost[$field] = '********';
            }
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
        $personCount = filter_var($_POST['personCount'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]);

        if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($date) || $personCount === false) {
            echo json_encode(['success' => false, 'message' => 'Pflichtfelder fehlen oder ungültig.']);
            exit;
        }

        // Datumsformat validieren (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Ungültiges Datumsformat.']);
            exit;
        }

        // Wache gegen bekannte Werte validieren (Whitelist aus DB + HTML-Optionen)
        $stationStmt = $conn->prepare("SELECT id FROM stations WHERE CONCAT(code, ' - ', name) = ? OR code = ? OR name = ?");
        $stationStmt->bind_param("sss", $station, $station, $station);
        $stationStmt->execute();
        if ($stationStmt->get_result()->num_rows === 0) {
            // Zusätzlich gegen Sonderwerte prüfen (OF - Langen etc.)
            $allowedExtra = ['OF - Langen'];
            if (!in_array($station, $allowedExtra, true)) {
                $station = '50 - Sonstige';
            }
        }
        $stationStmt->close();

        // Termin muss existieren und in der Zukunft liegen
        $dateCheckStmt = $conn->prepare("SELECT id FROM training_dates WHERE date = ? AND date >= CURDATE()");
        $dateCheckStmt->bind_param("s", $date);
        $dateCheckStmt->execute();
        if ($dateCheckStmt->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Kein gültiger Trainingstermin für dieses Datum.']);
            $dateCheckStmt->close();
            exit;
        }
        $dateCheckStmt->close();

        // Transaction starten um Race Condition bei Kapazitätsprüfung zu verhindern
        $conn->begin_transaction();

        try {
        $stmt = $conn->prepare("SELECT id FROM registrations WHERE email = ? AND date = ?");
        $stmt->bind_param("ss", $email, $date);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'E-Mail bereits für dieses Datum registriert.']);
            exit;
        }
        $stmt->close();

        // Kapazität mit Zeilensperre prüfen (FOR UPDATE verhindert parallele Überbuchung)
        $lockStmt = $conn->prepare("SELECT COALESCE(SUM(personCount), 0) as total FROM registrations WHERE date = ? AND waitlisted = 0 FOR UPDATE");
        $lockStmt->bind_param("s", $date);
        $lockStmt->execute();
        $participantsCount = (int)$lockStmt->get_result()->fetch_assoc()['total'];
        $lockStmt->close();

        $maxParticipants = getMaxParticipants();
        $isWaitlisted = ($participantsCount + $personCount) > $maxParticipants;

        if ($isWaitlisted && !$acceptWaitlist) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => "Nur noch " . ($maxParticipants - $participantsCount) . " Plätze frei."]);
            exit;
        }

        // Gebäude und Uhrzeit aus training_dates auslesen
        $buildingStmt = $conn->prepare("SELECT building, TIME_FORMAT(time, '%H:%i') as time FROM training_dates WHERE date = ?");
        $buildingStmt->bind_param("s", $date);
        $buildingStmt->execute();
        $buildingRow = $buildingStmt->get_result()->fetch_assoc();
        $building = $buildingRow['building'] ?? 'Messeturm';
        $trainingTime = $buildingRow['time'] ?? '19:00';
        $buildingStmt->close();

        $stmt = $conn->prepare("INSERT INTO registrations (name, email, phone, station, date, waitlisted, registrationTime, personCount, building) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        $waitlistedInt = $isWaitlisted ? 1 : 0;
        $stmt->bind_param("sssssiis", $name, $email, $phone, $station, $date, $waitlistedInt, $personCount, $building);

        if ($stmt->execute()) {
            $conn->commit();

            // E-Mail-Bestätigung senden, wenn aktiviert
            if (defined('MAIL_ENABLED') && MAIL_ENABLED) {
                sendRegistrationConfirmation($email, $name, $date, $personCount, $isWaitlisted, $station, $building, $trainingTime);
                error_log("Bestätigungs-E-Mail gesendet an: " . substr($email, 0, 1) . "***@*** für Datum: $date");
            }

            echo json_encode(['success' => true, 'message' => $isWaitlisted ? 'Auf Warteliste gesetzt.' : 'Anmeldung erfolgreich.', 'isWaitlisted' => $isWaitlisted]);
        } else {
            $conn->rollback();
            error_log("INSERT Fehler: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Registrierungsfehler.']);
        }
        $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Registrierung Transaction-Fehler: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Registrierungsfehler.']);
        }
        break;

    case 'getStats':
        $date = trim($_GET['date'] ?? '');
        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Kein oder ungültiges Datum angegeben.']);
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
        // Rate Limiting: file-basiert pro IP (nicht Session, da sonst umgehbar)
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitDir = sys_get_temp_dir() . '/skyrun_ratelimit';
        if (!is_dir($rateLimitDir)) @mkdir($rateLimitDir, 0700, true);
        $rateLimitFile = $rateLimitDir . '/' . md5($ip) . '.json';

        $attempts = ['count' => 0, 'last_attempt' => 0];
        if (file_exists($rateLimitFile)) {
            $attempts = json_decode(file_get_contents($rateLimitFile), true) ?: $attempts;
        }

        // Sperre abgelaufen? Zähler zurücksetzen
        if ($attempts['count'] >= 5 && (time() - $attempts['last_attempt']) > 60) {
            $attempts = ['count' => 0, 'last_attempt' => 0];
        }

        if ($attempts['count'] >= 5) {
            $waitSeconds = 60 - (time() - $attempts['last_attempt']);
            echo json_encode(['success' => false, 'message' => "Zu viele Fehlversuche. Bitte $waitSeconds Sekunden warten."]);
            break;
        }

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (verifyAdminLogin($username, $password)) {
            // Erfolg: Zähler löschen
            if (file_exists($rateLimitFile)) @unlink($rateLimitFile);
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_last_activity'] = time();
            $csrfToken = generateCsrfToken();
            echo json_encode(['success' => true, 'csrfToken' => $csrfToken]);
        } else {
            $attempts['count']++;
            $attempts['last_attempt'] = time();
            file_put_contents($rateLimitFile, json_encode($attempts), LOCK_EX);
            $remaining = 5 - $attempts['count'];
            $msg = $remaining > 0
                ? "Ungültige Zugangsdaten. Noch $remaining Versuch(e)."
                : "Zu viele Fehlversuche. Bitte 60 Sekunden warten.";
            echo json_encode(['success' => false, 'message' => $msg]);
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
        requireCsrf();
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $date = trim($_POST['date'] ?? '');
        if ($id <= 0 || empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Parameter.']);
            exit;
        }

        $conn->begin_transaction();
        $promoted = [];

        try {
            $stmt = $conn->prepare("SELECT waitlisted, personCount FROM registrations WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $removed = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$removed) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Teilnehmer nicht gefunden.']);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM registrations WHERE id = ?");
            $stmt->bind_param("i", $id);
            $deleted = $stmt->execute();
            $stmt->close();

            if (!$deleted) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Fehler beim Entfernen.']);
                exit;
            }

            if (!$removed['waitlisted']) {
                // Aktuelle Teilnehmerzahl nach dem Löschen ermitteln (innerhalb der Transaction gesperrt)
                $countStmt = $conn->prepare("SELECT COALESCE(SUM(personCount), 0) as total FROM registrations WHERE date = ? AND waitlisted = 0 FOR UPDATE");
                $countStmt->bind_param("s", $date);
                $countStmt->execute();
                $currentParticipants = (int)$countStmt->get_result()->fetch_assoc()['total'];
                $countStmt->close();

                $maxParticipants = getMaxParticipants();
                $availableSpots = $maxParticipants - $currentParticipants;

                if ($availableSpots > 0) {
                    $waitStmt = $conn->prepare("SELECT id, personCount FROM registrations WHERE date = ? AND waitlisted = 1 ORDER BY registrationTime ASC FOR UPDATE");
                    $waitStmt->bind_param("s", $date);
                    $waitStmt->execute();
                    $waitlistResult = $waitStmt->get_result();

                    while ($availableSpots > 0 && $waitlistEntry = $waitlistResult->fetch_assoc()) {
                        if ($waitlistEntry['personCount'] <= $availableSpots) {
                            $updateStmt = $conn->prepare("UPDATE registrations SET waitlisted = 0 WHERE id = ?");
                            $updateStmt->bind_param("i", $waitlistEntry['id']);
                            $updateStmt->execute();
                            $updateStmt->close();
                            $availableSpots -= $waitlistEntry['personCount'];
                            $promoted[] = $waitlistEntry['id'];
                        } else {
                            break;
                        }
                    }
                    $waitStmt->close();
                }
            }

            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
            error_log("removeParticipant Transaction-Fehler: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Fehler beim Entfernen.']);
            exit;
        }

        // E-Mails außerhalb der Transaction senden
        foreach ($promoted as $promotedId) {
            if (defined('MAIL_ENABLED') && MAIL_ENABLED) {
                $detailsStmt = $conn->prepare("SELECT name, email, station, personCount, building FROM registrations WHERE id = ?");
                $detailsStmt->bind_param("i", $promotedId);
                $detailsStmt->execute();
                $details = $detailsStmt->get_result()->fetch_assoc();
                $detailsStmt->close();

                if ($details) {
                    $timeStmt = $conn->prepare("SELECT TIME_FORMAT(time, '%H:%i') as time FROM training_dates WHERE date = ?");
                    $timeStmt->bind_param("s", $date);
                    $timeStmt->execute();
                    $timeRow = $timeStmt->get_result()->fetch_assoc();
                    $timeStmt->close();
                    $promoteTime = $timeRow['time'] ?? '19:00';

                    sendRegistrationConfirmation($details['email'], $details['name'], $date, $details['personCount'], false, $details['station'], $details['building'] ?? 'Messeturm', $promoteTime);
                    error_log("Auto-Hochstufungs-E-Mail gesendet an: " . substr($details['email'], 0, 1) . "***@*** für Datum: $date");
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Teilnehmer entfernt.']);
        break;

    case 'promoteFromWaitlist':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        requireCsrf();
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $date = trim($_POST['date'] ?? '');
        if ($id <= 0 || empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Parameter.']);
            exit;
        }

        $conn->begin_transaction();
        $details = null;

        try {
            $stmt = $conn->prepare("SELECT personCount FROM registrations WHERE id = ? AND waitlisted = 1 FOR UPDATE");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $promoteInfo = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$promoteInfo) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Nicht auf Warteliste.']);
                exit;
            }

            $personCountToPromote = $promoteInfo['personCount'];
            $maxParticipants = getMaxParticipants();

            $countStmt = $conn->prepare("SELECT COALESCE(SUM(personCount), 0) as total FROM registrations WHERE date = ? AND waitlisted = 0 FOR UPDATE");
            $countStmt->bind_param("s", $date);
            $countStmt->execute();
            $currentParticipants = (int)$countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();

            if (($currentParticipants + $personCountToPromote) > $maxParticipants) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Nicht genug Plätze.']);
                exit;
            }

            $stmt = $conn->prepare("UPDATE registrations SET waitlisted = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            $stmt->close();

            if (!$success) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Fehler beim Hochstufen.']);
                exit;
            }

            // Details für E-Mail vor Commit lesen
            $detailsStmt = $conn->prepare("SELECT name, email, station, personCount, building FROM registrations WHERE id = ?");
            $detailsStmt->bind_param("i", $id);
            $detailsStmt->execute();
            $details = $detailsStmt->get_result()->fetch_assoc();
            $detailsStmt->close();

            $conn->commit();

        } catch (Exception $e) {
            $conn->rollback();
            error_log("promoteFromWaitlist Transaction-Fehler: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Fehler beim Hochstufen.']);
            exit;
        }

        // E-Mail außerhalb der Transaction senden
        if ($details && defined('MAIL_ENABLED') && MAIL_ENABLED) {
            $timeStmt = $conn->prepare("SELECT TIME_FORMAT(time, '%H:%i') as time FROM training_dates WHERE date = ?");
            $timeStmt->bind_param("s", $date);
            $timeStmt->execute();
            $timeRow = $timeStmt->get_result()->fetch_assoc();
            $timeStmt->close();
            $promoteTime = $timeRow['time'] ?? '19:00';

            sendRegistrationConfirmation($details['email'], $details['name'], $date, $details['personCount'], false, $details['station'], $details['building'] ?? 'Messeturm', $promoteTime);
            error_log("Hochstufungs-E-Mail gesendet an: " . substr($details['email'], 0, 1) . "***@*** für Datum: $date");
        }

        echo json_encode(['success' => true, 'message' => 'Hochgestuft.']);
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
        requireCsrf();
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

        try {
        $checkStmt = $conn->prepare("SELECT id FROM registrations WHERE email = ? AND date = ?");
        $insertStmt = $conn->prepare("INSERT INTO registrations (name, email, phone, station, date, waitlisted, registrationTime, personCount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($importedData as $date => $groups) {
            // Datumsformat validieren (YYYY-MM-DD)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $skipCount += count($groups['participants'] ?? []) + count($groups['waitlist'] ?? []);
                continue;
            }

            $entries = array_merge($groups['participants'] ?? [], $groups['waitlist'] ?? []);
            foreach ($entries as $entry) {
                if (empty($entry['name']) || empty($entry['email']) || !filter_var($entry['email'], FILTER_VALIDATE_EMAIL)) {
                    $skipCount++;
                    continue;
                }
                $phone = $entry['phone'] ?? '';
                $station = $entry['station'] ?? '50 - Sonstige';
                $waitlisted = isset($entry['waitlisted']) ? (int)(bool)$entry['waitlisted'] : 0;
                $personCount = filter_var($entry['personCount'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10]]) ?: 1;
                $registrationTime = $entry['registrationTime'] ?? date('Y-m-d H:i:s');
                // registrationTime validieren (YYYY-MM-DD HH:MM:SS)
                if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $registrationTime)) {
                    $registrationTime = date('Y-m-d H:i:s');
                }

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
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Import Transaction-Fehler: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Importfehler.']);
        }
        break;

    case 'updateConfig':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        requireCsrf();
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
        if (isAdminAuthenticated()) {
            // Admin sieht alles
            $result = $conn->query("SELECT `key`, `value` FROM config");
            $config = [];
            while ($row = $result->fetch_assoc()) {
                $config[$row['key']] = ($row['key'] === 'max_participants' || $row['key'] === 'run_day') ? (int)$row['value'] : $row['value'];
            }
            $config += [
                'max_participants' => 25,
                'run_day' => 4,
                'run_time' => '19:00',
                'run_frequency' => 'weekly'
            ];
        } else {
            // Öffentlich: nur max_participants
            $config = ['max_participants' => (int)getMaxParticipants()];
        }
        echo json_encode(['success' => true, 'config' => $config]);
        break;

    case 'changePassword':
        if (!isAdminAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }
        requireCsrf();
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
        // Optionaler Building-Filter
        $filterBuilding = trim($_POST['building'] ?? '');

        if (!empty($filterBuilding) && in_array($filterBuilding, ['Messeturm', 'Trianon'])) {
            $stmt = $conn->prepare("
                SELECT station, COUNT(DISTINCT date) as participation_count
                FROM registrations
                WHERE waitlisted = 0 AND building = ?
                GROUP BY station
                ORDER BY participation_count DESC, station ASC
            ");
            $stmt->bind_param("s", $filterBuilding);
        } else {
            $stmt = $conn->prepare("
                SELECT station, COUNT(DISTINCT date) as participation_count
                FROM registrations
                WHERE waitlisted = 0
                GROUP BY station
                ORDER BY participation_count DESC, station ASC
            ");
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $peakBook = [];
        while ($row = $result->fetch_assoc()) {
            $peakBook[] = [
                'station' => $row['station'],
                'count'   => (int)$row['participation_count']
            ];
        }
        $stmt->close();
        echo json_encode(['success' => true, 'peakBook' => $peakBook]);
        break;

    case 'getTrainingDates':
        // Alle zukünftigen Termine abrufen (öffentlich)
        $stmt = $conn->prepare("SELECT id, date, TIME_FORMAT(time, '%H:%i') as time, building FROM training_dates WHERE date >= CURDATE() ORDER BY date ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        $dates = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = [
                'id'       => (int)$row['id'],
                'date'     => $row['date'],
                'time'     => $row['time'],
                'building' => $row['building']
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
        requireCsrf();
        $date     = trim($_POST['date'] ?? '');
        $time     = trim($_POST['time'] ?? '19:00');
        $building = trim($_POST['building'] ?? 'Messeturm');

        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Ungültiges Datum.']);
            exit;
        }
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Uhrzeit.']);
            exit;
        }
        if (!in_array($building, ['Messeturm', 'Trianon'])) {
            $building = 'Messeturm';
        }

        $stmt = $conn->prepare("INSERT INTO training_dates (date, time, building) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $date, $time, $building);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Termin hinzugefügt.', 'id' => $stmt->insert_id]);
        } else {
            if ($conn->errno == 1062) {
                echo json_encode(['success' => false, 'message' => 'Dieser Termin (Datum + Gebäude) existiert bereits.']);
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
        requireCsrf();
        $id       = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $date     = trim($_POST['date'] ?? '');
        $time     = trim($_POST['time'] ?? '19:00');
        $building = trim($_POST['building'] ?? 'Messeturm');

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
        if (!in_array($building, ['Messeturm', 'Trianon'])) {
            $building = 'Messeturm';
        }

        $stmt = $conn->prepare("UPDATE training_dates SET date = ?, time = ?, building = ? WHERE id = ?");
        $stmt->bind_param("sssi", $date, $time, $building, $id);
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
        requireCsrf();
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

    case 'sessionStatus':
        if (isAdminAuthenticated()) {
            echo json_encode(['success' => true, 'message' => 'Session aktiv.']);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session abgelaufen.']);
        }
        break;

    case 'smokeTest':
        // Token-Auth für automatisierte Tests (GitHub Actions)
        $token = $_GET['token'] ?? $_POST['token'] ?? '';
        if (!defined('BACKUP_TOKEN') || !hash_equals(BACKUP_TOKEN, $token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }

        $testDate = '2099-12-31';
        $testTime = '23:59';
        $testBuildings = ['Messeturm', 'Trianon'];
        $errors = [];
        $insertedIds = [];

        // 1. Termin(e) erstellen
        foreach ($testBuildings as $bld) {
            $stmt = $conn->prepare("INSERT INTO training_dates (date, time, building) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $testDate, $testTime, $bld);
            if ($stmt->execute()) {
                $insertedIds[] = $stmt->insert_id;
            } else {
                $errors[] = "INSERT $bld fehlgeschlagen: " . $conn->error;
            }
            $stmt->close();
        }

        // 2. Lesen prüfen
        $stmt = $conn->prepare("SELECT id, date, building FROM training_dates WHERE date = ?");
        $stmt->bind_param("s", $testDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $found = $result->num_rows;
        $stmt->close();

        if ($found !== count($insertedIds)) {
            $errors[] = "READ: erwartet " . count($insertedIds) . " Einträge, gefunden $found";
        }

        // 3. Aufräumen
        foreach ($insertedIds as $id) {
            $stmt = $conn->prepare("DELETE FROM training_dates WHERE id = ?");
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                $errors[] = "DELETE id=$id fehlgeschlagen: " . $conn->error;
            }
            $stmt->close();
        }

        // 4. Prüfen ob gelöscht
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM training_dates WHERE date = ?");
        $stmt->bind_param("s", $testDate);
        $stmt->execute();
        $remaining = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($remaining > 0) {
            $errors[] = "CLEANUP: $remaining Test-Einträge nicht gelöscht";
        }

        if (empty($errors)) {
            echo json_encode(['success' => true, 'message' => "Smoke-Test OK: INSERT($found), READ, DELETE erfolgreich."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Smoke-Test fehlgeschlagen', 'errors' => $errors]);
        }
        break;

    case 'createBackup':
        // Token-Auth fuer automatisierte Backups (GitHub Actions)
        $token = $_GET['token'] ?? $_POST['token'] ?? '';

        if ((!defined('BACKUP_TOKEN') || !hash_equals(BACKUP_TOKEN, $token)) && !isAdminAuthenticated()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
            exit;
        }

        try {
            $backupDir = __DIR__ . '/backups/';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $backupFile = $backupDir . 'skyrun_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $tables = ['registrations', 'users', 'config', 'stations', 'training_dates'];

            $output = "-- Skyrun Backup\n-- Date: " . date('Y-m-d H:i:s') . "\n\n";

            foreach ($tables as $table) {
                $createResult = $conn->query("SHOW CREATE TABLE `$table`");
                if ($createResult) {
                    $createRow = $createResult->fetch_assoc();
                    $output .= "\nDROP TABLE IF EXISTS `$table`;\n";
                    $output .= $createRow['Create Table'] . ";\n\n";
                }

                $dataResult = $conn->query("SELECT * FROM `$table`");
                if ($dataResult) {
                    while ($row = $dataResult->fetch_assoc()) {
                        $values = array_map(function($v) use ($conn) {
                            return $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
                        }, array_values($row));
                        $output .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                    }
                }
            }

            file_put_contents($backupFile, $output);

            // Alte Backups aufraeumen (behalte die letzten 30)
            $files = glob($backupDir . 'skyrun_backup_*.sql');
            if (count($files) > 30) {
                usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                foreach (array_slice($files, 30) as $old) {
                    unlink($old);
                }
            }

            $size = filesize($backupFile);
            echo json_encode(['success' => true, 'message' => "Backup erstellt: " . basename($backupFile) . " ($size Bytes)"]);
        } catch (Exception $e) {
            error_log("Backup-Fehler: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Backup fehlgeschlagen.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Ungültige Aktion.']);
}

// Verbindung schließen
$conn->close();

