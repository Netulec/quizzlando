<?php
session_start();
require_once "polaczenie.php";

if(!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$id_uzytkownika = $_SESSION['id'];
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0);

/* upload obrazu */
function uploadObrazu($inputName = 'obraz') {
    if(!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== 0) return null;
    $folder = "zdjecia_pytania/";
    if(!is_dir($folder)) mkdir($folder, 0777, true);
    
    $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
    $dozwolone = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if(!in_array($ext, $dozwolone)) return null;

    $nazwa = uniqid('pytanie_', true) . '.' . $ext;
    $sciezka = $folder . $nazwa;

    if(move_uploaded_file($_FILES[$inputName]['tmp_name'], $sciezka)) return $sciezka;
    return null;
}

/* dodawanie pytań z bazy */
if(isset($_POST['action']) && $_POST['action'] === 'dodaj_z_bazy') {
    header('Content-Type: application/json');
    $baza_id = (int)$_POST['baza_id'];

    $stmt = $polaczenie->prepare("SELECT q.kategoria_id, q.czy_baza_pytan, u.rola_id FROM quizy q JOIN uzytkownicy u ON u.id = ? WHERE q.id = ? AND q.autor_id = ? AND q.czy_usuniety = 0");
    $stmt->bind_param("iii", $id_uzytkownika, $quiz_id, $id_uzytkownika);
    $stmt->execute();
    $quiz_ajax = $stmt->get_result()->fetch_assoc();

    if(!$quiz_ajax || $quiz_ajax['czy_baza_pytan'] != 1 || !in_array($quiz_ajax['rola_id'], [2,3])) {
        echo json_encode(['success' => false, 'error' => 'Brak uprawnień.']);
        exit();
    }

    $stmt = $polaczenie->prepare("SELECT id FROM pytania WHERE id_quizu = ? AND baza_pytan_id = ?");
    $stmt->bind_param("ii", $quiz_id, $baza_id);
    $stmt->execute();

    if($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Pytanie już istnieje.']);
        exit();
    }

    $stmt = $polaczenie->prepare("SELECT * FROM baza_pytan WHERE id = ? AND kategoria_id = ?");
    $stmt->bind_param("ii", $baza_id, $quiz_ajax['kategoria_id']);
    $stmt->execute();
    $bp = $stmt->get_result()->fetch_assoc();

    if($bp) {
        $stmt = $polaczenie->prepare("INSERT INTO pytania (id_quizu, tresc, baza_pytan_id, sciezka_obrazu) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isis", $quiz_id, $bp['tresc'], $bp['id'], $bp['sciezka_obrazu']);
        if($stmt->execute()) {
            $pid = $stmt->insert_id;
            $stmt2 = $polaczenie->prepare("INSERT INTO odpowiedzi (pytanie_id, poprawna, odpowiedz_a, odpowiedz_b, odpowiedz_c, odpowiedz_d) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("isssss", $pid, $bp['poprawna_odpowiedz'], $bp['odpowiedz_a'], $bp['odpowiedz_b'], $bp['odpowiedz_c'], $bp['odpowiedz_d']);
            $stmt2->execute();

            echo json_encode([
                'success' => true,
                'pytanie' => [
                    'id' => $pid,
                    'tresc' => htmlspecialchars($bp['tresc']),
                    'a' => htmlspecialchars($bp['odpowiedz_a']),
                    'b' => htmlspecialchars($bp['odpowiedz_b']),
                    'c' => htmlspecialchars($bp['odpowiedz_c']),
                    'd' => htmlspecialchars($bp['odpowiedz_d']),
                    'poprawna' => htmlspecialchars($bp['poprawna_odpowiedz']),
                    'obraz' => $bp['sciezka_obrazu']
                ]
            ]);
            exit();
        }
    }
    echo json_encode(['success' => false, 'error' => 'Nie znaleziono pytania.']);
    exit();
}

/* quiz */
$stmt = $polaczenie->prepare("SELECT * FROM quizy WHERE id = ? AND autor_id = ? AND czy_usuniety = 0");
$stmt->bind_param("ii", $quiz_id, $id_uzytkownika);
$stmt->execute();
$quiz = $stmt->get_result()->fetch_assoc();

if(!$quiz) die("Brak dostępu.");

/* usuwanie quizu */
if(isset($_POST['usun_quiz'])) {
    $stmt = $polaczenie->prepare("UPDATE quizy SET czy_usuniety = 1 WHERE id = ?");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    header("Location: moje_quizy.php");
    exit();
}

/* rola */
$stmt = $polaczenie->prepare("SELECT rola_id FROM uzytkownicy WHERE id=?");
$stmt->bind_param("i", $id_uzytkownika);
$stmt->execute();
$rola = $stmt->get_result()->fetch_assoc()['rola_id'];

/* usuwanie pytania */
if(isset($_POST['usun_pytanie'])) {
    $pid = (int)$_POST['pytanie_id'];
    $stmt = $polaczenie->prepare("SELECT id, sciezka_obrazu FROM pytania WHERE id = ? AND id_quizu = ?");
    $stmt->bind_param("ii", $pid, $quiz_id);
    $stmt->execute();
    $pyt = $stmt->get_result()->fetch_assoc();

    if($pyt) {
        if(!empty($pyt['sciezka_obrazu']) && file_exists($pyt['sciezka_obrazu'])) unlink($pyt['sciezka_obrazu']);
        $stmt = $polaczenie->prepare("DELETE FROM odpowiedzi WHERE pytanie_id = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();

        $stmt = $polaczenie->prepare("DELETE FROM pytania WHERE id = ? AND id_quizu = ?");
        $stmt->bind_param("ii", $pid, $quiz_id);
        $stmt->execute();
    }
    header("Location: quiz_edytor.php?id=".$quiz_id);
    exit();
}

/* dodawanie pytania */
if(isset($_POST['zapisz_pytanie'])) {
    $tresc = trim($_POST['tresc']);
    $a = trim($_POST['a']);
    $b = trim($_POST['b']);
    $c = trim($_POST['c']);
    $d = trim($_POST['d']);
    $poprawna = $_POST['poprawna'];
    $sciezka_obrazu = uploadObrazu();

    if($tresc && $a && $b && $poprawna) {
        $stmt = $polaczenie->prepare("INSERT INTO pytania (id_quizu, tresc, sciezka_obrazu) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $quiz_id, $tresc, $sciezka_obrazu);
        $stmt->execute();
        $pid = $stmt->insert_id;

        $stmt2 = $polaczenie->prepare("INSERT INTO odpowiedzi (pytanie_id, poprawna, odpowiedz_a, odpowiedz_b, odpowiedz_c, odpowiedz_d) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param("isssss", $pid, $poprawna, $a, $b, $c, $d);
        $stmt2->execute();
    }
    header("Location: quiz_edytor.php?id=".$quiz_id);
    exit();
}

/* edycja pytania */
if(isset($_POST['edytuj_pytanie'])) {
    $pid = (int)$_POST['pytanie_id'];
    $tresc = trim($_POST['tresc']);
    $a = trim($_POST['a']);
    $b = trim($_POST['b']);
    $c = trim($_POST['c']);
    $d = trim($_POST['d']);
    $poprawna = $_POST['poprawna'];
    $nowy_obraz = uploadObrazu();

    if($tresc && $a && $b && $poprawna) {
        $stmt = $polaczenie->prepare("SELECT sciezka_obrazu FROM pytania WHERE id = ? AND id_quizu = ?");
        $stmt->bind_param("ii", $pid, $quiz_id);
        $stmt->execute();
        $stare = $stmt->get_result()->fetch_assoc();

        if($nowy_obraz) {
            if(!empty($stare['sciezka_obrazu']) && file_exists($stare['sciezka_obrazu'])) unlink($stare['sciezka_obrazu']);
            $stmt_upd = $polaczenie->prepare("UPDATE pytania SET tresc = ?, sciezka_obrazu = ? WHERE id = ?");
            $stmt_upd->bind_param("ssi", $tresc, $nowy_obraz, $pid);
        } else {
            $stmt_upd = $polaczenie->prepare("UPDATE pytania SET tresc = ? WHERE id = ?");
            $stmt_upd->bind_param("si", $tresc, $pid);
        }
        $stmt_upd->execute();

        $stmt_upd2 = $polaczenie->prepare("UPDATE odpowiedzi SET poprawna = ?, odpowiedz_a = ?, odpowiedz_b = ?, odpowiedz_c = ?, odpowiedz_d = ? WHERE pytanie_id = ?");
        $stmt_upd2->bind_param("sssssi", $poprawna, $a, $b, $c, $d, $pid);
        $stmt_upd2->execute();
    }
    header("Location: quiz_edytor.php?id=".$quiz_id);
    exit();
}

/* baza */
$baza = [];
$pokaz_baze = ($quiz['czy_baza_pytan'] == 1 && ($rola == 2 || $rola == 3));

if($pokaz_baze) {
    $stmt = $polaczenie->prepare("SELECT bp.* FROM baza_pytan bp LEFT JOIN pytania p ON p.baza_pytan_id = bp.id AND p.id_quizu = ? WHERE bp.kategoria_id = ? AND p.id IS NULL ORDER BY bp.id DESC");
    $stmt->bind_param("ii", $quiz_id, $quiz['kategoria_id']);
    $stmt->execute();
    $baza = $stmt->get_result();
}

/* pytania */
$pytania = $polaczenie->prepare("SELECT p.*, o.odpowiedz_a, o.odpowiedz_b, o.odpowiedz_c, o.odpowiedz_d, o.poprawna FROM pytania p LEFT JOIN odpowiedzi o ON p.id = o.pytanie_id WHERE p.id_quizu = ? ORDER BY p.id ASC");
$pytania->bind_param("i", $quiz_id);
$pytania->execute();
$lista = $pytania->get_result();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edytor quizu - <?= htmlspecialchars($quiz['tytul']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <style>
        .pytanie-box { position: relative; }
        .pytanie-akcje { margin-top: 0.5rem; display: flex; gap: 0.5rem; }
        .baza-pytanie-box { transition: opacity 0.3s ease; }
        .pytanie-obraz { max-width: 100%; border-radius: 12px; margin-top: 1rem; margin-bottom: 1rem; }
        @media screen and (min-width: 769px) {
            .pytanie-akcje { position: absolute; top: 1rem; right: 1rem; margin-top: 0; }
            .pytanie-widok { padding-right: 120px; }
        }
    </style>
</head>
<body>

<?php include "navbar.php"; ?>

<section class="section">
    <div class="container">
        <h1 class="title is-3">Edytor: <?= htmlspecialchars($quiz['tytul']) ?></h1>
        
        <div class="tabs is-boxed">
            <ul>
                <li class="is-active tab-link" data-tab="manual"><a>✍️ Pytania</a></li>
                <?php if($pokaz_baze): ?>
                <li class="tab-link" data-tab="baza"><a>📚 Baza pytań</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div id="manual" class="tab-content">
            <div class="box has-background-light">
                <h2 class="title is-5">Dodaj pytanie</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="field"><input class="input" name="tresc" placeholder="Treść pytania" required></div>
                    <div class="columns is-multiline">
                        <div class="column is-6"><input class="input" name="a" placeholder="A" required></div>
                        <div class="column is-6"><input class="input" name="b" placeholder="B" required></div>
                        <div class="column is-6"><input class="input" name="c" placeholder="C"></div>
                        <div class="column is-6"><input class="input" name="d" placeholder="D"></div>
                    </div>
                    <div class="field">
                        <label class="label">Zdjęcie</label>
                        <input type="file" name="obraz" class="input" accept="image/*">
                    </div>
                    <div class="field">
                        <label class="label">Poprawna odpowiedź</label>
                        <div class="select">
                            <select name="poprawna" required>
                                <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                            </select>
                        </div>
                    </div>
                    <button class="button is-success" name="zapisz_pytanie">Dodaj pytanie</button>
                </form>
            </div>
            <hr>
            <h2 class="title is-4">Lista pytań</h2>
            <div id="lista-pytan">
                <?php if($lista->num_rows === 0): ?>
                    <p class="has-text-grey" id="brak-pytan-msg">Quiz nie ma pytań.</p>
                <?php endif; ?>
                <?php while($p = $lista->fetch_assoc()): ?>
                    <div class="box pytanie-box" data-id="<?= $p['id'] ?>">
                        <div class="pytanie-widok">
                            <h3 class="title is-5 mb-2"><?= htmlspecialchars($p['tresc']) ?></h3>
                            <?php if(!empty($p['sciezka_obrazu'])): ?>
                                <img src="<?= htmlspecialchars($p['sciezka_obrazu']) ?>" class="pytanie-obraz">
                            <?php endif; ?>
                            <div class="columns is-multiline is-size-6">
                                <div class="column is-6"><strong>A:</strong> <?= htmlspecialchars($p['odpowiedz_a']) ?></div>
                                <div class="column is-6"><strong>B:</strong> <?= htmlspecialchars($p['odpowiedz_b']) ?></div>
                                <?php if(!empty($p['odpowiedz_c'])): ?><div class="column is-6"><strong>C:</strong> <?= htmlspecialchars($p['odpowiedz_c']) ?></div><?php endif; ?>
                                <?php if(!empty($p['odpowiedz_d'])): ?><div class="column is-6"><strong>D:</strong> <?= htmlspecialchars($p['odpowiedz_d']) ?></div><?php endif; ?>
                            </div>
                            <div class="mt-2 has-text-success has-text-weight-bold">Poprawna: <?= htmlspecialchars($p['poprawna']) ?></div>
                            <div class="pytanie-akcje">
                                <button type="button" class="button is-info is-small" onclick="pokazEdycje(<?= $p['id'] ?>)">Edytuj</button>
                                <form method="POST">
                                    <input type="hidden" name="pytanie_id" value="<?= $p['id'] ?>">
                                    <button class="button is-danger is-small" name="usun_pytanie" onclick="return confirm('Usunąć pytanie?');">Usuń</button>
                                </form>
                            </div>
                        </div>
                        <div class="pytanie-edycja" id="edycja-<?= $p['id'] ?>" style="display:none;">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="pytanie_id" value="<?= $p['id'] ?>">
                                <div class="field"><input class="input" name="tresc" value="<?= htmlspecialchars($p['tresc']) ?>" required></div>
                                <div class="columns is-multiline">
                                    <div class="column is-6"><input class="input" name="a" value="<?= htmlspecialchars($p['odpowiedz_a']) ?>" required></div>
                                    <div class="column is-6"><input class="input" name="b" value="<?= htmlspecialchars($p['odpowiedz_b']) ?>" required></div>
                                    <div class="column is-6"><input class="input" name="c" value="<?= htmlspecialchars($p['odpowiedz_c']) ?>"></div>
                                    <div class="column is-6"><input class="input" name="d" value="<?= htmlspecialchars($p['odpowiedz_d']) ?>"></div>
                                </div>
                                <div class="field">
                                    <label class="label">Nowe zdjęcie</label>
                                    <input type="file" name="obraz" class="input" accept="image/*">
                                </div>
                                <div class="field">
                                    <div class="select">
                                        <select name="poprawna">
                                            <option value="A" <?= $p['poprawna']=='A'?'selected':'' ?>>A</option>
                                            <option value="B" <?= $p['poprawna']=='B'?'selected':'' ?>>B</option>
                                            <option value="C" <?= $p['poprawna']=='C'?'selected':'' ?>>C</option>
                                            <option value="D" <?= $p['poprawna']=='D'?'selected':'' ?>>D</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="buttons">
                                    <button class="button is-success" name="edytuj_pytanie">Zapisz</button>
                                    <button type="button" class="button" onclick="ukryjEdycje(<?= $p['id'] ?>)">Anuluj</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <?php if($pokaz_baze): ?>
        <div id="baza" class="tab-content" style="display:none;">
            <?php while($b = $baza->fetch_assoc()): ?>
                <div class="box baza-pytanie-box" data-baza-id="<?= $b['id'] ?>">
                    <strong><?= htmlspecialchars($b['tresc']) ?></strong>
                    <?php if(!empty($b['sciezka_obrazu'])): ?>
                        <img src="<?= htmlspecialchars($b['sciezka_obrazu']) ?>" class="pytanie-obraz">
                    <?php endif; ?>
                    <div class="mt-2">
                        <button type="button" class="button is-success is-small" onclick="dodajZBazy(<?= $b['id'] ?>, this)">Dodaj</button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
document.querySelectorAll('.tab-link').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab-link').forEach(t => t.classList.remove('is-active'));
        tab.classList.add('is-active');
        const target = tab.dataset.tab;
        document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
        document.getElementById(target).style.display = 'block';
    });
});
function pokazEdycje(id) {
    document.querySelector('.pytanie-box[data-id="'+id+'"] .pytanie-widok').style.display = 'none';
    document.getElementById('edycja-'+id).style.display = 'block';
}
function ukryjEdycje(id) {
    document.querySelector('.pytanie-box[data-id="'+id+'"] .pytanie-widok').style.display = 'block';
    document.getElementById('edycja-'+id).style.display = 'none';
}
function dodajZBazy(bazaId, btn) {
    btn.disabled = true;
    const formData = new FormData();
    formData.append('action', 'dodaj_z_bazy');
    formData.append('baza_id', bazaId);
    formData.append('quiz_id', <?= $quiz_id ?>);

    fetch('quiz_edytor.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.success) location.reload();
        else { alert(data.error); btn.disabled = false; }
    })
    .catch(() => { alert('Błąd połączenia.'); btn.disabled = false; });
}
</script>
<?php include "footer.php"; ?>
</body>
</html>