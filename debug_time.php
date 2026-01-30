<?php
echo "Current PHP Timezone: " . date_default_timezone_get() . "\n";
echo "Current Server Timestamp: " . time() . "\n";
echo "Current Server Date: " . date("Y-m-d H:i:s") . "\n";
echo "User Offset Test (IST): " . date("Y-m-d H:i:s", time() + (5.5 * 3600)) . "\n";
?>
