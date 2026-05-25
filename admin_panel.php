<?php
session_start();
require_once "polaczenie.php";

// sprawdzenie logowania
if(!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}

$admin_id = $_SESSION['id'];

// usuń, przywróć quiz
if(isset($_GET['usun'])){
    $id = (int)$_GET['usun'];
    
    $res = $polaczenie->query("SELECT autor_id, tytul FROM quizy WHERE id=$id");
    if($res && $res->num_rows > 0) {
        $quiz = $res->fetch_assoc();
        $autor_id = (int)$quiz['autor_id'];
        $tytul = $polaczenie->real_escape_string($quiz['tytul']);
        $tresc = "Administrator usunął Twój quiz o nazwie: \"$tytul\".";
        
        $polaczenie->query("INSERT INTO powiadomienia (uzytkownik_id, nadawca_id, typ, tresc, czy_odczytane, data_utworzenia) 
                            VALUES ($autor_id, $admin_id, 'system', '$tresc', 0, NOW())");
    }

    $polaczenie->query("UPDATE quizy SET czy_usuniety=1 WHERE id=$id");
    $tab = $_GET['tab'] ?? 'quizy';
    header("Location: admin_panel.php?tab=$tab");
    exit();
}

if(isset($_GET['przywroc'])){
    $id = (int)$_GET['przywroc'];
    
    $res = $polaczenie->query("SELECT autor_id, tytul FROM quizy WHERE id=$id");
    if($res && $res->num_rows > 0) {
        $quiz = $res->fetch_assoc();
        $autor_id = (int)$quiz['autor_id'];
        $tytul = $polaczenie->real_escape_string($quiz['tytul']);
        $tresc = "Administrator przywrócił Twój quiz o nazwie: \"$tytul\".";
        
        $polaczenie->query("INSERT INTO powiadomienia (uzytkownik_id, nadawca_id, typ, tresc, czy_odczytane, data_utworzenia) 
                            VALUES ($autor_id, $admin_id, 'system', '$tresc', 0, NOW())");
    }

    $polaczenie->query("UPDATE quizy SET czy_usuniety=0 WHERE id=$id");
    $tab = $_GET['tab'] ?? 'quizy';
    header("Location: admin_panel.php?tab=$tab");
    exit();
}

// odrzucanie zgłoszeń
if(isset($_GET['odrzuc_zgloszenie'])){
    $quiz_id = (int)$_GET['odrzuc_zgloszenie'];
    $polaczenie->query("UPDATE zgloszenia SET status_id=2 WHERE quiz_id=$quiz_id");
    $tab = $_GET['tab'] ?? 'zgloszenia';
    header("Location: admin_panel.php?tab=$tab");
    exit();
}

// banowanie użytkowników
if(isset($_POST['ban'])){
    $user_id = (int)$_POST['ban'];
    
    $check_admin_query = $polaczenie->query("SELECT id FROM uzytkownicy WHERE id=$user_id AND (id = $admin_id OR rola_id = 3)");
    
    if ($check_admin_query && $check_admin_query->num_rows > 0) {
        header("Location: admin_panel.php?tab=uzytkownicy&error=cant_ban_admin");
        exit();
    }

    $powod = $polaczenie->real_escape_string($_POST['powod']);
    $na_zawsze = isset($_POST['na_zawsze']) ? true : false;

    if($na_zawsze) {
        $date = '2099-12-31';
        $tresc = "Twoje konto zostało permanentnie zbanowane. Powód: $powod";
    } else {
        $dni = (int)$_POST['dni'];
        $date = date("Y-m-d", strtotime("+$dni days"));
        $tresc = "Twoje konto zostało zbanowane do $date. Powód: $powod";
    }

    $polaczenie->query("UPDATE bany SET czy_odbanowany=1 WHERE uzytkownik_id=$user_id");

    $polaczenie->query("INSERT INTO bany (uzytkownik_id, admin_id, powod, ban_do, czy_odbanowany)
                        VALUES ($user_id, $admin_id, '$powod', '$date', 0)");
                        
    $polaczenie->query("INSERT INTO powiadomienia (uzytkownik_id, nadawca_id, typ, tresc, czy_odczytane, data_utworzenia) 
                        VALUES ($user_id, $admin_id, 'system', '$tresc', 0, NOW())");
                        
    header("Location: admin_panel.php?tab=uzytkownicy");
    exit();
}

// odbanowywanie użytkowników
if(isset($_GET['odbanuj'])){
    $user_id = (int)$_GET['odbanuj'];
    $polaczenie->query("UPDATE bany SET czy_odbanowany=1 WHERE uzytkownik_id=$user_id");
    
    $tresc = "Twoje konto zostało odblokowane przez administratora. Witamy z powrotem!";
    $polaczenie->query("INSERT INTO powiadomienia (uzytkownik_id, nadawca_id, typ, tresc, czy_odczytane, data_utworzenia) 
                        VALUES ($user_id, $admin_id, 'system', '$tresc', 0, NOW())");
    
    $tab = $_GET['tab'] ?? 'bany';
    header("Location: admin_panel.php?tab=$tab");
    exit();
}

// wyszukiwarka - filtry
$tab = $_GET['tab'] ?? 'quizy';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'data_utworzenia';

$filter_kat = $_GET['kategoria_id'] ?? '';
$filter_premium = $_GET['premium'] ?? '';
$filter_data_od = $_GET['data_od'] ?? '';
$filter_data_do = $_GET['data_do'] ?? '';
$filter_liczba_pytan = $_GET['liczba_pytan'] ?? '';
$filter_ocena = $_GET['ocena'] ?? '';

$queryParams = $_GET;
unset($queryParams['tab']);
$search_query_param = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';

$allowed_sorts = ['tytul', 'kategoria', 'uzytkownik', 'data_utworzenia', 'ilosc_zgloszen', 'liczba_pytan', 'ocena'];
if(!in_array($sort, $allowed_sorts)){
    $sort = 'data_utworzenia';
}

$search_sql = $polaczenie->real_escape_string($search);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
<style>
    .table-container { margin-bottom: 2rem; }
</style>
</head>
<body>

<?php include "navbar.php"; ?>

<section class="section">
<div class="container">

<h1 class="title has-text-centered mb-5">Panel Administratora</h1>

<?php if(isset($_GET['error']) && $_GET['error'] == 'cant_ban_admin'): ?>
<div class="notification is-danger is-light">
    <strong>Błąd:</strong> Nie możesz zbanować samego siebie ani innych administratorów!
</div>
<?php endif; ?>

<div class="box">
    <form method="get">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
    
    <div class="columns is-multiline is-mobile">
        
        <div class="column is-4-desktop is-12-mobile">
            <label class="label">Szukaj (Tytuł/Użytkownik)</label>
            <div class="control">
                <input class="input" type="text" name="search" placeholder="Wpisz frazę..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        
        <div class="column is-3-desktop is-6-mobile">
            <label class="label">Kategoria</label>
            <div class="control">
                <div class="select is-fullwidth">
                    <select name="kategoria_id">
                        <option value="">Wszystkie</option>
                        <?php
                        $kat_res = $polaczenie->query("SELECT id, nazwa FROM kategorie ORDER BY nazwa ASC");
                        if($kat_res) {
                            while($k = $kat_res->fetch_assoc()) {
                                $sel = ($filter_kat == $k['id']) ? 'selected' : '';
                                echo "<option value='{$k['id']}' $sel>".htmlspecialchars($k['nazwa'])."</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="column is-2-desktop is-6-mobile">
            <label class="label">Premium</label>
            <div class="control">
                <div class="select is-fullwidth">
                    <select name="premium">
                        <option value="">Wszystkie</option>
                        <option value="1" <?= $filter_premium==='1'?'selected':'' ?>>Tak</option>
                        <option value="0" <?= $filter_premium==='0'?'selected':'' ?>>Nie</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="column is-3-desktop is-12-mobile">
            <label class="label">Sortowanie wyników</label>
            <div class="control">
                <div class="select is-fullwidth">
                    <select name="sort">
                        <option value="data_utworzenia" <?= $sort=='data_utworzenia'?'selected':'' ?>>Najnowsze</option>
                        <option value="tytul" <?= $sort=='tytul'?'selected':'' ?>>Tytuł (A-Z)</option>
                        <option value="kategoria" <?= $sort=='kategoria'?'selected':'' ?>>Kategoria (A-Z)</option>
                        <option value="uzytkownik" <?= $sort=='uzytkownik'?'selected':'' ?>>Użytkownik (A-Z)</option>
                        <option value="liczba_pytan" <?= $sort=='liczba_pytan'?'selected':'' ?>>Najwięcej pytań</option>
                        <option value="ocena" <?= $sort=='ocena'?'selected':'' ?>>Najwyżej oceniane</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="column is-2-desktop is-6-mobile">
            <label class="label">Data od</label>
            <div class="control">
                <input class="input" type="date" name="data_od" value="<?= htmlspecialchars($filter_data_od) ?>">
            </div>
        </div>
        
        <div class="column is-2-desktop is-6-mobile">
            <label class="label">Data do</label>
            <div class="control">
                <input class="input" type="date" name="data_do" value="<?= htmlspecialchars($filter_data_do) ?>">
            </div>
        </div>
        
        <div class="column is-2-desktop is-6-mobile">
            <label class="label">Min. liczba pytań</label>
            <div class="control">
                <input class="input" type="number" min="0" name="liczba_pytan" placeholder="Np. 10" value="<?= htmlspecialchars($filter_liczba_pytan) ?>">
            </div>
        </div>

        <div class="column is-2-desktop is-6-mobile">
            <label class="label">Min. ocena (1-5)</label>
            <div class="control">
                <input class="input" type="number" min="1" max="5" step="0.1" name="ocena" placeholder="Np. 4.5" value="<?= htmlspecialchars($filter_ocena) ?>">
            </div>
        </div>
        
        <div class="column is-4-desktop is-12-mobile is-flex is-align-items-flex-end">
            <button class="button is-info is-fullwidth">
                <span class="icon"><i class="fas fa-search"></i></span>
                <span>Zastosuj filtry</span>
            </button>
            <a href="?tab=<?= htmlspecialchars($tab) ?>" class="button is-light ml-2" title="Resetuj filtry">Reset</a>
        </div>

    </div>
    </form>
</div>

<!-- zakładki -->

<div class="tabs is-centered is-boxed is-small-mobile">
  <ul>
    <li class="<?= $tab == 'quizy' ? 'is-active' : '' ?>">
        <a href="?tab=quizy<?= $search_query_param ?>"><span>📚 Wszystkie Quizy</span></a>
    </li>
    <li class="<?= $tab == 'usuniete_quizy' ? 'is-active' : '' ?>">
        <a href="?tab=usuniete_quizy<?= $search_query_param ?>"><span>🗑️ Usunięte Quizy</span></a>
    </li>
    <li class="<?= $tab == 'uzytkownicy' ? 'is-active' : '' ?>">
        <a href="?tab=uzytkownicy<?= $search_query_param ?>"><span>👥 Użytkownicy</span></a>
    </li>
    <li class="<?= $tab == 'uzytkownicy_usuniete' ? 'is-active' : '' ?>">
        <a href="?tab=uzytkownicy_usuniete<?= $search_query_param ?>"><span>🚫 Użyt. z usuniętymi</span></a>
    </li>
    <li class="<?= $tab == 'zgloszenia' ? 'is-active' : '' ?>">
        <a href="?tab=zgloszenia<?= $search_query_param ?>"><span>⚠️ Zgłoszenia</span></a>
    </li>
    <li class="<?= $tab == 'bany' ? 'is-active' : '' ?>">
        <a href="?tab=bany<?= $search_query_param ?>"><span>⛔ Bany</span></a>
    </li>
  </ul>
</div>

<!-- zakł. quizy/usunięte quizy -->

<?php if($tab == 'quizy' || $tab == 'usuniete_quizy'): ?>
<div>
    <div class="table-container">
      <table class="table is-fullwidth is-striped is-hoverable is-size-7-mobile">
          <thead>
              <tr>
                  <th>Tytuł</th>
                  <th>Statystyki</th>
                  <th>Kategoria</th>
                  <th>Data utw.</th>
                  <th>Użytkownik</th>
                  <th>Status</th>
                  <th>Akcje</th>
              </tr>
          </thead>
          <tbody>
          <?php
          $is_deleted = ($tab == 'usuniete_quizy') ? 1 : 0;
          
          $query_quizy = "SELECT q.*, u.nazwa as uzytkownik, k.nazwa AS kategoria,
                          (SELECT COUNT(id) FROM pytania WHERE id_quizu = q.id) AS liczba_pytan,
                          (SELECT IFNULL(AVG(ocena), 0) FROM oceny_quizu WHERE quiz_id = q.id) AS ocena_srednia
                          FROM quizy q 
                          LEFT JOIN kategorie k ON q.kategoria_id = k.id 
                          JOIN uzytkownicy u ON q.autor_id = u.id 
                          WHERE q.czy_usuniety=$is_deleted";
          
          // filtrowanie
          if($search != ""){
              $query_quizy .= " AND (q.tytul LIKE '%$search_sql%' OR u.nazwa LIKE '%$search_sql%')";
          }
          if($filter_kat != "") {
              $query_quizy .= " AND q.kategoria_id = " . (int)$filter_kat;
          }
          if($filter_premium === "1" || $filter_premium === "0") {
              $query_quizy .= " AND q.czy_premium = " . (int)$filter_premium;
          }
          if($filter_data_od != "") {
              $query_quizy .= " AND DATE(q.data_utworzenia) >= '" . $polaczenie->real_escape_string($filter_data_od) . "'";
          }
          if($filter_data_do != "") {
              $query_quizy .= " AND DATE(q.data_utworzenia) <= '" . $polaczenie->real_escape_string($filter_data_do) . "'";
          }

          $having = [];
          if($filter_liczba_pytan != "") {
              $having[] = "liczba_pytan >= " . (int)$filter_liczba_pytan;
          }
          if($filter_ocena != "") {
              $having[] = "ocena_srednia >= " . (float)$filter_ocena;
          }

          if(!empty($having)) {
              $query_quizy .= " HAVING " . implode(" AND ", $having);
          }
          
          // sortowanie
          $order_by = "q.data_utworzenia DESC";
          if($sort == 'tytul') $order_by = "q.tytul ASC";
          if($sort == 'kategoria') $order_by = "kategoria ASC";
          if($sort == 'uzytkownik') $order_by = "uzytkownik ASC";
          if($sort == 'liczba_pytan') $order_by = "liczba_pytan DESC";
          if($sort == 'ocena') $order_by = "ocena_srednia DESC";

          $query_quizy .= " ORDER BY $order_by";
          
          // wykonanie filtru
          $res = $polaczenie->query($query_quizy);

          if($res && $res->num_rows > 0):
              while($row = $res->fetch_assoc()): ?>
                  <tr>
                      <td>
                          <strong><?= htmlspecialchars($row['tytul']) ?></strong><br>
                          <?= isset($row['czy_premium']) && $row['czy_premium'] ? '<span class="tag is-warning is-light is-small mt-1">Premium</span>' : '' ?>
                      </td>
                      <td>
                          <span class="icon-text is-size-7">
                            <span class="icon has-text-info"><i class="fas fa-list-ol"></i></span>
                            <span><?= $row['liczba_pytan'] ?> pyt.</span>
                          </span><br>
                          <span class="icon-text is-size-7">
                            <span class="icon has-text-warning"><i class="fas fa-star"></i></span>
                            <span><?= round($row['ocena_srednia'], 2) ?></span>
                          </span>
                      </td>
                      <td><?= htmlspecialchars($row['kategoria'] ?? 'Brak') ?></td>
                      <td><?= date('d.m.Y H:i', strtotime($row['data_utworzenia'])) ?></td>
                      <td><strong><a href="profil.php?id=<?= $row['autor_id'] ?>"><?= htmlspecialchars($row['uzytkownik']) ?></a></strong></td>
                      <td>
                          <?php if($row['czy_usuniety']): ?>
                              <span class="tag is-danger">Usunięty</span>
                          <?php else: ?>
                              <span class="tag is-success">Aktywny</span>
                          <?php endif; ?>
                      </td>
                      <td>
                          <a class="button is-small is-link is-outlined mb-1" href="quiz_gra.php?id=<?= $row['id'] ?>" target="_blank">Rozwiąż</a>
                          <button class="button is-small is-info is-outlined mb-1" onclick="openPreviewModal(<?= $row['id'] ?>)">Podgląd</button>
                          
                          <?php if($row['czy_usuniety']): ?>
                              <a class="button is-small is-success is-outlined" href="?przywroc=<?= $row['id'] ?>&tab=usuniete_quizy" onclick="return confirm('Przywrócić ten quiz?');">Przywróć</a>
                          <?php else: ?>
                              <a class="button is-small is-danger is-outlined" href="?usun=<?= $row['id'] ?>&tab=quizy" onclick="return confirm('Na pewno chcesz usunąć ten quiz?');">Usuń</a>
                          <?php endif; ?>
                      </td>
                  </tr>

                  <div id="previewModal-<?= $row['id'] ?>" class="modal">
                    <div class="modal-background" onclick="closePreviewModal(<?= $row['id'] ?>)"></div>
                    <div class="modal-card">
                      <header class="modal-card-head">
                        <p class="modal-card-title">Podgląd: <?= htmlspecialchars($row['tytul']) ?></p>
                        <button class="delete" aria-label="close" onclick="closePreviewModal(<?= $row['id'] ?>)"></button>
                      </header>
                      <section class="modal-card-body">
                        <h4 class="title is-6">Opis quizu:</h4>
                        <p class="mb-4"><?= nl2br(htmlspecialchars($row['opis'] ?? 'Brak opisu')) ?></p>
                        <hr>
                        <h4 class="title is-6">Pytania (<?= $row['liczba_pytan'] ?>):</h4>
                        <ul class="list">
                            <?php
                            $pytania_query = $polaczenie->query("SELECT tresc FROM pytania WHERE id_quizu = {$row['id']}");
                            if($pytania_query && $pytania_query->num_rows > 0) {
                                $i = 1;
                                while($pyt = $pytania_query->fetch_assoc()) {
                                    echo "<li class='mb-2'><strong>$i.</strong> " . htmlspecialchars($pyt['tresc']) . "</li>";
                                    $i++;
                                }
                            } else {
                                echo "<li>Brak pytań lub błąd struktury.</li>";
                            }
                            ?>
                        </ul>
                      </section>
                    </div>
                  </div>

              <?php endwhile; 
          else: ?>
              <tr><td colspan="7" class="has-text-centered">Brak wyników dopasowanych do filtrów.</td></tr>
          <?php endif; ?>
          </tbody>
      </table>
    </div>
</div>
<?php endif; ?>

<!-- zakł. użytkownicy -->

<?php if($tab == 'uzytkownicy'): ?>
<div>
    <div class="table-container">
      <table class="table is-fullwidth is-striped is-hoverable">
          <thead>
              <tr>
                  <th>ID</th>
                  <th>Nazwa Użytkownika</th>
                  <th>Status Konta</th>
                  <th>Akcje</th>
              </tr>
          </thead>
          <tbody>
          <?php
          $query_users = "SELECT u.id, u.nazwa, u.email, r.nazwa AS nazwa_roli, u.premium_do, u.data_utworzenia, b.ban_do 
                          FROM uzytkownicy u 
                          LEFT JOIN bany b ON b.uzytkownik_id = u.id AND b.ban_do >= CURDATE() AND b.czy_odbanowany = 0
                          LEFT JOIN role r ON u.rola_id = r.id
                          WHERE 1=1";
                          
          if($search != ""){
              $query_users .= " AND u.nazwa LIKE '%$search_sql%'";
          }
          
          $order_u = ($sort == 'uzytkownik') ? "u.nazwa ASC" : "u.id DESC";
          $query_users .= " ORDER BY $order_u";

          $res = $polaczenie->query($query_users);

          if($res && $res->num_rows > 0):
              while($row = $res->fetch_assoc()): 
                  $is_banned = !empty($row['ban_do']);
              ?>
                  <tr>
                      <td><?= $row['id'] ?></td>
                      <td><strong><a href="profil.php?id=<?= $row['id'] ?>"><?= htmlspecialchars($row['nazwa']) ?></a></strong></td>
                      <td>
                          <?php if($is_banned): ?>
                              <span class="tag is-danger">Zbanowany (do: <?= $row['ban_do'] == '2099-12-31' ? 'Na zawsze' : date('d.m.Y', strtotime($row['ban_do'])) ?>)</span>
                          <?php else: ?>
                              <span class="tag is-success">Aktywny</span>
                          <?php endif; ?>
                      </td>
                      <td>
                          <button class="button is-small is-info is-outlined" 
                                  data-nazwa="<?= htmlspecialchars($row['nazwa']) ?>"
                                  data-email="<?= htmlspecialchars($row['email'] ?? 'Brak') ?>"
                                  data-rola="<?= htmlspecialchars($row['nazwa_roli'] ?? 'Brak roli') ?>"
                                  data-premium="<?= $row['premium_do'] ? date('d.m.Y H:i', strtotime($row['premium_do'])) : 'Brak' ?>"
                                  data-data="<?= date('d.m.Y H:i', strtotime($row['data_utworzenia'] ?? time())) ?>"
                                  onclick="openInspectModal(this)">
                              Inspekcja
                          </button>

                          <?php if($is_banned): ?>
                              <a href="?odbanuj=<?= $row['id'] ?>&tab=uzytkownicy" class="button is-small is-success" onclick="return confirm('Czy na pewno chcesz odbanować tego użytkownika?');">Odbanuj</a>
                          <?php else: ?>
                              <button class="button is-small is-warning" onclick="openBanModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nazwa'])) ?>')">Nałóż Ban</button>
                          <?php endif; ?>
                      </td>
                  </tr>
              <?php endwhile; 
          else: ?>
              <tr><td colspan="4" class="has-text-centered">Brak użytkowników.</td></tr>
          <?php endif; ?>
          </tbody>
      </table>
    </div>
</div>
<?php endif; ?>

<!-- zakł. usunięci użytkownicy -->

<?php if($tab == 'uzytkownicy_usuniete'): ?>
<div>
    <div class="table-container">
      <table class="table is-fullwidth is-striped is-hoverable">
          <thead>
              <tr>
                  <th>ID</th>
                  <th>Nazwa Użytkownika</th>
                  <th>Ilość usuniętych quizów</th>
                  <th>Akcje</th>
              </tr>
          </thead>
          <tbody>
          <?php
          $q_del_users = "SELECT u.id, u.nazwa, COUNT(q.id) as usuniete_liczba 
                          FROM uzytkownicy u 
                          JOIN quizy q ON u.id = q.autor_id 
                          WHERE q.czy_usuniety = 1 ";
                          
          if($search != ""){
              $q_del_users .= " AND u.nazwa LIKE '%$search_sql%' ";
          }
          
          $q_del_users .= " GROUP BY u.id, u.nazwa ORDER BY usuniete_liczba DESC";
                          
          $res_del = $polaczenie->query($q_del_users);

          if($res_del && $res_del->num_rows > 0):
              while($row = $res_del->fetch_assoc()): ?>
                  <tr>
                      <td><?= $row['id'] ?></td>
                      <td><strong><a href="profil.php?id=<?= $row['id'] ?>"><?= htmlspecialchars($row['nazwa']) ?></a></strong></td>
                      <td><span class="tag is-danger is-medium"><?= $row['usuniete_liczba'] ?></span></td>
                      <td>
                          <a href="?tab=usuniete_quizy&search=<?= urlencode($row['nazwa']) ?>" class="button is-small is-info is-outlined">
                              <span class="icon"><i class="fas fa-search"></i></span>
                              <span>Pokaż quizy użytkownika</span>
                          </a>
                      </td>
                  </tr>
              <?php endwhile; 
          else: ?>
              <tr><td colspan="4" class="has-text-centered">Super! Żaden użytkownik nie posiada usuniętych quizów.</td></tr>
          <?php endif; ?>
          </tbody>
      </table>
    </div>
</div>
<?php endif; ?>

<!-- zakł. zgłoszenia -->

<?php if($tab == 'zgloszenia'): ?>
<div>
    <?php 
    $order_zgl = ($sort == 'ilosc_zgloszen') ? "liczba_zgloszen DESC" : "liczba_zgloszen DESC";

    $quizy_zgloszone = $polaczenie->query("SELECT q.id, q.tytul, q.opis, COUNT(z.id) as liczba_zgloszen 
                                           FROM zgloszenia z 
                                           JOIN quizy q ON z.quiz_id = q.id 
                                           WHERE z.status_id = 1 
                                           GROUP BY q.id, q.tytul, q.opis 
                                           ORDER BY $order_zgl");
    
    if($quizy_zgloszone && $quizy_zgloszone->num_rows > 0):
        while($quiz = $quizy_zgloszone->fetch_assoc()): ?>
            <div class="box">
                <div class="level is-mobile mb-2">
                    <div class="level-left">
                        <div class="level-item">
                            <h3 class="title is-5 mb-0">Zgłoszenia dla: <span class="has-text-info"><?= htmlspecialchars($quiz['tytul']) ?></span></h3>
                        </div>
                        <div class="level-item">
                            <span class="tag is-danger"><?= $quiz['liczba_zgloszen'] ?> zgłoszeń</span>
                        </div>
                    </div>
                    <div class="level-right">
                        <a class="button is-small is-link mr-2" href="quiz_gra.php?id=<?= $quiz['id'] ?>" target="_blank">Rozwiąż ten quiz</a>
                        <button class="button is-small is-info mr-2" onclick="openPreviewModal(<?= $quiz['id'] ?>)">Podgląd Pytań</button>
                        <a class="button is-small is-warning mr-2" href="?odrzuc_zgloszenie=<?= $quiz['id'] ?>&tab=zgloszenia" onclick="return confirm('Czy odrzucić wszystkie zgłoszenia dla tego quizu i oznaczyć je jako rozwiązane?');">Odrzuć zgłoszenie</a>
                        <a class="button is-small is-danger" href="?usun=<?= $quiz['id'] ?>&tab=zgloszenia" onclick="return confirm('Usunąć quiz i zamknąć zgłoszenia?');">Usuń Quiz</a>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table is-fullwidth is-narrow is-striped text-is-small">
                        <thead>
                            <tr>
                                <th>Kto zgłosił (Zgłaszający)</th>
                                <th>Kategoria zgłoszenia</th>
                                <th>Uzasadnienie / Powód</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $zgloszenia = $polaczenie->query("SELECT z.powod, z.data_utworzenia as data_zgloszenia, u.nazwa AS user, u.id AS zglaszajacy_id, z.kategoria AS kategoria 
                                                          FROM zgloszenia z JOIN uzytkownicy u ON z.zglaszajacy_id = u.id 
                                                          WHERE z.quiz_id = {$quiz['id']} AND z.status_id = 1");
                        while($z = $zgloszenia->fetch_assoc()): ?>
                            <tr>
                                <td><strong><a href="profil.php?id=<?= $z['zglaszajacy_id'] ?>"><?= htmlspecialchars($z['user']) ?></a></strong></td>
                                <td><span class="tag is-warning is-light"><?= htmlspecialchars($z['kategoria']) ?></span></td>
                                <td><?= htmlspecialchars($z['powod']) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($z['data_zgloszenia'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div id="previewModal-<?= $quiz['id'] ?>" class="modal">
                  <div class="modal-background" onclick="closePreviewModal(<?= $quiz['id'] ?>)"></div>
                  <div class="modal-card">
                    <header class="modal-card-head">
                      <p class="modal-card-title">Podgląd: <?= htmlspecialchars($quiz['tytul']) ?></p>
                      <button class="delete" aria-label="close" onclick="closePreviewModal(<?= $quiz['id'] ?>)"></button>
                    </header>
                    <section class="modal-card-body">
                      <h4 class="title is-6">Opis quizu:</h4>
                      <p class="mb-4"><?= nl2br(htmlspecialchars($quiz['opis'] ?? 'Brak opisu')) ?></p>
                      <hr>
                      <h4 class="title is-6">Pytania:</h4>
                      <ul class="list">
                          <?php
                          $pytania_query = $polaczenie->query("SELECT tresc FROM pytania WHERE id_quizu = {$quiz['id']}");
                          if($pytania_query && $pytania_query->num_rows > 0) {
                              $i = 1;
                              while($pyt = $pytania_query->fetch_assoc()) {
                                  echo "<li class='mb-2'><strong>$i.</strong> " . htmlspecialchars($pyt['tresc']) . "</li>";
                                  $i++;
                              }
                          } else {
                              echo "<li>Brak pytań lub błąd struktury.</li>";
                          }
                          ?>
                      </ul>
                    </section>
                  </div>
                </div>

            </div>
        <?php endwhile; 
    else: ?>
        <div class="notification is-success is-light">Brak oczekujących zgłoszeń. Dobra robota!</div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- zakł. bany -->

<?php if($tab == 'bany'): ?>
<div>
    <div class="table-container">
      <table class="table is-fullwidth is-striped is-hoverable">
          <thead>
              <tr>
                  <th>Zbanowany Użytkownik</th>
                  <th>Powód Blokady</th>
                  <th>Zbanowany przez (ID Admina)</th>
                  <th>Ban wygasa</th>
                  <th>Akcje</th>
              </tr>
          </thead>
          <tbody>
          <?php
          $query_bany = "SELECT b.uzytkownik_id, u.nazwa as uzytkownik, b.powod, b.ban_do, b.admin_id 
                         FROM bany b 
                         JOIN uzytkownicy u ON b.uzytkownik_id = u.id 
                         WHERE b.ban_do >= CURDATE() AND b.czy_odbanowany = 0 
                         ORDER BY b.ban_do ASC";
                         
          $res_bany = $polaczenie->query($query_bany);

          if($res_bany && $res_bany->num_rows > 0):
              while($ban = $res_bany->fetch_assoc()): ?>
                  <tr>
                      <td><strong><a href="profil.php?id=<?= $ban['uzytkownik_id'] ?>"><?= htmlspecialchars($ban['uzytkownik']) ?></a></strong></td>
                      <td><?= htmlspecialchars($ban['powod']) ?></td>
                      <td><?= $ban['admin_id'] ?></td>
                      <td>
                          <?php if($ban['ban_do'] == '2099-12-31'): ?>
                              <span class="tag is-danger">Na zawsze</span>
                          <?php else: ?>
                              <span class="tag is-warning"><?= date('d.m.Y', strtotime($ban['ban_do'])) ?></span>
                          <?php endif; ?>
                      </td>
                      <td>
                          <a href="?odbanuj=<?= $ban['uzytkownik_id'] ?>&tab=bany" class="button is-small is-success" onclick="return confirm('Czy na pewno chcesz odbanować tego użytkownika?');">Odbanuj</a>
                      </td>
                  </tr>
              <?php endwhile; 
          else: ?>
              <tr><td colspan="5" class="has-text-centered">Brak aktywnych banów na kontach użytkowników.</td></tr>
          <?php endif; ?>
          </tbody>
      </table>
    </div>
</div>
<?php endif; ?>

</div>
</section>

<!-- modal banowanie -->

<div id="banModal" class="modal">
  <div class="modal-background" onclick="closeBanModal()"></div>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Banowanie: <span id="banUserName"></span></p>
      <button class="delete" aria-label="close" onclick="closeBanModal()"></button>
    </header>
    <section class="modal-card-body">
      <form method="POST" id="banForm" onsubmit="return confirm('Potwierdzasz nałożenie blokady na to konto?');">
        <input type="hidden" name="ban" id="banUserId">

        <div class="field">
            <label class="checkbox has-text-danger has-text-weight-bold mb-3">
              <input type="checkbox" name="na_zawsze" id="banForever" onchange="toggleBanDays()">
              Zbanuj na zawsze (Permaban)
            </label>
        </div>

        <div class="field" id="banDaysField">
          <label class="label">Ilość dni</label>
          <div class="control">
            <input class="input" type="number" name="dni" id="banDaysInput" min="1" value="7">
          </div>
        </div>

        <div class="field">
          <label class="label">Powód blokady</label>
          <div class="control">
            <textarea class="textarea" name="powod" required placeholder="Napisz dlaczego użytkownik zostaje zbanowany..."></textarea>
          </div>
        </div>
      </form>
    </section>
    <footer class="modal-card-foot">
      <button type="submit" form="banForm" class="button is-danger">Zatwierdź Ban</button>
      <button class="button" onclick="closeBanModal()">Anuluj</button>
    </footer>
  </div>
</div>

<!-- modal inspekcji uż. -->

<div id="inspectModal" class="modal">
  <div class="modal-background" onclick="closeInspectModal()"></div>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Inspekcja użytkownika</p>
      <button class="delete" aria-label="close" onclick="closeInspectModal()"></button>
    </header>
    <section class="modal-card-body">
      <ul style="font-size: 1.1rem; line-height: 2;">
          <li><strong>Nazwa konta:</strong> <span id="inspNazwa" class="has-text-info"></span></li>
          <li><strong>Adres e-mail:</strong> <span id="inspEmail"></span></li>
          <li><strong>Rola w serwisie:</strong> <span id="inspRola"></span></li>
          <li><strong>Data utworzenia:</strong> <span id="inspData"></span></li>
          <li><strong>Premium do:</strong> <span id="inspPremium"></span></li>
      </ul>
    </section>
    <footer class="modal-card-foot">
      <button class="button" onclick="closeInspectModal()">Zamknij</button>
    </footer>
  </div>
</div>

<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script>
function openBanModal(userId, userName){
    document.getElementById("banModal").classList.add("is-active");
    document.getElementById("banUserId").value = userId;
    document.getElementById("banUserName").innerText = userName;
    document.getElementById("banForever").checked = false;
    toggleBanDays();
}

function closeBanModal(){
    document.getElementById("banModal").classList.remove("is-active");
}

function toggleBanDays(){
    const isForever = document.getElementById("banForever").checked;
    const daysField = document.getElementById("banDaysField");
    const daysInput = document.getElementById("banDaysInput");
    
    if(isForever) {
        daysField.style.display = 'none';
        daysInput.removeAttribute('required');
    } else {
        daysField.style.display = 'block';
        daysInput.setAttribute('required', 'required');
    }
}

function openPreviewModal(quizId){
    const modal = document.getElementById("previewModal-" + quizId);
    if(modal) modal.classList.add("is-active");
}

function closePreviewModal(quizId){
    const modal = document.getElementById("previewModal-" + quizId);
    if(modal) modal.classList.remove("is-active");
}

function openInspectModal(btn) {
    document.getElementById('inspNazwa').innerText = btn.dataset.nazwa;
    document.getElementById('inspEmail').innerText = btn.dataset.email;
    document.getElementById('inspRola').innerText = btn.dataset.rola;
    document.getElementById('inspData').innerText = btn.dataset.data;
    document.getElementById('inspPremium').innerText = btn.dataset.premium;
    document.getElementById('inspectModal').classList.add('is-active');
}

function closeInspectModal() {
    document.getElementById('inspectModal').classList.remove('is-active');
}
</script>

<?php include "footer.php"; ?>

</body>
</html>