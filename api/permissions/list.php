<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();


$query = "

SELECT

    users.id,

    users.first_name,

    users.last_name,

    users.username,

    users.role,

    departments.name AS department_name,


    permissions.can_view,

    permissions.can_edit,

    permissions.can_approve,

    permissions.can_delete,

    permissions.can_share


FROM users


LEFT JOIN departments

ON users.department_id = departments.id


LEFT JOIN permissions

ON users.id = permissions.user_id


ORDER BY users.first_name ASC

";


$data = fetchAll($query);


echo json_encode([
    "success" => true,
    "data" => $data
]);

?>