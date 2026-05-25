<?php
require_once "polaczenie.php";

if(!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$id_uzytkownika = $_SESSION['id'];

$sql = "
SELECT q.*, AVG(o.ocena) AS srednia_ocena

FROM quizy q

LEFT JOIN oceny_quizu o 

ON q.id = o.quiz_id

WHERE q.autor_id = ? AND q.czy_usuniety = 0

GROUP BY q.id

ORDER BY q.data_utworzenia DESC
";

$stmt = $polaczenie->prepare($sql);
$stmt->bind_param("i", $id_uzytkownika);
$stmt->execute();

$wynik = $stmt->get_result();
?>

<style>

/* desktop css */

.quiz-table-wrapper {
    overflow-x: auto;
}

.quiz-table {
    border-radius: 16px;
    overflow: hidden;
    background: white;
}

/* mobile css */

.mobile-quiz-list {
    display: none;
}

.quiz-card {
    background: white;
    border-radius: 18px;
    padding: 18px;
    margin-bottom: 16px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
}

.quiz-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 14px;
}

.quiz-title {
    font-size: 1.15rem;
    font-weight: 700;
    line-height: 1.2;
    word-break: break-word;
}

.quiz-date {
    font-size: 0.8rem;
    color: #888;
}

.quiz-stars {
    font-size: 0.9rem;
    white-space: nowrap;
}

.star-full {
    color: #ffdd57;
}

.star-empty {
    color: #ddd;
}

.quiz-tags {
    margin-bottom: 14px;
}

.quiz-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.quiz-actions {
    display: flex;
    gap: 10px;
}

.quiz-actions .button {
    flex: 1;
    border-radius: 10px;
}

/* mobile css */

@media screen and (max-width: 768px) {

    .desktop-table {
        display: none;
    }

    .mobile-quiz-list {
        display: block;
    }
}

</style>

<?php if($wynik->num_rows > 0): ?>

<!-- desktop ver -->

<div class="desktop-table">

<div class="quiz-table-wrapper">

<table class="table is-fullwidth is-hoverable quiz-table">

<thead>
<tr>
    <th>Tytuł</th>
    <th>Data</th>
    <th>Ocena</th>
    <th>Premium</th>
    <th>Akcje</th>
</tr>
</thead>

<tbody>

<?php while($quiz = $wynik->fetch_assoc()): ?>

<?php

$ocena = $quiz['srednia_ocena'];

if ($ocena === null) {

    $gwiazdki_html = "
        <span class='has-text-grey-light is-size-7'>
            Brak ocen
        </span>
    ";

} else {

    $zaokraglona = round($ocena);
    $puste = 5 - $zaokraglona;

    $gwiazdki_html = "
        <span style='color:#ffdd57'>
            ".str_repeat('★', $zaokraglona)."
        </span>

        <span style='color:#ddd'>
            ".str_repeat('★', $puste)."
        </span>
    ";
}

?>

<tr>

<td>
    <strong><?= htmlspecialchars($quiz['tytul']) ?></strong>
</td>

<td>
    <?= date('d.m.Y', strtotime($quiz['data_utworzenia'])) ?>
</td>

<td>
    <?= $gwiazdki_html ?>
</td>

<td>

<?php if($quiz['czy_premium']): ?>

    <span class="tag is-danger is-light">
        Premium
    </span>

<?php else: ?>

    <span class="tag is-success is-light">
        Darmowy
    </span>

<?php endif; ?>

</td>

<td>

<div class="buttons">

    <a href="quiz_edytor.php?id=<?= $quiz['id'] ?>"
       class="button is-small is-warning">

        Edytuj

    </a>

    <a href="quiz_gra.php?id=<?= $quiz['id'] ?>"
       class="button is-small is-primary">

        Zagraj

    </a>

</div>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>

<!-- mobile ver -->

<div class="mobile-quiz-list">

<?php

$stmt->execute();
$wynik_mobile = $stmt->get_result();

while($quiz = $wynik_mobile->fetch_assoc()):

$ocena = $quiz['srednia_ocena'];

if ($ocena === null) {

    $mobile_stars = "
        <span class='has-text-grey-light'>
            Brak ocen
        </span>
    ";

} else {

    $pelne = round($ocena);
    $puste = 5 - $pelne;

    $mobile_stars = "
        <span class='star-full'>
            ".str_repeat('★', $pelne)."
        </span>

        <span class='star-empty'>
            ".str_repeat('★', $puste)."
        </span>
    ";
}

?>

<div class="quiz-card">

    <!-- tytuł, data, oceny-->

    <div class="quiz-card-top">

        <div>

            <div class="quiz-title">
                <?= htmlspecialchars($quiz['tytul']) ?>
            </div>

            <div class="quiz-date">
                <?= date('d.m.Y', strtotime($quiz['data_utworzenia'])) ?>
            </div>

        </div>

        <div class="quiz-stars">
            <?= $mobile_stars ?>
        </div>

    </div>

    <!-- czy premium -->

    <div class="quiz-tags">

        <?php if($quiz['czy_premium']): ?>

            <span class="tag is-danger is-light">
                ✨ Premium
            </span>

        <?php else: ?>

            <span class="tag is-success is-light">
                Darmowy
            </span>

        <?php endif; ?>

    </div>

    <!-- akcje -->

    <div class="quiz-actions">

        <a href="quiz_edytor.php?id=<?= $quiz['id'] ?>"
           class="button is-warning">

            Edytuj

        </a>

        <a href="quiz_gra.php?id=<?= $quiz['id'] ?>"
           class="button is-primary">

            Zagraj

        </a>

    </div>

</div>

<?php endwhile; ?>

</div>

<?php else: ?>
    
<!-- info o brak quizu -->
<div class="notification is-info">

    Nie stworzyłeś jeszcze żadnego quizu.

</div>

<?php include "popup.php"?>

<?php endif; ?>