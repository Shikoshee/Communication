<?php

require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::protect();

$user = Auth::getCurrentUser();

if (!$user) {
    die("Not authenticated.");
}

$messageId = (int)($_GET['id'] ?? 0);

if ($messageId <= 0) {
    die("Invalid message.");
}


/*
 * Get message.
 */

$message = fetchRow(

    "SELECT

        m.id,
        m.sender_id,
        m.subject,
        m.body,
        m.parent_id,
        m.message_type,
        m.is_draft,
        m.created_at,
        m.updated_at,

        u.first_name AS sender_first_name,
        u.last_name AS sender_last_name,
        u.email AS sender_email

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
        $messageId,
        (int)$user['id'],
        (int)$user['id']
    ]

);


if (!$message) {
    die("Message not found.");
}


/*
 * Mark as read if current user is a recipient.
 */

executeQuery(

    "UPDATE mail_recipients

     SET
        is_read=1,
        read_at=NOW()

     WHERE message_id=?
     AND user_id=?",

    [
        $messageId,
        (int)$user['id']
    ]

);


/*
 * Get recipients.
 */

$recipients = fetchAll(

    "SELECT

        mr.user_id,
        mr.recipient_type,

        u.first_name,
        u.last_name,
        u.email

     FROM mail_recipients mr

     INNER JOIN users u
        ON u.id=mr.user_id

     WHERE mr.message_id=?

     ORDER BY
        FIELD(mr.recipient_type, 'to', 'cc', 'bcc'),
        u.first_name,
        u.last_name",

    [
        $messageId
    ]

);


/*
 * Separate recipient display.
 */

$toRecipients = [];
$ccRecipients = [];

foreach ($recipients as $recipient) {

    $name = trim(
        ($recipient['first_name'] ?? '') .
        ' ' .
        ($recipient['last_name'] ?? '')
    );

    $display = $name !== ''
        ? $name . ' <' . $recipient['email'] . '>'
        : $recipient['email'];

    if ($recipient['recipient_type'] === 'to') {
        $toRecipients[] = $display;
    }

    if ($recipient['recipient_type'] === 'cc') {
        $ccRecipients[] = $display;
    }
}


$senderName = trim(
    ($message['sender_first_name'] ?? '') .
    ' ' .
    ($message['sender_last_name'] ?? '')
);


include "../includes/header.php";
include "../includes/sidebar.php";
include "../includes/navbar.php";

?>

<link rel="stylesheet" href="/Communication/assets/css/mail.css">


<div class="mail-page">

    <div class="mail-header">

        <div>

            <h1>Message</h1>

            <p>View message</p>

        </div>

        <a
            href="compose.php"
            class="mail-compose-btn">

            <i class="fa fa-edit"></i>
            Compose

        </a>

    </div>


    <div class="mail-view-container">


        <!-- MESSAGE TOOLBAR -->

        <div class="mail-view-toolbar">

            <a href="index.php">
                <i class="fa fa-arrow-left"></i>
                Back
            </a>


            <button
                onclick="deleteMessage(<?= $messageId ?>)">

                <i class="fa fa-trash"></i>
                Delete

            </button>

        </div>


        <!-- SUBJECT -->

        <div class="mail-view-subject">

            <?= htmlspecialchars(
                $message['subject'] ?: '(No subject)'
            ) ?>

        </div>


        <!-- SENDER -->

        <div class="mail-view-sender">

            <div class="mail-avatar">

                <?= strtoupper(
                    substr(
                        $senderName ?: 'U',
                        0,
                        1
                    )
                ) ?>

            </div>


            <div>

                <strong>

                    <?= htmlspecialchars(
                        $senderName ?: 'Unknown User'
                    ) ?>

                </strong>

                <div class="mail-email">

                    <?= htmlspecialchars(
                        $message['sender_email'] ?? ''
                    ) ?>

                </div>

            </div>


            <div class="mail-message-date">

                <?= htmlspecialchars(
                    date(
                        'M d, Y H:i',
                        strtotime($message['created_at'])
                    )
                ) ?>

            </div>

        </div>


        <!-- RECIPIENTS -->

        <div class="mail-recipients">

            <div>

                <strong>To:</strong>

                <?= htmlspecialchars(
                    implode(', ', $toRecipients)
                ) ?>

            </div>


            <?php if (!empty($ccRecipients)) { ?>

                <div>

                    <strong>CC:</strong>

                    <?= htmlspecialchars(
                        implode(', ', $ccRecipients)
                    ) ?>

                </div>

            <?php } ?>

        </div>


        <!-- BODY -->

        <div class="mail-body">

            <?= nl2br(
                htmlspecialchars(
                    $message['body'] ?? ''
                )
            ) ?>

        </div>


        <!-- ACTIONS -->

        <div class="mail-view-actions">

            <button
                class="mail-reply-btn"
                onclick="replyMessage(<?= $messageId ?>)">

                <i class="fa fa-reply"></i>
                Reply

            </button>


            <button
                class="mail-forward-btn"
                onclick="forwardMessage(<?= $messageId ?>)">

                <i class="fa fa-share"></i>
                Forward

            </button>

        </div>


    </div>

