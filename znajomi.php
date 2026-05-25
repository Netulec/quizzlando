<?php
session_start();

if(!isset($_SESSION['id'])) {
    header("Location: logowanie.php");
    exit();
}

require_once "polaczenie.php";
$user_id = (int)$_SESSION['id'];
$wiadomosc = "";

$nazwa_usera_wykonujacego = "Użytkownik";
$u_res = $polaczenie->query("SELECT nazwa FROM uzytkownicy WHERE id='$user_id'");
if($u_res && $u_res->num_rows > 0) {
    $u_row = $u_res->fetch_assoc();
    $nazwa_usera_wykonujacego = $u_row['nazwa'];
}

// szybkie dodawanie znajomych z powiadomień
if(isset($_GET['nav_akcja']) && isset($_GET['nadawca_id']) && isset($_GET['pow_id'])) {
    $nav_akcja = $_GET['nav_akcja'];
    $nadawca_id = (int)$_GET['nadawca_id'];
    $pow_id = (int)$_GET['pow_id'];
    
    // oznaczenie powiadomieia jako przeczytane, przez podjęcie akcji
    $polaczenie->query("UPDATE powiadomienia SET czy_odczytane=1 WHERE id='$pow_id' AND uzytkownik_id='$user_id'");
    
    if($nav_akcja == 'akceptuj') {
        $check_inv = $polaczenie->query("SELECT id FROM znajomi WHERE zapraszajacy_id='$nadawca_id' AND zaproszony_id='$user_id' AND status_id=1");
        if($check_inv && $check_inv->num_rows > 0) {
            $inv_row = $check_inv->fetch_assoc();
            $rel_id = $inv_row['id'];
            $polaczenie->query("UPDATE znajomi SET status_id = 2 WHERE id = '$rel_id'");
            
            $tresc_powiadomienia = "zaakceptował Twoje zaproszenie do znajomych.";
            $polaczenie->query("INSERT INTO powiadomienia (uzytkownik_id, nadawca_id, typ, tresc) VALUES ('$nadawca_id', '$user_id', 'zaakceptowal', '$tresc_powiadomienia')");
            $wiadomosc = "<div class='notification is-success'>Zaakceptowano zaproszenie!</div>";
        }
    } elseif($nav_akcja == 'odrzuc') {
        $check_inv = $polaczenie->query("SELECT id FROM znajomi WHERE zapraszajacy_id='$nadawca_id' AND zaproszony_id='$user_id' AND status_id=1");
        if($check_inv && $check_inv->num_rows > 0) {
            $inv_row = $check_inv->fetch_assoc();
            $rel_id = $inv_row['id'];
            $polaczenie->query("DELETE FROM znajomi WHERE id = '$rel_id'");
            
            $tresc_powiadomienia = "odrzucił Twoje zaproszenie do znajomych.";
            $polaczenie->query("INSERT INTO powiadomienia (uzytkownik_id, nadawca_id, typ, tresc) VALUES ('$nadawca_id', '$user_id', 'odrzucil', '$tresc_powiadomienia')");
            $wiadomosc = "<div class='notification is-info'>Odrzucono zaproszenie.</div>";
        }
    }
}

// wysłanie zaproszenia
if(isset($_POST['ajax_dodaj']) && isset($_POST['odbiorca_id'])) {
    $odbiorca_id = (int)$_POST['odbiorca_id'];
    
    $check_rel = $polaczenie->query("SELECT id FROM znajomi WHERE (zapraszajacy_id = '$user_id' AND zaproszony_id = '$odbiorca_id') OR (zapraszajacy_id = '$odbiorca_id' AND zaproszony_id = '$user_id')");
        
    if($check_rel->num_rows == 0 && $odbiorca_id != $user_id) {
        $polaczenie->query("INSERT INTO znajomi (zapraszajacy_id, zaproszony_id, status_id) VALUES ('$user_id', '$odbiorca_id', 1)");
        
        $tresc_powiadomienia = "wysłał Ci zaproszenie do znajomych.";
        $polaczenie->query("INSERT INTO powiadomienia (uzytkownik_id, nadawca_id, typ, tresc) VALUES ('$odbiorca_id', '$user_id', 'zaproszenie', '$tresc_powiadomienia')");
    }
    exit("ok");
}

