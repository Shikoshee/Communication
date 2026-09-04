<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once "includes/config.php";
require_once "includes/auth.php";
require_once "includes/Permission.php";

Auth::protect();

$user = Auth::getCurrentUser();

if (!$user) {
    die("Authentication required.");
}

include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>

<link rel="stylesheet" href="assets/css/documents.css">

<div class="page-header">

    <div>

        <h1>
            <i class="fa fa-trash"></i>
            Recycle Bin
        </h1>

        <p>
            Deleted documents are kept here until they are restored.
        </p>

    </div>

    <a href="documents.php" class="upload-btn">
        <i class="fa fa-arrow-left"></i>
        Back to Documents
    </a>

</div>


<div class="document-card">

    <table>

        <thead>

            <tr>
                <th>Document</th>
                <th>Department</th>
                <th>Owner</th>
                <th>Deleted By</th>
                <th>Deleted At</th>
                <th>Actions</th>
            </tr>

        </thead>

        <tbody id="recycleBinTable">

            <tr>

                <td
                    colspan="6"
                    style="text-align:center;padding:40px;"
                >

                    <i class="fa fa-spinner fa-spin"></i>

                    Loading recycle bin...

                </td>

            </tr>

        </tbody>

    </table>

</div>


<script>

/*
|--------------------------------------------------------------------------
| RECYCLE BIN
|--------------------------------------------------------------------------
|
| IMPORTANT:
| This script intentionally does NOT use document.createElement().
| This avoids conflicts with another JavaScript file that may be
| overriding/shadowing "document".
|
|--------------------------------------------------------------------------
*/


