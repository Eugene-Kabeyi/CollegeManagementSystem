<?php
// test_mail_bypass_xampp.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h3>Testing mail() with XAMPP library bypass</h3>";

// Method 1: Use full system path with LD_LIBRARY_PATH unset
$to = "eugenekabeyi144@gmail.com";
$subject = "Bypass XAMPP Test - " . date('Y-m-d H:i:s');
$message = "Testing mail with XAMPP library bypass";

$headers = "From: genedyce238@gmail.com\r\n" .
           "Reply-To: genedyce238@gmail.com\r\n";

// Unset LD_LIBRARY_PATH to avoid XAMPP libraries
putenv("LD_LIBRARY_PATH");

error_clear_last();
$result = mail($to, $subject, $message, $headers);

echo "Mail result: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . "<br>";

if (!$result) {
    $error = error_get_last();
    echo "Error: " . ($error['message'] ?? 'Unknown error') . "<br>";
}

// Method 2: Direct system call with clean environment
echo "<h4>Method 2: Direct system call</h4>";
$mail_content = "To: $to\nSubject: Direct System Test\nFrom: genedyce238@gmail.com\n\n$message";
$cmd = "echo " . escapeshellarg($mail_content) . " | /usr/sbin/sendmail -t -i -f genedyce238@gmail.com 2>&1";
$output = shell_exec("unset LD_LIBRARY_PATH; $cmd");
echo "Output: " . ($output ?: 'No output') . "<br>";
?>