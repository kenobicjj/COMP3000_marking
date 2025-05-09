<?php
// Connect to the database
require_once 'includes/config.php';

function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Start or resume session
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_email']);
}

// Check if user is an administrator
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// Redirect to login page if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Redirect to dashboard if already logged in
function redirectIfLoggedIn() {
    if (isLoggedIn()) {
        header("Location: dashboard.php");
        exit();
    }
}

// Format a mark as a percentage
function formatMark($mark) {
    if ($mark === null) {
        return "Not marked";
    }
    return number_format($mark, 2) . "%";
}

// Calculate average of two marks
function calculateAverage($mark1, $mark2) {
    if ($mark1 === null && $mark2 === null) {
        return null;
    } elseif ($mark1 === null) {
        return $mark2/2;
    } elseif ($mark2 === null) {
        return $mark1/2;
    } else {
        return ($mark1 + $mark2) / 2;
    }
}

// Security: validate student ID format
function validateStudentID($id) {
    return preg_match('/^[0-9]{8}$/', $id);
}

// Get marker name from email
function getMarkerName($email) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT Name FROM Marker WHERE Email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['Name'];
    }
    
    return "Unknown";
}

// Check if a marker is associated with a student
function isMarkerForStudent($markerEmail, $studentID) {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT ID FROM Student WHERE (FirstMarker = ? OR SecondMarker = ?) AND ID = ?");
    $stmt->bind_param("sss", $markerEmail, $markerEmail, $studentID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}
