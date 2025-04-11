<?php
require_once 'config.php';

// Datenbankverbindung herstellen
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

// Datenbankname
$db_name = DB_NAME;

// Backup-Datei erstellen
$backup_file = "backup_$db_name_" . date('Y-m-d_H-i-s') . '.sql';
$handle = fopen($backup_file, 'w');

// Tabellen abrufen
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// Für jede Tabelle Backup erstellen
foreach ($tables as $table) {
    // DROP TABLE und CREATE TABLE Anweisung
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_row();
    fwrite($handle, "\n\n" . "DROP TABLE IF EXISTS `$table`;" . "\n\n");
    fwrite($handle, $row[1] . ";\n\n");

    // Daten exportieren
    $result = $conn->query("SELECT * FROM `$table`");
    $num_fields = $result->field_count;

    while ($row = $result->fetch_row()) {
        $line = "INSERT INTO `$table` VALUES(";
        for ($i = 0; $i < $num_fields; $i++) {
            $row[$i] = $conn->real_escape_string($row[$i] ?? '');
            $line .= "'" . $row[$i] . "'";
            if ($i < $num_fields - 1) $line .= ",";
        }
        $line .= ");\n";
        fwrite($handle, $line);
    }
}

fclose($handle);
$conn->close();

// Download der Datei anbieten
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($backup_file) . '"');
header('Content-Length: ' . filesize($backup_file));
readfile($backup_file);

// Optional: Backup-Datei nach Download löschen
unlink($backup_file);
exit;
?>