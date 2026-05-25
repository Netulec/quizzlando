<?php
session_start();
require_once "polaczenie.php";

if (!isset($_SESSION['id'])) {
    die("Musisz być zalogowany");
}

$quiz_id = (int)$_POST['quiz_id'];
$zglaszajacy_id = (int)$_SESSION['id'];

$powod = $polaczenie->real_escape_string($_POST['opis']);
$kategoria = $polaczenie->real_escape_string($_POST['kategoria']);

$status_id = 1;

$check = $polaczenie->query("SELECT id FROM zgloszenia WHERE quiz_id = $quiz_id AND zglaszajacy_id = $zglaszajacy_id");

if($check->num_rows > 0) {
    header("Location: index.php?popup=Spam");
    exit();
}

$sql = "INSERT INTO zgloszenia (quiz_id, zglaszajacy_id, powod, status_id, kategoria)
        VALUES($quiz_id, $zglaszajacy_id, '$powod', $status_id, '$kategoria')";

if($polaczenie->query($sql)) {

    header("Location: index.php?popup=Potwierdzenie");
    exit();

} else {

    echo "Błąd SQL: " . $polaczenie->error;
}
?>

