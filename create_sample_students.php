<?php
/**
 * create_students_md5.php
 * Create student records in cea_sms.login_student and stud_2014_main using MD5 (testing only).
 *
 * - Edit DB credentials below.
 * - Edit the $students array to add the student(s) you want to create.
 * - Run once, then remove or protect this script.
 */

// ---------- CONFIG ----------
$dbHost   = '127.0.0.1';
$dbName   = 'cea_sms';
$dbUser   = 'root';
$dbPass   = '';      // change to your DB password
$dsn      = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

$use_md5 = true; // <<-- MD5 enabled for testing
// ---------- END CONFIG ----------

// Students to create (plaintext password will be md5()'d)
$students = [
    [
        'admission_number' => 5098,
        'name' => 'John Doe',
        'email' => 'john.doe@student.cea.edu',
        'department' => 'CSE',
        'year_of_admission' => '2014',
        'password' => 'Student123!',
        'gender' => 'Male',
        'program' => 'B.Tech',
        'batch' => '2014-18',
    ],
    [
        'admission_number' => 5099,
        'name' => 'Jane Smith',
        'email' => 'jane.smith@student.cea.edu',
        'department' => 'ECE',
        'year_of_admission' => '2014',
        'password' => 'Student456!',
        'gender' => 'Female',
        'program' => 'B.Tech',
        'batch' => '2014-18',
    ],
    [
        'admission_number' => 5507,
        'name' => 'Robert Johnson',
        'email' => 'robert.johnson@student.cea.edu',
        'department' => 'ME',
        'year_of_admission' => '2014',
        'password' => 'Student789!',
        'gender' => 'Male',
        'program' => 'B.Tech',
        'batch' => '2014-18',
    ],
    [
        'admission_number' => 58718,
        'name' => 'Eugene Kabeyi',
        'email' => 'eugenekabeyi144@gmail.com',
        'department' => 'CSE',
        'year_of_admission' => '2014',
        'password' => 'Student000!',
        'gender' => 'Male',
        'program' => 'B.Tech',
        'batch' => '2014-18',
    ],
];

// ---------- Helper Functions ----------
function generate_userid($name, $prefix = 'stud') {
    $clean_name = preg_replace('/[^a-z0-9]/i', '', strtolower($name));
    $random_part = bin2hex(random_bytes(3)); // 6 characters
    $unique_part = substr(uniqid(), -3); // 3 characters
    $userid = $prefix . substr($clean_name, 0, 8) . $random_part . $unique_part;
    return substr($userid, 0, 50);
}

function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function admission_number_exists($pdo, $admission_number) {
    $stmt = $pdo->prepare("SELECT 1 FROM login_student WHERE admission_number = ? LIMIT 1");
    $stmt->execute([$admission_number]);
    return $stmt->fetch() !== false;
}

function email_exists($pdo, $email) {
    $stmt = $pdo->prepare("SELECT 1 FROM login_student WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    return $stmt->fetch() !== false;
}

function user_id_exists_main($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT 1 FROM stud_2014_main WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->fetch() !== false;
}

function admno_exists_main($pdo, $admno) {
    $stmt = $pdo->prepare("SELECT 1 FROM stud_2014_main WHERE admno = ? LIMIT 1");
    $stmt->execute([$admno]);
    return $stmt->fetch() !== false;
}

function generate_roll_number($admission_number, $index) {
    return 1000 + $index; // Simple sequential roll numbers
}

function truncate_string($string, $max_length) {
    return substr($string, 0, $max_length);
}

// ---------- Main Execution ----------
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage() . "\n");
}

// Prepare insert statement for login_student table
$insertStudentSql = "INSERT INTO login_student
    (userid, admission_number, name, email, password, department, role, year_of_admission, login_log)
    VALUES (:userid, :admission_number, :name, :email, :password, :department, :role, :year_of_admission, :login_log)";
