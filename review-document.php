<?php

require_once "includes/config.php";
require_once "includes/auth.php";

Auth::protect();

$user = Auth::getCurrentUser();


$id = (int)($_GET['id'] ?? 0);


$document = fetchRow("

SELECT

d.*,

CONCAT(
u.first_name,
' ',
u.last_name
) AS uploader

FROM documents d

LEFT JOIN users u

ON u.id=d.uploaded_by

WHERE d.id=?

",
[
$id
]);


if(!$document){

    die("Document not found");

}


include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>


<link rel="stylesheet" href="assets/css/review.css">


<div class="page-header">

<h1>
Review Document
</h1>

<p>
Review, sign and approve submitted documents.
</p>

</div>



<div class="review-card">


<div class="review-header">

    <div class="document-icon">

        <i class="fa fa-file-signature"></i>

    </div>

    <div class="document-details">

        <h2>
            <?= htmlspecialchars($document['title']) ?>
        </h2>

        <div class="document-meta">

            <span>
                <i class="fa fa-user"></i>

                <?= htmlspecialchars($document['uploader']) ?>
            </span>

            <span>

                <i class="fa fa-calendar"></i>

                <?= date(
                    "d M Y H:i",
                    strtotime($document["created_at"])
                ) ?>

            </span>

            <span>

                <i class="fa fa-layer-group"></i>

                Version <?= htmlspecialchars($document["version"]) ?>

            </span>

            <span class="status <?= $document["status"] ?>">

                <?= ucfirst($document["status"]) ?>

            </span>

        </div>

    </div>

</div>


<div class="preview-card">

<div class="preview-header">

<h3>

<i class="fa fa-eye"></i>

Document Preview

</h3>



</div>

<?php

$filePath = trim($document['file_path']);


/*
|--------------------------------------------------------------------------
| Get file extension
|--------------------------------------------------------------------------
*/

$fileExtension = strtolower(
    pathinfo(
        parse_url($filePath, PHP_URL_PATH),
        PATHINFO_EXTENSION
    )
);


/*
|--------------------------------------------------------------------------
| Build document URL
|--------------------------------------------------------------------------
|
| Database should normally contain:
|
| uploads/documents/myfile.docx
|
| This converts it to:
|
| http://localhost/communication/uploads/documents/myfile.docx
|
|--------------------------------------------------------------------------
*/

if (
    preg_match('#^https?://#i', $filePath)
) {

    /*
     * If the database already contains a full URL,
     * use it only if it is NOT localhost HTTPS.
     */

    if (
        str_starts_with(
            strtolower($filePath),
            'https://localhost'
        )
    ) {

        $relativePath = parse_url(
            $filePath,
            PHP_URL_PATH
        );

        $documentUrl =
            rtrim(APP_URL, '/') .
            '/' .
            ltrim($relativePath, '/');

    } else {

        $documentUrl = $filePath;

    }

} else {

    /*
     * Normal database value:
     *
     * uploads/documents/file.docx
     */

    $documentUrl =
        rtrim(APP_URL, '/') .
        '/' .
        ltrim($filePath, '/');

}


$documentUrl = htmlspecialchars(
    $documentUrl,
    ENT_QUOTES,
    'UTF-8'
);


/*
|--------------------------------------------------------------------------
| Office application
|--------------------------------------------------------------------------
*/

$wordExtensions = [
    'pdf',
    'doc',
    'docx'
];

$excelExtensions = [
    'xls',
    'xlsx',
    'csv'
];


$isWord = in_array(
    $fileExtension,
    $wordExtensions,
    true
);

$isExcel = in_array(
    $fileExtension,
    $excelExtensions,
    true
);

?>


<div class="document-preview">

    <?php if ($isWord): ?>

    <a
        href="ms-word:ofe|u|<?= $documentUrl ?>"
        class="open-office-btn word-btn"
    >
        <i class="fa fa-file-word"></i>
        Open with Microsoft Word
    </a>

<?php elseif ($isExcel): ?>

    <a
        href="ms-excel:ofe|u|<?= $documentUrl ?>"
        class="open-office-btn excel-btn"
    >
        <i class="fa fa-file-excel"></i>
        Open with Microsoft Excel
    </a>

<?php endif; ?>
</div>


</div>





<hr>


<?php if(!empty($document["reviewed_file"])){ ?>

<div class="signed-preview">

<div>

<h4>

<i class="fa fa-check-circle"></i>

Signed Copy Uploaded

</h4>

<p>

A reviewed version has already been uploaded.

</p>

</div>

<a

href="<?= htmlspecialchars($document["reviewed_file"]) ?>"

target="_blank"

class="signed-download">

<i class="fa fa-download"></i>

View Signed Copy

</a>

</div>

<?php } ?>

<div class="upload-section">

<h3>

<i class="fa fa-file-upload"></i>

Signed Document

</h3>

<p>

Upload the reviewed copy with your signature and approval stamp.

</p>


<form

id="signedForm"

enctype="multipart/form-data">


<input

type="hidden"

name="id"

value="<?= $document['id'] ?>">



<div class="upload-box">

<input

type="file"

name="signed_file"

accept=".pdf,.doc,.docx"

required>

<p>

PDF or Word Document

</p>

</div>


<button

class="upload-signed-btn">

<i class="fa fa-upload"></i>

Upload Signed Copy

</button>


</form>
</div>




<div class="action-card">

<h3>

Approval Decision

</h3>

<p>

After reviewing and uploading the signed copy, choose the final action.

</p>

<div class="review-actions">


<button

onclick="approveDocument(<?= $document['id'] ?>)"

class="approve-btn">

<i class="fa fa-check"></i>

Approve Document

</button>



<button

onclick="rejectDocument(<?= $document['id'] ?>)"

class="reject-btn">

<i class="fa fa-times"></i>

Reject

</button>

</div>
</div>

</div>



</div>



<script src="assets/js/review.js"></script>
<script src="assets/js/approvals.js"></script>


<?php include "includes/footer.php"; ?>