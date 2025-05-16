<?php
/**
 * /api/index.php
 * Version: 1.0.0
 * Main API Router
 */

// Session starten
session_start();

// Einbinden der benötigten Module
require_once 'config.php';
require_once 'db.php';
require_once 'auth.php';
require_once 'utils.php';
require_once 'registration.php';
require_once 'admin.php';
require_once 'mail.php';

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
$conn = connectDatabase();

// API-Router
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

// Routing zu den entsprechenden Aktionen
switch ($action) {
    // Registrierungs-Endpunkte
    case 'getStations':
    case 'register':
    case 'getStats':
        handleRegistrationAction($action, $conn);
        break;
    
    // Admin-Endpunkte
    case 'adminLogin':
    case 'adminLogout':
    case 'getParticipants':
    case 'removeParticipant':
    case 'promoteFromWaitlist':
    case 'exportData':
    case 'importData':
    case 'updateConfig':
    case 'getConfig':
    case 'changePassword':
    case 'getPeakBook':
        handleAdminAction($action, $conn);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Ungültige Aktion.']);
}

// Verbindung schließen
$conn->close();
?>