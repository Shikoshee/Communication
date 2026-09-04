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
 * DRAFT MESSAGES
 * ==========================================================
 */

$drafts = fetchAll(

    "SELECT
        m.id,
        m.subject,
        m.body,
        m.message_type,
        m.is_draft,
        m.created_at,
        m.updated_at

     FROM mail_messages m

     WHERE m.sender_id=?
     AND m.is_draft=1

     ORDER BY m.updated_at DESC",

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


        <div class="mail-title">

            <h1>

                <i class="fas fa-file"></i>

                Drafts

            </h1>

            <p>
                Messages you have not sent yet
            </p>

        </div>


        <!-- HEADER ACTIONS -->

        <div class="mail-header-actions">


            <!-- BACK TO INBOX -->

            <a
                href="index.php"
                class="mail-back-btn"
            >

                <i class="fas fa-arrow-left"></i>

                Back to Inbox

            </a>


            <!-- COMPOSE -->

            <a
                href="compose.php"
                class="mail-compose-btn"
            >

                <i class="fas fa-edit"></i>

                Compose

            </a>


        </div>


    </div>



    <!-- ======================================================
         DRAFT CONTENT
    ======================================================= -->

    <div class="mail-content standalone-mail-content">


        <?php if (empty($drafts)) { ?>


            <!-- EMPTY STATE -->

            <div class="mail-empty">

                <i class="fas fa-file"></i>

                <h3>
                    No drafts
                </h3>

                <p>
                    Your saved drafts will appear here.
                </p>

            </div>


        <?php } else { ?>


            <!-- ==================================================
                 DRAFT LIST
            =================================================== -->

            <div class="draft-list">


                <?php foreach ($drafts as $draft) { ?>


                    <div
                        class="mail-row draft-row"
                        onclick="openDraft(
                            <?= (int)$draft['id'] ?>
                        )"
                    >


                        <!-- DRAFT LABEL -->

                        <div class="mail-sender">

                            <span class="draft-label">

                                <i class="fas fa-file"></i>

                                Draft

                            </span>

                        </div>


                        <!-- SUBJECT + PREVIEW -->

                        <div class="mail-subject">


                            <strong>

                                <?= htmlspecialchars(
                                    $draft['subject']
                                    ?: '(No subject)'
                                ) ?>

                            </strong>


                            <span>

                                -

                                <?= htmlspecialchars(
                                    substr(
                                        strip_tags(
                                            $draft['body'] ?? ''
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
                                        $draft['updated_at']
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

function openDraft(id) {

    window.location.href =
        "compose.php?draft=" +
        encodeURIComponent(id);

}

</script>



<!-- ==========================================================
     DRAFT PAGE CSS
     Page-specific styling
========================================================== -->

<style>

/* ==========================================================
   DRAFT PAGE
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

.mail-title h1,
.mail-header h1 {
    margin: 0;

    display: flex;
    align-items: center;

    gap: 10px;

    font-size: 26px;
    line-height: 1.2;

    color: var(--text);
}

.mail-title h1 i,
.mail-header h1 i {
    color: var(--primary);
}

.mail-title p,
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
   COMPOSE BUTTON
========================================================== */

.mail-compose-btn {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    gap: 8px;

    padding: 11px 18px;

    border: 1px solid var(--primary);

    border-radius: 8px;

    background: var(--primary);

    color: #fff;

    text-decoration: none;

    font-size: 14px;
    font-weight: 600;

    cursor: pointer;

    transition:
        background .2s ease,
        border-color .2s ease,
        transform .2s ease,
        box-shadow .2s ease;
}

.mail-compose-btn:hover {
    background: var(--primary-dark, #1d4ed8);

    border-color: var(--primary-dark, #1d4ed8);

    color: #fff;

    transform: translateY(-1px);

    box-shadow:
        0 4px 10px rgba(37, 99, 235, .18);
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
   DRAFT LIST
========================================================== */

.draft-list {
    width: 100%;

    display: flex;

    flex-direction: column;
}


/* ==========================================================
   DRAFT ROW
========================================================== */

.draft-list .mail-row {

    display: grid;

    grid-template-columns:
        220px
        minmax(0, 1fr)
        110px;

    align-items: center;

    width: 100%;

    min-height: 62px;

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


/*
 * Remove bottom border from final row.
 */

.draft-list .mail-row:last-child {
    border-bottom: none;
}


/* ==========================================================
   DRAFT ROW HOVER
========================================================== */

.draft-list .mail-row:hover {

    background: var(--surface-2);

    box-shadow:
        inset 3px 0 0 var(--primary);
}


/* ==========================================================
   DRAFT SENDER
========================================================== */

.draft-list .mail-sender {

    min-width: 0;

    overflow: hidden;

    white-space: nowrap;

    text-overflow: ellipsis;

    padding-right: 15px;

    font-size: 14px;

    color: var(--primary);

    font-weight: 600;
}


/* ==========================================================
   DRAFT LABEL
========================================================== */

.draft-label {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    color: var(--primary);

    font-weight: 600;
}

.draft-label i {
    font-size: 13px;
}


/* ==========================================================
   SUBJECT
========================================================== */

.draft-list .mail-subject {

    min-width: 0;

    overflow: hidden;

    white-space: nowrap;

    text-overflow: ellipsis;

    color: var(--text);

    font-size: 14px;
}

.draft-list .mail-subject strong {

    font-weight: 600;

    color: var(--text);
}

.draft-list .mail-subject span {

    color: var(--text-light);

    font-weight: 400;
}


/* ==========================================================
   DATE
========================================================== */

.draft-list .mail-date {

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

    .draft-list .mail-row {

        grid-template-columns:
            160px
            minmax(0, 1fr)
            100px;

        min-height: 60px;

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

        gap: 8px;
    }


    .mail-back-btn,
    .mail-compose-btn {

        flex: 1;

        width: 100%;

        box-sizing: border-box;
    }


    /* ------------------------------------------------------
       Draft Rows
    ------------------------------------------------------ */

    .draft-list .mail-row {

        display: flex;

        flex-direction: column;

        align-items: flex-start;

        gap: 6px;

        min-height: auto;

        padding: 14px 13px;
    }


    .draft-list .mail-sender,
    .draft-list .mail-subject,
    .draft-list .mail-date {

        width: 100%;

        padding-right: 0;

        text-align: left;
    }


    .draft-list .mail-sender {

        font-size: 14px;
    }


    .draft-list .mail-subject {

        white-space: normal;

        overflow: visible;

        text-overflow: unset;

        line-height: 1.5;
    }


    .draft-list .mail-date {

        margin-top: 2px;

        font-size: 12px;
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

    .mail-header h1 {

        font-size: 21px;
    }


    .mail-header-actions {

        flex-direction: column;
    }


    .mail-back-btn,
    .mail-compose-btn {

        width: 100%;

        flex: none;
    }


    .draft-list .mail-row {

        padding: 13px 12px;
    }

}

</style>


<?php include "../includes/footer.php"; ?>