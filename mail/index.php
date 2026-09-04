<?php

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/Permission.php';

Auth::protect();

$user = Auth::getCurrentUser();

if (!$user) {
    die("Not authenticated.");
}

include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/navbar.php';

?>

<link
    rel="stylesheet"
    href="/Communication/assets/css/mail.css"
>


<div class="mail-page">

    <div class="mail-layout">


        <!-- ======================================================
             MAIL SIDEBAR
        ======================================================= -->

        <aside class="mail-sidebar">


            <a
                href="compose.php"
                class="compose-btn"
            >

                <i class="fas fa-pen"></i>

                Compose

            </a>


            <nav class="mail-navigation">


                <a
                    href="index.php"
                    class="mail-folder active"
                >

                    <i class="fas fa-inbox"></i>

                    <span>Inbox</span>

                </a>


                <a
                    href="sent.php"
                    class="mail-folder"
                >

                    <i class="fas fa-paper-plane"></i>

                    <span>Sent</span>

                </a>


                <a
                    href="drafts.php"
                    class="mail-folder"
                >

                    <i class="fas fa-file"></i>

                    <span>Drafts</span>

                </a>


                <a
                    href="trash.php"
                    class="mail-folder"
                >

                    <i class="fas fa-trash"></i>

                    <span>Trash</span>

                </a>


            </nav>

        </aside>


        <!-- ======================================================
             MAIL CONTENT
        ======================================================= -->

        <main class="mail-content">


            <!-- MAIL TOOLBAR -->

            <div class="mail-toolbar">


                <button
                    type="button"
                    onclick="loadInbox()"
                    title="Refresh"
                >

                    <i class="fas fa-sync"></i>

                </button>


                <input
                    type="search"
                    id="mailSearch"
                    placeholder="Search mail..."
                    autocomplete="off"
                >


            </div>


            <!-- MAIL HEADER -->

            <div class="mail-header">

                <div class="mail-title">

                    <h1>

                        <i class="fas fa-inbox"></i>

                        Inbox

                    </h1>

                    <p>
                        Your messages
                    </p>

                </div>


            </div>


            <!-- MAIL LIST -->

            <div
                id="mailList"
                class="mail-list"
            >

                <div class="mail-loading">

                    <i class="fas fa-spinner fa-spin"></i>

                    Loading messages...

                </div>

            </div>


        </main>


    </div>

</div>


<script
    src="/Communication/assets/js/mail.js"
></script>

<style>

/* ==========================================================
   MAIN MAIL / INBOX PAGE
   ========================================================== */

.mail-page {
    width: 100%;
    max-width: 1500px;

    margin: 0 auto;

    padding: 25px 30px 40px;

    box-sizing: border-box;
}


/* ==========================================================
   MAIL LAYOUT
   ========================================================== */

.mail-layout {
    display: flex;

    width: 100%;
    min-height: 650px;

    background: #ffffff;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    overflow: hidden;

    box-shadow:
        0 2px 6px rgba(0, 0, 0, 0.04),
        0 10px 30px rgba(0, 0, 0, 0.04);
}


/* ==========================================================
   MAIL SIDEBAR
   ========================================================== */

.mail-sidebar {
    width: 220px;

    flex-shrink: 0;

    background: #f8fafc;

    border-right: 1px solid #e5e7eb;

    padding: 20px 15px;

    box-sizing: border-box;
}


/* ==========================================================
   COMPOSE BUTTON
   ========================================================== */

.compose-btn {
    display: flex;

    align-items: center;
    justify-content: center;

    gap: 9px;

    width: 100%;

    min-height: 44px;

    padding: 11px 15px;

    box-sizing: border-box;

    background: #2563eb;

    color: #ffffff;

    border: 1px solid #2563eb;

    border-radius: 8px;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;

    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.1s ease;
}

.compose-btn:hover {
    background: #1d4ed8;

    border-color: #1d4ed8;

    color: #ffffff;

    text-decoration: none;

    box-shadow:
        0 4px 10px rgba(37, 99, 235, 0.20);
}

.compose-btn:active {
    transform: translateY(1px);
}


/* ==========================================================
   MAIL NAVIGATION
   ========================================================== */

.mail-navigation {
    display: flex;

    flex-direction: column;

    gap: 4px;

    margin-top: 25px;
}


/* ==========================================================
   MAIL FOLDER
   ========================================================== */

.mail-folder {
    display: flex;

    align-items: center;

    gap: 12px;

    width: 100%;

    padding: 11px 13px;

    box-sizing: border-box;

    border-radius: 7px;

    color: #4b5563;

    text-decoration: none;

    font-size: 14px;

    font-weight: 500;

    transition:
        background-color 0.2s ease,
        color 0.2s ease;
}

