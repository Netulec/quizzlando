<?php
session_start();
require_once "polaczenie.php";

$komunikat = "";

if(isset($_POST['login'])) {
    $login = $polaczenie->real_escape_string($_POST['login']);

// Szukamy użytkownika po emailu lub nazwie
    $sql = "SELECT * FROM uzytkownicy WHERE email='$login' OR nazwa='$login'";
    $wynik = $polaczenie->query($sql);

    if($wynik->num_rows > 0) {
        $uzytkownik = $wynik->fetch_assoc();

// Generujemy unikalny token i czas wygaśnięcia (1h)
        $token = bin2hex(random_bytes(16));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

// Zapisujemy token w bazie
        $sql_update = "UPDATE uzytkownicy SET token='$token', token_wygasa='$expires' WHERE id=".$uzytkownik['id'];
        $polaczenie->query($sql_update);

// Przygotowujemy link do resetu
        $link = "https://quizzlando.taxsa.pl/nowe_haslo.php?token=".$token;

// Wysyłamy maila
        $to = $uzytkownik['email'];
        $subject = "Resetowanie hasła - Quizzlando";
        $message = "Kliknij w link aby zresetować hasło:\n\n$link\n\nLink jest ważny 1 godzinę.";

        if(mail($to, $subject, $message)) {
            $komunikat = "Wysłaliśmy link do resetowania hasła na Twój email.";
        } else {
            $komunikat = "Wystąpił błąd podczas wysyłania maila.";
        }

    } else {
        $komunikat = "Nie znaleziono użytkownika o takim loginie lub emailu.";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Quizzlando - Resetowanie hasła</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bulma -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="icon" type="image/png" href="/favicon.ico">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar is-primary">
    <div class="navbar-brand">
        <a class="navbar-item has-text-weight-bold" href="index.php">Quizzlando</a>
    </div>
</nav>

<!-- SEKCJA -->
<section class="section">
    <div class="container">
        <div class="columns is-centered">
            <div class="column is-5">

                <div class="box">
                    <h2 class="title has-text-centered">Resetowanie hasła</h2>

                    <?php if(!empty($komunikat)): ?>
                        <div class="notification is-info is-light">
                            <?php echo $komunikat; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="field">
                            <label class="label">Email lub nazwa użytkownika</label>
                            <div class="control">
                                <input class="input" type="text" name="login" required placeholder="np. user123 lub email@example.com">
                            </div>
                        </div>

                        <div class="field">
                            <div class="control">
                                <button class="button is-primary is-fullwidth" type="submit">
                                    Wyślij link resetujący
                                </button>
                            </div>
                        </div>
                    </form>

                </div>

            </div>
        </div>
    </div>
</section>

</body>
</html>
