<?php
/**
 * mail-functions.php
 * E-Mail-Funktionen für Skyrun-Anmeldungen (via PHPMailer + SMTP)
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/lib/phpmailer/Exception.php';
require_once __DIR__ . '/lib/phpmailer/PHPMailer.php';
require_once __DIR__ . '/lib/phpmailer/SMTP.php';

function sendMail($to, $subject, $message, $bcc = '') {
    if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
        error_log("E-Mail-Versand deaktiviert.");
        return false;
    }

    $fromEmail = defined('MAIL_FROM')      ? MAIL_FROM      : 'skyrun@mein-computerfreund.de';
    $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Anmeldung Training Skyrun';

    if (empty($bcc) && defined('MAIL_BCC') && !empty(MAIL_BCC)) {
        $bcc = MAIL_BCC;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = defined('MAIL_HOST')     ? MAIL_HOST     : 'smtp.goneo.de';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('MAIL_USERNAME') ? MAIL_USERNAME : $fromEmail;
        $mail->Password   = defined('MAIL_PASSWORD') ? MAIL_PASSWORD : '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // STARTTLS Port 587
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        if (!empty($bcc)) {
            foreach (explode(',', $bcc) as $bccAddr) {
                $bccAddr = trim($bccAddr);
                if ($bccAddr && filter_var($bccAddr, FILTER_VALIDATE_EMAIL)) {
                    $mail->addBCC($bccAddr);
                }
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        return true;

    } catch (MailerException $e) {
        error_log("PHPMailer SMTP fehlgeschlagen an " . substr($to, 0, 1) . "***@***: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Sendet eine Registrierungsbestätigung
 *
 * @param string $email       E-Mail des Teilnehmers
 * @param string $name        Name des Teilnehmers
 * @param string $date        Datum des Runs (YYYY-MM-DD)
 * @param int    $personCount Anzahl der Personen
 * @param bool   $isWaitlisted Ob auf Warteliste
 * @param string $station     Wachenname
 * @param string $building    Gebäude ('Messeturm' oder 'Trianon')
 * @return bool Erfolg
 */
function sendRegistrationConfirmation($email, $name, $date, $personCount, $isWaitlisted = false, $station = '', $building = 'Messeturm', $time = '19:00') {
    $dateObj = new DateTime($date);
    $formattedDate = $dateObj->format('d.m.Y');
    $weekday = $dateObj->format('l');

    $weekdays = [
        'Monday'    => 'Montag',
        'Tuesday'   => 'Dienstag',
        'Wednesday' => 'Mittwoch',
        'Thursday'  => 'Donnerstag',
        'Friday'    => 'Freitag',
        'Saturday'  => 'Samstag',
        'Sunday'    => 'Sonntag'
    ];
    $germanWeekday = $weekdays[$weekday] ?? $weekday;

    $time = htmlspecialchars($time, ENT_QUOTES, 'UTF-8');

    $statusText = $isWaitlisted
        ? "auf die <strong>Warteliste</strong> gesetzt"
        : "<strong>erfolgreich registriert</strong>";

    $personText = ($personCount == 1) ? "Person" : "Personen";

    // Gebäudespezifische Infos
    if ($building === 'Trianon') {
        $buildingDisplayName = 'Trianon Frankfurt';
        $buildingTitle       = 'Trianon Skyrun';
        $buildingStats       = 'viele viele Stufen | 47 Etagen | 186 H&ouml;henmeter | Trianon Frankfurt';
        $buildingAddress     = 'Mainzer Landstra&szlig;e 16&ndash;24, 60325 Frankfurt am Main';
    } else {
        $buildingDisplayName = 'MesseTurm Frankfurt';
        $buildingTitle       = 'MesseTurm Skyrun';
        $buildingStats       = '&uuml;ber 1200 Stufen | 61 Etagen | 213 H&ouml;henmeter | MesseTurm Frankfurt';
        $buildingAddress     = 'Friedrich-Ebert-Anlage 49, 60308 Frankfurt am Main';
    }

    $subject = $isWaitlisted
        ? "Warteliste Skyrun Training – $buildingDisplayName – $germanWeekday, $formattedDate"
        : "Anmeldung zum Skyrun Training – $buildingDisplayName – $germanWeekday, $formattedDate";

    $stationRow = !empty($station) ? "<p>Wache: <strong>" . htmlspecialchars($station) . "</strong></p>" : "";
    $waitlistHint = $isWaitlisted
        ? "<p>Sollte ein Platz frei werden, r&uuml;ckst du automatisch nach. Wir informieren dich in diesem Fall nicht gesondert.</p>"
        : "";

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
            <h1>Skyrun Training – $buildingTitle</h1>
            <p>Hallo " . htmlspecialchars($name) . ",</p>
            <p>vielen Dank f&uuml;r deine Anmeldung zum Skyrun Training im $buildingDisplayName.</p>

            <div class='info'>
                <p>Du wurdest f&uuml;r den Lauf am <strong>$germanWeekday, $formattedDate um $time Uhr</strong> mit <strong>$personCount $personText</strong> $statusText.</p>
                $stationRow
                <p>Ort: <strong>$buildingAddress</strong></p>
            </div>

            <p><strong>Bitte sei p&uuml;nktlich um $time Uhr vor Ort.</strong> Wer mit Atemschutz trainieren m&ouml;chte, muss um $time Uhr vollst&auml;ndig ausger&uuml;stet und einsatzbereit sein!</p>

            $waitlistHint

            <p>F&uuml;r Fragen oder &Auml;nderungen stehen wir dir gerne zur Verf&uuml;gung.</p>
            <p>Wir freuen uns auf deine Teilnahme!</p>
            <p>Dein Skyrun-Team</p>

            <div class='footer'>
                <p>$buildingStats</p>
            </div>
        </div>
    </body>
    </html>
    ";

    return sendMail($email, $subject, $message);
}

