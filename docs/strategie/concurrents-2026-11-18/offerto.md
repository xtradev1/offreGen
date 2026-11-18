# Offerto (getofferto.ch) : fiche concurrent

Relevé du 18.11.2026. Toutes les pages publiques listées dans le sitemap ont été téléchargées et lues en entier (accueil, 10 pages métier, /ausprobieren, /demo, /demo/preisbuch, /demo/bexio, /partner, /impressum, /datenschutz, /agb, /start, /login, robots.txt, sitemap.xml), ainsi que le code JavaScript public des démos animées.

Légende des sources :
- **[officiel]** : lu sur une page officielle de getofferto.ch.
- **[officiel, code]** : lu dans le code JavaScript public servi par getofferto.ch (textes des démos, configuration). Fiable sur ce que le site affiche ; ne prouve rien sur l'application derrière le login.
- **[registre]** : registre du commerce, via l'API publique Zefix (publications FOSC/SHAB reproduites).
- **[technique]** : RDAP nic.ch, en-têtes HTTP, certificats (crt.sh), Wayback Machine.
- **[non trouvé]** : cherché, rien trouvé.

Convention de citation : les citations allemandes sont reproduites telles quelles, sauf le tiret demi-cadratin du site, remplacé par un tiret court « - ».

---

## Résumé en dix lignes

1. Domaine getofferto.ch enregistré le **03.10.2026** ; premier certificat TLS et première capture Wayback le même jour. Le produit a **six semaines et demie**.
2. L'éditeur « Kaco GmbH » s'appelle au registre **« KACO - Kacan Consulting GmbH »**, société de conseil financier créée en **2016** à Zurich, transférée à Stäfa en 2024. Un seul associé-gérant, capital CHF 20'000. But social inchangé : conseil dans le secteur financier, rien sur le logiciel.
3. Inscription ouverte (lien de connexion par courriel), essai 14 jours. Le compteur affiche « noch 50 Plätze » sur 50 places fondateur : **aucune place vendue**, ou compteur figé.
4. Aucune application mobile en magasin, aucun manifeste PWA. Application web seule.
5. Voix : une **note libre unique** dictée « nach der Besichtigung », « vom Parkplatz ». Aucune structure pièce par pièce. Sur la page d'essai, la dictée passe par la reconnaissance vocale du navigateur, réglée en **de-CH**.
6. Sortie : offre PDF à l'en-tête de l'artisan, positions numérotées 1.1, 1.2, positions optionnelles cochées par le client en ligne, facture depuis l'offre avec **QR-facture**. Acomptes : non trouvé.
7. Interface en allemand seulement. Offres en français « ja », l'interface romande « folgt, sobald der erste Romandie-Partner an Bord ist ».
8. Prix : une formule, CHF 69 HT/mois (39 fondateur, 59 via une association professionnelle), offres illimitées, 1 entreprise.
9. IA : **Anthropic (Claude)**, USA. Base Supabase à Zurich, exécution Vercel à Francfort, courriel Resend en Irlande.
10. Aucun témoignage, aucun réseau social, aucune presse. Vitesse élevée : entre le 03.10 et le 18.11, bexio, factures QR, Gmail, programme partenaires, 10 pages métier et 4 démos sont arrivés.

---

## 1. Ancienneté, société, statut commercial

### Registre du commerce [registre]
Source : API Zefix `https://www.zefix.admin.ch/ZefixREST/api/v1/firm/1262772.json` ; extrait cantonal : https://zh.chregister.ch/cr-portal/auszug/auszug.xhtml?uid=CHE-330.532.473 (non ouvert, page JSF).

