<?php
/**
 * /api/admin.php
 * Version: 1.0.0
 * Admin-related API functions
 */

/**
 * Handler für Admin-Aktionen
 */
function handleAdminAction($action, $conn) {
    switch ($action) {
        case 'adminLogin':
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            if (verifyAdminLogin($conn, $username, $password)) {
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
                'maxParticipants' => getMaxParticipants($conn)
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
                $maxParticipants = getMaxParticipants($conn);
                $currentParticipants = countParticipantsForDate($conn, $date);
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
            $maxParticipants = getMaxParticipants($conn);
            $currentParticipants = countParticipantsForDate($conn, $date);

            if (($currentParticipants + $personCountToPromote) <= $maxParticipants) {
                $stmt = $conn->prepare("UPDATE registrations SET waitlisted = 0 WHERE id = ?");
                $stmt->bind_param("i", $id);
                $success = $stmt->execute();
                $stmt->close();
                
                if ($success) {
                    // Teilnehmer über Hochstufung informieren
                    if (defined('MAIL_ENABLED') && MAIL_ENABLED) {
                        // Zuerst Details des Teilnehmers abrufen
                        $detailsStmt = $conn->prepare("SELECT name, email, station, personCount, building FROM registrations WHERE id = ?");
                        $detailsStmt->bind_param("i", $id);
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

            if (!verifyAdminLogin($conn, $username, $currentPassword)) {
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
    }
}
?>