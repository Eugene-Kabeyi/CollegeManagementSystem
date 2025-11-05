<?php
// logout.php - Simple version with JavaScript confirm
session_start();

echo '<script>
    if (confirm("Are you sure you want to logout?")) {
        window.location.href = "do_logout.php";
    } else {
        window.history.back();
    }
</script>';
exit();
?>