$insertStudentStmt = $pdo->prepare($insertStudentSql);

// Prepare insert for stud_2014_main table
$insertMainSql = "INSERT INTO stud_2014_main
    (user_id, department, branch, program, batch, admno, rollNo, regno, name, sex, address, religion, cast, rsvgroup, fathername, fatheroccupation, yearOfAddmission, dateOfBirth, f_mob, lg_mob, blood_group, income, email, p_email, name_localG)
    VALUES (:user_id, :department, :branch, :program, :batch, :admno, :rollNo, :regno, :name, :sex, :address, :religion, :cast, :rsvgroup, :fathername, :fatheroccupation, :yearOfAddmission, :dateOfBirth, :f_mob, :lg_mob, :blood_group, :income, :email, :p_email, :name_localG)";
$insertMainStmt = $pdo->prepare($insertMainSql);

$success_count = 0;
$error_count = 0;

echo "Starting student creation process...\n";
echo "========================================\n";

foreach ($students as $index => $s) {
    $admission_number = (int)($s['admission_number'] ?? 0);
    $name = trim($s['name'] ?? '');
    $email = trim($s['email'] ?? '');
    $department = trim($s['department'] ?? '');
    $year_of_admission = trim($s['year_of_admission'] ?? '2014');
    $plain = (string)($s['password'] ?? '');
    $gender = $s['gender'] ?? 'Male';
    $program = $s['program'] ?? 'B.Tech';
    $batch = $s['batch'] ?? '2014-18';
    
    $current_student = "Student #" . ($index + 1) . " ({$name})";

    echo "\n{$current_student}:\n";

    // Validation
    if ($admission_number <= 0 || $name === '' || $email === '' || $plain === '' || $department === '') {
        echo "  ❌ SKIPPED: Missing required fields\n";
        $error_count++;
        continue;
    }
    
    if (!is_valid_email($email)) {
        echo "  ❌ SKIPPED: Invalid email '{$email}'\n";
        $error_count++;
        continue;
    }
    
    if (admission_number_exists($pdo, $admission_number)) {
        echo "  ❌ SKIPPED: Admission number {$admission_number} already exists in login_student\n";
        $error_count++;
        continue;
    }
    
    if (email_exists($pdo, $email)) {
        echo "  ❌ SKIPPED: Email '{$email}' already exists in login_student\n";
        $error_count++;
        continue;
    }
    
    if (admno_exists_main($pdo, (string)$admission_number)) {
        echo "  ❌ SKIPPED: Admission number {$admission_number} already exists in stud_2014_main\n";
        $error_count++;
        continue;
    }

    // Password hashing
    if ($use_md5) {
        $pass_to_store = md5($plain);
        echo "  ⚠️  SECURITY: Using MD5 (insecure)\n";
    } else {
        $pass_to_store = password_hash($plain, PASSWORD_DEFAULT);
    }

    // Generate unique userid
    $attempt = 0; $maxAttempts = 8; $userid = null;
    while ($attempt++ < $maxAttempts) {
        $candidate = generate_userid($name, 'stud');
        if (!user_id_exists_main($pdo, $candidate)) { 
            $userid = $candidate; 
            break; 
        }
    }
    
    if ($userid === null) {
        echo "  ❌ SKIPPED: Failed to generate unique userid\n";
        $error_count++;
        continue;
    }

    echo "  ✓ Generated userid: {$userid}\n";

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Prepare data with proper field length constraints
        $login_log = date('Y-m-d H:i:s') . ' - Account created';
        $rollNo = generate_roll_number($admission_number, $index);
        $regno = truncate_string($year_of_admission . $admission_number, 10);
        $admno_str = (string)$admission_number; // Convert to string for stud_2014_main
        
        // Ensure department fits in varchar(10) for login_student
        $department_short = truncate_string($department, 10);

        // Insert into login_student table
        $insertStudentStmt->execute([
            ':userid' => $userid,
            ':admission_number' => $admission_number,
            ':name' => truncate_string($name, 100),
            ':email' => truncate_string($email, 100),
            ':password' => $pass_to_store,
            ':department' => $department_short,
            ':role' => 'student',
            ':year_of_admission' => truncate_string($year_of_admission, 100),
            ':login_log' => $login_log,
        ]);
        
        echo "  ✓ Inserted into login_student\n";

        // Insert into stud_2014_main table with proper data types
        $insertMainStmt->execute([
            ':user_id' => truncate_string($userid, 50),
            ':department' => truncate_string($department, 50),
            ':branch' => truncate_string($department, 50), // Using department as branch
            ':program' => truncate_string($program, 50),
            ':batch' => truncate_string($batch, 50),
            ':admno' => truncate_string($admno_str, 50),
            ':rollNo' => $rollNo,
            ':regno' => truncate_string($regno, 10),
            ':name' => truncate_string($name, 100),
            ':sex' => truncate_string($gender, 20),
            ':address' => truncate_string('University Campus, Department of ' . $department, 500),
            ':religion' => truncate_string('Not Specified', 20),
            ':cast' => truncate_string('General', 20),
            ':rsvgroup' => truncate_string('No', 20),
            ':fathername' => truncate_string('Father of ' . $name, 50),
            ':fatheroccupation' => truncate_string('Business', 50),
            ':yearOfAddmission' => truncate_string($year_of_admission, 20),
            ':dateOfBirth' => truncate_string('1996-01-01', 20),
            ':f_mob' => truncate_string('9876543210', 15),
            ':lg_mob' => truncate_string('9876543210', 15),
            ':blood_group' => truncate_string('O+', 20),
            ':income' => truncate_string('500000', 20),
            ':email' => truncate_string($email, 100),
            ':p_email' => truncate_string('parent.' . $email, 100),
            ':name_localG' => truncate_string('Guardian of ' . $name, 100),
        ]);

        $pdo->commit();
        
        echo "  ✅ SUCCESS: Created student '{$name}'\n";
        echo "     - Admission: {$admission_number}\n";
        echo "     - User ID: {$userid}\n";
        echo "     - Department: {$department}\n";
        echo "     - Roll No: {$rollNo}\n";
        
        $success_count++;
        
    } catch (PDOException $ex) {
        $pdo->rollBack();
        echo "  ❌ DB ERROR: " . $ex->getMessage() . "\n";
        $error_count++;
    }
}

