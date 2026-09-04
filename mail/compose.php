<?php

require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::protect();

$user = Auth::getCurrentUser();

if (!$user) {
    die("Not authenticated.");
}


$userId = (int)($user['id'] ?? 0);

$replyId = (int)($_GET['reply'] ?? 0);

$forwardId = (int)($_GET['forward'] ?? 0);

$draftId = (int)($_GET['draft'] ?? 0);


$users = fetchAll(

    "SELECT
        id,
        first_name,
        last_name,
        email

     FROM users

     WHERE status='active'
     AND id<>?

     ORDER BY first_name, last_name",

    [
        $userId
    ]

);


$subject = '';

$body = '';

$selectedRecipient = 0;

$composeTitle = "Compose Message";


/*
 * ==========================================================
 * REPLY
 * ==========================================================
 */

if ($replyId > 0) {

    $replyMessage = fetchRow(

        "SELECT

            m.id,
            m.sender_id,
            m.subject,
            m.body,
            m.created_at,

            u.first_name,
            u.last_name,
            u.email

         FROM mail_messages m

         INNER JOIN users u
            ON u.id=m.sender_id

         WHERE m.id=?

         AND (

            m.sender_id=?

            OR EXISTS (

                SELECT 1
                FROM mail_recipients mr
                WHERE mr.message_id=m.id
                AND mr.user_id=?

            )

         )

         LIMIT 1",

        [
            $replyId,
            $userId,
            $userId
        ]

    );


    if ($replyMessage) {

        $selectedRecipient =
            (int)$replyMessage['sender_id'];

        $subject = $replyMessage['subject'];

        if (
            stripos(
                trim($subject),
                're:'
            ) !== 0
        ) {

            $subject = 'Re: ' . $subject;

        }


        $senderName = trim(
            $replyMessage['first_name'] .
            ' ' .
            $replyMessage['last_name']
        );


        $body =

            "\n\n\n" .

            "----- Original Message -----\n" .

            "From: " .
            $senderName .
            " <" .
            $replyMessage['email'] .
            ">\n" .

            "Date: " .
            $replyMessage['created_at'] .
            "\n" .

            "Subject: " .
            $replyMessage['subject'] .
            "\n\n" .

            $replyMessage['body'];


        $composeTitle = "Reply";

    }

}


/*
 * ==========================================================
 * FORWARD
 * ==========================================================
 */

if ($forwardId > 0) {

    $forwardMessage = fetchRow(

        "SELECT

            m.id,
            m.subject,
            m.body,
            m.created_at,

            u.first_name,
            u.last_name,
            u.email

         FROM mail_messages m

         INNER JOIN users u
            ON u.id=m.sender_id

         WHERE m.id=?

         AND (

            m.sender_id=?

            OR EXISTS (

                SELECT 1
                FROM mail_recipients mr
                WHERE mr.message_id=m.id
                AND mr.user_id=?

            )

         )

         LIMIT 1",

        [
            $forwardId,
            $userId,
            $userId
        ]

    );


    if ($forwardMessage) {

        $subject = $forwardMessage['subject'];

        if (
            stripos(
                trim($subject),
                'fwd:'
            ) !== 0
        ) {

            $subject = 'Fwd: ' . $subject;

        }


        $senderName = trim(
            $forwardMessage['first_name'] .
            ' ' .
            $forwardMessage['last_name']
        );


        $body =

            "\n\n\n" .

            "----- Forwarded Message -----\n" .

            "From: " .
            $senderName .
            " <" .
            $forwardMessage['email'] .
            ">\n" .

            "Subject: " .
            $forwardMessage['subject'] .
            "\n\n" .

            $forwardMessage['body'];


        $composeTitle = "Forward Message";

    }

}


/*
 * ==========================================================
 * DRAFT
 * ==========================================================
 */

if ($draftId > 0) {

    $draft = fetchRow(

        "SELECT *

         FROM mail_messages

         WHERE id=?
         AND sender_id=?
         AND is_draft=1

         LIMIT 1",

        [
            $draftId,
            $userId
        ]

    );


    if ($draft) {

        $subject = $draft['subject'];

        $body = $draft['body'];

        $composeTitle = "Edit Draft";


        $draftRecipient = fetchRow(

            "SELECT user_id

             FROM mail_recipients

             WHERE message_id=?

             LIMIT 1",

            [
                $draftId
            ]

        );


        if ($draftRecipient) {

            $selectedRecipient =
                (int)$draftRecipient['user_id'];

        }

    }

}


