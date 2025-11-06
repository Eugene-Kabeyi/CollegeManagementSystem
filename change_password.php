<?php
// Include header first (which starts the session)
include_once('header.php');

// Check if user is logged in and get user data
if (!isset($_SESSION['UserAuthData'])) {
    header('Location: index.php');
    exit();
}

// Handle different possible structures of UserAuthData
$userData = $_SESSION['UserAuthData'];
$userName = '';
$userId = '';
$userRole = 'student';

// Determine user data structure
if (is_array($userData)) {
    $userName = $userData['name'] ?? $userData['username'] ?? 'User';
    $userId = $userData['userid'] ?? $userData['id'] ?? '';
    $userRole = $userData['role'] ?? 'student';
} elseif (is_string($userData)) {
    $userName = $userData;
} else {
    $userName = 'User';
}

$successMessage = '';
$errorMessage = '';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Security token invalid. Please refresh the page and try again.";
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $errorMessage = "Please fill in all fields.";
        } elseif ($new_password !== $confirm_password) {
            $errorMessage = "New passwords do not match.";
        } elseif (strlen($new_password) < 8) {
            $errorMessage = "New password must be at least 8 characters long.";
        } else {
            // Verify current password and update password
            include_once(__DIR__ . '/config.php');
            
            try {
                // Dynamic table mapping based on role
                $tableMapping = [
                    'admin' => 'login_admin',
                    'staff' => 'login_staff', 
                    'student' => 'login_student',
                    'principal' => 'login_college_admin',
                    'manager' => 'login_college_admin',
                    'teacher' => 'login_staff',
                    'faculty' => 'login_staff',
                    'coordinator' => 'login_staff',
                    'director' => 'login_college_admin',
                    'hod' => 'login_staff',
                    'dean' => 'login_college_admin',
                ];
                
                // Determine table name
                $userTable = $tableMapping[$userRole] ?? 'login_student';
                
                if (empty($userId)) {
                    $errorMessage = "User ID not found. Please log in again.";
                } else {
                    $hashedCurrentPassword = md5($current_password);
                    
                    // DEBUG: Log the attempt
                    error_log("Password change attempt - User: $userId, Role: $userRole, Table: $userTable");
                    
                    // Build query based on role
                    $queryParams = [$userId, $hashedCurrentPassword];
                    $query = "SELECT * FROM `$userTable` WHERE userid = ? AND password = ?";
                    
                    $specialRoles = ['principal', 'manager', 'director', 'dean'];
                    if (in_array($userRole, $specialRoles)) {
                        $query .= " AND role = ?";
                        $queryParams[] = $userRole;
                    }
                    
                    $stmt = $conn->prepare($query);
                    $stmt->execute($queryParams);
                    $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$userRecord) {
                        $errorMessage = "Current password is incorrect.";
                        error_log("Current password verification failed for user: $userId");
                    } else {
                        // Update password
                        $hashedNewPassword = md5($new_password);
                        
                        $updateParams = [$hashedNewPassword, $userId];
                        $updateQuery = "UPDATE `$userTable` SET password = ? WHERE userid = ?";
                        
                        if (in_array($userRole, $specialRoles)) {
                            $updateQuery .= " AND role = ?";
                            $updateParams[] = $userRole;
                        }
                        
                        // DEBUG: Log the update attempt
                        error_log("Attempting update: $updateQuery");
                        error_log("Update params: " . print_r($updateParams, true));
                        
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->execute($updateParams);
                        $rowCount = $updateStmt->rowCount();
                        
                        // DEBUG: Log the result
                        error_log("Update result - Rows affected: $rowCount");
                        
                        if ($rowCount > 0) {
                            $successMessage = "Password changed successfully!";
                            $_POST = array();
                            
                            // DEBUG: Log success
                            error_log("Password successfully changed for user: $userId");
                        } else {
                            $errorMessage = "Failed to update password. No changes were made to the database.";
                            error_log("Password update failed - No rows affected for user: $userId");
                            
                            // Additional debugging - check if user exists in table
                            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM `$userTable` WHERE userid = ?");
                            $checkStmt->execute([$userId]);
                            $userExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
                            error_log("User exists check: " . print_r($userExists, true));
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error during password change: " . $e->getMessage());
                $errorMessage = "Database error: " . $e->getMessage();
            } catch (Exception $e) {
                error_log("General error: " . $e->getMessage());
                $errorMessage = "An error occurred. Please try again.";
            }
        }
    }
}

// Pass user data to the view
$viewData = [
    'userName' => $userName,
    'userRole' => $userRole,
    'successMessage' => $successMessage,
    'errorMessage' => $errorMessage,
    'csrfToken' => $_SESSION['csrf_token']
];

// Include the form view
include_once('change_password-form.php');
include_once('footer.php');
?>