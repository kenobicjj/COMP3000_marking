<?php
// view_report.php - Display detailed report for a student

require_once 'includes/config.php';
require_once 'functions.php';
requireLogin();

$studentID = $_GET['id'] ?? '';
$userEmail = $_SESSION['user_email'];
$isAdmin = isAdmin();

if (empty($studentID)) {
    //header("Location: dashboard.php");
    echo "No student ID provided.";
    exit();
}

$conn = connectDB();

// Fetch student details (moved outside the report queries)
$studentQuery = "SELECT ID, FirstName, LastName, Programme, FirstMarker, SecondMarker 
                 FROM Student 
                 WHERE ID = ?";
$studentStmt = $conn->prepare($studentQuery);
$studentStmt->bind_param("s", $studentID);
$studentStmt->execute();
$student = $studentStmt->get_result()->fetch_assoc();

if (!$student) {
    //header("Location: dashboard.php?message=Student not found");
    echo "Student:".$student;
    exit();
}

// Check if the user has access to this report.  Now checks against the student's markers.
$hasAccess = $isAdmin || $student['FirstMarker'] === $userEmail || $student['SecondMarker'] === $userEmail;

if (!$hasAccess) {
    //header("Location: dashboard.php?message=You do not have access to this report.");
    echo "You do not have access to this report.";
    exit();
}
$firstMarkerName = getMarkerName($student['FirstMarker']);
$secondMarkerName = getMarkerName($student['SecondMarker']);
// Function to calculate total mark for Coursework
function calculateCourseTotal($report, $markerPrefix) {
    $projectDef = $report["{$markerPrefix}_ProjectDefinition"] ?? 0;
    $contextReview = $report["{$markerPrefix}_ContextReview"] ?? 0;
    $methodology = $report["{$markerPrefix}_Methodology"] ?? 0;
    $evaluation = $report["{$markerPrefix}_Evaluation"] ?? 0;
    $structure = $report["{$markerPrefix}_Structure"] ?? 0;
    
    if ($projectDef === null && $contextReview === null && $methodology === null && 
        $evaluation === null && $structure === null) {
        return null;
    }
    
    $total = ($projectDef * 0.1) + ($contextReview * 0.15) + ($methodology * 0.5) + 
                ($evaluation * 0.15) + ($structure * 0.1);
    
    return $total;
}

// Function to calculate total mark for Practice
function calculatePracticeTotal($report, $markerPrefix) {
    $communication = $report["{$markerPrefix}_Communication"] ?? 0;
    $posterStructure = $report["{$markerPrefix}_PosterStructure"] ?? 0;
    $interview = $report["{$markerPrefix}_Interview"] ?? 0;
    
    if ($communication === null && $posterStructure === null && $interview === null) {
        return null;
    }
    
    $total = ($communication * 0.5) + ($posterStructure * 0.25) + ($interview * 0.25);
    
    return $total;
}



// Get coursework report details
$courseworkQuery = "SELECT r.*
            FROM Coursework r
            WHERE r.StudentID = ?";
$courseworkStmt = $conn->prepare($courseworkQuery);
$courseworkStmt->bind_param("s", $studentID);
$courseworkStmt->execute();
$courseworkReport = $courseworkStmt->get_result()->fetch_assoc();

// Get practice report details
$practiceQuery = "SELECT r.*
            FROM Practice r
            WHERE r.StudentID = ?";
$practiceStmt = $conn->prepare($practiceQuery);
$practiceStmt->bind_param("s", $studentID);
$practiceStmt->execute();
$practiceReport = $practiceStmt->get_result()->fetch_assoc();



// Calculate totals for Coursework and Practice.  Handles the cases where a report might not exist.
$courseFirstMarkerTotal = $courseworkReport ? calculateCourseTotal($courseworkReport, 'FM') : null;
$courseSecondMarkerTotal = $courseworkReport ? calculateCourseTotal($courseworkReport, 'SM') : null;
$courseAverageTotal = $courseworkReport ? calculateAverage($courseFirstMarkerTotal, $courseSecondMarkerTotal) : null;

