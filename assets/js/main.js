/* Idjô Académie : affichage des modules, recherche, aperçus et inscription. */
(function () {
  "use strict";

  var F = window.FORMATION;
  var C = window.CONFIG;
  var surPageFormation = document.body.classList.contains("page-formation");

  /* ---------- Outils ---------- */
  function $(sel, racine) { return (racine || document).querySelector(sel); }
  function $$(sel, racine) { return Array.prototype.slice.call((racine || document).querySelectorAll(sel)); }
  function el(tag, attrs, enfants) {
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === "texte") n.textContent = attrs[k];
      else if (k === "html") n.innerHTML = attrs[k];
      else n.setAttribute(k, attrs[k]);
    });
    (enfants || []).forEach(function (e) { if (e) n.appendChild(e); });
    return n;
  }
  function normaliser(s) { return s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase(); }
  function prix(n) { return new Intl.NumberFormat("fr-FR").format(n) + " " + C.devise; }
  function duree(min) {
    if (min < 60) return min + " min";
    var h = Math.floor(min / 60), m = min % 60;
    return h + " h" + (m ? " " + (m < 10 ? "0" : "") + m : "");
  }
  function minutesModule(mod) { return mod.lecons.reduce(function (t, l) { return t + l.duree; }, 0); }
  function totalMinutes() { return F.modules.reduce(function (t, m) { return t + minutesModule(m); }, 0); }
  function totalLecons() { return F.modules.reduce(function (t, m) { return t + m.lecons.length; }, 0); }
  function numeroLecon(id) { return id.replace("-", "."); }
  function trouverLecon(id) {
    for (var i = 0; i < F.modules.length; i++) {
      var mod = F.modules[i];
      for (var j = 0; j < mod.lecons.length; j++) if (mod.lecons[j].id === id) return { module: mod, lecon: mod.lecons[j] };
    }
    return null;
  }
  function formuleParId(id) {
    return C.formules.filter(function (f) { return f.id === id; })[0] || C.formules[0];
  }
  function icone(id, taille) {
    var ns = "http://www.w3.org/2000/svg";
    var svg = document.createElementNS(ns, "svg");
    svg.setAttribute("width", taille || 20); svg.setAttribute("height", taille || 20); svg.setAttribute("aria-hidden", "true");
    var use = document.createElementNS(ns, "use"); use.setAttribute("href", "#" + id);
    svg.appendChild(use);
    return svg;
  }

  /* ---------- Inscription : WhatsApp, lien de paiement ou section Tarifs ---------- */
  function lienInscription(formuleId) {
    var f = formuleParId(formuleId);
    if (C.whatsapp) {
      var msg = "Bonjour, je veux m'inscrire à la formation Idjô Académie, formule " + f.nom + " (" + prix(f.prix) + ").";
      return { href: "https://wa.me/" + C.whatsapp + "?text=" + encodeURIComponent(msg), externe: true };
    }
    if (C.lienPaiement) return { href: C.lienPaiement, externe: true };
    return { href: surPageFormation ? "index.html#tarifs" : "#tarifs", externe: false };
  }
  function brancherInscription(a) {
    var lien = lienInscription(a.getAttribute("data-formule"));
    a.setAttribute("href", lien.href);
    if (lien.externe) { a.setAttribute("target", "_blank"); a.setAttribute("rel", "noopener"); }
    else { a.removeAttribute("target"); }
  }
  function brancherToutesInscriptions() { $$(".js-inscription").forEach(brancherInscription); }

  /* ---------- Menu mobile ---------- */
  var entete = $("#entete");
  var boutonMenu = $(".entete__menu");
  if (boutonMenu) boutonMenu.addEventListener("click", function () {
    var ouvert = entete.classList.toggle("menu-ouvert");
    boutonMenu.setAttribute("aria-expanded", ouvert ? "true" : "false");
    boutonMenu.setAttribute("aria-label", ouvert ? "Fermer le menu" : "Ouvrir le menu");
  });

  /* ---------- Fenêtre d'aperçu d'une leçon ---------- */
  var fenetre = $("#fenetre-apercu");
  function ouvrirApercu(id) {
    var r = trouverLecon(id);
    if (!r || !fenetre) return false;
    $("#fenetre-module").textContent = "Module " + r.module.numero + ", leçon " + numeroLecon(r.lecon.id) + ", " + duree(r.lecon.duree);
    $("#fenetre-titre").textContent = r.lecon.titre;
    $("#fenetre-resume").textContent = r.lecon.resume;
    $("#fenetre-image").setAttribute("src", r.module.image);
    if (typeof fenetre.showModal === "function") fenetre.showModal();
    else fenetre.setAttribute("open", "");
    return true;
  }
  if (fenetre) {
    $$("[data-fermer]", fenetre).forEach(function (b) { b.addEventListener("click", function () { fenetre.close(); }); });
    fenetre.addEventListener("click", function (e) { if (e.target === fenetre) fenetre.close(); });
  }
  document.addEventListener("click", function (e) {
    var cible = e.target.closest(".js-apercu");
    if (!cible) return;
    if (ouvrirApercu(cible.getAttribute("data-lecon"))) e.preventDefault();
  });

  /* ---------- Recherche dans les leçons ---------- */
  var champ = $("#recherche-champ");
  var resultats = $("#recherche-resultats");
  var index = [];
  F.modules.forEach(function (mod) {
    mod.lecons.forEach(function (l) {
      index.push({ id: l.id, titre: l.titre, module: mod, texte: normaliser(l.titre + " " + l.resume + " " + mod.titre + " " + mod.court) });
    });
  });
  function lienLecon(id) { return (surPageFormation ? "" : "formation.html") + "#lecon-" + id; }
  function fermerResultats() { resultats.hidden = true; champ.setAttribute("aria-expanded", "false"); }
  function chercher() {
    var q = normaliser(champ.value.trim());
    resultats.innerHTML = "";
    if (q.length < 2) { fermerResultats(); return; }
    var mots = q.split(/\s+/);
    var trouves = index.filter(function (it) { return mots.every(function (m) { return it.texte.indexOf(m) !== -1; }); }).slice(0, 8);
    if (!trouves.length) {
      resultats.appendChild(el("li", { "class": "recherche__vide", texte: "Aucune leçon ne correspond. Essaie « prompt », « site » ou « pub »." }));
    } else {
      trouves.forEach(function (it) {
        var a = el("a", { href: lienLecon(it.id) }, [
          el("strong", { texte: numeroLecon(it.id) + "  " + it.titre }),
          el("span", { texte: "Module " + it.module.numero + " : " + it.module.court })
        ]);
        a.addEventListener("click", function () { fermerResultats(); champ.value = ""; });
        resultats.appendChild(el("li", null, [a]));
      });
    }
    resultats.hidden = false;
    champ.setAttribute("aria-expanded", "true");
  }
  if (champ && resultats) {
    champ.addEventListener("input", chercher);
    champ.addEventListener("keydown", function (e) {
      var premier = $("a", resultats);
      if (e.key === "ArrowDown" && premier) { e.preventDefault(); premier.focus(); }
      if (e.key === "Enter" && premier) {
        e.preventDefault();
        var href = premier.getAttribute("href");
        fermerResultats(); champ.value = "";
        window.location.href = href;
      }
      if (e.key === "Escape") fermerResultats();
    });
    resultats.addEventListener("keydown", function (e) {
      var liens = $$("a", resultats), i = liens.indexOf(document.activeElement);
      if (e.key === "ArrowDown" && i < liens.length - 1) { e.preventDefault(); liens[i + 1].focus(); }
      if (e.key === "ArrowUp") { e.preventDefault(); (i > 0 ? liens[i - 1] : champ).focus(); }
      if (e.key === "Escape") { fermerResultats(); champ.focus(); }
    });
    document.addEventListener("click", function (e) { if (!e.target.closest(".recherche")) fermerResultats(); });
  }

  /* ---------- Accueil : lecteur chapitré ---------- */
  var barre = $("#lecteur-barre");
  if (barre) {
    var lecteur = $("#lecteur");
    var choisirChapitre = function (i, depuisClic) {
      var mod = F.modules[i];
      $("#lecteur-image").setAttribute("src", mod.image);
      $("#lecteur-module").textContent = "Module " + mod.numero + ", " + mod.lecons.length + " leçons, " + duree(minutesModule(mod));
      $("#lecteur-titre").textContent = mod.titre;
      $("#lecteur-lire").setAttribute("data-lecon", mod.lecons[0].id);
      $("#lecteur-lire").setAttribute("aria-label", "Voir l'aperçu de la leçon " + numeroLecon(mod.lecons[0].id) + " : " + mod.lecons[0].titre);
      $$(".chapitre", barre).forEach(function (b, k) {
        b.setAttribute("aria-pressed", k === i ? "true" : "false");
        var rempli = $(".chapitre__rempli", b), tete = $(".chapitre__tete", b);
        var part = k < i ? 100 : (k === i ? 34 : 0);
        rempli.style.width = part + "%";
        tete.style.left = part + "%";
      });
      if (depuisClic) $$(".chapitre", barre)[i].focus();
    };
    F.modules.forEach(function (mod, i) {
      var b = el("button", {
        "class": "chapitre", type: "button", "aria-pressed": "false",
        style: "--poids:" + minutesModule(mod),
        "aria-label": "Module " + mod.numero + " : " + mod.court + ", " + mod.lecons.length + " leçons"
      }, [
        el("span", { "class": "chapitre__piste" }, [el("span", { "class": "chapitre__rempli" }), el("span", { "class": "chapitre__tete" })]),
        el("span", { "class": "chapitre__nom", texte: mod.court, "aria-hidden": "true" })
      ]);
      b.addEventListener("click", function () { choisirChapitre(i); });
      b.addEventListener("keydown", function (e) {
        if (e.key === "ArrowRight" && i < F.modules.length - 1) { e.preventDefault(); choisirChapitre(i + 1, true); }
        if (e.key === "ArrowLeft" && i > 0) { e.preventDefault(); choisirChapitre(i - 1, true); }
      });
      barre.appendChild(b);
    });
    $("#lecteur-total").textContent = totalLecons() + " leçons, " + duree(totalMinutes()) + " de vidéo (durée estimée)";
    // Premier affichage : barre vide, puis la lecture « démarre » (une seule animation, au chargement).
    $$(".chapitre", barre)[0].setAttribute("aria-pressed", "true");
    requestAnimationFrame(function () {
      lecteur.classList.add("est-pret");
      requestAnimationFrame(function () { choisirChapitre(0); });
    });
  }

  /* ---------- Accueil : cartes des modules ---------- */
  var cartes = $("#cartes-modules");
  if (cartes) F.modules.forEach(function (mod) {
    cartes.appendChild(el("li", null, [
      el("a", { "class": "carte", href: "formation.html#" + mod.id }, [
        el("div", { "class": "carte__image" }, [el("img", { src: mod.image, alt: "", width: "640", height: "360", loading: "lazy" })]),
        el("span", { "class": "carte__numero", texte: "Module " + mod.numero }),
        el("h3", { "class": "carte__titre", texte: mod.titre }),
        el("span", { "class": "carte__meta", texte: mod.lecons.length + " leçons, " + duree(minutesModule(mod)) }),
        el("span", { "class": "carte__livrable", texte: "Livrable : " + mod.livrable })
      ])
    ]));
  });

  /* ---------- Accueil : onglets par module ---------- */
  var listeOnglets = $("#onglets-liste");
  if (listeOnglets) {
    var panneaux = $("#onglets-panneaux");
    var onglets = [];
    var activer = function (i, focus) {
      onglets.forEach(function (o, k) {
        o.setAttribute("aria-selected", k === i ? "true" : "false");
        o.setAttribute("tabindex", k === i ? "0" : "-1");
        $("#panneau-" + F.modules[k].id).hidden = k !== i;
      });
      if (focus) onglets[i].focus();
    };
    F.modules.forEach(function (mod, i) {
      var o = el("button", {
        "class": "onglet", role: "tab", type: "button", id: "onglet-" + mod.id,
        "aria-controls": "panneau-" + mod.id, "aria-selected": "false", texte: mod.court
      });
      o.addEventListener("click", function () { activer(i); });
      o.addEventListener("keydown", function (e) {
        if (e.key === "ArrowRight") { e.preventDefault(); activer((i + 1) % onglets.length, true); }
        if (e.key === "ArrowLeft") { e.preventDefault(); activer((i - 1 + onglets.length) % onglets.length, true); }
      });
      onglets.push(o);
      listeOnglets.appendChild(o);

      var coches = el("ul", { "class": "coches" }, mod.objectifs.map(function (t) {
        return el("li", null, [icone("i-coche", 20), el("span", { texte: t })]);
      }));
      var lecons = el("ol", { "class": "lecons-courtes", "aria-label": "Leçons du module " + mod.numero }, mod.lecons.map(function (l) {
        return el("li", null, [
          el("span", { "class": "num", texte: numeroLecon(l.id) }),
          el("span", { texte: l.titre }),
          el("span", { "class": "duree", texte: duree(l.duree) })
        ]);
      }));
      panneaux.appendChild(el("div", { "class": "panneau", role: "tabpanel", id: "panneau-" + mod.id, "aria-labelledby": "onglet-" + mod.id, tabindex: "0" }, [
        el("div", { "class": "panneau__intro" }, [
          el("h3", { "class": "titre-bloc", texte: "Module " + mod.numero + " : " + mod.titre }),
          el("p", { texte: mod.accroche }),
          coches,
          el("p", { "class": "petit", html: "<strong>Livrable :</strong> " + mod.livrable }),
          el("div", null, [el("a", { "class": "bouton bouton--contour", href: "formation.html#" + mod.id, texte: "Voir le détail du module" })])
        ]),
        lecons
      ]));
    });
    activer(0);
  }

  /* ---------- Accueil : tarifs ---------- */
  var tarifs = $("#liste-tarifs");
  if (tarifs) C.formules.forEach(function (f) {
    var nom = el("h3", { "class": "tarif__nom" }, [document.createTextNode(f.nom)]);
    if (f.recommande) nom.appendChild(el("span", { "class": "tarif__badge", texte: "Recommandé" }));
    var p = el("p", { "class": "tarif__prix" });
    p.appendChild(document.createTextNode(new Intl.NumberFormat("fr-FR").format(f.prix)));
    p.appendChild(el("small", { texte: C.devise }));
    var bouton = el("a", {
      "class": "bouton js-inscription" + (f.recommande ? "" : " bouton--contour"),
      href: "#tarifs", "data-formule": f.id, texte: "Choisir la formule " + f.nom
    });
    tarifs.appendChild(el("li", { "class": "tarif" + (f.recommande ? " tarif--recommande fond-sombre" : "") }, [
      nom, p,
      el("p", { "class": "tarif__pour", texte: f.pour }),
      el("ul", { "class": "coches" }, f.inclus.map(function (t) { return el("li", null, [icone("i-coche", 20), el("span", { texte: t })]); })),
      bouton
    ]));
  });

  /* ---------- Pied de page ---------- */
  var piedModules = $("#pied-modules");
  if (piedModules) F.modules.forEach(function (mod) {
    piedModules.appendChild(el("li", null, [el("a", { href: "formation.html#" + mod.id, texte: "Module " + mod.numero + " : " + mod.court })]));
  });
  var annee = $("#annee");
  if (annee) annee.textContent = new Date().getFullYear();

  /* ---------- Page formation : programme en accordéon ---------- */
  var programme = $("#programme-liste");
  if (programme) {
    F.modules.forEach(function (mod) {
      var items = mod.lecons.map(function (l) {
        var enfants = [
          icone("i-video", 20),
          el("span", null, [el("span", { "class": "numero-lecon", texte: numeroLecon(l.id) }), document.createTextNode(l.titre)])
        ];
        enfants.push(l.apercu
          ? el("button", { "class": "apercu-lien js-apercu", type: "button", "data-lecon": l.id, texte: "Aperçu" })
          : el("span"));
        enfants.push(el("span", { "class": "duree", texte: duree(l.duree) }));
        return el("li", { id: "lecon-" + l.id }, enfants);
      });
      items.push(el("li", null, [
        icone("i-livrable", 20),
        el("span", { texte: "Livrable et quiz : " + mod.livrable.charAt(0).toLowerCase() + mod.livrable.slice(1) }),
        el("span"), el("span")
      ]));
      programme.appendChild(el("details", { id: mod.id }, [
        el("summary", null, [
          el("strong", { texte: "Module " + mod.numero + " : " + mod.titre }),
          el("span", { texte: mod.lecons.length + " leçons, " + duree(minutesModule(mod)) })
        ]),
        el("ul", null, items)
      ]));
    });
    $("#programme-total").textContent = F.modules.length + " modules, " + totalLecons() + " leçons, " + duree(totalMinutes()) + " au total (durées estimées)";
    $$(".js-total-lecons").forEach(function (n) { n.textContent = totalLecons() + " leçons"; });
    $$(".js-total-duree").forEach(function (n) { n.textContent = duree(totalMinutes()) + " de vidéo"; });

    var toutOuvrir = $("#tout-ouvrir");
    toutOuvrir.addEventListener("click", function () {
      var details = $$("details", programme);
      var ouvrir = details.some(function (d) { return !d.open; });
      details.forEach(function (d) { d.open = ouvrir; });
      toutOuvrir.textContent = ouvrir ? "Tout replier" : "Tout développer";
    });

    var suivreAncre = function () {
      $$(".surbrillance", programme).forEach(function (n) { n.classList.remove("surbrillance"); });
      var h = decodeURIComponent(window.location.hash.slice(1));
      if (!h) { $$("details", programme)[0].open = true; return; }
      var cible = document.getElementById(h);
      if (!cible || !programme.contains(cible)) return;
      var details = cible.tagName === "DETAILS" ? cible : cible.closest("details");
      details.open = true;
      if (cible.tagName === "LI") cible.classList.add("surbrillance");
      requestAnimationFrame(function () { cible.scrollIntoView({ block: "center", behavior: "auto" }); });
    };
    suivreAncre();
    // Les polices changent la mise en page : on recentre une fois la page chargée.
    window.addEventListener("load", function () { if (window.location.hash) suivreAncre(); });
    window.addEventListener("hashchange", suivreAncre);
  }

  /* ---------- Page formation : choix de la formule ---------- */
  var choix = $("#choix-formule");
  if (choix) {
    C.formules.forEach(function (f) {
      var id = "formule-" + f.id;
      var input = el("input", { type: "radio", name: "formule", id: id, value: f.id });
      if (f.recommande) input.checked = true;
      choix.appendChild(input);
      choix.appendChild(el("label", { "for": id, texte: f.nom }));
    });
    var majFormule = function () {
      var f = formuleParId($("input:checked", choix).value);
      var p = $("#carte-prix");
      p.textContent = new Intl.NumberFormat("fr-FR").format(f.prix);
      p.appendChild(el("small", { texte: C.devise }));
      $("#barre-prix").textContent = prix(f.prix);
      var liste = $("#carte-inclus");
      liste.innerHTML = "";
      f.inclus.forEach(function (t) { liste.appendChild(el("li", null, [icone("i-coche", 20), el("span", { texte: t })])); });
      $$(".js-inscription-formule").forEach(function (a) {
        a.setAttribute("data-formule", f.id);
        a.textContent = C.whatsapp ? "S'inscrire par WhatsApp" : "S'inscrire";
        brancherInscription(a);
      });
    };
    choix.addEventListener("change", majFormule);
    majFormule();
  }

  brancherToutesInscriptions();
})();
