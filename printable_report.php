<?php
// printable_report.php - Display printer-friendly report that can be exported to PDF
session_start();
require_once 'includes/config.php';
require_once 'functions.php';
requireLogin();

$userEmail = $_SESSION['user_email'];
$isAdmin = isAdmin();
$generatePDF = isset($_GET['pdf']) && $_GET['pdf'] == 'true';

$conn = connectDB();

// Get the student ID from the URL
$studentID = $_GET['id'] ?? '';

// Check if the student ID is provided
if (empty($studentID)) {
    header("Location: dashboard.php");
    exit();
}

// Get report details for Coursework
$courseworkQuery = "
    SELECT r.ReportID, r.StudentID,
           r.FM_ProjectDefinition, r.FM_ContextReview, r.FM_Methodology, r.FM_Evaluation, r.FM_Structure,
           r.SM_ProjectDefinition, r.SM_ContextReview, r.SM_Methodology, r.SM_Evaluation, r.SM_Structure,
           s.ID as StudentID, s.FirstName, s.LastName, s.Programme AS Programme, s.FirstMarker, s.SecondMarker,
           r.FM_LastModified AS FM_LastModified, r.FM_ProjectDefinitionComments, r.FM_ContextReviewComments,
           r.FM_MethodologyComments, r.FM_EvaluationComments, r.FM_StructureComments,
           r.SM_ProjectDefinitionComments, r.SM_ContextReviewComments, r.SM_MethodologyComments, r.SM_EvaluationComments, r.SM_StructureComments
    FROM Coursework r
    JOIN Student s ON r.StudentID = s.ID
    WHERE s.ID = ?
";
$courseworkStmt = $conn->prepare($courseworkQuery);
$courseworkStmt->bind_param("s", $studentID);
$courseworkStmt->execute();
$courseworkReport = $courseworkStmt->get_result()->fetch_assoc();

// Get report details for Practice
$practiceQuery = "
    SELECT r.ReportID, r.StudentID,
           r.FM_Communication, r.FM_CommunicationComments, r.FM_PosterStructure, r.FM_PosterStructureComments, r.FM_Interview, r.FM_InterviewComments,
           r.SM_Communication, r.SM_CommunicationComments, r.SM_PosterStructure, r.SM_PosterStructureComments, r.SM_Interview, r.SM_InterviewComments,
           s.ID as StudentID, s.FirstName, s.LastName, s.Programme, s.FirstMarker, s.SecondMarker,
           r.SM_LastModified AS SM_LastModified
    FROM Practice r
    JOIN Student s ON r.StudentID = s.ID
    WHERE s.ID = ?
";
$practiceStmt = $conn->prepare($practiceQuery);
$practiceStmt->bind_param("s", $studentID);
$practiceStmt->execute();
$practiceReport = $practiceStmt->get_result()->fetch_assoc();

// Check if reports exist
if (!$courseworkReport && !$practiceReport) {
    header("Location: dashboard.php");
    exit();
}

// Check if the user has access to this report
$hasAccess = $isAdmin ||
             ($courseworkReport && ($courseworkReport['FirstMarker'] === $userEmail || $courseworkReport['SecondMarker'] === $userEmail)) ||
             ($practiceReport && ($practiceReport['FirstMarker'] === $userEmail || $practiceReport['SecondMarker'] === $userEmail));

if (!$hasAccess) {
    header("Location: dashboard.php");
    exit();
}

// Get the first and second marker names
$firstMarkerName = $courseworkReport ? getMarkerName($courseworkReport['FirstMarker']) : getMarkerName($practiceReport['FirstMarker']);
$secondMarkerName = $courseworkReport ? getMarkerName($courseworkReport['SecondMarker']) : getMarkerName($practiceReport['SecondMarker']);

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

// Calculate totals for Coursework
$firstMarkerTotalCoursework = calculateCourseTotal($courseworkReport, 'FM');
$secondMarkerTotalCoursework = calculateCourseTotal($courseworkReport, 'SM');

// Calculate totals for Practice
$firstMarkerTotalPractice = calculatePracticeTotal($practiceReport, 'FM');
$secondMarkerTotalPractice = calculatePracticeTotal($practiceReport, 'SM');

// Calculate average marks
$averageMarksCoursework = calculateAverage($firstMarkerTotalCoursework, $secondMarkerTotalCoursework);
$averageMarksPractice = calculateAverage($firstMarkerTotalPractice, $secondMarkerTotalPractice);

// Calculate overall average total
$averageTotal = calculateAverage($averageMarksCoursework, $averageMarksPractice);


