<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::protect();

$user = Auth::getCurrentUser();

if (!$user) {
    die("Not authenticated.");
}

$userId = (int)($user['id'] ?? 0);


/*
 * ==========================================================
 * TRASH MESSAGES
 * ==========================================================
 */

$mail_messages = fetchAll(

    "SELECT

        m.id,
        m.subject,
        m.body,
        m.created_at,

        mr.deleted_at,

        u.first_name,
        u.last_name

     FROM mail_recipients mr

     INNER JOIN mail_messages m
        ON m.id = mr.message_id

     INNER JOIN users u
        ON u.id = m.sender_id

     WHERE mr.user_id = ?
     AND mr.is_deleted = 1

     ORDER BY mr.deleted_at DESC",

    [
        $userId
    ]

);


include "../includes/header.php";
include "../includes/sidebar.php";
include "../includes/navbar.php";

?>

<link
    rel="stylesheet"
    href="/Communication/assets/css/mail.css"
>


<div class="mail-page">


    <!-- ======================================================
         MAIL HEADER
    ======================================================= -->

    <div class="mail-header">


        <!-- TITLE -->

        <div class="mail-title">

            <h1>

                <i class="fas fa-trash"></i>

                Trash

            </h1>

            <p>
                Messages you have moved to trash
            </p>

        </div>


        <!-- HEADER ACTIONS -->

        <div class="mail-header-actions">

            <a
                href="index.php"
                class="mail-back-btn"
            >

                <i class="fas fa-arrow-left"></i>

                Back to Mail

            </a>

        </div>


    </div>


    <!-- ======================================================
         TRASH CONTENT
    ======================================================= -->

    <div class="mail-content standalone-mail-content">


        <?php if (empty($mail_messages)) { ?>


            <!-- ==================================================
                 EMPTY TRASH
            =================================================== -->

            <div class="mail-empty">

                <i class="fas fa-trash"></i>

                <h3>
                    Trash is empty
                </h3>

                <p>
                    No deleted messages.
                </p>

            </div>


        <?php } else { ?>


            <!-- ==================================================
                 TRASH MESSAGE LIST
            =================================================== -->

            <div class="trash-list">


                <?php foreach ($mail_messages as $message) { ?>


                    <div class="trash-row">


                        <!-- ======================================
                             MESSAGE ROW
                        ======================================= -->

                        <div
                            class="mail-row trash-mail-row"
                            onclick="openTrashMessage(
                                <?= (int)$message['id'] ?>
                            )"
                        >


                            <!-- SENDER -->

                            <div class="mail-sender">

                                <strong>

                                    <?= htmlspecialchars(
                                        trim(
                                            ($message['first_name'] ?? '') .
                                            ' ' .
                                            ($message['last_name'] ?? '')
                                        )
                                    ) ?>

                                </strong>

                            </div>


                            <!-- SUBJECT + PREVIEW -->

                            <div class="mail-subject">

                                <strong>

                                    <?= htmlspecialchars(
                                        $message['subject']
                                        ?: '(No subject)'
                                    ) ?>

                                </strong>

                                <span>

                                    -

                                    <?= htmlspecialchars(
                                        substr(
                                            strip_tags(
                                                $message['body'] ?? ''
                                            ),
                                            0,
                                            100
                                        )
                                    ) ?>

                                </span>

                            </div>


                            <!-- DATE -->

                            <div class="mail-date">

                                <?= htmlspecialchars(
                                    date(
                                        'M d, Y',
                                        strtotime(
                                            $message['deleted_at']
                                        )
                                    )
                                ) ?>

                            </div>


                        </div>


                        <!-- ======================================
                             RESTORE BUTTON
                        ======================================= -->

                        <button
                            type="button"
                            class="restore-btn"
                            onclick="
                                event.stopPropagation();
                                restoreMessage(
                                    <?= (int)$message['id'] ?>
                                );
                            "
                        >

                            <i class="fas fa-undo"></i>

                            <span>Restore</span>

                        </button>


                    </div>


                <?php } ?>


            </div>


        <?php } ?>


    </div>


</div>



<script>


/*
 * ==========================================================
 * OPEN TRASH MESSAGE
 * ==========================================================
 */

function openTrashMessage(id) {

    window.location.href =
        "view.php?id=" +
        encodeURIComponent(id);

}


