<?php if(isset($_GET['popup']) && $_GET['popup'] == 'Potwierdzenie'): ?>

<div id="Potwierdzenie" class="modal is-active">

    <div class="modal-background"></div>

    <div class="modal-card">

        <header class="modal-card-head has-background-success">
            <p class="modal-card-title has-text-white">Sukces</p>

            <button class="delete" onclick="ZamknijPotwierdzenie()"></button>
        </header>

        <section class="modal-card-body">
            Twoje zgłoszenie zostało wysłane pomyślnie.
        </section>

        <footer class="modal-card-foot">
            <button class="button is-success" onclick="ZamknijPotwierdzenie()">OK
            </button>
        </footer>

    </div>
</div>

<?php endif; ?>

<?php if(isset($_GET['popup']) && $_GET['popup'] == 'Spam'): ?>

<div id="Spam" class="modal is-active">

    <div class="modal-background"></div>

    <div class="modal-card">

        <header class="modal-card-head has-background-danger">
            <p class="modal-card-title has-text-white">Błąd</p>

            <button class="delete" onclick="ZamknijSpam()"></button>
        </header>

        <section class="modal-card-body">
            Nie możesz zgłosić quizu wielokrotnie
        </section>

        <footer class="modal-card-foot">
            <button class="button is-danger" onclick="ZamknijSpam()">Zamknij</button>
        </footer>

    </div>
</div>

<?php endif; ?>

<script>
function ZamknijPotwierdzenie() {

    const modal = document.getElementById("Potwierdzenie");

    if(modal) {
        modal.classList.remove("is-active");
    }
}

function ZamknijSpam() {

    const modal = document.getElementById("Spam");

    if(modal) {
        modal.classList.remove("is-active");
    }
}
</script>