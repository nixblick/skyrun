<?php
/**
 * /api/db.php
 * Version: 1.0.0
 * Database connection and common database functions
 */

/**
 * Stellt eine Datenbankverbindung her
 * 
 * @return mysqli Die Datenbankverbindung
 */
function connectDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("Datenbankverbindung fehlgeschlagen: " . $conn->connect_error);
        echo json_encode(['success' => false, 'message' => 'Serverfehler bei der Datenbankverbindung.']);
        exit;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * Maximale Teilnehmeranzahl aus der Datenbank abrufen
 */
function getMaxParticipants($conn) {
    $defaultMax = 25;
    $stmt = $conn->prepare("SELECT `value` FROM config WHERE `key` = ?");
    $key = 'max_participants';
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['value'] ?? $defaultMax;
}

/**
 * Anzahl der Teilnehmer für ein Datum zählen
 */
function countParticipantsForDate($conn, $date) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(personCount), 0) as total FROM registrations WHERE date = ? AND waitlisted = 0");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    return (int)$result->fetch_assoc()['total'];
}

/**
 * Anzahl der Wartenden für ein Datum zählen
 */
function countWaitlistedForDate($conn, $date) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM registrations WHERE date = ? AND waitlisted = 1");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    return (int)$result->fetch_assoc()['total'];
}
?>