/*
 * ==========================================================
 * RESTORE MESSAGE
 * ==========================================================
 */

function restoreMessage(id) {

    fetch("../api/mail/restore.php", {

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

    .then(result => {

        if (result.success) {

            Swal.fire(

                "Restored",

                "Message restored successfully.",

                "success"

            ).then(() => {

                location.reload();

            });

        } else {

            Swal.fire(

                "Error",

                result.message ||
                "Unable to restore the message.",

                "error"

            );

        }

    })

    .catch(error => {

        console.error(
            "RESTORE ERROR:",
            error
        );

        Swal.fire(

            "Error",

            "Unable to restore the message.",

            "error"

        );

    });

}


</script>



<!-- ==========================================================
     TRASH PAGE CSS
     Page-specific styling
========================================================== -->

<style>

/* ==========================================================
   MAIL PAGE
========================================================== */

.mail-page {

    width: 100%;

    max-width: 100%;

    padding: 0;

    box-sizing: border-box;

    color: var(--text);
}


/* ==========================================================
   MAIL HEADER
========================================================== */

.mail-header {

    width: 100%;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 25px;

    box-sizing: border-box;
}


.mail-title {

    min-width: 0;
}


.mail-title h1 {

    margin: 0;

    display: flex;

    align-items: center;

    gap: 10px;

    font-size: 26px;

    line-height: 1.2;

    color: var(--text);
}


.mail-title h1 i {

    color: var(--primary);
}


.mail-title p {

    margin: 6px 0 0;

    color: var(--text-light);

    font-size: 14px;
}


/* ==========================================================
   HEADER ACTIONS
========================================================== */

.mail-header-actions {

    display: flex;

    align-items: center;

    justify-content: flex-end;

    gap: 10px;

    flex-shrink: 0;
}


/* ==========================================================
   BACK BUTTON
========================================================== */

.mail-back-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 8px;

    padding: 11px 17px;

    border: 1px solid var(--border);

    border-radius: 8px;

    background: var(--surface);

    color: var(--text);

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;

    cursor: pointer;

    transition:
        background .2s ease,
        border-color .2s ease,
        color .2s ease,
        transform .2s ease;
}


.mail-back-btn:hover {

    background: var(--surface-2);

    border-color: var(--primary);

    color: var(--primary);

    transform: translateY(-1px);
}


/* ==========================================================
   MAIN CONTENT CARD
========================================================== */

.mail-content.standalone-mail-content {

    width: 100%;

    background: var(--surface);

    color: var(--text);

    border: 1px solid var(--border);

    border-radius: 14px;

    box-shadow: var(--shadow);

    overflow: hidden;

    box-sizing: border-box;
}


/* ==========================================================
   TRASH LIST
========================================================== */

.trash-list {

    width: 100%;

    display: flex;

    flex-direction: column;
}


/* ==========================================================
   TRASH ROW
========================================================== */

.trash-row {

    display: flex;

    align-items: stretch;

    width: 100%;

    min-height: 62px;

    box-sizing: border-box;

    background: var(--surface);

    border-bottom: 1px solid var(--border);

    transition:
        background .15s ease;
}


/*
 * Remove border from final row.
 */

.trash-list .trash-row:last-child {

    border-bottom: none;
}


/* ==========================================================
   MESSAGE ROW
========================================================== */

.trash-mail-row {

    flex: 1;

    min-width: 0;

    display: grid;

    grid-template-columns:
        220px
        minmax(0, 1fr)
        110px;

    align-items: center;

    min-height: 62px;

    padding: 0 10px 0 18px;

    box-sizing: border-box;

    background: transparent;

    color: var(--text);

    border-bottom: none;

    cursor: pointer;

    transition:
        background .15s ease,
        box-shadow .15s ease;
}


/* ==========================================================
   ROW HOVER
========================================================== */

.trash-row:hover {

    background: var(--surface-2);
}


.trash-row:hover .trash-mail-row {

    background: transparent;

    box-shadow:
        inset 3px 0 0 var(--primary);
}


/* ==========================================================
   SENDER
========================================================== */

.trash-mail-row .mail-sender {

    min-width: 0;

    overflow: hidden;

    white-space: nowrap;

    text-overflow: ellipsis;

    padding-right: 15px;

    font-size: 14px;

    color: var(--text);
}


.trash-mail-row .mail-sender strong {

    font-weight: 600;
}


/* ==========================================================
   SUBJECT
========================================================== */

.trash-mail-row .mail-subject {

    min-width: 0;

    overflow: hidden;

    white-space: nowrap;

    text-overflow: ellipsis;

    color: var(--text);

    font-size: 14px;
}


.trash-mail-row .mail-subject strong {

    font-weight: 600;

    color: var(--text);
}


.trash-mail-row .mail-subject span {

    color: var(--text-light);

    font-weight: 400;
}


/* ==========================================================
   DATE
========================================================== */

.trash-mail-row .mail-date {

    text-align: right;

    color: var(--text-light);

    font-size: 13px;

    white-space: nowrap;
}


/* ==========================================================
   RESTORE BUTTON
========================================================== */

.restore-btn {

    align-self: center;

    flex-shrink: 0;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    margin: 0 15px 0 8px;

    padding: 8px 13px;

    border: 1px solid var(--border);

    border-radius: 7px;

    background: var(--surface);

    color: var(--primary);

    cursor: pointer;

    font-family: inherit;

    font-size: 13px;

    font-weight: 600;

    white-space: nowrap;

    transition:
        background .2s ease,
        border-color .2s ease,
        color .2s ease,
        transform .2s ease;
}


.restore-btn:hover {

    background: rgba(37, 99, 235, .08);

    border-color: var(--primary);

    color: var(--primary);

    transform: translateY(-1px);
}


.restore-btn i {

    font-size: 12px;
}


/* ==========================================================
   EMPTY STATE
========================================================== */

.mail-empty {

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    min-height: 300px;

    padding: 70px 20px;

    text-align: center;

    color: var(--text-light);

    box-sizing: border-box;
}


.mail-empty i {

    margin-bottom: 15px;

    font-size: 45px;

    color: #b7c0cc;
}


.mail-empty h3 {

    margin: 0 0 7px;

    color: var(--text);

    font-size: 18px;
}


.mail-empty p {

    margin: 0;

    font-size: 14px;
}


/* ==========================================================
   TABLET
========================================================== */

@media (max-width: 900px) {

    .trash-mail-row {

        grid-template-columns:
            160px
            minmax(0, 1fr)
            95px;

        padding-left: 15px;
    }


    .restore-btn {

        margin-right: 12px;

        padding: 8px 11px;
    }

}


/* ==========================================================
   MOBILE
========================================================== */

@media (max-width: 600px) {

    .mail-header {

        flex-direction: column;

        align-items: stretch;

        gap: 15px;
    }


    .mail-title h1 {

        font-size: 23px;
    }


    .mail-header-actions {

        width: 100%;

        justify-content: stretch;
    }


    .mail-back-btn {

        width: 100%;

        box-sizing: border-box;
    }


    /* ------------------------------------------------------
       Trash row
    ------------------------------------------------------ */

    .trash-row {

        display: flex;

        flex-direction: column;

        align-items: stretch;

        padding: 0;
    }


    .trash-mail-row {

        display: flex;

        flex-direction: column;

        align-items: flex-start;

        gap: 6px;

        width: 100%;

        min-height: auto;

        padding: 14px 13px 8px;
    }


    .trash-mail-row .mail-sender,
    .trash-mail-row .mail-subject,
    .trash-mail-row .mail-date {

        width: 100%;

        padding-right: 0;

        text-align: left;
    }


    .trash-mail-row .mail-subject {

        white-space: normal;

        overflow: visible;

        text-overflow: unset;

        line-height: 1.5;
    }


    .trash-mail-row .mail-date {

        margin-top: 2px;

        font-size: 12px;
    }


    /* ------------------------------------------------------
       Restore
    ------------------------------------------------------ */

    .restore-btn {

        align-self: flex-start;

        margin: 3px 13px 13px;

        width: auto;
    }


    .mail-empty {

        min-height: 250px;

        padding: 50px 20px;
    }

}


/* ==========================================================
   VERY SMALL SCREENS
========================================================== */

@media (max-width: 400px) {

    .mail-title h1 {

        font-size: 21px;
    }


    .trash-mail-row {

        padding: 13px 12px 8px;
    }


    .restore-btn {

        margin-left: 12px;

        margin-bottom: 12px;
    }

}

</style>


<?php include "../includes/footer.php"; ?>