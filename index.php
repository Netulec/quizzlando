<?php
session_start();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzlando</title>
    <!-- Bulma -->
    <link rel="icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>
<body>
<!-- Navbar -->
<nav class="navbar is-primary" role="navigation" aria-label="main navigation">
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

<!-- Główna sekcja -->
<section class="section">
  <div class="container">
    <h1 class="title">Witaj w Quizzlando!</h1>
    <p class="subtitle">Twoje miejsce na quizy online.</p>
  </div>
</section>

<!-- Bulma JS -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const burger = document.querySelector('.navbar-burger');
  const menu = document.querySelector('#navbarBasic');

  burger.addEventListener('click', () => {
    burger.classList.toggle('is-active');
    menu.classList.toggle('is-active');
  });
});
</script>
</body>
</html>
