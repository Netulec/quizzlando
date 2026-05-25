<?php
session_start();
require_once "polaczenie.php";

if(!isset($_SESSION['id'])) {
    header("Location: logowanie.php");
    exit();
}

$quiz_id = intval($_GET['id']);
if($quiz_id <= 0) die("Nieprawidłowy quiz.");

// wyjście z quizu
if (isset($_GET['abort'])) {
    unset($_SESSION['quiz']); 
    header("Location: index.php"); 
    exit();
}

// ocena quizu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ocena'])) {
    $ocena = intval($_POST['ocena']);
    $uzytkownik_id = $_SESSION['id'];

    if ($ocena >= 1 && $ocena <= 5) {
        $spr = $polaczenie->prepare("SELECT id FROM oceny_quizu WHERE quiz_id=? AND uzytkownik_id=?");
        $spr->bind_param("ii", $quiz_id, $uzytkownik_id);
        $spr->execute();
        $wynik_spr = $spr->get_result();
        
        if ($wynik_spr->num_rows == 0) {
            $ins = $polaczenie->prepare("INSERT INTO oceny_quizu (quiz_id, uzytkownik_id, ocena, data_oceny) VALUES (?, ?, ?, NOW())");
            $ins->bind_param("iii", $quiz_id, $uzytkownik_id, $ocena);
            $ins->execute();
            $ins->close();
        } else {
            $upd = $polaczenie->prepare("UPDATE oceny_quizu SET ocena=?, data_oceny=NOW() WHERE quiz_id=? AND uzytkownik_id=?");
            $upd->bind_param("iii", $ocena, $quiz_id, $uzytkownik_id);
            $upd->execute();
            $upd->close();
        }
        $spr->close();
        
        $_SESSION['ocena_sukces'] = true;
    }
    
    header("Location: quiz_gra.php?id=" . $quiz_id . "&status=complete");
    exit();
}

// pobranie danych o quizie
$sql = "SELECT * FROM quizy WHERE id=$quiz_id AND czy_usuniety=0";
$wynik_quiz = $polaczenie->query($sql);
if($wynik_quiz->num_rows == 0) die("Quiz nie istnieje.");
$quiz = $wynik_quiz->fetch_assoc();

if($quiz['czy_premium'] == 1) {
    $u_id = $_SESSION['id'];
    $rola_stmt = $polaczenie->prepare("SELECT rola_id FROM uzytkownicy WHERE id = ?");
    $rola_stmt->bind_param("i", $u_id);
    $rola_stmt->execute();
    $rola_res = $rola_stmt->get_result()->fetch_assoc();
    $aktualna_rola = $rola_res['rola_id'];
    $rola_stmt->close();
    
    if(!in_array($aktualna_rola, [2, 3])) {
        die("Ten quiz jest dostępny tylko dla użytkowników premium.");
    }
}

// start rozwiązywania quizu
if((!isset($_SESSION['quiz']) || $_SESSION['quiz']['id'] !== $quiz_id) && !isset($_GET['status'])) {
    unset($_SESSION['quiz']); 

    $sql = "SELECT * FROM pytania WHERE id_quizu=$quiz_id ORDER BY RAND()";
    $wynik_pytan = $polaczenie->query($sql);

    $pytania = [];
    while($row = $wynik_pytan->fetch_assoc()) {
        $row['odpowiedzi'] = []; 
        $pytania[$row['id']] = $row;
    }

    if (empty($pytania)) {
        ?>
        <!DOCTYPE html>
        <html lang="pl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Błąd quizu</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
        </head>
        <body class="has-background-light" style="min-height:100vh;display:flex;align-items:center;">
            <div class="container px-3 has-text-centered">
                <div class="box">
                    <h1 class="title is-4-mobile is-3-tablet has-text-danger">Ten quiz ma za mało pytań</h1>
                    <p class="mb-4">Spróbuj wybrać inny quiz.</p>
                    <a href="index.php" class="button is-primary">Powrót do menu</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit();
    }

    if(!empty($pytania)) {
        $ids = implode(",", array_keys($pytania));
        $sql = "SELECT * FROM odpowiedzi WHERE pytanie_id IN ($ids)";
        $wynik_odp = $polaczenie->query($sql);

        while($row = $wynik_odp->fetch_assoc()) {
            $lista_odp = [
                ['litera'=>'A','tresc'=>$row['odpowiedz_a']],
                ['litera'=>'B','tresc'=>$row['odpowiedz_b']],
                ['litera'=>'C','tresc'=>$row['odpowiedz_c']],
                ['litera'=>'D','tresc'=>$row['odpowiedz_d']],
            ];
            $lista_odp = array_filter($lista_odp, fn($o)=>!empty($o['tresc']));
            $pytania[$row['pytanie_id']]['odpowiedzi'] = $lista_odp;
            $pytania[$row['pytanie_id']]['poprawna'] = strtoupper($row['poprawna']);
        }
    }

    $_SESSION['quiz'] = [
        'id' => $quiz_id,
        'start' => time(),
        'pytania' => array_values($pytania),
        'current' => 0,
        'score' => 0,
        'summary' => []
    ];
}