.mail-folder i {
    width: 20px;

    text-align: center;

    font-size: 15px;
}

.mail-folder:hover {
    background: #e5edff;

    color: #2563eb;

    text-decoration: none;
}


/* ACTIVE FOLDER */

.mail-folder.active {
    background: #dbeafe;

    color: #1d4ed8;

    font-weight: 600;
}

.mail-folder.active i {
    color: #2563eb;
}


/* ==========================================================
   MAIN MAIL CONTENT
   ========================================================== */

.mail-content {
    flex: 1;

    min-width: 0;

    background: #ffffff;

    padding: 0;

    box-sizing: border-box;
}


/* ==========================================================
   MAIL TOOLBAR
   ========================================================== */

.mail-toolbar {
    display: flex;

    align-items: center;

    gap: 12px;

    padding: 15px 20px;

    background: #ffffff;

    border-bottom: 1px solid #e5e7eb;
}


/* REFRESH BUTTON */

.mail-toolbar button {
    display: inline-flex;

    align-items: center;
    justify-content: center;

    width: 38px;
    height: 38px;

    padding: 0;

    background: #ffffff;

    color: #4b5563;

    border: 1px solid #d1d5db;

    border-radius: 7px;

    cursor: pointer;

    font-size: 14px;

    transition:
        background-color 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease,
        transform 0.1s ease;
}

.mail-toolbar button:hover {
    background: #f3f4f6;

    color: #2563eb;

    border-color: #9ca3af;
}

.mail-toolbar button:active {
    transform: rotate(20deg);
}


/* ==========================================================
   SEARCH
   ========================================================== */

