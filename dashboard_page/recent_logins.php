<?php
// ✅ Assume $UserAuthData['userid'] is set from your session

try {
    // Prepare the SQL query
    $stmt = $conn->prepare(
        "SELECT ip, os, browser, timestamp 
         FROM log_login 
         WHERE userid = :userid 
         ORDER BY id DESC 
         LIMIT 5"
    );

    // Bind the parameter safely
    $stmt->bindParam(':userid', $UserAuthData['userid'], PDO::PARAM_INT);

    // Execute the query
    $stmt->execute();

    // Fetch all results
    $recentLogins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Loop and display
    foreach ($recentLogins as $row) {
        $date = date("F j, Y, g:i A", strtotime($row['timestamp']));
        echo '<tr>';
            echo '<td>' . htmlspecialchars($row['ip']) . '</td>';
            echo '<td>' . htmlspecialchars($row['os']) . '</td>';
            echo '<td>' . htmlspecialchars($row['browser']) . '</td>';
            echo '<td>' . $date . '</td>';
        echo '</tr>';
    }
} catch (PDOException $e) {
    echo "<tr><td colspan='4'>❌ Error fetching login data: " . $e->getMessage() . "</td></tr>";
}
?>
