
<?php
header('Content-Type: text/plain');
echo "BEFORE CONFIG\n";
flush();

require_once __DIR__ . '/includes/config.php';

echo "AFTER CONFIG\n";
flush();
