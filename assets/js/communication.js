document.addEventListener("DOMContentLoaded", function () {

    "use strict";

    console.log("communication.js loaded successfully");


    // ==========================================================
    // GLOBAL VARIABLES
    // ==========================================================

    let selectedDocument = null;
    let selectedImage = null;

    let latestMessageId = 0;
    let notificationReady = false;


    // ==========================================================
    // ELEMENTS
    // ==========================================================

    const messageInput =
        document.getElementById("messageInput");

    const sendButton =
        document.getElementById("sendMessageBtn");

    const chatBody =
        document.getElementById("chatBody");

    const imageInput =
        document.getElementById("imageInput");

    const attachImageBtn =
        document.getElementById("attachImageBtn");

    const imagePreview =
        document.getElementById("imagePreview");

    const imagePreviewImage =
        document.getElementById("imagePreviewImage");

    const imagePreviewName =
        document.getElementById("imagePreviewName");

    const removeImage =
        document.getElementById("removeImage");


    // Document attachment elements

    const documentModal =
        document.getElementById("documentModal");

    const documentSelect =
        document.getElementById("documentSelect");

    const attachButton =
        document.getElementById("attachDocumentBtn");

    const confirmAttach =
        document.getElementById("selectDocument");

    const closeDocument =
        document.getElementById("closeDocument");

    const attachmentPreview =
        document.getElementById("attachmentPreview");

    const attachmentName =
        document.getElementById("attachmentName");

    const removeAttachment =
        document.getElementById("removeAttachment");

    const search =
        document.getElementById("conversationSearch");


    // ==========================================================
    // CHECK IMPORTANT ELEMENTS
    // ==========================================================

    console.log("Elements found:", {
        messageInput: !!messageInput,
        sendButton: !!sendButton,
        chatBody: !!chatBody,
        imageInput: !!imageInput,
        attachImageBtn: !!attachImageBtn,
        imagePreview: !!imagePreview,
        imagePreviewImage: !!imagePreviewImage,
        imagePreviewName: !!imagePreviewName,
        removeImage: !!removeImage,
        documentModal: !!documentModal,
        documentSelect: !!documentSelect,
        attachButton: !!attachButton
    });


    // ==========================================================
    // FILE URL HELPER
    // ==========================================================

    function getFileUrl(path) {

        if (!path) {
            return "";
        }

        path = String(path).trim();

        if (!path) {
            return "";
        }

        /*
         * Already a complete URL.
         */

        if (
            path.startsWith("http://") ||
            path.startsWith("https://")
        ) {
            return path;
        }

        /*
         * Already starts with /
         */

        if (path.startsWith("/")) {
            return path;
        }

        /*
         * PHP normally stores:
         *
         * uploads/communication/images/example.jpg
         *
         * The application is:
         *
         * /Communication/
         */

        return "/Communication/" +
            path.replace(/^\/+/, "");
    }


    // ==========================================================
    // ESCAPE HTML
    // ==========================================================

    function escapeHtml(value) {

        const div =
            document.createElement("div");

        div.textContent =
            value ?? "";

        return div.innerHTML;
    }


    // ==========================================================
    // ESCAPE ATTRIBUTE
    // ==========================================================

    function escapeAttribute(value) {

        return escapeHtml(value)
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }


    // ==========================================================
    // MARK CONVERSATION AS READ
    // ==========================================================

    function markConversationAsRead() {

        if (
            typeof CURRENT_CONVERSATION === "undefined" ||
            !CURRENT_CONVERSATION
        ) {
            return;
        }

        fetch(
            "api/communication/mark-read.php",
            {
                method: "POST",

                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded"
                },

                body:
                    new URLSearchParams({
                        conversation_id:
                            CURRENT_CONVERSATION
                    })
            }
        )

        .then(async response => {

            const text =
                await response.text();

            console.log(
                "mark-read response:",
                text
            );

            if (!text.trim()) {
                throw new Error(
                    "mark-read.php returned an empty response."
                );
            }

            try {
                return JSON.parse(text);
            } catch (error) {

                console.error(
                    "Invalid JSON from mark-read.php:",
                    text
                );

                throw error;
            }
        })

        .then(data => {

            console.log(
                "Conversation marked as read:",
                data
            );

            if (!data.success) {
                return;
            }

            /*
             * Remove unread styling from messages.
             */

            document
                .querySelectorAll(".unread-message")
                .forEach(message => {

                    message.classList.remove(
                        "unread-message"
                    );
                });


            /*
             * Remove unread styling from current
             * sidebar conversation.
             */

            const currentConversation =
                document.querySelector(
                    `.conversation[data-id="${CURRENT_CONVERSATION}"]`
                );

            if (currentConversation) {

                currentConversation.classList.remove(
                    "has-unread"
                );

                const dot =
                    currentConversation.querySelector(
                        ".unread-dot"
                    );

                if (dot) {
                    dot.remove();
                }
            }

        })

        .catch(error => {

            console.error(
                "markConversationAsRead error:",
                error
            );

        });
    }


    // ==========================================================
    // IMAGE ATTACHMENT
    // ==========================================================

    if (attachImageBtn) {

        attachImageBtn.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                console.log(
                    "Image attachment button clicked"
                );

                if (!imageInput) {

                    console.error(
                        "imageInput was not found."
                    );

                    alert(
                        "The image upload control was not found."
                    );

                    return;
                }

                imageInput.click();
            }
        );
    }


    // ==========================================================
    // IMAGE INPUT CHANGE
    // ==========================================================

    if (imageInput) {

        imageInput.addEventListener(
            "change",
            function () {

                console.log(
                    "Image input changed"
                );

                const file =
                    this.files &&
                    this.files.length
                        ? this.files[0]
                        : null;

                if (!file) {
                    return;
                }

                console.log(
                    "Selected image:",
                    file.name,
                    file.type,
                    file.size
                );


                // ------------------------------------------------
                // VALID IMAGE TYPES
                // ------------------------------------------------

                const allowedTypes = [
                    "image/jpeg",
                    "image/png",
                    "image/gif",
                    "image/webp"
                ];


                if (
                    !allowedTypes.includes(
                        file.type
                    )
                ) {

                    alert(
                        "Please select a JPG, PNG, GIF or WEBP image."
                    );

                    this.value = "";

                    selectedImage = null;

                    return;
                }


                // ------------------------------------------------
                // MAXIMUM SIZE
                // ------------------------------------------------

                if (
                    file.size >
                    5 * 1024 * 1024
                ) {

                    alert(
                        "Image must not exceed 5 MB."
                    );

                    this.value = "";

                    selectedImage = null;

                    return;
                }


                // ------------------------------------------------
                // SAVE SELECTED IMAGE
                // ------------------------------------------------

                selectedImage = file;

                console.log(
                    "selectedImage is now:",
                    selectedImage
                );


                // ------------------------------------------------
                // IMAGE PREVIEW
                // ------------------------------------------------

                if (imagePreviewImage) {

                    const reader =
                        new FileReader();

                    reader.onload =
                        function (event) {

                            imagePreviewImage.src =
                                event.target.result;
                        };

                    reader.readAsDataURL(file);
                }


                if (imagePreviewName) {

                    imagePreviewName.textContent =
                        file.name;
                }


                if (imagePreview) {

                    imagePreview.style.display =
                        "block";
                }
            }
        );
    }


    // ==========================================================
    // REMOVE IMAGE
    // ==========================================================

    if (removeImage) {

        removeImage.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                clearSelectedImage();
            }
        );
    }


    function clearSelectedImage() {

        console.log(
            "Clearing selected image"
        );

        selectedImage = null;


        if (imageInput) {
            imageInput.value = "";
        }


        if (imagePreviewImage) {
            imagePreviewImage.src = "";
        }


        if (imagePreviewName) {
            imagePreviewName.textContent = "";
        }


        if (imagePreview) {
            imagePreview.style.display =
                "none";
        }
    }


    // ==========================================================
    // DOCUMENT ATTACHMENT
    // ==========================================================

    if (attachButton) {

        attachButton.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                console.log(
                    "Document attachment button clicked"
                );

                if (!documentModal) {
                    return;
                }

                documentModal.style.display =
                    "flex";
            }
        );
    }


    // ==========================================================
    // CONFIRM DOCUMENT
    // ==========================================================

    if (confirmAttach) {

        confirmAttach.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                if (
                    !documentSelect ||
                    !documentSelect.value
                ) {

                    alert(
                        "Please select a document."
                    );

                    return;
                }


                selectedDocument =
                    documentSelect.value;


                console.log(
                    "Selected document:",
                    selectedDocument
                );


                if (attachmentName) {

                    const selectedOption =
                        documentSelect.options[
                            documentSelect.selectedIndex
                        ];

                    if (selectedOption) {

                        attachmentName.textContent =
                            selectedOption.text;
                    }
                }


                if (attachmentPreview) {

                    attachmentPreview.style.display =
                        "block";
                }


                if (documentModal) {

                    documentModal.style.display =
                        "none";
                }
            }
        );
    }


    // ==========================================================
    // REMOVE DOCUMENT
    // ==========================================================

    if (removeAttachment) {

        removeAttachment.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                clearSelectedDocument();
            }
        );
    }


    function clearSelectedDocument() {

        selectedDocument = null;


        if (documentSelect) {
            documentSelect.value = "";
        }


        if (attachmentName) {
            attachmentName.textContent = "";
        }


        if (attachmentPreview) {
            attachmentPreview.style.display =
                "none";
        }
    }


    // ==========================================================
    // CLOSE DOCUMENT MODAL
    // ==========================================================

    if (closeDocument) {

        closeDocument.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                if (documentModal) {

                    documentModal.style.display =
                        "none";
                }
            }
        );
    }


    // ==========================================================
    // CLOSE MODAL WHEN CLICKING OUTSIDE
    // ==========================================================

    window.addEventListener(
        "click",
        function (event) {

            if (
                documentModal &&
                event.target === documentModal
            ) {

                documentModal.style.display =
                    "none";
            }
        }
    );


    // ==========================================================
    // SEND MESSAGE
    // ==========================================================

    async function sendMessage() {

        console.log(
            "sendMessage() called"
        );


        if (
            typeof CURRENT_CONVERSATION ===
                "undefined" ||
            !CURRENT_CONVERSATION
        ) {

            alert(
                "No conversation is currently selected."
            );

            return;
        }


        const text =
            messageInput
                ? messageInput.value.trim()
                : "";


        /*
         * Nothing to send.
         */

        if (
            !text &&
            !selectedDocument &&
            !selectedImage
        ) {

            console.log(
                "Nothing to send."
            );

            return;
        }


        /*
         * Disable send button while sending.
         */

        if (sendButton) {
            sendButton.disabled = true;
        }


        try {

            const formData =
                new FormData();


            formData.append(
                "conversation_id",
                CURRENT_CONVERSATION
            );


            formData.append(
                "message",
                text
            );


            if (selectedDocument) {

                formData.append(
                    "document_id",
                    selectedDocument
                );

                console.log(
                    "Adding document:",
                    selectedDocument
                );
            }


            if (
                selectedImage &&
                selectedImage instanceof File
            ) {

                console.log(
                    "ADDING IMAGE TO FORMDATA:",
                    selectedImage.name
                );

                formData.append(
                    "image",
                    selectedImage,
                    selectedImage.name
                );
            }


            // ------------------------------------------------
            // SEND REQUEST
            // ------------------------------------------------

            const response =
                await fetch(
                    "api/communication/send.php",
                    {
                        method: "POST",
                        body: formData,
                        cache: "no-store"
                    }
                );


            const responseText =
                await response.text();


            console.log(
                "SEND API RESPONSE:",
                responseText
            );


            if (!responseText.trim()) {

                throw new Error(
                    "send.php returned an empty response."
                );
            }


            let data;

            try {

                data =
                    JSON.parse(
                        responseText
                    );

            } catch (error) {

                console.error(
                    "Invalid JSON from send.php:",
                    responseText
                );

                throw new Error(
                    "The server returned invalid JSON."
                );
            }


            if (!data.success) {

                throw new Error(
                    data.message ||
                    "Unable to send message."
                );
            }


            console.log(
                "Message sent successfully:",
                data
            );


            // ------------------------------------------------
            // CLEAR INPUT
            // ------------------------------------------------

            if (messageInput) {
                messageInput.value = "";
            }


            clearSelectedImage();

            clearSelectedDocument();


            // ------------------------------------------------
            // MOVE CONVERSATION TO TOP
            // ------------------------------------------------

            if (
                typeof CHAT_USER_ID !==
                    "undefined"
            ) {

                moveConversationToTop(
                    CHAT_USER_ID
                );
            }


            // ------------------------------------------------
            // RELOAD MESSAGES
            // ------------------------------------------------

            loadMessages();

        }

        catch (error) {

            console.error(
                "Send message error:",
                error
            );


            alert(
                error.message ||
                "Unable to send the message."
            );

        }

        finally {

            if (sendButton) {
                sendButton.disabled = false;
            }
        }
    }


    // ==========================================================
    // SEND BUTTON
    // ==========================================================

    if (sendButton) {

        sendButton.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                sendMessage();
            }
        );
    }


    // ==========================================================
    // ENTER TO SEND
    // ==========================================================

    if (messageInput) {

        messageInput.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key === "Enter" &&
                    !event.shiftKey
                ) {

                    event.preventDefault();

                    sendMessage();
                }
            }
        );
    }


    // ==========================================================
    // LOAD MESSAGES
    // ==========================================================

    window.loadMessages =
        function () {

            if (
                typeof CURRENT_CONVERSATION ===
                    "undefined" ||
                !CURRENT_CONVERSATION
            ) {

                return;
            }


            if (!chatBody) {

                console.error(
                    "chatBody was not found."
                );

                return;
            }


            /*
             * Preserve scroll.
             */

            const wasNearBottom =
                chatBody.scrollHeight -
                chatBody.scrollTop -
                chatBody.clientHeight <
                100;


            const oldScrollHeight =
                chatBody.scrollHeight;


            const oldScrollTop =
                chatBody.scrollTop;


            fetch(
                "api/communication/messages.php" +
                "?conversation_id=" +
                encodeURIComponent(
                    CURRENT_CONVERSATION
                ) +
                "&t=" +
                Date.now(),
                {
                    cache: "no-store"
                }
            )

            .then(async response => {

                const text =
                    await response.text();


                console.log(
                    "MESSAGES API RESPONSE:",
                    text
                );


                if (!text.trim()) {

                    throw new Error(
                        "messages.php returned an empty response."
                    );
                }


                try {

                    return JSON.parse(
                        text
                    );

                } catch (error) {

                    console.error(
                        "Invalid JSON from messages.php:",
                        text
                    );

                    throw error;
                }
            })

            .then(messages => {

                if (
                    !Array.isArray(messages)
                ) {

                    console.error(
                        "messages.php did not return an array:",
                        messages
                    );

                    return;
                }


                // ------------------------------------------------
                // NO MESSAGES
                // ------------------------------------------------

                if (!messages.length) {

                    chatBody.innerHTML =
                        `
                        <div class="empty-chat">
                            No messages yet.
                        </div>
                        `;

                    markConversationAsRead();

                    return;
                }


                let html = "";


                // ------------------------------------------------
                // BUILD MESSAGES
                // ------------------------------------------------

                messages.forEach(
                    function (msg) {

                        const senderId =
                            parseInt(
                                msg.sender_id,
                                10
                            );


                        const currentUserId =
                            parseInt(
                                CURRENT_USER_ID,
                                10
                            );


                        const type =
                            senderId ===
                            currentUserId
                                ? "sent"
                                : "received";


                        const unreadClass =
                            senderId !==
                                currentUserId &&
                            !msg.read_at
                                ? "unread-message"
                                : "";


                        // ----------------------------------------
                        // MESSAGE TEXT
                        // ----------------------------------------

                        let messageHtml = "";


                        if (
                            msg.message &&
                            String(
                                msg.message
                            ).trim()
                        ) {

                            messageHtml =
                                `
                                <div class="message-text">
                                    ${
                                        escapeHtml(
                                            msg.message
                                        ).replace(
                                            /\n/g,
                                            "<br>"
                                        )
                                    }
                                </div>
                                `;
                        }


                        // ----------------------------------------
                        // IMAGE
                        // ----------------------------------------

                        let imageHtml = "";


                        if (msg.image_path) {

                            const imageUrl =
                                getFileUrl(
                                    msg.image_path
                                );


                            imageHtml =
                                `
                                <div class="message-image">
                                    <a
                                        href="${escapeAttribute(imageUrl)}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <img
                                            src="${escapeAttribute(imageUrl)}"
                                            alt="Image"
                                            loading="lazy"
                                            onclick="event.stopPropagation();"
                                        >
                                    </a>
                                </div>
                                `;
                        }


                        // ----------------------------------------
                        // DOCUMENT
                        // ----------------------------------------

                        let documentHtml = "";


                        const documentPath =
                            msg.file_path ||
                            msg.document_path ||
                            msg.attachment_path;


                        const documentName =
                            msg.file_name ||
                            msg.document_name ||
                            "Attached document";


                        if (documentPath) {

                            const documentUrl =
                                getFileUrl(
                                    documentPath
                                );


                            documentHtml =
                                `
                                <div class="message-document">

                                    <a
                                        href="${escapeAttribute(documentUrl)}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="document-link"
                                    >

                                        <span class="document-icon">
                                            <i class="fa fa-file"></i>
                                        </span>

                                        <span class="document-name">
                                            ${escapeHtml(
                                                documentName
                                            )}
                                        </span>

                                        <span class="document-open">
                                            <i class="fa fa-external-link"></i>
                                        </span>

                                    </a>

                                </div>
                                `;
                        }


                        // ----------------------------------------
                        // DATE
                        // ----------------------------------------

                        const createdAt =
                            msg.created_at ||
                            "";


                        // ----------------------------------------
                        // MESSAGE HTML
                        // ----------------------------------------

                        html +=
                            `
                            <div
                                class="message ${type} ${unreadClass}"
                                data-message-id="${escapeAttribute(
                                    msg.id || ""
                                )}"
                            >

                                <div class="message-content">

                                    ${messageHtml}

                                    ${imageHtml}

                                    ${documentHtml}

                                    <div class="message-time">
                                        ${escapeHtml(
                                            createdAt
                                        )}
                                    </div>

                                </div>

                            </div>
                            `;
                    }
                );


                // ------------------------------------------------
                // UPDATE CHAT
                // ------------------------------------------------

                chatBody.innerHTML =
                    html;


                // ------------------------------------------------
                // RESTORE SCROLL
                // ------------------------------------------------

                if (wasNearBottom) {

                    chatBody.scrollTop =
                        chatBody.scrollHeight;

                } else {

                    const newScrollHeight =
                        chatBody.scrollHeight;


                    const scrollDifference =
                        newScrollHeight -
                        oldScrollHeight;


                    chatBody.scrollTop =
                        oldScrollTop +
                        scrollDifference;
                }


                // ------------------------------------------------
                // MARK READ
                // ------------------------------------------------

                markConversationAsRead();

            })

            .catch(error => {

                console.error(
                    "Unable to load messages:",
                    error
                );
            });
        };


    // ==========================================================
    // CONVERSATION CLICK
    // ==========================================================
    //
    // IMPORTANT:
    // We use EVENT DELEGATION here.
    //
    // This means it still works if PHP/AJAX replaces the
    // conversation list after this script has loaded.
    //
    // ==========================================================

    document.addEventListener(
        "click",
        function (event) {

            const conversation =
                event.target.closest(
                    ".conversation"
                );


            if (!conversation) {
                return;
            }


            /*
             * Ignore clicks on actual links/buttons inside
             * a conversation if there are any.
             */

            if (
                event.target.closest(
                    "a, button"
                )
            ) {
                return;
            }


            const userId =
                conversation.dataset.id;


            console.log(
                "Conversation clicked. User ID:",
                userId
            );


            if (!userId) {

                console.error(
                    "Conversation has no data-id:",
                    conversation
                );

                alert(
                    "This conversation does not have a user ID."
                );

                return;
            }


            // Remove unread state.

            conversation.classList.remove(
                "has-unread"
            );


            const dot =
                conversation.querySelector(
                    ".unread-dot"
                );


            if (dot) {
                dot.remove();
            }


            /*
             * Open conversation.
             */

            window.location.href =
                "communication.php?user=" +
                encodeURIComponent(
                    userId
                );
        }
    );


    // ==========================================================
    // CONVERSATION SEARCH
    // ==========================================================

    if (search) {

        search.addEventListener(
            "input",
            function () {

                const value =
                    this.value
                        .toLowerCase()
                        .trim();


                document
                    .querySelectorAll(
                        ".conversation"
                    )
                    .forEach(
                        function (item) {

                            const text =
                                item.textContent
                                    .toLowerCase();


                            item.style.display =
                                text.includes(
                                    value
                                )
                                    ? "flex"
                                    : "none";
                        }
                    );
            }
        );
    }


    // ==========================================================
    // REFRESH UNREAD SIDEBAR
    // ==========================================================

    function refreshUnreadSidebar() {

        fetch(
            "api/communication/unread.php?t=" +
            Date.now(),
            {
                cache: "no-store"
            }
        )

        .then(async response => {

            const text =
                await response.text();


            if (!text.trim()) {

                throw new Error(
                    "unread.php returned an empty response."
                );
            }


            try {

                return JSON.parse(
                    text
                );

            } catch (error) {

                console.error(
                    "Invalid JSON from unread.php:",
                    text
                );

                throw error;
            }
        })

        .then(data => {

            if (!data.success) {
                return;
            }


            const unreadData =
                data.unread || {};


            document
                .querySelectorAll(
                    ".conversation"
                )
                .forEach(
                    function (item) {

                        const userId =
                            parseInt(
                                item.dataset.id,
                                10
                            );


                        if (isNaN(userId)) {
                            return;
                        }


                        const unread =
                            parseInt(
                                unreadData[userId] ||
                                0,
                                10
                            );


                        item.classList.remove(
                            "has-unread"
                        );


                        const existingDot =
                            item.querySelector(
                                ".unread-dot"
                            );


                        if (existingDot) {
                            existingDot.remove();
                        }


                        if (unread > 0) {

                            item.classList.add(
                                "has-unread"
                            );


                            const dot =
                                document.createElement(
                                    "span"
                                );


                            dot.className =
                                "unread-dot";


                            dot.title =
                                unread +
                                " unread message" +
                                (
                                    unread === 1
                                        ? ""
                                        : "s"
                                );


                            item.appendChild(
                                dot
                            );
                        }
                    }
                );
        })

        .catch(error => {

            console.error(
                "Unread sidebar error:",
                error
            );
        });
    }


    // ==========================================================
    // CHECK FOR NEW MESSAGES
    // ==========================================================

    function checkForNewMessages() {

        fetch(
            "api/communication/latest-message.php?t=" +
            Date.now(),
            {
                cache: "no-store"
            }
        )

        .then(async response => {

            const text =
                await response.text();


            if (!text.trim()) {

                return {
                    success: false
                };
            }


            try {

                return JSON.parse(
                    text
                );

            } catch (error) {

                console.error(
                    "Invalid JSON from latest-message.php:",
                    text
                );

                throw error;
            }
        })

        .then(data => {

            if (!data || !data.success) {
                return;
            }


            if (!data.message) {

                notificationReady = true;

                return;
            }


            const message =
                data.message;


            const messageId =
                parseInt(
                    message.id,
                    10
                );


            if (isNaN(messageId)) {
                return;
            }


            // ------------------------------------------------
            // FIRST CHECK
            // ------------------------------------------------

            if (!notificationReady) {

                latestMessageId =
                    messageId;

                notificationReady =
                    true;

                return;
            }


            // ------------------------------------------------
            // NEW MESSAGE
            // ------------------------------------------------

            if (
                messageId >
                latestMessageId
            ) {

                latestMessageId =
                    messageId;


                moveConversationToTop(
                    message.sender_id
                );


                const sender =
                    message.sender ||
                    "Someone";


                let messageText =
                    message.message ||
                    "";


                if (
                    !String(
                        messageText
                    ).trim()
                ) {

                    if (
                        message.image_path
                    ) {

                        messageText =
                            "Sent you an image";

                    } else if (
                        message.file_name
                    ) {

                        messageText =
                            "Sent you a document";

                    } else {

                        messageText =
                            "Sent you a new message";
                    }
                }


                if (
                    messageText.length >
                    80
                ) {

                    messageText =
                        messageText.substring(
                            0,
                            80
                        ) +
                        "...";
                }


                // ------------------------------------------------
                // SWEETALERT
                // ------------------------------------------------

                if (
                    typeof Swal !==
                    "undefined"
                ) {

                    Swal.fire({

                        toast: true,

                        position:
                            "top-end",

                        icon:
                            "info",

                        title:
                            "New message from " +
                            sender,

                        text:
                            messageText,

                        showConfirmButton:
                            false,

                        showCloseButton:
                            true,

                        timer:
                            6000,

                        timerProgressBar:
                            true,

                        didOpen:
                            function (toast) {

                                toast.style.cursor =
                                    "pointer";


                                toast.addEventListener(
                                    "click",
                                    function () {

                                        window.location.href =
                                            "communication.php?user=" +
                                            encodeURIComponent(
                                                message.sender_id
                                            );
                                    }
                                );
                            }
                    });
                }


                refreshUnreadSidebar();
            }
        })

        .catch(error => {

            console.error(
                "Message notification error:",
                error
            );
        });
    }


    // ==========================================================
    // MOVE CONVERSATION TO TOP
    // ==========================================================

    function moveConversationToTop(userId) {

        const conversationList =
            document.querySelector(
                ".conversation-scroll"
            );


        if (!conversationList) {
            return;
        }


        const conversation =
            document.querySelector(
                `.conversation[data-id="${userId}"]`
            );


        if (!conversation) {
            return;
        }


        conversationList.prepend(
            conversation
        );
    }


    // ==========================================================
    // INITIAL LOAD
    // ==========================================================

    if (
        typeof CURRENT_CONVERSATION !==
            "undefined" &&
        CURRENT_CONVERSATION > 0
    ) {

        loadMessages();

        markConversationAsRead();
    }


    // ==========================================================
    // INITIAL SIDEBAR REFRESH
    // ==========================================================

    refreshUnreadSidebar();


    // ==========================================================
    // INITIAL NOTIFICATION CHECK
    // ==========================================================

    checkForNewMessages();


    // ==========================================================
    // AUTO REFRESH
    // ==========================================================

    setInterval(
        function () {

            if (
                typeof CURRENT_CONVERSATION !==
                    "undefined" &&
                CURRENT_CONVERSATION > 0
            ) {

                loadMessages();
            }


            checkForNewMessages();

            refreshUnreadSidebar();

        },
        5000
    );


    // ==========================================================
    // DEBUG INFORMATION
    // ==========================================================

    console.log(
        "Communication system initialized."
    );

    console.log(
        "Current conversation:",
        typeof CURRENT_CONVERSATION !== "undefined"
            ? CURRENT_CONVERSATION
            : "undefined"
    );

    console.log(
        "Current user:",
        typeof CURRENT_USER_ID !== "undefined"
            ? CURRENT_USER_ID
            : "undefined"
    );

});