// odpowiedź na pytanie
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['odpowiedz'])) {
    $pytanie = $_SESSION['quiz']['pytania'][$_SESSION['quiz']['current']];
    $wybrana = $_POST['odpowiedz'] ?? '';

    $czy_poprawnie = false;
    if($wybrana && $wybrana === $pytanie['poprawna']){
        $_SESSION['quiz']['score']++;
        $czy_poprawnie = true;
    }

    $_SESSION['quiz']['summary'][] = [
        'tresc' => $pytanie['tresc'],
        'odpowiedzi' => $pytanie['odpowiedzi'],
        'twoja_odpowiedz' => $wybrana,
        'poprawna_odpowiedz' => $pytanie['poprawna'],
        'czy_poprawnie' => $czy_poprawnie
    ];

    $_SESSION['quiz']['current']++;

    if($_SESSION['quiz']['current'] >= count($_SESSION['quiz']['pytania'])) {
        $czas_trwania = time() - $_SESSION['quiz']['start'];
        $score = $_SESSION['quiz']['score'];
        $liczba_pytan = count($_SESSION['quiz']['pytania']);
        $uzytkownik_id = $_SESSION['id'];

        $stmt = $polaczenie->prepare("
            INSERT INTO proby_quizu 
            (quiz_id,uzytkownik_id,wynik,liczba_pytan,czas_trwania,data_rozpoczecia) 
            VALUES (?,?,?,?,?,NOW())
        ");
        $stmt->bind_param("iiiis", $quiz_id, $uzytkownik_id, $score, $liczba_pytan, $czas_trwania);
        $stmt->execute();
        $stmt->close();

        $_SESSION['last_result'] = [
            'score' => $score,
            'total' => $liczba_pytan,
            'time' => $czas_trwania,
            'summary' => $_SESSION['quiz']['summary']
        ];
        
        unset($_SESSION['quiz']);
        
        header("Location: quiz_gra.php?id=" . $quiz_id . "&status=complete");
        exit();
    }

    header("Location: quiz_gra.php?id=" . $quiz_id);
    exit();
}

// ekran końcowy
if (isset($_GET['status']) && $_GET['status'] === 'complete' && isset($_SESSION['last_result'])) {
    $rezultat_koncowy = $_SESSION['last_result'];
    
    $pokaz_sukces_oceny = false;
    if(isset($_SESSION['ocena_sukces'])) {
        $pokaz_sukces_oceny = true;
        unset($_SESSION['ocena_sukces']); 
    }
    
    $u_id = $_SESSION['id'];
    $check_ocena = $polaczenie->prepare("SELECT ocena FROM oceny_quizu WHERE quiz_id=? AND uzytkownik_id=?");
    $check_ocena->bind_param("ii", $quiz_id, $u_id);
    $check_ocena->execute();
    $wynik_oceny = $check_ocena->get_result();
    
    $aktualna_ocena = null;
    if ($wynik_oceny->num_rows > 0) {
        $wiersz = $wynik_oceny->fetch_assoc();
        $aktualna_ocena = $wiersz['ocena'];
    }
    $check_ocena->close();
    
    $procent = $rezultat_koncowy['total'] > 0 ? round(($rezultat_koncowy['score'] / $rezultat_koncowy['total']) * 100) : 0;
    
    // ranking i podsumowanie
    
    // najlepszy wynik
    $best_stmt = $polaczenie->prepare("SELECT wynik, liczba_pytan, czas_trwania, data_rozpoczecia FROM proby_quizu WHERE quiz_id = ? AND uzytkownik_id = ? ORDER BY wynik DESC, czas_trwania ASC, id ASC LIMIT 1");
    $best_stmt->bind_param("ii", $quiz_id, $u_id);
    $best_stmt->execute();
    $best_res = $best_stmt->get_result();
    $best_user = $best_res->fetch_assoc();
    $best_stmt->close();

    // top 10 graczy
    $top_stmt = $polaczenie->prepare("
        SELECT p.wynik, p.liczba_pytan, p.czas_trwania, u.nazwa, u.id as user_id 
        FROM proby_quizu p
        JOIN uzytkownicy u ON p.uzytkownik_id = u.id
        WHERE p.quiz_id = ?
          AND p.id = (
              SELECT p2.id FROM proby_quizu p2
              WHERE p2.uzytkownik_id = p.uzytkownik_id AND p2.quiz_id = p.quiz_id
              ORDER BY p2.wynik DESC, p2.czas_trwania ASC, p2.id ASC
              LIMIT 1
          )
        ORDER BY p.wynik DESC, p.czas_trwania ASC
        LIMIT 10
    ");
    $top_stmt->bind_param("i", $quiz_id);
    $top_stmt->execute();
    $top_res = $top_stmt->get_result();

    ?>
    <!DOCTYPE html>
    <html lang="pl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Koniec Quizu</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            .word-break-all { word-break: break-word; }

			.quiz-wrapper {
    			width: 80%;
    			max-width: 1400px;
    			min-width: 1100px;
    			margin: 0 auto;
    			position: relative;
			}

			.quiz-box {
    			width: 100%;
    			min-height: 650px;
    			border-radius: 22px;
    			padding: 2rem;
			}

			.question-title {
    			font-size: 2rem;
    			line-height: 1.4;
    			min-height: 120px;
    			display: flex;
    			align-items: center;
		    	justify-content: center;
			}

			.answer-box {
    			min-height: 90px;
    			display: flex;
    			align-items: center;
    			border-radius: 16px;
    			font-size: 1.15rem;
			}

			.pytanie-img {
			    width: 100%;
			    max-height: 420px;
			    object-fit: contain;
			    border-radius: 16px;
			    background: #fff;
			    padding: 1rem;
			}

			@media screen and (max-width: 1200px) {

    			.quiz-wrapper {
    		    	width: 95%;
    		    	min-width: unset;
    			}

    			.quiz-box {
    		    	min-height: auto;
    		    	padding: 1.25rem;
    			}

    			.question-title {
    		    	font-size: 1.35rem;
    		    	min-height: auto;
    			}

    			.answer-box {
        			min-height: 75px;
        			font-size: 1rem;
    			}

    			.pytanie-img {
        			max-height: 260px;
    			}
			}

        </style>
    </head>
    <body class="has-background-light" style="min-height: 100vh;">
        <?php include_once "navbar.php"; ?>
        
<!-- zgłoszenie quizu -->

        <div id="report-modal" class="modal px-3">
            <div class="modal-background" onclick="closeReportModal()"></div>
            <div class="modal-card">
                <header class="modal-card-head">
                    <p class="modal-card-title is-size-5-mobile">Zgłoś ten quiz</p>
                    <button class="delete" aria-label="close" onclick="closeReportModal()"></button>
                </header>
                <section class="modal-card-body">
                    <form id="reportForm" action="zglos.php" method="POST">
                        <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">
                        
                        <div class="field">
                            <label class="label">Kategoria powoda</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="kategoria" required>
                                        <option value="" disabled selected>Wybierz kategorię...</option>
                                        <option value="Błędne odpowiedzi">Błędne odpowiedzi w pytaniach</option>
                                        <option value="Nieodpowiednie treści">Nieodpowiednie treści / Język</option>
                                        <option value="Spam / Niska jakość">Spam / Niska jakość</option>
                                        <option value="Inne">Inne powody</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="field">
                            <label class="label">Opis szczegółowy</label>
                            <div class="control">
                                <textarea class="textarea" name="opis" placeholder="Opisz krótko, co jest nie tak z tym quizem..." required></textarea>
                            </div>
                        </div>
                    </form>
                </section>
                <footer class="modal-card-foot is-flex-wrap-wrap" style="gap: 10px;">
                    <button type="submit" form="reportForm" class="button is-danger">Wyślij zgłoszenie</button>
                    <button class="button" onclick="closeReportModal()">Anuluj</button>
                </footer>
            </div>
        </div>

        <script>
            function openReportModal() { document.getElementById('report-modal').classList.add('is-active'); }
            function closeReportModal() { document.getElementById('report-modal').classList.remove('is-active'); }
        </script>
        <div class="container px-3" style="position: relative; padding-top: 1.5rem; padding-bottom: 1.5rem;">
            
<!-- koniec quizu info -->

            <div class="columns is-centered">
                <div class="column is-10-tablet is-8-desktop">
                    <div class="box has-text-centered">
                        <h1 class="title is-3-mobile is-2-tablet mb-3">Gratulacje!</h1>
                        
                        <p class="has-text-weight-semibold is-size-6-mobile is-size-5-tablet mb-5 has-text-grey">
                            Ukończono z wynikiem <?= $procent ?>%
                        </p>
                        
                        <div class="notification is-primary is-light mb-5 p-4">
                            <p class="heading">Twój wynik</p>
                            <p class="title is-2-mobile is-1-tablet"><?= $rezultat_koncowy['score'] ?> / <?= $rezultat_koncowy['total'] ?></p>
                        </div>
                        <p class="subtitle is-6-mobile is-5-tablet mb-5">Czas gry: <strong><?= $rezultat_koncowy['time'] ?></strong> sekund</p>
                        
                        <hr>
                        
                        <?php if($pokaz_sukces_oceny): ?>
                            <div class="notification is-success is-light subtitle is-6 has-text-weight-semibold mb-4">
                                Dziękujemy! Twoja ocena została zapisana.
                            </div>
                        <?php endif; ?>

                        <?php if($aktualna_ocena): ?>
                            <p class="subtitle is-6 mb-3 has-text-weight-bold">Twoja obecna ocena to <?= $aktualna_ocena ?> ⭐. Chcesz ją zmienić?</p>
                        <?php else: ?>
                            <p class="subtitle is-6 mb-3 has-text-weight-bold">Jak oceniasz ten quiz?</p>
                        <?php endif; ?>
                        
                        <form method="POST" action="?id=<?= $quiz_id ?>">
                            <div class="buttons is-centered is-flex-wrap-wrap mb-4">
                                <button type="submit" name="ocena" value="1" class="button <?= $aktualna_ocena == 1 ? 'is-danger' : 'is-danger is-light' ?>">1 ⭐</button>
                                <button type="submit" name="ocena" value="2" class="button <?= $aktualna_ocena == 2 ? 'is-warning' : 'is-warning is-light' ?>">2 ⭐</button>
                                <button type="submit" name="ocena" value="3" class="button <?= $aktualna_ocena == 3 ? 'is-info' : 'is-info is-light' ?>">3 ⭐</button>
                                <button type="submit" name="ocena" value="4" class="button <?= $aktualna_ocena == 4 ? 'is-link' : 'is-link is-light' ?>">4 ⭐</button>
                                <button type="submit" name="ocena" value="5" class="button <?= $aktualna_ocena == 5 ? 'is-success' : 'is-success is-light' ?>">5 ⭐</button>
                            </div>
                        </form>
                        
                        <div class="px-3-mobile px-5-tablet">
                            <a href='index.php' class='button is-primary is-medium is-fullwidth mb-5' onclick="<?php unset($_SESSION['last_result']); ?>">Powrót do menu</a>
                        </div>
                        
                        <hr>

                        <div class="columns mb-5 mt-2">
                            <div class="column is-12 has-text-left">
                                <h3 class="title is-5-mobile is-4-tablet mb-4"><i class="fas fa-medal has-text-warning mr-2"></i> Ranking quizu</h3>
                                
                                <?php if($best_user): ?>
                                    <div class="notification is-info is-light mb-4 p-3">
                                        <p class="mb-1 is-size-7-mobile is-size-6-tablet"><strong>Twój najlepszy wynik:</strong></p>
                                        <p class="is-size-7-mobile is-size-6-tablet">
                                            <span class="tag is-info"><?= $best_user['wynik'] ?>/<?= $best_user['liczba_pytan'] ?> pkt</span> w <?= $best_user['czas_trwania'] ?> sek. <br>
                                            <small class="has-text-grey">(<?= date('d.m.Y H:i', strtotime($best_user['data_rozpoczecia'])) ?>)</small>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="box p-0" style="overflow-x: auto; border-radius: 8px;">
                                    <table class="table is-fullwidth is-striped is-hoverable mb-0 is-size-7-mobile">
                                        <thead>
                                            <tr>
                                                <th>Msc</th>
                                                <th>Gracz</th>
                                                <th>Punkty</th>
                                                <th>Czas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $miejsce = 1;
                                            while($top = $top_res->fetch_assoc()): 
                                                $is_me = ($top['user_id'] == $u_id);
                                            ?>
                                            <tr <?= $is_me ? 'class="is-selected"' : '' ?>>
                                                <td class="has-text-centered"><strong>#<?= $miejsce++ ?></strong></td>
                                                <td class="word-break-all"><a href="profil.php?id=<?= $top['user_id'] ?>" class="<?= $is_me ? 'has-text-white' : 'has-text-link' ?> has-text-weight-bold"><?= htmlspecialchars($top['nazwa']) ?></a></td>
                                                <td><span class="tag is-small <?= $is_me ? 'is-white' : 'is-success' ?>"><?= $top['wynik'] ?>/<?= $top['liczba_pytan'] ?></span></td>
                                                <td><?= $top['czas_trwania'] ?>s</td>
                                            </tr>
                                            <?php endwhile; ?>
                                            <?php if($top_res->num_rows == 0): ?>
                                                <tr><td colspan="4" class="has-text-centered has-text-grey">Brak wyników do wyświetlenia.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <!-- podsumowanie odpowiedzi -->
                        <h3 class="title is-5-mobile is-4-tablet mb-4">Szczegółowe podsumowanie</h3>
                        <div class="has-text-left mb-4">
                            <?php foreach($rezultat_koncowy['summary'] as $i => $p): ?>
                            <div class="box mb-4 p-4-mobile <?= $p['czy_poprawnie'] ? 'has-background-success-light' : 'has-background-danger-light' ?>" style="border-radius: 12px; box-shadow: none;">
                                
                                <h4 class="title is-6-mobile is-5-tablet mb-3 word-break-all"><?= ($i+1) ?>. <?= htmlspecialchars($p['tresc']) ?></h4>
                                
                                <div class="columns is-multiline is-size-7-mobile is-size-6-tablet mt-1 mb-2">
                                    <?php foreach($p['odpowiedzi'] as $odp): ?>
                                    <div class="column is-12-mobile is-6-tablet py-1 <?= $p['poprawna_odpowiedz'] === $odp['litera'] ? 'has-text-weight-bold has-text-success' : '' ?>">
                                        <strong><?= $odp['litera'] ?>:</strong> <span class="word-break-all"><?= htmlspecialchars($odp['tresc']) ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-3 pt-3 is-size-7-mobile is-size-6-tablet" style="border-top: 1px solid rgba(0,0,0,0.1);">
                                    <div class="is-flex is-align-items-center is-flex-wrap-wrap" style="gap: 0.5rem 1rem;">
                                        <div>
                                            <span class="has-text-weight-bold">Twoja odp: </span> 
                                            <?php if($p['twoja_odpowiedz']): ?>
                                                <span class="tag <?= $p['czy_poprawnie'] ? 'is-success' : 'is-danger' ?> has-text-weight-bold">
                                                    <?= $p['twoja_odpowiedz'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="tag is-warning">Brak</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if(!$p['czy_poprawnie']): ?>
                                        <div>
                                            <span class="has-text-weight-bold">Poprawna: </span> 
                                            <span class="tag is-success has-text-weight-bold"><?= $p['poprawna_odpowiedz'] ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

if (!isset($_SESSION['quiz'])) {
    header("Location: index.php");
    exit();
}

// widok pytania
$pytanie = $_SESSION['quiz']['pytania'][$_SESSION['quiz']['current']];
$current_num = $_SESSION['quiz']['current'] + 1;
$total = count($_SESSION['quiz']['pytania']);
$procent_postepu = ($current_num / $total) * 100;
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz: <?= htmlspecialchars($quiz['tytul']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body { background-color: #f5f5f5; min-height: 100vh; }
        .pytanie-img { max-width: 100%; max-height: 350px; object-fit: contain; border-radius: 8px; }
        @media screen and (max-width: 768px) {
            .pytanie-img { max-height: 250px; }
        }
        .answer-label input[type="radio"] { display: none; }
        .answer-label { display: block; cursor: pointer; transition: all 0.2s ease-in-out; }
        .answer-box { border: 2px solid transparent; transition: all 0.2s ease-in-out; word-break: break-word; } 
        .answer-label:hover .answer-box { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .answer-label input[type="radio"]:checked + .answer-box { border-color: #00d1b2; background-color: #ebfffc; color: #00947e; }
        .answer-label input[type="radio"]:checked + .answer-box strong { color: #00947e; }
        
		.report-flag {
    		position: fixed;
    		top: 110px;
    		right: 80px;
    		z-index: 9999;
    		font-size: 2rem;
    		color: #ffdd57;
    		transition: transform 0.2s ease;
		}
		.report-flag:hover {
		    transform: scale(1.12);
    		color: #ffb70f;
		}

    </style>
</head>

<body>
    <?php include_once "navbar.php"; ?>
    
    <!-- wyjście z quizu -->
    <div id="custom-modal" class="modal px-3">
      <div class="modal-background" onclick="closeModal()"></div>
      <div class="modal-card">
        <header class="modal-card-head">
          <p class="modal-card-title is-size-5-mobile">Potwierdzenie wyjścia</p>
          <button class="delete" aria-label="close" onclick="closeModal()"></button>
        </header>
        <section class="modal-card-body is-size-6-mobile">
          Czy na pewno chcesz przerwać ten quiz i wrócić do menu? Cały Twój obecny postęp zostanie bezpowrotnie usunięty.
        </section>
        <footer class="modal-card-foot is-flex-wrap-wrap" style="gap: 10px;">
          <a href="?id=<?= $quiz_id ?>&abort=1" class="button is-danger">Tak, wyjdź</a>
          <button class="button is-success" onclick="closeModal()">Anuluj</button>
        </footer>
      </div>
    </div>

    <!-- flaga do zgłaszania quizu -->
    <div id="report-modal-game" class="modal px-3">
        <div class="modal-background" onclick="closeReportModalGame()"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title is-size-5-mobile">Zgłoś ten quiz</p>
                <button class="delete" aria-label="close" onclick="closeReportModalGame()"></button>
            </header>
            <section class="modal-card-body">
                <form id="reportFormGame" action="zglos.php" method="POST">
                    <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">
                    
                    <div class="field">
                        <label class="label">Kategoria powoda</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="kategoria" required>
                                    <option value="" disabled selected>Wybierz kategorię...</option>
                                    <option value="Błędne odpowiedzi">Błędne odpowiedzi w pytaniach</option>
                                    <option value="Nieodpowiednie treści">Nieodpowiednie treści / Język</option>
                                    <option value="Spam / Niska jakość">Spam / Niska jakość</option>
                                    <option value="Inne">Inne powody</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Opis szczegółowy</label>
                        <div class="control">
                            <textarea class="textarea" name="opis" placeholder="Opisz krótko, co jest nie tak z tym quizem..." required></textarea>
                        </div>
                    </div>
                </form>
            </section>
            <footer class="modal-card-foot is-flex-wrap-wrap" style="gap: 10px;">
                <button type="submit" form="reportFormGame" class="button is-danger">Wyślij zgłoszenie</button>
                <button type="button" class="button" onclick="closeReportModalGame()">Anuluj</button>
            </footer>
        </div>
    </div>

    <script>
      function openModal() { document.getElementById('custom-modal').classList.add('is-active'); }
      function closeModal() { document.getElementById('custom-modal').classList.remove('is-active'); }
      
      function openReportModalGame() { document.getElementById('report-modal-game').classList.add('is-active'); }
      function closeReportModalGame() { document.getElementById('report-modal-game').classList.remove('is-active'); }
    </script>
    
    <div class="quiz-wrapper">

	<div class="quiz-wrapper">
	    <a href="javascript:void(0)" 
	       onclick="openReportModalGame()" 
	       class="report-flag" 
	       title="Zgłoś ten quiz">
	        <i class="fas fa-flag"></i>
	    </a>
    
	    </div>

        <div class="columns is-centered mt-4 px-3">
            <div class="column is-one-third">
                <div class="box has-background-primary has-text-white has-text-centered py-3 shadow-sm" style="border-radius: 20px;">
                    <h1 class="title is-5-mobile is-4-tablet has-text-white mb-0" style="word-break: break-word;"><?= htmlspecialchars($quiz['tytul']) ?></h1>
                </div>
            </div>
        </div>

        <section class="section pt-0 px-3-mobile">
            <div class="container">
                <div class="columns is-centered">
                    <div class="column is-12">
                        <div class="box quiz-box">
                            <div class="is-flex is-justify-content-space-between is-align-items-center is-flex-wrap-wrap mb-2">
                                <span class="has-text-weight-bold has-text-grey is-size-6-mobile mb-1">
                                    Pytanie <?= $current_num ?> z <?= $total ?>
                                </span>
                                <div class="is-flex is-align-items-center mb-1">
                                    <button type="button" class="button is-danger is-light is-small mr-3" onclick="openModal()">
                                        <span class="is-hidden-mobile">Zacznij od nowa /&nbsp;</span>Wyjdź
                                    </button>
                                    <span class="has-text-weight-bold has-text-primary is-size-6-mobile">
                                        <?= round($procent_postepu) ?>%
                                    </span>
                                </div>
                            </div>
                            <progress class="progress is-primary is-small mb-4" value="<?= $current_num ?>" max="<?= $total ?>"></progress>

                            <?php if(!empty($pytanie['sciezka_obrazu'])): ?>

                            <figure class="image mb-5 has-text-centered">
    							<img src="<?= htmlspecialchars($pytanie['sciezka_obrazu']) ?>" 
         							class="pytanie-img is-inline-block"
         							style="cursor: zoom-in;"
         							onclick="otworzModal(this.src)"
         							title="Kliknij, aby powiększyć">
							</figure>

                            <?php endif; ?>

                            <h2 class="title has-text-centered mb-5 question-title" style="word-break: break-word;">
                                <?= htmlspecialchars($pytanie['tresc']) ?>
                            </h2>

                            <form method="POST">
                                <div class="mb-4">
                                    <?php foreach($pytanie['odpowiedzi'] as $odp): 
                                        $litera = $odp['litera'] ?? '';
                                        $tresc = $odp['tresc'] ?? '';
                                    ?>
                                        <label class="answer-label mb-3">
                                            <input type="radio" name="odpowiedz" value="<?= $litera ?>" required>
                                            <div class="box answer-box has-background-light py-3 px-4">
                                                <span class="is-size-6-mobile is-size-5-tablet">
                                                    <strong class="mr-2"><?= $litera ?>.</strong> 
                                                    <?= htmlspecialchars($tresc) ?>
                                                </span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="submit" class="button is-primary is-medium-mobile is-large-tablet is-fullwidth has-text-weight-bold">
                                    Potwierdź
                                </button>
                            </form>

                        </div> 
                    </div>
                </div>
            </div>
        </section>
    </div>

<div id="modal-foto" class="modal">
    <div class="modal-background" onclick="zamknijModal()"></div>
    <div class="modal-content has-text-centered">
        <p class="image is-inline-block">
            <img id="modal-img" src="" style="border-radius: 6px; max-height: 80vh; object-fit: contain;">
        </p>
    </div>
    <button class="modal-close is-large" aria-label="close" onclick="zamknijModal()"></button>
</div>

<script>
// zgłoszenie wej/wyj
function openModal() { document.getElementById('custom-modal').classList.add('is-active'); }
function closeModal() { document.getElementById('custom-modal').classList.remove('is-active'); }

function openReportModalGame() { document.getElementById('report-modal-game').classList.add('is-active'); }
function closeReportModalGame() { document.getElementById('report-modal-game').classList.remove('is-active'); }

// powiększanie zdjęcia
function otworzModal(src) {
    document.getElementById('modal-img').src = src;
    document.getElementById('modal-foto').classList.add('is-active');
    
    // ukrycie flagi
    const flaga = document.querySelector('.report-flag');
    if(flaga) flaga.style.display = 'none';
}

function zamknijModal() {
    document.getElementById('modal-foto').classList.remove('is-active');
    
    // przywrócenie flagi
    const flaga = document.querySelector('.report-flag');
    if(flaga) flaga.style.display = 'block';
}
</script>

<?php include "footer.php"; ?>

</body>
</html>