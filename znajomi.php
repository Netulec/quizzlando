<?php
session_start();
require_once "polaczenie.php";

if(!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['id'];
$komunikat = "";

// ----------------- Akcje -----------------

// Wysyłanie zaproszenia
if(isset($_POST['zapros_id'])) {
    $zaproszony_id = (int)$_POST['zapros_id'];

    // Sprawdzenie liczby znajomych użytkownika
    $limit_sql = $polaczenie->query("
        SELECT COUNT(*) as liczba
        FROM znajomi
        WHERE status_id=2 AND (zapraszajacy_id='$user_id' OR zaproszony_id='$user_id')
    ");
    $limit = $limit_sql->fetch_assoc()['liczba'];

    if($limit >= 10) {
        $komunikat = "Masz już maksymalną liczbę 10 znajomych!";
    } else {
        // Sprawdzenie czy już jest zaproszenie lub znajomość
        $check = $polaczenie->query("
            SELECT * FROM znajomi 
            WHERE (zapraszajacy_id='$user_id' AND zaproszony_id='$zaproszony_id') 
               OR (zapraszajacy_id='$zaproszony_id' AND zaproszony_id='$user_id')
        ");

        if($check->num_rows > 0) {
            $komunikat = "Już istnieje zaproszenie lub jesteście znajomymi.";
        } else {
            $polaczenie->query("
                INSERT INTO znajomi (zapraszajacy_id, zaproszony_id, status_id, data_utworzenia)
                VALUES ('$user_id', '$zaproszony_id', 1, NOW())
            ");
            $komunikat = "Zaproszenie wysłane!";
        }
    }
}

// Akceptacja zaproszenia
if(isset($_POST['akceptuj_id'])) {
    $akceptuj_id = (int)$_POST['akceptuj_id'];
    $polaczenie->query("UPDATE znajomi 
                        SET status_id=2 
                        WHERE zapraszajacy_id='$akceptuj_id' 
                          AND zaproszony_id='$user_id' 
                          AND status_id=1");
    $komunikat = "Zaproszenie zaakceptowane!";
}

// Odrzucenie zaproszenia
if(isset($_POST['odrzuc_id'])) {
    $odrzuc_id = (int)$_POST['odrzuc_id'];
    $polaczenie->query("DELETE FROM znajomi 
                        WHERE zapraszajacy_id='$odrzuc_id' 
                          AND zaproszony_id='$user_id' 
                          AND status_id=1");
    $komunikat = "Zaproszenie odrzucone.";
}

// Anulowanie wysłanego zaproszenia
if(isset($_POST['anuluj_id'])) {
    $anuluj_id = (int)$_POST['anuluj_id'];
    $polaczenie->query("DELETE FROM znajomi 
                        WHERE zapraszajacy_id='$user_id' 
                          AND zaproszony_id='$anuluj_id' 
                          AND status_id=1");
    $komunikat = "Zaproszenie anulowane.";
}

// Usuwanie znajomego
if(isset($_POST['usun_id'])) {
    $usun_id = (int)$_POST['usun_id'];
    $polaczenie->query("DELETE FROM znajomi 
                        WHERE (zapraszajacy_id='$user_id' AND zaproszony_id='$usun_id' AND status_id=2)
                           OR (zapraszajacy_id='$usun_id' AND zaproszony_id='$user_id' AND status_id=2)");
    $komunikat = "Znajomy został usunięty.";
}

// ----------------- Wyszukiwarka -----------------
$szukaj = "";
if(isset($_GET['szukaj'])) {
    $szukaj = $polaczenie->real_escape_string($_GET['szukaj']);
}

$sql = "SELECT id, nazwa FROM uzytkownicy 
        WHERE id != '$user_id' 
          AND nazwa LIKE '%$szukaj%'
          AND id NOT IN (
              SELECT zaproszony_id FROM znajomi WHERE zapraszajacy_id='$user_id' AND status_id=1
          )";
$uzytkownicy = $polaczenie->query($sql);

// ----------------- Prawe kolumny -----------------

// Otrzymane zaproszenia
$otrzymane_sql = "
SELECT u.id, u.nazwa
FROM znajomi z
JOIN uzytkownicy u ON u.id = z.zapraszajacy_id
WHERE z.zaproszony_id='$user_id' AND z.status_id=1
ORDER BY z.data_utworzenia DESC
";
$otrzymane_wynik = $polaczenie->query($otrzymane_sql);

// Wysłane zaproszenia oczekujące
$wyslane_sql = "
SELECT u.id, u.nazwa
FROM znajomi z
JOIN uzytkownicy u ON u.id = z.zaproszony_id
WHERE z.zapraszajacy_id='$user_id' AND z.status_id=1
ORDER BY z.data_utworzenia DESC
";
$wyslane_wynik = $polaczenie->query($wyslane_sql);

// Znajomi zaakceptowani
$znajomi_sql = "
SELECT u.id, u.nazwa
FROM znajomi z
JOIN uzytkownicy u ON u.id = CASE 
                                WHEN z.zapraszajacy_id='$user_id' THEN z.zaproszony_id
                                ELSE z.zapraszajacy_id
                              END
WHERE (z.zapraszajacy_id='$user_id' OR z.zaproszony_id='$user_id') AND z.status_id=2
ORDER BY z.data_utworzenia DESC
";
$znajomi_wynik = $polaczenie->query($znajomi_sql);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Znajomi - Quizzlando</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .card .card-content { display: flex; justify-content: space-between; align-items: center; }
        .card .card-content form { margin: 0 2px; }
    </style>
</head>
<body>
<?php include "navbar.php"; ?>

<section class="section">
  <div class="container">
    <h1 class="title">Znajomi</h1>
    <p class="subtitle">Wyszukuj nowych znajomych i zarządzaj zaproszeniami</p>

    <?php if($komunikat): ?>
        <div class="notification is-info"><?= $komunikat ?></div>
    <?php endif; ?>

    <div class="columns">

      <!-- Lewa kolumna: wyszukiwarka i wysyłanie zaproszeń -->
      <div class="column is-half">
        <form method="get" class="mb-4">
            <div class="field has-addons">
              <div class="control is-expanded">
                <input class="input" type="text" name="szukaj" placeholder="Wyszukaj po nicku" value="<?= htmlspecialchars($szukaj) ?>">
              </div>
              <div class="control">
                <button class="button is-link"><i class="fas fa-search"></i> Szukaj</button>
              </div>
            </div>
        </form>

        <?php if($uzytkownicy->num_rows > 0): ?>
            <?php while($u = $uzytkownicy->fetch_assoc()): ?>
                <div class="card mb-2">
                    <div class="card-content">
                        <p class="title is-6"><?= htmlspecialchars($u['nazwa']); ?></p>
                        <form method="post">
                            <input type="hidden" name="zapros_id" value="<?= $u['id']; ?>">
                            <button class="button is-link is-small"><i class="fas fa-user-plus"></i> Wyślij zaproszenie</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="notification is-info">Brak użytkowników do zaproszenia.</div>
        <?php endif; ?>
      </div>

      <!-- Prawa kolumna: otrzymane, wysłane i znajomi -->
      <div class="column is-half">

        <!-- Otrzymane zaproszenia -->
        <h2 class="title is-5">Otrzymane zaproszenia</h2>
        <?php if($otrzymane_wynik->num_rows > 0): ?>
            <?php while($z = $otrzymane_wynik->fetch_assoc()): ?>
                <div class="card mb-2">
                    <div class="card-content">
                        <p class="title is-6"><?= htmlspecialchars($z['nazwa']); ?></p>
                        <div>
                            <form method="post" class="is-inline-block">
                                <input type="hidden" name="akceptuj_id" value="<?= $z['id']; ?>">
                                <button class="button is-success is-small"><i class="fas fa-check"></i></button>
                            </form>
                            <form method="post" class="is-inline-block">
                                <input type="hidden" name="odrzuc_id" value="<?= $z['id']; ?>">
                                <button class="button is-danger is-small"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="notification is-info">Brak nowych zaproszeń.</div>
        <?php endif; ?>

        <!-- Wysłane zaproszenia -->
        <h2 class="title is-5 mt-5">Wysłane zaproszenia oczekujące</h2>
        <?php if($wyslane_wynik->num_rows > 0): ?>
            <?php while($z = $wyslane_wynik->fetch_assoc()): ?>
                <div class="card mb-2">
                    <div class="card-content">
                        <p class="title is-6"><?= htmlspecialchars($z['nazwa']); ?></p>
                        <form method="post">
                            <input type="hidden" name="anuluj_id" value="<?= $z['id']; ?>">
                            <button class="button is-danger is-small"><i class="fas fa-times"></i> Anuluj</button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="notification is-info">Brak wysłanych zaproszeń.</div>
        <?php endif; ?>

        <!-- Moi znajomi -->
        <h2 class="title is-5 mt-5">Moi znajomi</h2>
        <?php if($znajomi_wynik->num_rows > 0): ?>
            <?php while($z = $znajomi_wynik->fetch_assoc()): ?>
                <div class="card mb-2">
                    <div class="card-content">
                        <p class="title is-6"><?= htmlspecialchars($z['nazwa']); ?></p>
                        <div>
                            <span class="tag is-success"><i class="fas fa-user"></i> Znajomy</span>
                            <form method="post" class="is-inline-block">
                                <input type="hidden" name="usun_id" value="<?= $z['id']; ?>">
                                <button class="button is-danger is-small"><i class="fas fa-trash"></i> Usuń</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="notification is-info">Nie masz jeszcze znajomych.</div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</section>
</body>
</html>
