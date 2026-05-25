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
<body class="has-background-light" style="min-height: 100vh;">

<?php include "navbar.php"; ?>

<section class="section is-flex-grow-1 is-flex is-align-items-center">
  <div class="container">
    <div class="columns is-centered">
      <div class="column is-half is-one-third-widescreen">

        <div class="box p-5 mt-5">
            <h1 class="title is-4 has-text-centered mb-5">Rejestracja</h1>

            <?php if(!empty($blad)): ?>
                <div class="notification is-danger is-light">
                    <button class="delete" onclick="this.parentElement.style.display='none';"></button>
                    <?= htmlspecialchars($blad) ?>
                </div>
            <?php endif; ?>

            <?php if(!empty($sukces)): ?>
                <div class="notification is-success is-light">
                    <button class="delete" onclick="this.parentElement.style.display='none';"></button>
                    <?= htmlspecialchars($sukces) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
              <div class="field">
                <label class="label">Nazwa użytkownika</label>
                <div class="control">
                  <input class="input" type="text" name="nazwa" placeholder="np. jankowalski" required>
                </div>
              </div>

              <div class="field">
                <label class="label">Email</label>
                <div class="control">
                  <input class="input" type="email" name="email" placeholder="np. jan@example.com" required>
                </div>
              </div>

              <div class="field">
                <label class="label">Hasło</label>
                <div class="control">
                  <input class="input" type="password" name="haslo" placeholder="••••••••" required>
                </div>
                <p class="help has-text-grey">
                  Min. 8 znaków, duża i mała litera, cyfra oraz znak specjalny.
                </p>
              </div>

              <div class="field mt-5">
                <button class="button is-primary is-fullwidth has-text-weight-bold">
                  Zarejestruj się
                </button>
              </div>
            </form>

            <hr>

            <div class="has-text-centered mt-4 is-size-6">
              <p>Masz już konto? <strong><a href="logowanie.php" class="has-text-link">Zaloguj się</a></strong></p>
            </div>
        </div>

      </div>
    </div>
  </div>
</section>

<?php include "footer.php"; ?>

</body>
</html>