<?php

$host = "localhost";
$user = "serwer319687_quizzlando";
$haslo = "c%Q4B1hFh#vIfiAd";
$baza = "serwer319687_quizzlando";

$polaczenie = new mysqli($host, $user, $haslo, $baza);

if ($polaczenie->connect_error) {
    die("Błąd połączenia z bazą danych: " . $polaczenie->connect_error);
}

?>
