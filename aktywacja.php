<?php
require_once "polaczenie.php";

$komunikat = "";
$typ = "is-danger"; // domyślnie czerwony

if (isset($_GET['token'])) {

    $token = $_GET['token'];

    $stmt = $polaczenie->prepare("UPDATE uzytkownicy 
        SET czy_email_potwierdzony = 1, token = NULL 
        WHERE token = ?");

    $stmt->bind_param("s", $token);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $komunikat = "Email został potwierdzony. Możesz się zalogować.";
        $typ = "is-success";
    } else {
        $komunikat = "Nieprawidłowy lub wygasły link.";
    }

    $stmt->close();

} else {
    $komunikat = "Brak tokenu aktywacyjnego.";
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Aktywacja konta - Quizzlando</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- BULMA -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">

    <!-- Ikony -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<section class="hero is-fullheight is-light">
  <div class="hero-body">
    <div class="container">
      <div class="columns is-centered">
        <div class="column is-5">

          <div class="card">
            <div class="card-content has-text-centered">

              <span class="icon is-large mb-4">
                <i class="fas fa-envelope-circle-check fa-3x"></i>
              </span>

              <h1 class="title is-4">Aktywacja konta</h1>

              <div class="notification <?= $typ ?>">
                <?= $komunikat ?>
              </div>

              <a href="logowanie.php" class="button is-primary is-fullwidth">
                Przejdź do logowania
              </a>

            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>

</body>
</html>
