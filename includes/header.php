<?php

require_once __DIR__ . '/Settings.php';

$settings = Settings::all();

$theme = $settings['theme'] ?? 'light';

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>


<title>
Communication System
</title>


<!-- ==========================================================
     GLOBAL CSS
     ========================================================== -->

<link
    rel="stylesheet"
    href="/Communication/assets/css/style.css"
>


<link
    rel="stylesheet"
    href="/Communication/assets/css/theme.css"
>


<!-- Sidebar -->

<link
    rel="stylesheet"
    href="/Communication/assets/css/sidebar.css"
>


<!-- Navbar -->

<link
    rel="stylesheet"
    href="/Communication/assets/css/navbar.css"
>


<!-- Responsive -->

<link
    rel="stylesheet"
    href="/Communication/assets/css/responsive.css"
>


<!-- Dashboard -->

<link
    rel="stylesheet"
    href="/Communication/assets/css/dashboard.css"
>


<!-- ==========================================================
     FONT AWESOME
     ========================================================== -->

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
>


<!-- ==========================================================
     CHART.JS
     ========================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/chart.js"
></script>


<!-- ==========================================================
     SWEETALERT
     ========================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
></script>


</head>


<body class="<?= htmlspecialchars($theme) ?>">


<div class="container">