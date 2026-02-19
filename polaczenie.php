<?php

$host = "localhost";
$user = "serwer319687_quizzlando";
$haslo = "I!vsL46Kh9v80@b%";
$baza = "serwer319687_quizzlando";

$polaczenie = new mysqli($host, $user, $haslo, $baza);

if ($polaczenie->connect_error) {
    die("Błąd połączenia z bazą danych: " . $polaczenie->connect_error);
}

?>
