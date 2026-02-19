<nav class="navbar is-primary" role="navigation">
  <div class="navbar-brand">
    <a class="navbar-item" href="index.php">
      <strong>Quizzlando</strong>
    </a>

    <a role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarBasic">
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
    </a>
  </div>

  <div id="navbarBasic" class="navbar-menu">
    <div class="navbar-end">
      <?php if(isset($_SESSION['id'])): ?>
        <a class="navbar-item" href="panel.php">Panel</a>
        <a class="navbar-item" href="wyloguj.php">Wyloguj</a>
      <?php else: ?>
        <a class="navbar-item" href="rejestracja.php">Rejestracja</a>
        <a class="navbar-item" href="logowanie.php">Logowanie</a>
      <?php endif; ?>
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
