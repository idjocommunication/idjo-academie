/* Idjô Académie : outils de l'admin (envoi de vidéos par morceaux, confirmations). */
(function () {
  "use strict";


  /* Confirmation avant les suppressions */
  document.addEventListener("click", function (ev) {
    var bouton = ev.target.closest("[data-confirmer]");
    if (bouton && !window.confirm(bouton.getAttribute("data-confirmer"))) {
      ev.preventDefault();
    }
  });

  function lisible(octets) {
    if (octets >= 1073741824) return (octets / 1073741824).toFixed(1).replace(".", ",") + " Go";
    if (octets >= 1048576) return Math.round(octets / 1048576) + " Mo";
    return Math.max(1, Math.round(octets / 1024)) + " Ko";
  }

  /* Identifiant stable pour un fichier donné : permet de reprendre un envoi interrompu. */
  function identifiant(fichier, lecon, cible) {
    var texte = [cible, lecon, fichier.name, fichier.size, fichier.lastModified].join("|");
    var donnees = new TextEncoder().encode(texte);
    return crypto.subtle.digest("SHA-256", donnees).then(function (h) {
      return Array.prototype.map.call(new Uint8Array(h), function (o) { return ("0" + o.toString(16)).slice(-2); }).join("");
    });
  }

  function dureeVideo(fichier) {
    return new Promise(function (resolve) {
      var v = document.createElement("video");
      var url = URL.createObjectURL(fichier);
      var fini = false;
      function terminer(d) { if (fini) return; fini = true; URL.revokeObjectURL(url); resolve(d || 0); }
      v.preload = "metadata";
      v.onloadedmetadata = function () { terminer(isFinite(v.duration) ? v.duration : 0); };
      v.onerror = function () { terminer(0); };
      setTimeout(function () { terminer(0); }, 8000);
      v.src = url;
    });
  }

  function attendre(ms) { return new Promise(function (r) { setTimeout(r, ms); }); }

  function preparerZone(zone) {
    var cible = zone.dataset.envoi;
    var lecon = zone.dataset.lecon;
    var csrf = zone.dataset.csrf;
    var TAILLE_MORCEAU = parseInt(zone.dataset.morceau || "2000000", 10);
    var champ = zone.querySelector('input[type="file"]');
    var etiquette = zone.querySelector(".zone-envoi__cible");
    var bloc = zone.querySelector(".zone-envoi__progression");
    var barre = bloc.querySelector(".barre-progression span");
    var etat = bloc.querySelector(".zone-envoi__etat");
    var annuler = bloc.querySelector(".zone-envoi__annuler");
    var enCours = false;
    var stop = false;

    function poster(champs, morceau) {
      var corps = new FormData();
      Object.keys(champs).forEach(function (cle) { corps.append(cle, champs[cle]); });
      if (morceau) corps.append("morceau", morceau, "morceau");
      return fetch("/api/envoi.php", {
        method: "POST", body: corps, credentials: "same-origin", headers: { "X-CSRF": csrf, "Accept": "application/json" }
      }).then(function (r) {
        return r.json().catch(function () { return { erreur: "Réponse inattendue du serveur (" + r.status + ")." }; })
          .then(function (j) { j._statut = r.status; return j; });
      });
    }

    function afficher(recu, total, debutTemps, debutOctets) {
      var pct = total ? Math.floor(recu * 100 / total) : 0;
      barre.style.width = pct + "%";
      var secondes = (Date.now() - debutTemps) / 1000;
      var vitesse = secondes > 1 ? (recu - debutOctets) / secondes : 0;
      var reste = vitesse > 0 ? Math.round((total - recu) / vitesse) : 0;
      etat.textContent = pct + " % · " + lisible(recu) + " sur " + lisible(total) +
        (reste > 0 ? " · environ " + (reste > 90 ? Math.round(reste / 60) + " min" : reste + " s") + " restantes" : "");
    }

    function envoyer(fichier) {
      if (enCours) return;
      enCours = true;
      stop = false;
      etiquette.hidden = true;
      bloc.hidden = false;
      etat.textContent = "Préparation…";
      var base = { cible: cible, lecon: lecon, nom: fichier.name, taille: fichier.size };
      var duree = 0;

      var preparation = cible === "video" ? dureeVideo(fichier).then(function (d) { duree = d; }) : Promise.resolve();
      preparation.then(function () { return identifiant(fichier, lecon, cible); }).then(function (id) {
        base.envoi = id;
        return poster(Object.assign({ action: "etat" }, base));
      }).then(function (r) {
        if (r.erreur) throw new Error(r.erreur);
        var recu = r.recu || 0;
        var debutTemps = Date.now();
        var debutOctets = recu;
        var essais = 0;
        if (recu > 0) etat.textContent = "Reprise de l'envoi à " + lisible(recu) + "…";

        function suivant() {
          if (stop) throw new Error("Envoi annulé.");
          afficher(recu, fichier.size, debutTemps, debutOctets);
          var morceau = fichier.slice(recu, Math.min(recu + TAILLE_MORCEAU, fichier.size));
          var champs = Object.assign({ debut: recu }, base);
          if (recu + morceau.size >= fichier.size && duree) champs.duree = duree;
          return poster(champs, morceau).then(function (rep) {
            if (rep.erreur === "decalage") { recu = rep.recu; return suivant(); }
            if (rep.erreur) throw new Error(rep.erreur);
            essais = 0;
            recu = rep.recu;
            if (rep.termine) return true;
            return suivant();
          }, function () {
            essais += 1;
            if (essais > 5) throw new Error("La connexion a coupé. Choisis à nouveau le même fichier pour reprendre l'envoi.");
            etat.textContent = "Connexion instable, nouvel essai (" + essais + "/5)…";
            return attendre(2000 * essais).then(function () {
              return poster(Object.assign({ action: "etat" }, base)).then(function (e) { if (!e.erreur) recu = e.recu; return suivant(); }, suivant);
            });
          });
        }
        return suivant();
      }).then(function () {
        barre.style.width = "100%";
        etat.textContent = "Envoi terminé. Mise à jour de la page…";
        enCours = false;
        window.location.reload();
      }).catch(function (err) {
        enCours = false;
        etat.textContent = err.message;
        etat.classList.add("champ__erreur");
        etiquette.hidden = false;
        champ.value = "";
      });
    }

    champ.addEventListener("change", function () {
      if (champ.files && champ.files[0]) envoyer(champ.files[0]);
    });
    ["dragenter", "dragover"].forEach(function (type) {
      zone.addEventListener(type, function (ev) { ev.preventDefault(); zone.classList.add("est-survolee"); });
    });
    ["dragleave", "drop"].forEach(function (type) {
      zone.addEventListener(type, function () { zone.classList.remove("est-survolee"); });
    });
    zone.addEventListener("drop", function (ev) {
      ev.preventDefault();
      if (ev.dataTransfer.files && ev.dataTransfer.files[0]) envoyer(ev.dataTransfer.files[0]);
    });
    annuler.addEventListener("click", function () { stop = true; });
    window.addEventListener("beforeunload", function (ev) {
      if (enCours) { ev.preventDefault(); ev.returnValue = ""; }
    });
  }

  Array.prototype.forEach.call(document.querySelectorAll("[data-envoi]"), preparerZone);
})();
