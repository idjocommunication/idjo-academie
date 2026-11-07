/* Idjô Académie : modules, assistant, aperçus, recherche, animations et inscription. */
(function () {
  "use strict";

  var F = window.FORMATION;
  var C = window.CONFIG;
  var surPageFormation = document.body.classList.contains("page-formation");
  var mouvementReduit = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

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
    (enfants || []).forEach(function (e) { if (e) n.appendChild(typeof e === "string" ? document.createTextNode(e) : e); });
    return n;
  }
  function normaliser(s) { return s.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase(); }
  function nombre(n) { return new Intl.NumberFormat("fr-FR").format(n); }
  function prix(n) { return nombre(n) + " " + C.devise; }
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
  function formuleParId(id) { return C.formules.filter(function (f) { return f.id === id; })[0] || C.formules[0]; }
  function icone(id, taille) {
    var ns = "http://www.w3.org/2000/svg";
    var svg = document.createElementNS(ns, "svg");
    svg.setAttribute("width", taille || 20); svg.setAttribute("height", taille || 20); svg.setAttribute("aria-hidden", "true");
    var use = document.createElementNS(ns, "use"); use.setAttribute("href", "#" + id);
    svg.appendChild(use);
    return svg;
  }
  function texteGlissant(texte) {
    return el("span", { "class": "glisse" }, [el("span", { texte: texte }), el("span", { texte: texte, "aria-hidden": "true" })]);
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

  /* ---------- Navigation mobile ---------- */
  var nav = $("#nav");
  var boutonMenu = $(".nav__menu");
  if (boutonMenu) boutonMenu.addEventListener("click", function () {
    var ouvert = nav.classList.toggle("menu-ouvert");
    boutonMenu.setAttribute("aria-expanded", ouvert ? "true" : "false");
    boutonMenu.setAttribute("aria-label", ouvert ? "Fermer le menu" : "Ouvrir le menu");
  });
  $$("#nav-liens a").forEach(function (a) { a.addEventListener("click", function () { nav.classList.remove("menu-ouvert"); }); });

  /* ---------- Barre de navigation : sombre sur le haut de page, claire ensuite ---------- */
  var hautSombre = $(".hero, .tete-formation");
  if (nav && hautSombre) {
    var navEnAttente = false;
    var majNav = function () {
      navEnAttente = false;
      var basNav = nav.querySelector(".nav__barre").getBoundingClientRect().bottom;
      nav.classList.toggle("nav--sombre", hautSombre.getBoundingClientRect().bottom > basNav);
    };
    majNav();
    window.addEventListener("scroll", function () { if (!navEnAttente) { navEnAttente = true; requestAnimationFrame(majNav); } }, { passive: true });
    window.addEventListener("resize", majNav);
  }

  /* ---------- Apparitions au défilement ---------- */
  function observerApparitions(racine) {
    var cibles = $$(".apparait:not(.est-visible)", racine);
    if (mouvementReduit || !("IntersectionObserver" in window)) { cibles.forEach(function (c) { c.classList.add("est-visible"); }); return; }
    var io = new IntersectionObserver(function (entrees) {
      entrees.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add("est-visible"); io.unobserve(e.target); } });
    }, { threshold: 0.2, rootMargin: "0px 0px -40px 0px" });
    cibles.forEach(function (c) { io.observe(c); });
  }

  /* ---------- Vidéo du haut de page : ordinateur seulement ---------- */
  var video = $("#hero-video");
  if (video) {
    var economie = navigator.connection && navigator.connection.saveData;
    if (window.matchMedia("(min-width: 900px)").matches && !economie && !mouvementReduit) {
      video.src = video.getAttribute("data-src");
      video.addEventListener("canplay", function () { video.classList.add("est-prete"); }, { once: true });
      var lecture = video.play();
      if (lecture && lecture.catch) lecture.catch(function () { /* lecture automatique refusée : l'image reste affichée */ });
    }
  }

  /* ---------- Fenêtre d'aperçu d'une leçon ---------- */
  var fenetre = $("#fenetre-apercu");
  function ouvrirApercu(id) {
    var r = trouverLecon(id);
    if (!r || !fenetre) return false;
    $("#fenetre-module").textContent = "Module " + r.module.numero + ", leçon " + numeroLecon(r.lecon.id) + ", " + duree(r.lecon.duree);
    $("#fenetre-titre").textContent = r.lecon.titre;
    $("#fenetre-resume").textContent = r.lecon.resume;
    $("#fenetre-image").setAttribute("src", r.module.imageGrande || r.module.image);
    if (typeof fenetre.showModal === "function") fenetre.showModal(); else fenetre.setAttribute("open", "");
    return true;
  }
  if (fenetre) {
    $$("[data-fermer]", fenetre).forEach(function (b) { b.addEventListener("click", function () { fenetre.close(); }); });
    fenetre.addEventListener("click", function (e) { if (e.target === fenetre) fenetre.close(); });
  }
  document.addEventListener("click", function (e) {
    var cible = e.target.closest(".js-apercu");
    if (cible && ouvrirApercu(cible.getAttribute("data-lecon"))) e.preventDefault();
  });

  /* ---------- Recherche dans les leçons ---------- */
  var recherche = $("#recherche");
  var champ = $("#recherche-champ");
  var resultats = $("#recherche-resultats");
  var index = [];
  F.modules.forEach(function (mod) {
    mod.lecons.forEach(function (l) {
      index.push({ id: l.id, titre: l.titre, module: mod, texte: normaliser(l.titre + " " + l.resume + " " + mod.titre + " " + mod.court) });
    });
  });
  function lienLecon(id) { return (surPageFormation ? "" : "formation.html") + "#lecon-" + id; }
  function ouvrirRecherche() { recherche.classList.add("est-ouverte"); champ.value = ""; resultats.innerHTML = ""; champ.focus(); }
  function fermerRecherche() { recherche.classList.remove("est-ouverte"); }
  function chercher() {
    var q = normaliser(champ.value.trim());
    resultats.innerHTML = "";
    if (q.length < 2) return;
    var mots = q.split(/\s+/);
    var trouves = index.filter(function (it) { return mots.every(function (m) { return it.texte.indexOf(m) !== -1; }); }).slice(0, 8);
    if (!trouves.length) {
      resultats.appendChild(el("li", { "class": "recherche__vide", texte: "Aucune leçon ne correspond. Essaie « prompt », « site » ou « pub »." }));
      return;
    }
    trouves.forEach(function (it) {
      var a = el("a", { href: lienLecon(it.id) }, [
        el("strong", { texte: numeroLecon(it.id) + "  " + it.titre }),
        el("span", { texte: "Module " + it.module.numero + " : " + it.module.court })
      ]);
      a.addEventListener("click", fermerRecherche);
      resultats.appendChild(el("li", null, [a]));
    });
  }
  if (recherche) {
    $$(".js-ouvrir-recherche").forEach(function (b) { b.addEventListener("click", ouvrirRecherche); });
    $("[data-fermer-recherche]", recherche).addEventListener("click", fermerRecherche);
    recherche.addEventListener("click", function (e) { if (e.target === recherche) fermerRecherche(); });
    champ.addEventListener("input", chercher);
    champ.addEventListener("keydown", function (e) {
      var premier = $("a", resultats);
      if (e.key === "ArrowDown" && premier) { e.preventDefault(); premier.focus(); }
      if (e.key === "Enter" && premier) { e.preventDefault(); fermerRecherche(); window.location.href = premier.getAttribute("href"); }
    });
    resultats.addEventListener("keydown", function (e) {
      var liens = $$("a", resultats), i = liens.indexOf(document.activeElement);
      if (e.key === "ArrowDown" && i < liens.length - 1) { e.preventDefault(); liens[i + 1].focus(); }
      if (e.key === "ArrowUp") { e.preventDefault(); (i > 0 ? liens[i - 1] : champ).focus(); }
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && recherche.classList.contains("est-ouverte")) fermerRecherche();
      if (e.key === "/" && !/input|textarea/i.test(document.activeElement.tagName)) { e.preventDefault(); ouvrirRecherche(); }
    });
  }

  /* ---------- Accueil : cartes des modules ---------- */
  var cartes = $("#cartes-modules");
  if (cartes) F.modules.forEach(function (mod, i) {
    cartes.appendChild(el("li", { "class": "apparait", style: "--delai:" + (i * 0.08) + "s" }, [
      el("a", { "class": "module verre", href: "formation.html#" + mod.id }, [
        el("div", { "class": "module__image" }, [el("img", { src: mod.image, alt: "", width: "900", height: "502", loading: "lazy" })]),
        el("div", { "class": "module__corps" }, [
          el("span", { "class": "module__num", texte: "Module " + mod.numero }),
          el("h3", { "class": "module__titre", texte: mod.titre }),
          el("span", { "class": "module__meta", texte: mod.lecons.length + " leçons, " + duree(minutesModule(mod)) }),
          el("span", { "class": "module__livrable", texte: "Livrable : " + mod.livrable })
        ])
      ])
    ]));
  });

  /* ---------- Assistant : discussion avec réponses rapides ---------- */
  var fil = $("#chat-fil");
  function repondre(question) {
    var q = normaliser(question);
    var f = C.formules;
    if (/prix|tarif|combien.*(cout|coute|paye|fcfa)|cout|cher|fcfa|formule/.test(q))
      return "Il y a 3 formules : " + f.map(function (x) { return x.nom + " à " + prix(x.prix); }).join(", ") + ". Les 26 leçons sont dans les trois ; ce qui change, c'est l'accompagnement.";
    if (/pai|payer|wave|orange|mtn|moov|mobile money|carte/.test(q))
      return "Le paiement se fait par Mobile Money. Choisis ta formule et écris-nous sur WhatsApp : on t'envoie les détails du paiement et ton accès.";
    if (/temps|duree|semaine|jour|long|rythme|heure/.test(q))
      return "Compte 7 à 9 semaines à ton rythme, ou 30 jours avec 2 heures par jour. Chaque leçon dure 8 à 15 minutes.";
    if (/debut|connais rien|zero|novice|nul|facile|niveau/.test(q))
      return "Oui, c'est fait pour toi : le module 2 part de zéro. Tu crées tes comptes, tu écris tes premiers prompts et tu produis tes premiers contenus pas à pas.";
    if (/ordi|ordinateur|telephone|portable|smartphone|pc/.test(q))
      return "Les modules 1 et 2 se suivent très bien sur téléphone. Un ordinateur est conseillé pour les modules 3 et 4, où tu construis des sites et gères des campagnes.";
    if (/garanti|rembours|gagner|argent|revenu|riche/.test(q))
      return "Personne ne peut te garantir un revenu. La formation te donne des compétences qui se vendent et une méthode pour trouver des clients ; le reste dépend de ton travail.";
    if (/certificat|diplome|attestation/.test(q))
      return "Un certificat de fin de formation est remis dans les formules Accompagné et VIP, contre les 4 livrables.";
    if (/formateur|alan|qui (est|anime|enseigne)/.test(q))
      return "La formation est animée par Alan, fondateur de l'agence Idjô Communication.";
    if (/module|programme|contenu|apprend|lecon|cours/.test(q))
      return "4 modules : " + F.modules.map(function (m) { return m.numero + ". " + m.court; }).join(", ") + ". " + totalLecons() + " leçons vidéo au total, avec un livrable à la fin de chaque module.";
    if (/bonjour|salut|bonsoir|hello/.test(q))
      return "Bonjour ! Pose-moi ta question sur la formation : prix, paiement, durée ou contenu.";
    return "Je n'ai pas la réponse à cette question. Écris-nous sur WhatsApp, ou regarde le programme complet sur la page de la formation.";
  }
  function ajouterBulle(texte, auteur, instantane) {
    var b = el("p", { "class": "bulle bulle--" + auteur + (instantane ? "" : " apparait"), texte: texte });
    fil.appendChild(b);
    if (!instantane) requestAnimationFrame(function () { b.classList.add("est-visible"); });
    fil.scrollTop = fil.scrollHeight;
  }
  if (fil) {
    var amorces = [
      ["Bienvenue ! Je réponds à tes questions sur la formation : prix, paiement, durée et contenu.", "ia"],
      ["Je n'y connais rien en IA, je peux suivre ?", "moi"],
      [repondre("je debute"), "ia"]
    ];
    amorces.forEach(function (a, i) {
      fil.appendChild(el("p", { "class": "bulle bulle--" + a[1] + " apparait", style: "--delai:" + (i * 0.15) + "s", texte: a[0] }));
    });
    var poser = function (question) {
      if (!question.trim()) return;
      ajouterBulle(question, "moi");
      setTimeout(function () { ajouterBulle(repondre(question), "ia"); }, 450);
    };
    $("#chat-formulaire").addEventListener("submit", function (e) {
      e.preventDefault();
      var c = $("#chat-champ"); poser(c.value); c.value = "";
    });
    $$("#chat-suggestions button").forEach(function (b) { b.addEventListener("click", function () { poser(b.textContent); }); });
  }

  /* ---------- Assistant : aperçu des leçons chapitré ---------- */
  var chapitres = $("#chapitres");
  if (chapitres) {
    var images = $("#lecon-images");
    F.modules.forEach(function (mod, i) {
      images.appendChild(el("img", { src: mod.imageGrande || mod.image, alt: "", loading: "lazy", "class": i === 0 ? "est-active" : "" }));
    });
    var choisir = function (i) {
      var mod = F.modules[i];
      $$("img", images).forEach(function (im, k) { im.classList.toggle("est-active", k === i); });
      $("#lecon-module").textContent = "Module " + mod.numero + ", " + mod.lecons.length + " leçons";
      $("#lecon-titre").textContent = mod.titre;
      var lire = $("#lecon-lire");
      lire.setAttribute("data-lecon", mod.lecons[0].id);
      lire.setAttribute("aria-label", "Voir l'aperçu de la leçon " + numeroLecon(mod.lecons[0].id) + " : " + mod.lecons[0].titre);
      $$(".chapitre", chapitres).forEach(function (b, k) {
        b.setAttribute("aria-pressed", k === i ? "true" : "false");
        $(".chapitre__rempli", b).style.width = (k < i ? 100 : (k === i ? 40 : 0)) + "%";
      });
    };
    F.modules.forEach(function (mod, i) {
      var b = el("button", {
        "class": "chapitre", type: "button", "aria-pressed": "false", style: "--poids:" + minutesModule(mod),
        "aria-label": "Module " + mod.numero + " : " + mod.court
      }, [
        el("span", { "class": "chapitre__piste" }, [el("span", { "class": "chapitre__rempli" })]),
        el("span", { "class": "chapitre__nom", texte: mod.court, "aria-hidden": "true" })
      ]);
      b.addEventListener("click", function () { choisir(i); });
      chapitres.appendChild(b);
    });
    choisir(0);
  }

  /* ---------- Assistant : parallaxe (tableau de bord et premier plan) ---------- */
  var section = $("#assistant"), tableau = $("#tableau"), premierPlan = $("#premier-plan");
  if (section && tableau && !mouvementReduit) {
    var grand = window.matchMedia("(min-width: 901px)");
    var enAttente = false;
    var maj = function () {
      enAttente = false;
      var r = section.getBoundingClientRect(), vh = window.innerHeight;
      var p = Math.min(1, Math.max(0, (vh - r.top) / (vh + r.height)));
      if (grand.matches) {
        tableau.style.transform = "translate3d(0," + (120 - 240 * p).toFixed(1) + "px,0)";
        premierPlan.style.transform = "translate3d(0," + (200 - 400 * p).toFixed(1) + "px,0)";
      } else {
        tableau.style.transform = "";
        premierPlan.style.transform = "translate3d(0," + (80 - 120 * p).toFixed(1) + "px,0)";
      }
    };
    var demander = function () { if (!enAttente) { enAttente = true; requestAnimationFrame(maj); } };
    window.addEventListener("scroll", demander, { passive: true });
    window.addEventListener("resize", demander);
    maj();
  }

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
      var o = el("button", { "class": "onglet", role: "tab", type: "button", id: "onglet-" + mod.id, "aria-controls": "panneau-" + mod.id, "aria-selected": "false", texte: mod.court });
      o.addEventListener("click", function () { activer(i); });
      o.addEventListener("keydown", function (e) {
        if (e.key === "ArrowRight") { e.preventDefault(); activer((i + 1) % onglets.length, true); }
        if (e.key === "ArrowLeft") { e.preventDefault(); activer((i - 1 + onglets.length) % onglets.length, true); }
      });
      onglets.push(o);
      listeOnglets.appendChild(o);
      panneaux.appendChild(el("div", { "class": "panneau verre", role: "tabpanel", id: "panneau-" + mod.id, "aria-labelledby": "onglet-" + mod.id, tabindex: "0" }, [
        el("div", { "class": "panneau__intro" }, [
          el("h3", { "class": "titre-bloc", texte: mod.titre }),
          el("p", { texte: mod.accroche }),
          el("ul", { "class": "coches" }, mod.objectifs.map(function (t) { return el("li", null, [icone("i-coche", 20), el("span", { texte: t })]); })),
          el("p", { "class": "petit", texte: "Livrable : " + mod.livrable }),
          el("div", null, [el("a", { "class": "bouton bouton--verre bouton--petit", href: "formation.html#" + mod.id, texte: "Voir le détail du module" })])
        ]),
        el("ol", { "class": "lecons-courtes", "aria-label": "Leçons du module " + mod.numero }, mod.lecons.map(function (l) {
          return el("li", null, [el("span", { "class": "num", texte: numeroLecon(l.id) }), el("span", { texte: l.titre }), el("span", { "class": "duree", texte: duree(l.duree) })]);
        }))
      ]));
    });
    activer(0);
  }

  /* ---------- Accueil : tarifs ---------- */
  var tarifs = $("#liste-tarifs");
  if (tarifs) C.formules.forEach(function (f, i) {
    var nom = el("h3", { "class": "tarif__nom" }, [f.nom]);
    if (f.recommande) nom.appendChild(el("span", { "class": "tarif__badge", texte: "Recommandé" }));
    var bouton = el("a", { "class": "bouton js-inscription" + (f.recommande ? "" : " bouton--verre"), href: "#tarifs", "data-formule": f.id }, [texteGlissant("Choisir " + f.nom)]);
    tarifs.appendChild(el("li", { "class": "tarif verre apparait" + (f.recommande ? " tarif--recommande" : ""), style: "--delai:" + (i * 0.08) + "s" }, [
      nom,
      el("p", { "class": "tarif__prix" }, [nombre(f.prix), el("small", { texte: C.devise })]),
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
        return el("li", { id: "lecon-" + l.id }, [
          icone("i-video", 20),
          el("span", null, [el("span", { "class": "numero-lecon", texte: numeroLecon(l.id) }), l.titre]),
          l.apercu ? el("button", { "class": "apercu-lien js-apercu", type: "button", "data-lecon": l.id, texte: "Aperçu" }) : el("span"),
          el("span", { "class": "duree", texte: duree(l.duree) })
        ]);
      });
      items.push(el("li", null, [icone("i-livrable", 20), el("span", { texte: "Livrable et quiz : " + mod.livrable.charAt(0).toLowerCase() + mod.livrable.slice(1) }), el("span"), el("span")]));
      programme.appendChild(el("details", { id: mod.id, "class": "verre" }, [
        el("summary", null, [el("strong", { texte: "Module " + mod.numero + " : " + mod.titre }), el("span", { texte: mod.lecons.length + " leçons, " + duree(minutesModule(mod)) })]),
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
    window.addEventListener("hashchange", suivreAncre);
    window.addEventListener("load", function () { if (window.location.hash) suivreAncre(); });
  }

  /* ---------- Page formation : choix de la formule ---------- */
  var choix = $("#choix-formule");
  if (choix) {
    var options = $(".choix-formule__options", choix) || choix;
    C.formules.forEach(function (f) {
      var id = "formule-" + f.id;
      var input = el("input", { type: "radio", name: "formule", id: id, value: f.id });
      if (f.recommande) input.checked = true;
      options.appendChild(input);
      options.appendChild(el("label", { "for": id, texte: f.nom }));
    });
    var majFormule = function () {
      var f = formuleParId($("input:checked", choix).value);
      var p = $("#carte-prix");
      p.textContent = nombre(f.prix);
      p.appendChild(el("small", { texte: C.devise }));
      $("#barre-prix").textContent = prix(f.prix);
      var liste = $("#carte-inclus");
      liste.innerHTML = "";
      f.inclus.forEach(function (t) { liste.appendChild(el("li", null, [icone("i-coche", 20), el("span", { texte: t })])); });
      $$(".js-inscription-formule").forEach(function (a) {
        a.setAttribute("data-formule", f.id);
        brancherInscription(a);
      });
    };
    choix.addEventListener("change", majFormule);
    majFormule();
  }

  $$(".js-inscription").forEach(brancherInscription);
  observerApparitions(document);
})();
