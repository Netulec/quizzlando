<?php
session_start();
require_once "polaczenie.php";

if(!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$id_uzytkownika = $_SESSION['id'];
$blad = "";

// pobranie roli użytkownika
$uzytkownik = $polaczenie->prepare("SELECT rola_id FROM uzytkownicy WHERE id = ?");
$uzytkownik->bind_param("i", $id_uzytkownika);
$uzytkownik->execute();
$typ_result = $uzytkownik->get_result()->fetch_assoc();
$typ_uzytkownika = $typ_result['rola_id'];
$is_premium_user = ($typ_uzytkownika == 2 || $typ_uzytkownika == 3);

// stworzenie quizu
if(isset($_POST['stworz'])) {

    $tytul = trim($_POST['tytul']);
    $opis = trim($_POST['opis']);
    $kategoria_id = (int)$_POST['kategoria_id'];

    $czy_premium = (isset($_POST['czy_premium']) && $is_premium_user) ? 1 : 0;
    $czy_baza_pytan = (isset($_POST['czy_baza_pytan']) && $is_premium_user) ? 1 : 0;

    if(empty($tytul) || empty($kategoria_id)) {
        $blad = "Musisz podać tytuł i wybrać kategorię.";
    } else {

        $sql = "INSERT INTO quizy 
        (tytul, opis, kategoria_id, autor_id, czy_premium, czy_baza_pytan, czy_usuniety, data_utworzenia)
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";

        $stmt = $polaczenie->prepare($sql);
        $stmt->bind_param(
            "ssiiii",
            $tytul,
            $opis,
            $kategoria_id,
            $id_uzytkownika,
            $czy_premium,
            $czy_baza_pytan
        );

        $stmt->execute();
        $nowe_id = $stmt->insert_id;

        header("Location: quiz_edytor.php?id=" . $nowe_id);
        exit();
    }
}

$kategorie = $polaczenie->query("SELECT id, nazwa FROM kategorie ORDER BY nazwa ASC");
?>

<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>Stwórz quiz | Quizzlando</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<nav class="navbar is-primary">
  <div class="navbar-brand">
    <a class="navbar-item" href="index.php"><strong>Quizzlando</strong></a>
  </div>
</nav>

<section class="section">
<div class="container">

<div class="columns is-centered">
<div class="column is-6">

<div class="card">
<div class="card-content">

<!-- nagłówek -->
<h1 class="title has-text-centered">
<i class="fas fa-plus-circle"></i> Stwórz nowy quiz
</h1>

<?php if($blad): ?>
<div class="notification is-danger"><?= $blad ?></div>
<?php endif; ?>

<form method="POST">

<!-- tytuł -->
<div class="field">
    <label class="label">Tytuł quizu</label>
    <input class="input" type="text" name="tytul" required>
</div>

<!-- opis -->
<div class="field">
    <label class="label">Opis</label>
    <textarea class="textarea" name="opis"></textarea>
</div>

<!-- kategoria -->
<div class="field">
    <label class="label">Kategoria</label>
    <div class="select is-fullwidth">
        <select name="kategoria_id" required>
            <option value="">Wybierz kategorię</option>
            <?php while($kat = $kategorie->fetch_assoc()): ?>
                <option value="<?= $kat['id'] ?>">
                    <?= htmlspecialchars($kat['nazwa']) ?>
                </option>
            <?php endwhile; ?>
        </select>
    </div>
</div>

<!-- checkbox czy quiz z bazy pytań -->
<div class="field" <?= !$is_premium_user ? 'title="Tylko dla użytkowników premium"' : '' ?>>
    <label class="checkbox" <?= !$is_premium_user ? 'style="color: #999; cursor: not-allowed;"' : '' ?>>
        <input type="checkbox" name="czy_baza_pytan" value="1" <?= !$is_premium_user ? 'disabled' : '' ?>>
        Stwórz quiz z istniejącej bazy pytań 📚
    </label>
</div>

<!-- checkbox czy quiz dla uż. premium -->
<div class="field" <?= !$is_premium_user ? 'title="Tylko dla użytkowników premium"' : '' ?>>
    <label class="checkbox" <?= !$is_premium_user ? 'style="color: #999; cursor: not-allowed;"' : '' ?>>
        <input type="checkbox" name="czy_premium" value="1" <?= !$is_premium_user ? 'disabled' : '' ?>>
        Quiz tylko dla użytkowników premium 🔒
    </label>
</div>

<!-- przycisk stwórz quiz/anuluj -->
<div class="field is-grouped is-justify-content-space-between mt-5">
    <a href="panel.php" class="button is-light">Anuluj</a>
    <button class="button is-primary" name="stworz">
        <i class="fas fa-rocket"></i>&nbsp;Stwórz quiz
    </button>
</div>

</form>

</div>
</div>

</div>
</div>

</div>
</section>

<?php include "footer.php"; ?>

</body>
</html>