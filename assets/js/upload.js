
console.log("Upload JS Loaded");

const fileInput = document.getElementById("documentFile");
const fileName = document.getElementById("fileName");
const uploadForm = document.getElementById("uploadForm");
const uploadMode = document.getElementById("uploadMode");
const uploadModeHelp = document.getElementById("uploadModeHelp");

const uploadButton = document.getElementById("uploadDocumentButton");
const signButton = document.getElementById("signDocumentButton");
const finalizeButton = document.getElementById("finalizeUploadButton");

const draftIdInput = document.getElementById("draftId");
const draftTokenInput = document.getElementById("draftToken");
const progressBar = document.getElementById("progressBar");

const isReturningDraft = Boolean(
    draftIdInput &&
    draftTokenInput &&
    draftIdInput.value.trim() !== "" &&
    draftTokenInput.value.trim() !== ""
);

const SIGNABLE_EXTENSIONS = [
    "doc", "docx", "docm", "dot", "dotx",
    "odt", "rtf", "txt"
];

console.log("Upload workflow:", {
    isReturningDraft,
    draftId: draftIdInput?.value || null
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function showError(title, message) {
    Swal.fire({
        icon: "error",
        title,
        text: message
    });
}

function setProgress(percent, text = "") {
    if (!progressBar) return;

    progressBar.style.width = `${percent}%`;
    progressBar.textContent = text;
}

function setBusy(button, busy, busyText = "Please wait...") {
    if (!button) return;

    if (busy) {
        if (!button.dataset.originalHtml) {
            button.dataset.originalHtml = button.innerHTML;
        }

        button.disabled = true;
        button.innerHTML =
            `<i class="fa fa-spinner fa-spin"></i> ${busyText}`;
    } else {
        button.disabled = false;

        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    }
}

function showLoading(title, text) {
    Swal.fire({
        title,
        text,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
    });
}

function getFormData() {
    return new FormData(uploadForm);
}

function getSelectedFile() {
    return fileInput?.files?.[0] || null;
}

function getFileExtension(file) {
    return file.name.split(".").pop().toLowerCase();
}

function sendRequest({
    endpoint,
    formData,
    button,
    busyText,
    loadingTitle,
    loadingText,
    onSuccess
}) {
    setBusy(button, true, busyText);
    setProgress(0);

    showLoading(loadingTitle, loadingText);

    const xhr = new XMLHttpRequest();
    xhr.open("POST", endpoint, true);
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");

    xhr.upload.addEventListener("progress", function (event) {
        if (event.lengthComputable) {
            const percent = Math.round(
                (event.loaded / event.total) * 100
            );

            setProgress(percent, `${percent}%`);
        }
    });

    xhr.onload = function () {
        let response;

        try {
            response = JSON.parse(xhr.responseText);
        } catch (error) {
            console.error(
                "Invalid server response:",
                xhr.responseText
            );

            setBusy(button, false);

            Swal.fire(
                "Server Error",
                "The server returned an invalid response. Check the PHP error log.",
                "error"
            );

            return;
        }

        if (
            xhr.status < 200 ||
            xhr.status >= 300 ||
            !response.success
        ) {
            setBusy(button, false);

            Swal.fire({
                icon: "error",
                title: "Request Failed",
                text: response.message ||
                    "The request could not be completed."
            });

            return;
        }

        onSuccess(response);
    };

    xhr.onerror = function () {
        setBusy(button, false);

        showError(
            "Connection Error",
            "The server connection failed. Please try again."
        );
    };

    xhr.send(formData);
}

/*
|--------------------------------------------------------------------------
| File selection
|--------------------------------------------------------------------------
*/

if (fileInput && fileName && !isReturningDraft) {
    fileInput.addEventListener("change", function () {
        fileName.textContent = fileInput.files.length
            ? fileInput.files[0].name
            : "";
    });
}

/*
|--------------------------------------------------------------------------
| Show the correct action for the selected upload mode
|--------------------------------------------------------------------------
*/

function updateUploadModeUI() {
    if (isReturningDraft || !uploadMode) return;

    const signingMode = uploadMode.value === "sign";

    if (uploadButton) {
        uploadButton.style.display = signingMode ? "none" : "";
    }

    if (signButton) {
        signButton.style.display = signingMode ? "" : "none";
    }

    if (uploadModeHelp) {
        uploadModeHelp.textContent = signingMode
            ? "Click Sign Document to create a temporary draft and open the document editor. The document will not be uploaded until you finalize it."
            : "Click Upload Document to upload using the normal workflow.";
    }
}

if (uploadMode && !isReturningDraft) {
    uploadMode.addEventListener("change", updateUploadModeUI);
    updateUploadModeUI();
}

/*
|--------------------------------------------------------------------------
| SIGN DOCUMENT
|
| This button creates a temporary signing draft and opens ONLYOFFICE.
| It does NOT upload/finalize the document or send it for approval.
|--------------------------------------------------------------------------
*/

if (signButton && uploadForm && !isReturningDraft) {
    signButton.addEventListener("click", function () {
        if (uploadMode && uploadMode.value !== "sign") {
            showError(
                "Select Signing Mode",
                "Choose Sign Before Submitting before clicking Sign Document."
            );
            return;
        }

        // Run required-field validation because this is type="button",
        // so the browser will not validate the form automatically.
        if (!uploadForm.reportValidity()) {
            return;
        }

        const selectedFile = getSelectedFile();

        if (!selectedFile) {
            Swal.fire(
                "Missing File",
                "Please select a document to sign.",
                "warning"
            );
            return;
        }

        const extension = getFileExtension(selectedFile);

        if (!SIGNABLE_EXTENSIONS.includes(extension)) {
            Swal.fire({
                icon: "warning",
                title: "Unsupported File Type",
                text:
                    "Sign Before Submitting supports DOC, DOCX, DOCM, " +
                    "DOT, DOTX, ODT, RTF and TXT."
            });
            return;
        }

        const formData = getFormData();
        formData.set("upload_mode", "sign");

        sendRequest({
            endpoint: "api/documents/create-upload-draft.php",
            formData,
            button: signButton,
            busyText: "Creating Draft...",
            loadingTitle: "Creating Signing Draft...",
            loadingText: "Saving the original document for signing...",

            onSuccess: function (response) {
                if (!response.draft_id || !response.draft_token) {
                    setBusy(signButton, false);

                    showError(
                        "Draft Error",
                        "The server did not return the draft ID and access token."
                    );

                    return;
                }

                Swal.fire({
                    icon: "success",
                    title: "Draft Created",
                    text: "Opening the document editor...",
                    timer: 1000,
                    showConfirmButton: false,
                    allowOutsideClick: false
                }).then(function () {
                    const editorUrl = new URL(
                        "document-editor.php",
                        window.location.href
                    );

                    editorUrl.searchParams.set(
                        "draft_id",
                        String(response.draft_id)
                    );

                    editorUrl.searchParams.set(
                        "draft_token",
                        String(response.draft_token)
                    );

                    window.location.assign(editorUrl.toString());
                });
            }
        });
    });
}

/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|
| Normal mode: upload.php -> api/documents/upload.php
| Draft mode:  upload.php -> api/documents/finalize-upload-draft.php
|
| IMPORTANT:
| Submitting the form in signing mode must NOT create a draft or open
| ONLYOFFICE. The user must click the separate Sign Document button.
|--------------------------------------------------------------------------
*/

if (uploadForm) {
    uploadForm.addEventListener("submit", function (event) {
        event.preventDefault();

        /*
         * Returning from ONLYOFFICE: finalize only when the user clicks
         * Finalize Upload. The signed PDF must already be saved and valid.
         */
        if (isReturningDraft) {
            if (!finalizeButton) {
                showError(
                    "Button Missing",
                    "The Finalize Upload button could not be found."
                );
                return;
            }

            if (finalizeButton.disabled) {
                Swal.fire(
                    "Signed PDF Required",
                    "Return to the editor and generate a valid signed PDF before finalizing.",
                    "warning"
                );
                return;
            }

            const formData = getFormData();

            formData.set("draft_id", draftIdInput.value);
            formData.set("draft_token", draftTokenInput.value);

            sendRequest({
                endpoint: "api/documents/finalize-upload-draft.php",
                formData,
                button: finalizeButton,
                busyText: "Finalizing...",
                loadingTitle: "Finalizing Upload...",
                loadingText:
                    "Saving the signed PDF as a document...",

                onSuccess: function (response) {
                    setBusy(finalizeButton, true, "Upload Complete");

                    Swal.fire({
                        icon: "success",
                        title: "Upload Complete",
                        text: response.message ||
                            "The signed document has been uploaded.",
                        confirmButtonText: "View Documents",
                        allowOutsideClick: false
                    }).then(function () {
                        window.location.assign("documents.php");
                    });
                }
            });

            return;
        }

        /*
         * A form submission in signing mode must never start signing.
         * Signing is handled only by the separate Sign Document button.
         */
        const mode = uploadMode ? uploadMode.value : "normal";

        if (mode === "sign") {
            Swal.fire({
                icon: "info",
                title: "Ready to Sign?",
                text: "Click the separate Sign Document button to open the editor."
            });
            return;
        }

        /*
         * Normal upload workflow: keep the existing upload endpoint.
         */
        const selectedFile = getSelectedFile();

        if (!selectedFile) {
            Swal.fire(
                "Missing File",
                "Please select a document.",
                "warning"
            );
            return;
        }

        const formData = getFormData();
        formData.set("upload_mode", "normal");

        sendRequest({
            endpoint: "api/documents/upload.php",
            formData,
            button: uploadButton,
            busyText: "Uploading...",
            loadingTitle: "Uploading...",
            loadingText: "Uploading and sharing your document...",

            onSuccess: function (response) {
                setBusy(uploadButton, true, "Upload Complete");

                Swal.fire({
                    icon: "success",
                    title: "Uploaded",
                    text: response.message ||
                        "Document uploaded successfully.",
                    confirmButtonText: "View Documents",
                    allowOutsideClick: false
                }).then(function () {
                    window.location.assign("documents.php");
                });
            }
        });
    });
}
