<?php
// Add detailed error reporting at the very top
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start output buffering to catch any unexpected output
ob_start();

include_once('../../config.php');
session_start();

// Function to send consistent JSON responses
function sendResponse($success, $message = '', $data = []) {
    $response = [
        'response' => $success ? 'success' : 'error',
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Check authentication
if (!isset($_SESSION['UserAuthData'])) {
    error_log("Unauthorized access attempt");
    sendResponse(false, 'Unauthorized access');
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    error_log("CSRF token validation failed");
    sendResponse(false, 'CSRF token validation failed');
}

// Check if action is provided
$action = $_POST['action'] ?? '';
if (empty($action)) {
    error_log("No action specified in request");
    sendResponse(false, 'No action specified');
}

// Validate and sanitize input
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Check database connection
if (!isset($conn)) {
    error_log("Database connection not established");
    sendResponse(false, 'Database connection error');
}

try {
    // Verify database connection is alive
    $conn->query("SELECT 1");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    sendResponse(false, 'Database connection failed');
}

if ($action == 'register-new-batch') {
    error_log("Starting batch registration process");
    
    // Validate required fields
    $required_fields = ['program_name', 'year_of_admission', 'acadamic_scheme'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        error_log("Missing required fields: " . implode(', ', $missing_fields));
        sendResponse(false, 'Missing required fields: ' . implode(', ', $missing_fields));
    }

    $program_name = sanitizeInput($_POST['program_name']);
    $year_of_admission = sanitizeInput($_POST['year_of_admission']);
    $academic_scheme = sanitizeInput($_POST['acadamic_scheme']);
    $current_semester = '1';

    error_log("Processing batch: $program_name, $year_of_admission, $academic_scheme");

    // Validate year format
    if (!preg_match('/^\d{4}$/', $year_of_admission)) {
        error_log("Invalid year format: $year_of_admission");
        sendResponse(false, 'Invalid year format. Please use 4-digit year.');
    }

    // Validate year range (2000-2030)
    if ($year_of_admission < 2000 || $year_of_admission > 2030) {
        error_log("Year out of range: $year_of_admission");
        sendResponse(false, 'Year must be between 2000 and 2030.');
    }

    // Check if batch already exists
    try {
        $check_stmt = $conn->prepare("SELECT id FROM academic_data WHERE course = ? AND admission_year = ?");
        $check_stmt->execute([$program_name, $year_of_admission]);
        if ($check_stmt->rowCount() > 0) {
            error_log("Batch already exists: $program_name - $year_of_admission");
            sendResponse(false, 'Batch already exists for this program and year.');
        }
    } catch (Exception $e) {
        error_log("Batch check error: " . $e->getMessage());
        sendResponse(false, 'Error checking existing batches');
    }

    // Start transaction
    $conn->beginTransaction();

    try {
        error_log("Inserting academic data into database");
        
        // Insert academic data - include ALL required fields
        $stmt = $conn->prepare("INSERT INTO `academic_data` (course, admission_year, university_scheme, current_semester, semester_starting_date, semester_ending_date) VALUES (?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare INSERT statement: " . implode(', ', $conn->errorInfo()));
        }

        // Set default dates for the first semester
        $default_start_date = $year_of_admission . '-07-01'; // July 1st of admission year
        $default_end_date = $year_of_admission . '-12-31';   // December 31st of admission year
        
        error_log("Executing INSERT with dates: $default_start_date to $default_end_date");
        
        $result = $stmt->execute([$program_name, $year_of_admission, $academic_scheme, $current_semester, $default_start_date, $default_end_date]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Failed to execute INSERT: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        $batch_id = $conn->lastInsertId();
        error_log("Successfully inserted academic data with ID: $batch_id");

        // Validate table name to prevent SQL injection
        $table_suffix = preg_replace('/[^0-9]/', '', $year_of_admission);
        if (empty($table_suffix) || strlen($table_suffix) !== 4) {
            throw new Exception("Invalid year format for table creation: $year_of_admission");
        }

        error_log("Creating student tables for year: $table_suffix");

        // Create tables with proper error handling
        $tables_created = createStudentTables($conn, $table_suffix);
        
        if (!$tables_created) {
            throw new Exception("Failed to create one or more student tables");
        }

        // Commit transaction
        $conn->commit();
        error_log("Transaction committed successfully");
        
        sendResponse(true, 'Batch registered successfully', [
            'batch_id' => $batch_id,
            'tables_created' => [
                "stud_{$table_suffix}_main",
                "stud_{$table_suffix}_attendance", 
                "stud_{$table_suffix}_data"
            ]
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollBack();
        error_log("Transaction rolled back due to error: " . $e->getMessage());
        
        sendResponse(false, 'Failed to register batch: ' . $e->getMessage());
    }

} else if ($action == 'update_semester') {
    error_log("Starting semester update process");
    
    // Validate required fields
    $required_fields = ['update_id', 'update_semester', 'update_start_date', 'update_end_date'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        error_log("Missing required fields for semester update: " . implode(', ', $missing_fields));
        sendResponse(false, 'Missing required fields: ' . implode(', ', $missing_fields));
    }

    $update_id = sanitizeInput($_POST['update_id']);
    $update_semester = sanitizeInput($_POST['update_semester']);
    $update_start_date = sanitizeInput($_POST['update_start_date']);
    $update_end_date = sanitizeInput($_POST['update_end_date']);

    error_log("Updating semester: ID=$update_id, Semester=$update_semester, Dates=$update_start_date to $update_end_date");

    // Validate ID is numeric
    if (!is_numeric($update_id) || $update_id <= 0) {
        error_log("Invalid ID format: $update_id");
        sendResponse(false, 'Invalid ID format');
    }

    // Validate semester (0-10, where 0 means "Passout")
    if (!preg_match('/^[0-9]|10$/', $update_semester)) {
        error_log("Invalid semester: $update_semester");
        sendResponse(false, 'Invalid semester. Must be between 0-10.');
    }

    // Validate date format and logic
    if (!validateDate($update_start_date) || !validateDate($update_end_date)) {
        error_log("Invalid date format: $update_start_date or $update_end_date");
        sendResponse(false, 'Invalid date format. Use YYYY-MM-DD.');
    }

    // Validate date logic
    $start_date = new DateTime($update_start_date);
    $end_date = new DateTime($update_end_date);
    
    if ($end_date <= $start_date) {
        error_log("End date not after start date: $update_start_date to $update_end_date");
        sendResponse(false, 'End date must be after start date.');
    }

    try {
        error_log("Executing semester update query");
        
        $stmt = $conn->prepare("UPDATE `academic_data` SET current_semester = ?, semester_starting_date = ?, semester_ending_date = ? WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare UPDATE statement: " . implode(', ', $conn->errorInfo()));
        }
        
        $result = $stmt->execute([$update_semester, $update_start_date, $update_end_date, $update_id]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Failed to execute UPDATE: " . ($errorInfo[2] ?? 'Unknown error'));
        }

        $affected_rows = $stmt->rowCount();
        error_log("Semester update successful. Affected rows: $affected_rows");
        
        if ($affected_rows === 0) {
            sendResponse(false, 'No record found with the specified ID');
        } else {
            sendResponse(true, 'Semester updated successfully', [
                'updated_id' => $update_id
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Semester update error: " . $e->getMessage());
        sendResponse(false, 'Failed to update semester: ' . $e->getMessage());
    }

} else {
    error_log("Invalid action specified: $action");
    sendResponse(false, 'Invalid action specified');
}

/**
 * Create student tables for a given year
 */
function createStudentTables($conn, $year_suffix) {
    error_log("Creating student tables for year suffix: $year_suffix");
    
    $table_definitions = [
        "stud_{$year_suffix}_main" => "
            CREATE TABLE IF NOT EXISTS `stud_{$year_suffix}_main` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `department` varchar(50) NOT NULL,
                `branch` varchar(50) NOT NULL,
                `program` varchar(50) NOT NULL,
                `batch` varchar(50) NOT NULL,
                `admno` varchar(50) NOT NULL,
                `rollNo` int(10) NOT NULL,
                `regno` varchar(10) NOT NULL,
                `name` varchar(100) NOT NULL,
                `sex` varchar(20) NOT NULL,
                `address` varchar(500) NOT NULL,
                `religion` varchar(20) NOT NULL,
                `cast` varchar(20) NOT NULL,
                `rsvgroup` varchar(20) NOT NULL,
                `fathername` varchar(50) NOT NULL,
                `fatheroccupation` varchar(50) NOT NULL,
                `yearOfAddmission` varchar(20) NOT NULL,
                `dateOfBirth` varchar(20) NOT NULL,
                `f_mob` varchar(15) NOT NULL,
                `lg_mob` varchar(15) NOT NULL,
                `currentsem` varchar(20) NOT NULL,
                `blood_group` varchar(20) NOT NULL,
                `income` varchar(20) NOT NULL,
                `email` varchar(100) NOT NULL,
                `p_email` varchar(100) NOT NULL,
                `name_localG` varchar(100) NOT NULL,
                `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `admno` (`admno`),
                UNIQUE KEY `email` (`email`),
                INDEX `program_batch` (`program`, `batch`),
                INDEX `department_branch` (`department`, `branch`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1
        ",
        
        "stud_{$year_suffix}_attendance" => "
            CREATE TABLE IF NOT EXISTS `stud_{$year_suffix}_attendance` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `department` varchar(50) NOT NULL,
                `course` varchar(50) NOT NULL,
                `branch` varchar(50) NOT NULL,
                `semester` varchar(10) NOT NULL,
                `batch` varchar(10) NOT NULL DEFAULT '1',
                `date` date NOT NULL,
                `period` varchar(20) NOT NULL,
                `subject` varchar(20) NOT NULL,
                `type` varchar(10) NOT NULL DEFAULT 'TH',
                `duration` int(10) NOT NULL DEFAULT '1',
                `teacher` varchar(10) NOT NULL,
                `from` varchar(10) NOT NULL,
                `to` varchar(10) NOT NULL,
                `absents` varchar(500) NOT NULL,
                `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `date_subject` (`date`, `subject`),
                INDEX `batch_semester` (`batch`, `semester`),
                INDEX `teacher_date` (`teacher`, `date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1
        ",
        
        "stud_{$year_suffix}_data" => "
            CREATE TABLE IF NOT EXISTS `stud_{$year_suffix}_data` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `add_no` varchar(20) NOT NULL,
                `subject_code` varchar(20) NOT NULL,
                `category` varchar(20) NOT NULL,
                `value` varchar(20) NOT NULL,
                `remark` varchar(20) NOT NULL,
                `batch` varchar(10) NOT NULL DEFAULT '1',
                `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `add_no_subject` (`add_no`, `subject_code`),
                INDEX `batch_category` (`batch`, `category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=1
        "
    ];

    foreach ($table_definitions as $table_name => $create_sql) {
        try {
            // Check if table already exists
            $check = $conn->query("SHOW TABLES LIKE '$table_name'");
            if ($check && $check->rowCount() > 0) {
                error_log("Table $table_name already exists, skipping creation");
                continue;
            }
            
            error_log("Creating table: $table_name");
            $result = $conn->exec($create_sql);
            
            if ($result === false) {
                error_log("Failed to create table $table_name");
                return false;
            }
            
            error_log("Successfully created table: $table_name");
        } catch (PDOException $e) {
            error_log("Failed to create table {$table_name}: " . $e->getMessage());
            return false;
        }
    }
    
    return true;
}

/**
 * Validate date format
 */
function validateDate($date, $format = 'Y-m-d') {
    if ($date === '0000-00-00' || $date === '') {
        return false;
    }
    
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// End output buffering and clean any unexpected output
if (ob_get_length()) {
    $unexpected_output = ob_get_clean();
    if (!empty(trim($unexpected_output))) {
        error_log("Unexpected output detected: " . $unexpected_output);
        sendResponse(false, 'Server error: Unexpected output');
    }
}
?>