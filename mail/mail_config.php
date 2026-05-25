<?php

$MAIL_FROM = "quizzlando@taxsa.pl";
$MAIL_REPLY = "quizzlando@taxsa.pl";
$APP_URL = "https://quizzlando.taxsa.pl";

function wyslijMail($do, $temat, $tresc) {
    global $MAIL_FROM, $MAIL_REPLY;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "From: Quizzlando <{$MAIL_FROM}>\r\n";
    $headers .= "Reply-To: {$MAIL_REPLY}\r\n";
    $headers .= "Return-Path: {$MAIL_FROM}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($do, $temat, $tresc, $headers);
}
