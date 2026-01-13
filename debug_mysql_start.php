<?php
// debug_mysql_start.php
// Attempt to start MySQL directly and capture output

$mysqld = "C:\\xampp\\mysql\\bin\\mysqld.exe";
$ini = "C:\\xampp\\mysql\\bin\\my.ini";

if (!file_exists($mysqld)) {
    die("Error: mysqld.exe not found at $mysqld");
}

$cmd = "\"$mysqld\" --defaults-file=\"$ini\" --console";

echo "Attempting to run: $cmd\n";
echo "---------------------------------------------------\n";

$descriptorspec = [
    0 => ["pipe", "r"],  // stdin
    1 => ["pipe", "w"],  // stdout
    2 => ["pipe", "w"]   // stderr
];

$process = proc_open($cmd, $descriptorspec, $pipes);

if (is_resource($process)) {
    // Set streams to non-blocking
    stream_set_blocking($pipes[1], 0);
    stream_set_blocking($pipes[2], 0);

    $startTime = time();
    $output = "";
    $errors = "";
    $running = true;

    // Monitor for 5 seconds
    while (time() - $startTime < 5) {
        $status = proc_get_status($process);
        if (!$status['running']) {
            $running = false;
            break;
        }

        $out = fgets($pipes[1]);
        $err = fgets($pipes[2]);

        if ($out) { $output .= $out; echo "$out"; }
        if ($err) { $errors .= $err; echo "$err"; }

        usleep(100000); // 100ms
    }

    echo "\n---------------------------------------------------\n";
    if ($running) {
        echo "RESULT: Process is STILL RUNNING after 5 seconds.\n";
        echo "This indicates it started successfully (or is hung).\n";
        // Kill it so we don't leave it running attached to this script
        proc_terminate($process);
    } else {
        echo "RESULT: Process EXITED immediately.\n";
        echo "Exit Code: " . $status['exitcode'] . "\n";
    }

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
} else {
    echo "Failed to launch process.\n";
}
?>
