<?php
// login.php - User authentication
require_once 'includes/config.php';
require_once 'functions.php';
redirectIfLoggedIn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        $conn = connectDB();
        $stmt = $conn->prepare("SELECT Email, Name, Password, Administrator, LastLogin FROM Marker WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // Verify password
            if (hash('sha256', $password) === $user['Password']) {
                // Check if it's the first login (password is hash of email)
                $defaultPassword = hash('sha256', $email);
                if ($user['Password'] === $defaultPassword) {
                    // Store email in session for password change
                    $_SESSION['temp_email'] = $email;
                    header("Location: change_password.php");
                    exit();
                }

                // Login successful
                $_SESSION['user_email'] = $user['Email'];
                $_SESSION['user_name'] = $user['Name'];
                $_SESSION['is_admin'] = (bool)$user['Administrator'];

                // Update last login time
                $stmt = $conn->prepare("UPDATE Marker SET LastLogin = NOW() WHERE Email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
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
    <title>Login - COMP3000 Marking System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h2>COMP3000 Student Marking System</h2>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="post" action="login.php">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email:</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password:</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Login</button>
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
