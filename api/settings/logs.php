<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$logs = fetchAll(

"
SELECT

audit_logs.id,

audit_logs.action,

audit_logs.entity_type,

audit_logs.entity_id,

audit_logs.created_at,

users.first_name,

users.last_name

FROM audit_logs

LEFT JOIN users

ON audit_logs.user_id = users.id

ORDER BY audit_logs.created_at DESC

LIMIT 100
"

);


echo json_encode([

    "success" => true,

    "logs" => $logs

]);