include "../includes/header.php";
include "../includes/sidebar.php";
include "../includes/navbar.php";

?>

<link rel="stylesheet" href="/Communication/assets/css/mail.css">


<div class="mail-page">

    <div class="mail-header">

        <div>

            <h1>
                <?= htmlspecialchars($composeTitle) ?>
            </h1>

            <p>
                Send an internal message
            </p>

        </div>


        <!-- ==================================================
             HEADER ACTIONS
        =================================================== -->

        <div class="mail-header-actions">

            <a
                href="index.php"
                class="mail-back-btn">

                <i class="fa fa-arrow-left"></i>

                Back to Inbox

            </a>

        </div>

    </div>


    <div class="compose-container">

        <form id="composeForm">


            <input
                type="hidden"
                name="draft_id"
                id="draftId"
                value="<?= $draftId ?>">


            <input
                type="hidden"
                name="parent_id"
                value="<?= $replyId ?: $forwardId ?>">


            <input
                type="hidden"
                name="message_type"
                value="<?=
                    $replyId
                        ? 'reply'
                        : ($forwardId ? 'forward' : 'sent')
                ?>">


            <label>To</label>

            <select
                name="to"
                id="mailTo"
                required>

                <option value="">
                    Select recipient
                </option>

                <?php foreach ($users as $recipient) { ?>

                    <option
                        value="<?= (int)$recipient['id'] ?>"
                        <?= $selectedRecipient === (int)$recipient['id']
                            ? 'selected'
                            : '' ?>>

                        <?= htmlspecialchars(
                            trim(
                                $recipient['first_name'] .
                                ' ' .
                                $recipient['last_name']
                            )
                        ) ?>

                        -
                        <?= htmlspecialchars(
                            $recipient['email']
                        ) ?>

                    </option>

                <?php } ?>

            </select>


            <label>Subject</label>

            <input
                type="text"
                name="subject"
                id="mailSubject"
                maxlength="255"
                value="<?= htmlspecialchars($subject) ?>"
                placeholder="Subject">


            <label>Message</label>

            <textarea
                name="body"
                id="mailBody"
                rows="18"
                placeholder="Write your message..."
                required><?= htmlspecialchars($body) ?></textarea>


            <div class="compose-actions">

                <button
                    type="button"
                    onclick="saveDraft()"
                    class="draft-btn">

                    <i class="fa fa-save"></i>

                    Save Draft

                </button>


                <button
                    type="submit"
                    class="send-btn">

                    <i class="fa fa-paper-plane"></i>

                    Send

                </button>

            </div>

        </form>

    </div>

</div>


<script>

document
    .getElementById("composeForm")
    .addEventListener(
        "submit",
        function(e) {

            e.preventDefault();

            sendMail();

        }
    );


function sendMail() {

    const form =
        document.getElementById("composeForm");

    const formData =
        new FormData(form);


    fetch("../api/mail/send.php", {

        method: "POST",

        body: formData

    })

    .then(async response => {

        const text = await response.text();

        console.log(
            "SEND MAIL HTTP STATUS:",
            response.status
        );

        console.log(
            "SEND MAIL RAW RESPONSE:",
            text
        );


        try {

            return JSON.parse(text);

        } catch (error) {

            console.error(
                "Invalid JSON returned by send.php:",
                text
            );

            throw new Error(
                "Server returned an invalid response. Check the browser console."
            );

        }

    })

    .then(result => {

        console.log(
            "SEND MAIL RESULT:",
            result
        );


        if (result.success) {

            Swal.fire(
                "Sent",
                result.message ||
                "Message sent successfully.",
                "success"
            ).then(() => {

                window.location.href =
                    "sent.php";

            });

        } else {

            Swal.fire(
                "Error",
                result.message ||
                "Unable to send message.",
                "error"
            );

        }

    })

    .catch(error => {

        console.error(
            "SEND MAIL ERROR:",
            error
        );

        Swal.fire(
            "Error",
            error.message ||
            "Unable to send message.",
            "error"
        );

    });

}


