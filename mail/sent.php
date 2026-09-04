<?php

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
 * SENT MESSAGES
 * ==========================================================
 */

$mail_messages = fetchAll(

    "SELECT
        m.id,
        m.subject,
        m.body,
        m.message_type,
        m.created_at,

        GROUP_CONCAT(
            DISTINCT CONCAT(
                COALESCE(u.first_name, ''),
                ' ',
                COALESCE(u.last_name, '')
            )
            SEPARATOR ', '
        ) AS recipients

    FROM mail_messages AS m

    LEFT JOIN mail_recipients AS mr
        ON mr.message_id = m.id

    LEFT JOIN users AS u
        ON u.id = mr.user_id

    WHERE m.sender_id = ?
    AND m.is_draft = 0

    GROUP BY
        m.id,
        m.subject,
        m.body,
        m.message_type,
        m.created_at

    ORDER BY m.created_at DESC",

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

        <div>

            <h1>
                <i class="fas fa-paper-plane"></i>
                Sent
            </h1>

            <p>
                Messages you have sent
            </p>

        </div>


        <!-- HEADER ACTIONS -->

        <div class="mail-header-actions">


            <!-- BACK TO MAIN MAIL / INBOX -->

            <a
                href="index.php"
                class="mail-back-btn"
            >

                <i class="fas fa-arrow-left"></i>

                Back to Mail

            </a>


            <!-- COMPOSE -->

            <a
                href="compose.php"
                class="mail-compose-btn"
            >

                <i class="fa fa-edit"></i>

                Compose

            </a>

        </div>


    </div>


    <!-- ======================================================
         SENT MESSAGES
    ======================================================= -->

    <div class="mail-content standalone-mail-content">


        <?php if (empty($mail_messages)) { ?>


            <!-- EMPTY STATE -->

            <div class="mail-empty">

                <i class="fa fa-paper-plane"></i>

                <h3>
                    No sent messages
                </h3>

                <p>
                    You have not sent any messages yet.
                </p>

            </div>


        <?php } else { ?>


            <!-- SENT MESSAGE LIST -->

            <div class="mail-table">


                <?php foreach ($mail_messages as $message) { ?>


                    <div
                        class="mail-row"
                        onclick="openSentMessage(
                            <?= (int)$message['id'] ?>
                        )"
                    >


                        <!-- RECIPIENT -->

                        <div class="mail-sender">

                            <strong>To:</strong>

                            <?= htmlspecialchars(
                                $message['recipients']
                                ?? 'Unknown recipient'
                            ) ?>

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
                                        $message['created_at']
                                    )
                                )
                            ) ?>

                        </div>


                    </div>


                <?php } ?>


            </div>


        <?php } ?>


    </div>


</div>


<script>

function openSentMessage(id) {

    window.location.href =
        "view.php?id=" +
        encodeURIComponent(id);

}

</script>


<!-- ==========================================================
     PAGE-SPECIFIC MAIL STYLING
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

.mail-header > div:first-child {
    min-width: 0;
}

.mail-header h1 {
    margin: 0;

    display: flex;
    align-items: center;

    gap: 10px;

    color: var(--text);

    font-size: 26px;
    line-height: 1.2;
}

.mail-header h1 i {
    color: var(--primary);
}

