<?php
session_start();

// Unserialize user session
$UserAuthData = $_SESSION['UserAuthData'] ?? null;
if (is_string($UserAuthData)) {
    $UserAuthData = unserialize($UserAuthData);
}

// Include database connection
include_once('../../config.php');

$action = $_POST['action'] ?? '';

if ($action === 'fetch_staff_data') {

    $Count = 0;
    $department_code = $_POST['department_code'] ?? '';

    try {
        if ($department_code !== 'all') {
            $stmt = $conn->prepare("SELECT * FROM `login_staff` WHERE department = :department ORDER BY type DESC");
            $stmt->execute([':department' => $department_code]);
        } else {
            $stmt = $conn->prepare("SELECT * FROM `login_staff` ORDER BY department ASC");
            $stmt->execute();
        }

        $staffs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo '<tr>
            <td><strong>#</strong></td>
            <td><strong>Name</strong></td>
            <td><strong>Designation</strong></td>
            <td><strong>Type</strong></td>
            <td><strong>E-Mail</strong></td>
            <td><strong>Phone</strong></td>
            <td><strong>Join Date</strong></td>';

        if (($UserAuthData['role'] ?? '') === 'admin' || ($UserAuthData['role'] ?? '') === 'hod' && $department_code !== 'all') {
            echo '<td><strong>Operation</strong></td>';
        }
        echo '</tr>';

        foreach ($staffs as $row) {
            $Count++;
            $designation = ($row['role'] === 'hod') ? "Head of the Department" : $row['designation'];
            
            echo '<tr>';
            echo '<td>' . $Count . '</td>';
            echo ($department_code !== 'all') 
                ? '<td>' . htmlspecialchars($row['name']) . '</td>'
                : '<td>' . htmlspecialchars($row['name']) . ' (' . htmlspecialchars($row['department']) . ')</td>';
            echo '<td>' . htmlspecialchars($designation) . '</td>';
            echo '<td>' . ucfirst(htmlspecialchars($row['type'])) . '</td>';
            echo '<td>' . htmlspecialchars($row['email']) . '</td>';
            echo '<td>' . htmlspecialchars($row['phone']) . '</td>';
            echo '<td>' . htmlspecialchars($row['joining_date']) . '</td>';

            // Delete button for admin or HOD of that department
            if (($UserAuthData['role'] === 'admin') || ($UserAuthData['role'] === 'hod' && ($UserAuthData['department'] ?? '') === $department_code)) {
                if ($department_code !== 'all') {
                    echo "<td><a href='delete.php?p=delete-staff&id=" . $row['id'] . "'>
                            <button type='button' class='btn btn-danger'>Delete</button></a></td>";
                }
            } elseif ($UserAuthData['role'] === 'hod' && ($UserAuthData['department'] ?? '') !== $department_code) {
                echo "<td>No Permission</td>";
            }

            echo '</tr>';
        }

        // Add New Staff button for eligible users
        if ($department_code !== 'all' && (($UserAuthData['role'] ?? '') === 'admin' || (($UserAuthData['role'] ?? '') === 'hod' && ($UserAuthData['department'] ?? '') === $department_code))) {
            echo '<tr>
                <td colspan="8">
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#AddNewStaff">Add New Staff</button>
                </td>
            </tr>';
        }

    } catch (PDOException $e) {
        echo '<tr><td colspan="8">Error fetching staff: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
    }
}
