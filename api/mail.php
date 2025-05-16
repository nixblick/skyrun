<?php
/**
 * /api/mail.php
 * Version: 1.0.0
 * Email functionality
 */

/**
 * Sendet eine E-Mail mit der PHP mail() Funktion
 * 
 * @param string $to Empfänger-E-Mail
 * @param string $subject Betreff
 * @param string $message Inhalt (HTML)
 * @param string $bcc BCC-Empfänger (optional)
 * @return bool Erfolg
 */
function sendMail($to, $subject, $message, $bcc = '') {
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
        error_log("E-Mail-Versand deaktiviert.");
        return false;
    }

    // Header aufbauen
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Anmeldung Training Skyrun';
    $fromEmail = defined('MAIL_FROM') ? MAIL_FROM : 'skyrun@mein-computerfreund.de';
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // BCC hinzufügen falls konfiguriert und nicht bereits als Parameter übergeben
    if (empty($bcc) && defined('MAIL_BCC') && !empty(MAIL_BCC)) {
        $bcc = MAIL_BCC;
    }
    
    if (!empty($bcc)) {
        $headers[] = 'Bcc: ' . $bcc;
    }

    // Mail senden
    $success = mail($to, $subject, $message, implode("\r\n", $headers));
    
    if (!$success) {
        error_log("E-Mail konnte nicht gesendet werden an: $to");
    }
    
    return $success;
}

/**
 * Sendet eine Registrierungsbestätigung
 * 
 * @param string $email E-Mail des Teilnehmers
 * @param string $name Name des Teilnehmers
 * @param string $date Datum des Runs (YYYY-MM-DD)
 * @param int $personCount Anzahl der Personen
 * @param bool $isWaitlisted Ob auf Warteliste
 * @param string $station Wachenname
 * @return bool Erfolg
 */
function sendRegistrationConfirmation($email, $name, $date, $personCount, $isWaitlisted = false, $station = '') {
    // Datum formatieren
    $dateObj = new DateTime($date);
    $formattedDate = $dateObj->format('d.m.Y');
    $weekday = $dateObj->format('l');
    
    // Deutsche Wochentage
    $weekdays = [
        'Monday' => 'Montag',
        'Tuesday' => 'Dienstag',
        'Wednesday' => 'Mittwoch',
        'Thursday' => 'Donnerstag',
        'Friday' => 'Freitag',
        'Saturday' => 'Samstag',
        'Sunday' => 'Sonntag'
    ];
    
    $germanWeekday = $weekdays[$weekday] ?? $weekday;
    
    // Status-Text
    $statusText = $isWaitlisted ? 
        "auf die <strong>Warteliste</strong> gesetzt" : 
        "<strong>erfolgreich registriert</strong>";
    
    // Personen-Text
    $personText = ($personCount == 1) ? "Person" : "Personen";
    
    // Betreffzeile
    $subject = $isWaitlisted ? 
        "Skyrun Warteliste - $germanWeekday, $formattedDate" : 
        "Skyrun Anmeldung - $germanWeekday, $formattedDate";
    
    // HTML-Nachricht erstellen
    $message = "
    <html>
    <head>
        <title>$subject</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            h1 { color: #3498db; }
            .info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .footer { font-size: 12px; color: #666; margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>Trainig MesseTurm Skyrun</h1>
            <p>Hallo $name,</p>
            <p>vielen Dank für deine Anmeldung zum Training im MesseTurm Frankfurt.</p>
            
            <div class='info'>
                <p>Du wurdest für den Lauf am <strong>$germanWeekday, $formattedDate</strong> mit <strong>$personCount $personText</strong> $statusText.</p>
                " . (!empty($station) ? "<p>Wache: <strong>$station</strong></p>" : "") . "
            </div>
            
            " . ($isWaitlisted ? "<p>Sollte ein Platz frei werden, rückst du automatisch nach. Wir informieren dich in diesem Fall nicht gesondert.</p>" : "") . "
            
            <p>Für Fragen oder Änderungen stehen wir dir gerne zur Verfügung.</p>
            
            <p>Wir freuen uns auf deine Teilnahme!</p>
            <p>Dein Skyrun-Team</p>
            
            <div class='footer'>
                <p>über 1200 Stufen | 61 Etagen | 213 Höhenmeter | MesseTurm Frankfurt</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendMail($email, $subject, $message);
}
?>