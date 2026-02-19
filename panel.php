<?php
session_start();

if(!isset($_SESSION['id'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Quizzlando - Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- BULMA -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">

    <!-- Ikony -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include "navbar.php"; ?>

<section class="section">
  <div class="container">

    <!-- Nagłówek i przyciski -->
    <div class="level">
      <div class="level-left">
        <div>
          <h1 class="title">Moje quizy</h1>
          <p class="subtitle">Zarządzaj swoimi quizami</p>
        </div>
      </div>

      <div class="level-right">
        <!-- Znajomi – po lewej od ustawień -->
        <a href="znajomi.php" class="button is-light mr-2">
          <span class="icon">
            <i class="fas fa-user-friends"></i>
          </span>
        </a>

        <!-- Zębatka – ustawienia -->
        <a href="ustawienia.php" class="button is-light">
          <span class="icon">
            <i class="fas fa-gear"></i>
          </span>
        </a>
      </div>
    </div>

    <!-- Ładowanie quizów -->
    <?php include "moje_quizy.php"; ?>

  </div>
</section>

</body>
</html>