/* ======================================================
   FULL IMAGE VIEWER
====================================================== */

document.addEventListener("click", function (event) {

    const image = event.target.closest(".chat-image img");

    if (!image) {
        return;
    }

    const viewer = document.getElementById("imageViewer");
    const viewerImage = document.getElementById("imageViewerImage");

    if (!viewer || !viewerImage) {
        return;
    }

    viewerImage.src = image.src;

    viewer.style.display = "flex";

});


/* ======================================================
   CLOSE IMAGE VIEWER
====================================================== */

document.addEventListener("click", function (event) {

    const viewer = document.getElementById("imageViewer");
    const closeButton = document.getElementById("imageViewerClose");

    if (!viewer) {
        return;
    }

    /* Click X */

    if (event.target === closeButton) {

        viewer.style.display = "none";

        const viewerImage =
            document.getElementById("imageViewerImage");

        if (viewerImage) {
            viewerImage.src = "";
        }

        return;
    }


    /* Click dark background */

    if (event.target === viewer) {

        viewer.style.display = "none";

        const viewerImage =
            document.getElementById("imageViewerImage");

        if (viewerImage) {
            viewerImage.src = "";
        }

    }

});


/* ======================================================
   ESCAPE KEY
====================================================== */

document.addEventListener("keydown", function (event) {

    if (event.key !== "Escape") {
        return;
    }

    const viewer = document.getElementById("imageViewer");

    if (!viewer) {
        return;
    }

    viewer.style.display = "none";

    const viewerImage =
        document.getElementById("imageViewerImage");

    if (viewerImage) {
        viewerImage.src = "";
    }

});
