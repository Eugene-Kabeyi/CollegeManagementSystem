<?php
include_once('../../config.php'); // assumes $conn is a PDO connection

header('Content-Type: application/json'); // force JSON output

$action = $_POST['action'] ?? '';

if (!$action) {
    echo json_encode(['response' => 'No action specified']);
    exit;
}

// Helper function to sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// ============================
// ADD NEW SUBJECT
// ============================
if ($action == 'add_new_subject') {
    $department = sanitize($_POST['department'] ?? '');
    $program = sanitize($_POST['program'] ?? '');
    $course = sanitize($_POST['course'] ?? '');
    $scheme = sanitize($_POST['scheme'] ?? '');
    $semester = sanitize($_POST['semester'] ?? '');
    $code = sanitize($_POST['code'] ?? '');
    $code_sub = sanitize($_POST['code_sub'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    $type = sanitize($_POST['type'] ?? '');
    $hours = intval($_POST['hours'] ?? 0);
    $internal = intval($_POST['internal'] ?? 0);
    $external = intval($_POST['external'] ?? 0);

    try {
        $stmt = $conn->prepare("
            INSERT INTO `semester_subject`
            (subj_code, subj_name, type, subj_code_sub, department, scheme, semester, course, program, hours, in_mark, ex_mark)
            VALUES (:code, :name, :type, :code_sub, :department, :scheme, :semester, :course, :program, :hours, :internal, :external)
        ");

        $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':type' => $type,
            ':code_sub' => $code_sub,
            ':department' => $department,
            ':scheme' => $scheme,
            ':semester' => $semester,
            ':course' => $course,
            ':program' => $program,
            ':hours' => $hours,
            ':internal' => $internal,
            ':external' => $external
        ]);

        echo json_encode(['response' => 'success']);
    } catch (PDOException $e) {
        echo json_encode(['response' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================
// ALLOT STAFF
// ============================
elseif ($action == 'allot_staff') {
    $program_name = sanitize($_POST['program_name'] ?? '');
    $course = sanitize($_POST['course'] ?? '');
    $batch = sanitize($_POST['batch'] ?? '');
    $semester = sanitize($_POST['semester'] ?? '');
    $course_code = sanitize($_POST['course_code'] ?? '');
    $course_code_sub = sanitize($_POST['course_code_sub'] ?? '');
    $scheme = sanitize($_POST['scheme'] ?? '');
    $department_from = sanitize($_POST['department_from'] ?? '');
    $alloted_staff = sanitize($_POST['alloted_staff'] ?? '');
    $department = sanitize($_POST['department'] ?? '');

    try {
        // Check if record exists
        $checkStmt = $conn->prepare("
            SELECT * FROM `subject_allotment`
            WHERE subject_code = :course_code
              AND subject_code_sub = :course_code_sub
              AND course = :course
              AND department_id = :department
              AND program = :program_name
              AND batch = :batch
              AND semester = :semester
              AND scheme = :scheme
        ");
        $checkStmt->execute([
            ':course_code' => $course_code,
            ':course_code_sub' => $course_code_sub,
            ':course' => $course,
            ':department' => $department,
            ':program_name' => $program_name,
            ':batch' => $batch,
            ':semester' => $semester,
            ':scheme' => $scheme
        ]);

        if ($checkStmt->rowCount() > 0) {
            // Update existing
            $updateStmt = $conn->prepare("
                UPDATE `subject_allotment`
                SET teacher_id = :alloted_staff
                WHERE subject_code = :course_code
                  AND subject_code_sub = :course_code_sub
                  AND course = :course
                  AND department_id = :department
                  AND program = :program_name
                  AND batch = :batch
                  AND semester = :semester
                  AND scheme = :scheme
            ");
            $updateStmt->execute([
                ':alloted_staff' => $alloted_staff,
                ':course_code' => $course_code,
                ':course_code_sub' => $course_code_sub,
                ':course' => $course,
                ':department' => $department,
                ':program_name' => $program_name,
                ':batch' => $batch,
                ':semester' => $semester,
                ':scheme' => $scheme
            ]);

            echo json_encode(['response' => 'success']);
        } else {
            // Insert new
            $insertStmt = $conn->prepare("
                INSERT INTO `subject_allotment`
                (department_id, course, program, batch, semester, subject_code, subject_code_sub, department_from, teacher_id, scheme)
                VALUES (:department, :course, :program_name, :batch, :semester, :course_code, :course_code_sub, :department_from, :alloted_staff, :scheme)
            ");
            $insertStmt->execute([
                ':department' => $department,
                ':course' => $course,
                ':program_name' => $program_name,
                ':batch' => $batch,
                ':semester' => $semester,
                ':course_code' => $course_code,
                ':course_code_sub' => $course_code_sub,
                ':department_from' => $department_from,
                ':alloted_staff' => $alloted_staff,
                ':scheme' => $scheme
            ]);

            echo json_encode(['response' => 'success']);
        }

    } catch (PDOException $e) {
        echo json_encode(['response' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ============================
// INVALID ACTION
// ============================
else {
    echo json_encode(['response' => 'Invalid action']);
    exit;
}
?>
