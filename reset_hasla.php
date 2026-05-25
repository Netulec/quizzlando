<?php
session_start();
require_once "polaczenie.php";

$blad = "";
$sukces = "";

if(isset($_POST['login'])) {
    $login = trim($_POST['login']);

    if(empty($login)) {
        $blad = "Proszę podać email lub nazwę użytkownika.";
    } else {
        // Bezpieczne wyszukiwanie użytkownika (Prepared Statements)
        $stmt = $polaczenie->prepare("SELECT id, email, czy_usuniety FROM uzytkownicy WHERE email=? OR nazwa=?");
        $stmt->bind_param("ss", $login, $login);
        $stmt->execute();
        $wynik = $stmt->get_result();

        if($wynik->num_rows > 0) {
            $uzytkownik = $wynik->fetch_assoc();

            // Zabezpieczenie: jeśli konto jest usunięte, nie pozwalamy na reset hasła
            if($uzytkownik['czy_usuniety'] == 1) {
                $blad = "To konto zostało usunięte. Resetowanie hasła jest niemożliwe.";
            } else {
                // Generowanie unikalnego tokenu i czasu wygaśnięcia 1h
                $token = bin2hex(random_bytes(16));
                $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

                // Zapis tokenu w bazie (Prepared Statements)
                $stmt_update = $polaczenie->prepare("UPDATE uzytkownicy SET token=?, token_wygasa=? WHERE id=?");
                $stmt_update->bind_param("ssi", $token, $expires, $uzytkownik['id']);
                $stmt_update->execute();
                $stmt_update->close();

                // Link do resetu
                $link = "https://quizzlando.taxsa.pl/nowe_haslo.php?token=".$token;

                // Wysłanie maila
                $to = $uzytkownik['email'];
                $subject = "Resetowanie hasła - Quizzlando";
                $message = "Kliknij w poniższy link, aby zresetować hasło:\n\n$link\n\nLink jest ważny przez 1 godzinę. Jeśli to nie Ty prosiłeś o zmianę hasła, zignoruj tę wiadomość.";
                
                // Dodajemy nagłówki, aby polskie znaki wyświetlały się poprawnie w mailu
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/plain;charset=UTF-8" . "\r\n";
                $headers .= "From: Quizzlando <no-reply@quizzlando.taxsa.pl>\r\n";

                if(mail($to, $subject, $message, $headers)) {
                    $sukces = "Wysłaliśmy link do resetowania hasła na Twój adres e-mail.";
                } else {
                    $blad = "Wystąpił błąd podczas wysyłania e-maila.";
                }
            }
        } else {
            $blad = "Nie znaleziono użytkownika o podanym loginie lub e-mailu.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Quizzlando - Resetowanie hasła</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    <h1 class="title is-4 has-text-centered mb-5">Resetowanie hasła</h1>

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
                            <label class="label">Email lub nazwa użytkownika</label>
                            <div class="control">
                                <input class="input" type="text" name="login" required placeholder="np. jankowalski lub jan@example.com">
                            </div>
                        </div>

                        <div class="field mt-5">
                            <div class="control">
                                <button class="button is-primary is-fullwidth has-text-weight-bold" type="submit">
                                    Wyślij link resetujący
                                </button>
                            </div>
                        </div>
                    </form>

                    <hr>

                    <div class="has-text-centered mt-4 is-size-6">
                        <p><a href="logowanie.php" class="has-text-link">Wróć do logowania</a></p>
                    </div>

                </div>

            </div>
        </div>
    </div>
</section>

<?php include "footer.php"; ?>

</body>
</html>