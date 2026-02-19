<?php

$host = "localhost";
$user = "root";
$haslo = "";
$baza = "quizzlando";

$polaczenie = new mysqli($host, $user, $haslo, $baza);

if ($polaczenie->connect_error) {
    die("Błąd połączenia z bazą danych: " . $polaczenie->connect_error);
}

?>
