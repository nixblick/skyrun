<?php
/**
 * /api/utils.php
 * Version: 1.0.0
 * Utility functions
 */

/**
 * Prüft und bereinigt Eingabedaten
 * 
 * @param string $data Die zu bereinigende Eingabe
 * @return string Die bereinigte Eingabe
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validiert eine E-Mail-Adresse
 * 
 * @param string $email Die zu validierende E-Mail-Adresse
 * @return bool True wenn gültig, sonst False
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validiert ein Datum im Format YYYY-MM-DD
 * 
 * @param string $date Das zu validierende Datum
 * @return bool True wenn gültig, sonst False
 */
function validateDate($date) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $dateTime = DateTime::createFromFormat('Y-m-d', $date);
        return $dateTime && $dateTime->format('Y-m-d') === $date;
    }
    return false;
}

/**
 * Formatiert ein Datum für die Anzeige
 * 
 * @param string $date Datum im Format YYYY-MM-DD
 * @return string Formatiertes Datum (z.B. "Donnerstag, 16.05.2025")
 */
function formatDate($date) {
    $dateObj = new DateTime($date);
    $formatter = new IntlDateFormatter(
        'de_DE',
        IntlDateFormatter::FULL,
        IntlDateFormatter::NONE,
        'Europe/Berlin',
        IntlDateFormatter::GREGORIAN,
        'EEEE, dd.MM.yyyy'
    );
    return $formatter->format($dateObj);
}
?>