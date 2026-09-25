<?php

require_once "includes/config.php";
require_once "includes/auth.php";

Auth::protect();

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
    die("Missing document ID.");
}

$document = fetchRow(
    "SELECT id, title, file_path FROM documents WHERE id=?",
    [$id]
);

if (!$document) {
    die("Document not found.");
}

?>

<!DOCTYPE html>
<html>

<head>

    <meta charset="UTF-8">

    <title>Signing Test</title>

</head>

<body>

<h2>
    Signing Test
</h2>

<p>
    Document:
    <?= htmlspecialchars($document["title"]) ?>
</p>

<button
    onclick="prepareDocument(<?= $document["id"] ?>)"
>
    Prepare Document
</button>

<pre id="result"></pre>

<script>

function prepareDocument(id) {

    const formData = new FormData();

    formData.append("id", id);


    fetch("api/documents/prepare-signing.php", {

    method: "POST",

    body: formData

})

.then(async response => {

    const text = await response.text();

    console.log("HTTP status:", response.status);
    console.log("Raw response:", text);

    document.getElementById("result").textContent =
        "HTTP " + response.status + "\n\n" + text;

    if (!text.trim()) {
        throw new Error(
            "The API returned an empty response."
        );
    }

    try {

        return JSON.parse(text);

    } catch (error) {

        throw new Error(
            "The API returned invalid JSON:\n\n" + text
        );

    }

})

.then(data => {

    console.log("Parsed data:", data);

    document.getElementById("result").textContent =
        JSON.stringify(data, null, 4);

})

.catch(error => {

    console.error(error);

    document.getElementById("result").textContent =
        error.message;

});
}

</script>

</body>

</html>