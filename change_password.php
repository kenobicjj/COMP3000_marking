<?php
// change_password.php - Change password on first login

require_once 'includes/config.php';
require_once 'functions.php';

if (isset($_SESSION['temp_email'])) {
    $email = $_SESSION['temp_email'];
} elseif (isset($_SESSION['user_email'])) {
    $email = $_SESSION['user_email'];
} else {
    // Neither session variable is set. Redirect.
    header("Location: login.php?message=no email set");
    exit();
}
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Please enter both password fields";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Passwords do not match";
    } elseif (strlen($newPassword) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        $hashedPassword = hash('sha256', $newPassword);

        $conn = connectDB();
        $stmt = $conn->prepare("UPDATE Marker SET Password = ? WHERE Email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);

        if ($stmt->execute()) {
            $success = "Password changed successfully";
            // Clean up session

            // Redirect after a brief delay
			if (isset($_SESSION['user_email']))
				header("refresh:1;url=dashboard.php?message=Password changed.");
			else{
				unset($_SESSION['temp_email']);
				header("refresh:1;url=login.php");
            }
        } else {
            $error = "Failed to update password";
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Student Marking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h2>Change Password</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-center mb-4"><?php if (isset($_SESSION['temp_email'])) echo "This is your first login.";?> Please change your password.</p>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="post" action="change_password.php">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password:</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password:</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
