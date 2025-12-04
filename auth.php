<?php
/**
 * Authentication Helper
 * Provides authenticateUser() function for endpoints
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/includes/auth_helper.php';

/**
 * Authenticate user from Bearer token
 * @return array|null User data or null
 */
function authenticateUser() {
    $conn = getDbConnection();
    $user = getAuthenticatedUser($conn);
    $conn->close();
    return $user;
}
?>