.mail-toolbar input[type="search"] {
    flex: 1;

    max-width: 450px;

    height: 38px;

    padding: 0 14px;

    box-sizing: border-box;

    background: #f9fafb;

    color: #111827;

    border: 1px solid #d1d5db;

    border-radius: 7px;

    outline: none;

    font-family: inherit;

    font-size: 14px;

    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.mail-toolbar input[type="search"]::placeholder {
    color: #9ca3af;
}

.mail-toolbar input[type="search"]:focus {
    background: #ffffff;

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px rgba(37, 99, 235, 0.10);
}


/* ==========================================================
   MAIL HEADER
   ========================================================== */

.mail-header {
    display: flex;

    align-items: center;
    justify-content: space-between;

    gap: 20px;

    padding: 22px 25px;

    margin: 0;

    background: #ffffff;

    border-bottom: 1px solid #e5e7eb;
}


.mail-title h1 {
    display: flex;

    align-items: center;

    gap: 9px;

    margin: 0;

    color: #1f2937;

    font-size: 25px;

    font-weight: 700;
}

.mail-title h1 i {
    color: #2563eb;

    font-size: 22px;
}

.mail-title p {
    margin: 5px 0 0;

    color: #6b7280;

    font-size: 13px;
}


/* ==========================================================
   MAIL LIST
   ========================================================== */

.mail-list {
    width: 100%;

    background: #ffffff;
}


/* ==========================================================
   MAIL ROW
   ========================================================== */

.mail-row {
    display: grid;

    grid-template-columns:
        minmax(180px, 25%)
        minmax(0, 1fr)
        110px;

    align-items: center;

    gap: 15px;

    width: 100%;

    min-height: 62px;

    padding: 10px 20px;

    box-sizing: border-box;

    background: #ffffff;

    border-bottom: 1px solid #edf0f3;

    cursor: pointer;

    transition:
        background-color 0.15s ease,
        box-shadow 0.15s ease;
}


/* HOVER */

.mail-row:hover {
    background: #f8fafc;

    box-shadow:
        inset 3px 0 0 #2563eb;
}


/* ==========================================================
   SENDER
   ========================================================== */

.mail-sender {
    min-width: 0;

    overflow: hidden;

    color: #374151;

    font-size: 14px;

    font-weight: 500;

    white-space: nowrap;

    text-overflow: ellipsis;
}

.mail-sender strong {
    color: #111827;

    font-weight: 600;
}


/* ==========================================================
   SUBJECT
   ========================================================== */

.mail-subject {
    min-width: 0;

    overflow: hidden;

    color: #6b7280;

    font-size: 14px;

    white-space: nowrap;

    text-overflow: ellipsis;
}

.mail-subject strong {
    color: #1f2937;

    font-weight: 600;
}

.mail-subject span {
    color: #9ca3af;

    margin-left: 3px;
}


/* ==========================================================
   DATE
   ========================================================== */

.mail-date {
    color: #6b7280;

    font-size: 12px;

    text-align: right;

    white-space: nowrap;
}


/* ==========================================================
   LOADING STATE
   ========================================================== */

.mail-loading {
    display: flex;

    align-items: center;
    justify-content: center;

    gap: 10px;

    min-height: 180px;

    padding: 30px;

    box-sizing: border-box;

    color: #6b7280;

    font-size: 14px;
}

.mail-loading i {
    color: #2563eb;

    font-size: 17px;
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

    padding: 40px 20px;

    text-align: center;

    color: #6b7280;
}

.mail-empty > i {
    display: flex;

    align-items: center;
    justify-content: center;

    width: 60px;
    height: 60px;

    margin-bottom: 15px;

    background: #eff6ff;

    color: #2563eb;

    border-radius: 50%;

    font-size: 24px;
}

.mail-empty h3 {
    margin: 0 0 6px;

    color: #374151;

    font-size: 18px;
}

.mail-empty p {
    margin: 0;

    color: #6b7280;

    font-size: 14px;
}


/* ==========================================================
   SCROLLING
   ========================================================== */

.mail-list {
    overflow-x: hidden;
}


/* ==========================================================
   RESPONSIVE - TABLET
   ========================================================== */

@media (max-width: 900px) {

    .mail-page {
        padding: 20px 15px 30px;
    }

    .mail-sidebar {
        width: 190px;
    }

    .mail-row {
        grid-template-columns:
            minmax(140px, 25%)
            minmax(0, 1fr)
            95px;

        gap: 10px;

        padding-left: 15px;
        padding-right: 15px;
    }

}


/* ==========================================================
   RESPONSIVE - MOBILE
   ========================================================== */

@media (max-width: 700px) {

    .mail-page {
        padding: 10px;
    }


    .mail-layout {
        display: block;

        min-height: auto;

        border-radius: 8px;
    }


    /* SIDEBAR */

    .mail-sidebar {
        width: 100%;

        padding: 12px;

        border-right: none;

        border-bottom: 1px solid #e5e7eb;
    }


    .compose-btn {
        width: 100%;
    }


    .mail-navigation {
        display: grid;

        grid-template-columns:
            repeat(4, 1fr);

        gap: 5px;

        margin-top: 10px;
    }


    .mail-folder {
        flex-direction: column;

        justify-content: center;

        gap: 5px;

        padding: 9px 5px;

        font-size: 11px;

        text-align: center;
    }


    .mail-folder i {
        width: auto;

        font-size: 15px;
    }


    /* TOOLBAR */

    .mail-toolbar {
        padding: 12px;

        gap: 8px;
    }


    .mail-toolbar input[type="search"] {
        max-width: none;
    }


    /* HEADER */

    .mail-header {
        padding: 18px 15px;
    }


    .mail-title h1 {
        font-size: 21px;
    }


    /* MAIL ROW */

    .mail-row {
        display: block;

        position: relative;

        min-height: auto;

        padding: 13px 15px;

        padding-right: 75px;
    }


    .mail-sender {
        margin-bottom: 5px;

        font-size: 13px;
    }


    .mail-subject {
        font-size: 13px;

        line-height: 1.4;
    }


    .mail-date {
        position: absolute;

        top: 14px;

        right: 15px;

        font-size: 11px;
    }

}


/* ==========================================================
   RESPONSIVE - SMALL MOBILE
   ========================================================== */

@media (max-width: 430px) {

    .mail-navigation {
        grid-template-columns:
            repeat(2, 1fr);
    }


    .mail-folder {
        flex-direction: row;

        justify-content: flex-start;

        gap: 8px;

        padding: 10px;
    }


    .mail-toolbar {
        flex-wrap: nowrap;
    }


    .mail-toolbar button {
        flex-shrink: 0;
    }


    .mail-header {
        align-items: flex-start;
    }


    .mail-row {
        padding-right: 15px;
    }


    .mail-date {
        position: static;

        margin-top: 6px;

        text-align: left;
    }

}


/* ==========================================================
   ACCESSIBILITY / FOCUS
   ========================================================== */

.mail-folder:focus-visible,
.compose-btn:focus-visible,
.mail-toolbar button:focus-visible,
.mail-toolbar input:focus-visible,
.mail-row:focus-visible {
    outline: 3px solid rgba(37, 99, 235, 0.25);

    outline-offset: 2px;
}

</style>
<?php

include '../includes/footer.php';

?>