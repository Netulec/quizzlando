<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "polaczenie.php";

//sprawdzenie czy konto usunięte
if (isset($_SESSION['id'])) {
    $sprawdz_usuniecie = $polaczenie->prepare("SELECT czy_usuniety FROM uzytkownicy WHERE id = ?");
    $sprawdz_usuniecie->bind_param("i", $_SESSION['id']);
    $sprawdz_usuniecie->execute();
    $wynik_usuniecia = $sprawdz_usuniecie->get_result();
    
    if ($wynik_usuniecia->num_rows > 0) {
        $user_del = $wynik_usuniecia->fetch_assoc();
        if ($user_del['czy_usuniety'] == 1) {
            // Konto usunięte - niszczymy sesję i wyrzucamy na logowanie
            session_unset();
            session_destroy();
            header("Location: logowanie.php?konto_usuniete=1");
            exit();
        }
    }
    $sprawdz_usuniecie->close();
}

// system banów
if(isset($_SESSION['id'])) {
    $user_id = $_SESSION['id'];
    
    // Sprawdzenie czy uż. ma akt. bana
    $ban_query = $polaczenie->query("SELECT powod, ban_do FROM bany WHERE uzytkownik_id=$user_id AND ban_do >= CURDATE() AND czy_odbanowany=0 ORDER BY ban_do DESC LIMIT 1");
    
    if($ban_query && $ban_query->num_rows > 0) {
        $ban_data = $ban_query->fetch_assoc();
        $ban_wygasa = ($ban_data['ban_do'] == '2099-12-31') ? 'Na zawsze (Permaban)' : date('d.m.Y', strtotime($ban_data['ban_do']));
        
        // wyświetlenie bana i blokada strony
        ?>
        <!DOCTYPE html>
        <html lang="pl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Konto Zbanowane - Quizzlando</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body class="has-background-light" style="min-height: 100vh; display: flex; align-items: center; justify-content: center;">
            <div class="box has-text-centered" style="border: 2px solid #f14668; max-width: 600px; width: 100%; padding: 3rem; margin: 1rem;">
                <span class="icon is-large mb-4"><i class="fas fa-ban fa-3x has-text-danger"></i></span>
                <h1 class="title has-text-danger is-3">Twoje konto zostało zbanowane</h1>
                
                <div class="content is-medium mt-5 mb-5 text-is-left" style="text-align: left; background: #fff5f7; padding: 1.5rem; border-radius: 8px;">
                    <p><strong>Powód blokady:</strong> <br> <?= nl2br(htmlspecialchars($ban_data['powod'])) ?></p>
                    <p><strong>Blokada wygasa:</strong> <br> <span class="tag is-danger is-medium mt-1"><?= $ban_wygasa ?></span></p>
                </div>
                
                <p class="mb-5 is-size-5">
                    W celu odwołania się od decyzji, skontaktuj się z administracją poprzez e-mail:<br>
                    <strong><a href="mailto:quizzlando@taxsa.pl" class="has-text-link">quizzlando@taxsa.pl</a></strong>
                </p>
                <a href="wyloguj.php" class="button is-danger is-medium">Wyloguj się z konta</a>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
}

// navbar
$rola_id = 0;
$premium_do = null;
$powiadomienia = [];
$liczba_powiadomien = 0;

if(isset($_SESSION['id'])) {
    $user_id = $_SESSION['id'];

    $res = $polaczenie->query("SELECT rola_id, premium_do FROM uzytkownicy WHERE id='$user_id' LIMIT 1");
    if($res && $res->num_rows > 0) {
        $dane = $res->fetch_assoc();
        $rola_id = (int)$dane['rola_id'];
        $premium_do = $dane['premium_do'];
    }

    if($rola_id == 2 && $premium_do !== NULL && strtotime($premium_do) < time()) {
        $polaczenie->query("UPDATE uzytkownicy SET rola_id=1, premium_do=NULL WHERE id='$user_id'");
        $rola_id = 1;
        $premium_do = null;
    }

    if(isset($_POST['testuj_premium']) && $rola_id != 3) {
        $nowa_data = date("Y-m-d H:i:s", strtotime("+7 days"));
        $polaczenie->query("UPDATE uzytkownicy SET premium_do='$nowa_data', rola_id=2 WHERE id='$user_id'");
        echo "<script>window.location.replace('logowanie.php?msg=premium_aktywowane');</script>";
        exit();
    }
    
    if(isset($_GET['odczytaj_pow'])) {
        $id_pow = (int)$_GET['odczytaj_pow'];
        $polaczenie->query("UPDATE powiadomienia SET czy_odczytane=1 WHERE id='$id_pow' AND uzytkownik_id='$user_id'");
        
        $queryParams = $_GET;
        unset($queryParams['odczytaj_pow']);
        
        $redirectUrl = $_SERVER['PHP_SELF'];
        if(!empty($queryParams)) {
            $redirectUrl .= '?' . http_build_query($queryParams);
        }
        
        echo "<script>window.location.replace('$redirectUrl');</script>";
        exit();
    }

    $res_pow = $polaczenie->query("
        SELECT p.id, p.tresc, p.typ, p.nadawca_id, p.data_utworzenia, u.nazwa as nazwa_nadawcy 
        FROM powiadomienia p 
        LEFT JOIN uzytkownicy u ON p.nadawca_id = u.id 
        WHERE p.uzytkownik_id='$user_id' AND p.czy_odczytane=0 
        ORDER BY p.id DESC
    ");
    if($res_pow) {
        while($row = $res_pow->fetch_assoc()) {
            $powiadomienia[] = $row;
        }
    }
    $liczba_powiadomien = count($powiadomienia);
}

$show_premium_banner = isset($_SESSION['id']) && $rola_id == 1 && !isset($_COOKIE['hide_premium_banner']);
?>

<style>
  .bell-badge {
    position: absolute;
    top: 6px;
    right: 4px;
    background-color: #f14668;
    color: white;
    border-radius: 50%;
    padding: 0.15rem 0.4rem;
    font-size: 0.7rem;
    font-weight: bold;
    line-height: 1;
  }
  .notif-dropdown {
    width: 350px;
    white-space: normal;
  }
  .notif-item {
    padding: 12px 16px;
    color: #4a4a4a;
  }
  .notif-time {
    display: block;
    font-size: 0.75rem;
    color: #999;
    margin-top: 2px;
  }
  .mark-read-btn {
    font-size: 0.75rem;
    color: #3273dc;
    text-decoration: none;
    font-weight: bold;
  }
  .mark-read-btn:hover {
    text-decoration: underline;
  }
  .nav-action-btn {
    width: 28px;
    height: 28px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    color: white;
    font-size: 0.85rem;
  }
  .nav-action-btn.is-accept { background-color: #48c78e; }
  .nav-action-btn.is-accept:hover { background-color: #3ec487; }
  .nav-action-btn.is-reject { background-color: #f14668; }
  .nav-action-btn.is-reject:hover { background-color: #f03a5f; }
</style>

<!-- wytestuj premium -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php if($show_premium_banner): ?>
<div class="notification is-warning mb-0" id="premium-banner" style="border-radius:0;">
  <div class="container">
    <button class="delete" aria-label="zamknij" onclick="zamknijBannerPremium()"></button>
    <div class="level">
      <div class="level-left">
        <strong>🔥 Wytestuj premium przez tydzień za darmo!</strong>
      </div>
      <div class="level-right mr-5">
        <a href="?popup=1" class="button is-primary">Wypróbuj</a>
      </div>
    </div>
  </div>
</div>

<script>
function zamknijBannerPremium() {
    document.getElementById('premium-banner').style.display = 'none';
    const date = new Date();
    date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
    document.cookie = "hide_premium_banner=1; expires=" + date.toUTCString() + "; path=/";
}
</script>
<?php endif; ?>

<?php if(isset($_GET['popup']) && $show_premium_banner): ?>
<div class="modal is-active">
  <div class="modal-background"></div>
  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Aktywacja premium</p>
      <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="delete"></a>
    </header>
    <section class="modal-card-body">
      Czy na pewno chcesz aktywować darmowy tydzień premium?
    </section>
    <footer class="modal-card-foot">
      <form method="post">
        <button name="testuj_premium" class="button is-success">Tak, aktywuj</button>
      </form>
      <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>" class="button">Anuluj</a>
    </footer>
  </div>
</div>
<?php endif; ?>

<nav class="navbar is-primary" role="navigation">
  <div class="container">
    
    <div class="navbar-brand">
      <a class="navbar-item" href="index.php">
        <img src="logo.png" alt="Quizzlando Logo" style="margin-right: 10px; max-height: 2rem;">
        <strong>Quizzlando</strong>
      </a>
      <a role="button" class="navbar-burger" data-target="navbarBasic">
        <span></span>
        <span></span>
        <span></span>
      </a>
    </div>

<!-- navbar menu -->
    <div id="navbarBasic" class="navbar-menu">
      <div class="navbar-end">

        <?php if(isset($_SESSION['id'])): ?>

          <div class="navbar-item has-dropdown is-hoverable">
            <a class="navbar-link is-arrowless" style="position: relative; padding-right: 24px;">
              <i class="fas fa-bell"></i>
              <?php if($liczba_powiadomien > 0): ?>
                <span class="bell-badge"><?= $liczba_powiadomien ?></span>
              <?php endif; ?>
            </a>
            
            <div class="navbar-dropdown is-right notif-dropdown">
              <?php if($liczba_powiadomien > 0): ?>
                  <?php foreach($powiadomienia as $p): ?>
                      <div class="notif-item" style="border-bottom: 1px solid #ededed;">
                        
                        <?php if($p['typ'] == 'zaproszenie'): ?>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                              <div>
                                <strong><?= htmlspecialchars($p['nazwa_nadawcy']) ?></strong> <?= htmlspecialchars($p['tresc']) ?>
                                <span class="notif-time"><?= date("d.m.Y H:i", strtotime($p['data_utworzenia'])) ?></span>
                              </div>
                              <div style="display: flex; gap: 5px; flex-shrink: 0;">
                                <a href="znajomi.php?nav_akcja=akceptuj&nadawca_id=<?= $p['nadawca_id'] ?>&pow_id=<?= $p['id'] ?>" class="nav-action-btn is-accept" title="Akceptuj">
                                  <i class="fas fa-check"></i>
                                </a>
                                <a href="znajomi.php?nav_akcja=odrzuc&nadawca_id=<?= $p['nadawca_id'] ?>&pow_id=<?= $p['id'] ?>" class="nav-action-btn is-reject" title="Odrzuć">
                                  <i class="fas fa-times"></i>
                                </a>
                              </div>
                            </div>
                        <?php else: ?>
                            <div>
                              <a href="profil.php?id=<?= $p['nadawca_id'] ?>" style="font-weight: bold; color: #3273dc;">
                                <?= htmlspecialchars($p['nazwa_nadawcy'] ?? 'Użytkownik') ?>
                              </a> 
                              <?= htmlspecialchars($p['tresc']) ?>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                              <span class="notif-time"><?= date("d.m.Y H:i", strtotime($p['data_utworzenia'])) ?></span>
                              <?php
                              $urlParams = $_GET;
                              $urlParams['odczytaj_pow'] = $p['id'];
                              $targetUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($urlParams);
                              ?>
                              <a href="<?= $targetUrl ?>" class="mark-read-btn">
                                <i class="fas fa-eye mr-1"></i> Usuń
                              </a>
                            </div>
                        <?php endif; ?>

                      </div>
                  <?php endforeach; ?>
              <?php else: ?>
                  <div class="navbar-item">Brak nowych powiadomień.</div>
              <?php endif; ?>
            </div>
          </div>
          <a class="navbar-item" href="panel.php">Panel</a>

          <?php if($rola_id == 2 && $premium_do !== NULL && strtotime($premium_do) >= time()): ?>
            <span class="navbar-item has-text-warning">⭐ Premium</span>
          <?php endif; ?>

          <?php if($rola_id == 3): ?>
            <a href="admin_panel.php" class="navbar-item has-text-danger">🛡️ Admin</a>
          <?php endif; ?>

          <a class="navbar-item" href="wyloguj.php">Wyloguj</a>

        <?php else: ?>

          <a class="navbar-item" href="rejestracja.php">Rejestracja</a>
          <a class="navbar-item" href="logowanie.php">Logowanie</a>

        <?php endif; ?>

      </div>
    </div>

  </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const burger = document.querySelector('.navbar-burger');
  const menu = document.querySelector('#navbarBasic');
  if(burger){
    burger.addEventListener('click', () => {
      burger.classList.toggle('is-active');
      menu.classList.toggle('is-active');
    });
  }
});
</script>