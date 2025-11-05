<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in as student
if (!isset($_SESSION['UserAuthData']) || $_SESSION['UserAuthData']['role'] != 'student') {
    header("Location: ../index.php");
    exit();
}

// Get student data from session
$UserAuthData = $_SESSION['UserAuthData'];
$student_user_id = $UserAuthData['userid']; // This is the userid from login_student

// Include database configuration
include_once(__DIR__ . '/../config.php');

// Get student details including the id (primary key)
try {
    $stmt = $conn->prepare("SELECT ls.id as login_id, ls.userid, sm.* 
                           FROM login_student ls 
                           LEFT JOIN stud_2014_main sm ON ls.userid = sm.user_id 
                           WHERE ls.userid = ?");
    $stmt->execute([$student_user_id]);
    $student = $stmt->fetch();

    if (!$student) {
        die("Student not found in database");
    }
    
    $student_login_id = $student['login_id']; // This is the actual primary key
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get current semester
$current_semester = '1';

// Check if registration table exists, create if not
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'student_subject_registration'");
    if ($table_check->rowCount() == 0) {
        // Create the table with correct foreign key
        $create_table_sql = "
            CREATE TABLE `student_subject_registration` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `student_login_id` INT NOT NULL,
                `student_user_id` VARCHAR(50) NOT NULL,
                `subject_code` VARCHAR(12) NOT NULL,
                `subject_code_sub` VARCHAR(5) DEFAULT '',
                `semester` VARCHAR(10) NOT NULL,
                `registered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`student_login_id`) REFERENCES `login_student`(`id`) ON DELETE CASCADE,
                UNIQUE KEY `unique_registration` (`student_user_id`, `subject_code`, `subject_code_sub`, `semester`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        $conn->exec($create_table_sql);
    }
} catch (PDOException $e) {
    error_log("Table creation error: " . $e->getMessage());
}

// Get subjects for the student's course, semester, and batch
try {
    $subjects_stmt = $conn->prepare("
        SELECT sa.*, ss.subj_name, ss.type, ss.hours, l.name as teacher_name
        FROM subject_allotment sa 
        JOIN semester_subject ss ON sa.subject_code = ss.subj_code AND sa.subject_code_sub = ss.subj_code_sub
        JOIN login_staff l ON sa.teacher_id = l.userid
        WHERE sa.department_id = ? AND sa.course = ? AND sa.batch = ? AND sa.semester = ?
    ");
    $subjects_stmt->execute([
        $student['department'] ?? '', 
        $student['branch'] ?? '', 
        $student['batch'] ?? '1',
        $current_semester
    ]);
    $subjects = $subjects_stmt->fetchAll();
} catch (PDOException $e) {
    $subjects = [];
    error_log("Error fetching subjects: " . $e->getMessage());
}

// Handle course registration if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_subjects'])) {
    $registered_subjects = $_POST['subjects'] ?? [];
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Delete existing registrations for this semester
        $delete_stmt = $conn->prepare("
            DELETE FROM student_subject_registration 
            WHERE student_user_id = ? AND semester = ?
        ");
        $delete_stmt->execute([$student_user_id, $current_semester]);
        
        // Insert new registrations with both login_id and user_id
        $insert_stmt = $conn->prepare("
            INSERT INTO student_subject_registration 
            (student_login_id, student_user_id, subject_code, subject_code_sub, semester) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $registration_count = 0;
        foreach ($registered_subjects as $subject_code) {
            // Parse subject code and sub code (format: CODE_SUB or just CODE)
            $parts = explode('_', $subject_code);
            $main_code = $parts[0];
            $sub_code = $parts[1] ?? '';
            
            $insert_stmt->execute([$student_login_id, $student_user_id, $main_code, $sub_code, $current_semester]);
            $registration_count++;
        }
        
        $conn->commit();
        $registration_success = "Successfully registered for $registration_count subjects!";
        
    } catch (PDOException $e) {
        $conn->rollBack();
        $registration_error = "Registration failed: " . $e->getMessage();
        error_log("Registration error: " . $e->getMessage());
    }
}

// Get currently registered subjects
try {
    $registered_stmt = $conn->prepare("
        SELECT subject_code, subject_code_sub 
        FROM student_subject_registration 
        WHERE student_user_id = ? AND semester = ?
    ");
    $registered_stmt->execute([$student_user_id, $current_semester]);
    $registered_subjects = $registered_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $registered_codes = [];
    foreach ($registered_subjects as $reg) {
        $code = $reg['subject_code'] . ($reg['subject_code_sub'] ? '_' . $reg['subject_code_sub'] : '');
        $registered_codes[$code] = true;
    }
} catch (PDOException $e) {
    $registered_codes = [];
    error_log("Error fetching registered subjects: " . $e->getMessage());
}

$has_subjects = count($subjects) > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects - Student Portal</title>
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
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Registration Messages -->
        <?php if (isset($registration_success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $registration_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($registration_error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $registration_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4><i class="fas fa-book-open"></i> Semester <?php echo $current_semester; ?> Subjects</h4>
                        <div>
                            <span class="badge bg-primary me-2">
                                Student ID: <?php echo htmlspecialchars($student_user_id); ?>
                            </span>
                            <span class="badge bg-success">
                                Department: <?php echo htmlspecialchars($student['department'] ?? 'N/A'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($has_subjects): ?>
                            <form method="POST" id="subjectRegistrationForm">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th width="50">Register</th>
                                                <th>Subject Code</th>
                                                <th>Subject Name</th>
                                                <th>Type</th>
                                                <th>Hours/Week</th>
                                                <th>Teacher</th>
                                                <th>Department</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subjects as $subject): 
                                                $subject_code_full = $subject['subject_code'] . ($subject['subject_code_sub'] ? '_' . $subject['subject_code_sub'] : '');
                                                $is_registered = isset($registered_codes[$subject_code_full]);
                                            ?>
                                                <tr class="<?php echo $is_registered ? 'table-success' : ''; ?>">
                                                    <td>
                                                        <input type="checkbox" 
                                                               name="subjects[]" 
                                                               value="<?php echo htmlspecialchars($subject_code_full); ?>"
                                                               <?php echo $is_registered ? 'checked' : ''; ?>
                                                               class="form-check-input">
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong>
                                                        <?php if ($subject['subject_code_sub']): ?>
                                                            <small class="text-muted">(<?php echo htmlspecialchars($subject['subject_code_sub']); ?>)</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($subject['subj_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo htmlspecialchars($subject['type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($subject['hours']); ?> hrs</td>
                                                    <td><?php echo htmlspecialchars($subject['teacher_name'] ?? 'Not Assigned'); ?></td>
                                                    <td><?php echo htmlspecialchars($subject['department_from']); ?></td>
                                                    <td>
                                                        <?php if ($is_registered): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-check"></i> Registered
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Not Registered</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3 text-center">
                                    <button type="submit" name="register_subjects" class="btn btn-success btn-lg">
                                        <i class="fas fa-save"></i> Register Selected Subjects
                                    </button>
                                    <a href="../student_dashboard.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                                    </a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> 
                                No subjects found for current semester (Semester <?php echo $current_semester; ?>).
                                <br>
                                <small class="text-muted">Please contact your department if this seems incorrect.</small>
                            </div>
                            <div class="text-center">
                                <a href="../student_dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($has_subjects): ?>
    <script>
        // Only run this script if the form exists
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('subjectRegistrationForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const checkedBoxes = this.querySelectorAll('input[type="checkbox"]:checked');
                    if (checkedBoxes.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one subject to register.');
                        return false;
                    }
                    
                    return confirm(`Are you sure you want to register for ${checkedBoxes.length} subject(s)?`);
                });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>