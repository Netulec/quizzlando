<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PopUp</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">


</head>
<body>

<?php

$pokazPopup = false;

if (isset($_SESSION['id'])) {

    $id = (int) $_SESSION['id'];

    $res = $polaczenie->query("SELECT id FROM quizy WHERE autor_id = $id AND czy_usuniety = 1 AND popup_wyslany = 0");

    if ($res && $res->num_rows > 0 && empty($_SESSION['popup_dostarczony'])) {

        $pokazPopup = true;

        $polaczenie->query("UPDATE quizy SET popup_wyslany = 1 WHERE autor_id = $id AND czy_usuniety = 1 AND popup_wyslany = 0");
        $_SESSION['popup_dostarczony'] = true;

        }
    }
?>

<script>
function zamknijPopup() {
    const modal = document.getElementById("popup");
    if (modal) {
        modal.classList.remove("is-active");
    }
}
</script>

<div id="popup" class="modal <?= $pokazPopup ? 'is-active' : '' ?>">
    <div class="modal-background"></div>

    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">Powiadomienie</p>
            <button class="delete" aria-label="close" onclick="zamknijPopup()"></button>
        </header>

        <section class="modal-card-body">
            Twój quiz został usunięty przez administratora
        </section>

        <footer class="modal-card-foot">
            <button class="button is-success" onclick="zamknijPopup()">OK</button>
        </footer>
    </div>
</div>



</body>
</html>