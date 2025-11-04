<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Debug: Check what's in session
error_log("Session data: " . print_r($_SESSION, true));

if (!isset($_SESSION['UserAuthData']) || $_SESSION['UserAuthData']['role'] != 'student') {
    error_log("Redirecting to index.php - UserAuthData not set or not student");
    header("Location: index.php");
    exit();
}

// Assign session data to variables
$UserAuthData = $_SESSION['UserAuthData'];

// Now you can use $UserAuthData - with proper error checking
$student_id = $UserAuthData['userid'] ?? 'Unknown';
$student_name = $UserAuthData['name'] ?? 'Unknown';
$student_email = $UserAuthData['email'] ?? 'Unknown';
$student_department = $UserAuthData['department'] ?? 'Unknown';
$admission_number = $UserAuthData['admission_number'] ?? 'Unknown';
$year_of_admission = $UserAuthData['year_of_admission'] ?? 'Unknown';

include_once(__DIR__ . '/config.php'); // This should define $conn, not $pdo

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-card {
            transition: transform 0.3s;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">CEA Student Portal</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">Welcome, <?php echo htmlspecialchars($student_name); ?></span>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="card dashboard-card text-white bg-primary">
                    <div class="card-body text-center">
                        <h5><i class="fas fa-user"></i> Profile</h5>
                        <a href="student/student_profile.php" class="stretched-link text-white text-decoration-none"></a>
                    </div>
                </div>
            <div class="col-md-3 mb-4">
                <div class="card dashboard-card text-white bg-warning">
                    <div class="card-body text-center">
                        <h5><i class="fas fa-book"></i> Subjects</h5>
                        <a href="student/student_subjects.php" class="stretched-link text-white text-decoration-none"></a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Recent Announcements</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6>Semester Exams Schedule</h6>
                            <p class="mb-0">Final semester exams will commence from March 15, 2015.</p>
                            <small class="text-muted">Posted on: February 10, 2015</small>
                        </div>
                        <div class="alert alert-warning">
                            <h6>Fee Payment Reminder</h6>
                            <p class="mb-0">Last date for fee payment is February 28, 2015.</p>
                            <small class="text-muted">Posted on: February 5, 2015</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Quick Info</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get student basic info - use $conn instead of $pdo
                        try {
                            $stmt = $conn->prepare("SELECT * FROM stud_2014_main WHERE user_id = ?");
                            $stmt->execute([$student_id]);
                            $student = $stmt->fetch();
                            
                            if ($student) {
                                echo "<p><strong>Roll No:</strong> " . htmlspecialchars($student['rollNo'] ?? 'N/A') . "</p>";
                                echo "<p><strong>Department:</strong> " . htmlspecialchars($student['department'] ?? 'N/A') . "</p>";
                                echo "<p><strong>Program:</strong> " . htmlspecialchars($student['program'] ?? 'N/A') . "</p>";
                                echo "<p><strong>Batch:</strong> " . htmlspecialchars($student['batch'] ?? 'N/A') . "</p>";
                            } else {
                                echo "<p class='text-muted'>No additional student information found.</p>";
                                echo "<p><strong>Student ID:</strong> " . htmlspecialchars($student_id) . "</p>";
                                echo "<p><strong>Department:</strong> " . htmlspecialchars($student_department) . "</p>";
                            }
                        } catch (Exception $e) {
                            echo "<p class='text-danger'>Error loading student information.</p>";
                            error_log("Database error: " . $e->getMessage());
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>