- Raison sociale inscrite : « KACO - Kacan Consulting GmbH » (variantes Sàrl, Sagl, Ltd liab. Co). Le site écrit « Kaco GmbH », forme abrégée qui ne correspond pas à la raison sociale inscrite.
- UID CHE-330.532.473, CH-020-4058447-0, statut « EXISTIEREND ».
- **Inscription : FOSC n° 90 du 11.05.2016** (statuts du 02.05.2016), siège Zurich, Rieterstrasse 37. Capital social CHF 20'000 (200 parts de CHF 100). Renonciation au contrôle restreint.
- But (inchangé sur le fond en 2024) : « Erbringung von Beratungsdienstleistungen im Finanzsektor, insbesondere in der Planung und Durchführung von Projekten in den Bereichen Reorganisation, Restrukturierung, Outsourcing, Offshoring, Change, Lean Management und Strategie. » Aucune mention de logiciel.
- Personnes : un seul associé, gérant avec signature individuelle, de nationalité allemande, 200 parts. Une gérante externe a été inscrite d'août 2017 à janvier 2018, puis radiée.
- Mutations : 25.08.2017 changement d'adresse (Zurich) ; 07.09.2017 et 19.01.2018 changements d'organes ; **25.10.2024** : transfert du siège à Stäfa, domicile « c/o [l'associé], Rhynerstrasse 7, 8712 Stäfa », but reformulé (ajout des clauses de financement de groupe). Le domicile est donc l'adresse privée de l'associé.
- Inscrite à la TVA : « MWST-Nr.: CHE-330.532.473 MWST » (https://getofferto.ch/impressum) [officiel].

Lecture : une coquille de conseil existante a été réutilisée pour porter le produit. Aucune société dédiée, aucun indice de levée de fonds.

### Domaines [technique]
Source : RDAP nic.ch (`https://rdap.nic.ch/domain/<nom>`).
- **getofferto.ch : enregistré le 03.10.2026**, registraire Infomaniak Network SA, DNSSEC actif. Titulaire non publié par nic.ch.
- **offerto.ch : enregistré le 13.10.1999**, registraire InterNetX GmbH (Regensburg), sans DNSSEC. Titulaire non publié. Le site répond par une page « Welcome to nginx! ». Captures Wayback de 2000 à 2019 sans lien établi avec Kaco. Conclusion : offerto.ch appartient à un tiers inconnu ; Offerto ne possède pas son nom de domaine court. **Titulaire : non trouvé** (nic.ch ne publie plus les titulaires).

### Premières traces [technique]
- Certificats TLS (crt.sh) : premier certificat pour getofferto.ch et www.getofferto.ch le **03.10.2026**. Aucun sous-domaine public.
- Wayback Machine : **une seule capture, 03.10.2026 à 15:10**. État ce jour-là (https://web.archive.org/web/20261003151035/https://getofferto.ch/) :
  - prix unique « CHF 69 / Monat », pas de prix fondateur ;
  - témoignages en gabarit non rempli : « [Zitat Pilot-Betrieb 1 - was sich im Alltag geändert hat] », « [Name], Malergeschäft[Ort am Zürichsee] » ;
  - « Wir rufen zurück: [Telefon] » et « Offerto in 60 Sekunden - [Demo-Video] » : gabarits ;
  - bexio : « Die bexio-Anbindung folgt, bis dahin PDF. » ;
  - import : « 10 bis 50 alte Offerten als PDF » (aujourd'hui « Zwei, drei alte Offerten reichen zum Start »).
- Réseaux sociaux : aucun lien sur le site (vérifié dans le HTML de toutes les pages). Recherches LinkedIn, Instagram, Facebook, X, YouTube, Product Hunt, presse et forums : **non trouvé**. Moneyhouse : page générique sans contenu exploitable en lecture automatique.

### En vente ou en pré-lancement ? [officiel]
- Inscription ouverte : « Gib deine Mailadresse ein. Wir schicken dir einen Link - kein Passwort nötig. Neu hier? Dann legen wir deinen Zugang gleich an. » (https://getofferto.ch/start). Non testé : je n'ai pas créé de compte.
- AGB « Stand: Oktober 2026 », contrat payant après « Weiter nutzen » (https://getofferto.ch/agb).
- **Places fondateur restantes : « Gründerpreis für die ersten 50 Betriebe, solange das Abo läuft - noch 50 Plätze »** (https://getofferto.ch/#preis). 50 sur 50 au 18.11.2026.
- Aucun témoignage, aucun client nommé, aucun chiffre d'usage.
- Verdict : commercialement ouvert, sans client payant visible. Pré-lancement de fait.

## 2. Application mobile

- App Store (API iTunes, magasin CH, « offerto ») : **non trouvé**.
- Google Play (recherche « offerto », CH) : **non trouvé**.
- PWA : aucun manifeste (`/manifest.json`, `/manifest.webmanifest`, `/site.webmanifest` en 404), aucun service worker (`/sw.js` 404), aucun `assetlinks.json` ni `apple-app-site-association` [technique].
- Impressum : « Website und App sind erreichbar unter getofferto.ch. » [officiel]. L'« App » est l'application web.
- En-tête HTTP : `Permissions-Policy: camera=(), ... microphone=(self)` [technique]. Micro autorisé, caméra en direct bloquée par le navigateur. L'envoi d'une photo par sélection de fichier reste possible ; non vérifié dans l'application.
- La démo indique « Handy quer drehen für die grosse Ansicht » [officiel, code] : usage mobile prévu dans le navigateur.

## 3. Entrée

### Canaux [officiel]
- « Mail weiterleiten, Text einfügen oder nach dem Telefon reinsprechen. Fehlt was, fragt Luca genau einmal. » (https://getofferto.ch/)
- Adresse de réception par entreprise, visible dans la capture du 03.10 : « Weitergeleitet an muster-maler@in.getofferto.ch » [technique, Wayback].
- Démo bexio, écran « Verbindungen » : « Webseiten-Formular - Anfragen von deiner Website » [officiel, code]. Formulaire web intégrable sur le site de l'artisan ; état réel non vérifié.
- Datenschutz : « an Offerto weitergeleitete Anfragen » via Resend (https://getofferto.ch/datenschutz).

### Voix [officiel, code]
- Démo « Nach der Besichtigung » (onglet de l'accueil) : « Nach der Besichtigung. », « Vom Parkplatz », « Kurz reinsprechen. Luca schreibt. », « Einfügen, beschreiben - oder aufs Mikrofon tippen und erzählen. »
- Exemple dicté, d'un seul tenant : « Familie Brunner, Seestrasse 14, Männedorf. Bad komplett, etwa 6 Quadratmeter, alte Platten raus, neue 60er rein. Dusche neu, WC wandhängend. Montag könnte ich anfangen. »
- Ce que Luca extrait : bloc « LUCA HAT ERKANNT » avec les champs Kunde, Objekt, Leistung, Menge, Termin, puis le brouillon.
- **Structure pièce par pièce : non trouvé.** La voix sert à une note courte et globale, après la visite. Aucune dictée guidée, aucune segmentation par pièce, **durée maximale : non trouvée**.
- Implémentation sur /ausprobieren : `window.SpeechRecognition ?? webkitSpeechRecognition`, `lang = "de-CH"`, `continuous = true`. Message si le navigateur ne sait pas : « Dein Browser kann nicht diktieren. Nutze die Diktierfunktion deiner Tastatur oder füge den Text ein. » Conséquences : transcription par le navigateur (Google pour Chrome, Apple pour Safari), langue forcée en suisse alémanique, pas de dictée sous Firefox. L'implémentation dans l'application connectée est **non vérifiée**.
- Une démo montre dans la boîte de réception une entrée « Sprachnotiz » (note vocale) à côté des courriels.

### Photos, plans, PDF, mesures [officiel]
- Page métallerie : « Mengen aus Plänen oder Fotos schätzt Luca gelb. » (https://getofferto.ch/fuer/metallbau). Seule mention des photos et plans sur tout le site. Le canal d'envoi n'est pas décrit.
- Import des anciennes offres : « hochgeladene Offerten (PDF, Word) » (Datenschutz). Tri automatique : « Rechnung - übersprungen », « Fremdofferte - übersprungen » [officiel, code].
- Peintres : « Nennt der Kunde nur die Wohnfläche, rechnet Luca die Wand- und Deckenfläche hoch, markiert sie gelb und sagt dir, wie er geschätzt hat. Korrigierst du den Faktor ein paarmal, übernimmt er deinen. » (https://getofferto.ch/fuer/maler). Démo : 80 m² habitables donnent « ca. 290 m² », avec « Luca fragt: Raumhöhe 2.4 m angenommen - stimmt das? ».
- Mesure par photo (métrage automatique) : **non trouvé**. WhatsApp : **non trouvé** (le mot n'apparaît que dans une liste technique de robots d'aperçu de liens).

### Catalogue de prix [officiel]
- « Du lädst einmal deine alten Offerten hoch. Daraus liest Luca Firma, MwSt, Konditionen, deine Texte und jede Preisposition. Nichts wird erfunden - jeder Preis zeigt, aus welcher Offerte er stammt. » (accueil)
- Traçabilité dans le brouillon : « Preis aus Offerte 240461, Sep 2024 », « 9.30/m² aus 6 Offerten · Menge geschätzt - bitte prüfen », « Spanne 140-1'180 aus 2 Offerten - hängt vom Objekt ab » [officiel, code].
- Sans prix connu : « ist kein passender Preis im Preisbuch, bleibt das Feld leer und du trägst ihn ein. Luca erfindet keine Preise. » (https://getofferto.ch/fuer/schreiner)
- Tarifs horaires : « Elektromonteur 112.- / h » deviennent des positions ; heures de régie estimées et marquées en jaune (https://getofferto.ch/fuer/elektro).
- Positions implicites : « Gerüst, Absperrung oder Entsorgung übernimmt Luca als eigene Positionen aus deinen Offerten - auch wenn der Kunde sie nicht erwähnt » (https://getofferto.ch/fuer/dachdecker).
- Apprentissage : « Luca merkt sich die Korrektur fürs nächste Mal. »
- Saisie directe d'un catalogue de prix (sans anciennes offres), import CSV d'un barème : **non trouvé**. Le catalogue naît uniquement des anciennes offres ou de bexio.

## 4. Sortie

- Offre PDF à l'en-tête de l'artisan : la capture du 03.10 montre l'en-tête « Malergeschäft Muster GmbH, Dorfstrasse 5, 8712 Stäfa, CHE-123.456.789 MWST », la formule de politesse et les conditions reprises des anciennes offres [technique, Wayback]. « PDF-Erstellung und Versand » (Datenschutz) [officiel]. Logo : non vérifié.
- Structure : **par positions numérotées** (1.1, 1.2, 1.3) avec unité, quantité, prix, sous-total HT, TVA 8.1 %, total TTC (« Offertenbetrag inkl. MwSt. Fr. 3'628.90 »). **Regroupement par pièce : non trouvé.**
- Positions optionnelles : « Teure Varianten setzt du als optionale Position - der Kunde kreuzt auf der Annahme-Seite an, was er will, und das Total rechnet mit. » (https://getofferto.ch/fuer/sanitaer). Le code de calcul gère `optional`, `selected_by_customer`, `recommended` et `rate_only` (position au tarif seul, hors total) [officiel, code].
- TVA suisse : taux 8.1, 2.6, 3.8 ; arrondi à 5 centimes ; format « 1'234.50 » ; numéro TVA reconnu avec suffixe MWST, TVA ou IVA [officiel, code].
- Envoi : depuis Offerto (Resend) ou depuis le Gmail de l'artisan (autorisation `gmail.send`) (Datenschutz). « Antworten landen bei dir. »
- Acceptation en ligne : « Bei der Annahme speichern wir Name, Zeitpunkt und IP-Adresse als Nachweis » ; suivi des ouvertures (« geöffnet 2× ») (Datenschutz, démo).
- Relances : « Erinnert dich ans Nachfassen und bereitet die Mail vor. » (accueil). Relance préparée, envoi manuel.
- **Facture : oui.** « Erstellen Sie aus einer angenommenen Offerte eine Rechnung, speichern wir Rechnungsnummer, Positionen, Beträge, Fälligkeit, Empfängeradresse und Zahlungsstatus sowie die IBAN Ihres Betriebs für den QR-Zahlteil. Offerto bucht keine Zahlungen und liest keine Kontodaten; «bezahlt» setzen Sie selbst. » (Datenschutz)
- Démo bexio : « Rechnung RE-2026-0087 · Positionen 1:1 aus Offerte · QR-Rechnung · fällig in 30 Tagen » [officiel, code].
- **Acomptes, factures partielles, métré final (Ausmass), rappels de paiement : non trouvé.** Partenaires : « Verbuchung, Mahnwesen, Abschluss bleiben bei Ihnen. » (https://getofferto.ch/partner)
- Export à la résiliation : « PDF und CSV ».

## 5. Langues

- Interface : allemand uniquement. Configuration publique : `locale: "de-CH"`, `greeting: "Grüezi"`, `docName: "Offerte"` [officiel, code]. Tutoiement partout.
- **Citation clé** (https://getofferto.ch/partner, FAQ) : « Gibt es Offerto auf Französisch? Die Offerten ja - Luca schreibt in der Sprache der hochgeladenen Vorlagen. Die Oberfläche folgt, sobald der erste Romandie-Partner an Bord ist. »
- Italien, anglais : **non trouvé**.
- Version romande : annoncée sans date, conditionnée à un partenaire romand. Au 18.11.2026, la dictée de la page d'essai est réglée en de-CH : une dictée en français y serait mal transcrite.
- La configuration est centralisée dans un objet `COUNTRY` (devise, TVA, locale, mentions légales) : architecture prévue pour décliner d'autres pays ou langues [officiel, code ; intention déduite].

## 6. Prix

| Élément | Valeur | Source |
|---|---|---|
| Tarif normal | CHF 69 / mois / entreprise, HT (TVA 8.1 % en plus) | accueil, AGB art. 4 |
| Prix fondateur | CHF 39 / mois HT, 50 premières entreprises, « solange das Abo läuft » | accueil |
| Prix association | « Mitgliederlink mit CHF 59 statt 69 pro Monat (exkl. MWST) » | /partner |
| Essai | 14 jours, sans carte, fin automatique | AGB art. 3 |
| Engagement | aucun, résiliable à la fin du mois de facturation, pas de remboursement au prorata | AGB art. 5 |
| Facturation | mensuelle, par courriel, payable à 30 jours | AGB art. 4 |
| Contenu | « Unbegrenzt Offerten, 1 Betrieb », tous métiers, envoi, acceptation en ligne, relances, base à Zurich | accueil |
| Frais d'installation | aucun | accueil |

- Une seule formule. **Nombre d'utilisateurs par entreprise : non trouvé** (« 1 Betrieb » seulement). Formule multi-entreprises ou paliers : non trouvé.
- Incohérence : l'AGB (art. 4) ne mentionne que CHF 69 et le prix membre, **pas le prix fondateur à 39**.
- Paiement : facture uniquement (« Zahlungsangaben wie Kreditkarten speichern wir nicht »).

## 7. Intégrations, hébergement, IA

- **bexio** : lecture des contacts, création de contacts, transfert des offres et factures envoyées, mise à jour du statut d'acceptation ; reprise des offres bexio des 24 derniers mois pour bâtir le catalogue. « Bestehende Dokumente in bexio ändert Offerto nicht. » (Datenschutz). Démo : « Offerto schreibt, bexio bucht. »
- **Google (Gmail)** : envoi seul (`gmail.send`, `openid`, `email`), sans lecture de la boîte (Datenschutz).
- **Formulaire web** pour le site de l'artisan : vu dans la démo, détail non trouvé.
- **Fournisseurs de matériaux** : « Preislisten-Integration. Ab 2027 » (/partner).
- **Plateformes de demandes** (le site cite Ofri, Renovero, Houzy, local.ch) : « Integration auf Anfrage » (/partner).
- Autres logiciels de gestion (Abacus, Sage, etc.) : **non trouvé**.

Sous-traitants déclarés (https://getofferto.ch/datenschutz, section 4) [officiel] :

| Prestataire | Rôle | Lieu de traitement |
|---|---|---|
| Supabase Inc., USA | base de données, fichiers, login | Zurich (AWS eu-central-2) |
| Vercel Inc., USA | hébergement du site et de l'application | exécution à Francfort, diffusion mondiale |
| Resend (Plus Five Five, Inc.), USA | envoi et réception de courriels | Irlande |
| **Anthropic PBC, USA** | « KI-Verarbeitung (Sprachmodell Claude) » : lecture des offres importées, rédaction des brouillons | USA |

- Citation : « Die Datenbank mit Ihren Daten befindet sich in Zürich. Zur Erbringung des Dienstes werden Daten jedoch auch in der EU (Deutschland, Irland) und in den USA bearbeitet, insbesondere bei der KI-Verarbeitung durch Anthropic. »
- Le slogan « Datenbank in Zürich » est exact au sens strict. Les données des clients finaux partent aux USA pour l'IA.
- Constaté [technique] : `Server: Vercel`, `X-Vercel-Id: fra1`, CSP autorisant un projet `*.supabase.co`, Next.js (Turbopack). Domaine chez Infomaniak.
- Pas de traqueur, polices auto-hébergées (Datenschutz) ; confirmé par la CSP stricte.

## 8. Équipe, financement, clients

- Site : « Ein kleines Team aus Stäfa am Zürichsee. » (accueil). Page partenaires : « Gegründet von einem, der sein Haus renoviert hat und dabei zwölf Offerten eingeholt hat - alle abends um elf geschrieben. » [officiel]
- Registre : un seul associé-gérant [registre]. Taille réelle de l'équipe : **non trouvé** ; tout indique une personne seule.
- Financement : capital de CHF 20'000 en 2016, aucune levée trouvée. **Non trouvé.**
- Autres produits de Kaco : **non trouvé** (le registre décrit une activité de conseil financier).
- Clients, témoignages, chiffres d'usage : **non trouvé**. Les témoignages de la version du 03.10 étaient des gabarits vides ; ils ont été retirés depuis.
- Associations visées pour le canal partenaires : « SMGV, suissetec, EIT.swiss, JardinSuisse, VSSM, Gebäudehülle, Metaltec ». Partenariat effectivement signé : **non trouvé**.
- Commission partenaires : « 20 % wiederkehrende Provision » pour fiduciaires et plateformes, payée trimestriellement.

## 9. Forces, faiblesses, vitesse

### Faiblesses visibles
- **Produit sans clients visibles** : 50 places fondateur sur 50 libres, aucun témoignage, aucune présence publique, domaine de six semaines.
- **Allemand seulement**, dictée réglée en de-CH. Romandie reportée à l'arrivée d'un partenaire romand.
- **Pas de visite pièce par pièce** : une note vocale libre et courte. Les quantités sont estimées (« 80 m² Wohnfläche → ca. 290 m² ») et marquées en jaune. Rien pour relever une surface pièce par pièce sur place.
- **Catalogue dépendant du passé** : il faut d'anciennes offres ou bexio. Un artisan sans historique numérique démarre à vide.
- **IA hors de Suisse** (Anthropic, USA) derrière un discours « Datenbank in Zürich ».
- **Société de conseil financier recyclée**, raison sociale abrégée sur le site, but social sans rapport, domicile privé : fragilité perçue par un client méfiant ou une association.
- Facturation minimale : QR-facture depuis l'offre, sans acomptes, rappels ni encaissement.
- Pas d'application installable, caméra bloquée par la politique du navigateur.
- AGB sans le prix fondateur.

### Forces visibles
- Promesse simple et bien écrite, en dialecte de métier (« Abends keine Offerten mehr schreiben. »).
- Prix traçables (« Preis aus Offerte 240461 »), estimations en jaune, aucune invention de prix affichée.
- **Intégration bexio livrée**, très présente chez les artisans alémaniques ; canal fiduciaires bexio avec 20 % de commission.
- Canal associations avec prix membre (SMGV en tête pour les peintres).
- Acceptation en ligne avec options cochées par le client, suivi des ouvertures, QR-facture.
- Envoi depuis le Gmail de l'artisan.
- Coût bas, sans engagement, inscription sans carte.
- Dix pages métier avec FAQ spécifique : effort de référencement.

### Vitesse d'avancement [technique + officiel]
Entre la capture du 03.10.2026 et le 18.11.2026 :
- bexio passé de « folgt » à livré (démo animée dédiée) ;
- factures avec QR-facture, connexion Gmail, acceptation en ligne avec options ;
- prix fondateur, programme partenaires avec tableau de bord, prix membre ;
- 10 pages métier, 4 démos animées, page d'essai sans compte ;
- AGB et Datenschutz datées « Oktober 2026 ».
Environ six semaines pour tout cela : rythme d'un développeur seul très productif, probablement assisté par IA. Journal des nouveautés, feuille de route publique : **non trouvé**. Seule annonce datée : intégration des listes de prix fournisseurs « Ab 2027 ».

## Non trouvé (récapitulatif)
- Titulaire de getofferto.ch et de offerto.ch (nic.ch ne publie plus les titulaires).
- Toute publication sur réseaux sociaux, Product Hunt, presse, forums.
- Application iOS ou Android, PWA.
- Durée maximale de dictée ; dictée en français ; mode de transcription dans l'application connectée.
- Structure par pièce ; mesure par photo ; WhatsApp.
- Acomptes, factures partielles, rappels ; logo sur le PDF.
- Nombre d'utilisateurs par compte.
- Italien, anglais ; date de la version romande.
- Taille réelle de l'équipe, financement, clients, chiffres d'usage, partenariats signés.
- Journal des nouveautés ou feuille de route.

## Non fait, volontairement
- Pas de compte créé, pas d'appel à l'API d'essai `/api/probe` (exécuterait leur IA à leurs frais) : l'application derrière le login reste non observée.
