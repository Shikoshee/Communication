<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';

Auth::protect();

$user = Auth::getCurrentUser();


/*
 * ==========================================================
 * CURRENT USER / ROLE
 * ==========================================================
 */

$userId = (int)($user['id'] ?? 0);

$userRole = strtolower(
    trim(
        (string)($user['role'] ?? 'user')
    )
);

$isAdmin = in_array(
    $userRole,
    [
        'admin',
        'administrator'
    ],
    true
);

$isManager = (
    $userRole === 'manager'
    ||
    str_contains($userRole, 'manager')
);


/*
 * ==========================================================
 * ACCESS
 * ==========================================================
 *
 * Department management is restricted to administrators
 * and managers.
 */

if (!$isAdmin && !$isManager) {

    die("Access Denied.");

}


/*
 * ==========================================================
 * MANAGER DEPARTMENT
 * ==========================================================
 */

$departmentId = (int)(
    $user['department_id'] ?? 0
);


/*
 * Some sessions may not contain department_id.
 * Get it directly from the database.
 */

if ($isManager && $departmentId <= 0) {

    $departmentRow = fetchRow(

        "SELECT
            department_id

         FROM users

         WHERE id=?

         LIMIT 1",

        [
            $userId
        ]

    );

    $departmentId = (int)(
        $departmentRow['department_id'] ?? 0
    );

}


/*
 * ==========================================================
 * MANAGER MUST HAVE DEPARTMENT
 * ==========================================================
 */

if ($isManager && $departmentId <= 0) {

    die(
        "Your account is not assigned to a department."
    );

}


/*
 * ==========================================================
 * PAGE
 * ==========================================================
 */

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";


$pageTitle = "Departments";

$breadcrumb = $isAdmin
    ? "Dashboard / Departments"
    : "Dashboard / My Department";

$buttonText = $isAdmin
    ? "Add Department"
    : "";

$buttonLink = $isAdmin
    ? "javascript:addDepartment();"
    : "";


include "includes/page-header.php";


/*
 * ==========================================================
 * DEPARTMENT HEADS
 * ==========================================================
 *
 * Only active managers/admins/administrators can be selected
 * as department heads.
 *
 * This is supplied once to departments.js.
 */

