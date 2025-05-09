<?php
// dashboard.php - Display list of students based on marker's role

require_once 'includes/config.php';
require_once 'functions.php';
requireLogin();

$conn = connectDB();
$userEmail = $_SESSION['user_email'];
$isAdmin = isAdmin();
if (isset($_GET['message'])) $message=$_GET['message'];


// Prepare the student query based on user's role
if ($isAdmin) {
    // Administrators see all students
    $query = "SELECT s.ID, s.FirstName, s.LastName, s.Programme, s.FirstMarker, s.SecondMarker,
              c.ReportID as CourseReportID, p.ReportID as PracticeReportID,
              m1.Name as FirstMarkerName, m2.Name as SecondMarkerName
              FROM Student s
              LEFT JOIN Coursework c ON s.ID = c.StudentID
              LEFT JOIN Practice p ON s.ID = p.StudentID
              LEFT JOIN Marker m1 ON s.FirstMarker = m1.Email
              LEFT JOIN Marker m2 ON s.SecondMarker = m2.Email
              ORDER BY m1.Name, m2.Name";
    $stmt = $conn->prepare($query);
} else {
    // Regular markers see only their assigned students
    $query = "SELECT s.ID, s.FirstName, s.LastName, s.Programme, s.FirstMarker, s.SecondMarker,
              c.ReportID as CourseReportID, p.ReportID as PracticeReportID,
              m1.Name as FirstMarkerName, m2.Name as SecondMarkerName
              FROM Student s
              LEFT JOIN Coursework c ON s.ID = c.StudentID
              LEFT JOIN Practice p ON s.ID = p.StudentID
              LEFT JOIN Marker m1 ON s.FirstMarker = m1.Email
              LEFT JOIN Marker m2 ON s.SecondMarker = m2.Email
              WHERE s.FirstMarker = ? OR s.SecondMarker = ?
              ORDER BY
				CASE
					WHEN s.FirstMarker = ? THEN 0
					WHEN s.SecondMarker = ? THEN 1
					ELSE 2
				END,
				s.FirstMarker,
				s.SecondMarker;";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssss", $userEmail, $userEmail, $userEmail, $userEmail);
}

$stmt->execute();
$result = $stmt->get_result();

// Get the marking status for each report
function getMarkingStatus($reportID, $markerEmail, $reportType) {
    $conn = connectDB();
    $table = ($reportType === 'course') ? 'Coursework' : 'Practice';
    $fieldPrefix = ($markerEmail === $_SESSION['user_email']) ? 'FM' : 'SM';

    // Check if the first field is marked
    $query = "SELECT {$fieldPrefix}_LastModified FROM {$table} WHERE ReportID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $reportID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        return ($row["{$fieldPrefix}_LastModified"]) ? "Marked" : "Not marked";
    }

    return "N/A";
}

// Check if the user is an admin
if ($isAdmin) {
    // Fetch marker names from the database
    $markerQuery = "SELECT Email, Name FROM Marker"; // Adjusted to match the schema
    $markerResult = $conn->query($markerQuery);
    $markers = $markerResult->fetch_all(MYSQLI_ASSOC);

    // Check if the form was submitted to save markers
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Retrieve the first and second marker assignments
        $firstMarkers = $_POST['firstMarker'] ?? [];
        $secondMarkers = $_POST['secondMarker'] ?? [];

        // Prepare the update statement
        $updateQuery = "UPDATE Student SET FirstMarker = ?, SecondMarker = ? WHERE ID = ?";
        $stmt = $conn->prepare($updateQuery);

        // Loop through each student ID and update their markers
        foreach ($firstMarkers as $studentID => $firstMarker) {
            $secondMarker = $secondMarkers[$studentID] ?? null; // Get the second marker if set

            // Execute the update for each student
            $stmt->bind_param("ssi", $firstMarker, $secondMarker, $studentID);
            $stmt->execute();
        }

        // Close the statement
        $stmt->close();

        // Redirect back to the dashboard with a success message
        header("Location: dashboard.php?message=Assignments saved successfully.");
        exit();
    }
    // Start the form for saving assignments
    echo '<form method="POST" action="dashboard.php">'; // Action is empty to submit to the same page
}



?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Student Marking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">COMP3000 Student Marking System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="change_password.php">Change Password</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                <?php if (isset($_GET['message'])):
                    $message = $_GET['message']; ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <p>
                    Role: <?php echo $isAdmin ? 'Administrator' : 'Marker'; ?>
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Student List</h3>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Programme</th>
                                    <th>1st Marker</th>
                                    <th>2nd Marker</th>
                                    <th>Reports</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['ID']); ?></td>
                                        <td><?php echo htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['Programme']); ?></td>
                                        <td>
                                            <?php

                                            if ($isAdmin){ ?>
                                                <select name="firstMarker[<?php echo $row['ID']; ?>]">
                                                    <?php foreach ($markers as $marker): ?>
                                                        <option value="<?php echo $marker['Email']; ?>" <?php echo ($marker['Email'] === $row['FirstMarker']) ? 'selected' : ''; ?>>
                                                            <?php echo $marker['Name']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <option value="" <?php echo (empty($row['FirstMarker'])) ? 'selected' : ''; ?>>-</option>
                                                </select>
                                            <?php }
                                            else {
                                                echo htmlspecialchars($row['FirstMarkerName'] ?? '-');
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php

                                            if ($isAdmin){ ?>
                                                <select name="secondMarker[<?php echo $row['ID']; ?>]">
                                                    <?php foreach ($markers as $marker): ?>
                                                        <option value="<?php echo $marker['Email']; ?>" <?php echo ($marker['Email'] === $row['SecondMarker']) ? 'selected' : ''; ?>>
                                                            <?php echo $marker['Name']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <option value="" <?php echo (empty($row['SecondMarker'])) ? 'selected' : ''; ?>>-</option>
                                                </select>
                                            <?php }
                                            else {
                                                echo htmlspecialchars($row['SecondMarkerName'] ?? '-');
                                            } ?>
                                        </td>
                                        <td>
                                        <?php
                                            // Create a single report link
                                            $reportID = $row['CourseReportID'] ?: $row['PracticeReportID']; // Use the first available report ID
                                            if ($reportID): ?>
                                                <a href="view_report.php?id=<?php echo $row['ID']; ?>" class="btn btn-sm btn-outline-primary">
                                                    View Report
                                                </a>
                                            <?php else: ?>
                                                <span>No Report Available</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No students found.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($isAdmin): ?>
            <button type="submit" class="btn btn-primary">Save Assignments</button>
        <?php endif; ?>
    </div>
</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
