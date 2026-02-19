<?php
session_start();
require_once "polaczenie.php";

if(!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$id_uzytkownika = $_SESSION['id'];

// Pobieramy quizy użytkownika
$sql = "SELECT * FROM quizy 
        WHERE autor_id = ? AND czy_usuniety = 0 
        ORDER BY data_utworzenia DESC";

$stmt = $polaczenie->prepare($sql);
$stmt->bind_param("i", $id_uzytkownika);
$stmt->execute();
$wynik = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Moje quizy</title>
    <link rel="stylesheet" href="bulma.min.css">
</head>
<body>

<section class="section">
<div class="container">

<h1 class="title">Moje quizy</h1>

<?php if($wynik->num_rows > 0): ?>

<table class="table is-fullwidth is-striped">
    <thead>
        <tr>
            <th>Tytuł</th>
            <th>Data utworzenia</th>
            <th>Premium</th>
            <th>Akcje</th>
        </tr>
    </thead>
    <tbody>

    <?php while($quiz = $wynik->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($quiz['tytul']) ?></td>
            <td><?= $quiz['data_utworzenia'] ?></td>
            <td>
                <?= $quiz['czy_premium'] ? "Tak" : "Nie" ?>
            </td>
            <td>
                <a href="quiz_edytor.php?id=<?= $quiz['id'] ?>" 
                   class="button is-small is-warning">
                   Edytuj
                </a>

                <a href="quiz_gra.php?id=<?= $quiz['id'] ?>" 
                   class="button is-small is-primary">
                   Zagraj
                </a>
            </td>
        </tr>
    <?php endwhile; ?>

    </tbody>
</table>

<?php else: ?>

<div class="notification is-info">
    Nie stworzyłeś jeszcze żadnego quizu.
</div>

<?php endif; ?>

</div>
</section>

</body>
</html>