(function () {

    "use strict";


    /*
    |--------------------------------------------------------------------------
    | GET TABLE
    |--------------------------------------------------------------------------
    */

    function getRecycleTable() {

        return window.document.getElementById(
            "recycleBinTable"
        );

    }


    /*
    |--------------------------------------------------------------------------
    | ESCAPE HTML
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");

    }


    /*
    |--------------------------------------------------------------------------
    | FORMAT DATE
    |--------------------------------------------------------------------------
    */

    function formatDeletedDate(value) {

        if (!value) {
            return "-";
        }

        const date = new Date(
            String(value).replace(" ", "T")
        );

        if (isNaN(date.getTime())) {

            return escapeHtml(value);

        }

        return escapeHtml(
            date.toLocaleString()
        );

    }


    /*
    |--------------------------------------------------------------------------
    | SHOW ERROR
    |--------------------------------------------------------------------------
    */

    function showRecycleError(message) {

        const table = getRecycleTable();

        if (!table) {
            return;
        }

        table.innerHTML = `

            <tr>

                <td
                    colspan="6"
                    style="
                        text-align:center;
                        padding:50px;
                        color:#dc3545;
                    "
                >

                    <i
                        class="fa fa-exclamation-triangle"
                        style="font-size:35px;"
                    ></i>

                    <p style="margin-top:15px;">
                        ${escapeHtml(message)}
                    </p>

                    <button
                        type="button"
                        id="retryRecycleBin"
                        class="upload-btn"
                        style="margin-top:10px;"
                    >
                        <i class="fa fa-refresh"></i>
                        Try Again
                    </button>

                </td>

            </tr>

        `;

        const retryButton =
            window.document.getElementById(
                "retryRecycleBin"
            );

        if (retryButton) {

            retryButton.addEventListener(
                "click",
                loadRecycleBin
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | LOAD RECYCLE BIN
    |--------------------------------------------------------------------------
    */

    function loadRecycleBin() {

        const table = getRecycleTable();

        if (!table) {

            console.error(
                "Recycle Bin: table not found."
            );

            return;

        }


        table.innerHTML = `

            <tr>

                <td
                    colspan="6"
                    style="text-align:center;padding:40px;"
                >

                    <i class="fa fa-spinner fa-spin"></i>

                    Loading recycle bin...

                </td>

            </tr>

        `;


        console.log(
            "Recycle Bin: requesting API..."
        );


        fetch(
            "api/documents/recycle-bin.php",
            {
                method: "GET",

                cache: "no-store",

                headers: {
                    "Accept": "application/json"
                }
            }
        )

        .then(function (response) {

            console.log(
                "Recycle Bin HTTP status:",
                response.status
            );


            return response.text();

        })

        .then(function (text) {

            console.log(
                "Recycle Bin API response:",
                text
            );


            if (!text || !text.trim()) {

                throw new Error(
                    "The recycle bin API returned an empty response."
                );

            }


            let data;

            try {

                data = JSON.parse(text);

            } catch (error) {

                console.error(
                    "Recycle Bin invalid JSON:",
                    text
                );

                throw new Error(
                    "The recycle bin API did not return valid JSON."
                );

            }


            return data;

        })

        .then(function (data) {

            console.log(
                "Recycle Bin parsed data:",
                data
            );


            if (!data || data.success !== true) {

                throw new Error(
                    data && data.message
                        ? data.message
                        : "Failed to load recycle bin."
                );

            }


            const documents =
                Array.isArray(data.documents)
                    ? data.documents
                    : [];


            /*
            |--------------------------------------------------------------------------
            | EMPTY RECYCLE BIN
            |--------------------------------------------------------------------------
            */

            if (documents.length === 0) {

                table.innerHTML = `

                    <tr>

                        <td
                            colspan="6"
                            style="
                                text-align:center;
                                padding:50px;
                            "
                        >

                            <i
                                class="fa fa-trash"
                                style="
                                    font-size:45px;
                                    opacity:.4;
                                "
                            ></i>

                            <p style="margin-top:15px;">
                                The recycle bin is empty.
                            </p>

                        </td>

                    </tr>

                `;

                return;

            }


            /*
            |--------------------------------------------------------------------------
            | BUILD TABLE USING HTML
            |--------------------------------------------------------------------------
            |
            | We deliberately use insertAdjacentHTML instead of
            | document.createElement().
            |
            */

            let rows = "";


            documents.forEach(function (doc) {

                const id =
                    Number(doc.id) || 0;


                const title =
                    doc.title ||
                    "Untitled Document";


                const department =
                    doc.department_name ||
                    "No Department";


                const owner =
                    doc.owner_name ||
                    "-";


                const deletedBy =
                    doc.deleted_by_name ||
                    "-";


                const deletedAt =
                    formatDeletedDate(
                        doc.deleted_at
                    );


                rows += `

                    <tr>

                        <td>

                            <i class="fa fa-file"></i>

                            ${escapeHtml(title)}

                        </td>


                        <td>

                            ${escapeHtml(department)}

                        </td>


                        <td>

                            ${escapeHtml(owner)}

                        </td>


                        <td>

                            ${escapeHtml(deletedBy)}

                        </td>


                        <td>

                            ${deletedAt}

                        </td>


                        <td>

                            <button
                                type="button"
                                class="action restore-document-btn"
                                data-id="${id}"
                                data-title="${escapeHtml(title)}"
                                title="Restore Document"
                            >

                                <i class="fa fa-undo"></i>

                            </button>

                        </td>

                    </tr>

                `;

            });


            table.innerHTML = rows;


            console.log(
                "Recycle Bin: displayed",
                documents.length,
                "document(s)."
            );

        })

        .catch(function (error) {

            console.error(
                "Recycle Bin error:",
                error
            );


            showRecycleError(
                error.message ||
                "Failed to load recycle bin."
            );

        });

    }


    /*
    |--------------------------------------------------------------------------
    | RESTORE DOCUMENT
    |--------------------------------------------------------------------------
    */

    function restoreDocument(id, title) {

        if (!id) {

            console.error(
                "Invalid document ID:",
                id
            );

            return;

        }


        if (
            typeof window.Swal === "undefined"
        ) {

            alert(
                "SweetAlert is not loaded."
            );

            return;

        }


        window.Swal.fire({

            title: "Restore Document?",

            text:
                'Restore "' +
                title +
                '" back to your documents?',

            icon: "question",

            showCancelButton: true,

            confirmButtonText: "Restore",

            cancelButtonText: "Cancel"

        })

        .then(function (result) {

            if (!result.isConfirmed) {
                return;
            }


            /*
            |--------------------------------------------------------------------------
            | SEND RESTORE REQUEST
            |--------------------------------------------------------------------------
            */

            return fetch(
                "api/documents/restore.php",
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/x-www-form-urlencoded"
                    },

                    body:
                        new URLSearchParams({
                            id: id
                        })
                }
            );

        })

        .then(function (response) {

            if (!response) {
                return null;
            }


            return response.text();

        })

        .then(function (text) {

            if (text === null) {
                return;
            }


            console.log(
                "Restore API response:",
                text
            );


            if (!text || !text.trim()) {

                throw new Error(
                    "Restore API returned an empty response."
                );

            }


            let data;

            try {

                data = JSON.parse(text);

            } catch (error) {

                console.error(
                    "Invalid restore JSON:",
                    text
                );

                throw new Error(
                    "Restore API did not return valid JSON."
                );

            }


            if (data.success) {

                return window.Swal.fire({

                    icon: "success",

                    title: "Restored",

                    text:
                        data.message ||
                        "Document restored successfully."

                });

            }


            throw new Error(
                data.message ||
                "Failed to restore document."
            );

        })

        .then(function (result) {

            /*
            |--------------------------------------------------------------------------
            | RELOAD TABLE AFTER SUCCESSFUL RESTORE
            |--------------------------------------------------------------------------
            */

            if (
                result &&
                result.isConfirmed
            ) {

                loadRecycleBin();

            }

        })

        .catch(function (error) {

            console.error(
                "Restore error:",
                error
            );


            if (
                typeof window.Swal !== "undefined"
            ) {

                window.Swal.fire(
                    "Error",
                    error.message ||
                    "Restore request failed.",
                    "error"
                );

            } else {

                alert(
                    error.message ||
                    "Restore request failed."
                );

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | RESTORE BUTTON EVENT
    |--------------------------------------------------------------------------
    |
    | Event delegation means we do not need to create individual
    | event listeners for every row.
    |
    */

    function setupRestoreEvents() {

        const table = getRecycleTable();

        if (!table) {
            return;
        }


        table.addEventListener(
            "click",
            function (event) {

                const button =
                    event.target.closest(
                        ".restore-document-btn"
                    );


                if (!button) {
                    return;
                }


                const id =
                    Number(
                        button.getAttribute(
                            "data-id"
                        )
                    );


                const title =
                    button.getAttribute(
                        "data-title"
                    ) ||
                    "this document";


                restoreDocument(
                    id,
                    title
                );

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | START
    |--------------------------------------------------------------------------
    */

    function startRecycleBin() {

        console.log(
            "Recycle Bin JavaScript loaded."
        );


        setupRestoreEvents();

        loadRecycleBin();

    }


    /*
    |--------------------------------------------------------------------------
    | DOM READY
    |--------------------------------------------------------------------------
    */

    if (
        window.document.readyState ===
        "loading"
    ) {

        window.document.addEventListener(
            "DOMContentLoaded",
            startRecycleBin
        );

    } else {

        startRecycleBin();

    }


})();
</script>


<?php

include "includes/footer.php";

?>