$interviewMark = calculateAverage($practiceReport['FM_Interview'] ?? 0, $practiceReport['SM_Interview'] ?? 0);
$posterStructureMark = calculateAverage($practiceReport['FM_PosterStructure'] ?? 0, $practiceReport['SM_PosterStructure'] ?? 0);
$communicationMark = calculateAverage($practiceReport['FM_Communication'] ?? 0, $practiceReport['SM_Communication'] ?? 0);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Printable Report - COMP3000 Student Marking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
<style>
  /* Overall container for the report content to control width */
  #report-content {
    width: 100%; /* Adjust as needed, can also use px or other units */
    max-width: 210mm; /* Optional: set a maximum width */
  }

  /* Style for the tables within the report content */
  #report-content table {
    width: 99%; /* Make the table fill the width of its container */
    border-collapse: collapse; /* Collapse borders for a cleaner look */
    margin-bottom: 20px; /* Add some space below each table */
  }

  /* Style for table headers (th) */
  #report-content th {
    background-color: #f2f2f2;
    border: 1px solid black;
    padding: 8px;
    text-align: left;
    font-size: 12px; /* Slightly larger font for headers */
  }

  /* Style for table data cells (td) */
  #report-content td {
    border: 1px solid black;
    padding: 8px;
    text-align: left;
    font-size: 10px; /* Consistent font size for most content */
  }

  /* Specific style for the mark column */
  #report-content .mark-column {
    text-align: center; /* Center the marks */
    font-weight: bold; /* Make the marks stand out */
    font-size: 12px; /* Slightly larger font for marks */
  }

  /* Style for feedback paragraphs */
  #report-content td p {
    font-size: 10px; /* Slightly smaller font for feedback for better readability in dense text */
    line-height: 1.5; /* Improve readability of feedback text */
    margin-bottom: 5px; /* Add some space between feedback paragraphs */
  }

  /* Style for the total row */
  #report-content .table-active td {
    font-weight: bold;
    background-color: #e9ecef; /* Light grey background for total row */
  }

  /* Ensure page breaks work correctly for printing/PDF */
  .page-break {
    page-break-before: always;
    break-before: page;
  }

  /* Non-print styles (adjust these if needed for screen display) */
  .container {
    width: 95%;
    max-width: 200mm; /* Or your preferred max width for screen */
    margin: 0 auto;
    padding: 15px;
  }
  .report-header {
    margin-bottom: 30px;
  }
  .report-title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 10px;
  }
  .student-info {
    margin-bottom: 5px;
    font-size: 14px; /* Match the general table font size for consistency */
  }
  .no-print {
    margin-bottom: 15px; /* Add some space below the non-print buttons */
  }
</style>
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

