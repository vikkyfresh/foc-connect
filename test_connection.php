<?php
// Simple connection test
$host = 'btpaf0bjadqhld71gzms-mysql.services.clever-cloud.com';
$port = 3306;

$connection = @fsockopen($host, $port, $errno, $errstr, 5);

if ($connection) {
    echo "✅ Can reach Clever Cloud server<br>";
    fclose($connection);
} else {
    echo "❌ Cannot reach Clever Cloud: $errstr ($errno)<br>";
    echo "InfinityFree might be blocking external MySQL connections";
}

// Also test if mysqli extension is loaded
if (extension_loaded('mysqli')) {
    echo "✅ MySQLi extension is loaded<br>";
} else {
    echo "❌ MySQLi extension is NOT loaded<br>";
}
?>
