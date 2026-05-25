<?php
session_start();
require_once "polaczenie.php";

if(!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$my_id = $_SESSION['id'];
$profil_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($profil_id <= 0) {
    die("Nieprawidłowy profil.");
}

// pobranie danych użytkownika
$stmt = $polaczenie->prepare("
    SELECT u.nazwa, u.rola_id, u.czy_usuniety, r.nazwa AS ranga 
    FROM uzytkownicy u
    LEFT JOIN role r ON u.rola_id = r.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $profil_id);
$stmt->execute();
$wynik_user = $stmt->get_result();
if ($wynik_user->num_rows == 0) {
    die("Użytkownik nie istnieje.");
}
$user_data = $wynik_user->fetch_assoc();
$stmt->close();

// informacja o usuniętym koncie
if ($user_data['czy_usuniety'] == 1) {
    ?>
    <!DOCTYPE html>
    <html lang="pl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Konto usunięte</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    </head>
    <body class="has-background-light" style="min-height: 100vh; display: flex; flex-direction: column;">
        <?php include "navbar.php"; ?>
        <section class="section is-flex-grow-1 is-flex is-align-items-center is-justify-content-center">
            <div class="box has-text-centered p-6" style="max-width: 500px;">
                <h1 class="title is-4 has-text-danger">Użytkownik nie istnieje</h1>
                <p class="subtitle is-6 mt-3">Konto tego użytkownika zostało usunięte i nie figuruje już w systemie Quizzlando. Jego wyniki oraz statystyki zostały trwale ukryte.</p>
                <a href="index.php" class="button is-primary mt-4">Wróć na stronę główną</a>
            </div>
        </section>
        <?php include "footer.php"; ?>
    </body>
    </html>
    <?php
    exit(); // Przerywa dalsze ładowanie profilu
}

$komunikat = "";

// zaproszenia
if(isset($_POST['zapros_id']) && $_POST['zapros_id'] == $profil_id) {
    $limit_sql = $polaczenie->query("SELECT COUNT(*) as liczba FROM znajomi WHERE status_id=2 AND (zapraszajacy_id='$my_id' OR zaproszony_id='$my_id')");
    $limit = $limit_sql->fetch_assoc()['liczba'];

    if($limit >= 10) {
        $komunikat = "Masz już maksymalną liczbę 10 znajomych!";
    } else {
        $check = $polaczenie->query("SELECT * FROM znajomi WHERE (zapraszajacy_id='$my_id' AND zaproszony_id='$profil_id') OR (zapraszajacy_id='$profil_id' AND zaproszony_id='$my_id')");
        if($check->num_rows > 0) {
            $komunikat = "Już istnieje zaproszenie lub jesteście znajomymi.";
        } else {
            $polaczenie->query("INSERT INTO znajomi (zapraszajacy_id, zaproszony_id, status_id, data_utworzenia) VALUES ('$my_id', '$profil_id', 1, NOW())");
            $komunikat = "Zaproszenie wysłane!";
        }
    }
}

if(isset($_POST['usun_id']) && $_POST['usun_id'] == $profil_id) {
    $polaczenie->query("DELETE FROM znajomi WHERE (zapraszajacy_id='$my_id' AND zaproszony_id='$profil_id' AND status_id=2) OR (zapraszajacy_id='$profil_id' AND zaproszony_id='$my_id' AND status_id=2)");
    $komunikat = "Znajomy został usunięty.";
}

if(isset($_POST['akceptuj_id']) && $_POST['akceptuj_id'] == $profil_id) {
    $polaczenie->query("UPDATE znajomi SET status_id=2 WHERE zapraszajacy_id='$profil_id' AND zaproszony_id='$my_id' AND status_id=1");
    $komunikat = "Zaproszenie zaakceptowane!";
}

if(isset($_POST['anuluj_id']) && $_POST['anuluj_id'] == $profil_id) {
    $polaczenie->query("DELETE FROM znajomi WHERE zapraszajacy_id='$my_id' AND zaproszony_id='$profil_id' AND status_id=1");
    $komunikat = "Zaproszenie anulowane.";
}

// status znajomości uż. z profilem
$status_znajomosci = "brak";
if ($my_id != $profil_id) {
    $check_z = $polaczenie->query("SELECT * FROM znajomi WHERE (zapraszajacy_id='$my_id' AND zaproszony_id='$profil_id') OR (zapraszajacy_id='$profil_id' AND zaproszony_id='$my_id')");
    if($w = $check_z->fetch_assoc()) {
        if ($w['status_id'] == 2) {
            $status_znajomosci = "znajomi";
        } else {
            if ($w['zapraszajacy_id'] == $my_id) {
                $status_znajomosci = "wyslane";
            } else {
                $status_znajomosci = "otrzymane";
            }
        }
    }
}

// top 10 najlepszych prób quizów użytkownika
$top_quizy_stmt = $polaczenie->prepare("
    SELECT q.tytul, q.id as quiz_id, p.wynik, p.liczba_pytan, p.czas_trwania
    FROM proby_quizu p
    JOIN quizy q ON p.quiz_id = q.id
    WHERE p.uzytkownik_id = ?
      AND p.id = (
          SELECT p2.id FROM proby_quizu p2
          WHERE p2.uzytkownik_id = p.uzytkownik_id AND p2.quiz_id = p.quiz_id
          ORDER BY p2.wynik DESC, p2.czas_trwania ASC, p2.id ASC
          LIMIT 1
      )
    ORDER BY p.wynik DESC, p.czas_trwania ASC
    LIMIT 10
");
$top_quizy_stmt->bind_param("i", $profil_id);
$top_quizy_stmt->execute();
$top_quizy = $top_quizy_stmt->get_result();

// znajomi użytkownika
$znajomi_stmt = $polaczenie->prepare("
    SELECT u.id, u.nazwa
    FROM znajomi z
    JOIN uzytkownicy u ON u.id = CASE 
                                    WHEN z.zapraszajacy_id=? THEN z.zaproszony_id
                                    ELSE z.zapraszajacy_id
                                  END
    WHERE (z.zapraszajacy_id=? OR z.zaproszony_id=?) AND z.status_id=2
");
$znajomi_stmt->bind_param("iii", $profil_id, $profil_id, $profil_id);
$znajomi_stmt->execute();
$znajomi_wynik = $znajomi_stmt->get_result();

// statystyki quizów użytkownika
$stworzone_quizy_stmt = $polaczenie->prepare("
    SELECT id, tytul, data_utworzenia
    FROM quizy
    WHERE autor_id = ? AND czy_usuniety = 0
    ORDER BY data_utworzenia DESC
");
$stworzone_quizy_stmt->bind_param("i", $profil_id);
$stworzone_quizy_stmt->execute();
$stworzone_quizy_wynik = $stworzone_quizy_stmt->get_result();
$ilosc_stworzonych_quizow = $stworzone_quizy_wynik->num_rows;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil: <?= htmlspecialchars($user_data['nazwa']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .word-break-all {
            word-wrap: break-word;
            word-break: break-all;
            hyphens: auto;
        }
    </style>
</head>
<body class="has-background-light" style="min-height: 100vh;">
    
<?php include "navbar.php"; ?>

<section class="section is-small-mobile">
    <div class="container">
        <?php if($komunikat): ?>
            <div class="notification is-info is-light">
                <button class="delete" onclick="this.parentElement.style.display='none';"></button>
                <?= $komunikat ?>
            </div>
        <?php endif; ?>
        
        <div class="columns is-multiline">
            <div class="column is-4">
                
                <div class="box has-text-centered">
                    <figure class="image is-128x128 is-inline-block mb-4">
                        <img class="is-rounded" src="https://ui-avatars.com/api/?name=<?= urlencode($user_data['nazwa']) ?>&size=128&background=random" alt="Avatar">
                    </figure>
                    
                    <h1 class="title is-3 mb-1 word-break-all"><?= htmlspecialchars($user_data['nazwa']) ?></h1>
                    
                    <?php if(!empty($user_data['ranga'])): ?>
                        <?php 
                            $tag_color = "is-link";
                            if($user_data['rola_id'] == 1) $tag_color = "is-danger"; 
                            elseif($user_data['rola_id'] == 2) $tag_color = "is-warning";
                        ?>
                        <span class="tag <?= $tag_color ?> is-medium mb-3">
                            <i class="fas fa-star mr-2"></i> <?= htmlspecialchars($user_data['ranga']) ?>
                        </span>
                    <?php endif; ?>
                    
                    <?php if ($my_id != $profil_id): ?>
                        <div class="mt-4">
                            <?php if($status_znajomosci == 'brak'): ?>
                                <form method="post">
                                    <input type="hidden" name="zapros_id" value="<?= $profil_id ?>">
                                    <button class="button is-link is-fullwidth"><i class="fas fa-user-plus mr-2"></i> Dodaj do znajomych</button>
                                </form>
                            <?php elseif($status_znajomosci == 'znajomi'): ?>
                                <span class="tag is-success is-medium mb-2 is-fullwidth"><i class="fas fa-user-check mr-2"></i> Jesteście znajomymi</span>
                                <form method="post">
                                    <input type="hidden" name="usun_id" value="<?= $profil_id ?>">
                                    <button class="button is-danger is-small is-fullwidth mt-2"><i class="fas fa-trash mr-2"></i> Usuń ze znajomych</button>
                                </form>
                            <?php elseif($status_znajomosci == 'wyslane'): ?>
                                <span class="tag is-warning is-medium mb-2 is-fullwidth"><i class="fas fa-clock mr-2"></i> Zaproszenie wysłane</span>
                                <form method="post">
                                    <input type="hidden" name="anuluj_id" value="<?= $profil_id ?>">
                                    <button class="button is-danger is-small is-fullwidth mt-2"><i class="fas fa-times mr-2"></i> Anuluj zaproszenie</button>
                                </form>
                            <?php elseif($status_znajomosci == 'otrzymane'): ?>
                                <form method="post" class="mb-2">
                                    <input type="hidden" name="akceptuj_id" value="<?= $profil_id ?>">
                                    <button class="button is-success is-fullwidth"><i class="fas fa-check mr-2"></i> Akceptuj zaproszenie</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="mt-4">
                            <span class="tag is-info is-medium is-light is-fullwidth">To jest Twój profil</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="box">
                    <h3 class="title is-5 is-size-6-mobile"><i class="fas fa-users mr-2 has-text-link"></i> Znajomi (<?= $znajomi_wynik->num_rows ?>/10)</h3>
                    <?php if($znajomi_wynik->num_rows > 0): ?>
                        <ul style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php while($z = $znajomi_wynik->fetch_assoc()): ?>
                            <li>
                                <a href="profil.php?id=<?= $z['id'] ?>" class="has-text-dark is-size-5 is-size-6-mobile is-flex is-align-items-center">
                                    <span class="icon is-small mr-2"><i class="fas fa-user-circle"></i></span>
                                    <strong class="word-break-all"><?= htmlspecialchars($z['nazwa']) ?></strong>
                                </a>
                            </li>
                        <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="has-text-grey is-size-7-mobile">Użytkownik nie ma jeszcze żadnych znajomych.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="column is-8">
                
                <div class="box mb-5">
                    <h2 class="title is-4 is-size-5-mobile"><i class="fas fa-pencil-alt mr-2 has-text-info"></i> Stworzone quizy (<?= $ilosc_stworzonych_quizow ?>)</h2>
                    <?php if($ilosc_stworzonych_quizow > 0): ?>
                        <div class="table-container" style="max-height: 300px; overflow-y: auto;">
                            <table class="table is-fullwidth is-striped is-hoverable is-size-7-mobile">
                                <thead>
                                    <tr>
                                        <th>Nazwa quizu</th>
                                        <th>Data utworzenia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($sq = $stworzone_quizy_wynik->fetch_assoc()): ?>
                                        <tr>
                                            <td class="word-break-all"><strong><a href="quiz_gra.php?id=<?= $sq['id'] ?>" class="has-text-link"><?= htmlspecialchars($sq['tytul']) ?></a></strong></td>
                                            <td><?= date('d.m.Y', strtotime($sq['data_utworzenia'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="has-text-grey is-size-7-mobile">Użytkownik nie stworzył jeszcze żadnych quizów.</p>
                    <?php endif; ?>
                </div>

                <div class="box">
                    <h2 class="title is-4 is-size-5-mobile"><i class="fas fa-trophy mr-2 has-text-warning"></i> TOP 10 najlepszych gier gracza</h2>
                    <?php if($top_quizy->num_rows > 0): ?>
                        <div class="table-container">
                            <table class="table is-fullwidth is-striped is-hoverable is-size-7-mobile">
                                <thead>
                                    <tr>
                                        <th>Nazwa quizu</th>
                                        <th>Najlepszy wynik</th>
                                        <th>Najlepszy czas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($tq = $top_quizy->fetch_assoc()): ?>
                                        <tr>
                                            <td class="word-break-all"><strong><a href="quiz_gra.php?id=<?= $tq['quiz_id'] ?>" class="has-text-link"><?= htmlspecialchars($tq['tytul']) ?></a></strong></td>
                                            <td>
                                                <span class="tag is-success is-normal-mobile is-medium"><?= $tq['wynik'] ?>/<?= $tq['liczba_pytan'] ?> pkt</span>
                                            </td>
                                            <td><?= $tq['czas_trwania'] ?> sek.</td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="has-text-grey is-size-7-mobile">Użytkownik nie rozwiązał jeszcze żadnych quizów.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include "footer.php"; ?>

</body>
</html>