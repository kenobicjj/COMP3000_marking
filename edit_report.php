<?php
// edit_report.php - Edit detailed report for a student
session_start();
require_once 'includes/config.php';
require_once 'functions.php';
requireLogin();

$reportType = $_GET['type'] ?? '';
$reportID = $_GET['id'] ?? '';
$markerType = $_GET['marker'] ?? '';
$userEmail = $_SESSION['user_email'];
$isAdmin = isAdmin();

// Validate inputs
if (empty($reportType) || empty($reportID) || !in_array($reportType, ['course', 'practice']) ||
    !in_array($markerType, ['first', 'second'])) {
    header("Location: dashboard.php?message=Missing marker type or report ID.");
    exit();
}

$conn = connectDB();

// Determine which table to query
$table = ($reportType === 'course') ? 'Coursework' : 'Practice';

// Get report details
$query = "SELECT r.*, s.ID as StudentID, s.FirstName, s.LastName, s.Programme, s.FirstMarker, s.SecondMarker
         FROM {$table} r
         JOIN Student s ON r.StudentID = s.ID
         WHERE r.ReportID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $reportID);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$studentID=$report["StudentID"];
if (!$report) {
    header("Location: dashboard.php?message=No report found.");
    exit();
}

// Determine access rights
$isFirstMarker = $report['FirstMarker'] === $userEmail;
$isSecondMarker = $report['SecondMarker'] === $userEmail;

// Check if the user has access to edit this report as the specified marker
$hasAccess = false;

if ($markerType === 'first' && ($isAdmin || $isFirstMarker)) {
    $hasAccess = true;
    $markerPrefix = 'FM';
} elseif ($markerType === 'second' && ($isAdmin || $isSecondMarker)) {
    $hasAccess = true;
    $markerPrefix = 'SM';
}

if (!$hasAccess) {
    header("Location: view_report.php?id={$studentID}?message=not enough access rights.");
    exit();
}

// Get the first and second marker names
$firstMarkerName = getMarkerName($report['FirstMarker']);
$secondMarkerName = getMarkerName($report['SecondMarker']);

