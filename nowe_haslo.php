<?php
session_start();
require_once "polaczenie.php";

$komunikat = "";
$typ = "is-success";

if(isset($_GET['token'])) {

    $token = $polaczenie->real_escape_string($_GET['token']);
    $sql = "SELECT * FROM uzytkownicy 
            WHERE token='$token' AND token_wygasa > NOW()";
    $wynik = $polaczenie->query($sql);

    if($wynik->num_rows > 0) {

        $uzytkownik = $wynik->fetch_assoc();

        if(isset($_POST['haslo']) && isset($_POST['haslo2'])) {

            if($_POST['haslo'] !== $_POST['haslo2']) {
                $komunikat = "Hasła nie są takie same.";
                $typ = "is-danger";
            } else {

                $haslo_hash = password_hash($_POST['haslo'], PASSWORD_DEFAULT);

                $sql_update = "UPDATE uzytkownicy 
                    SET haslo_hash='$haslo_hash',
                        token=NULL,
                        token_wygasa=NULL
                    WHERE id=".$uzytkownik['id'];

                if($polaczenie->query($sql_update)) {
                    $komunikat = "Hasło zostało zmienione. Możesz się zalogować.";
                } else {
                    $komunikat = "Wystąpił błąd.";
                    $typ = "is-danger";
                }
            }
        }

    } else {
        $komunikat = "Token jest nieprawidłowy lub wygasł.";
        $typ = "is-danger";
    }

} else {
    $komunikat = "Brak tokena.";
    $typ = "is-danger";
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Quizzlando - Nowe hasło</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="icon" type="image/png" href="/favicon.ico">
</head>
<body>

<?php include "navbar.php"; ?>

<section class="section">
  <div class="container">
    <div class="columns is-centered">
      <div class="column is-half">

        <h1 class="title has-text-centered">Ustaw nowe hasło</h1>

        <?php if($komunikat): ?>
            <div class="notification <?= $typ ?>">
                <?= $komunikat ?>
            </div>
        <?php endif; ?>

        <?php if($typ !== "is-success" || isset($_POST['haslo']) == false): ?>
        <form method="POST">
          <div class="field">
            <input class="input" type="password" name="haslo" placeholder="Nowe hasło" required>
          </div>

          <div class="field">
            <input class="input" type="password" name="haslo2" placeholder="Powtórz hasło" required>
          </div>

          <button class="button is-primary is-fullwidth">
            Zmień hasło
          </button>
        </form>
        <?php endif; ?>

      </div>
    </div>
  </div>
</section>

</body>
</html>