</div>


<script>

function deleteMessage(id) {

    Swal.fire({

        title: "Move to Trash?",

        text: "This message will be moved to your trash.",

        icon: "warning",

        showCancelButton: true,

        confirmButtonColor: "#dc3545",

        confirmButtonText: "Move to Trash"

    }).then(result => {

        if (!result.isConfirmed) {
            return;
        }


        fetch("../api/mail/delete.php", {

            method: "POST",

            headers: {
                "Content-Type":
                    "application/x-www-form-urlencoded"
            },

            body: new URLSearchParams({
                message_id: id
            })

        })

        .then(response => response.json())

        .then(data => {

            if (data.success) {

                window.location.href = "index.php";

            } else {

                Swal.fire(
                    "Error",
                    data.message,
                    "error"
                );

            }

        });

    });

}


function replyMessage(id) {

    window.location.href =
        "compose.php?reply=" +
        encodeURIComponent(id);

}


function forwardMessage(id) {

    window.location.href =
        "compose.php?forward=" +
        encodeURIComponent(id);

}

</script>

<style>

/* ==========================================================
   MESSAGE VIEW PAGE
   ========================================================== */

.mail-view-container {
    width: 100%;
    max-width: 1100px;
    margin: 25px auto;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}


/* ==========================================================
   MESSAGE TOOLBAR
   ========================================================== */

.mail-view-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 15px 20px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}


/* BACK BUTTON */

.mail-view-toolbar a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 15px;
    background: #ffffff;
    color: #334155;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.mail-view-toolbar a:hover {
    background: #f1f5f9;
    color: #1e293b;
    border-color: #94a3b8;
}


/* DELETE BUTTON */

.mail-view-toolbar button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 15px;
    background: #ffffff;
    color: #dc3545;
    border: 1px solid #f1aeb5;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.mail-view-toolbar button:hover {
    background: #dc3545;
    color: #ffffff;
    border-color: #dc3545;
}


/* ==========================================================
   SUBJECT
   ========================================================== */

.mail-view-subject {
    padding: 25px 30px 18px;
    font-size: 24px;
    font-weight: 600;
    color: #1e293b;
    border-bottom: 1px solid #f1f5f9;
    word-break: break-word;
}


/* ==========================================================
   SENDER
   ========================================================== */

.mail-view-sender {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px 30px;
    border-bottom: 1px solid #f1f5f9;
}


/* AVATAR */

.mail-avatar {
    width: 46px;
    height: 46px;
    min-width: 46px;
    display: flex;
    align-items: center;
    justify-content: center;

    background: #2563eb;
    color: #ffffff;

    border-radius: 50%;

    font-size: 18px;
    font-weight: 600;
    text-transform: uppercase;
}


