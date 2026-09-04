<?php

function timeAgo($datetime){

    $time = strtotime($datetime);

    $difference = time() - $time;

    if($difference < 60){

        return "Just now";

    }

    if($difference < 3600){

        $minutes = floor($difference / 60);

        return $minutes . " minute" . ($minutes != 1 ? "s" : "") . " ago";

    }

    if($difference < 86400){

        $hours = floor($difference / 3600);

        return $hours . " hour" . ($hours != 1 ? "s" : "") . " ago";

    }

    if($difference < 172800){

        return "Yesterday";

    }

    if($difference < 604800){

        $days = floor($difference / 86400);

        return $days . " days ago";

    }

    return date("d M Y", $time);

}