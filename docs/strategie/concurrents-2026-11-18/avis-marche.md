# Avis d'utilisateurs, acquisition des éditeurs, marché romand

Relevé du 2026-11-18. Toutes les lectures datent de ce jour sauf mention contraire.

Marquage :
- **[source]** : lu à la source (page, API ou fichier de l'éditeur de la donnée).
- **[secondaire]** : rapporté par un tiers (comparatif, article, résumé de moteur de recherche), page d'origine non lue.
- **[non trouvé]** : cherché, rien de vérifiable.

Limites de méthode, à garder en tête avant d'utiliser un chiffre :
- Capterra (capterra.fr, capterra.com) et trusted.de renvoient 403 à la lecture automatique : leurs notes ne sont citées qu'en [secondaire].
- Reddit n'a remonté aucun fil dans les recherches, même avec `site:reddit.com`. Les groupes Facebook ne sont pas lisibles. Côté forums, un seul fil exploitable (UK Business Forums).
- L'outil de lecture de pages tronque les citations à environ 125 caractères pour Trustpilot. Les citations App Store, elles, sont complètes (flux RSS d'Apple).
- Notes App Store : lues par l'API publique iTunes Search (`itunes.apple.com/search`), **par boutique nationale** (FR, DE, CH, US, GB). Une même app a une note différente dans chaque pays.
- Avis App Store : flux `itunes.apple.com/<pays>/rss/customerreviews/id=<id>/sortby=mostrecent/json` (au plus 3 pages). Ces flux ne contiennent que les avis **écrits**. Leur moyenne est nettement plus basse que la note globale, qui inclut les étoiles sans texte : Jobber 3,39 sur ses 150 derniers avis écrits contre 4,81 au total ; bexio Go 2,66 contre 4,51. Les notes globales des boutiques flattent donc les produits ; les avis écrits disent ce qui fâche.
- Notes Google Play : lues dans le HTML de la fiche (`play.google.com/store/apps/details?id=...`).
- Fichiers bruts conservés : `scratchpad/apps/rev_*.tsv` (avis App Store), `scratchpad/bfs/*.csv` (OFS).

---

## Partie A. Avis d'utilisateurs

### A.1 Notes et volumes par plateforme