// odebranie zaproszenia przez powiadomienia
if(isset($_POST['akcja_zaproszenie']) && isset($_POST['relacja_id'])) {
    $relacja_id = (int)$_POST['relacja_id'];
    $akcja = $_POST['akcja_zaproszenie'];
    
    $check = $polaczenie->query("SELECT zapraszajacy_id FROM znajomi WHERE id = '$relacja_id' AND zaproszony_id = '$user_id' AND status_id = 1");
    
    if($check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $zapraszajacy_id = $row['zapraszajacy_id'];

        $polaczenie->query("UPDATE powiadomienia SET czy_odczytane=1 WHERE uzytkownik_id='$user_id' AND nadawca_id='$zapraszajacy_id' AND typ='zaproszenie'");

        if($akcja == 'akceptuj') {
            $polaczenie->query("UPDATE znajomi SET status_id = 2 WHERE id = '$relacja_id'");
            $wiadomosc = "<div class='notification is-success'>Zaproszenie zostało zaakceptowane!</div>";
            
            $tresc_powiadomienia = "zaakceptował Twoje zaproszenie do znajomych.";
            $polaczenie->query("INSERT INTO powiadomienia (uzytkownik_id, nadawca_id, typ, tresc) VALUES ('$zapraszajacy_id', '$user_id', 'zaakceptowal', '$tresc_powiadomienia')");
            
        } elseif($akcja == 'odrzuc') {
            $polaczenie->query("DELETE FROM znajomi WHERE id = '$relacja_id'");
            $wiadomosc = "<div class='notification is-info'>Zaproszenie zostało odrzucone.</div>";
            
            $tresc_powiadomienia = "odrzucił Twoje zaproszenie do znajomych.";
            $polaczenie->query("INSERT INTO powiadomienia (uzytkownik_id, nadawca_id, typ, tresc) VALUES ('$zapraszajacy_id', '$user_id', 'odrzucil', '$tresc_powiadomienia')");
        }
    }
}

// usuwanie znajomych
if(isset($_POST['usun_znajomego_id'])) {
    $usun_id = (int)$_POST['usun_znajomego_id'];
    $polaczenie->query("DELETE FROM znajomi WHERE (zapraszajacy_id = '$user_id' AND zaproszony_id = '$usun_id') OR (zapraszajacy_id = '$usun_id' AND zaproszony_id = '$user_id')");
    $wiadomosc = "<div class='notification is-warning'>Użytkownik został usunięty ze znajomych.</div>";
    
    $tresc_powiadomienia = "usunął Cię ze swojej listy znajomych.";
    $polaczenie->query("INSERT INTO powiadomienia (uzytkownik_id, nadawca_id, typ, tresc) VALUES ('$usun_id', '$user_id', 'usunal', '$tresc_powiadomienia')");
}

// anulowanie wysłanych zaproszeń
if(isset($_POST['anuluj_zaproszenie_id'])) {
    $rel_id = (int)$_POST['anuluj_zaproszenie_id'];
    
    $check_anuluj = $polaczenie->query("SELECT zaproszony_id FROM znajomi WHERE id = '$rel_id' AND zapraszajacy_id = '$user_id' AND status_id = 1");
    if($check_anuluj && $check_anuluj->num_rows > 0) {
        $wiersz = $check_anuluj->fetch_assoc();
        $odbiorca_id = $wiersz['zaproszony_id'];
        
        // usuwanie relacji z bazy
        $polaczenie->query("DELETE FROM znajomi WHERE id = '$rel_id'");
        
        // usunięcie powiadomienia o zaproszeniu z powiadomień
        $polaczenie->query("DELETE FROM powiadomienia WHERE uzytkownik_id = '$odbiorca_id' AND nadawca_id = '$user_id' AND typ = 'zaproszenie'");
        
        $wiadomosc = "<div class='notification is-warning is-light'>Wysłane zaproszenie zostało pomyślnie anulowane.</div>";
    }
}

