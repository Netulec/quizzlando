<?php
session_start();
require_once "polaczenie.php";

if(!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['id'];

// pobranie danych użytkownika
$sql = "SELECT u.nazwa, u.email, r.nazwa AS rola, u.premium_do
        FROM uzytkownicy u
        LEFT JOIN role r ON u.rola_id = r.id
        WHERE u.id='$user_id'";
$result = $polaczenie->query($sql);
$user = $result->fetch_assoc();

$komunikat = "";
$typ = "is-success";
$komunikat_usuniecia = "";
$pokaz_potwierdzenie = false;

if($_SERVER['REQUEST_METHOD'] == 'POST') {

    // zmiana nazwy
    if(isset($_POST['nazwa']) && !empty($_POST['nazwa'])) {
        $nowa_nazwa = $polaczenie->real_escape_string($_POST['nazwa']);

        $check = $polaczenie->query("SELECT id FROM uzytkownicy WHERE nazwa='$nowa_nazwa' AND id!='$user_id'");
        if($check->num_rows > 0) {
            $komunikat = "Nazwa jest już zajęta.";
            $typ = "is-danger";
        } else {
            $polaczenie->query("UPDATE uzytkownicy SET nazwa='$nowa_nazwa' WHERE id='$user_id'");
            $komunikat = "Nazwa została zmieniona.";
        }
    }

    // zmiana hasła
    if(!empty($_POST['stare_haslo']) && !empty($_POST['haslo']) && !empty($_POST['haslo2'])) {
        $res = $polaczenie->query("SELECT haslo_hash FROM uzytkownicy WHERE id='$user_id'");
        $dane = $res->fetch_assoc();
        $aktualne_haslo_hash = $dane['haslo_hash'];

        if(password_verify($_POST['stare_haslo'], $aktualne_haslo_hash)) {
            if($_POST['haslo'] === $_POST['haslo2']) {
                $haslo_hash2 = password_hash($_POST['haslo'], PASSWORD_DEFAULT);
                $polaczenie->query("UPDATE uzytkownicy SET haslo_hash='$haslo_hash2' WHERE id='$user_id'");
                $komunikat = "Hasło zostało zmienione.";
            } else {
                $komunikat = "Nowe hasła nie są identyczne.";
                $typ = "is-danger";
            }
        } else {
            $komunikat = "Stare hasło jest nieprawidłowe.";
            $typ = "is-danger";
        }
    }

    // zmiana emaila
    if(!empty($_POST['email']) && $_POST['email'] !== $user['email']) {
        $nowy_email = $polaczenie->real_escape_string($_POST['email']);

        $check = $polaczenie->query("SELECT id FROM uzytkownicy WHERE email='$nowy_email' AND id!='$user_id'");
        if($check->num_rows > 0) {
            $komunikat = "Ten email jest już zajęty.";
            $typ = "is-danger";
        } else {
            $token = bin2hex(random_bytes(16));
            $polaczenie->query("UPDATE uzytkownicy 
                SET email='$nowy_email', token='$token', czy_email_potwierdzony=0 
                WHERE id='$user_id'");
            $komunikat = "Na nowy email wysłano link potwierdzający.";
        }
    }

    // usunięcie konta 1
    if(isset($_POST['usun_konto'])) {
        $komunikat_usuniecia = "Czy na pewno chcesz usunąć swoje konto? To działanie można potwierdzić poniżej.";
        $pokaz_potwierdzenie = true;
    }

    // usunięcie konta 2
    if(isset($_POST['potwierdz_usuniecie'])) {
        $polaczenie->query("UPDATE uzytkownicy SET czy_usuniety=1 WHERE id='$user_id'");
        session_destroy();
        header("Location: index.php");
        exit();
    }

    // odświeżenie danych użytkownika
    $result = $polaczenie->query("SELECT u.nazwa, u.email, r.nazwa AS rola, u.premium_do
                                  FROM uzytkownicy u
                                  LEFT JOIN role r ON u.rola_id = r.id
                                  WHERE u.id='$user_id'");
    $user = $result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Quizzlando - Ustawienia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="icon" type="image/png" href="/favicon.ico">
</head>
<body>

<?php include "navbar.php"; ?>

<section class="section">
  <div class="container">

    <h1 class="title">Ustawienia konta</h1>
    <p class="subtitle">Zarządzaj swoim kontem</p>

    <?php if($komunikat): ?>
        <div class="notification <?= $typ ?>">
            <?= $komunikat ?>
        </div>
    <?php endif; ?>

    <div class="columns is-multiline">

      <!-- zmiana nazwy -->
      <div class="column is-one-third">
        <div class="card">
          <div class="card-content">
            <p class="title is-5">Zmień nazwę</p>
            <form method="post">
              <div class="field">
                <input class="input" type="text" name="nazwa"
                value="<?= htmlspecialchars($user['nazwa']); ?>" required>
              </div>
              <button class="button is-primary is-fullwidth">Zapisz</button>
            </form>
          </div>
        </div>
      </div>

      <!-- zmiana email -->
      <div class="column is-one-third">
        <div class="card">
          <div class="card-content">
            <p class="title is-5">Zmień email</p>
            <form method="post">
              <div class="field">
                <input class="input" type="email" name="email"
                value="<?= htmlspecialchars($user['email']); ?>" required>
              </div>
              <button class="button is-link is-fullwidth">Zapisz</button>
            </form>
          </div>
        </div>
      </div>

      <!-- zmiana hasła -->
      <div class="column is-one-third">
        <div class="card">
          <div class="card-content">
            <p class="title is-5">Zmień hasło</p>
            <form method="post">
              <div class="field">
                <input class="input" type="password" name="stare_haslo" placeholder="Stare hasło" required>
              </div>
              <div class="field">
                <input class="input" type="password" name="haslo" placeholder="Nowe hasło" required>
              </div>
              <div class="field">
                <input class="input" type="password" name="haslo2" placeholder="Powtórz hasło" required>
              </div>
              <button class="button is-danger is-fullwidth">Zmień hasło</button>
            </form>
          </div>
        </div>
      </div>

      <!-- wyświetlenie roli i czasu premium -->
      <div class="column is-one-third">
        <div class="card">
          <div class="card-content">
            <p class="title is-5">Twoja rola</p>
            <p class="subtitle">
                <?= htmlspecialchars($user['rola'] ?: 'Brak roli'); ?>

                <?php if(!empty($user['premium_do']) && strtotime($user['premium_do']) > time()): ?>
                    <br>
                    <small>
                        Premium do:
                        <?php
                            $sekundy = strtotime($user['premium_do']) - time();
                            $dni = floor($sekundy / 86400);
                            $godziny = floor(($sekundy % 86400) / 3600);

                            echo $dni . " dni " . $godziny . " godzin";
                        ?>
                    </small>
                <?php endif; ?>
            </p>
          </div>
        </div>
      </div>

      <!-- usunięcie konta -->
      <div class="column is-one-third">
        <div class="card">
          <div class="card-content">
            <p class="title is-5">Usuń konto</p>

            <?php if(!empty($komunikat_usuniecia) && $pokaz_potwierdzenie): ?>
                <div class="notification is-warning">
                    <?= $komunikat_usuniecia ?>
                </div>
                <form method="post">
                    <input type="hidden" name="potwierdz_usuniecie" value="1">
                    <button class="button is-danger is-fullwidth">Potwierdź usunięcie</button>
                </form>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="usun_konto" value="1">
                    <button class="button is-danger is-fullwidth">Usuń konto</button>
                </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>

  </div>
</section>

<?php include "footer.php"; ?>

</body>
</html>