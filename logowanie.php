<?php
session_start();
require_once "polaczenie.php";

$blad = "";

if(isset($_POST['login']) && isset($_POST['haslo'])) {

    $login = $polaczenie->real_escape_string($_POST['login']);
    $haslo = $_POST['haslo'];

    $sql = "SELECT * FROM uzytkownicy 
            WHERE (email='$login' OR nazwa='$login') 
            AND czy_email_potwierdzony = 1";

    $wynik = $polaczenie->query($sql);

    if($wynik->num_rows > 0) {

        $uzytkownik = $wynik->fetch_assoc();

        if($uzytkownik['czy_usuniety'] == 1) {
            $blad = "Konto zostało usunięte.";
        } else {
            if(password_verify($haslo, $uzytkownik['haslo_hash'])) {
                $_SESSION['id'] = $uzytkownik['id'];
                header("Location: panel.php");
                exit();
            } else {
                $blad = "Niepoprawne hasło.";
            }
        }

    } else {
        $blad = "Użytkownik nie istnieje lub nie potwierdził emaila.";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzlando - Logowanie</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="icon" type="image/png" href="/favicon.ico">
</head>
<body>

<?php include "navbar.php"; ?>

<section class="section">
  <div class="container">
    <div class="columns is-centered">
      <div class="column is-half">

        <h1 class="title has-text-centered">Logowanie</h1>

        <?php if(!empty($blad)): ?>
          <div class="notification is-danger">
            <?= $blad ?>
          </div>
        <?php endif; ?>

        <form method="POST">

          <div class="field">
            <label class="label">Nazwa lub Email</label>
            <div class="control">
              <input class="input" type="text" name="login" required>
            </div>
          </div>

          <div class="field">
            <label class="label">Hasło</label>
            <div class="control">
              <input class="input" type="password" name="haslo" required>
            </div>
          </div>

          <div class="field">
            <button class="button is-primary is-fullwidth">
              Zaloguj się
            </button>
          </div>

        </form>

        <div class="has-text-centered mt-4">
          <a href="reset_hasla.php">Nie pamiętasz hasła?</a>
          <br><br>
          Nie masz konta? <a href="rejestracja.php">Zarejestruj się</a>
        </div>

      </div>
    </div>
  </div>
</section>

</body>
</html>
