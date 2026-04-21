<?php
/**
 * fr.php — Fichier de traduction français EXASUR
 * Application : EXASUR — Examens de Sûreté de l'Aviation Civile
 * ANAC GABON — Direction de la Sûreté et de la Facilitation
 * Modifications : Renommage AIR SECURE → EXASUR, ECHEC→AJOURNÉ, RÉUSSITE→VALIDÉ
 *                 Code accès 4 chiffres, suppression références PNSAC/OACI sur accueil
 */
$lang = [
    // ── Navigation ────────────────────────────────────────────────────
    'accueil'                       => 'Accueil',
    'a_propos'                      => 'À Propos',
    'contact'                       => 'Contact',
    'administration'                => 'Administration',
    'deconnexion'                   => 'Déconnexion',
    'retour_accueil'                => 'Retour à l\'accueil',

    // ── Langue ────────────────────────────────────────────────────────
    'langue'                        => 'Langue',
    'francais'                      => 'Français',
    'anglais'                       => 'English',

    // ── Page d'accueil ────────────────────────────────────────────────
    'titre_accueil'                 => 'EXASUR — Plateforme d\'Examens de Sûreté de l\'Aviation Civile',
    'sous_titre_accueil'            => 'Examens de Sûreté & Facilitation de l\'Aviation Civile en République Gabonaise',
    'description_accueil'           => 'Plateforme officielle d\'évaluation et de certification du personnel AVSEC-FAL de l\'ANAC GABON.',
    'consulter_note'                => 'Consulter ma note',
    'categories_personnel'          => 'Examens disponibles',
    'type_examen'                   => 'Type d\'examen',
    'presentation_projet'           => 'EXASUR — Plateforme officielle d\'examens de sûreté aérienne',

    // ── Catégories ────────────────────────────────────────────────────
    'agent_surete'                  => 'Agent de Sûreté',
    'agent_surete_desc'             => 'Certification des agents de sûreté aéroportuaire (50 QCM, seuil 80%)',
    'agent_if'                      => 'Agent Inspection Filtrage',
    'agent_if_desc'                 => 'Certification théorique (50 QCM, seuil ≥70%) + pratique (images radiologiques, seuil 80% cumulé)',
    'instructeur'                   => 'Instructeur AVSEC',
    'instructeur_desc'              => 'Certification des instructeurs en sûreté de l\'aviation (50 QCM, seuil 80%)',
    'sensibilisation'               => 'Sensibilisation Sûreté',
    'sensibilisation_desc'          => 'Sensibilisation à la sûreté de l\'aviation civile (20 QCM, seuil 70%)',
    'formation'                     => 'Évaluation de Formation',
    'formation_desc'                => 'Évaluation post-formation par modules avec pondération (seuil 70%)',

    // ── Caractéristiques ──────────────────────────────────────────────
    'duree'                         => '1h30',
    'questions'                     => 'questions',
    'seuil'                         => 'seuil',
    'voir_instructions'             => 'Voir les instructions',

    // ── Fonctionnalités ───────────────────────────────────────────────
    'securite_max'                  => 'Sécurité maximale',
    'securite_desc'                 => 'Système anti-fraude actif : détection des changements d\'onglet, verrouillage automatique après 5 infractions.',
    'resultats_immediats'           => 'Résultats immédiats',
    'resultats_desc'                => 'Note et pourcentage affichés dès la fin de l\'examen avec mention VALIDÉ ou AJOURNÉ.',
    'navigation_libre'              => 'Navigation libre',
    'navigation_desc'               => 'Pour les examens théoriques : avancez et revenez sur vos réponses avant validation finale.',

    // ── Pied de page ──────────────────────────────────────────────────
    'liens_utiles'                  => 'Liens utiles',
    'pnsac'                         => 'EXASUR',
    'pnftra'                        => 'ANAC GABON',
    'suivez_nous'                   => 'Suivez-nous',
    'droits_reserves'               => 'Tous droits réservés',

    // ── Messages communs ──────────────────────────────────────────────
    'code_acces'                    => 'Code d\'accès (4 chiffres)',
    'mot_de_passe'                  => 'Mot de passe',
    'verifier'                      => 'Vérifier',
    'annuler'                       => 'Annuler',
    'remplir_champs'                => 'Veuillez remplir tous les champs.',
    'code_5_chiffres'               => 'Le code doit comporter 4 chiffres.',
    'note_non_disponible'           => 'Note non disponible',
    'erreur'                        => 'Erreur',
    'impossible_verifier'           => 'Impossible de vérifier la note.',
    'code'                          => 'Code',
    'nom'                           => 'Nom',
    'type'                          => 'Type',
    'session'                       => 'Session',
    'code_sur_convocation'          => 'Le code se trouve sur votre convocation (4 chiffres)',
    'mdp_fourni'                    => 'Mot de passe fourni par l\'administration',
    'session_examen'                => 'Session d\'examen',
    'selectionnez_session'          => '-- Sélectionnez une session --',
    'accepte_conditions'            => 'J\'ai lu et j\'accepte les conditions de l\'examen',
    'voir_conditions'               => 'Voir les conditions',
    'acceder_examen'                => 'Accéder à l\'examen',
    'retour_instructions'           => 'Retour aux instructions',
    'instructions_examen'           => 'INSTRUCTIONS DE L\'EXAMEN',
    'consignes_generales'           => 'CONSIGNES GÉNÉRALES',
    'lisez_attentivement'           => 'Lisez attentivement avant de commencer l\'épreuve',
    'aucune_session_titre'          => 'Aucune session disponible',
    'aucune_session'                => 'Aucune session d\'examen disponible',
    'aucune_session_message'        => 'Aucune session n\'est actuellement planifiée pour cette catégorie.',
    'contactez_admin_plus_tard'     => 'Veuillez contacter l\'administration ou revenir plus tard.',
    'examen_contient'               => 'L\'examen contient',
    'questions_choix_unique'        => 'questions à choix unique',
    'disposez_de'                   => 'Vous disposez de',
    'pour_completer'                => 'pour compléter l\'examen',
    'bonne_reponse_vaut'            => 'Chaque bonne réponse vaut',
    'une_fois_commence'             => 'Une fois commencé, le chronomètre démarre et ne peut pas être arrêté.',
    'lisez_attentivement_question'  => 'Lisez attentivement chaque question avant de répondre.',
    'revenir_en_arriere'            => 'Pour les examens théoriques, vous pouvez revenir en arrière pour modifier vos réponses.',
    'evitez_veille'                 => 'Évitez que votre appareil se mette en veille.',
    'pas_appels'                    => 'Ne répondez pas aux appels ou notifications pendant l\'épreuve.',
    'batterie_suffisante'           => 'Assurez-vous d\'avoir suffisamment de batterie.',
    'connexion_stable'              => 'Assurez-vous d\'avoir une connexion internet stable.',
    'pas_changer_onglet'            => 'Ne changez pas d\'onglet, n\'ouvrez pas d\'autres applications.',
    'surveillance_active'           => 'La surveillance en ligne est active.',
    'triche_annulation'             => 'Toute tentative de fraude entraînera l\'annulation de l\'examen.',
    'questions_traitees'            => 'Seules les questions traitées sont comptabilisées.',
    'tentatives_max'                => 'Vous disposez de 5 tentatives maximum.',
    'verrouillage'                  => 'À la 5e infraction, l\'épreuve est verrouillée définitivement.',
    'seuil_reussite'                => 'Seuil de validation',
    'fin_note_affichee'             => 'À la fin, votre note sera immédiatement affichée.',
    'commencer_authentification'    => 'Commencer l\'authentification',

    // ── Confirmation ──────────────────────────────────────────────────
    'confirmation_identite'         => 'CONFIRMATION D\'IDENTITÉ',
    'verifiez_infos'                => 'Vérifiez que ces informations sont correctes avant de continuer.',
    'oui_cest_moi'                  => 'OUI, C\'EST MOI',
    'non'                           => 'NON',

    // ── Examen ────────────────────────────────────────────────────────
    'examen'                        => 'Examen',
    'questions_capital'             => 'QUESTIONS',
    'repondues'                     => 'Répondues',
    'restantes'                     => 'Restantes',
    'non_repondue'                  => 'Non répondue',
    'repondue'                      => 'Répondue',
    'question'                      => 'Question',
    'precedente'                    => 'PRÉCÉDENTE',
    'suivante'                      => 'SUIVANTE',
    'terminer'                      => 'TERMINER',
    'terminer_examen'               => 'TERMINER L\'EXAMEN ?',
    'oui_soumettre'                 => 'OUI, SOUMETTRE',
    'aucune_reponse'                => 'Aucune réponse sélectionnée',
    'passer_suivante'               => 'Voulez-vous vraiment passer à la question suivante ?',
    'continuer'                     => 'Continuer',
    'reponse_enregistree'           => 'Réponse enregistrée',

    // ── Anti-triche ───────────────────────────────────────────────────
    'non_respect_consignes'         => 'NON-RESPECT DES CONSIGNES',
    'tentative'                     => 'Tentative',
    'examen_verrouille'             => 'EXAMEN VERROUILLÉ',
    'verrouille_message'            => '5 infractions détectées. Votre examen est terminé et verrouillé.',

    // ── Temps ─────────────────────────────────────────────────────────
    'minutes_restantes_10'          => '10 MINUTES RESTANTES',
    'gerez_temps'                   => 'Gérez votre temps efficacement !',
    'minutes_restantes_5'           => '5 MINUTES RESTANTES',
    'finalisez_reponses'            => 'Finalisez vos réponses rapidement !',

    // ── Résultats — mentions VALIDÉ / AJOURNÉ ─────────────────────────
    'resultat_officiel'             => 'RÉSULTAT OFFICIEL',
    'bonnes_reponses'               => 'Bonnes réponses',
    'mauvaises_reponses'            => 'Mauvaises réponses',
    'pourcentage'                   => 'Pourcentage',
    'seuil_reussite_resultat'       => 'Seuil de validation',
    'atteint'                       => 'Atteint',
    'non_atteint'                   => 'Non atteint',
    'felicitations'                 => 'FÉLICITATIONS !',
    'essayez_encore'                => 'AJOURNÉ',       // Anciennement "ESSAYEZ ENCORE"
    'reussite'                      => 'VALIDÉ',        // Anciennement "RÉUSSITE"
    'echec'                         => 'AJOURNÉ',       // Anciennement "ÉCHEC"
    'evaluez_experience'            => 'ÉVALUEZ VOTRE EXPÉRIENCE',
    'satisfait'                     => 'SATISFAIT',
    'moyen'                         => 'MOYEN',
    'insatisfait'                   => 'INSATISFAIT',
    'avis_aide'                     => 'Votre avis nous aide à améliorer la plateforme EXASUR',
    'evaluation_enregistree'        => 'Évaluation enregistrée, merci !',
    'merci_evaluation'              => 'MERCI POUR VOTRE ÉVALUATION !',
    'deja_evalue'                   => 'Vous avez déjà donné votre avis.',
    'retour_accueil_bouton'         => 'RETOUR À L\'ACCUEIL',
    'imprimer'                      => 'IMPRIMER',
    'document_officiel'             => 'Document officiel — Direction de la Sûreté et de la Facilitation',
    'partie_theorique'              => 'Partie théorique',
    'partie_pratique'               => 'Partie pratique',
    'ok'                            => 'OK',
    'merci'                         => 'Merci',

    // ── Mention résultat ──────────────────────────────────────────────
    'mention_valide'                => 'VALIDÉ',
    'mention_ajourne'               => 'AJOURNÉ',
    'mention_valide_desc'           => 'Vous avez atteint le seuil requis. Votre certification est validée.',
    'mention_ajourne_desc'          => 'Vous n\'avez pas atteint le seuil requis. Vous êtes ajourné(e).',

    // ── Erreurs ───────────────────────────────────────────────────────
    'session_invalide'              => 'Session invalide',
    'session_indisponible'          => 'Cette session n\'est pas disponible.',
    'acces_refuse'                  => 'ACCÈS REFUSÉ',
    'mdp_incorrect'                 => 'Mot de passe incorrect.',
    'compte_bloque'                 => 'Compte bloqué',
    'contactez_admin'               => 'Votre compte a été bloqué. Contactez l\'administration ANAC.',
    'examen_deja_passe'             => 'Examen déjà passé',
    'deja_complete'                 => 'Vous avez déjà complété cet examen. Voici vos résultats.',
    'session_active'                => 'Session active',
    'deja_connecte'                 => 'Vous êtes déjà connecté sur un autre appareil.',
    'code_categorie_incorrect'      => 'Code d\'accès, catégorie ou session incorrect.',
    'aucun_examen_trouve_pour_ce_type' => 'Aucun examen trouvé pour ce type avec ce code.',
    'code_incorrect_ou_categorie'   => 'Code incorrect ou catégorie erronée.',

    // ── À propos EXASUR ───────────────────────────────────────────────
    'apropos_titre'                 => 'EXASUR — Plateforme d\'Examens de Sûreté',
    'apropos_intro'                 => 'EXASUR est la plateforme officielle de l\'ANAC GABON dédiée aux examens de certification et d\'évaluation du personnel de sûreté et de facilitation de l\'aviation civile en République Gabonaise.',
    'apropos_objectif_titre'        => 'Objectif de la plateforme',
    'apropos_objectif'              => 'Permettre aux agents AVSEC-FAL de passer leurs examens de certification en ligne, de manière sécurisée, transparente et automatisée — en remplacement du système manuel traditionnel.',
    'apropos_examens_titre'         => 'Examens disponibles',
    'apropos_securite_titre'        => 'Sécurité & Intégrité',
    'apropos_securite'              => 'Système anti-fraude intégré, surveillance active, verrouillage automatique des tentatives de triche, résultats immédiats et traçabilité complète.',
    'apropos_contact'               => 'Pour toute assistance, contactez l\'administration ANAC GABON.',

    // ── Contenu réglementaire (conservé pour usage interne) ───────────
    'oaci_annexe17'                 => 'L\'annexe 17 de l\'OACI définit la sûreté comme la protection de l\'aviation civile contre les actes d\'intervention illicites.',
    'role_direction'                => 'La Direction de la sûreté et de la facilitation de l\'ANAC est l\'entité responsable de la supervision du système national de sûreté.',
    'service_surete'                => 'Service de la Sûreté',
    'service_facilitation'          => 'Service de la Facilitation',
    'surete_missions'               => [
        'Élaborer, mettre à jour et diffuser la réglementation nationale relative à la sûreté.',
        'Participer à la délivrance, suspension ou retrait des certificats.',
        'Évaluer les informations concernant les exploitants.',
        'Veiller au respect de la réglementation.',
        'Réévaluer constamment le niveau de la menace.',
        'Certifier les agents et instructeurs.',
    ],
    'facilitation_missions'         => [
        'Élaborer la réglementation relative à la facilitation.',
        'Veiller au respect des normes de l\'OACI.',
        'Participer aux enquêtes.',
        'Établir des rapports périodiques.',
    ],
    'fixe_objectif'                 => 'Fixe l\'objectif de protection de l\'aviation civile.',
    'recapitule_mesures'            => 'Récapitule l\'ensemble des mesures de sûreté.',
    'decrit_organisation'           => 'Décrit l\'organisation des services.',
    'programmes_connexes'           => 'Programmes connexes : PNCQ, PNFSAC, PNCP, PSA, PSEA.',
];
?>