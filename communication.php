<?php

require_once 'includes/config.php';
require_once 'includes/auth.php';
Auth::protect();
$user = Auth::getCurrentUser();
include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>

<link rel="stylesheet" href="assets/css/communication.css">

<div class="page-header">

    <div>
        <h1>Department Communication</h1>
        <p>Send messages, announcements and share documents.</p>
    </div>

</div>

<div class="communication-wrapper">

    <!-- Conversations -->

    <div class="conversation-list">

        <div class="conversation-header">

            <input
                type="text"
                placeholder="Search conversations..."
                id="conversationSearch">

        </div>

        <div class="conversation active">

            <div class="avatar finance">
                F
            </div>

            <div class="conversation-info">

                <h4>Finance Department</h4>

                <small>Budget report uploaded.</small>

            </div>

        </div>

        <div class="conversation">

            <div class="avatar hr">
                H
            </div>

            <div class="conversation-info">

                <h4>Human Resource</h4>

                <small>Policy updated.</small>

            </div>

        </div>
         <div class="conversation">

            <div class="avatar hr">
                P
            </div>

            <div class="conversation-info">

                <h4>Supply Chain</h4>

                <small>Procured.</small>

            </div>
    

        </div>
         <div class="conversation">

            <div class="avatar hr">
                s
            </div>

            <div class="conversation-info">

                <h4>Sales</h4>

                <small>sales target updated.</small>

            </div>

        </div>

         <div class="conversation">

            <div class="avatar hr">
                P
            </div>

            <div class="conversation-info">

                <h4>Production</h4>

                <small>New Product.</small>

            </div>

        </div>
        <div class="conversation">

            <div class="avatar it">
                I
            </div>

            <div class="conversation-info">

                <h4>IT Department</h4>

                <small>System maintenance today.</small>

            </div>

        </div>

    </div>

    <!-- Chat Area -->

    <div class="chat-panel">

        <div class="chat-header">

            <h3>Finance Department</h3>

            <span class="online">
                ● Online
            </span>

        </div>

        <div class="chat-body">

            <div class="message received">

                <p>
                    Please review the new financial report.
                </p>

                <span>09:15 AM</span>

            </div>

            <div class="message sent">

                <p>
                    Received. I will approve it shortly.
                </p>

                <span>09:18 AM</span>

            </div>

            <div class="message received attachment">

                <i class="fa fa-file-pdf"></i>

                Budget_Report.pdf

            </div>

        </div>

        <div class="chat-footer">

            <button
                class="attach-btn"
                onclick="attachDocument()">

                <i class="fa fa-paperclip"></i>

            </button>

            <input
                type="text"
                id="messageInput"
                placeholder="Type your message...">

            <button
                class="send-btn"
                onclick="sendMessage()">

                <i class="fa fa-paper-plane"></i>

            </button>

        </div>

    </div>

    <!-- Notifications -->

    <div class="notification-panel">

        <h3>Notifications</h3>

        <div class="notification">

            <i class="fa fa-bell"></i>

            Finance shared a document.

        </div>

        <div class="notification">

            <i class="fa fa-check-circle"></i>

            HR approved a policy.

        </div>

        <div class="notification">

            <i class="fa fa-upload"></i>

            IT uploaded a new manual.

        </div>

    </div>

</div>

<script src="assets/js/communication.js"></script>

<?php

include "includes/footer.php";

?>