/* SENDER NAME */

.mail-view-sender strong {
    display: block;
    color: #1e293b;
    font-size: 15px;
    margin-bottom: 3px;
}


/* EMAIL */

.mail-email {
    color: #64748b;
    font-size: 13px;
}


/* DATE */

.mail-message-date {
    margin-left: auto;
    color: #64748b;
    font-size: 13px;
    white-space: nowrap;
}


/* ==========================================================
   RECIPIENTS
   ========================================================== */

.mail-recipients {
    padding: 15px 30px;
    background: #fafafa;
    border-bottom: 1px solid #f1f5f9;
    color: #475569;
    font-size: 13px;
    line-height: 1.8;
}

.mail-recipients strong {
    color: #334155;
    margin-right: 5px;
}


/* ==========================================================
   MESSAGE BODY
   ========================================================== */

.mail-body {
    padding: 30px;
    min-height: 220px;

    color: #334155;
    font-size: 15px;
    line-height: 1.75;

    white-space: normal;
    word-wrap: break-word;
    overflow-wrap: break-word;
}


/* ==========================================================
   ACTION BUTTONS
   ========================================================== */

.mail-view-actions {
    display: flex;
    align-items: center;
    gap: 10px;

    padding: 18px 30px;

    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
}


/* COMMON ACTION BUTTON */

.mail-view-actions button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;

    padding: 10px 18px;

    border-radius: 6px;

    font-size: 14px;
    font-weight: 500;

    cursor: pointer;

    transition: all 0.2s ease;
}


/* REPLY */

.mail-reply-btn {
    background: #2563eb;
    color: #ffffff;
    border: 1px solid #2563eb;
}

.mail-reply-btn:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
}


/* FORWARD */

.mail-forward-btn {
    background: #ffffff;
    color: #475569;
    border: 1px solid #cbd5e1;
}

.mail-forward-btn:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
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
   BACK TO MAIL BUTTON
   ========================================================== */

.mail-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;

    padding: 10px 16px;

    background: #ffffff;
    color: #334155;

    border: 1px solid #cbd5e1;
    border-radius: 6px;

    text-decoration: none;

    font-size: 14px;
    font-weight: 500;

    transition: all 0.2s ease;
}

.mail-back-btn:hover {
    background: #f1f5f9;
    color: #1e293b;
    border-color: #94a3b8;
}


/* ==========================================================
   MOBILE
   ========================================================== */

@media (max-width: 768px) {

    .mail-view-container {
        margin: 15px 0;
        border-radius: 8px;
    }


    .mail-view-toolbar {
        padding: 12px 15px;
    }


    .mail-view-subject {
        padding: 20px 18px 15px;
        font-size: 20px;
    }


    .mail-view-sender {
        padding: 16px 18px;
    }


    .mail-message-date {
        font-size: 12px;
    }


    .mail-recipients {
        padding: 13px 18px;
    }


    .mail-body {
        padding: 22px 18px;
        font-size: 14px;
    }


    .mail-view-actions {
        padding: 15px 18px;
        flex-wrap: wrap;
    }


    .mail-view-actions button {
        flex: 1;
    }


    .mail-header-actions {
        flex-wrap: wrap;
    }


    .mail-back-btn,
    .mail-compose-btn {
        padding: 9px 12px;
        font-size: 13px;
    }

}


/* ==========================================================
   SMALL MOBILE
   ========================================================== */

@media (max-width: 480px) {

    .mail-view-sender {
        align-items: flex-start;
    }


    .mail-message-date {
        margin-left: auto;
        text-align: right;
    }


    .mail-view-toolbar a,
    .mail-view-toolbar button {
        padding: 8px 11px;
        font-size: 13px;
    }


    .mail-view-toolbar a i,
    .mail-view-toolbar button i {
        margin-right: 0;
    }

}

</style>
<?php include "../includes/footer.php"; ?>