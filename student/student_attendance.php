<?php
session_start();
if (!isset($_SESSION['student_id']) || $_SESSION['role'] != 'student') {
    header("Location: student_login.php");
    exit();
}

include 'db_connection.php';

$student_id = $_SESSION['student_id'];

// Get student details
$stmt = $pdo->prepare("SELECT * FROM stud_2014_main WHERE user_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Get attendance data (you'll need to modify this based on your actual attendance table structure)
$attendance_stmt = $pdo->prepare("
    SELECT * FROM stud_2014_attendance 
    WHERE department = ? AND course = ? AND batch = ?
    ORDER BY date DESC
    LIMIT 20
");
$attendance_stmt->execute([$student['department'], $student['branch'], $student['batch']]);
$attendance_records = $attendance_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="student_dashboard.php">CEA Student Portal</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="student_dashboard.php">Dashboard</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Attendance Records</h4>
                    </div>
                    <div class="card-body">
                        <?php if (count($attendance_records) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Subject</th>
                                            <th>Period</th>
                                            <th>Teacher</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_records as $record): 
                                            // Check if student was absent in this record
                                            $absents = explode(',', $record['absents']);
                                            $is_absent = in_array($student['admno'], $absents);
                                        ?>
                                            <tr>
                                                <td><?php echo $record['date']; ?></td>
                                                <td><?php echo $record['subject']; ?></td>
                                                <td><?php echo $record['period']; ?></td>
                                                <td><?php echo $record['teacher']; ?></td>
                                                <td>
                                                    <?php if ($is_absent): ?>
                                                        <span class="badge bg-danger">Absent</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Present</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No attendance records found.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>