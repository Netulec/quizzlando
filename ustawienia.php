<?php
session_start();
require_once "polaczenie.php";

if(!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['id'];

$sql = "SELECT nazwa, email FROM uzytkownicy WHERE id='$user_id'";
$result = $polaczenie->query($sql);
$user = $result->fetch_assoc();

$komunikat = "";
$typ = "is-success";

if($_SERVER['REQUEST_METHOD'] == 'POST') {

// Zmiana nazwy
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

// Zmiana hasła
	if(!empty($_POST['stare_haslo']) && !empty($_POST['haslo']) && !empty($_POST['haslo2'])) {

// Pobranie aktualnego hasła z bazy
    	$res = $polaczenie->query("SELECT haslo_hash FROM uzytkownicy WHERE id='$user_id'");
    	$dane = $res->fetch_assoc();
    	$aktualne_haslo_hash = $dane['haslo_hash'];

// Sprawdzenie starego hasła
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

// Zmiana emaila
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

    $result = $polaczenie->query("SELECT nazwa, email FROM uzytkownicy WHERE id='$user_id'");
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

    <div class="columns">

      <!-- Zmiana nazwy -->
      <div class="column">
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

      <!-- Zmiana email -->
      <div class="column">
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

      <!-- Zmiana hasła -->
      <div class="column">
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

    </div>

  </div>
</section>

</body>
</html>
