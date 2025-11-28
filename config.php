<?php
// config.php
// This file handles initial setup, including session management, database connection, 
// and essential security/authentication helper functions.

// --- 1. Session Initialization ---
// Start the session at the very beginning to ensure we can store user login state 
// across different pages (like user_id, role_name, etc.).
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 2. Database Credentials ---
// Define the parameters needed to connect to the MySQL database.
// NOTE: These should be replaced with secured credentials in a production environment.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // CHANGE THIS to your actual username
define('DB_PASSWORD', '');     // CHANGE THIS to your actual password
define('DB_NAME', 'new_pos_db');

/**
 * 3. Database Connection Setup
 * Establishes the connection using the defined credentials.
 * The $conn object is made global and used by all files that include config.php.
 */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection status immediately upon creation
if ($conn->connect_error) {
    // If the connection fails, stop execution and report the error.
    die("Connection failed: " . $conn->connect_error);
}

/**
 * 4. Authentication and Authorization Helper (RBAC Gatekeeper)
 * Ensures the user is logged in and optionally enforces a specific role (Role-Based Access Control).
 *
 * @param string|null $required_role_name The role name required for access (e.g., 'Admin', 'Manager').
 */
function require_auth($required_role_name = null) {
    // 4.1. Check for user login status (Is 'user_id' set in the session?)
    if (!isset($_SESSION['user_id'])) {
        // User is not logged in. Send them to the main login page.
        header("Location: index.php");
        exit(); // Stop script execution after redirect
    }

    // 4.2. Role-Based Access Control (RBAC) Check
    if ($required_role_name) {
        // A specific role is mandatory for this page.
        if (!isset($_SESSION['role_name']) || $_SESSION['role_name'] !== $required_role_name) {
            
            // User is logged in but lacks the necessary role (e.g., a Cashier trying to view Analytics).
            // Using a JavaScript alert instead of a header redirect ensures the user sees the denial message 
            // before the script potentially redirects or stops.
            echo "<script>alert('Access Denied. You do not have the required role ({$required_role_name}).');</script>";
            
            // Stop further processing of the restricted page content.
            exit(); 
        }
    }
}
?>