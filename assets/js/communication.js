document.addEventListener("DOMContentLoaded", () => {

    // ==========================================================
    // NEW MESSAGE NOTIFICATION
    // ==========================================================

    let latestMessageId = 0;
    let notificationReady = false;


    function checkForNewMessages() {

        fetch(
            "api/communication/latest-message.php?t=" + Date.now(),
            {
                cache: "no-store"
            }
        )

        .then(async response => {

            const text = await response.text();

            console.log(
                "Latest message API response:",
                text
            );


            if (!text.trim()) {

                console.warn(
                    "latest-message.php returned an empty response."
                );

                return {
                    success: false,
                    message: "Empty API response"
                };

            }


            try {

                return JSON.parse(text);

            } catch (error) {

                console.error(
                    "Invalid JSON from latest-message.php:",
                    text
                );

                throw error;

            }

        })

        .then(data => {

            if (!data) {
                return;
            }


            console.log(
                "Latest message data:",
                data
            );


            if (!data.success) {

                /*
                 * Do not break the notification system if the
                 * endpoint temporarily returns an error.
                 */

                console.error(
                    "Latest message API failed:",
                    data
                );

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

                console.error(
                    "Invalid message ID:",
                    message
                );

                return;

            }


            // ==================================================
            // FIRST CHECK
            // ==================================================

            if (!notificationReady) {

                latestMessageId =
                    messageId;

                notificationReady =
                    true;

                return;

            }


            // ==================================================
            // NEW MESSAGE
            // ==================================================

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


                /*
                 * If there is no text message, determine what
                 * kind of attachment was sent.
                 */

                if (!messageText.trim()) {

                    if (message.image_path) {

                        messageText =
                            "Sent you an image";

                    } else if (message.file_name) {

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
                        ) + "...";

                }


                console.log(
                    "NEW MESSAGE DETECTED:",
                    message
                );


                /*
                 * SweetAlert notification
                 */

                if (
                    typeof Swal !==
                    "undefined"
                ) {

                    Swal.fire({

                        toast: true,

                        position:
                            "top-end",

                        icon: "info",

                        title:
                            "New message from " +
                            sender,

                        text:
                            messageText,

                        showConfirmButton:
                            false,

                        showCloseButton:
                            true,

                        timer: 6000,

                        timerProgressBar:
                            true,

                        didOpen:
                            (toast) => {

                                toast.style.cursor =
                                    "pointer";


                                toast.addEventListener(
                                    "click",
                                    () => {

                                        window.location.href =
                                            "communication.php?user=" +
                                            message.sender_id;

                                    }
                                );

                            }

                    });

                }


                /*
                 * Refresh unread sidebar
                 */

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
    // REFRESH SIDEBAR UNREAD COUNTS
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

                return JSON.parse(text);

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


            /*
             * Make sure unread exists.
             */

            const unreadData =
                data.unread || {};


            document
                .querySelectorAll(
                    ".conversation"
                )
                .forEach(item => {

                    const userId =
                        parseInt(
                            item.dataset.id,
                            10
                        );


                    const unread =
                        parseInt(
                            unreadData[userId] ||
                            0,
                            10
                        );


                    /*
                     * Remove existing unread state
                     */

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


                    /*
                     * Add unread dot
                     */

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

                });

        })

        .catch(error => {

            console.error(
                "Unread sidebar error:",
                error
            );

        });

    }



    // ==========================================================
// VARIABLES
// ==========================================================
let selectedDocument = null;
let selectedImage = null;
   


    imageInput?.addEventListener("change", function () {
    console.log("=================================");
    console.log("IMAGE INPUT CHANGED");
    console.log("=================================");

    const file = this.files && this.files.length
        ? this.files[0]
        : null;

    if (!file) {
        console.log("No image selected.");
        selectedImage = null;
        return;
    }

    console.log("Selected image:", file);
    console.log("Name:", file.name);
    console.log("Type:", file.type);
    console.log("Size:", file.size);

    const allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/gif",
        "image/webp"
    ];

    if (!allowedTypes.includes(file.type)) {
        alert("Please select a JPG, PNG, GIF or WEBP image.");

        this.value = "";
        selectedImage = null;

        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert("Image must not exceed 5 MB.");

        this.value = "";
        selectedImage = null;

        return;
    }

    // IMPORTANT
    selectedImage = file;

    console.log("selectedImage is now:", selectedImage);

    // Preview
    if (imagePreviewImage) {
        const reader = new FileReader();

        reader.onload = function (event) {
            imagePreviewImage.src = event.target.result;
        };

        reader.readAsDataURL(file);
    }

    if (imagePreviewName) {
        imagePreviewName.textContent = file.name;
    }

    if (imagePreview) {
        imagePreview.style.display = "block";
    }
});

    const messageInput =
        document.getElementById(
            "messageInput"
        );


    const sendButton =
        document.getElementById(
            "sendMessageBtn"
        );


    const chatBody =
        document.getElementById(
            "chatBody"
        );


    const documentModal =
        document.getElementById(
            "documentModal"
        );


    const documentSelect =
        document.getElementById(
            "documentSelect"
        );


    const attachButton =
        document.getElementById(
            "attachDocumentBtn"
        );


    const confirmAttach =
        document.getElementById(
            "selectDocument"
        );


    const closeDocument =
        document.getElementById(
            "closeDocument"
        );


    const attachmentPreview =
        document.getElementById(
            "attachmentPreview"
        );


    const attachmentName =
        document.getElementById(
            "attachmentName"
        );


    const removeAttachment =
        document.getElementById(
            "removeAttachment"
        );


    const search =
        document.getElementById(
            "conversationSearch"
        );



    // ==========================================================
    // MARK CONVERSATION AS READ
    // ==========================================================

    function markConversationAsRead() {

        if (
            typeof CURRENT_CONVERSATION ===
                "undefined" ||
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
                "Mark conversation as read:",
                data
            );


            if (!data.success) {

                console.error(
                    "Unable to mark messages as read:",
                    data
                );

                return;

            }


            /*
             * Remove unread styling from messages
             */

            document
                .querySelectorAll(
                    ".unread-message"
                )
                .forEach(message => {

                    message.classList.remove(
                        "unread-message"
                    );

                });


            /*
             * Remove unread styling from selected
             * sidebar conversation.
             */

            if (
                typeof CHAT_USER_ID !==
                "undefined"
            ) {

                const selectedConversation =
                    document.querySelector(
                        `.conversation[data-id="${CHAT_USER_ID}"]`
                    );


                if (selectedConversation) {

                    selectedConversation.classList.remove(
                        "has-unread"
                    );


                    const dot =
                        selectedConversation.querySelector(
                            ".unread-dot"
                        );


                    if (dot) {

                        dot.remove();

                    }

                }

            }

        })

        .catch(error => {

            console.error(
                "Mark-read error:",
                error
            );

        });

    }



    // ==========================================================
    // IMAGE ATTACHMENT
    // ==========================================================

    attachImageBtn?.addEventListener(
        "click",
        () => {

            console.log(
                "Image button clicked"
            );


            if (!imageInput) {

                console.error(
                    "imageInput element was not found."
                );

                return;

            }


            imageInput.click();

        }
    );


    imageInput?.addEventListener(
        "change",
        function () {

            console.log(
                "Image input changed"
            );


            const file =
                this.files?.[0];


            if (!file) {

                console.log(
                    "No image selected."
                );

                return;

            }


            console.log(
                "Selected image:",
                file.name
            );


            /*
             * Allowed image MIME types
             */

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


            /*
             * Maximum size = 5 MB
             */

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


            /*
             * Store selected image
             */

            selectedImage =
                file;


            /*
             * Show preview
             */

            if (
                imagePreviewImage
            ) {

                const reader =
                    new FileReader();


                reader.onload =
                    function (event) {

                        imagePreviewImage.src =
                            event.target.result;

                    };


                reader.readAsDataURL(
                    file
                );

            }


            if (
                imagePreviewName
            ) {

                imagePreviewName.textContent =
                    file.name;

            }


            if (
                imagePreview
            ) {

                imagePreview.style.display =
                    "block";

            }

        }
    );


    /*
     * Remove selected image
     */

    removeImage?.addEventListener(
        "click",
        () => {

            selectedImage =
                null;


            if (imageInput) {

                imageInput.value =
                    "";

            }


            if (
                imagePreviewImage
            ) {

                imagePreviewImage.src =
                    "";

            }


            if (
                imagePreviewName
            ) {

                imagePreviewName.textContent =
                    "";

            }


            if (
                imagePreview
            ) {

                imagePreview.style.display =
                    "none";

            }

                "Image removed."
});

        }
    );



    // ==========================================================
    // AUTO RESIZE MESSAGE INPUT
    // ==========================================================

    messageInput?.addEventListener(
        "input",
        function () {

            this.style.height =
                "auto";


            this.style.height =
                Math.min(
                    this.scrollHeight,
                    120
                ) + "px";

        }
    );



    // ==========================================================
    // CLEAR DOCUMENT ATTACHMENT
    // ==========================================================

    function clearAttachment() {

        selectedDocument =
            null;


        if (attachmentName) {

            attachmentName.textContent =
                "";

        }


        if (attachmentPreview) {

            attachmentPreview.style.display =
                "none";

        }


        if (documentSelect) {

            documentSelect.selectedIndex =
                0;

        }

    }



    // ==========================================================
    // SEND MESSAGE
    // ==========================================================

  window.sendMessage = function () {

    if (!messageInput) {
        console.error("messageInput was not found.");
        return;
    }

    const message = messageInput.value.trim();

    console.log("=================================");
    console.log("SEND MESSAGE");
    console.log("=================================");
    console.log("Text:", message);
    console.log("selectedDocument:", selectedDocument);
    console.log("selectedImage:", selectedImage);

    if (!message && !selectedDocument && !selectedImage) {
        console.error("NOTHING TO SEND");
        return;
    }

    if (!CURRENT_CONVERSATION) {
        alert("No active conversation.");
        return;
    }

    if (sendButton) {
        sendButton.disabled = true;
    }

    const formData = new FormData();

    formData.append(
        "conversation_id",
        CURRENT_CONVERSATION
    );

    formData.append(
        "message",
        message
    );

    formData.append(
        "document_id",
        selectedDocument || ""
    );

    // ==========================================
    // IMAGE
    // ==========================================

    if (selectedImage instanceof File) {

        console.log(
            "ADDING IMAGE TO FORMDATA:",
            selectedImage.name
        );

        formData.append(
            "image",
            selectedImage,
            selectedImage.name
        );

    } else {

        console.log(
            "NO IMAGE ADDED TO FORMDATA"
        );
    }

    // ==========================================
    // DEBUG FORMDATA
    // ==========================================

    for (const [key, value] of formData.entries()) {

        if (value instanceof File) {

            console.log(
                "FormData:",
                key,
                value.name,
                value.type,
                value.size
            );

        } else {

            console.log(
                "FormData:",
                key,
                value
            );
        }
    }

    // ==========================================
    // SEND
    // ==========================================

    fetch(
        "api/communication/send.php",
        {
            method: "POST",
            body: formData
        }
    )
    .then(async response => {

        const text = await response.text();

        console.log(
            "Send message response:",
            text
        );

        if (!text.trim()) {
            throw new Error(
                "send.php returned an empty response."
            );
        }

        try {
            return JSON.parse(text);

        } catch (error) {

            console.error(
                "Invalid JSON from send.php:",
                text
            );

            throw error;
        }
    })
    .then(data => {

        if (!data.success) {

            alert(
                data.message ||
                "Unable to send message."
            );

            return;
        }

        // ==========================================
        // CLEAR TEXT
        // ==========================================

        messageInput.value = "";
        messageInput.style.height = "auto";

        // ==========================================
        // CLEAR DOCUMENT
        // ==========================================

        clearAttachment();

        // ==========================================
        // CLEAR IMAGE
        // ==========================================

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
            imagePreview.style.display = "none";
        }

        // ==========================================
        // MOVE CONVERSATION TO TOP
        // ==========================================

        if (typeof CHAT_USER_ID !== "undefined") {
            moveConversationToTop(CHAT_USER_ID);
        }

        // ==========================================
        // RELOAD MESSAGES
        // ==========================================

        loadMessages();
    })
    .catch(error => {

        console.error(
            "Send message error:",
            error
        );

        alert(
            error.message ||
            "Unable to connect to the server."
        );

    })
    .finally(() => {

        if (sendButton) {
            sendButton.disabled = false;
        }

    });
};



    // ==========================================================
    // LOAD MESSAGES
    // ==========================================================

    window.loadMessages =
        function () {

            if (
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
             * Remember current scroll position
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
                `api/communication/messages.php?conversation_id=${CURRENT_CONVERSATION}&t=${Date.now()}`,
                {
                    cache: "no-store"
                }
            )

            .then(async response => {

                const text =
                    await response.text();


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

                /*
                 * Make sure we received an array.
                 */

                if (
                    !Array.isArray(
                        messages
                    )
                ) {

                    console.error(
                        "messages.php did not return an array:",
                        messages
                    );

                    return;

                }


                /*
                 * No messages
                 */

                if (
                    !messages.length
                ) {

                    chatBody.innerHTML = `
                        <div class="empty-chat">
                            No messages yet.
                        </div>
                    `;


                    markConversationAsRead();

                    return;

                }


                let html =
                    "";


                messages.forEach(
                    msg => {

                        const type =
                            msg.sender_id ==
                            CURRENT_USER_ID
                                ? "sent"
                                : "received";


                        /*
                         * Determine unread state
                         */

                        const unreadClass =
                            (
                                msg.sender_id !=
                                    CURRENT_USER_ID &&
                                !msg.read_at
                            )
                                ? "unread-message"
                                : "";


                        /*
                         * Message text
                         */

                        const messageHtml =
                            msg.message
                                ? `
                                    <div class="message-text">
                                        ${escapeHtml(
                                            msg.message
                                        ).replace(
                                            /\n/g,
                                            "<br>"
                                        )}
                                    </div>
                                  `
                                : "";


                        /*
                         * Image attachment
                         */

                        let imageHtml =
                            "";


                        if (
                            msg.image_path
                        ) {

                            const imagePath =
                                getFileUrl(
                                    msg.image_path
                                );


                            imageHtml = `
                                <div class="chat-image">

                                    <a
                                        href="${escapeAttribute(
                                            imagePath
                                        )}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >

                                        <img
                                            src="${escapeAttribute(
                                                imagePath
                                            )}"
                                            alt="Image"
                                            loading="lazy"
                                        >

                                    </a>

                                </div>
                            `;

                        }


                        /*
                         * Document attachment
                         */

                        let documentHtml =
                            "";


                        if (
                            msg.file_name
                        ) {

                            const filePath =
                                getFileUrl(
                                    msg.file_path ||
                                    ""
                                );


                            documentHtml = `
                                <div class="attachment">

                                    <i class="fa fa-file-pdf"></i>

                                    <a
                                        href="${escapeAttribute(
                                            filePath
                                        )}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        ${escapeHtml(
                                            msg.file_name
                                        )}
                                    </a>

                                </div>
                            `;

                        }


                        /*
                         * Build message
                         */

                        html += `

                            <div
                                class="message-row ${type} ${unreadClass}"
                            >

                                <div class="message-header">

                                    <span class="sender">
                                        ${escapeHtml(
                                            msg.sender ||
                                            ""
                                        )}
                                    </span>

                                    <span class="time">
                                        ${escapeHtml(
                                            msg.created_at ||
                                            ""
                                        )}
                                    </span>

                                </div>


                                ${messageHtml}


                                ${imageHtml}


                                ${documentHtml}

                            </div>

                        `;

                    }
                );


                /*
                 * Replace messages
                 */

                chatBody.innerHTML =
                    html;


                /*
                 * Restore scroll position
                 */

                if (
                    wasNearBottom
                ) {

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


                /*
                 * Mark incoming messages as read
                 */

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
    // FILE URL HELPER
    // ==========================================================

    function getFileUrl(path) {

        if (!path) {
            return "";
        }


        /*
         * Already an absolute URL
         */

        if (
            path.startsWith(
                "http://"
            ) ||
            path.startsWith(
                "https://"
            ) ||
            path.startsWith(
                "/"
            )
        ) {

            return path;

        }


        /*
         * Our PHP stores paths like:
         *
         * uploads/communication/images/file.jpg
         *
         * The communication system is located at:
         *
         * /Communication/
         */

        return (
            "/Communication/" +
            path.replace(
                /^\/+/,
                ""
            )
        );

    }



    // ==========================================================
    // ESCAPE HTML
    // ==========================================================

    function escapeHtml(value) {

        const div =
            document.createElement(
                "div"
            );


        div.textContent =
            value ?? "";


        return div.innerHTML;

    }



    // ==========================================================
    // ESCAPE ATTRIBUTE
    // ==========================================================

    function escapeAttribute(value) {

        return escapeHtml(
            value
        )
        .replace(
            /"/g,
            "&quot;"
        )
        .replace(
            /'/g,
            "&#039;"
        );

    }



    // ==========================================================
    // DOCUMENT ATTACHMENT
    // ==========================================================

    attachButton?.addEventListener(
        "click",
        () => {

            if (!documentModal) {
                return;
            }


            documentModal.style.display =
                "flex";

        }
    );


    confirmAttach?.addEventListener(
        "click",
        () => {

            if (
                !documentSelect ||
                !documentSelect.value
            ) {

                return;

            }


            selectedDocument =
                documentSelect.value;


            if (attachmentName) {

                attachmentName.textContent =
                    documentSelect
                        .options[
                            documentSelect.selectedIndex
                        ]
                        .text;

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


    removeAttachment?.addEventListener(
        "click",
        clearAttachment
    );


    closeDocument?.addEventListener(
        "click",
        () => {

            if (documentModal) {

                documentModal.style.display =
                    "none";

            }

        }
    );


    window.addEventListener(
        "click",
        e => {

            if (
                documentModal &&
                e.target ===
                    documentModal
            ) {

                documentModal.style.display =
                    "none";

            }

        }
    );



    // ==========================================================
    // SEND WITH ENTER
    // ==========================================================

    sendButton?.addEventListener(
        "click",
        () => {

            sendMessage();

        }
    );


    messageInput?.addEventListener(
        "keydown",
        e => {

            /*
             * Enter = send
             *
             * Shift + Enter = new line
             */

            if (
                e.key === "Enter" &&
                !e.shiftKey
            ) {

                e.preventDefault();

                sendMessage();

            }

        }
    );



    // ==========================================================
    // CONVERSATION SEARCH
    // ==========================================================

    search?.addEventListener(
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
                .forEach(item => {

                    item.style.display =
                        item.textContent
                            .toLowerCase()
                            .includes(value)
                            ? "flex"
                            : "none";

                });

        }
    );



    // ==========================================================
    // CONVERSATION CLICK
    // ==========================================================

    document
        .querySelectorAll(
            ".conversation"
        )
        .forEach(item => {

            item.addEventListener(
                "click",
                () => {

                    const userId =
                        item.dataset.id;


                    /*
                     * Remove unread dot immediately
                     */

                    item.classList.remove(
                        "has-unread"
                    );


                    const dot =
                        item.querySelector(
                            ".unread-dot"
                        );


                    if (dot) {

                        dot.remove();

                    }


                    /*
                     * Open conversation
                     */

                    window.location.href =
                        `communication.php?user=${encodeURIComponent(
                            userId
                        )}`;

                }
            );

        });



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
    // INITIAL MESSAGE NOTIFICATION CHECK
    // ==========================================================

    checkForNewMessages();



    // ==========================================================
    // AUTO REFRESH
    // ==========================================================

    setInterval(
        () => {

            /*
             * Reload messages for the active conversation.
             */

            if (
                typeof loadMessages ===
                "function" &&
                typeof CURRENT_CONVERSATION !==
                    "undefined" &&
                CURRENT_CONVERSATION > 0
            ) {

                loadMessages();

            }


            /*
             * Check for new messages.
             */

            checkForNewMessages();


            /*
             * Refresh unread dots.
             */

            refreshUnreadSidebar();

        },
        5000
    );

;



// ==========================================================
// MOVE CONVERSATION TO TOP
// ==========================================================

function moveConversationToTop(
    userId
) {

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


    /*
     * Move conversation to the top.
     */

    conversationList.prepend(
        conversation
    );

}