$departmentHeads = fetchAll("

    SELECT

        id,
        first_name,
        last_name,
        email,
        phone,
        role

    FROM users

    WHERE status='active'

    AND role IN (
        'manager',
        'admin',
        'administrator'
    )

    ORDER BY
        first_name,
        last_name

");


/*
 * ==========================================================
 * SUMMARY
 * ==========================================================
 */

if ($isAdmin) {

    /*
     * ADMIN:
     * Organization-wide statistics.
     */

    $totalDepartments = countRows("
        SELECT id
        FROM departments
    ");

    $activeDepartments = countRows("
        SELECT id
        FROM departments
        WHERE status='active'
    ");

    $totalEmployees = countRows("
        SELECT id
        FROM users
        WHERE status='active'
    ");

    $totalDocuments = countRows("
        SELECT id
        FROM documents
    ");

} else {

    /*
     * MANAGER:
     * Only their department.
     */

    $totalDepartments = countRows(
        "
        SELECT id
        FROM departments
        WHERE id=?
        ",
        [
            $departmentId
        ]
    );

    $activeDepartments = countRows(
        "
        SELECT id
        FROM departments
        WHERE id=?
        AND status='active'
        ",
        [
            $departmentId
        ]
    );

    $totalEmployees = countRows(
        "
        SELECT id
        FROM users
        WHERE department_id=?
        AND status='active'
        ",
        [
            $departmentId
        ]
    );

    $totalDocuments = countRows(
        "
        SELECT id
        FROM documents
        WHERE department_id=?
        ",
        [
            $departmentId
        ]
    );

}


/*
 * ==========================================================
 * DEPARTMENT CARDS
 * ==========================================================
 *
 * Department Manager and Admin are determined from:
 *
 * users.department_id
 * users.role
 *
 * NOT from departments.head_id.
 */

if ($isAdmin) {

    $departmentCards = fetchAll("

        SELECT
            d.id,
            d.name,
            d.description,
            d.head_id,
            d.status,

            (
                SELECT COUNT(*)
                FROM users emp
                WHERE emp.department_id = d.id
            ) AS employee_count,

            (
                SELECT COUNT(*)
                FROM documents doc
                WHERE doc.department_id = d.id
            ) AS document_count

        FROM departments d

        ORDER BY d.name

    ");

} else {

    $departmentCards = fetchAll(

        "

        SELECT
            d.id,
            d.name,
            d.description,
            d.head_id,
            d.status,

            (
                SELECT COUNT(*)
                FROM users emp
                WHERE emp.department_id = d.id
            ) AS employee_count,

            (
                SELECT COUNT(*)
                FROM documents doc
                WHERE doc.department_id = d.id
            ) AS document_count

        FROM departments d

        WHERE d.id = ?

        ORDER BY d.name

        ",

        [
            $departmentId
        ]

    );

}


/*
 * ==========================================================
 * GET MANAGER + ADMIN FROM USERS TABLE
 * ==========================================================
 */

foreach ($departmentCards as &$card) {

    $cardDepartmentId = (int)$card['id'];


    /*
     * Get the manager.
     */

    $manager = fetchRow(

        "

        SELECT
            id,
            first_name,
            last_name,
            email,
            phone,
            role,
            status

        FROM users

        WHERE department_id = ?

        AND LOWER(TRIM(role)) LIKE '%manager%'

        ORDER BY id ASC

        LIMIT 1

        ",

        [
            $cardDepartmentId
        ]

    );


    /*
     * Get the admin.
     */

    $admin = fetchRow(

        "

        SELECT
            id,
            first_name,
            last_name,
            email,
            phone,
            role,
            status

        FROM users

        WHERE department_id = ?

        AND LOWER(TRIM(role)) IN (
            'admin',
            'administrator'
        )

        ORDER BY id ASC

        LIMIT 1

        ",

        [
            $cardDepartmentId
        ]

    );


    /*
     * Build manager name.
     */

    $managerName = '';

    if ($manager) {

        $managerName = trim(

            ($manager['first_name'] ?? '')
            . ' '
            . ($manager['last_name'] ?? '')

        );

    }


    /*
     * Build admin name.
     */

    $adminName = '';

    if ($admin) {

        $adminName = trim(

            ($admin['first_name'] ?? '')
            . ' '
            . ($admin['last_name'] ?? '')

        );

    }


    /*
     * Attach them to the department card.
     */

    $card['manager_name'] =
        $managerName !== ''
            ? $managerName
            : '-';


    $card['admin_name'] =
        $adminName !== ''
            ? $adminName
            : '-';


    /*
     * Debug temporarily.
     *
     * You can remove this later.
     */

    /*
    error_log(
        'DEPARTMENT ' .
        $cardDepartmentId .
        ' MANAGER=' .
        $card['manager_name'] .
        ' ADMIN=' .
        $card['admin_name']
    );
    */

}

unset($card);
/*
 * ==========================================================
 * DEPARTMENT TABLE
 * ==========================================================
 */

$departments = $departmentCards;


/*
 * ==========================================================
 * CHART DATA
 * ==========================================================
 */

$chartLabels = [];

$chartValues = [];


if ($isAdmin) {

    $chartData = fetchAll("

        SELECT

            d.name,

            COUNT(doc.id)
                AS total_documents

        FROM departments d

        LEFT JOIN documents doc
            ON doc.department_id = d.id

        GROUP BY
            d.id,
            d.name

        ORDER BY
            d.name

    ");


} else {

    $chartData = fetchAll(

        "

        SELECT

            d.name,

            COUNT(doc.id)
                AS total_documents

        FROM departments d

        LEFT JOIN documents doc
            ON doc.department_id = d.id

        WHERE d.id=?

        GROUP BY
            d.id,
            d.name

        ORDER BY
            d.name

        ",

        [
            $departmentId
        ]

    );

}


foreach ($chartData as $row) {

    $chartLabels[] =
        $row['name'];

    $chartValues[] =
        (int)$row['total_documents'];

}


/*
 * ==========================================================
 * MANAGER DEPARTMENT NAME
 * ==========================================================
 */

$managerDepartmentName = '';

if ($isManager) {

    $managerDepartment = fetchRow(

        "

        SELECT
            name

        FROM departments

        WHERE id=?

        LIMIT 1

        ",

        [
            $departmentId
        ]

    );

    $managerDepartmentName =
        $managerDepartment['name']
        ?? 'Your Department';

}

?>


<link
    rel="stylesheet"
    href="assets/css/departments.css"
>


<!-- ==========================================================
     SUMMARY
     ========================================================== -->

<div class="department-summary">


    <!-- TOTAL DEPARTMENTS -->

    <div class="summary-card blue">

        <i class="fa-solid fa-building"></i>

        <div>

            <h2>
                <?= (int)$totalDepartments ?>
            </h2>

            <p>
                <?= $isAdmin
                    ? 'Total Departments'
                    : 'Department'
                ?>
            </p>

        </div>

    </div>


    <!-- ACTIVE DEPARTMENTS -->

    <div class="summary-card green">

        <i class="fa-solid fa-circle-check"></i>

        <div>

            <h2>
                <?= (int)$activeDepartments ?>
            </h2>

            <p>
                Active Departments
            </p>

        </div>

    </div>


    <!-- EMPLOYEES -->

    <div class="summary-card orange">

        <i class="fa-solid fa-users"></i>

        <div>

            <h2>
                <?= (int)$totalEmployees ?>
            </h2>

            <p>
                Employees
            </p>

        </div>

    </div>


    <!-- DOCUMENTS -->

    <div class="summary-card red">

        <i class="fa-solid fa-folder"></i>

        <div>

            <h2>
                <?= (int)$totalDocuments ?>
            </h2>

            <p>
                Documents
            </p>

        </div>

    </div>

</div>


<!-- ==========================================================
     MANAGER NOTICE
     ========================================================== -->

<?php if ($isManager) { ?>

    <div
        class="permission-scope"
        style="margin-bottom:20px;"
    >

        <i class="fa fa-building"></i>

        Viewing:

        <strong>
            <?= htmlspecialchars(
                $managerDepartmentName
            ) ?>
        </strong>

    </div>

<?php } ?>


<!-- ==========================================================
     TOOLBAR
     ========================================================== -->

<div class="department-toolbar">


    <input
        type="text"
        id="departmentSearch"
        placeholder="Search department..."
    >


    <select id="departmentFilter">

        <?php if ($isAdmin) { ?>

            <option value="">
                All Departments
            </option>

        <?php } ?>


        <?php foreach ($departments as $department) { ?>

            <option
                value="<?= htmlspecialchars(
                    strtolower(
                        $department['name']
                    ),
                    ENT_QUOTES
                ) ?>"
            >

                <?= htmlspecialchars(
                    $department['name']
                ) ?>

            </option>

        <?php } ?>

    </select>

</div>


<!-- ==========================================================
     DEPARTMENT ICON HELPER
     ========================================================== -->

<?php

function departmentIcon($name)
{

    $name = strtolower(
        (string)$name
    );


    if (str_contains($name, 'finance')) {

        return "fa-money-bill-trend-up finance";

    }


    if (
        str_contains($name, 'human')
        ||
        str_contains($name, 'hr')
    ) {

        return "fa-user-group hr";

    }


    if (
        str_contains($name, 'ict')
        ||
        str_contains($name, 'technology')
    ) {

        return "fa-computer ict";

    }


    if (
        str_contains($name, 'supply')
        ||
        str_contains($name, 'procurement')
    ) {

        return "fa-truck-fast supply";

    }


    if (str_contains($name, 'quality')) {

        return "fa-shield-halved quality";

    }


    if (str_contains($name, 'sales')) {

        return "fa-chart-line sales";

    }


    if (str_contains($name, 'production')) {

        return "fa-industry production";

    }


    if (str_contains($name, 'marketing')) {

        return "fa-bullhorn marketing";

    }


    return "fa-building general";

}

?>


<!-- ==========================================================
     DEPARTMENT CARDS
     ========================================================== -->

<div class="department-grid">


<?php foreach ($departmentCards as $card) { ?>


    <?php

    $iconParts = explode(
        ' ',
        departmentIcon(
            $card['name']
        )
    );

    $iconClass =
        $iconParts[0]
        ?? 'fa-building';

    $colorClass =
        $iconParts[1]
        ?? 'general';

    ?>


    <div
        class="department-card"
        onclick="viewDepartment(
            <?= (int)$card['id'] ?>
        )"
        data-department="<?= htmlspecialchars(
            strtolower(
                $card['name']
            ),
            ENT_QUOTES
        ) ?>"
    >


        <i
            class="fa-solid
                   <?= htmlspecialchars(
                       $iconClass,
                       ENT_QUOTES
                   ) ?>
                   icon
                   <?= htmlspecialchars(
                       $colorClass,
                       ENT_QUOTES
                   ) ?>"
        ></i>


        <h3>

            <?= htmlspecialchars(
                $card['name']
            ) ?>

        </h3>


        <p>

    <strong>
        Manager:
    </strong>

    <?= htmlspecialchars(
        $card['manager_name'] ?? '-'
    ) ?>

</p>

<p>

    <strong>
        Admin:
    </strong>

    <?= htmlspecialchars(
        $card['admin_name'] ?? '-'
    ) ?>

</p>


        <p>

            <strong>
                Employees:
            </strong>

            <?= (int)(
                $card['employee_count'] ?? 0
            ) ?>

        </p>


        <p>

            <strong>
                Documents:
            </strong>

            <?= (int)(
                $card['document_count'] ?? 0
            ) ?>

        </p>


        <span
            class="status
                   <?= htmlspecialchars(
                       strtolower(
                           $card['status'] ?? ''
                       ),
                       ENT_QUOTES
                   ) ?>"
        >

            <?= htmlspecialchars(
                ucfirst(
                    $card['status'] ?? ''
                )
            ) ?>

        </span>


    </div>


<?php } ?>


</div>


<!-- ==========================================================
     DEPARTMENT TABLE
     ========================================================== -->

<div class="department-table">


    <h3>

        <?= $isAdmin
            ? 'Department Directory'
            : 'Your Department'
        ?>

    </h3>


    <table>


        <thead>

            <tr>

                <th>
                    Department
                </th>

                <th>
                    Heads
                </th>

                <th>
                    Employees
                </th>

                <th>
                    Documents
                </th>

                <th>
                    Status
                </th>

                <th>
                    Actions
                </th>

            </tr>

        </thead>


        <tbody id="departmentTable">


        <?php foreach ($departments as $department) { ?>


            <tr
                data-department="<?= htmlspecialchars(
                    strtolower(
                        $department['name']
                    ),
                    ENT_QUOTES
                ) ?>"
            >


                <td>

                    <?= htmlspecialchars(
                        $department['name']
                    ) ?>

                </td>


                <td>

    <?php

    $headCount = 0;


    /*
     * Manager counts as one head.
     */
    if (
        !empty($department['manager_name'])
        &&
        $department['manager_name'] !== '-'
    ) {

        $headCount++;

    }


    /*
     * Admin counts as one head.
     */
    if (
        !empty($department['admin_name'])
        &&
        $department['admin_name'] !== '-'
    ) {

        $headCount++;

    }

    ?>

    <?= $headCount ?>

</td>


                <td>

                    <?= (int)(
                        $department['employee_count']
                        ?? 0
                    ) ?>

                </td>


                <td>

                    <?= (int)(
                        $department['document_count']
                        ?? 0
                    ) ?>

                </td>


                <td>

                    <span
                        class="status
                               <?= htmlspecialchars(
                                   strtolower(
                                       $department['status']
                                       ?? ''
                                   ),
                                   ENT_QUOTES
                               ) ?>"
                    >

                        <?= htmlspecialchars(
                            ucfirst(
                                $department['status']
                                ?? ''
                            )
                        ) ?>

                    </span>

                </td>


                <td>


                    <button
                        class="view-btn"
                        onclick="event.stopPropagation(); viewDepartment(
                            <?= (int)$department['id'] ?>
                        )"
                    >

                        <i class="fa fa-eye"></i>

                    </button>


                    <?php if ($isAdmin) { ?>


                        <button
                            class="edit-btn"
                            onclick="event.stopPropagation(); editDepartment(
                                <?= (int)$department['id'] ?>
                            )"
                        >

                            <i class="fa fa-edit"></i>

                        </button>


                        <button
                            class="delete-btn"
                            onclick="event.stopPropagation(); deleteDepartment(
                                <?= (int)$department['id'] ?>,
                                '<?= htmlspecialchars(
                                    $department['name'],
                                    ENT_QUOTES
                                ) ?>'
                            )"
                        >

                            <i class="fa fa-trash"></i>

                        </button>


                    <?php } ?>


                </td>


            </tr>


        <?php } ?>


        </tbody>


    </table>

</div>


<!-- ==========================================================
     CHART
     ========================================================== -->

<div class="department-chart">


    <h3>
        Documents by Department
    </h3>


    <canvas
        id="departmentStatistics"
    ></canvas>


</div>


<!-- ==========================================================
     JAVASCRIPT DATA
     ========================================================== -->

<script>

const departmentHeads =
    <?= json_encode(
        $departmentHeads,
        JSON_HEX_TAG |
        JSON_HEX_APOS |
        JSON_HEX_AMP |
        JSON_HEX_QUOT
    ) ?>;


const departmentLabels =
    <?= json_encode(
        $chartLabels
    ) ?>;


const departmentDocuments =
    <?= json_encode(
        $chartValues
    ) ?>;


const currentUserRole =
    <?= json_encode(
        $userRole
    ) ?>;


const currentDepartmentId =
    <?= json_encode(
        $departmentId
    ) ?>;

</script>


<!-- ==========================================================
     DEPARTMENTS JAVASCRIPT
     ========================================================== -->

<script src="assets/js/departments.js"></script>


<?php

include "includes/footer.php";

?>