</head>
<body>
    <div class="container my-4">
        <div class="row no-print mb-3">
            <div class="col d-flex justify-content-between">
                <a href="view_report.php?id=<?php echo $studentID; ?>" class="btn btn-secondary">
                    Back to Report
                </a>
                <a href="#" id="generate-pdf" class="btn btn-primary">Download PDF</a>
                </a>
            </div>
        </div>
        <div id="report-content">
            <div class="report-header">
                <div class="report-title">
                COMP3000 Report for <?php echo $practiceReport['FirstName'] . ' ' . $practiceReport['LastName'];?>
                </div>
                <div class="student-info">Student: <?php echo  $courseworkReport['FirstName'] . ' ' . $courseworkReport['LastName'];?> (<?php echo $courseworkReport['StudentID']; ?>)</div>
                <div class="student-info">Programme: <?php echo $courseworkReport['Programme']; ?></div>
                <div class="student-info">First Marker: <?php echo htmlspecialchars($firstMarkerName); ?></div>
                <div class="student-info">Second Marker: <?php echo htmlspecialchars($secondMarkerName); ?></div>
            </div>
            <div id="report-header"><strong>Total Marks:(<?php echo formatMark($averageMarksCoursework);?>x80%)+(<?php echo formatMark($averageMarksPractice);?>x20%)=<?php echo formatMark($averageTotal);?></strong></div>
            <!-- Coursework Report Table -->
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th width="25%">Component</th>
                        <th width="15%" class="mark-column">Marks</th>
                        <th width="60%">Feedback</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Project Definition and Planning (10%)</td>
                        <td class="mark-column"><?php echo formatMark(calculateAverage($courseworkReport['FM_ProjectDefinition'], $courseworkReport['SM_ProjectDefinition'])); ?></td>
                        <td>

                            <p><?php echo nl2br(htmlspecialchars($courseworkReport['FM_ProjectDefinitionComments'] ?? 'No comments')); ?></p>
                            <strong>==========================================================================</strong>
                            <p><?php echo nl2br(htmlspecialchars($courseworkReport['SM_ProjectDefinitionComments'] ?? 'No comments')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td>Context Review and Subject Knowledge (15%)</td>
                        <td class="mark-column"><?php echo formatMark(calculateAverage($courseworkReport['FM_ContextReview'], $courseworkReport['SM_ContextReview'])); ?></td>
                        <td>

                            <p><?php echo nl2br(htmlspecialchars($courseworkReport['FM_ContextReviewComments'] ?? 'No comments')); ?></p>
                            <strong>==========================================================================</strong>
                            <p><?php echo nl2br(htmlspecialchars($courseworkReport['SM_ContextReviewComments'] ?? 'No comments')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td>Project Methodology and Implementation (50%)</td>
                        <td class="mark-column"><?php echo formatMark(calculateAverage($courseworkReport['FM_Methodology'], $courseworkReport['SM_Methodology'])); ?></td>
                        <td>

                            <p><?php echo nl2br(htmlspecialchars($courseworkReport['FM_MethodologyComments'] ?? 'No comments')); ?></p>
                            <strong>==========================================================================</strong>
                            <p><?php echo nl2br(htmlspecialchars($courseworkReport['SM_MethodologyComments'] ?? 'No comments')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td>Critical Evaluation and Conclusions (15%)</td>
                        <td class="mark-column"><?php echo formatMark(calculateAverage($courseworkReport['FM_Evaluation'], $courseworkReport['SM_Evaluation'])); ?></td>
                        <td>

                            <p><?php echo nl2br(htmlspecialchars($courseworkReport['FM_EvaluationComments'] ?? 'No comments')); ?></p>
                            <strong>==========================================================================</strong>
                            <p><?php echo nl2br(htmlspecialchars($courseworkReport['SM_EvaluationComments'] ?? 'No comments')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td>Structure and Presentation (10%)</td>
                        <td class="mark-column"><?php echo formatMark(calculateAverage($courseworkReport['FM_Structure'], $courseworkReport['SM_Structure'])); ?></td>
                        <td>

                            <p><?php echo nl2br(htmlspecialchars($courseworkReport['FM_StructureComments'] ?? 'No comments')); ?></p>
                            <strong>==========================================================================</strong>
                            <p><?php echo nl2br(htmlspecialchars($courseworkReport['SM_StructureComments'] ?? 'No comments')); ?></p>
                        </td>
                    </tr>
                    <tr class="table-active">
                        <td><strong>TOTAL</strong></td>
                        <td class="mark-column"><strong><?php echo formatMark($averageMarksCoursework); ?></strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
			<div class="page-break"></div>
            <!-- Practice Report Table -->
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th width="25%">Component</th>
                        <th width="15%" class="mark-column">Marks</th>
                        <th width="60   %">Feedback</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Communication of Information (50%)</td>
                        <td class="mark-column"><?php echo formatMark($communicationMark); ?></td>
                        <td>

                            <p><?php echo nl2br(htmlspecialchars($practiceReport['FM_CommunicationComments'] ?? 'No comments')); ?></p>
                            <strong>==========================================================================</strong>
                            <p><?php echo nl2br(htmlspecialchars($practiceReport['SM_CommunicationComments'] ?? 'No comments')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td>Poster Structure and Aesthetics (25%)</td>
                        <td class="mark-column"><?php echo formatMark($posterStructureMark); ?></td>
                        <td>

                            <p><?php echo nl2br(htmlspecialchars($practiceReport['FM_PosterStructureComments'] ?? 'No comments')); ?></p>
                            <strong>==========================================================================</strong>
                            <p><?php echo nl2br(htmlspecialchars($practiceReport['SM_PosterStructureComments'] ?? 'No comments')); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <td>Interview (25%)</td>
                        <td class="mark-column"><?php echo formatMark($interviewMark); ?></td>
                        <td>

                            <p><?php echo nl2br(htmlspecialchars($practiceReport['FM_InterviewComments'] ?? 'No comments')); ?></p>
                            <strong>==========================================================================</strong>
                            <p><?php echo nl2br(htmlspecialchars($practiceReport['SM_InterviewComments'] ?? 'No comments')); ?></p>
                        </td>
                    </tr>
                    <tr class="table-active">
                        <td><strong>TOTAL</strong></td>
                        <td class="mark-column"><strong><?php echo formatMark($averageMarksPractice); ?></strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

        </div>

    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>

<script>
document.getElementById("generate-pdf").addEventListener("click", function(e) {
  e.preventDefault();
  const element = document.getElementById("report-content");
  html2pdf().set({ margin: 5, filename: '<?php echo  $courseworkReport['FirstName'] . '_' . $courseworkReport['LastName'];?>(<?php echo $courseworkReport['StudentID']; ?>)_report.pdf', image: { type: 'jpeg', quality: 1 }, html2canvas: { scale: 3 }, jsPDF: { format: 'a4', orientation: 'portrait' } }).from(element).save();
});
</script>

</html>
