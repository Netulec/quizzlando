<?php

function mailAktywacyjny($nazwa, $token) {

    global $APP_URL;

    $link = $APP_URL . "/aktywacja.php?token=" . $token;

    $temat = "Potwierdzenie rejestracji - Quizzlando";

    $tresc = "
Cześć $nazwa,

Dziękujemy za rejestrację w Quizzlando!

Kliknij w link poniżej, aby potwierdzić swój adres email:

$link

Jeśli to nie Ty się rejestrowałeś, zignoruj tę wiadomość.

Pozdrawiamy,
Zespół Quizzlando
";

    return [
        "temat" => $temat,
        "tresc" => $tresc
    ];
}
