<?php
// /skyrun/config.php

// --- !!! WICHTIG: DATENBANK-ZUGANGSDATEN ANPASSEN !!! ---
define('DB_HOST', 'localhost');                 // Oft 'localhost'
define('DB_USER', '15055m10623_10');            // Dein DB-Benutzername
define('DB_PASS', '2cclZ6qFvb6qhF7amQJU');      // Dein DB-Passwort hier eintragen!
define('DB_NAME', '15055m10623_10');            // Dein DB-Name
// ---------------------------------------------------------

// Zeitzone (wichtig für Datumsvergleiche und Zeitstempel)
define('TIMEZONE', 'Europe/Berlin');

// Fehler-Logging (true = Fehler in Logdatei schreiben, false = deaktiviert)
// In Produktion sollte display_errors auf false stehen!
define('ENABLE_ERROR_LOGGING', true);
define('DISPLAY_PHP_ERRORS', false); // true nur zur Fehlersuche, nie in Produktion!
define('PHP_ERROR_LOG_FILE', __DIR__ . '/php_errors.log'); // Logdatei im selben Ordner

// Error Reporting basierend auf Konfiguration
if (DISPLAY_PHP_ERRORS) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

if (ENABLE_ERROR_LOGGING) {
    ini_set('log_errors', 1);
    ini_set('error_log', PHP_ERROR_LOG_FILE);
}

// Zeitzone setzen
date_default_timezone_set(TIMEZONE);

?>