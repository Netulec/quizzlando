<?php
require_once "polaczenie.php";

if (isset($_GET['token'])) {

    $token = $_GET['token'];

    $stmt = $polaczenie->prepare("UPDATE uzytkownicy SET czy_email_potwierdzony = 1, token = NULL WHERE token = ?");

    $stmt->bind_param("s", $token);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "Email został potwierdzony. Możesz się zalogować.";
    } else {
        echo "Nieprawidłowy lub wygasły link.";
    }

    $stmt->close();
} else {
    echo "Brak tokenu.";
}
?>
