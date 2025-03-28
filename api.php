<?php
// Grundlegende Sicherheitsprüfungen
header('Content-Type: application/json');

// Konfiguration
$dataFile = 'registrations.json';
$adminPassword = 'skyrun2025'; // In der Praxis ein sicheres Passwort verwenden

// Hilfsfunktionen
function loadData() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        file_put_contents($dataFile, '{}');
        return [];
    }
    $data = file_get_contents($dataFile);
    return json_decode($data, true) ?: [];
}

function saveData($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}

// Endpoint für verschiedene Aktionen
$action = isset($_POST['action']) ? $_POST['action'] : '';

// CORS-Header für lokale Entwicklung
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS-Anfragen für CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Aktionen verarbeiten
switch ($action) {
    case 'register':
        // Neue Anmeldung hinzufügen
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $date = isset($_POST['date']) ? trim($_POST['date']) : '';
        $acceptWaitlist = isset($_POST['acceptWaitlist']) ? ($_POST['acceptWaitlist'] === 'true') : false;
        
        if (empty($name) || empty($email) || empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Bitte alle Pflichtfelder ausfüllen']);
            exit;
        }
        
        $data = loadData();
        
        // Prüfen, ob E-Mail bereits registriert ist
        if (isset($data[$date])) {
            foreach ($data[$date] as $reg) {
                if ($reg['email'] === $email) {
                    echo json_encode(['success' => false, 'message' => 'Diese E-Mail-Adresse ist für dieses Datum bereits registriert']);
                    exit;
                }
            }
        } else {
            $data[$date] = [];
        }
        
        // Anzahl der Teilnehmer zählen
        $participantsCount = 0;
        if (isset($data[$date])) {
            foreach ($data[$date] as $reg) {
                if (!$reg['waitlisted']) {
                    $participantsCount++;
                }
            }
        }
        
        // Prüfen, ob auf Warteliste
        $isWaitlisted = $participantsCount >= 25;
        if ($isWaitlisted && !$acceptWaitlist) {
            echo json_encode([
                'success' => false, 
                'message' => 'Für dieses Datum sind bereits alle 25 Plätze belegt. Bitte wählen Sie ein anderes Datum oder aktivieren Sie die Warteliste-Option'
            ]);
            exit;
        }
        
        // Neue Registrierung hinzufügen
        $registration = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'date' => $date,
            'waitlisted' => $isWaitlisted,
            'registrationTime' => date('c')
        ];
        
        $data[$date][] = $registration;
        saveData($data);
        
        echo json_encode([
            'success' => true, 
            'message' => $isWaitlisted ? 'Sie wurden erfolgreich auf die Warteliste gesetzt' : 'Ihre Anmeldung wurde erfolgreich registriert',
            'isWaitlisted' => $isWaitlisted
        ]);
        break;
        
    case 'getStats':
        // Statistiken abrufen
        $date = isset($_GET['date']) ? trim($_GET['date']) : '';
        
        if (empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Kein Datum angegeben']);
            exit;
        }
        
        $data = loadData();
        $participantsCount = 0;
        $waitlistCount = 0;
        
        if (isset($data[$date])) {
            foreach ($data[$date] as $reg) {
                if ($reg['waitlisted']) {
                    $waitlistCount++;
                } else {
                    $participantsCount++;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'participants' => $participantsCount,
            'waitlist' => $waitlistCount
        ]);
        break;
        
    case 'adminLogin':
        // Admin-Login
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        if ($password === $adminPassword) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Falsches Passwort']);
        }
        break;
        
    case 'getParticipants':
        // Teilnehmer abrufen
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $date = isset($_POST['date']) ? trim($_POST['date']) : '';
        
        if ($password !== $adminPassword) {
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
            exit;
        }
        
        if (empty($date)) {
            echo json_encode(['success' => false, 'message' => 'Kein Datum angegeben']);
            exit;
        }
        
        $data = loadData();
        $participants = [];
        $waitlist = [];
        
        if (isset($data[$date])) {
            foreach ($data[$date] as $reg) {
                if ($reg['waitlisted']) {
                    $waitlist[] = $reg;
                } else {
                    $participants[] = $reg;
                }
            }
        }
        
        // Sortieren nach Anmeldezeit
        usort($participants, function($a, $b) {
            return strtotime($a['registrationTime']) - strtotime($b['registrationTime']);
        });
        
        usort($waitlist, function($a, $b) {
            return strtotime($a['registrationTime']) - strtotime($b['registrationTime']);
        });
        
        echo json_encode([
            'success' => true,
            'participants' => $participants,
            'waitlist' => $waitlist
        ]);
        break;
        
    case 'removeParticipant':
        // Teilnehmer entfernen
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $date = isset($_POST['date']) ? trim($_POST['date']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        if ($password !== $adminPassword) {
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
            exit;
        }
        
        if (empty($date) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Fehlende Parameter']);
            exit;
        }
        
        $data = loadData();
        $wasParticipant = false;
        
        // Teilnehmer entfernen
        if (isset($data[$date])) {
            foreach ($data[$date] as $key => $reg) {
                if ($reg['email'] === $email) {
                    $wasParticipant = !$reg['waitlisted'];
                    unset($data[$date][$key]);
                    break;
                }
            }
            
            // Array neu indizieren
            $data[$date] = array_values($data[$date]);
            
            // Wenn es ein regulärer Teilnehmer war, ersten von der Warteliste hochstufen
            if ($wasParticipant) {
                $participantsCount = 0;
                foreach ($data[$date] as $reg) {
                    if (!$reg['waitlisted']) {
                        $participantsCount++;
                    }
                }
                
                if ($participantsCount < 25) {
                    foreach ($data[$date] as $key => $reg) {
                        if ($reg['waitlisted']) {
                            $data[$date][$key]['waitlisted'] = false;
                            break;
                        }
                    }
                }
            }
            
            saveData($data);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Keine Daten für dieses Datum']);
        }
        break;
        
    case 'promoteFromWaitlist':
        // Von Warteliste hochstufen
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $date = isset($_POST['date']) ? trim($_POST['date']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        
        if ($password !== $adminPassword) {
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
            exit;
        }
        
        if (empty($date) || empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Fehlende Parameter']);
            exit;
        }
        
        $data = loadData();
        
        // Teilnehmer von der Warteliste hochstufen
        if (isset($data[$date])) {
            $found = false;
            
            foreach ($data[$date] as $key => $reg) {
                if ($reg['email'] === $email && $reg['waitlisted']) {
                    $data[$date][$key]['waitlisted'] = false;
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                saveData($data);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Teilnehmer nicht gefunden oder nicht auf der Warteliste']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Keine Daten für dieses Datum']);
        }
        break;
        
    case 'exportData':
        // Daten exportieren
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        if ($password !== $adminPassword) {
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
            exit;
        }
        
        $data = loadData();
        echo json_encode(['success' => true, 'data' => $data]);
        break;
        
    case 'importData':
        // Daten importieren
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $jsonData = isset($_POST['data']) ? $_POST['data'] : '';
        
        if ($password !== $adminPassword) {
            echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
            exit;
        }
        
        if (empty($jsonData)) {
            echo json_encode(['success' => false, 'message' => 'Keine Daten zum Importieren']);
            exit;
        }
        
        $importedData = json_decode($jsonData, true);
        
        if ($importedData === null) {
            echo json_encode(['success' => false, 'message' => 'Ungültiges JSON-Format']);
            exit;
        }
        
        saveData($importedData);
        echo json_encode(['success' => true, 'message' => 'Daten erfolgreich importiert']);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ungültige Aktion']);
}