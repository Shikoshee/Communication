<?php

require_once "../../includes/config.php";
require_once "../../includes/auth.php";

Auth::protect();

header("Content-Type: application/json");

$id=(int)$_POST['id'];

$newPassword=substr(md5(time()),0,10);

$result=updateData("users",[

    "password"=>password_hash($newPassword,PASSWORD_DEFAULT),

    "must_change_password"=>1

],[

    "id"=>$id

]);

echo json_encode([

"success"=>$result['success'],

"message"=>$result['success']
?"Temporary password: ".$newPassword
:$result['error']

]);