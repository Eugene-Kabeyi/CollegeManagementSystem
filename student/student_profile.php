<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once('session_manager.php');
SessionManager::start(30); // 30 minutes timeout
session_start();

// Debug: Check what's in session
error_log("Session data: " . print_r($_SESSION, true));

if (!isset($_SESSION['UserAuthData']) || $_SESSION['UserAuthData']['role'] != 'student') {
    error_log("Redirecting to index.php - UserAuthData not set or not student");
    header("Location: ../index.php");
    exit();
}

// Get student data from session - FIXED: Use UserAuthData, not student_id directly
$UserAuthData = $_SESSION['UserAuthData'];
$student_id = $UserAuthData['userid'] ?? '';

// Include database configuration
include_once(__DIR__ . '/../config.php');

// Get student details - FIXED: Use $conn instead of $pdo
try {
    $stmt = $conn->prepare("SELECT * FROM stud_2014_main WHERE user_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        die("Student not found in database");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="student_dashboard.php">
            <i class="fas fa-graduation-cap"></i> CEA Student Portal
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="../student_dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-cog"></i> Account
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="../change_password.php">
                        <i class="fas fa-key me-2"></i> Change Password
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4>Student Profile</h4>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Name:</strong> <?php echo htmlspecialchars($student['name'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Admission Number:</strong> <?php echo htmlspecialchars($student['admno'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Roll No:</strong> <?php echo htmlspecialchars($student['rollNo'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Registration No:</strong> <?php echo htmlspecialchars($student['regno'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Department:</strong> <?php echo htmlspecialchars($student['department'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Program:</strong> <?php echo htmlspecialchars($student['program'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Gender:</strong> <?php echo htmlspecialchars($student['sex'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Date of Birth:</strong> <?php echo htmlspecialchars($student['dateOfBirth'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Email:</strong> <?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Phone:</strong> <?php echo htmlspecialchars($student['lg_mob'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-12">
                                <strong>Address:</strong> <?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Father's Name:</strong> <?php echo htmlspecialchars($student['fathername'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Father's Occupation:</strong> <?php echo htmlspecialchars($student['fatheroccupation'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Blood Group:</strong> <?php echo htmlspecialchars($student['blood_group'] ?? 'N/A'); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Religion:</strong> <?php echo htmlspecialchars($student['religion'] ?? 'N/A'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>