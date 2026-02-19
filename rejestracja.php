<?php
session_start();
require_once "polaczenie.php";
require_once "mail/mail_config.php";
require_once "mail/mail_templates.php";

$blad = "";
$sukces = "";

function sprawdzHaslo($haslo) {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $haslo);
}

if(isset($_POST['nazwa']) && isset($_POST['email']) && isset($_POST['haslo'])) {

    $nazwa = trim($_POST['nazwa']);
    $email = trim($_POST['email']);
    $haslo_raw = $_POST['haslo'];

    if(!sprawdzHaslo($haslo_raw)) {
        $blad = "Hasło musi mieć min. 8 znaków, dużą i małą literę, cyfrę oraz znak specjalny.";
    } else {

        $haslo = password_hash($haslo_raw, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));

        $stmt = $polaczenie->prepare("SELECT id FROM uzytkownicy WHERE nazwa = ?");
        $stmt->bind_param("s", $nazwa);
        $stmt->execute();
        $stmt->store_result();

        if($stmt->num_rows > 0) {
            $blad = "Ta nazwa użytkownika jest już zajęta.";
        } else {

            $stmt2 = $polaczenie->prepare("SELECT id FROM uzytkownicy WHERE email = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $stmt2->store_result();

            if($stmt2->num_rows > 0) {
                $blad = "Ten adres email jest już zarejestrowany.";
            } else {

                $stmt3 = $polaczenie->prepare("
                    INSERT INTO uzytkownicy 
                    (nazwa, email, haslo_hash, data_utworzenia, token, czy_email_potwierdzony)
                    VALUES (?, ?, ?, NOW(), ?, 0)
                ");

                $stmt3->bind_param("ssss", $nazwa, $email, $haslo, $token);

                if($stmt3->execute()) {

                    $mail = mailAktywacyjny($nazwa, $token);
                    wyslijMail($email, $mail['temat'], $mail['tresc']);

                    $sukces = "Rejestracja zakończona. Sprawdź email i potwierdź konto.";
                } else {
                    $blad = "Wystąpił błąd podczas rejestracji.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Quizzlando - Rejestracja</title>
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

        <h1 class="title has-text-centered">Rejestracja</h1>

        <?php if(!empty($blad)): ?>
            <div class="notification is-danger">
                <?= $blad ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($sukces)): ?>
            <div class="notification is-success">
                <?= $sukces ?>
            </div>
        <?php endif; ?>

        <form method="POST">
          <div class="field">
            <label class="label">Nazwa użytkownika</label>
            <div class="control">
              <input class="input" type="text" name="nazwa" required>
            </div>
          </div>

          <div class="field">
            <label class="label">Email</label>
            <div class="control">
              <input class="input" type="email" name="email" required>
            </div>
          </div>

          <div class="field">
            <label class="label">Hasło</label>
            <div class="control">
              <input class="input" type="password" name="haslo" required>
            </div>
            <p class="help">
              Min. 8 znaków, duża i mała litera, cyfra, znak specjalny
            </p>
          </div>

          <div class="field">
            <button class="button is-primary is-fullwidth">
              Zarejestruj się
            </button>
          </div>
        </form>

        <p class="has-text-centered">
          Masz już konto? <a href="logowanie.php">Zaloguj się</a>
        </p>

      </div>
    </div>
  </div>
</section>

</body>
</html>