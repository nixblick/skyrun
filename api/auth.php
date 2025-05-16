<?php
/**
 * /api/auth.php
 * Version: 1.0.0
 * Authentication functions
 */

/**
 * Prüft Admin-Login
 */
function verifyAdminLogin($conn, $username, $password) {
    if (empty($username) || empty($password)) return false;
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return password_verify($password, $row['password_hash']);
    }
    return false;
}

/**
 * Prüft, ob der aktuelle Benutzer authentifiziert ist
 */
function isAdminAuthenticated() {
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}
?>