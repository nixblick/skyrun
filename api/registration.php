<?php
/**
 * /api/registration.php
 * Version: 1.0.0
 * Registration-related API functions
 */

/**
 * Handler für Registrierungsaktionen
 */
function handleRegistrationAction($action, $conn) {
    switch ($action) {
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

            $maxParticipants = getMaxParticipants($conn);
            $participantsCount = countParticipantsForDate($conn, $date);
            $isWaitlisted = ($participantsCount + $personCount) > $maxParticipants;

            if ($isWaitlisted && !$acceptWaitlist) {
                echo json_encode(['success' => false, 'message' => "Nur noch " . ($maxParticipants - $participantsCount) . " Plätze frei."]);
                exit;
            }

            // Gebäude aus training_dates auslesen
            $buildingStmt = $conn->prepare("SELECT building FROM training_dates WHERE date = ?");
            $buildingStmt->bind_param("s", $date);
            $buildingStmt->execute();
            $buildingRow = $buildingStmt->get_result()->fetch_assoc();
            $building = $buildingRow['building'] ?? 'Messeturm';
            $buildingStmt->close();

            $stmt = $conn->prepare("INSERT INTO registrations (name, email, phone, station, date, waitlisted, registrationTime, personCount, building) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
            $waitlistedInt = $isWaitlisted ? 1 : 0;
            $stmt->bind_param("sssssiis", $name, $email, $phone, $station, $date, $waitlistedInt, $personCount, $building);

            if ($stmt->execute()) {
                // E-Mail-Bestätigung senden, wenn aktiviert
                if (defined('MAIL_ENABLED') && MAIL_ENABLED) {
                    sendRegistrationConfirmation($email, $name, $date, $personCount, $isWaitlisted, $station, $building);
                    
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
                'participants' => countParticipantsForDate($conn, $date),
                'waitlist' => countWaitlistedForDate($conn, $date),
                'maxParticipants' => getMaxParticipants($conn)
            ]);
            break;
    }
}
?>