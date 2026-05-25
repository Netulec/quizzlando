<div id="reportModal" class="modal">
  <div class="modal-background"></div>

  <div class="modal-card">
    <header class="modal-card-head">
      <p class="modal-card-title">Zgłoś quiz</p>
      <button class="delete" onclick="zamknijZgloszenie()"></button>
    </header>

    <form method="POST" action="zglos.php">
      <section class="modal-card-body">

        <input type="hidden" name="quiz_id" id="report_quiz_id">

        <div class="field">
          <label class="label">Kategoria</label>
          <div class="select">
            <select name="kategoria" required>
              <option value="">Wybierz</option>
              <option value="spam">Spam</option>
              <option value="obraźliwe">Treści obraźliwe</option>
              <option value="błąd">Błąd w quizie</option>
              <option value="inne">Inne</option>
            </select>
          </div>
        </div>

        <div class="field">
          <label class="label">Opis</label>
          <textarea class="textarea" name="opis" required></textarea>
        </div>

      </section>

      <footer class="modal-card-foot">
        <button class="button is-danger">Wyślij</button>
        <button type="button" class="button" onclick="zamknijZgloszenie()">Anuluj</button>
      </footer>
    </form>
  </div>
</div>

<script>
function otworzZgloszenie(quizId) {

    document.getElementById("report_quiz_id").value = quizId;

    document.getElementById("reportModal")
        .classList.add("is-active");
}

function zamknijZgloszenie() {

    document.getElementById("reportModal")
        .classList.remove("is-active");
}

document.addEventListener("DOMContentLoaded", function () {

    const bg = document.querySelector(
        "#reportModal .modal-background"
    );

    if (bg) {
        bg.addEventListener("click", zamknijZgloszenie);
    }
});
</script>