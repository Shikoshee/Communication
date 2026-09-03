<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/Settings.php";
require_once "../../includes/Audit.php";

Auth::protect();

header("Content-Type: application/json");

$user = Auth::getCurrentUser();

$allowed = [

    "organization_name",
    "organization_email",
    "organization_phone",
    "organization_website",
    "organization_address",

    "two_factor_auth",
    "password_expiry",
    "force_login_approval",

    "email_notifications",
    "approval_notifications",
    "browser_notifications",
    "require_document_approval",

    "smtp_host",
    "smtp_port",
    "smtp_username",
    "smtp_password",
    "smtp_encryption",
    "smtp_from_name",
    "smtp_from_email",

    "theme",
    "default_visibility",
    "max_upload_size",
    "allowed_extensions"

];

foreach ($allowed as $key) {

    if (!array_key_exists($key, $_POST)) {
        continue;
    }

    $value = trim($_POST[$key]);

    $old = Settings::get($key);

    Settings::set(
        $key,
        $value,
        $user['id']
    );

    if ($old != $value) {

        Audit::log(
            "Updated Setting",
            "settings",
            null,
            json_encode([$key => $old]),
            json_encode([$key => $value])
        );

    }
}
echo json_encode([
    "success" => true,
    "message" => "Settings saved successfully."
]);