// oczekujące zaproszenia do akceptacji
$oczekujace_res = $polaczenie->query("
    SELECT z.id as relacja_id, u.nazwa 
    FROM znajomi z 
    JOIN uzytkownicy u ON z.zapraszajacy_id = u.id 
    WHERE z.zaproszony_id = '$user_id' AND z.status_id = 1 AND u.czy_usuniety = 0
");

// wysłane zaproszenia czekające na akceptację
$wyslane_res = $polaczenie->query("
    SELECT z.id as relacja_id, u.id as uzytkownik_id, u.nazwa 
    FROM znajomi z 
    JOIN uzytkownicy u ON z.zaproszony_id = u.id 
    WHERE z.zapraszajacy_id = '$user_id' AND z.status_id = 1 AND u.czy_usuniety = 0
");

// moi znajomi
$znajomi_res = $polaczenie->query("
    SELECT u.id, u.nazwa 
    FROM znajomi z
    JOIN uzytkownicy u ON (z.zapraszajacy_id = u.id OR z.zaproszony_id = u.id)
    WHERE (z.zapraszajacy_id = '$user_id' OR z.zaproszony_id = '$user_id') 
    AND z.status_id = 2 
    AND u.id != '$user_id'
    AND u.czy_usuniety = 0
");

// wyszukiwanie nowych użytkowników do dodania
$szukani_uzytkownicy = [];
if(isset($_GET['szukaj']) && !empty(trim($_GET['szukaj']))) {
    $szukaj = $polaczenie->real_escape_string(trim($_GET['szukaj']));
    
    $search_sql = "
        SELECT id, nazwa 
        FROM uzytkownicy 
        WHERE nazwa LIKE '%$szukaj%' 
        AND id != '$user_id'
        AND czy_usuniety = 0
        AND id NOT IN (
            SELECT zaproszony_id FROM znajomi WHERE zapraszajacy_id = '$user_id'
            UNION
            SELECT zapraszajacy_id FROM znajomi WHERE zaproszony_id = '$user_id'
        )
        LIMIT 10
    ";
    $search_res = $polaczenie->query($search_sql);
    if($search_res) {
        while($r = $search_res->fetch_assoc()) {
            $szukani_uzytkownicy[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Znajomi - Quizzlando</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body class="has-background-light" style="min-height: 100vh;">

<?php include "navbar.php"; ?>

<section class="section">
    <div class="container">
        
        <h1 class="title">Znajomi</h1>
        <?= $wiadomosc ?>

        <div class="columns">
            
            <div class="column is-7">
                <?php if($oczekujace_res && $oczekujace_res->num_rows > 0): ?>
                <div class="box has-background-warning-light">
                    <h2 class="subtitle"><strong>Oczekujące zaproszenia</strong></h2>
                    <?php while($zaproszenie = $oczekujace_res->fetch_assoc()): ?>
                        <div class="level is-mobile box mb-2 px-3 py-2">
                            <div class="level-left">
                                <div class="level-item">
                                    <strong><?= htmlspecialchars($zaproszenie['nazwa']) ?></strong>&nbsp;chce zostać znajomym
                                </div>
                            </div>
                            <div class="level-right">
                                <div class="level-item">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="relacja_id" value="<?= $zaproszenie['relacja_id'] ?>">
                                        <button type="submit" name="akcja_zaproszenie" value="akceptuj" class="button is-success is-small mr-2">Akceptuj</button>
                                        <button type="submit" name="akcja_zaproszenie" value="odrzuc" class="button is-danger is-small is-outlined">Odrzuć</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>

                <div class="box">
                    <h2 class="subtitle"><strong>Twoi znajomi</strong></h2>
                    <?php if($znajomi_res && $znajomi_res->num_rows > 0): ?>
                        <table class="table is-fullwidth is-hoverable">
                            <tbody>
                            <?php while($znajomy = $znajomi_res->fetch_assoc()): ?>
                                <tr>
                                    <td class="is-vcentered">
                                        <a href="profil.php?id=<?= $znajomy['id'] ?>" class="has-text-dark" style="text-decoration: underline;">
                                            <strong><?= htmlspecialchars($znajomy['nazwa']) ?></strong>
                                        </a>
                                    </td>
                                    <td class="has-text-right">
                                        <form method="POST" onsubmit="return confirm('Czy na pewno chcesz usunąć tego użytkownika ze znajomych?');">
                                            <input type="hidden" name="usun_znajomego_id" value="<?= $znajomy['id'] ?>">
                                            <button type="submit" class="button is-danger is-outlined is-small">
                                                Usuń
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="has-text-grey">Nie masz jeszcze żadnych znajomych. Użyj wyszukiwarki po prawej stronie, aby ich dodać!</p>
                    <?php endif; ?>
                </div>

            </div>

            <div class="column is-5">
                
                <div class="box">
                    <h2 class="subtitle"><strong>Dodaj znajomego</strong></h2>
                    <form method="GET" action="znajomi.php" class="mb-4">
                        <div class="field has-addons">
                            <div class="control is-expanded">
                                <input class="input" type="text" name="szukaj" placeholder="Nazwa użytkownika..." value="<?= isset($_GET['szukaj']) ? htmlspecialchars($_GET['szukaj']) : '' ?>" required>
                            </div>
                            <div class="control">
                                <button type="submit" class="button is-primary">Szukaj</button>
                            </div>
                        </div>
                    </form>

                    <?php if(isset($_GET['szukaj'])): ?>
                        <?php if(count($szukani_uzytkownicy) > 0): ?>
                            <p class="mb-2">Wyniki wyszukiwania:</p>
                            <?php foreach($szukani_uzytkownicy as $su): ?>
                                <div class="level is-mobile box mb-2 px-3 py-2">
                                    <div class="level-left">
                                        <div class="level-item">
                                            <a href="profil.php?id=<?= $su['id'] ?>" class="has-text-dark"><strong><?= htmlspecialchars($su['nazwa']) ?></strong></a>
                                        </div>
                                    </div>
                                    <div class="level-right">
                                        <div class="level-item">
                                            <button type="button" class="button is-info is-small" onclick="wyslijZaproszenieTlo(this, <?= $su['id'] ?>)">Dodaj</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="has-text-danger">Nie znaleziono użytkowników lub zaproszenie już zostało wysłane.</p>
                        <?php endif; ?>
                        <br>
                        <a href="znajomi.php" class="button is-small is-light is-fullwidth">Wyczyść wyniki wyszukiwania</a>
                    <?php endif; ?>
                </div>
                <div class="box">
                    <h2 class="subtitle"><strong>Wysłane prośby</strong></h2>
                    <?php if($wyslane_res && $wyslane_res->num_rows > 0): ?>
                        <table class="table is-fullwidth is-hoverable">
                            <tbody>
                            <?php while($wyslane = $wyslane_res->fetch_assoc()): ?>
                                <tr>
                                    <td class="is-vcentered">
                                        <a href="profil.php?id=<?= $wyslane['uzytkownik_id'] ?>" class="has-text-dark" style="text-decoration: underline;">
                                            <strong><?= htmlspecialchars($wyslane['nazwa']) ?></strong>
                                        </a>
                                        <br><span class="is-size-7 has-text-grey">Oczekuje na akceptację...</span>
                                    </td>
                                    <td class="has-text-right">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="anuluj_zaproszenie_id" value="<?= $wyslane['relacja_id'] ?>">
                                            <button type="submit" class="button is-danger is-outlined is-small" onclick="return confirm('Czy na pewno chcesz anulować wysłane zaproszenie?');">Anuluj</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="has-text-grey">Nie masz żadnych wysłanych zaproszeń.</p>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>
</section>

<script>
function wyslijZaproszenieTlo(przycisk, odbiorcaId) {
    przycisk.classList.remove('is-info');
    przycisk.classList.add('is-success');
    przycisk.innerHTML = 'Wysłano';
    przycisk.disabled = true; 

    const formData = new FormData();
    formData.append('ajax_dodaj', '1');
    formData.append('odbiorca_id', odbiorcaId);

    fetch('znajomi.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {

        // auto odświeżenie strony
        setTimeout(() => {
            window.location.href = 'znajomi.php';
        }, 1000); 
    })
    .catch(error => {
        przycisk.innerHTML = 'Błąd!';
        przycisk.classList.replace('is-success', 'is-danger');
    });
}
</script>

<?php include "footer.php"; ?>

</body>
</html>