/* Idjô Académie : lecteur de leçon (reprise, progression, leçon terminée). */
(function () {
  "use strict";

  var video = document.getElementById("video-lecon");
  var formulaire = document.querySelector(".js-terminer");

  function envoyer(donnees) {
    var corps = new FormData();
    Object.keys(donnees).forEach(function (cle) { corps.append(cle, donnees[cle]); });
    var csrf = formulaire ? formulaire.querySelector('[name="csrf"]').value : "";
    return fetch("/api/progression.php", {
      method: "POST",
      body: corps,
      credentials: "same-origin",
      headers: { "Accept": "application/json", "X-CSRF": csrf },
      keepalive: true
    }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
  }

  function afficherTerminee(oui) {
    if (!formulaire) return;
    var bouton = formulaire.querySelector("button");
    formulaire.querySelector('[name="terminee"]').value = oui ? "0" : "1";
    bouton.dataset.fait = oui ? "1" : "0";
    bouton.textContent = oui ? "✓ Terminée" : "Marquer comme terminée";
    bouton.classList.toggle("bouton--verre", oui);
    var actuel = document.querySelector(".lecture__sommaire li.en-cours");
    if (actuel) {
      actuel.classList.toggle("finie", oui);
      var icone = actuel.querySelector("span[aria-hidden]");
      if (icone) icone.textContent = oui ? "✓" : "▶";
    }
  }

  if (formulaire) {
    formulaire.addEventListener("submit", function (ev) {
      ev.preventDefault();
      var marquer = formulaire.querySelector('[name="terminee"]').value;
      envoyer({ lecon: formulaire.querySelector('[name="lecon"]').value, terminee: marquer }).then(function (r) {
        if (r && r.ok) afficherTerminee(r.terminee);
        else formulaire.submit();
      });
    });
  }

  if (!video) return;
  var lecon = video.dataset.lecon;
  var dejaTerminee = video.dataset.terminee === "1";
  var derniereSauvegarde = 0;

  video.addEventListener("loadedmetadata", function () {
    var position = parseInt(video.dataset.position || "0", 10);
    if (position > 5 && position < video.duration - 10) {
      video.currentTime = position;
    }
  }, { once: true });

  function sauvegarder(forcer) {
    var maintenant = Date.now();
    if (!forcer && maintenant - derniereSauvegarde < 15000) return;
    derniereSauvegarde = maintenant;
    var donnees = { lecon: lecon, position: Math.floor(video.currentTime) };
    if (!dejaTerminee && video.duration && video.currentTime / video.duration >= 0.9) {
      donnees.terminee = "1";
      dejaTerminee = true;
      afficherTerminee(true);
    }
    envoyer(donnees);
  }

  video.addEventListener("timeupdate", function () { sauvegarder(false); });
  video.addEventListener("pause", function () { sauvegarder(true); });
  video.addEventListener("ended", function () { sauvegarder(true); });
  document.addEventListener("visibilitychange", function () {
    if (document.visibilityState === "hidden" && !video.paused) sauvegarder(true);
  });
})();
