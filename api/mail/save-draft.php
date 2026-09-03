<?php

require_once '../../includes/config.php';
require_once '../../includes/auth.php';

Auth::protect();

header('Content-Type: application/json; charset=utf-8');

$user = Auth::getCurrentUser();

if (!$user) {

    echo json_encode([
        "success" => false,
        "message" => "Not authenticated."
    ]);

    exit;
}


$userId = (int)($user['id'] ?? 0);

$draftId = (int)(
    $_POST['draft_id'] ?? 0
);

$recipientId = (int)(
    $_POST['to'] ?? 0
);

$subject = trim(
    (string)(
        $_POST['subject'] ?? ''
    )
);

$body = (string)(
    $_POST['body'] ?? ''
);

$parentId = (int)(
    $_POST['parent_id'] ?? 0
);


/*
 * Subject can be empty.
 */

if ($subject === '') {
    $subject = '(No subject)';
}


/*
 * ==========================================================
 * UPDATE EXISTING DRAFT
 * ==========================================================
 */

if ($draftId > 0) {

    $draft = fetchRow(

        "SELECT
            id

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


    if (!$draft) {

        echo json_encode([
            "success" => false,
            "message" => "Draft not found."
        ]);

        exit;
    }


    $result = updateData(

        "mail_messages",

        [

            "subject" => $subject,

            "body" => $body,

            "parent_id" =>
                $parentId > 0
                ? $parentId
                : null,

            "message_type" => "draft",

            "is_draft" => 1

        ],

        [

            "id" => $draftId,

            "sender_id" => $userId

        ]

    );


    if (
        !is_array($result)
        ||
        empty($result['success'])
    ) {

        echo json_encode([

            "success" => false,

            "message" => "Unable to update draft.",

            "error" => $result['error'] ?? null

        ]);

        exit;
    }


    /*
     * Remove previous recipient.
     */

    $deleteResult = deleteData(

        "mail_recipients",

        [
            "message_id" => $draftId
        ]

    );


    if (
        !is_array($deleteResult)
        ||
        empty($deleteResult['success'])
    ) {

        echo json_encode([

            "success" => false,

            "message" => "Draft updated, but recipient could not be updated.",

            "error" => $deleteResult['error'] ?? null

        ]);

        exit;
    }


    /*
     * Save recipient if one was selected.
     */

    if ($recipientId > 0) {

        $recipient = fetchRow(

            "SELECT
                id

             FROM users

             WHERE id=?
             AND status='active'

             LIMIT 1",

            [
                $recipientId
            ]

        );


        if (!$recipient) {

            echo json_encode([

                "success" => false,

                "message" => "Selected recipient was not found."

            ]);

            exit;
        }


        if ($recipientId === $userId) {

            echo json_encode([

                "success" => false,

                "message" => "You cannot send a message to yourself."

            ]);

            exit;
        }


        $recipientResult = insertData(

            "mail_recipients",

            [

                "message_id" => $draftId,

                "user_id" => $recipientId,

                "recipient_type" => "to",

                "is_read" => 0,

                "is_starred" => 0,

                "is_deleted" => 0

            ]

        );


        if (
            !is_array($recipientResult)
            ||
            empty($recipientResult['success'])
        ) {

            echo json_encode([

                "success" => false,

                "message" => "Draft saved, but recipient could not be saved.",

                "error" => $recipientResult['error'] ?? null

            ]);

            exit;
        }

    }


    echo json_encode([

        "success" => true,

        "message" => "Draft saved successfully.",

        "message_id" => $draftId

    ]);

    exit;
}


/*
 * ==========================================================
 * CREATE NEW DRAFT
 * ==========================================================
 */

$result = insertData(

    "mail_messages",

    [

        "sender_id" => $userId,

        "subject" => $subject,

        "body" => $body,

        "parent_id" =>
            $parentId > 0
            ? $parentId
            : null,

        "message_type" => "draft",

        "is_draft" => 1

    ]

);


if (
    !is_array($result)
    ||
    empty($result['success'])
) {

    echo json_encode([

        "success" => false,

        "message" => "Unable to create draft.",

        "error" => $result['error'] ?? null

    ]);

    exit;
}


$newDraftId = (int)$result['insert_id'];


/*
 * Save recipient if selected.
 */

if ($recipientId > 0) {

    $recipient = fetchRow(

        "SELECT
            id

         FROM users

         WHERE id=?
         AND status='active'

         LIMIT 1",

        [
            $recipientId
        ]

    );


    if (!$recipient) {

        /*
         * Delete draft because recipient is invalid.
         */

        deleteData(

            "mail_messages",

            [
                "id" => $newDraftId
            ]

        );


        echo json_encode([

            "success" => false,

            "message" => "Selected recipient was not found."

        ]);

        exit;
    }


    if ($recipientId === $userId) {

        deleteData(

            "mail_messages",

            [
                "id" => $newDraftId
            ]

        );


        echo json_encode([

            "success" => false,

            "message" => "You cannot send a message to yourself."

        ]);

        exit;
    }


    $recipientResult = insertData(

        "mail_recipients",

        [

            "message_id" => $newDraftId,

            "user_id" => $recipientId,

            "recipient_type" => "to",

            "is_read" => 0,

            "is_starred" => 0,

            "is_deleted" => 0

        ]

    );


    if (
        !is_array($recipientResult)
        ||
        empty($recipientResult['success'])
    ) {

        deleteData(

            "mail_messages",

            [
                "id" => $newDraftId
            ]

        );


        echo json_encode([

            "success" => false,

            "message" => "Draft could not save its recipient.",

            "error" => $recipientResult['error'] ?? null

        ]);

        exit;
    }

}


/*
 * SUCCESS
 */

echo json_encode([

    "success" => true,

    "message" => "Draft saved successfully.",

    "message_id" => $newDraftId

]);

exit;