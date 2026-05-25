<?php
session_start();
require_once "polaczenie.php";

$blad = "";
$blad_is_html = false;

// Komunikat, gdy użytkownik zostanie wyrzucony z sesji (np. z navbar.php)
if (isset($_GET['konto_usuniete']) && $_GET['konto_usuniete'] == 1) {
    $blad = "Twoje konto zostało usunięte z platformy.";
}

if(isset($_POST['login']) && isset($_POST['haslo'])) {

    $login = trim($_POST['login']);
    $haslo = trim($_POST['haslo']);

    if(empty($login) || empty($haslo)) {
        $blad = "Proszę wypełnić wszystkie pola.";
    } else {
        // Zastosowanie Prepared Statements (najwyższe bezpieczeństwo)
        $stmt = $polaczenie->prepare("SELECT * FROM uzytkownicy WHERE (email=? OR nazwa=?) AND czy_email_potwierdzony = 1");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $wynik = $stmt->get_result();

        if($wynik->num_rows > 0) {
            $uzytkownik = $wynik->fetch_assoc();

            // Sprawdzanie czy konto zostało usunięte
            if($uzytkownik['czy_usuniety'] == 1) {
                // Zaktualizowany, czytelny komunikat (dodano cudzysłów)
                $blad = 'Te konto zostało usunięte. Jeśli to błąd, <a href="mailto:quizzlando@taxsa.pl" class="has-text-weight-bold">skontaktuj się z administracją</a>.';
                $blad_is_html = true; // Zaznaczamy, że ten błąd zawiera bezpieczny HTML
            } else {
                // Weryfikacja hasła
                if(password_verify($haslo, $uzytkownik['haslo_hash'])) {
                    
                    $_SESSION['id'] = $uzytkownik['id'];
                    $_SESSION['rola_id'] = $uzytkownik['rola_id'];
                    $_SESSION['nazwa'] = $uzytkownik['nazwa'];
                    
                    header("Location: panel.php");
                    exit();

                } else {
                    $blad = "Niepoprawne hasło.";
                }
            }
        } else {
            $blad = "Użytkownik nie istnieje lub nie potwierdził emaila.";
        }
        $stmt->close();
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
<body class="has-background-light" style="min-height: 100vh; display: flex; flex-direction: column;">

<?php include "navbar.php"; ?>

<section class="section is-flex-grow-1 is-flex is-align-items-center">
  <div class="container">
    <div class="columns is-centered">
      <div class="column is-half is-one-third-widescreen">

        <div class="box p-5 mt-5">
            <h1 class="title is-4 has-text-centered mb-5">Logowanie</h1>

            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'premium_aktywowane'): ?>
              <div class="notification is-success is-light">
                Premium zostało aktywowane. Zaloguj się ponownie.
              </div>
            <?php endif; ?>

            <?php if(!empty($blad)): ?>
              <div class="notification is-danger is-light">
                <button class="delete" onclick="this.parentElement.style.display='none';"></button>
                <?php
                    if($blad_is_html) {
                        echo $blad;
                    } else {
                        echo htmlspecialchars($blad);
                    }
                ?>
              </div>
            <?php endif; ?>

            <form method="POST">
              <div class="field">
                <label class="label">Nazwa lub Email</label>
                <div class="control">
                  <input class="input" type="text" name="login" placeholder="Wpisz nazwę lub email" required>
                </div>
              </div>

              <div class="field">
                <label class="label">Hasło</label>
                <div class="control">
                  <input class="input" type="password" name="haslo" placeholder="••••••••" required>
                </div>
              </div>

              <div class="field mt-5">
                <button class="button is-primary is-fullwidth has-text-weight-bold">
                  Zaloguj się
                </button>
              </div>
            </form>

            <hr>

            <div class="has-text-centered mt-4 is-size-6">
              <p class="mb-2"><a href="reset_hasla.php" class="has-text-grey">Nie pamiętasz hasła?</a></p>
              <p>Nie masz konta? <strong><a href="rejestracja.php" class="has-text-link">Zarejestruj się</a></strong></p>
            </div>
        </div>

      </div>
    </div>
  </div>
</section>

<?php include "footer.php"; ?>

</body>
</html>