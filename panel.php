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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>

body {
    background: #f5f6fa;
}

/* mobile header css */

@media screen and (max-width: 768px) {

    .panel-header {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 15px;
    }

    .panel-actions {
        width: 100%;
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }

    .create-btn {
        width: 100%;
    }
}

</style>

</head>

<body>

<?php include "navbar.php"; ?>

<section class="section">

<div class="container">

    <!-- header -->

    <div class="level panel-header">

        <div class="level-left">

            <div>

                <h1 class="title mb-1">Moje quizy</h1><br>
				
                <p class="subtitle is-6">
                    Zarządzaj swoimi quizami
                </p>

            </div>

        </div>

		<div class="level-right panel-actions">

    		<a href="quiz_stworz.php" class="button is-success create-btn" style="margin-right: 15px;"> + Stwórz Quiz </a>

    		<a href="znajomi.php" class="button is-light" style="margin-right: 15px;">
        		<span class="icon">
            		<i class="fas fa-user-friends"></i>
        		</span>
    		</a>

    		<a href="ustawienia.php" class="button is-light">
        		<span class="icon">
            		<i class="fas fa-gear"></i>
        		</span>
    		</a>

		</div>

</div>

    <!-- moje quizy -->

    <?php include "moje_quizy.php"; ?>

</div>

</section>

<?php include "footer.php"; ?>

</body>
</html>