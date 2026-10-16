
<?php
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_OFF);

foreach ([3306, 3307] as $port) {
    echo "Testing port {$port}...\n";
    flush();

    $db = mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);

    $connected = @$db->real_connect(
        '127.0.0.1',
        'root',
        '',
        'communication_system',
        $port
    );

    if (!$connected) {
        echo "FAILED: " . $db->connect_error . "\n";
        continue;
    }

    $result = $db->query('SELECT DATABASE() AS db, 1 AS test');

    if ($result) {
        $row = $result->fetch_assoc();
        echo "CONNECTED: " . $row['db'] . "\n";
    } else {
        echo "CONNECTED, QUERY FAILED: " . $db->error . "\n";
    }

    $db->close();
}