// Process form submission
$formSubmitted = false;
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formSubmitted = true;

    try {
        // Start transaction
        $conn->begin_transaction();

        $fields = [];
        $values = [];
        $types = '';

        // Process form fields based on report type
        if ($reportType === 'course') {
            $criteria = [
                'ProjectDefinition' => 0.1,
                'ContextReview' => 0.15,
                'Methodology' => 0.5,
                'Evaluation' => 0.15,
                'Structure' => 0.1
            ];

            foreach ($criteria as $field => $weight) {
                // Get mark
                $markField = "{$markerPrefix}_{$field}";
                $mark = $_POST[$markField] ?? null;

                if ($mark !== '' && $mark !== null) {
                    $mark = floatval($mark);
                    if ($mark < 0 || $mark > 100) {
                        throw new Exception("Mark for {$field} must be between 0 and 100");
                    }
                    $fields[] = "{$markField} = ?";
                    $values[] = $mark;
                    $types .= 'd'; // Decimal type
                } else {
                    $fields[] = "{$markField} = NULL";
                }

                // Get comments
                $commentField = "{$markerPrefix}_{$field}Comments";
                $comments = $_POST[$commentField] ?? '';
                $fields[] = "{$commentField} = ?";
                $values[] = $comments;
                $types .= 's'; // String type
            }
        } else { // Practice report
            $criteria = [
                'Communication' => 0.5,
                'PosterStructure' => 0.25,
                'Interview' => 0.25
            ];

            foreach ($criteria as $field => $weight) {
                // Get mark
                $markField = "{$markerPrefix}_{$field}";
                $mark = $_POST[$markField] ?? null;

                if ($mark !== '' && $mark !== null) {
                    $mark = floatval($mark);
                    if ($mark < 0 || $mark > 100) {
                        throw new Exception("Mark for {$field} must be between 0 and 100");
                    }
                    $fields[] = "{$markField} = ?";
                    $values[] = $mark;
                    $types .= 'd'; // Decimal type
                } else {
                    $fields[] = "{$markField} = NULL";
                }

                // Get comments
                $commentField = "{$markerPrefix}_{$field}Comments";
                $comments = $_POST[$commentField] ?? '';
                $fields[] = "{$commentField} = ?";
                $values[] = $comments;
                $types .= 's'; // String type
            }
        }

        // Update last modified timestamp
        $lastModifiedField = "{$markerPrefix}_LastModified";
        $fields[] = "{$lastModifiedField} = NOW()";

        // Build and execute the update query
        $updateQuery = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE ReportID = ?";
        $stmt = $conn->prepare($updateQuery);

        // Add report ID to values array and types string
        $values[] = $reportID;
        $types .= 'i'; // Integer type

        // Bind parameters dynamically
        $stmt->bind_param($types, ...$values);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        // Redirect to the view report page

        header("Location: view_report.php?id={$studentID}&message=Report updated successfully.");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Helper function to safely retrieve form values
function getFormValue($fieldName, $default = '') {
    global $formSubmitted, $report;

    if ($formSubmitted) {
        return $_POST[$fieldName] ?? $default;
    } else {
        return $report[$fieldName] ?? $default;
    }
}

// Page title based on report and marker type
$markerTypeName = ($markerType === 'first') ? '1st Marker' : '2nd Marker';
$reportTypeName = ($reportType === 'course') ? 'Coursework' : 'Practice';
$pageTitle = "Edit {$reportTypeName} Report ({$markerTypeName})";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Student Marking System</title>
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
                    <?php if ($isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="export_csv.php">Generate CSV</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-3">
            <div class="col">
                <h2><?php echo $pageTitle; ?></h2>
                <p class="mb-0">Student: <?php echo htmlspecialchars($report['FirstName'] . ' ' . $report['LastName']); ?> (<?php echo htmlspecialchars($report['StudentID']); ?>)</p>
                <p class="mb-0">Programme: <?php echo htmlspecialchars($report['Programme']); ?></p>
                <p class="mb-0">First Marker: <?php echo htmlspecialchars($firstMarkerName); ?></p>
                <p>Second Marker: <?php echo htmlspecialchars($secondMarkerName); ?></p>

                <div class="mb-3">
                    <a href="view_report.php?id=<?php echo $studentID; ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Report View
                    </a>
                </div>

                <?php if ($errorMessage): ?>
                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                <?php endif; ?>

                <?php if ($successMessage): ?>
                <div class="alert alert-success"><?php echo $successMessage; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h3>Edit Report</h3>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <?php if ($reportType === 'course'): ?>
                    <!-- Coursework Report Form -->
                    <h4 class="mb-3">Project Definition and Planning (10%)</h4>
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <label for="<?php echo "{$markerPrefix}_ProjectDefinition"; ?>" class="form-label">Mark (0-100)</label>
                            <input type="number" class="form-control" id="<?php echo "{$markerPrefix}_ProjectDefinition"; ?>" name="<?php echo "{$markerPrefix}_ProjectDefinition"; ?>" min="0" max="100" step="0.01" value="<?php echo getFormValue("{$markerPrefix}_ProjectDefinition"); ?>">
                        </div>
                        <div class="col-md-10">
                            <label for="<?php echo "{$markerPrefix}_ProjectDefinitionComments"; ?>" class="form-label">Comments</label>
                            <textarea class="form-control" id="<?php echo "{$markerPrefix}_ProjectDefinitionComments"; ?>" name="<?php echo "{$markerPrefix}_ProjectDefinitionComments"; ?>" rows="3"><?php echo getFormValue("{$markerPrefix}_ProjectDefinitionComments"); ?></textarea>
                        </div>
                    </div>

                    <h4 class="mb-3">Context Review and Subject Knowledge (15%)</h4>
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <label for="<?php echo "{$markerPrefix}_ContextReview"; ?>" class="form-label">Mark (0-100)</label>
                            <input type="number" class="form-control" id="<?php echo "{$markerPrefix}_ContextReview"; ?>" name="<?php echo "{$markerPrefix}_ContextReview"; ?>" min="0" max="100" step="0.01" value="<?php echo getFormValue("{$markerPrefix}_ContextReview"); ?>">
                        </div>
                        <div class="col-md-10">
                            <label for="<?php echo "{$markerPrefix}_ContextReviewComments"; ?>" class="form-label">Comments</label>
                            <textarea class="form-control" id="<?php echo "{$markerPrefix}_ContextReviewComments"; ?>" name="<?php echo "{$markerPrefix}_ContextReviewComments"; ?>" rows="3"><?php echo getFormValue("{$markerPrefix}_ContextReviewComments"); ?></textarea>
                        </div>
                    </div>

                    <h4 class="mb-3">Project Methodology and Implementation (50%)</h4>
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <label for="<?php echo "{$markerPrefix}_Methodology"; ?>" class="form-label">Mark (0-100)</label>
                            <input type="number" class="form-control" id="<?php echo "{$markerPrefix}_Methodology"; ?>" name="<?php echo "{$markerPrefix}_Methodology"; ?>" min="0" max="100" step="0.01" value="<?php echo getFormValue("{$markerPrefix}_Methodology"); ?>">
                        </div>
                        <div class="col-md-10">
                            <label for="<?php echo "{$markerPrefix}_MethodologyComments"; ?>" class="form-label">Comments</label>
                            <textarea class="form-control" id="<?php echo "{$markerPrefix}_MethodologyComments"; ?>" name="<?php echo "{$markerPrefix}_MethodologyComments"; ?>" rows="3"><?php echo getFormValue("{$markerPrefix}_MethodologyComments"); ?></textarea>
                        </div>
                    </div>

                    <h4 class="mb-3">Critical Evaluation and Conclusions (15%)</h4>
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <label for="<?php echo "{$markerPrefix}_Evaluation"; ?>" class="form-label">Mark (0-100)</label>
                            <input type="number" class="form-control" id="<?php echo "{$markerPrefix}_Evaluation"; ?>" name="<?php echo "{$markerPrefix}_Evaluation"; ?>" min="0" max="100" step="0.01" value="<?php echo getFormValue("{$markerPrefix}_Evaluation"); ?>">
                        </div>
                        <div class="col-md-10">
                            <label for="<?php echo "{$markerPrefix}_EvaluationComments"; ?>" class="form-label">Comments</label>
                            <textarea class="form-control" id="<?php echo "{$markerPrefix}_EvaluationComments"; ?>" name="<?php echo "{$markerPrefix}_EvaluationComments"; ?>" rows="3"><?php echo getFormValue("{$markerPrefix}_EvaluationComments"); ?></textarea>
                        </div>
                    </div>

                    <h4 class="mb-3">Structure and Presentation (10%)</h4>
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <label for="<?php echo "{$markerPrefix}_Structure"; ?>" class="form-label">Mark (0-100)</label>
                            <input type="number" class="form-control" id="<?php echo "{$markerPrefix}_Structure"; ?>" name="<?php echo "{$markerPrefix}_Structure"; ?>" min="0" max="100" step="0.01" value="<?php echo getFormValue("{$markerPrefix}_Structure"); ?>">
                        </div>
                        <div class="col-md-10">
                            <label for="<?php echo "{$markerPrefix}_StructureComments"; ?>" class="form-label">Comments</label>
                            <textarea class="form-control" id="<?php echo "{$markerPrefix}_StructureComments"; ?>" name="<?php echo "{$markerPrefix}_StructureComments"; ?>" rows="3"><?php echo getFormValue("{$markerPrefix}_StructureComments"); ?></textarea>
                        </div>
                    </div>

                    <?php else: ?>
                    <!-- Practice Report Form -->
                    <h4 class="mb-3">Communication of Information (50%)</h4>
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <label for="<?php echo "{$markerPrefix}_Communication"; ?>" class="form-label">Mark (0-100)</label>
                            <input type="number" class="form-control" id="<?php echo "{$markerPrefix}_Communication"; ?>" name="<?php echo "{$markerPrefix}_Communication"; ?>" min="0" max="100" step="0.01" value="<?php echo getFormValue("{$markerPrefix}_Communication"); ?>">
                        </div>
                        <div class="col-md-10">
                            <label for="<?php echo "{$markerPrefix}_CommunicationComments"; ?>" class="form-label">Comments</label>
                            <textarea class="form-control" id="<?php echo "{$markerPrefix}_CommunicationComments"; ?>" name="<?php echo "{$markerPrefix}_CommunicationComments"; ?>" rows="3"><?php echo getFormValue("{$markerPrefix}_CommunicationComments"); ?></textarea>
                        </div>
                    </div>

                    <h4 class="mb-3">Poster Structure and Aesthetics (25%)</h4>
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <label for="<?php echo "{$markerPrefix}_PosterStructure"; ?>" class="form-label">Mark (0-100)</label>
                            <input type="number" class="form-control" id="<?php echo "{$markerPrefix}_PosterStructure"; ?>" name="<?php echo "{$markerPrefix}_PosterStructure"; ?>" min="0" max="100" step="0.01" value="<?php echo getFormValue("{$markerPrefix}_PosterStructure"); ?>">
                        </div>
                        <div class="col-md-10">
                            <label for="<?php echo "{$markerPrefix}_PosterStructureComments"; ?>" class="form-label">Comments</label>
                            <textarea class="form-control" id="<?php echo "{$markerPrefix}_PosterStructureComments"; ?>" name="<?php echo "{$markerPrefix}_PosterStructureComments"; ?>" rows="3"><?php echo getFormValue("{$markerPrefix}_PosterStructureComments"); ?></textarea>
                        </div>
                    </div>

                    <h4 class="mb-3">Interview (25%)</h4>
                    <div class="row mb-4">
                        <div class="col-md-2">
                            <label for="<?php echo "{$markerPrefix}_Interview"; ?>" class="form-label">Mark (0-100)</label>
                            <input type="number" class="form-control" id="<?php echo "{$markerPrefix}_Interview"; ?>" name="<?php echo "{$markerPrefix}_Interview"; ?>" min="0" max="100" step="0.01" value="<?php echo getFormValue("{$markerPrefix}_Interview"); ?>">
                        </div>
                        <div class="col-md-10">
                            <label for="<?php echo "{$markerPrefix}_InterviewComments"; ?>" class="form-label">Comments</label>
                            <textarea class="form-control" id="<?php echo "{$markerPrefix}_InterviewComments"; ?>" name="<?php echo "{$markerPrefix}_InterviewComments"; ?>" rows="3"><?php echo getFormValue("{$markerPrefix}_InterviewComments"); ?></textarea>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="row mt-4">
                        <div class="col">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="view_report.php?type=<?php echo $reportType; ?>&id=<?php echo $reportID; ?>" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Last modified information -->
        <div class="card mb-4">
            <div class="card-header">
                <h4>Marker Information</h4>
            </div>
            <div class="card-body">
                <p><strong>Last Modified:</strong>
                    <?php echo $report["{$markerPrefix}_LastModified"] ? date('Y-m-d H:i:s', strtotime($report["{$markerPrefix}_LastModified"])) : 'Never'; ?>
                </p>
                <p class="mb-0 text-muted"><small>Note: Saving changes will update the last modified timestamp.</small></p>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