| Produit | Plateforme | Note | Nombre d'avis | URL | Statut |
|---|---|---|---|---|---|
| Obat | Trustpilot | 4,6 | 714 (111 sur 12 mois) | https://fr.trustpilot.com/review/obat.fr | [source] |
| Obat | App Store FR | 3,33 | 33 | https://apps.apple.com/fr/app/id1666504412 | [source] |
| Obat | Google Play | 4,2 | 43 (10 k+ téléchargements) | https://play.google.com/store/apps/details?id=fr.obat | [source] |
| Obat | Google (avis d'entreprise) | 4,9 | 2 884 | https://www.obat.fr/devis-factures/devis-vocal-ia/ (chiffre affiché par l'éditeur) | [secondaire] |
| Tolteck | Trustpilot | 4,9 | 973 (65 sur 12 mois) | https://fr.trustpilot.com/review/tolteck.com | [source] |
| Tolteck | Google Play | 3,9 | 1,06 k (500 k+ téléchargements) | https://play.google.com/store/apps/details?id=com.tolteck.tezcat | [source] |
| Tolteck | App Store FR | | | recherche iTunes sans résultat | [non trouvé] |
| Tolteck | Capterra FR | 4,5 | 12 | https://www.capterra.fr/software/170527/tolteck (403) | [secondaire] |
| Batappli | Trustpilot | aucun avis | 0 | https://fr.trustpilot.com/review/batappli.fr | [source] |
| Batappli Mobile | App Store FR | 3,85 | 117 | https://apps.apple.com/fr/app/id571614303 | [source] |
| Batappli Mobile | Google Play | 3,5 | 146 (10 k+) | https://play.google.com/store/apps/details?id=com.systemlog.ibatappli | [source] |
| Batappli | Google (avis d'entreprise) | 4,3 | 201 (au 16/06/2025) | https://independant.io/avis/batappli/ | [secondaire] |
| Batappli | Appvizer | aucun avis | 0 | https://www.appvizer.fr/construction/btp/batappli | [source] |
| Renalto | App Store FR | 3,9 | 10 | https://apps.apple.com/fr/app/renalto/id6747433792 | [source] |
| Renalto | Google Play | 4,6 | 10 (1 k+) | https://play.google.com/store/apps/details?id=com.renalto.app | [source] |
| Renalto | Trustpilot | | | fr.trustpilot.com/review/renalto.com renvoie 404 | [non trouvé] |
| Boby (Cegid) | Appvizer | 4,4 | 56 (5 affichés, tous 2022-2023) | https://www.appvizer.fr/construction/btp/boby | [source] |
| Boby (Cegid) | Google / Trustpilot | 5,0 / 4,6 | 23 / 31 | https://independant.io/avis/boby (mis à jour 25/09/2026) | [secondaire] |
| Boby (Cegid) | App Store | | | l'app « Boby » de l'App Store FR est un babyphone (MLN group) | [non trouvé] |
| Artibot | toutes | | | Appvizer : 0 avis ; l'app « ArtiBot.ai » de l'App Store est un autre produit | [non trouvé] |
| Flowo | toutes | | | La Fabrique du Net : aucun avis vérifié, moins de 100 entreprises clientes (fiche éditeur) | [non trouvé] |
| plancraft | OMR Reviews | 4,7 | 12 | https://omr.com/de/reviews/product/plancraft | [source] |
| plancraft | Trustpilot | 4,4 | 248 (123 sur 12 mois) | https://de.trustpilot.com/review/plancraft.com | [source] |
| plancraft | App Store DE | 4,10 | 119 | https://apps.apple.com/de/app/id1545141852 | [source] |
| plancraft | Google Play | 4,3 | 105 (10 000+) | https://play.google.com/store/apps/details?id=de.plancraft.app | [source] |
| HERO Software | App Store DE | 4,43 | 2 919 | https://apps.apple.com/de/app/id1126062151 | [source] |
| HERO Software | Google Play | 3,3 | 295 (100 000+) | https://play.google.com/store/apps/details?id=de.energieheld.hero | [source] |
| HERO Software | Trustpilot | 3,9 | 81 (47 sur 12 mois) | https://de.trustpilot.com/review/hero-software.de | [source] |
| HERO Software | OMR Reviews | | | omr.com/de/reviews/product/hero-software renvoie 404 | [non trouvé] |
| Angebotsmeister | App Store DE | 5 | 1 | https://apps.apple.com/de/app/id6762550412 | [source] |
| Angebotsmeister | OMR Reviews | | 0 | https://omr.com/de/reviews/product/angebotsmeister | [secondaire] |
| ToolTime | OMR Reviews | 4,5 | 6 (tous de plus de 12 mois) | https://omr.com/de/reviews/product/tooltime | [source] |
| ToolTime | App Store DE | 3,88 | 104 | https://apps.apple.com/de/app/id1460115148 | [source] |
| ToolTime | Google Play | 3,9 | 127 (10 000+) | https://play.google.com/store/apps/details?id=com.tooltime | [source] |
| Jobber | App Store US | 4,81 | 21 673 | https://apps.apple.com/us/app/id1014146758 | [source] |
| Jobber | Google Play | 4,4 | 7,01 k (1 M+) | https://play.google.com/store/apps/details?id=com.getjobber.jobber | [source] |
| Jobber | Trustpilot | 4,1 | 236 (122 sur 12 mois) | https://www.trustpilot.com/review/getjobber.com | [source] |
| Jobber | Capterra | 4,6 | environ 1 456 | https://www.capterra.com/p/127994/Jobber/reviews/ | [secondaire] |
| Jottle | App Store GB | 5 | 2 | https://apps.apple.com/gb/app/id6788550231 | [source] |
| Jottle | G2, GetApp | | 0 | https://www.g2.com/products/jottle/discuss | [secondaire] |
| bexio | Trustpilot | 2,6 (« Mangelhaft ») | 349 (82 sur 12 mois ; 58 % à 1 étoile) | https://ch.trustpilot.com/review/bexio.com | [source] |
| bexio Go | App Store CH | 4,51 | 3 759 | https://apps.apple.com/ch/app/id1481169504 | [source] |
| bexio Go | Google Play | 4,3 | 993 (10 000+) | https://play.google.com/store/apps/details?id=com.bexio.bxBexio | [source] |
| Magic Heidi | Trustpilot | 4,5 | 21 | https://fi.trustpilot.com/review/magicheidi.ch | [source] |
| Magic Heidi | App Store CH | 4,78 | 95 | https://apps.apple.com/ch/app/id6444294159 | [source] |
| Magic Heidi | Google Play | 4,6 | 72 (1 k+) | https://play.google.com/store/apps/details?id=ch.magicheidi.app.magic_heidi | [source] |

Hors liste, trouvés en chemin (devis vocal ou IA, France) : ATTIX App Store FR 4,44 sur 39 (id6748462097), Rappidos App Store FR 5 sur 15 (id6756271466). [source]

### A.2 Avis sur la fonction vocale ou IA

Constat d'ensemble : les avis d'utilisateurs sur la dictée et l'IA sont **rares** (quelques dizaines au total, sur des apps récentes à 10-40 avis). Aucun avis d'artisan trouvé ne parle de reconnaissance vocale gênée par l'accent ou le bruit : ce sujet n'apparaît que chez les éditeurs. Aucun avis trouvé ne décrit une erreur de l'IA sur une quantité ou un prix.

**Signal à vérifier avant de croire les notes des apps IA récentes** : les avis 5 étoiles se regroupent sur quelques jours. ATTIX : 4 avis le 2025-11-05 et 2 le 2025-11-08. Rappidos : 3 avis entre le 2026-03-12 et le 2026-03-15. Ce profil correspond à une sollicitation de l'entourage au lancement. C'est un indice, sans preuve : ces notes pèsent peu.

Compliments sur la voix ou l'IA, cités tels quels :
- Renalto, Google Play, 12 septembre 2025 : « Excellente application pour faire ses devis du bâtiment rapidement. Nécessite un peu de travail pour personnaliser et prendre le pli des instructions vocales envoyées, mais une fois maîtrisé ça déroule ! […] (réduit le temps de rédaction, on se concentre sur la vérification et la relecture !) » [source]
- Renalto, Google Play, 29 mai 2026 : « mon avis sur Renalto, il est le 5 ieme que j'ai utiliser et finalement le seul que j'ai acheté car il est réellement plus efficace, IA marche très bien, l'assistance edt réactif » [source]
- ATTIX, App Store FR, 2025-11-05 : « La fonctionnalité avec la voix fonctionne parfaitement et permet de gagner beaucoup de temps sur le terrain. » [source]
- ATTIX, App Store FR, 2025-11-05 : « J'évalue le coût de mes travaux en quelques instants, et sans taper des lignes et des lignes. L'ia pose des questions super précises, ça me permet de rien oublier. » [source]
- Rappidos, App Store FR, 2026-09-09 : « je sors de chez mon client, je raconte ce que j'ai fais au téléphone et en 30 secondes il me sort la facture, en 1 clic elle est envoyée. Plus besoin de rédaction et pas d'oubli! L'application me rappelle tout ce que j'aurais pu oublier » [source]
- Obat, Trustpilot, OHMS Services, 23 févr. 2026 : a testé « le devis vocal » pour un devis et s'en dit impressionné (citation tronquée par l'outil). [source] La page Obat cite le même avis : « Je viens d'essayer pour un devis la rédaction via le devis vocal et Ouah bluffé. » (témoignage choisi par l'éditeur) https://www.obat.fr/devis-factures/devis-vocal-ia/ [secondaire]
- Obat, page éditeur, Jean Christophe M. : « je les fais de suite avec le vocal je corrige vite fait en 15 mn » ; Marouan C. : « Module IA pour la saisie des devis est bluffant. » (témoignages choisis par l'éditeur) [secondaire]
- plancraft, Trustpilot, 3 étoiles, 1er juin 2026 : « Ich war mit der KI Hilfe sehr zufrieden. Im Programm selber fehlen mir einige Übersichtlisten. » [source]
- Jottle, App Store GB, 2026-11-06 : « Being able to create professional quotes simply by using my voice is a massive time saver. » [source]

Reproches sur la voix ou l'IA, cités tels quels :
- Renalto, App Store FR, 1 étoile, 2025-10-11 : « Pas du tout pratique sur téléphone ca zoom impossible de revenir a une taille ok  J'ai attendue 10 minutes pour générer un PDF du devis rien et ca m'a pris 5 minimum pour supprimer le compte » [source]
- ATTIX, App Store FR, 1 étoile, 2025-12-11 : « Je m'attendais à beaucoup mieux, rien de nouveau! Des concurrents comme Obat le font déjà et en beaucoup mieux. » ; 2026-04-28 : « Malheureusement pas encore au point par rapport à d'autre » [source]
- Obat, Trustpilot, 2 étoiles, Rénovatech, 28 août 2025 : « Depuis les avancées de l'IA, Obat paraît dépassé et peine à prendre le virage de l'automatisation. » [source]
- plancraft, App Store DE, 1 étoile, 2025-08-14, titre « Ki » : « Es wird mit KI geworben, allerdings gibt es das nicht offenbar noch nicht. Schade » [source]
- plancraft, Trustpilot, 3 étoiles, 11 nov. 2026 (poseur de sols) : « Bedauerlicherweise ist das Aufmaß ohne KI Funktion leider für unser Gewerk als Bodenleger sehr unzufriedenstellend. » [source]
- HERO, Trustpilot, 2 étoiles, 2 oct. 2026 : « HERO stellt seine KI-Funktionen stark in den Vordergrund » (suite tronquée ; le même avis : « Der kostenpflichtige (!) Support ist meist freundlich, war mir aber oft keine echte Hilfe. ») [source]

Ce que disent les éditeurs sur les limites de la voix sur chantier (pas des avis d'artisans) :
- Anodos (éditeur français de gestion de chantier), retour sur 50 PME, mai 2026 : « accentuation (régional), bruit ambiant, mots très spécialisés non reconnus » ; « meule » transcrit « mule ». https://dev.to/olivier_ebrahim_1bbaa5877/lia-vocale-en-gestion-de-chantier-retour-dexperience-apres-50-projets-btp-2d4o [source, chiffres non vérifiables]
- Même auteur, sur 2 000 devis : le bruit est « The Real Killer » (une perceuse fait entendre « widows » pour « windows ») ; remède : enregistrements de 10-15 secondes et bouton de remise à zéro ; « Give me 2×2 metres of scaffolding » est ambigu (surface, modules ou hauteur), une étape de confirmation « eliminates 80% of interpretation errors » ; environ 5 % des devis exigent de retaper au clavier, et les utilisateurs sont frustrés « when they HAD to type instead ». https://dev.to/olivier_ebrahim_1bbaa5877/voice-ai-for-construction-estimating-lessons-from-2000-quotes-in-2026-m18 [source, chiffres non vérifiables]
- Angebotsmeister (test KI-Syndikat, vérifié le 12 oct. 2026, sans prise en main) : « Korrekturen bleiben nötig » ; « Die Kalkulation rechnet nur so gut, wie du eigene Zeitwerte und Materialpreise gepflegt hast » ; « Spracherkennung über OpenAI, Angebotstexte über Anthropic, beide in Kalifornien » ; « Der Inhalt jedes KI-Angebots verlässt die EU trotzdem » ; plan gratuit « seit September 2026 beschnitten ». https://www.ki-syndikat.de/tools/angebotsmeister/ [source]
- Guide d'un vendeur canadien : les outils photo-vers-devis restent « wildly inaccurate as of 2026 » (exemple d'un chantier à 5 000 $ chiffré 1 200 $ ou 11 000 $). https://b2b-high-cpl.pages.dev/guides/ai-quote-generator-for-canadian-painters/ [secondaire, résumé de recherche]

### A.3 Avis sur le logiciel en général : reproches qui reviennent

Classés par fréquence observée dans les avis lus (comptage manuel, pas une statistique).

**1. Hausses de prix, souvent en cours de contrat**
- Obat, Trustpilot, 11 juin 2025 : « Bon logiciel mais beaucoup trop cher pour les fonctions de base, L'abonnement a doublé en 3 ans de 273 a 581... » [source]
- Obat, Trustpilot, 12 mars 2025 : « Je trouve inamissible d'appliquer une augmentation de tarif de 150%, » [source]
- Obat, Trustpilot, 25 nov. 2025 : « Beug sans arrêt ,travail perdu il faut recommencer perte de temps je paie 38.40 euros aujourd'hui 58.80 euros » [source]
- Obat, Trustpilot, 13 août 2024 : « Clients OBAT depuis 2 ans, nous avons été très déçus de constater un doublement de l'abonnement. » [source]
- Obat, App Store FR, 2025-10-05 : « Obat vous change les tarifs sans prévenir en cours d'année, ils ne remboursent pas la facturation […] faire très attention à la facturation de l'abonnement » [source]
- bexio, Trustpilot, 12 oct. 2026 : « Innerhalb von nur vier Jahren hat sich der Preis von rund 180 CHF auf über 550 CHF mehr als verdreifacht » ; 14 juil. 2026 : « Sie informierte mich über den 20% Aufschlag. » [source]
- plancraft, OMR : « Preiserhöhung im übertriebenen Maße. » / « Preisänderung im laufenden Vertrag » / « Funktionen die im laufenden Vertrag enthalten waren, wurden gestrichen » [source]
- plancraft, App Store DE, 2025-07-28 : « Wird jedes jahr teurer Nun schon 4000€ pa für einen 8 mann betrieb  Wechseln ist auch sehr schwer da es keine exportfunktion gibt  Support ist katastrofe » [source]
- Jobber, Trustpilot, 25 août 2026 : « they KEEP raising the price. » ; App Store US, 2026-10-02 : « $60/month for the lowest tier. Come on. » [source]
- ToolTime, OMR : « Das einzige was mich an Tooltime stört ist der hohe Preis. » [source]

**2. Bugs, plantages, travail perdu, régressions après mise à jour**
- Obat, Trustpilot, 27 mai 2025 : « Ce logiciel est une catastrophe. Il plante tellement souvent qu'il faut sauvegarder toutes les 5 minutes » [source]
- Obat, Trustpilot, 28 févr. 2025, **SARL LES PEINTRES JONNEAUX** (entreprise de peinture) : « Beaucoup de bugs, des surfaces qui se mettent toutes seules à 1 m2, » (suite tronquée) [source]
- Obat, App Store FR, 2024-08-27 : « L'application est régulièrement bloquée et je dois la quitter et la réouvrir et mon travail n'a pas enregistré. Je dois tout recommencer. » [source]
- Jobber, Google Play, 7 nov. 2026 : « I will have my entire quote or invoice filled out with all.my details and the app will freeze. […] It's embarrassing whem all of this is usually happening right in front of my customers. » [source]
- bexio Go, App Store CH, 2026-07-23 : « Es scheint, als ob neue Releases ungetestet ausgeliefert werden » [source]
- HERO, Google Play, 12 nov. 2026 : « In den letzten Monaten gab's immer wieder Probleme nach Updates. » [source]
- plancraft, App Store DE, 2026-10-15 : « Alle paar Tage ein Update , dann immer irgendeine Änderung. Last doch die App einfach laufen » [source]
- Jobber, App Store US, 2026-07-07 : « Seems like every update there's a bunch of people that aren't actually contractors working in the field making adjustments » [source]

**3. Application mobile appauvrie par rapport au poste de bureau**
- Obat, App Store FR, 2025-01-14 : « On ne peut pas utiliser la bibliothèque de tarifs sur l'application, peu d'intérêt pour un artisan du coup.. bcp de bugs. Passez votre chemin » [source]
- Obat, App Store FR, 2025-11-15 : devis en brouillon impossible à enregistrer ou envoyer depuis iPhone ou iPad. [source]
- Obat, Trustpilot, 14 oct. 2026 : « L'appli tel et tablette differe du pc » [source]
- Batappli, App Store FR, 2019-12-23 : « Les actions ce limite à la consultation de vos devis […] Et seulement si le logiciel et l'ordinateur sont allumés au bureau » ; 2018-11-08 : « Manque une chose essentielle : la possibilité de créer devis et facture directement chez le client et pouvoir synchroniser une fois rentré au bureau » [source]
- plancraft, App Store DE, 2024-05-30 : « Eigentlich kann die App nicht wirklich was ausser nur das Anzeigen was am PC erstellt wurde. » [source]
- bexio Go, App Store CH, 2025-10-23 : « Dommage qu'on ne peut pas modifier ces offres ou factures sur l'application comme on peut le faire sur ordinateur. » [source]
- Contre-avis : bexio Go, Google Play, 17 juin 2026 : « Offerten auf dem Mobile zu schreiben, braucht aus meiner Sicht kein Mensch. » [source]

**4. Support injoignable, payant, ou qui disparaît après la signature**
- bexio, Trustpilot, 2 nov. 2026 : « Weil auch der Support nachgelassen hat (keine Telefonsupport mehr für kleine Unternehmen) » [source]
- HERO, Google Play, 12 nov. 2026 : « Telefonische Hilfe gibt es nur noch für den Abopreis von 20 Euro im Monat. » [source]
- HERO, Trustpilot, 8 sept. 2026 : « Nach dem Vertragsabschluss, gibt es trotz versprechungen keinen Persönlichen Support mehr. » [source]
- Batappli, App Store FR, 2015-12-10 : « Obliger de passer par leur assistances qui est payante. » ; independant.io résume : « Quelques clients reprochent un temps d'attente trop long lors d'un appel téléphonique avec l'assistance technique » [source / secondaire]
- Jobber, Trustpilot : 9 des 20 avis à 1-2 étoiles de la première page portent sur le support (« There is absolutely no one you can call to help fix the problem. », 2 juin 2026). [source]

**5. Engagement, résiliation, prélèvements**
- Jobber, Trustpilot, 9 nov. 2026 : « They still refuse to allow the annual plan to be cancelled. » ; 15 juil. 2026 : « they still used it and pulled $215.92 out 2 different times. » [source]
- Obat, Trustpilot, 6 nov. 2024 : « Le service client a prélevé sur mon compte la cotisation annuelle en appliquant une augmentation du tarif annuel » ; 18 nov. 2024 : « j'ai payé l'année complète. fuyez fuyez très loin » [source]
- plancraft, Trustpilot, 16 juil. 2026 : « Das Abo um 1 Tag zu spät gekündigt » [source]
- Tolteck, Trustpilot, 2 janv. 2026 : suspension immédiate après un paiement refusé, « inacceptable aucune autre entreprise ne fait ca en 2026 » [source]
- HERO : facture reçue après résiliation et accès bloqué (reviewhero.io). [secondaire]

**6. Promesses de vente non tenues**
- ToolTime, App Store DE, 2022-02-28 : « In der Demovorstellung und am Telefon wurden mir mehrmals unsere Anforderungen zugesichert. […] Viele zugesicherte Funktionen, die mir wichtig waren, stehen nicht zur Verfügung. » [source]
- Jobber, App Store US, 2026-09-08 : « Sales guy was 6 weeks new and clearly lied about everything they said this app had. » [source]
- HERO, Trustpilot, 2 nov. 2026 : « Abschlüsse zählen mehr als zufriedene Kunden. » ; App Store DE, 2025-08-25 : « Die Verkäufer können ihren Job, sobald es dann um die Umstellung und die Einarbeitung in die Software geht ist allerdings ganz viel Luft nach oben. » [source]
- Obat, Trustpilot, 5 oct. 2023 : « très déçu par le logiciel ce qui nous a été vendu n'est pas la triste réalité. » [source]

**7. Connexion obligatoire, absence de hors ligne**
- ToolTime, OMR (chef de chantier) : « Auf Baustellen ist die Internetverbindung nicht immer ideal. » / « Eine stärkere Offline-Funktionalität wäre super » ; autre avis : « Ein Problem bei Tooltime ist die dauerhaft benötigte Internetverbindung » [source]
- Jobber, App Store US, 2026-07-07, titre « No offline support » : « Has to query for the schedule every time the app opens. No offline cacheing of any kind?? » [source]
- Magic Heidi, App Store CH, 2023-07-12, titre « Kein Offlinebetrieb » [source]
- plancraft, Google Play, 7 oct. 2026 : « Habe das Problem das jeden Tag zwischen 2 und 5 GB gebraucht werden. » [source]
- plancraft, OMR : « Ich finde es ein wenig schade dass es eine Cloudbasierte Lösung ist » [source]
- Tolteck se vend sur le fonctionnement « avec ou sans internet » (fiches tierces). [secondaire]

**8. Trop complexe, « usine à gaz »**
- Obat, App Store FR, 2025-09-15 : « Une usine à gaz! Trop de fonctionnalités inutiles qui complique l'usage! » [source]
- UK Business Forums (fil 2025-2026) : l'auteur, artisan seul, juge Jobber « way too heavy for just me » ; un modérateur rapporte que son ami maçon a trouvé toutes les apps « all way too complicated for him » et ajoute « They like WhatsApp. » https://www.ukbusinessforums.co.uk/threads/what-apps-are-trades-using-for-quoting-jobs-now.430559/ [source]
- HERO, App Store DE, 2026-08-19 : « Zu kompliziert. » ; für-gruender.de résume : « Zu viele Funktionen für kleine Projekte » [source / secondaire]

### A.4 Compliments qui reviennent (logiciel en général)

- **Le temps gagné et le devis fait sur place** : Obat, App Store FR, 2025-04-07 : « L'appli me permet de préparer mes devis sur le terrain et de les finaliser sur l'ordi le soir. Formation et support de qualité » ; Obat, App Store FR, 2024-10-14 : « Je gagne beaucoup plus de temps maintenant sur mes chantiers » ; Boby, Appvizer, 2022 : « je gagne énormément de temps au quotidien. » [source]
- **La simplicité, y compris pour les moins à l'aise** : Tolteck, Trustpilot, 13 nov. 2026 : « Logiciel de facturation et devis très bien, facile d'utilisation. » ; Renalto, App Store FR, 2026-04-23 : « c'est fluide, intuitif, même sans être ultra à l'aise avec les outils digitaux » ; ToolTime, Google Play, 23 mai 2026 : « Auch unsere älteren Monteure können die App bedienen, ohne un jedesmal zu fragen wie das nochmal geht. » ; plancraft, OMR : « Die Oberfläche von Plancraft ist super simpel gestaltet und übersichtlich gehalten. » [source]
- **Un humain qui rappelle vite** : Obat, Google Play, 15 janv. 2025 : « J'ai téléchargé pour tester tard dans la nuit. J'ai fait mon devis. Le lendemain à 10h, un conseiller, Anthony m'appelle pour savoir s'il peut m'aider » ; Tolteck, Trustpilot, 2 nov. 2026 : « Hotline disponible et efficace » ; Obat, Trustpilot, 14 oct. 2026 : « Un service client rapide et performant. » [source]
- **L'image professionnelle devant le client** : Renalto, App Store FR : « une image beaucoup plus sérieuse auprès des clients » ; Jobber, App Store US, 2026-09-28 : « help our company look more established and organized » [source]
- **La gratuité ou le prix d'entrée** : Boby, Appvizer, 2023 : « même la version gratuite est suffisamment complète pour débuter » ; Magic Heidi, Trustpilot, 27 mars 2026 : « Excellent logiciel même avec la version gratuite. » [source]

### A.5 Ce que les artisans demandent et n'obtiennent pas

| Demande | Citation | Source |
|---|---|---|
| Faire le devis complet sur le téléphone, avec sa bibliothèque de prix | voir A.3, point 3 (Obat, Batappli, plancraft, bexio) | [source] |
| Fonctionner sans réseau sur chantier | ToolTime OMR, Jobber, Magic Heidi (A.3, point 7) | [source] |
| Acompte et relances | Tolteck, Google Play, 26 févr. 2024 : « Ce qu'il manquerai, ça serai une petite case à cocher pour demander un acompte et choisir le pourcentage, il manque aussi une proposition de relance devis et/ou facture » | [source] |
| Marge calculée | Tolteck, Google Play, 22 sept. 2021 : « on rentre les prix de revient (le déboursé sec) pour les matériaux et la main-d'oeuvre, mais on ne sait pas pourquoi, car ça ne calcule pas le gain par facture ou devis » ; Trustpilot 2022 : absence de calcul des marges | [source] |
| Mise à jour d'un prix propagée aux ouvrages | Tolteck, Google Play, 24 juin 2021 : « la modification des articles (matériaux) à la base, n'engendre pas leur modification automatiquement dans les ouvrages […] grosse perte de temps et source d'erreur dans les devis » | [source] |
| Pouvoir partir avec ses données | plancraft, App Store DE : « Wechseln ist auch sehr schwer da es keine exportfunktion gibt » | [source] |
| Passerelle comptable et facture électronique | Obat, Trustpilot, 30 oct. 2026 : « Passerelle inexistante entre les plateformes comptables (Pennylane par exemple) » / « La facturation électronique n'est pas encore disponible.. » ; Tolteck, Trustpilot, 21 avr. 2026 : crainte de fin du service faute de réception des factures électroniques | [source] |
| Importer ses clients et articles existants | Magic Heidi, App Store CH, 2023-12-20 : « mir fehlt aber mindestens die Möglichkeit bestehenden Kunden und Artikelstamm zu importieren. » | [source] |
| Champs de facture complets (personne de contact, rabais, description d'article) | Magic Heidi, App Store CH, 2024-01-24 : « es ist nicht möglich einen Rabatt auf der Rechnung auszuweisen » | [source] |
| Interface stable, moins de mises à jour | plancraft, HERO, Jobber (A.3, point 2) | [source] |
| Tablette en paysage, iPad optimisé | Batappli, App Store FR, 2025-10-05 : « Manque un mode paysage pour l'iPad » ; ToolTime, App Store DE, 2025-07-03 : « Leider mit iPad nicht zu nutzen » | [source] |
| Métré automatique par IA | plancraft, Trustpilot (poseur de sols, voir A.2) | [source] |

---

## Partie B. Comment ces éditeurs trouvent leurs clients

Aucune interview d'éditeur ne donne un coût d'acquisition ou un taux de conversion par canal. Ce qui suit est l'observable.

**Distributeurs de matériaux (négoce)**
- Tolteck : Saint-Gobain Distribution Bâtiment France actionnaire majoritaire depuis 2018 ; intégré au programme fidélité « Génération Artisans » de Point P ; carte prépayée vendue en agence Point P (149 € HT par an pour les membres, 228 € HT pour les autres, février 2021) ; import depuis l'outil de chiffrage Solu+ de Point P. https://www.batiweb.com/actualites/numerique-bim/tolteck-fait-son-trou-dans-l-univers-de-la-distribution-saint-gobain-36957 et https://www.batiweb.com/actualites/numerique-bim/saint-gobain-annonce-que-les-outils-solu-et-tolteck-sont-desormais-relies-37474 [secondaire, résumé de recherche]. Tolteck a la plus grosse base d'avis de la liste (973 Trustpilot, 500 k+ téléchargements Android).
- HERO : app « Sonepar HERO » publiée par Sonepar Deutschland (grossiste électrique), App Store DE 4,42 sur 57. https://apps.apple.com/de/app/id1397296182 [source] ; page partenaire Lexware https://www.lexware.de/partner/hero/ [secondaire]
- ToolTime : intégré au programme de partenaires installateurs de Vaillant (teaser Handwerksblatt, mars 2022) [secondaire] ; recommandations Vaillant et Bosch citées sur le site de l'éditeur [secondaire].
- b:work (Allemagne) : logiciel distribué gratuitement par une douzaine de grossistes. https://www.handwerksblatt.de/betriebsfuehrung/software-bwork-soll-kleine-betriebe-und-existenzgruender-im-handwerk-branchenspezifisch-digitalisieren [secondaire]

**Associations professionnelles**
- CAPEB (France) : partenariats logiciels avec rabais aux adhérents : Costructor (10 % la première année, 2026, motif : facture électronique), Sage Batigest Connect (20 % la première année, flyer Batimat 2024), Artinove, Codris. https://www.capeb.fr/actualites/la-capeb-s-associe-a-costructor-pour-accompagner-les-entreprises-artisanales-du-batiment-dans-le-cadre-de-la-reforme-de-la-facturation-electronique [secondaire]
- SMGV (Suisse) : coopération avec smartconext (veille de chantiers, rabais de 30 % aux membres), 28 mai 2026 ; citation de Yvonne Saccomanno, responsable digitalisation : « Die Kooperation mit smartconext ermöglicht es unseren Betrieben, die Digitalisierung als Chance zu nutzen, um Aufträge schneller zu finden und professioneller zu akquirieren. » https://www.moneycab.com/startups/smgv-und-smartconext-schliessen-strategische-kooperation/amp/ [source]
- SMGV : programme de partenaires payant, palier Basic à 6 000 CHF. https://www.smgv.ch/documents/01_verband/07_partner/2025_09-partnerkonzept.pdf [secondaire]
- Abacus AbaBau (ERP suisse pour peintres et plâtriers) : bâti « sur les données de calcul du SMGV ». https://www.abacus.ch/fileadmin/ablage/02_dokumente/01_flyer/de/flyer_abacus_ababau_maler-gipser_de.pdf [secondaire]. Point d'attention : le catalogue de prix de référence de la branche (licences NPK SMGV) est payant et vendu par l'association. https://www.smgv.ch/documents/02_service/01_fachliches/ausschreibung/bestellformular-smgv-npk-lizenzen-v26-interaktiv.pdf [secondaire]
- FREPP : regroupe 5 associations cantonales (FR, GE, NE, VS, VD) ; partenaires affichés : SORBA (logiciel de gestion de branche peinture et plâtrerie), RUCO, la plateforme « Les Vrais Pros ». https://www.frepp.ch [source]. Lien commercial et conditions avec SORBA : [non trouvé].

**Vente au téléphone et démonstration**
- ToolTime (Handwerksblatt, 7 oct. 2021) : « Wir telefonieren jeden Monat mit 10.000 Handwerkern. » ; deux douzaines de vendeurs, 50 embauches prévues pour la vente ; préfère une démo guidée à un essai seul (« nicht 30 Tage lang das Produkt alleine testen ») ; « Er will seine Kunden emotional abholen. » ; plus de 1 000 entreprises clientes pour un cœur de cible de 250 000. https://www.handwerksblatt.de/betriebsfuehrung/handwerkersoftware-tooltime-bietet-sich-als-langfristiger-partner-fuer-die-digitalisierung-im-handwerk-an [source]
- Obat : rappel par un conseiller le lendemain de l'inscription (avis Google Play cité en A.4). Effet inverse rapporté sur Trustpilot, 13 mars 2024 : « Intrusif dès l'inscription alors qu'il y a 15 jours d'essais gratuit (mail + téléphone). » [source]
- Effet pervers documenté dans les avis : la promesse du vendeur devient le premier motif de colère (A.3, point 6).

**Gratuité et essai**
- Boby au lancement (Maddyness, 25 mai 2023) : 4 000 utilisateurs en quatre mois ; Aurélien Rey : « Nous ne voulions pas leur faire payer quelque chose qu'ils ont l'habitude de faire gratuitement. » https://maddyness.com/2023/05/25/boby-artisanat-batiment [source]. Offre gratuite retirée début 2025 (independant.io) [secondaire].
- Jobber : 14 jours sans carte bancaire [secondaire]. Obat : 14 ou 15 jours selon les sources [secondaire]. Angebotsmeister : plan gratuit réduit en septembre 2026 [source, KI-Syndikat].

**Parrainage**
- HERO : bannière in-app « HERO empfohlen und 1000€ verdienen », que l'utilisateur ne peut pas fermer et qu'il reproche (App Store DE, 2026-02-19). [source]
- Obat : liens `obat.fr/invitation?c=...&p=...` visibles dans les résultats de recherche ; conditions du programme [non trouvé].

**WhatsApp**
- Boby (Cegid) et Renalto acceptent la dictée par message vocal WhatsApp (comparatif Tensoria). https://tensoria.fr/blog/outils-ia-artisans-batiment-comparatif [secondaire]
- Signal terrain : « They like WhatsApp. » (UKBF, voir A.3, point 8). [source]

**Vente par le fondateur, au contact**
- Jobber : premier client un peintre d'Edmonton ; un des fondateurs est peintre ; Forrest Zeisler : « We went for six months, every couple of days, talking to a business, showing them our product, and hearing the word no. » [secondaire, résumés de podcasts]
- Anodos : « Social proof and quick wins (1-2 successful quotes) drive adoption more than feature richness. » ; 89 % d'usage de la voix à la quatrième semaine (chiffre éditeur). [source, non vérifiable]
- UKBF, un conseiller : « the issue isn't usually the tool, it's the process. » ; un autre : « Most jobs are not lost on price, they are lost in the silence after the quote. » [source]

---

## Partie C. Le marché romand

### C.1 Chiffres officiels (OFS, STATENT)

Source : OFS, STATENT, tables PXWeb lues par l'API le 2026-11-18. [source]
- Entreprises (« unités institutionnelles ») : `px-x-0602010000_105`, https://www.pxweb.bfs.admin.ch/pxweb/fr/px-x-0602010000_105/
- Établissements : `px-x-0602010000_103`
- Dernière année disponible dans la table : 2024. Statut définitif ou provisoire de 2024 : [non vérifié].
- « Romandie » ci-dessous = FR, VD, VS, NE, GE, JU (cantons entiers). Fribourg et Valais incluent leur partie germanophone ; le Jura bernois est compté dans Berne et ne se sépare pas dans ces tables.

**Peinture et plâtrerie, entreprises, 2024**

| Genre NOGA | Suisse : entreprises | Suisse : emplois | Romandie : entreprises | Romandie : emplois |
|---|---|---|---|---|
| 433401 Peinture | 4 297 | 13 419 | 882 | 1 825 |
| 433402 Peinture et gypserie sans prédominance | 3 576 | 15 956 | 1 632 | 7 240 |
| 433100 Travaux de plâtrerie | 1 115 | 6 680 | 76 | 354 |
| **Total** | **8 988** | **36 055** (32 221 EPT) | **2 590** | **9 419** (8 588 EPT) |

- Moyenne romande : 3,64 emplois par entreprise de peinture ou plâtrerie (2,07 pour « Peinture » seule).
- Par canton (total des trois genres) : Vaud 863, Genève 648, Valais 458, Fribourg 340, Neuchâtel 214, Jura 67. Berne (dont Jura bernois) : 895.
- 2023, même périmètre : Suisse 9 114 entreprises, Romandie 2 596.

**Second œuvre (NOGA 43.2 installation + 43.3 finition), entreprises, 2024**

| Périmètre | Suisse : entreprises | Suisse : emplois | Romandie : entreprises | Romandie : emplois |
|---|---|---|---|---|
| 43.3 Travaux de finition (plâtrerie, menuiserie, sols, carrelage, peinture, vitrerie) | 16 130 | 64 287 | 4 550 | 16 733 |
| 43.2 Installation (électricité, sanitaire, chauffage, isolation) | 14 291 | 122 630 | 3 817 | 31 738 |
| **Second œuvre 43.2 + 43.3** | **30 421** | **186 917** | **8 367** | **48 471** |

Romandie par canton (second œuvre) : Vaud 2 883, Genève 1 911, Valais 1 534, Fribourg 1 182, Neuchâtel 598, Jura 259.

**Part des entreprises de 1 à 9 personnes**
- OFS, démographie des entreprises, `px-x-0602030000_202`, année 2023, division 43 (travaux de construction spécialisés), toute la Suisse : 41 484 entreprises actives, dont 1 emploi : 16 702 ; 2 à 4 : 12 236 ; 5 à 9 : 6 453 ; 10 et plus : 6 093. **Part de 1 à 9 : 85,3 %.** [source] Cette statistique a un périmètre différent de STATENT ; ne pas additionner les deux.
- Part de 1 à 9 pour la peinture seule ou pour la Romandie : [non trouvé] dans les tables publiques de l'OFS (la classe de taille n'y croise pas le genre NOGA et le canton). La moyenne de 3,6 emplois par entreprise rend une part très majoritaire probable, sans chiffre officiel.

### C.2 Chiffres non officiels (à signaler comme tels)

- business-monitor.ch (agrégateur privé du registre du commerce, Zefix, FOSC, mis à jour le 2026-11-18) : 2 738 entreprises de peinture actives en Suisse romande (VD 933, GE 730, VS 514, FR 384, NE 184, JU 82), **98 % de micro-entreprises (1 à 9)** ; formes : Sàrl 45 %, raison individuelle 42 %, SA 11 %. https://business-monitor.ch/fr/cp/peintres/suisse-romande [source, chiffre non officiel]. Ordre de grandeur cohérent avec les 2 514 entreprises OFS des genres 433401 et 433402. Sa catégorie « plâtriers » (83 en Romandie) est incohérente et inutilisable.
- SMGV : environ 1 900 entreprises membres (Suisse entière, trois régions linguistiques). https://www.moneycab.com/startups/smgv-und-smartconext-schliessen-strategische-kooperation/amp/ [source, chiffre de l'association]
- Suva, classe 44D (peinture et plâtrerie) : 6 789 entreprises, 35 613 personnes occupées, document EKAS de 2017. [secondaire ; PDF renvoie 404]
- CCT du second œuvre romand : environ 22 000 salariés, toutes branches du second œuvre, cantons romands avec Jura bernois et Haut-Valais (brochure Unia 2019). [secondaire]
- FREPP : nombre d'entreprises membres [non trouvé] (absent du site).
- Fédération vaudoise des entrepreneurs, Groupe vaudois des entreprises de plâtrerie-peinture : nombre de membres [non trouvé].
- suissetec : nombre d'entreprises membres [non trouvé] (seuls 4 396 apprentis dans les entreprises membres, comptes 2025). [secondaire]

### C.3 Usage du numérique chez les artisans suisses

- Enquête Ofri (portail d'offres), publiée le 7 août 2020, 166 entreprises artisanales suisses : **37 % utilisent un logiciel pour établir factures et offres** ; plus de 80 % ont un site web ; le papier reste juste derrière le courriel comme moyen de communication. https://www.netzwoche.ch/news/2020-08-07/schweizer-handwerksbetriebe-sehen-die-digitalisierung-als-chance [source]. Réserves : échantillon petit, commanditaire intéressé, chiffre vieux de six ans.
- Statistique OFS de l'usage des TIC ou des logiciels par branche, construction : [non trouvé].
- Enquête de la SMGV, de la FREPP ou de suissetec sur l'usage de logiciels de devis : [non trouvé].
- Part des artisans suisses utilisant un logiciel de devis par la voix ou l'IA : [non trouvé].
