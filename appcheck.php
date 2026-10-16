
<?php
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo "STEP 1: PHP started\n";
flush();

require_once __DIR__ . '/includes/config.php';

echo "STEP 2: Configuration loaded\n";
flush();

$result = fetchRow("SELECT 1 AS test_value");

echo "STEP 3: Database query completed\n";
var_export($result);