function saveDraft() {

    const form =
        document.getElementById("composeForm");

    const formData =
        new FormData(form);


    fetch("../api/mail/save-draft.php", {

        method: "POST",

        body: formData

    })

    .then(async response => {

        const text = await response.text();

        console.log(
            "SAVE DRAFT HTTP STATUS:",
            response.status
        );

        console.log(
            "SAVE DRAFT RAW RESPONSE:",
            text
        );


        try {

            return JSON.parse(text);

        } catch (error) {

            console.error(
                "INVALID SAVE DRAFT RESPONSE:",
                text
            );

            throw new Error(
                "Server returned an invalid response."
            );

        }

    })

    .then(result => {

        console.log(
            "SAVE DRAFT RESULT:",
            result
        );


        if (result.success) {

            /*
             * Keep the new draft ID in the form.
             * The next Save Draft will UPDATE
             * the same draft.
             */

            document.getElementById("draftId").value =
                result.message_id;


            Swal.fire({

                title: "Saved",

                text:
                    result.message ||
                    "Draft saved successfully.",

                icon: "success",

                confirmButtonText: "OK"

            });

        } else {

            console.error(
                "SAVE DRAFT ERROR:",
                result.error
            );


            Swal.fire({

                title: "Error",

                text:
                    result.error
                    ? result.message +
                      "\n\nDatabase: " +
                      result.error
                    : (
                        result.message ||
                        "Unable to save draft."
                    ),

                icon: "error"

            });

        }

    })

    .catch(error => {

        console.error(
            "SAVE DRAFT REQUEST ERROR:",
            error
        );


        Swal.fire({

            title: "Error",

            text:
                error.message ||
                "Unable to save draft.",

            icon: "error"

        });

    });

}

</script>

<style>

/* ==========================================================
   MAIL COMPOSE PAGE
   ========================================================== */

.mail-page {
    width: 100%;
    max-width: 1400px;
    margin: 0 auto;
    padding: 25px 30px 40px;
    box-sizing: border-box;
}


/* ==========================================================
   MAIL HEADER
   ========================================================== */

.mail-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 25px;
}

.mail-header h1 {
    margin: 0;
    color: #1f2937;
    font-size: 28px;
    font-weight: 700;
    line-height: 1.3;
}

.mail-header h1 i {
    color: #2563eb;
    margin-right: 8px;
}

.mail-header p {
    margin: 6px 0 0;
    color: #6b7280;
    font-size: 14px;
}


/* ==========================================================
   HEADER ACTIONS
   ========================================================== */

.mail-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}


/* ==========================================================
   BACK BUTTON
   ========================================================== */

.mail-back-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    padding: 10px 16px;

    background: #ffffff;
    color: #374151;

    border: 1px solid #d1d5db;
    border-radius: 7px;

    text-decoration: none;
    font-size: 14px;
    font-weight: 600;

    cursor: pointer;

    transition:
        background-color 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.mail-back-btn:hover {
    background: #f3f4f6;
    color: #111827;
    border-color: #9ca3af;
    text-decoration: none;
}

.mail-back-btn i {
    font-size: 13px;
}


/* ==========================================================
   COMPOSE BUTTON
   ========================================================== */

.mail-compose-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    padding: 10px 17px;

    background: #2563eb;
    color: #ffffff;

    border: 1px solid #2563eb;
    border-radius: 7px;

    text-decoration: none;

    font-size: 14px;
    font-weight: 600;

    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.1s ease;
}

.mail-compose-btn:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
    color: #ffffff;
    text-decoration: none;
}

.mail-compose-btn:active {
    transform: translateY(1px);
}


/* ==========================================================
   COMPOSE CONTAINER
   ========================================================== */

.compose-container {
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;

    background: #ffffff;

    border: 1px solid #e5e7eb;
    border-radius: 10px;

    box-shadow:
        0 2px 6px rgba(0, 0, 0, 0.04),
        0 8px 25px rgba(0, 0, 0, 0.04);

    padding: 30px;

    box-sizing: border-box;
}


/* ==========================================================
   FORM
   ========================================================== */

#composeForm {
    width: 100%;
}


/* ==========================================================
   LABELS
   ========================================================== */

