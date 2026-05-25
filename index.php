<?php
session_start();
require_once "polaczenie.php";

$rola_id = isset($_SESSION['rola_id']) ? (int)$_SESSION['rola_id'] : 0;

$sort = $_GET['sort'] ?? 'data_utworzenia';
$search = $_GET['search'] ?? '';
$premium_filter = $_GET['premium'] ?? 'all';
$kategoria_filter = $_GET['kategoria'] ?? 'all';

$allowed_sorts = [
    'data_utworzenia',
    'liczba_pytan',
    'kategoria',
    'ocena',
    'tytul',
    'autor_id'
];

if(!in_array($sort, $allowed_sorts)) {
    $sort = 'data_utworzenia';
}

$search_sql = $polaczenie->real_escape_string($search);

// kategoria
$kategorie = $polaczenie->query("
    SELECT id, nazwa
    FROM kategorie
    ORDER BY nazwa ASC
");

// czy premium
$where_premium = "";
if ($premium_filter === 'premium') {
    $where_premium = "AND q.czy_premium = 1";
} elseif ($premium_filter === 'free') {
    $where_premium = "AND q.czy_premium = 0";
}

// kategoria
$where_kategoria = "";
if ($kategoria_filter !== 'all') {
    $kategoria_filter = (int)$kategoria_filter;
    $where_kategoria = "
        AND q.kategoria_id = $kategoria_filter
    ";
}

// sql - pobieranie quizów (z filtrowaniem usuniętych autorów)
$sql = "
SELECT
    q.*,
    u.nazwa AS autor,
    k.nazwa AS kategoria,
    AVG(o.ocena) AS ocena,
    COUNT(DISTINCT p.id) AS liczba_pytan
FROM quizy q
JOIN uzytkownicy u ON q.autor_id = u.id
LEFT JOIN kategorie k ON q.kategoria_id = k.id
LEFT JOIN oceny_quizu o ON q.id = o.quiz_id
LEFT JOIN pytania p ON p.id_quizu = q.id
WHERE
    q.czy_usuniety = 0
    AND u.czy_usuniety = 0 
    AND q.tytul LIKE '%$search_sql%'
    $where_premium
    $where_kategoria
GROUP BY q.id
HAVING COUNT(DISTINCT p.id) > 0
ORDER BY $sort DESC
LIMIT 10
";

$wynik = $polaczenie->query($sql);

// ostatnie quizy znajomych
$znajomi_quizy = [];

if (isset($_SESSION['id'])) {

    $user_id = (int)$_SESSION['id'];

    // Pobieranie znajomych (z filtrowaniem usuniętych kont)
    $sql_znajomi = "
        SELECT
            u.id,
            u.nazwa
        FROM znajomi z
        JOIN uzytkownicy u
        ON (
            (
                z.zapraszajacy_id = $user_id
                AND u.id = z.zaproszony_id
            )
            OR
            (
                z.zaproszony_id = $user_id
                AND u.id = z.zapraszajacy_id
            )
        )
        WHERE z.status_id = 2 AND u.czy_usuniety = 0
        ORDER BY u.nazwa ASC
    ";

    $wynik_znajomi = $polaczenie->query($sql_znajomi);

    while ($znajomy = $wynik_znajomi->fetch_assoc()) {

        $znajomy_id = (int)$znajomy['id'];

        $sql_quizy = "
            SELECT
                q.id,
                q.tytul,
                MAX(p.data_rozpoczecia) AS data_rozpoczecia,
                MAX(p.wynik) AS wynik,
                MAX(p.liczba_pytan) AS max_wynik
            FROM proby_quizu p
            JOIN quizy q
            ON q.id = p.quiz_id
            WHERE
                p.uzytkownik_id = $znajomy_id
                AND q.czy_usuniety = 0
            GROUP BY q.id, q.tytul
            ORDER BY data_rozpoczecia DESC
            LIMIT 5
        ";

        $wynik_quizy = $polaczenie->query($sql_quizy);

        $quizy = [];
        while ($quiz = $wynik_quizy->fetch_assoc()) {
            $quizy[] = $quiz;
        }

        if (count($quizy) > 0) {
            $znajomi_quizy[] = [
                'id' => $znajomy['id'],
                'nazwa' => $znajomy['nazwa'],
                'quizy' => $quizy
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pl">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Quizzlando</title>

<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

body {
    background: #f5f6fa;
}

/* kolumny css */

.columns.is-multiline {
    display: flex;
    flex-wrap: wrap;
}

.column.is-one-third {
    display: flex;
}

/* tablet css */

@media screen and (max-width: 1023px) {

    .column.is-one-third {
        width: 50%;
    }
}

/* mobile css */

@media screen and (max-width: 768px) {

    .column.is-one-third {
        width: 100%;
    }

    .filters-level {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 15px;
    }

    .filters-right {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .filters-right > div {
        width: 100%;
        margin-right: 0 !important;
    }

    .select,
    .select select,
    .input,
    .button {
        width: 100%;
    }

    .field.has-addons {
        display: flex;
    }

    .field.has-addons .control {
        flex: 1;
    }

    .quiz-header {
        flex-direction: column;
        align-items: flex-start !important;
    }

    .quiz-rating {
        margin-top: -5px;
    }

    .star-glyph {
        font-size: 0.9rem !important;
    }

    .quiz-meta {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 5px;
    }
}

/* cardy */

.card {
    width: 100%;
    display: flex;
    flex-direction: column;
    height: 100%;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.card-content {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

/* quiz head css */

.quiz-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 12px;
}

.quiz-title {
    font-size: 1.2rem;
    font-weight: 700;
    line-height: 1.2;
    word-break: break-word;
    flex: 1;
}

.quiz-rating {
    flex-shrink: 0;
    white-space: nowrap;
}

/* znajomi css */

.friends-section {
    margin-bottom: 35px;
}

.friend-card {
    border-radius: 14px;
    height: 100%;
}

.friend-quiz-item {
    border-top: 1px solid #f0f0f0;
    padding: 10px 0;
}

.friend-quiz-item:first-child {
    border-top: none;
    padding-top: 0;
}

.friend-name {
    font-weight: 700;
    font-size: 1rem;
}

.friend-empty {
    color: #999;
    font-size: 0.9rem;
}

.friend-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.friend-avatar-placeholder {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #777;
    font-size: 1rem;
    flex-shrink: 0;
}

/* gwiazdki oceny css */

.star-glyph {
    letter-spacing: 1px;
    font-size: 1rem;
}

/* meta css */

.quiz-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
}

/* button css */

.card-footer-item {
    font-weight: 600;
}

/* wyszukiwarka mobile css */

.mobile-search-toggle {
    display: none;
}

@media screen and (max-width: 768px) {

    .mobile-search-toggle {
        display: flex;
        justify-content: flex-end;
    }

    .filters-box {
        display: none;
        animation: fadeIn 0.2s ease;
    }

    .filters-box.active {
        display: block;
    }

    .column.is-three-quarters,
    .column.is-one-quarter {
        width: 100%;
    }
}

/* desktop css */

@media screen and (min-width: 769px) {

    .filters-box {
        display: block !important;
    }
}

/* animacje css */

@keyframes fadeIn {

    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* home css */

.home-layout {
    align-items: flex-start;
}

/* akt. znajomych css */

.friend-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.friend-avatar,
.friend-avatar-placeholder {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    flex-shrink: 0;
}

.friend-avatar {
    object-fit: cover;
}

.friend-avatar-placeholder {
    background: #ececec;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #777;
}

/* mobile css */

@media screen and (max-width: 1023px) {

    .home-layout {
        flex-direction: column;
    }

    .popular-column,
    .friends-column {
        width: 100%;
    }
}

</style>

</head>

<body>

<?php include_once "navbar.php"; ?>

<?php include "popup.php"; ?>

<?php include "popup_zglos.php"; ?>

<section class="section">

<div class="container">

<h1 class="title">
    Witaj w Quizzlando!
</h1>

<form method="get" class="mb-5">

    <div class="mobile-search-toggle mb-3">

        <button type="button"
                class="button is-info is-rounded"
                id="toggleFilters">

            <span class="icon">
                <i class="fas fa-search"></i>
            </span>

        </button>

    </div>

    <div class="box filters-box" id="filtersBox">

        <div class="level filters-level">

            <div class="level-left">

                <div>

                    <p class="is-size-7 has-text-grey mb-1">
                        Wyszukiwanie
                    </p>

                    <div class="field has-addons">

                        <div class="control">

                            <input
                                class="input"
                                type="text"
                                name="search"
                                placeholder="Szukaj quizu..."
                                value="<?= htmlspecialchars($search) ?>"
                            >

                        </div>

                        <div class="control">

                            <button class="button is-info">
                                Szukaj
                            </button>

                        </div>

                    </div>

                </div>

            </div>

            <div class="level-right filters-right">

                <div class="mr-3">

                    <p class="is-size-7 has-text-grey mb-1">
                        Sortowanie
                    </p>

                    <div class="select is-small">

                        <select name="sort">

                            <option value="data_utworzenia"
                                <?= $sort=='data_utworzenia'?'selected':'' ?>>

                                Data

                            </option>

                            <option value="ocena"
                                <?= $sort=='ocena'?'selected':'' ?>>

                                Ocena

                            </option>

                            <option value="tytul"
                                <?= $sort=='tytul'?'selected':'' ?>>

                                Tytuł

                            </option>

                            <option value="liczba_pytan"
                                <?= $sort=='liczba_pytan'?'selected':'' ?>>

                                Liczba pytań

                            </option>

                        </select>

                    </div>

                </div>

                <div class="mr-3">

                    <p class="is-size-7 has-text-grey mb-1">
                        Kategoria
                    </p>

                    <div class="select is-small">

                        <select name="kategoria">

                            <option value="all">
                                Wszystkie
                            </option>

                            <?php while($k = $kategorie->fetch_assoc()): ?>

                                <option
                                    value="<?= $k['id'] ?>"
                                    <?= $kategoria_filter == $k['id']
                                        ? 'selected'
                                        : '' ?>
                                >

                                    <?= htmlspecialchars($k['nazwa']) ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>

                </div>

                <div class="mr-4">

                    <p class="is-size-7 has-text-grey mb-1">
                        Typ quizu
                    </p>

                    <div class="select is-small">

                        <select name="premium">

                            <option value="all"
                                <?= $premium_filter=='all'
                                    ? 'selected'
                                    : '' ?>>

                                Wszystkie

                            </option>

                            <option value="premium"
                                <?= $premium_filter=='premium'
                                    ? 'selected'
                                    : '' ?>>

                                Premium

                            </option>

                            <option value="free"
                                <?= $premium_filter=='free'
                                    ? 'selected'
                                    : '' ?>>

                                Darmowe

                            </option>

                        </select>

                    </div>

                </div>

                <div>

                    <p class="is-size-7 has-text-grey mb-1">
                        &nbsp;
                    </p>

                    <button class="button is-info">
                        Filtruj
                    </button>

                </div>

            </div>

        </div>

    </div>

</form>

<div class="columns home-layout">

    <div class="column <?= isset($_SESSION['id']) && count($znajomi_quizy) > 0 ? 'is-three-quarters' : 'is-full' ?>">

        <h2 class="title is-4 mb-4">

            <i class="fas fa-fire mr-2"></i>

            Ostatnio popularne

        </h2>

        <div class="columns is-multiline">

        <?php while($quiz = $wynik->fetch_assoc()): ?>

        <?php

        $is_premium = $quiz['czy_premium'] == 1;
        $has_access = !$is_premium || in_array($rola_id, [2, 3]);

        if (!isset($_SESSION['id'])) {
            $link = "logowanie.php";
            $onclick = "";
            $tekst_przycisku = "Zaloguj się, by zagrać";
            $title_attr = "";
        } elseif (!$has_access) {
            $link = "#";
            $onclick = "onclick=\"alert('Ten quiz jest dostępny tylko dla użytkowników premium!'); return false;\"";
            $tekst_przycisku = "<i class=\"fas fa-lock mr-2\"></i> Tylko Premium";
            $title_attr = "title=\"Tylko dla użytkowników premium\"";
        } else {
            $link = "quiz_gra.php?id=".$quiz['id'];
            $onclick = "";
            $tekst_przycisku = "Zagraj";
            $title_attr = "";
        }

        $srednia = $quiz['ocena'];

        if ($srednia === null) {

            $gwiazdki_html = "
                <span class='has-text-grey-light is-size-7'>
                    Brak ocen
                </span>
            ";

        } else {

            $pelne = round($srednia);
            $puste = 5 - $pelne;

            $gwiazdki_html = "
                <span>
                    <span class='has-text-warning star-glyph'>
                        ".str_repeat('★', $pelne)."
                    </span>

                    <span class='has-text-grey-lighter star-glyph'>
                        ".str_repeat('★', $puste)."
                    </span>
                </span>
            ";
        }

        ?>

        <div class="column <?= isset($_SESSION['id']) && count($znajomi_quizy) > 0 ? 'is-half' : 'is-one-third' ?>">

            <div class="card">

                <div class="card-content">

                    <div class="quiz-header">

                        <div class="quiz-title">

                            <?= htmlspecialchars($quiz['tytul']) ?>

                        </div>

                        <div class="quiz-rating">

                            <?= $gwiazdki_html ?>

                        </div>

                    </div>

                    <div class="tags mb-2">

                        <span class="tag is-info is-light">

                            <?= htmlspecialchars($quiz['kategoria'] ?? 'Ogólny') ?>

                        </span>

                        <?php if($is_premium): ?>

                            <span class="tag is-danger is-light">
                                ✨ Premium
                            </span>

                        <?php endif; ?>

                    </div>

                    <p class="is-size-7 has-text-grey mt-3">

                        Liczba pytań:

                        <strong>

                            <?= $quiz['liczba_pytan'] ?>

                        </strong>

                    </p>

                    <div class="quiz-meta">

                        <span class="is-size-7 has-text-grey">

                            Utworzono:
                            <?= date(
                                'd.m.Y',
                                strtotime($quiz['data_utworzenia'])
                            ) ?>

                        </span>

                        <span class="is-size-7 has-text-grey">
                            Autor:
                            <strong>
                                <a href="profil.php?id=<?= $quiz['autor_id'] ?>" class="has-text-info is-underlined">
                                    <?= htmlspecialchars($quiz['autor']) ?>
                                </a>
                            </strong>
                        </span>

                    </div>

                </div>

                <footer class="card-footer">

                    <a href="<?= $link ?>" <?= $onclick ?> class="card-footer-item <?= !$has_access ? 'has-text-grey-light' : '' ?>" <?= $title_attr ?>>
                        <?= $tekst_przycisku ?>
                    </a>

                </footer>

            </div>

        </div>

        <?php endwhile; ?>

        </div>

    </div>

    <?php if(isset($_SESSION['id']) && count($znajomi_quizy) > 0): ?>

    <div class="column is-one-quarter">

        <h2 class="title is-4 mb-4">

            <i class="fas fa-user-friends mr-2"></i>

            Aktywność znajomych

        </h2>

        <?php foreach($znajomi_quizy as $znajomy): ?>

            <div class="card friend-card mb-4">

                <div class="card-content">

                    <div class="friend-user mb-3">

                        <div class="friend-avatar-placeholder">

                            <i class="fas fa-user"></i>

                        </div>

                        <a
                            href="profil.php?id=<?= $znajomy['id'] ?>"
                            class="friend-name has-text-info"
                        >

                            <?= htmlspecialchars($znajomy['nazwa']) ?>

                        </a>

                    </div>

                    <?php foreach($znajomy['quizy'] as $quiz): ?>

                        <div class="friend-quiz-item">

                            <a
                                href="quiz_gra.php?id=<?= $quiz['id'] ?>"
                                class="has-text-dark has-text-weight-semibold"
                            >

                                <?= htmlspecialchars($quiz['tytul']) ?>

                            </a>

                            <div class="is-size-7 has-text-grey mt-1">

                                <?= date(
                                    'd.m.Y H:i',
                                    strtotime($quiz['data_rozpoczecia'])
                                ) ?>

                            </div>

                            <div class="is-size-7 has-text-info mt-1">

                                Wynik:
                                <strong>
                                    <?= (int)$quiz['wynik'] ?>/<?= (int)$quiz['max_wynik'] ?>
                                </strong>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

    <?php endif; ?>

</div>

</div>

</section>

<script>

const toggleBtn = document.getElementById('toggleFilters');

const filtersBox = document.getElementById('filtersBox');

if(toggleBtn) {

    toggleBtn.addEventListener('click', () => {

        filtersBox.classList.toggle('active');

    });

}

</script>

<?php include "footer.php"; ?>

</body>
</html>