// Summary
echo "\n========================================\n";
echo "PROCESSING COMPLETE\n";
echo "========================================\n";
echo "✅ Successfully created: {$success_count} students\n";
echo "❌ Errors encountered: {$error_count} students\n";
echo "📊 Total processed: " . count($students) . " students\n";

// Display recent students
if ($success_count > 0) {
    echo "\n=== RECENTLY CREATED STUDENTS ===\n";
    try {
        $stmt = $pdo->query("
            SELECT admission_number, name, email, department, year_of_admission 
            FROM login_student 
            ORDER BY id DESC 
            LIMIT 10
        ");
        $recentStudents = $stmt->fetchAll();
        
        if ($recentStudents) {
            foreach ($recentStudents as $student) {
                echo "🎓 Adm No: {$student['admission_number']} | ";
                echo "Name: {$student['name']} | ";
                echo "Dept: {$student['department']} | ";
                echo "Email: {$student['email']}\n";
            }
        }
    } catch (PDOException $e) {
        echo "Note: Could not fetch summary: " . $e->getMessage() . "\n";
    }
}

// Security warning
if ($use_md5) {
    echo "\n🔴 SECURITY WARNING:\n";
    echo "   MD5 hashing is being used which is INSECURE for production.\n";
    echo "   This script should only be used for testing/development.\n";
    echo "   Remove or disable this script after use.\n";
}

echo "\n";
?>