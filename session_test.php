<?php
// Set a short timeout for execution so we don't hang forever if verify fails
set_time_limit(5);

echo "<h1>Session Test</h1>";
echo "<p>1. Starting session...</p>";
flush();

// Attempt to start session
if (session_start(['read_and_close' => true])) {
    echo "<p style='color:green'>2. Session Check Passed!</p>";
} else {
    echo "<p style='color:red'>2. Session Failed to Start.</p>";
}

echo "<p>3. Done.</p>";
?>
