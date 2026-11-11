/*
 * Réglages du site : modifie seulement ce fichier pour changer
 * le numéro WhatsApp, le lien de paiement et les prix.
 */
window.CONFIG = {
  // Numéro WhatsApp au format international, sans espace ni « + ».
  // Exemple pour la Côte d'Ivoire : "2250700000000".
  // Tant qu'il est vide, les boutons « S'inscrire » mènent à la section Tarifs.
  whatsapp: "2250747933748",

  // Lien de paiement en ligne (Chariow, CinetPay…). Laisse vide si tu encaisses par WhatsApp.
  lienPaiement: "",

  devise: "FCFA",

  // Vidéo de présentation du haut de l'accueil (fichier .mp4 dans assets/video/).
  // Tant que c'est vide, l'accueil affiche « La vidéo de présentation arrive très bientôt ».
  // Exemple : "assets/video/presentation.mp4"
  videoPresentation: "",

  // Page de vente où arrive le visiteur après avoir laissé son nom et son WhatsApp sur l'accueil.
  pageVente: "vente.html",
  affichePresentation: "assets/img/flow/abidjan-nuit.webp",

  formules: [
    {
      id: "autonome",
      nom: "Autonome",
      prix: 35000,
      pour: "Tu avances seul, à ton rythme.",
      inclus: [
        "Les 26 leçons vidéo",
        "Les fiches PDF de chaque leçon",
        "Les exercices et les 4 quiz",
        "La boîte à prompts"
      ]
    },
    {
      id: "accompagne",
      nom: "Accompagné",
      prix: 75000,
      recommande: true,
      pour: "Tu avances avec un groupe et des retours sur ton travail.",
      inclus: [
        "Tout le contenu Autonome",
        "Le groupe WhatsApp des élèves",
        "Un live de questions-réponses chaque semaine",
        "La correction de tes 4 livrables",
        "Le certificat de fin de formation"
      ]
    },
    {
      id: "vip",
      nom: "VIP",
      prix: 150000,
      pour: "Tu veux un suivi individuel jusqu'à ton premier client.",
      inclus: [
        "Tout le contenu Accompagné",
        "4 séances individuelles en visio",
        "La revue de ta première campagne publicitaire",
        "Les modèles de devis, contrat et rapport mensuel"
      ]
    }
  ]
};
