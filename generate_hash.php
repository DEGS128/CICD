<?php
// generate_hash.php
// Use this script to generate a new bcrypt hash for testing.

$passwordToHash = 'password123'; // The password you want to use

// Generate the hash using bcrypt default cost
$hashedPassword = password_hash($passwordToHash, PASSWORD_BCRYPT);

if ($hashedPassword === false) {
    echo "Error generating hash.";
} else {
    echo "Password: " . htmlspecialchars($passwordToHash) . "<br>";
    echo "Generated Hash: <pre>" . htmlspecialchars($hashedPassword) . "</pre>";
    echo "<br>Use the generated hash in the SQL UPDATE statement.";
}
?>
