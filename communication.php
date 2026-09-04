
<?php

require_once "includes/config.php";
require_once "includes/auth.php";
Auth::protect();
$user = Auth::getCurrentUser();
/*
|--------------------------------------------------------------------------
| Load Users For Communication
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Load Users For Communication
|--------------------------------------------------------------------------
|
| Users with recent conversations appear first.
|
| Sorting:
|
|   1. Users who have messages come first.
|   2. Most recently active conversation comes first.
|   3. If timestamps are identical, newest message ID wins.
|   4. Users with no conversation go to the bottom.
|
*/

$users = fetchAll(
"
SELECT

    u.id,
    u.first_name,
    u.last_name,
    u.email,

    /*
     * Latest message text
     */

    (
        SELECT m.message

        FROM messages m

        INNER JOIN conversations cx
            ON cx.id = m.conversation_id

        WHERE
            (
                cx.user_one = ?
                AND cx.user_two = u.id
            )
            OR
            (
                cx.user_two = ?
                AND cx.user_one = u.id
            )

        ORDER BY
            m.created_at DESC,
            m.id DESC

        LIMIT 1

    ) AS last_message,


    /*
     * Latest message timestamp
     */

    (
        SELECT m.created_at

        FROM messages m

        INNER JOIN conversations cx
            ON cx.id = m.conversation_id

        WHERE
            (
                cx.user_one = ?
                AND cx.user_two = u.id
            )
            OR
            (
                cx.user_two = ?
                AND cx.user_one = u.id
            )

        ORDER BY
            m.created_at DESC,
            m.id DESC

        LIMIT 1

    ) AS last_message_at,


    /*
     * Latest message ID.
     *
     * This gives us a reliable tie-breaker
     * when two messages have the same timestamp.
     */

    (
        SELECT m.id

        FROM messages m

        INNER JOIN conversations cx
            ON cx.id = m.conversation_id

        WHERE
            (
                cx.user_one = ?
                AND cx.user_two = u.id
            )
            OR
            (
                cx.user_two = ?
                AND cx.user_one = u.id
            )

        ORDER BY
            m.created_at DESC,
            m.id DESC

        LIMIT 1

    ) AS last_message_id,


    /*
     * Count unread messages from this user.
     */

    (
        SELECT COUNT(*)

        FROM messages m

        INNER JOIN conversations cx
            ON cx.id = m.conversation_id

        WHERE
    (
        (
            cx.user_one = ?
            AND cx.user_two = u.id
        )
        OR
        (
            cx.user_two = ?
            AND cx.user_one = u.id
        )
    )

    AND m.sender_id != ?

    AND m.read_at IS NULL
    ) AS unread_count


FROM users u


WHERE
    u.id != ?
    AND u.status = 'active'


/*
|--------------------------------------------------------------------------
| MOST RECENT CONVERSATION FIRST
|--------------------------------------------------------------------------
*/

ORDER BY

    /*
     * Users with no messages go to the bottom.
     */

    CASE
        WHEN last_message_id IS NULL THEN 1
        ELSE 0
    END ASC,


    /*
     * Newest message first.
     */

    last_message_at DESC,


    /*
     * Final tie-breaker.
     */

    last_message_id DESC,


    /*
     * Alphabetical ordering for users
     * who have never messaged.
     */

    u.first_name ASC,
    u.last_name ASC
",
[
    /* Latest message text */
    $user['id'],
    $user['id'],

    /* Latest message timestamp */
    $user['id'],
    $user['id'],

    /* Latest message ID */
    $user['id'],
    $user['id'],

    /* Unread messages */
    $user['id'],
    $user['id'],
    $user['id'],

    /* Exclude current user */
    $user['id']
]
);
/*
|--------------------------------------------------------------------------
| Selected Chat User
|--------------------------------------------------------------------------
*/
$selectedUser = isset($_GET['user'])
    ? (int)$_GET['user']
    : 0;

/*
|--------------------------------------------------------------------------
| Selected User Details
|--------------------------------------------------------------------------
*/

$currentChatUser = null;

if ($selectedUser > 0) {

    $currentChatUser = fetchRow(
        "
        SELECT
            id,
            CONCAT(first_name,' ',last_name) AS name,
            email
        FROM users
        WHERE id=?
        ",
        [
            $selectedUser
        ]
    );

}

/*
|--------------------------------------------------------------------------
| Find/Create Conversation
|--------------------------------------------------------------------------
*/

$conversation = null;

