<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include_once(__DIR__ . '/../../config.php');

// Function to generate random string
function random_string($length) {
    $key = '';
    $keys = array_merge(range(0, 9), range('a', 'z'));
    for ($i = 0; $i < $length; $i++) {
        $key .= $keys[array_rand($keys)];
    }
    return $key;
}

// Set content type
header('Content-Type: application/json');

// Only process POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_new_staff') {
        // Get POST data with proper sanitization
        $department = trim($_POST['department'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $joindate = trim($_POST['joindate'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $type = trim($_POST['type'] ?? '');

        // Validate required fields
        $required_fields = ['department', 'name', 'dob', 'email', 'phone', 'joindate', 'designation', 'type'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty(trim($_POST[$field] ?? ''))) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            http_response_code(400);
            echo json_encode([
                'response' => 'error',
                'message' => 'Missing required fields: ' . implode(', ', $missing_fields)
            ]);
            exit;
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'response' => 'error',
                'message' => 'Invalid email format'
            ]);
            exit;
        }

        // Clean and validate phone number
        $cleaned_phone = preg_replace('/\D/', '', $phone);
        if (strlen($cleaned_phone) !== 10) {
            http_response_code(400);
            echo json_encode([
                'response' => 'error',
                'message' => 'Phone number must be 10 digits'
            ]);
            exit;
        }

        // Determine role
        $role = (strtolower($designation) === 'head of the department') ? 'hod' : 'staff';

        // Generate password
        $password_plain = strtolower(str_replace(' ', '', $name)) . $cleaned_phone;
        $password_hashed = md5($password_plain);

        // Generate unique userid
        $userid = rand(123, 1795) . strtolower(preg_replace('/\s+/', '', $name)) . random_string(5);

        try {
            // Check if email already exists
            $check_email = $conn->prepare("SELECT COUNT(*) FROM login_staff WHERE email = :email");
            $check_email->execute([':email' => $email]);
            if ($check_email->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode([
                    'response' => 'error',
                    'message' => 'Email already exists'
                ]);
                exit;
            }

            // Check if phone number already exists
            $check_phone = $conn->prepare("SELECT COUNT(*) FROM login_staff WHERE phone = :phone");
            $check_phone->execute([':phone' => $cleaned_phone]);
            if ($check_phone->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode([
                    'response' => 'error', 
                    'message' => 'Phone number already exists'
                ]);
                exit;
            }

            // First, let's check the table structure to see what fields we have
            $table_check = $conn->prepare("DESCRIBE login_staff");
            $table_check->execute();
            $columns = $table_check->fetchAll(PDO::FETCH_COLUMN);
            
            error_log("Table columns: " . implode(', ', $columns));

            // Insert the new staff member - handle the login_log field
            if (in_array('login_log', $columns)) {
                // If login_log column exists, include it with a default value
                $stmt = $conn->prepare("INSERT INTO `login_staff` 
                    (userid, name, date_of_birth, email, phone, password, role, designation, type, department, joining_date, login_log) 
                    VALUES (:userid, :name, :dob, :email, :phone, :password, :role, :designation, :type, :department, :joindate, :login_log)");

                $result = $stmt->execute([
                    ':userid'      => $userid,
                    ':name'        => $name,
                    ':dob'         => $dob,
                    ':email'       => $email,
                    ':phone'       => $cleaned_phone,
                    ':password'    => $password_hashed,
                    ':role'        => $role,
                    ':designation' => $designation,
                    ':type'        => $type,
                    ':department'  => $department,
                    ':joindate'    => $joindate,
                    ':login_log'   => '[]' // Default empty JSON array or adjust as needed
                ]);
            } else {
                // If login_log column doesn't exist, use the original query
                $stmt = $conn->prepare("INSERT INTO `login_staff` 
                    (userid, name, date_of_birth, email, phone, password, role, designation, type, department, joining_date) 
                    VALUES (:userid, :name, :dob, :email, :phone, :password, :role, :designation, :type, :department, :joindate)");

                $result = $stmt->execute([
                    ':userid'      => $userid,
                    ':name'        => $name,
                    ':dob'         => $dob,
                    ':email'       => $email,
                    ':phone'       => $cleaned_phone,
                    ':password'    => $password_hashed,
                    ':role'        => $role,
                    ':designation' => $designation,
                    ':type'        => $type,
                    ':department'  => $department,
                    ':joindate'    => $joindate
                ]);
            }

            if ($result) {
                echo json_encode([
                    'response' => 'success',
                    'pass'     => $password_plain,
                    'userid'   => $userid,
                    'message'  => 'Staff member added successfully'
                ]);
            } else {
                throw new Exception('Failed to insert record');
            }

        } catch (PDOException $e) {
            error_log("Staff Registration PDO Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'response' => 'error',
                'message'  => 'Database error: Unable to register staff member.',
                'debug' => $e->getMessage()
            ]);
        } catch (Exception $e) {
            error_log("Staff Registration Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'response' => 'error',
                'message'  => 'System error: Please try again.'
            ]);
        }
    } else {
        http_response_code(400);
        echo json_encode([
            'response' => 'error',
            'message' => 'Invalid action'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'response' => 'error', 
        'message' => 'Method not allowed'
    ]);
}
exit;
?>