.mail-header p {
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

    padding: 11px 18px;

    border: 1px solid var(--border);

    border-radius: 8px;

    background: var(--surface);

    color: var(--text);

    text-decoration: none;

    font-size: 14px;
    font-weight: 600;

    cursor: pointer;

    box-sizing: border-box;

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
   COMPOSE BUTTON
========================================================== */

.mail-compose-btn {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 8px;

    padding: 11px 18px;

    border: none;

    border-radius: 8px;

    background: var(--primary);

    color: #fff;

    text-decoration: none;

    font-size: 14px;
    font-weight: 600;

    cursor: pointer;

    box-sizing: border-box;

    transition:
        background .2s ease,
        transform .2s ease,
        box-shadow .2s ease;
}

.mail-compose-btn:hover {
    background: var(--primary-dark, #1d4ed8);

    color: #fff;

    transform: translateY(-1px);

    box-shadow:
        0 4px 10px rgba(37, 99, 235, .18);
}


/* ==========================================================
   MAIL CONTENT PANEL
========================================================== */

.mail-content,
.standalone-mail-content {
    width: 100%;

    min-width: 0;

    background: var(--surface);

    color: var(--text);

    border: 1px solid var(--border);

    border-radius: 14px;

    box-shadow: var(--shadow);

    overflow: hidden;

    box-sizing: border-box;
}


/* ==========================================================
   MAIL TABLE
========================================================== */

.mail-table {
    width: 100%;

    display: block;

    box-sizing: border-box;
}


/* ==========================================================
   SENT MAIL ROW
========================================================== */

.standalone-mail-content .mail-row {

    width: 100%;

    min-height: 60px;

    display: grid;

    grid-template-columns:
        220px
        minmax(0, 1fr)
        110px;

    align-items: center;

    padding: 0 18px;

    box-sizing: border-box;

    background: var(--surface);

    color: var(--text);

    border-bottom: 1px solid var(--border);

    cursor: pointer;

    transition:
        background .15s ease,
        box-shadow .15s ease;
}


/* Remove border from last row */

.standalone-mail-content .mail-row:last-child {
    border-bottom: none;
}


/* Hover */

.standalone-mail-content .mail-row:hover {
    background: var(--surface-2);

    box-shadow:
        inset 3px 0 0 var(--primary);
}


/* ==========================================================
   SENDER / RECIPIENT
========================================================== */

.mail-sender {

    min-width: 0;

    padding-right: 15px;

    overflow: hidden;

    white-space: nowrap;

    text-overflow: ellipsis;

    color: var(--text);

    font-size: 14px;
}

.mail-sender strong {
    font-weight: 700;

    color: var(--text);
}


/* ==========================================================
   SUBJECT
========================================================== */

.mail-subject {

    min-width: 0;

    overflow: hidden;

    white-space: nowrap;

    text-overflow: ellipsis;

    color: var(--text);

    font-size: 14px;
}

.mail-subject strong {
    font-weight: 600;

    color: var(--text);
}

.mail-subject span {
    color: var(--text-light);
}


/* ==========================================================
   DATE
========================================================== */

.mail-date {

    text-align: right;

    color: var(--text-light);

    font-size: 13px;

    white-space: nowrap;
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

    box-sizing: border-box;

    text-align: center;

    color: var(--text-light);
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

    .standalone-mail-content .mail-row {

        grid-template-columns:
            160px
            minmax(0, 1fr)
            90px;

        padding: 0 15px;
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


    .mail-header h1 {

        font-size: 23px;
    }


    .mail-header-actions {

        width: 100%;

        display: flex;

        flex-direction: column;

        align-items: stretch;

        gap: 8px;
    }


    .mail-back-btn,
    .mail-compose-btn {

        width: 100%;

        box-sizing: border-box;
    }


    /* ----------------------------------------------
       MOBILE MAIL ROW
    ---------------------------------------------- */

    .standalone-mail-content .mail-row {

        display: flex;

        flex-direction: column;

        align-items: flex-start;

        gap: 5px;

        min-height: auto;

        padding: 14px;

        box-sizing: border-box;
    }


    .standalone-mail-content .mail-sender,
    .standalone-mail-content .mail-subject,
    .standalone-mail-content .mail-date {

        width: 100%;

        padding-right: 0;

        text-align: left;

        box-sizing: border-box;
    }


    .standalone-mail-content .mail-sender {

        font-size: 14px;
    }


    .standalone-mail-content .mail-subject {

        white-space: normal;

        overflow: visible;

        text-overflow: clip;

        line-height: 1.5;
    }


    .standalone-mail-content .mail-date {

        margin-top: 2px;

        font-size: 12px;
    }

}


/* ==========================================================
   VERY SMALL SCREENS
========================================================== */

@media (max-width: 400px) {

    .mail-header h1 {

        font-size: 21px;
    }


    .standalone-mail-content .mail-row {

        padding: 13px;
    }

}

</style>


<?php include "../includes/footer.php"; ?>