$practiceFirstMarkerTotal = $practiceReport ? calculatePracticeTotal($practiceReport, 'FM') : null;
$practiceSecondMarkerTotal = $practiceReport ? calculatePracticeTotal($practiceReport, 'SM') : null;
$practiceAverageTotal = $practiceReport ? calculateAverage($practiceFirstMarkerTotal, $practiceSecondMarkerTotal) : null;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Report - COMP3000 Student Marking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Student Marking System</a>
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
    <?php if (isset($_GET['message'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($_GET["message"]); ?>
                    </div>
                <?php endif; ?>
    <div class="container mt-4">
        <div class="row mb-3">
            <div class="col">
                <h2>Student Reports</h2>
                <p>Student: <?php echo htmlspecialchars($student['FirstName'] . ' ' . $student['LastName']); ?> (<?php echo htmlspecialchars($student['ID']); ?>)</p>
                <p>Programme: <?php echo htmlspecialchars($student['Programme']); ?></p>
                <p>First Marker: <?php echo htmlspecialchars($firstMarkerName); ?></p>
                <p>Second Marker: <?php echo htmlspecialchars($secondMarkerName); ?></p>
            </div>
        </div>
        
        <?php if ($courseworkReport): ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3>Coursework Report</h3>
                    <div>
                        <?php if ($isAdmin || $student['FirstMarker'] === $userEmail): ?>
                        <a href="edit_report.php?type=course&id=<?php echo $courseworkReport['ReportID']; ?>&marker=first" class="btn btn-primary me-2">
                            Edit as 1st Marker
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($isAdmin || $student['SecondMarker'] === $userEmail): ?>
                        <a href="edit_report.php?type=course&id=<?php echo $courseworkReport['ReportID']; ?>&marker=second" class="btn btn-primary me-2">
                            Edit as 2nd Marker
                        </a>
                        <?php endif; ?>
                        <a href="printable_report.php?id=<?php echo $studentID; ?>" class="btn btn-outline-secondary">
                            Generate PDF
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th width="30%">Criteria</th>
                                <th width="10%">Weight</th>
                                <th width="15%">1st Marker<br><?php echo htmlspecialchars($firstMarkerName); ?></th>
                                <th width="15%">2nd Marker<br><?php echo htmlspecialchars($secondMarkerName); ?></th>
                                <th width="15%">Average</th>
                                <th width="15%">Weighted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Project Definition and Planning</td>
                                <td>10%</td>
                                <td><?php echo formatMark($courseworkReport['FM_ProjectDefinition']); ?></td>
                                <td><?php echo formatMark($courseworkReport['SM_ProjectDefinition']); ?></td>
                                <td><?php echo formatMark(calculateAverage($courseworkReport['FM_ProjectDefinition'], $courseworkReport['SM_ProjectDefinition'])); ?></td>
                                <td><?php echo formatMark(calculateAverage($courseworkReport['FM_ProjectDefinition'], $courseworkReport['SM_ProjectDefinition']) * 0.1); ?></td>
                            </tr>
                            <tr>
                                <td>Context Review and Subject Knowledge</td>
                                <td>15%</td>
                                <td><?php echo formatMark($courseworkReport['FM_ContextReview']); ?></td>
                                <td><?php echo formatMark($courseworkReport['SM_ContextReview']); ?></td>
                                <td><?php echo formatMark(calculateAverage($courseworkReport['FM_ContextReview'], $courseworkReport['SM_ContextReview'])); ?></td>
                                <td><?php echo formatMark(calculateAverage($courseworkReport['FM_ContextReview'], $courseworkReport['SM_ContextReview']) * 0.15); ?></td>
                            </tr>
                            <tr>
                                <td>Project Methodology and Implementation</td>
                                <td>50%</td>
                                <td><?php echo formatMark($courseworkReport['FM_Methodology']); ?></td>
                                <td><?php echo formatMark($courseworkReport['SM_Methodology']); ?></td>
                                <td><?php echo formatMark(calculateAverage($courseworkReport['FM_Methodology'], $courseworkReport['SM_Methodology'])); ?></td>
                                <td><?php echo formatMark(calculateAverage($courseworkReport['FM_Methodology'], $courseworkReport['SM_Methodology']) * 0.5); ?></td>
                            </tr>
                            <tr>
                                <td>Critical Evaluation and Conclusions</td>
                                <td>15%</td>
                                <td><?php echo formatMark($courseworkReport['FM_Evaluation']); ?></td>
                                <td><?php echo formatMark($courseworkReport['SM_Evaluation']); ?></td>
                                <td><?php echo formatMark(calculateAverage($courseworkReport['FM_Evaluation'], $courseworkReport['SM_Evaluation'])); ?></td>
                                <td><?php echo formatMark(calculateAverage($courseworkReport['FM_Evaluation'], $courseworkReport['SM_Evaluation']) * 0.15); ?></td>
                            </tr>
                            <tr>
                                <td>Structure and Presentation</td>
                                <td>10%</td>
                                <td><?php echo formatMark($courseworkReport['FM_Structure']); ?></td>
                                <td><?php echo formatMark($courseworkReport['SM_Structure']); ?></td>
                                <td><?php echo formatMark(calculateAverage($courseworkReport['FM_Structure'], $courseworkReport['SM_Structure'])); ?></td>
                                <td><?php echo formatMark(calculateAverage($courseworkReport['FM_Structure'], $courseworkReport['SM_Structure']) * 0.1); ?></td>
                            </tr>
                            <tr class="table-active fw-bold">
                                <td>TOTAL</td>
                                <td>100%</td>
                                <td><?php echo formatMark($courseFirstMarkerTotal); ?></td>
                                <td><?php echo formatMark($courseSecondMarkerTotal); ?></td>
                                <th colspan="2"><center><?php echo formatMark($courseAverageTotal); ?></center></th>

                            </tr>
                        </tbody>
                    </table>

                    <h4 class="mt-4">Feedback Comments</h4>
                    
                    <div class="mb-4">
                        <h5>Project Definition and Planning</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">1st Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($courseworkReport['FM_ProjectDefinitionComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">2nd Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($courseworkReport['SM_ProjectDefinitionComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Context Review and Subject Knowledge</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">1st Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($courseworkReport['FM_ContextReviewComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">2nd Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($courseworkReport['SM_ContextReviewComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Project Methodology and Implementation</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">1st Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($courseworkReport['FM_MethodologyComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">2nd Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($courseworkReport['SM_MethodologyComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Critical Evaluation and Conclusions</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">1st Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($courseworkReport['FM_EvaluationComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">2nd Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($courseworkReport['SM_EvaluationComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Structure and Presentation</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">1st Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($courseworkReport['FM_StructureComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">2nd Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($courseworkReport['SM_StructureComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <p><strong>1st Marker Last Modified:</strong> 
                                <?php echo $courseworkReport['FM_LastModified'] ? date('Y-m-d H:i:s', strtotime($courseworkReport['FM_LastModified'])) : 'Never'; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>2nd Marker Last Modified:</strong> 
                                <?php echo $courseworkReport['SM_LastModified'] ? date('Y-m-d H:i:s', strtotime($courseworkReport['SM_LastModified'])) : 'Never'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($practiceReport): ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3>Practice Report</h3>
                    <div>
                         <?php if ($isAdmin || $student['FirstMarker'] === $userEmail): ?>
                        <a href="edit_report.php?type=practice&id=<?php echo $practiceReport['ReportID']; ?>&marker=first" class="btn btn-primary me-2">
                            Edit as 1st Marker
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($isAdmin || $student['SecondMarker'] === $userEmail): ?>
                        <a href="edit_report.php?type=practice&id=<?php echo $practiceReport['ReportID']; ?>&marker=second" class="btn btn-primary me-2">
                            Edit as 2nd Marker
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th width="30%">Criteria</th>
                                <th width="10%">Weight</th>
                                <th width="15%">1st Marker<br><?php echo htmlspecialchars($firstMarkerName); ?></th>
                                <th width="15%">2nd Marker<br><?php echo htmlspecialchars($secondMarkerName); ?></th>
                                <th width="15%">Average</th>
                                <th width="15%">Weighted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Communication of Information</td>
                                <td>50%</td>
                                <td><?php echo formatMark($practiceReport['FM_Communication']); ?></td>
                                <td><?php echo formatMark($practiceReport['SM_Communication']); ?></td>
                                <td><?php echo formatMark(calculateAverage($practiceReport['FM_Communication'], $practiceReport['SM_Communication'])); ?></td>
                                <td><?php echo formatMark(calculateAverage($practiceReport['FM_Communication'], $practiceReport['SM_Communication']) * 0.5); ?></td>
                            </tr>
                            <tr>
                                <td>Poster Structure and Aesthetics</td>
                                <td>25%</td>
                                <td><?php echo formatMark($practiceReport['FM_PosterStructure']); ?></td>
                                <td><?php echo formatMark($practiceReport['SM_PosterStructure']); ?></td>
                                <td><?php echo formatMark(calculateAverage($practiceReport['FM_PosterStructure'], $practiceReport['SM_PosterStructure'])); ?></td>
                                <td><?php echo formatMark(calculateAverage($practiceReport['FM_PosterStructure'], $practiceReport['SM_PosterStructure']) * 0.25); ?></td>
                            </tr>
                            <tr>
                                <td>Interview</td>
                                <td>25%</td>
                                <td><?php echo formatMark($practiceReport['FM_Interview']); ?></td>
                                <td><?php echo formatMark($practiceReport['SM_Interview']); ?></td>
                                <td><?php echo formatMark(calculateAverage($practiceReport['FM_Interview'], $practiceReport['SM_Interview'])); ?></td>
                                <td><?php echo formatMark(calculateAverage($practiceReport['FM_Interview'], $practiceReport['SM_Interview']) * 0.25); ?></td>
                            </tr>
                            <tr class="table-active fw-bold">
                                <td>TOTAL</td>
                                <td>100%</td>
                                 <td><?php echo formatMark($practiceFirstMarkerTotal); ?></td>
                                <td><?php echo formatMark($practiceSecondMarkerTotal); ?></td>
                                <th colspan="2"><center><?php echo formatMark($practiceAverageTotal); ?></center></th>
                            </tr>
                        </tbody>
                    </table>

                    <h4 class="mt-4">Feedback Comments</h4>
                    
                    <div class="mb-4">
                        <h5>Communication of Information</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">1st Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($practiceReport['FM_CommunicationComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">2nd Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($practiceReport['SM_CommunicationComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Poster Structure and Aesthetics</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">1st Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($practiceReport['FM_PosterStructureComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">2nd Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($practiceReport['SM_PosterStructureComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h5>Interview</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">1st Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($practiceReport['FM_InterviewComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">2nd Marker Comments</div>
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($practiceReport['SM_InterviewComments'] ?? 'No comments')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <p><strong>1st Marker Last Modified:</strong> 
                                <?php echo $practiceReport['FM_LastModified'] ? date('Y-m-d H:i:s', strtotime($practiceReport['FM_LastModified'])) : 'Never'; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>2nd Marker Last Modified:</strong> 
                                <?php echo $practiceReport['SM_LastModified'] ? date('Y-m-d H:i:s', strtotime($practiceReport['SM_LastModified'])) : 'Never'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