#composeForm label {
    display: block;

    margin: 0 0 8px;

    color: #374151;

    font-size: 14px;
    font-weight: 600;
}

#composeForm label:not(:first-of-type) {
    margin-top: 20px;
}


/* ==========================================================
   INPUTS / SELECT / TEXTAREA
   ========================================================== */

#composeForm input[type="text"],
#composeForm select,
#composeForm textarea {
    width: 100%;

    box-sizing: border-box;

    padding: 12px 14px;

    background: #ffffff;

    color: #111827;

    border: 1px solid #d1d5db;
    border-radius: 7px;

    font-family: inherit;
    font-size: 14px;

    outline: none;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        background-color 0.2s ease;
}


/* SELECT */

#composeForm select {
    min-height: 44px;

    cursor: pointer;

    appearance: auto;
}


/* INPUT */

#composeForm input[type="text"] {
    min-height: 44px;
}


/* TEXTAREA */

#composeForm textarea {
    min-height: 360px;

    resize: vertical;

    line-height: 1.6;

    white-space: pre-wrap;
}


/* PLACEHOLDER */

#composeForm input::placeholder,
#composeForm textarea::placeholder {
    color: #9ca3af;
}


/* FOCUS */

#composeForm input[type="text"]:focus,
#composeForm select:focus,
#composeForm textarea:focus {
    border-color: #2563eb;

    box-shadow:
        0 0 0 3px rgba(37, 99, 235, 0.12);

    background: #ffffff;
}


/* ==========================================================
   COMPOSE ACTIONS
   ========================================================== */

.compose-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;

    gap: 10px;

    margin-top: 25px;

    padding-top: 20px;

    border-top: 1px solid #e5e7eb;
}


/* ==========================================================
   ACTION BUTTON BASE
   ========================================================== */

.compose-actions button {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 8px;

    min-height: 42px;

    padding: 10px 18px;

    border-radius: 7px;

    font-family: inherit;

    font-size: 14px;
    font-weight: 600;

    cursor: pointer;

    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.1s ease;
}


/* ==========================================================
   SAVE DRAFT
   ========================================================== */

.draft-btn {
    background: #ffffff;

    color: #374151;

    border: 1px solid #d1d5db;
}

.draft-btn:hover {
    background: #f9fafb;

    border-color: #9ca3af;

    color: #111827;
}

.draft-btn:active {
    transform: translateY(1px);
}


/* ==========================================================
   SEND BUTTON
   ========================================================== */

.send-btn {
    background: #2563eb;

    color: #ffffff;

    border: 1px solid #2563eb;

    box-shadow:
        0 2px 4px rgba(37, 99, 235, 0.15);
}

.send-btn:hover {
    background: #1d4ed8;

    border-color: #1d4ed8;

    box-shadow:
        0 4px 8px rgba(37, 99, 235, 0.2);
}

.send-btn:active {
    transform: translateY(1px);
}


/* ==========================================================
   HIDDEN INPUTS
   ========================================================== */

#composeForm input[type="hidden"] {
    display: none;
}


/* ==========================================================
   RESPONSIVE
   ========================================================== */

@media (max-width: 768px) {

    .mail-page {
        padding: 20px 15px 30px;
    }


    .mail-header {
        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }


    .mail-header-actions {
        width: 100%;

        justify-content: flex-start;

        flex-wrap: wrap;
    }


    .mail-header h1 {
        font-size: 24px;
    }


    .compose-container {
        padding: 20px;

        border-radius: 8px;
    }


    #composeForm textarea {
        min-height: 280px;
    }


    .compose-actions {
        flex-direction: column;

        align-items: stretch;
    }


    .compose-actions button {
        width: 100%;
    }

}


@media (max-width: 480px) {

    .mail-page {
        padding: 15px 10px 25px;
    }


    .mail-header-actions {
        flex-direction: column;

        align-items: stretch;
    }


    .mail-back-btn,
    .mail-compose-btn {
        width: 100%;

        box-sizing: border-box;
    }


    .compose-container {
        padding: 16px;
    }


    #composeForm input[type="text"],
    #composeForm select,
    #composeForm textarea {
        font-size: 14px;
    }

}

</style>
<?php include "../includes/footer.php"; ?>