if ($selectedUser > 0) {

    $conversation = fetchRow(
        "
        SELECT id
        FROM conversations
        WHERE
            (user_one=? AND user_two=?)
            OR
            (user_one=? AND user_two=?)
        LIMIT 1
        ",
        [
            $user['id'],
            $selectedUser,
            $selectedUser,
            $user['id']
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | Create conversation only after user is selected
    |--------------------------------------------------------------------------
    */

    if (!$conversation) {

        $result = insertData(
            "conversations",
            [
                "user_one" => $user['id'],
                "user_two" => $selectedUser
            ]
        );

        if ($result['success']) {

            $conversation = [
                "id" => $result['insert_id']
            ];

        }

    }

}

/*
|--------------------------------------------------------------------------
| Load Messages
|--------------------------------------------------------------------------
*/
$messages=[];
if(!empty($conversation['id'])){
$messages = fetchAll(
"
SELECT
m.id,
m.sender_id,
m.message,
m.created_at,
m.read_at,
m.document_id,
m.image_path,
CONCAT(
u.first_name,

' ',

u.last_name

) AS sender,


d.title,

d.file_name,
d.file_path


FROM messages m


LEFT JOIN users u

ON u.id=m.sender_id


LEFT JOIN documents d

ON d.id=m.document_id


WHERE m.conversation_id=?


ORDER BY m.created_at DESC, m.id DESC


",

[
$conversation['id']
]

);


}




/*
|--------------------------------------------------------------------------
| Approved Documents
|--------------------------------------------------------------------------
*/

$documents = fetchAll(

"
SELECT

id,

title,

file_name

FROM documents

WHERE status='approved'

ORDER BY title

"

);



include "includes/header.php";
include "includes/sidebar.php";
include "includes/navbar.php";

?>


<link rel="stylesheet" href="assets/css/communication.css">



<div class="page-header">



</div>



<div class="communication-wrapper">



<!-- ======================================================
LEFT PANEL USERS
======================================================= -->


<div class="conversation-list">


<div class="conversation-header">


<h3>
Users
</h3>


<input

type="text"

id="conversationSearch"

placeholder="Search users..."

>


</div>


<div class="conversation-scroll">

<?php foreach ($users as $chatUser): ?>

    <div
        class="conversation
        <?= $chatUser['id'] == $selectedUser ? 'active' : '' ?>
        <?= !empty($chatUser['unread_count']) ? 'has-unread' : '' ?>"
        data-id="<?= (int)$chatUser['id'] ?>"
    >

        <div class="avatar general">

            <?= strtoupper(
                substr(
                    $chatUser['first_name'],
                    0,
                    1
                )
            ) ?>

        </div>


        <div class="conversation-info">

            <h4>

                <?= htmlspecialchars(
                    $chatUser['first_name'] . " " .
                    $chatUser['last_name']
                ) ?>

            </h4>


            <small class="conversation-preview">

                <?php if (!empty($chatUser['last_message'])): ?>

                    <?= htmlspecialchars(
                        mb_strimwidth(
                            $chatUser['last_message'],
                            0,
                            40,
                            "..."
                        )
                    ) ?>

                <?php else: ?>

                    Start conversation

                <?php endif; ?>

            </small>

        </div>


        <?php if (!empty($chatUser['unread_count'])): ?>

            <span
                class="unread-dot"
                title="<?= (int)$chatUser['unread_count'] ?> unread message(s)"
            ></span>

        <?php endif; ?>

    </div>

<?php endforeach; ?>

</div>


</div>

<!-- ======================================================
CHAT PANEL
======================================================= -->


<div class="chat-panel">


<div class="chat-header">


<div>

<h3>
<?= htmlspecialchars(
    $currentChatUser['name'] ?? 'Select a User'
) ?>
</h3>

<small>
<?= $currentChatUser
    ? 'Private Conversation'
    : 'Choose a user from the list to open a conversation'
?>
</small>

</div>
<span class="online">
● Active
</span>
</div>
<div class="chat-body" id="chatBody">

<?php if (!$selectedUser): ?>

    <div class="empty-chat">
        <p>Select a user from the list to open a conversation.</p>
    </div>

<?php elseif (empty($messages)): ?>

    <div class="message received">
        <p>No messages yet.</p>
    </div>

<?php endif; ?>

<?php foreach($messages as $message): ?>
<div class="message-row
    <?= $message['sender_id'] == $user['id'] ? 'sent' : 'received' ?>
    <?= (
        $message['sender_id'] != $user['id']
        && empty($message['read_at'])
    ) ? 'unread-message' : '' ?>"
>


<div class="message-header">

<span class="sender">
<?= htmlspecialchars($message['sender']) ?>
</span>

<span class="time">
<?= date("d M Y H:i", strtotime($message['created_at'])) ?>
</span>

</div>


<?php if(!empty($message['message'])): ?>

<div class="message-text">
<?= htmlspecialchars($message['message']) ?>
</div>

<?php endif; ?>
<?php if(!empty($message['file_name'])): ?>
<div class="attachment">
    <i class="fa fa-file-pdf"></i>
    <a
        href="uploads/documents/<?= urlencode($message['file_name']) ?>"
        target="_blank"
    >
        <?= htmlspecialchars($message['title']) ?>
    </a>
</div>
<?php endif; ?>

</div>
<?php endforeach; ?>
</div>
<div class="chat-footer">

    <!-- ======================================================
         DOCUMENT ATTACHMENT PREVIEW
    ======================================================= -->

    <div
        id="attachmentPreview"
        class="attachment-preview"
        style="display:none;"
    >
        <div class="attachment-preview-content">

            <i class="fa fa-file"></i>

            <span id="attachmentName"></span>

            <button
                type="button"
                id="removeAttachment"
                class="remove-attachment"
                title="Remove attachment"
            >
                ×
            </button>

        </div>
    </div>


    <!-- ======================================================
         IMAGE ATTACHMENT PREVIEW
    ======================================================= -->

    <div
        id="imagePreview"
        class="attachment-preview"
        style="display:none;"
    >
        <div class="attachment-preview-content">

            <img
                id="imagePreviewImage"
                src=""
                alt="Image preview"
                style="
                    width:120px;
                    height:80px;
                    object-fit:cover;
                    border-radius:8px;
                "
            >

            <span id="imagePreviewName"></span>

            <button
                type="button"
                id="removeImage"
                class="remove-attachment"
                title="Remove image"
            >
                ×
            </button>

        </div>
    </div>


    <!-- ======================================================
         DOCUMENT BUTTON
    ======================================================= -->

    <button
        type="button"
        class="attach-btn"
        id="attachDocumentBtn"
        title="Attach document"
    >
        <i class="fa fa-paperclip"></i>
    </button>


    <!-- ======================================================
         IMAGE INPUT
    ======================================================= -->

   <input
    type="file"
    id="imageInput"
    accept="image/jpeg,image/png,image/gif,image/webp"
    style="display:none;"
>

<label
    for="imageInput"
    class="attach-btn"
    id="attachImageBtn"
    title="Send image"
>
    <i class="fa fa-image"></i>
</label>

    <!-- ======================================================
         IMAGE BUTTON
    ======================================================= -->

    <!--<button
        type="button"
        class="attach-btn"
        id="attachImageBtn"
        title="Send image"
    >
        <i class="fa fa-image"></i>
    </button>
-->

    <!-- ======================================================
         MESSAGE INPUT
    ======================================================= -->

    <textarea
        id="messageInput"
        name="message"
        placeholder="Write a message..."
        rows="1"
    ></textarea>


    <!-- ======================================================
         SEND BUTTON
    ======================================================= -->

    <button
        type="button"
        class="send-btn"
        id="sendMessageBtn"
        title="Send message"
    >
        <i class="fa fa-paper-plane"></i>
    </button>

</div>
<script>

const CHAT_USER_ID = <?= $selectedUser ?>;

const CURRENT_USER_ID = <?= $user['id'] ?>;

const CURRENT_CONVERSATION = <?= $conversation['id'] ?? 0 ?>;

</script>

<!-- Document Attachment Modal -->

<div id="documentModal" class="document-modal">

    <div class="document-box">

        <h3>
            Attach Document
        </h3>


        <select id="documentSelect">

            <option value="">
                Select Document
            </option>


            <?php foreach($documents as $doc): ?>

            <option value="<?= $doc['id'] ?>">

                <?= htmlspecialchars($doc['title']) ?>

            </option>

            <?php endforeach; ?>


        </select>


        <button 
        id="selectDocument"
        class="send-btn">

            Attach

        </button>


        <button 
        id="closeDocument"
        class="attach-btn">

            Cancel

        </button>


    </div>

</div>



<script src="assets/js/communication.js"></script>

<!-- ======================================================
     FULL IMAGE VIEWER
====================================================== -->

<div id="imageViewer" class="image-viewer">

    <button
        type="button"
        id="imageViewerClose"
        class="image-viewer-close"
        title="Close"
    >
        ×
    </button>

    <img
        id="imageViewerImage"
        src=""
        alt="Full size image"
    >

</div>
<?php

include "includes/footer.php";

?>
