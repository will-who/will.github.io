<?php
    // Spoločný formulár pre add-game.php a edit-game.php
    // Očakáva: $formTitle, $formSubtitle, $formAction, $submitLabel, $autosave,
    //          $values (title, tags, image), $error, $saved
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css?v=<?= filemtime(__DIR__ . '/style.css') ?>">
    <title><?= e($formTitle) ?></title>
</head>
<body class="auth-page">

    <div class="auth-box">

        <a class="close-auth" href="track.php">×</a>

        <h2><?= e($formTitle) ?></h2>
        <p class="auth-subtitle"><?= e($formSubtitle) ?></p>

        <form method="POST" action="<?= e($formAction) ?>" id="gameForm">
            <?= csrf_field() ?>

            <label for="title">Game title</label>
            <input type="text" id="title" name="title" maxlength="100"
                   placeholder="e.g. Apex Legends"
                   value="<?= e($values["title"]) ?>" required>

            <label for="tags">Tags</label>
            <input type="text" id="tags" name="tags" maxlength="100"
                   placeholder="e.g. First person shooter"
                   value="<?= e($values["tags"]) ?>">

            <label for="image">Img source</label>
            <input type="text" id="image" name="image" maxlength="500"
                   placeholder="https://… or img/game.png"
                   value="<?= e($values["image"]) ?>">

            <button type="submit" class="auth-submit"><?= e($submitLabel) ?></button>
        </form>

        <div class="auth-message<?= $saved ? " ok" : "" ?>" id="formMessage"><?php
            if ($error) { echo e($error); }
            elseif ($saved) { echo "Saved ✓"; }
        ?></div>

        <?php if ($autosave): ?>
        <script>
            // Automatické ukladanie: po každej zmene sa po krátkej pauze odošle formulár do DB.
            // Bez JS funguje formulár normálne cez tlačidlo SAVE.
            (function () {
                var form = document.getElementById("gameForm");
                var msg  = document.getElementById("formMessage");
                var btn  = form.querySelector(".auth-submit");
                var timer = null;

                if (!window.fetch || !window.FormData) { return; }
                btn.style.display = "none";

                function setMsg(text, cls) {
                    msg.className = "auth-message " + (cls || "");
                    msg.textContent = text;
                }

                function save() {
                    setMsg("Saving…", "muted");
                    fetch(form.action, {
                        method: "POST",
                        body: new FormData(form),
                        headers: { "X-Requested-With": "fetch" },
                        credentials: "same-origin"
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j.ok) { setMsg("Saved ✓", "ok"); }
                        else      { setMsg(j.error || "Save failed.", ""); }
                    })
                    .catch(function () { setMsg("Save failed.", ""); });
                }

                form.addEventListener("input", function () {
                    clearTimeout(timer);
                    setMsg("Unsaved changes…", "muted");
                    timer = setTimeout(save, 700);
                });
            })();
        </script>
        <?php endif; ?>

    </div>

</body>
</html>