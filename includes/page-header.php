<?php

$pageTitle = $pageTitle ?? "Dashboard";
$breadcrumb = $breadcrumb ?? "Dashboard";
$buttonText = $buttonText ?? "";
$buttonLink = $buttonLink ?? "";

?>

<div class="page-banner">

    <div class="page-left">

        <span class="breadcrumb">
            <?php echo $breadcrumb; ?>
        </span>

        <h1>
            <?php echo $pageTitle; ?>
        </h1>

    </div>

    <div class="page-right">

        <?php if($buttonText != ""){ ?>

            <a href="<?php echo $buttonLink; ?>" class="page-btn">

                <i class="fa fa-plus"></i>

                <?php echo $buttonText; ?>

            </a>

        <?php } ?>

        <div class="system-info">

            <span id="currentDate"></span>

            <small>
            </small>

        </div>

    </div>

</div>