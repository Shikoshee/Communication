document.addEventListener("DOMContentLoaded", function () {

    if (document.getElementById("mailList")) {
        loadInbox();
    }

});


function loadInbox() {

    fetch("../api/mail/list.php")

        .then(response => response.json())

        .then(result => {

            if (!result.success) {

                document.getElementById("mailList").innerHTML = `
                    <div class="mail-error">
                        ${result.message || "Unable to load mail."}
                    </div>
                `;

                return;
            }

            renderMailList(result.data);

        })

        .catch(error => {

            console.error(error);

            document.getElementById("mailList").innerHTML = `
                <div class="mail-error">
                    Unable to connect to the mail server.
                </div>
            `;

        });

}


function renderMailList(messages) {

    const container = document.getElementById("mailList");

    if (!messages || messages.length === 0) {

        container.innerHTML = `
            <div class="mail-empty">
                <i class="fa fa-inbox"></i>
                <h3>Your inbox is empty</h3>
                <p>No messages have been received.</p>
            </div>
        `;

        return;
    }


    let unread = 0;


    let html = "";


    messages.forEach(function (mail) {

        if (parseInt(mail.is_read) === 0) {
            unread++;
        }


        const sender =
            `${mail.first_name || ""} ${mail.last_name || ""}`.trim();


        const subject =
            mail.subject || "(No subject)";


        const preview =
            stripHtml(mail.body || "")
                .substring(0, 100);


        html += `

            <div
                class="mail-row ${mail.is_read == 0 ? "unread" : ""}"
                onclick="openMail(${mail.id})">

                <div class="mail-checkbox"
                     onclick="event.stopPropagation();">

                    <input
                        type="checkbox"
                        class="mail-select"
                        value="${mail.id}">

                </div>


                <div class="mail-star"
                     onclick="event.stopPropagation();">

                    <i class="fa ${
                        mail.is_starred == 1
                        ? "fa-star"
                        : "fa-star-o"
                    }"></i>

                </div>


                <div class="mail-sender">

                    ${escapeHtml(sender)}

                </div>


                <div class="mail-subject">

                    <strong>
                        ${escapeHtml(subject)}
                    </strong>

                    <span>
                        - ${escapeHtml(preview)}
                    </span>

                </div>


                <div class="mail-date">

                    ${formatMailDate(mail.created_at)}

                </div>

            </div>

        `;

    });


    container.innerHTML = html;


    const unreadCount =
        document.getElementById("unreadCount");


    if (unreadCount) {
        unreadCount.textContent = unread;
    }

}


function openMail(id) {

    window.location.href =
        "view.php?id=" + encodeURIComponent(id);

}


function searchMail() {

    const input =
        document.getElementById("mailSearch");

    if (!input) return;


    const search =
        input.value.toLowerCase().trim();


    document
        .querySelectorAll(".mail-row")
        .forEach(row => {

            row.style.display =
                row.textContent
                    .toLowerCase()
                    .includes(search)
                    ? ""
                    : "none";

        });

}


function markSelectedRead() {

    const selected =
        getSelectedMessages();


    if (selected.length === 0) {

        Swal.fire(
            "Nothing selected",
            "Select at least one message.",
            "info"
        );

        return;

    }


    Promise.all(

        selected.map(id =>

            fetch("../api/mail/mark-read.php", {

                method: "POST",

                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded"
                },

                body:
                    new URLSearchParams({
                        message_id: id
                    })

            })

        )

    ).then(() => {

        loadInbox();

    });

}


function deleteSelected() {

    const selected =
        getSelectedMessages();


    if (selected.length === 0) {

        Swal.fire(
            "Nothing selected",
            "Select at least one message.",
            "info"
        );

        return;

    }


    Swal.fire({

        title: "Move to Trash?",

        text:
            "The selected messages will be moved to trash.",

        icon: "warning",

        showCancelButton: true,

        confirmButtonText: "Move to Trash",

        confirmButtonColor: "#dc3545"

    }).then(result => {

        if (!result.isConfirmed) return;


        Promise.all(

            selected.map(id =>

                fetch("../api/mail/delete.php", {

                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/x-www-form-urlencoded"
                    },

                    body:
                        new URLSearchParams({
                            message_id: id
                        })

                })

            )

        ).then(() => {

            Swal.fire(
                "Moved",
                "Messages moved to trash.",
                "success"
            ).then(() => {

                loadInbox();

            });

        });

    });

}


function getSelectedMessages() {

    return Array.from(

        document.querySelectorAll(
            ".mail-select:checked"
        )

    ).map(
        checkbox => checkbox.value
    );

}


function stripHtml(html) {

    const div =
        document.createElement("div");

    div.innerHTML = html;

    return div.textContent || div.innerText || "";

}


function escapeHtml(value) {

    return String(value)

        .replace(/&/g, "&amp;")

        .replace(/</g, "&lt;")

        .replace(/>/g, "&gt;")

        .replace(/"/g, "&quot;")

        .replace(/'/g, "&#039;");

}


function formatMailDate(dateString) {

    if (!dateString) {
        return "";
    }

    const date =
        new Date(dateString.replace(" ", "T"));


    if (isNaN(date.getTime())) {
        return dateString;
    }


    return date.toLocaleDateString(
        undefined,
        {
            year: "numeric",
            month: "short",
            day: "numeric"
        }
    );

}