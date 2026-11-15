<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Adresse\Adresse;
use OffreGen\Compte\Reglages;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotFactures;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Facture\Montants;
use OffreGen\Facture\Numerotation;
use OffreGen\Facture\PlanAcomptes;
use OffreGen\Facture\QrFacture;
use OffreGen\Facture\SectionPaiement;
use OffreGen\Http\Conflit;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\Formats;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\Saisie;
use OffreGen\Offre\Totaux;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Les factures (sous-chantier 18, réponses de Xavier du 2026-11-13) : « Facturer » depuis l'offre, la facture en
 * brouillon (ses lignes passent par LigneControleur), son en-tête, sa suppression, l'onglet Factures.
 * Une facture émise ou annulée ne s'écrit plus ici : 409.
 *
 * @phpstan-import-type Facture from DepotFactures
 * @phpstan-import-type Offre from DepotOffres
 * @phpstan-import-type Genre from PlanAcomptes
 */
final class FactureControleur
{
    private const FUSEAU = 'Europe/Zurich';

    public function __construct(private readonly Conteneur $c)
    {
    }

    /** L'onglet Factures : de la plus récente à la plus ancienne, avec le montant à payer et l'état. */
    public function liste(Request $requete): Response
    {
        $portee = $this->portee($requete);
        $depot = $this->c->factures($portee);
        $factures = $depot->liste();
        $r = $this->c->reglages($portee)->lire();
        $lignes = $depot->lignesDe(array_column($factures, 'id'));
        $aujourdhui = $this->aujourdhui()->format('Y-m-d');
        $rangees = [];
        foreach ($factures as $f) {
            $montants = Montants::de($f, $lignes[$f['id']] ?? [], $r, $f['statut'] === DepotFactures::BROUILLON ? $depot->dejaFacture($f['offre_id']) : 0);
            $localite = self::localite($f['client_adresse']);
            $rangees[] = $f + [
                'a_payer_vue' => $montants['a_payer'],
                'localite' => $localite,
                'etat' => self::etat($f, $aujourdhui),
                'texte' => mb_strtolower($f['numero'] . ' ' . $f['client_nom'] . ' ' . $localite . ' ' . $f['titre']),
            ];
        }
        return new Response($this->c->twig()->render('factures.html.twig', ['factures' => $rangees]));
    }

    /**
     * La feuille Facturer (maquette 25, E1 à E4) : la facture proposée, son aperçu, « Créer la facture ».
     *
     * @param array<string, string> $parametres
     */
    public function nouvelle(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $possibles = $this->possibles($requete, $offre);
        return $this->formulaireNouvelle($requete, $offre, $possibles, $this->champsProposes($requete, $possibles), [], 200);
    }

    /**
     * L'aperçu de la facture que « Créer la facture » créerait avec ces choix (E1) : le document, marqué « Aperçu », et
     * le montant à payer pour le bouton. Rien n'est écrit. 422 et le message si un choix est refusé.
     *
     * @param array<string, string> $parametres
     */
    public function apercuNouvelle(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $possibles = $this->possibles($requete, $offre);
        $q = $requete->query;
        $champs = array_merge($this->champsProposes($requete, $possibles), array_filter([
            'genre' => $q->getString('genre'), 'pourcent' => $q->getString('pourcent'), 'montant' => $q->getString('montant'),
            'numero' => $q->getString('numero'), 'date' => $q->getString('date'), 'echeance' => $q->getString('echeance'),
        ], static fn (string $v): bool => $v !== ''));
        if (!$q->has('montant')) {
            $champs['montant'] = '';
        }
        // Offre facturée en entier : un état, pas une saisie refusée (200, le message seul).
        if ($possibles === []) {
            return new Response($this->c->twig()->render('_apercu_facture.html.twig', ['erreurs' => ['genre' => 'Cette offre est facturée en entier.']]));
        }
        // Le client n'est exigé qu'à la création : l'aperçu montre déjà la facture.
        [$pret, $erreurs] = $this->lireFacturer($requete, $offre, $possibles, $champs);
        if ($pret === null) {
            return new Response($this->c->twig()->render('_apercu_facture.html.twig', ['erreurs' => $erreurs]), 422);
        }
        return new Response($this->c->twig()->render('_apercu_facture.html.twig', $this->vueApercu($requete, $offre, $pret)));
    }

    /**
     * « Créer la facture » (E2, E3) : créée et émise d'un coup, puis son PDF prêt à envoyer. Si l'émission est empêchée
     * (ligne sans prix, montant négatif), rien ne reste : la feuille revient avec la raison. « Corriger des lignes »
     * (suite=corriger, finale seulement, E5) : un brouillon sans numéro, qui prendra le sien à la création.
     *
     * @param array<string, string> $parametres
     */
    public function creer(Request $requete, array $parametres): Response
    {
        $portee = $this->portee($requete);
        $offre = $this->offre($requete, $parametres);
        if ($offre['client_id'] === null) {
            throw new Conflit("Choisissez le client de l'offre avant de la facturer.");
        }
        $possibles = $this->possibles($requete, $offre);
        if ($possibles === []) {
            throw new Conflit('Cette offre est déjà facturée en entier.');
        }
        $r = $requete->request;
        $champs = ['genre' => $r->getString('genre'), 'pourcent' => $r->getString('pourcent'), 'montant' => $r->getString('montant'),
                   'numero' => $r->getString('numero'), 'date' => $r->getString('date'), 'echeance' => $r->getString('echeance')];
        $corriger = $r->getString('suite') === 'corriger';
        if (trim($champs['numero']) === '') {
            // Numéro laissé vide : le suivant, comme le propose la feuille.
            $champs['numero'] = $this->numeroPropose($requete);
        }
        [$pret, $erreurs] = $this->lireFacturer($requete, $offre, $possibles, $champs);
        if ($pret !== null && $corriger && $pret['choisi']['genre'] !== 'finale') {
            $erreurs['genre'] = "Seule la facture finale se corrige ligne par ligne : un acompte se crée tel quel.";
        }
        if ($pret === null || $erreurs !== []) {
            return $this->formulaireNouvelle($requete, $offre, $possibles, $champs, $erreurs, 422);
        }
        $choisi = $pret['choisi'];
        $entete = $pret['entete'];
        // Le brouillon attend son numéro (E5), sauf si l'artisan en a tapé un autre que celui proposé.
        $numero = $corriger && $entete['numero'] === $this->numeroPropose($requete) ? null : $entete['numero'];
        try {
            $id = $this->c->factures($portee)->creer([
                'offre_id' => $offre['id'], 'numero' => $numero, 'genre' => $choisi['genre'],
                'acompte_rang' => $choisi['rang'], 'acompte_nombre' => $choisi['nombre'], 'acompte_pourcent' => $choisi['pourcent'],
                'date_facture' => $entete['date'], 'echeance' => $entete['echeance'],
                'titre' => $offre['titre'], 'client_nom' => (string) $offre['client_nom'], 'client_adresse' => $offre['client_adresse'],
                'client_postale' => $this->adresseClient($requete, $offre),
            ], $pret['lignes'], $this->c->horloge()->now());
        } catch (\PDOException $e) {
            // Deux factures créées au même instant avec le même numéro : l'index unique tranche.
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            return $this->formulaireNouvelle($requete, $offre, $possibles, $champs, ['numero' => 'Ce numéro est déjà pris par une autre facture.'], 422);
        }
        if ($corriger) {
            Flash::dire($requete, 'info', 'Corrigez les lignes, puis « Créer la facture »');
            return new RedirectResponse('/factures/' . $id, 303);
        }
        $f = $this->c->factures($portee)->parId($id) ?? throw new PageIntrouvable();
        $raison = $this->emettreFacture($requete, $f);
        if ($raison !== null) {
            $this->c->factures($portee)->supprimerBrouillon($id);
            return $this->formulaireNouvelle($requete, $offre, $possibles, $champs, ['creation' => 'La facture n\'est pas créée : ' . $raison], 422);
        }
        Flash::dire($requete, 'info', 'Facture ' . $entete['numero'] . ' créée');
        return new RedirectResponse('/factures/' . $id . '/apercu', 303);
    }

    /**
     * Les choix proposés à l'ouverture de la feuille : la première facture possible, le pourcentage du plan, le numéro
     * suivant, la date du jour et l'échéance des conditions.
     *
     * @param list<Genre> $possibles
     * @return array{genre: string, pourcent: string, montant: string, numero: string, date: string, echeance: string}
     */
    private function champsProposes(Request $requete, array $possibles): array
    {
        $date = $this->aujourdhui();
        $genre = $possibles[0]['genre'] ?? 'finale';
        $acompte = array_values(array_filter($possibles, static fn (array $p): bool => $p['genre'] === 'acompte'))[0] ?? null;
        return [
            'genre' => $genre,
            'pourcent' => $acompte === null ? '' : (string) $acompte['pourcent'],
            'montant' => '',
            'numero' => $this->numeroPropose($requete),
            'date' => $date->format('Y-m-d'),
            'echeance' => PlanAcomptes::echeance($this->c->reglages($this->portee($requete))->lire(), $genre, $date)->format('Y-m-d'),
        ];
    }

    /**
     * Lit les choix de la feuille et calcule les lignes de la facture, sans rien écrire : la même lecture sert l'aperçu
     * et la création, l'aperçu montre donc exactement ce qui sera créé.
     *
     * @param Offre $offre
     * @param list<Genre> $possibles
     * @param array<string, string> $champs genre, pourcent (nombre ou « chf »), montant (CHF TTC), numero, date, echeance
     * @return array{array{choisi: Genre, entete: array{numero: string, date: \DateTimeImmutable, echeance: \DateTimeImmutable}, lignes: list<array{designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, tva_pour_dix_mille: int|null, categorie: string|null, produit_id: int|null}>}|null, array<string, string>}
     */
    private function lireFacturer(Request $requete, array $offre, array $possibles, array $champs): array
    {
        $portee = $this->portee($requete);
        [$entete, $erreurs] = $this->lireEntete($requete, $champs, null);
        $choisi = null;
        foreach ($possibles as $p) {
            if ($p['genre'] === ($champs['genre'] ?? '')) {
                $choisi = $p;
            }
        }
        if ($choisi === null) {
            $erreurs['genre'] = 'Choisissez la facture à faire.';
            return [null, $erreurs];
        }
        $r = $this->c->reglages($portee)->lire();
        $lignesOffre = $this->c->offres($portee)->lignes($offre['id']);
        $parTaux = Totaux::parTaux($lignesOffre, $r->tvaPourDixMille);
        $reste = Totaux::calculer($lignesOffre, $r->tvaPourDixMille, $r->arrondiCinqCentimes)['ttc'] - $this->c->factures($portee)->dejaFacture($offre['id']);
        $lignes = [];
        if ($choisi['genre'] === 'acompte') {
            $pourcent = trim($champs['pourcent'] ?? '');
            if ($pourcent === 'chf') {
                // E4 : ce que le client paie, TVA comprise.
                $montant = Saisie::centimes($champs['montant'] ?? '');
                if ($montant === null || $montant <= 0) {
                    $erreurs['montant'] = "Indiquez le montant à facturer, TVA comprise, par exemple 1500.";
                    return [null, $erreurs];
                }
                $parts = PlanAcomptes::partsPourMontant($parTaux, $montant, $r->tvaPourDixMille, $r->arrondiCinqCentimes);
                $choisi['pourcent'] = null;
                $choisi['nombre'] = null;
            } else {
                if ($pourcent !== '' && (preg_match('/^\d{1,3}$/', $pourcent) !== 1 || (int) $pourcent < 1 || (int) $pourcent > 100)) {
                    $erreurs['pourcent'] = 'Pourcentage entier, de 1 à 100.';
                    return [null, $erreurs];
                }
                if ($pourcent !== '' && (int) $pourcent !== $choisi['pourcent']) {
                    // Hors du plan des conditions : plus de « sur N » dans la désignation.
                    $choisi['pourcent'] = (int) $pourcent;
                    $choisi['nombre'] = null;
                }
                $parts = PlanAcomptes::parts($parTaux, (int) $choisi['pourcent'], $r->tvaPourDixMille);
            }
            // Une ligne par taux de TVA de l'offre (D11) ; une seule quand l'offre n'en a qu'un.
            $designation = PlanAcomptes::designation((int) $choisi['rang'], $choisi['nombre'], $choisi['pourcent'], $offre['numero']);
            foreach ($parts as $p) {
                $lignes[] = ['designation' => $designation . (count($parts) > 1 ? ', prestations à ' . Formats::taux((int) $p['taux']) : ''),
                    'quantite_milliemes' => 1000, 'unite' => 'forfait', 'prix_centimes' => $p['ht'], 'tva_pour_dix_mille' => $p['taux'],
                    'categorie' => null, 'produit_id' => null];
            }
            $ttc = Totaux::calculer($lignes, $r->tvaPourDixMille, $r->arrondiCinqCentimes)['ttc'];
            if ($ttc > $reste) {
                $erreurs[$pourcent === 'chf' ? 'montant' : 'pourcent'] = 'Plus que le reste à facturer sur cette offre (' . Formats::chf(max(0, $reste)) . ').';
            }
        } else {
            // La finale copie le taux de chaque ligne, celui du produit compris : changer le catalogue ensuite ne la change pas.
            foreach ($lignesOffre as $l) {
                $lignes[] = ['designation' => $l['designation'], 'quantite_milliemes' => $l['quantite_milliemes'], 'unite' => $l['unite'],
                    'prix_centimes' => $l['prix_centimes'], 'tva_pour_dix_mille' => $l['tva_pour_dix_mille'],
                    'categorie' => $l['categorie'], 'produit_id' => $l['produit_id']];
            }
        }
        if ($entete === null || $erreurs !== []) {
            return [null, $erreurs];
        }
        return [['choisi' => $choisi, 'entete' => $entete, 'lignes' => $lignes], []];
    }

    /**
     * L'adresse du client en champs, pour la section de paiement ; vide pour une fiche d'avant les champs séparés.
     *
     * @param Offre $offre
     */
    private function adresseClient(Request $requete, array $offre): Adresse
    {
        $client = $offre['client_id'] === null ? null : $this->c->clients($this->portee($requete))->parId($offre['client_id']);
        return $client === null ? new Adresse() : DepotClients::adresse($client);
    }

    /**
     * Le document de la facture qui serait créée : une facture et des lignes en mémoire, jamais écrites.
     *
     * @param Offre $offre
     * @param array{choisi: Genre, entete: array{numero: string, date: \DateTimeImmutable, echeance: \DateTimeImmutable}, lignes: list<array{designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, tva_pour_dix_mille: int|null, categorie: string|null, produit_id: int|null}>} $pret
     * @return array<string, mixed>
     */
    private function vueApercu(Request $requete, array $offre, array $pret): array
    {
        $maintenant = $this->c->horloge()->now()->format('Y-m-d H:i:s');
        $f = [
            'id' => 0, 'offre_id' => $offre['id'], 'offre_numero' => $offre['numero'], 'offre_cree_le' => $offre['cree_le'],
            'numero' => $pret['entete']['numero'], 'genre' => $pret['choisi']['genre'], 'acompte_rang' => $pret['choisi']['rang'],
            'acompte_nombre' => $pret['choisi']['nombre'], 'acompte_pourcent' => $pret['choisi']['pourcent'], 'statut' => DepotFactures::BROUILLON,
            'date_facture' => $pret['entete']['date']->format('Y-m-d'), 'echeance' => $pret['entete']['echeance']->format('Y-m-d'),
            'titre' => $offre['titre'], 'client_nom' => (string) $offre['client_nom'], 'client_adresse' => $offre['client_adresse'],
            'tva_pour_dix_mille' => null, 'arrondi_cinq' => null, 'iban' => null, 'reference' => null, 'ht' => null, 'tva' => null,
            'arrondi' => null, 'ttc' => null, 'deja_facture' => null, 'a_payer' => null, 'cree_le' => $maintenant, 'modifiee_le' => null,
            'emise_le' => null, 'annulee_le' => null, 'refaite_depuis' => null,
        ] + $this->adresseClient($requete, $offre)->versBase('client_');
        $lignes = [];
        foreach ($pret['lignes'] as $i => $l) {
            $lignes[] = $l + ['id' => -1 - $i, 'facture_id' => 0, 'rang' => $i + 1, 'a_verifier' => 0, 'entendu' => null, 'tva_ligne' => $l['tva_pour_dix_mille']];
        }
        /** @var Facture $f */
        $m = $this->c->reglages($this->portee($requete))->lireMarque();
        return $this->donneesDe($requete, $f, $lignes) + ['m' => $m, 'logo_src' => $m->logo === null ? null : '/reglages/logo?v=' . $m->logo, 'erreurs' => []];
    }

    /**
     * La facture. Émise ou annulée, elle se voit toujours en PDF (E5, 2026-11-15) : redirection vers l'aperçu. Brouillon
     * (une finale à corriger) : ses lignes, son aperçu, « Créer la facture ».
     *
     * @param array<string, string> $parametres
     */
    public function afficher(Request $requete, array $parametres): Response
    {
        $f = $this->facture($requete, $parametres);
        if ($f['statut'] !== DepotFactures::BROUILLON) {
            return new RedirectResponse('/factures/' . $f['id'] . '/apercu', 303);
        }
        $d = $this->donnees($requete, $f);
        return new Response($this->c->twig()->render('facture.html.twig', $d + ['raisons' => self::raisonsDeNePasEmettre($d)]));
    }

    /**
     * Le document d'un brouillon, marqué « Aperçu », dans la marque de l'artisan : seul ou à côté des lignes sur PC.
     * Une facture émise ou annulée : son PDF (E5).
     *
     * @param array<string, string> $parametres
     */
    public function document(Request $requete, array $parametres): Response
    {
        $f = $this->facture($requete, $parametres);
        if ($f['statut'] !== DepotFactures::BROUILLON) {
            return new RedirectResponse('/factures/' . $f['id'] . '/apercu', 303);
        }
        return new Response($this->c->twig()->render('facture_document.html.twig', $this->vueDocument($requete, $f)));
    }

    /**
     * « Créer la facture » depuis un brouillon (E2, E3 : sans page de confirmation) : il prend son numéro, se fige
     * (taux, arrondi, IBAN, montants), son PDF est rendu et gardé ; puis ce PDF, prêt à envoyer. Si quelque chose
     * l'empêche, le brouillon reste et dit pourquoi.
     *
     * @param array<string, string> $parametres
     */
    public function emettre(Request $requete, array $parametres): Response
    {
        $f = $this->brouillon($requete, $parametres);
        $raison = $this->emettreFacture($requete, $f);
        if ($raison !== null) {
            Flash::dire($requete, 'erreur', 'La facture n\'est pas créée : ' . $raison);
            return new RedirectResponse('/factures/' . $f['id'], 303);
        }
        $emise = $this->c->factures($this->portee($requete))->parId($f['id']) ?? throw new PageIntrouvable();
        Flash::dire($requete, 'info', 'Facture ' . $emise['numero'] . ' créée');
        return new RedirectResponse('/factures/' . $f['id'] . '/apercu', 303);
    }

    /**
     * Une facture émise ou annulée : son PDF dans l'application (D7 A, E5), avec « Envoyer » ; sur PC, un panneau à
     * côté (montant, échéance, client, actions, E8). Un brouillon n'a pas de PDF : retour à la facture.
     *
     * @param array<string, string> $parametres
     */
    public function apercu(Request $requete, array $parametres): Response
    {
        $f = $this->facture($requete, $parametres);
        $base = '/factures/' . $f['id'];
        if ($f['statut'] === DepotFactures::BROUILLON) {
            return new RedirectResponse($base, 303);
        }
        $portee = $this->portee($requete);
        $annulable = $f['statut'] === DepotFactures::EMISE
            && !($f['genre'] === 'acompte' && $this->c->factures($portee)->finaleEmise($f['offre_id']));
        return new Response($this->c->twig()->render('apercu_pdf.html.twig', [
            'pdfjs' => OffreControleur::PDFJS,
            'titre' => 'Facture ' . $f['numero'],
            'retour' => ['href' => '/factures', 'texte' => 'Factures'],
            'pdf' => $base . '/pdf',
            'fichier' => self::fichier($f),
            'partage' => 'Facture n° ' . $f['numero'] . ' · ' . $f['titre'],
            'marquer' => null,
            'modifier' => null,
            'facturer' => null,
            'facture' => [
                'f' => $f,
                'etat' => self::etat($f, $this->aujourdhui()->format('Y-m-d')),
                'annuler' => $annulable ? $base . '/annuler' : null,
            ],
        ]));
    }

    /**
     * Fige et émet un brouillon : rend null, ou la raison qui l'en empêche (rien n'est alors écrit).
     *
     * @param Facture $f
     */
    private function emettreFacture(Request $requete, array $f): ?string
    {
        $portee = $this->portee($requete);
        // Un brouillon sans numéro prend le suivant au moment où il devient une facture (E5, 2026-11-15).
        $numero = $f['numero'] ?? $this->numeroPropose($requete);
        $f['numero'] = $numero;
        $d = $this->donnees($requete, $f);
        $raisons = self::raisonsDeNePasEmettre($d);
        if ($raisons !== []) {
            return $raisons[0];
        }
        /** @var array{ht: int, tva: int, arrondi: int, ttc: int, taux: int, deja: int, a_payer: int} $montants */
        $montants = $d['montants'];
        $r = $this->c->reglages($portee)->lire();
        $fige = [
            'numero' => $numero, 'tva_pour_dix_mille' => $montants['taux'], 'arrondi_cinq' => $r->arrondiCinqCentimes ? 1 : 0,
            'iban' => $r->iban === '' ? null : $r->iban,
            'reference' => $r->iban === '' ? null : QrFacture::reference($r->iban, $f['id'], $numero),
            'ht' => $montants['ht'], 'tva' => $montants['tva'], 'arrondi' => $montants['arrondi'], 'ttc' => $montants['ttc'],
            'deja_facture' => $montants['deja'], 'a_payer' => $montants['a_payer'],
        ];
        $quand = $this->c->horloge()->now();
        // Le PDF se rend avec les valeurs figées, exactement comme on le relira.
        $emise = array_merge($f, $fige, ['statut' => DepotFactures::EMISE, 'emise_le' => $quand->format('Y-m-d H:i:s')]);
        $pdf = $this->rendrePdf($this->vueDocument($requete, $emise));
        try {
            if (!$this->c->factures($portee)->emettre($f['id'], $fige, $pdf, $quand)) {
                return 'Cette facture est déjà émise.';
            }
        } catch (\PDOException $e) {
            // Une autre facture a pris ce numéro entre-temps : l'index unique tranche, rien n'est écrit.
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            return 'Le numéro ' . $numero . ' vient d\'être pris par une autre facture : réessayez.';
        }
        return null;
    }

    /**
     * Le PDF gardé à l'émission, tel quel. Un brouillon n'en a pas : 409.
     *
     * @param array<string, string> $parametres
     */
    public function pdf(Request $requete, array $parametres): Response
    {
        $f = $this->facture($requete, $parametres);
        $pdf = $this->c->factures($this->portee($requete))->pdf($f['id']);
        if ($pdf === null) {
            throw new Conflit("Émettez la facture pour obtenir son PDF : un brouillon n'en a pas.");
        }
        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . self::fichier($f) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @param array<string, string> $parametres */
    public function confirmationAnnulation(Request $requete, array $parametres): Response
    {
        $f = $this->emise($requete, $parametres);
        return new Response($this->c->twig()->render('facture_annuler.html.twig', $this->donnees($requete, $f)));
    }

    /**
     * Annule une facture émise ; « refaire » crée aussitôt un brouillon copié, au numéro suivant.
     *
     * @param array<string, string> $parametres
     */
    public function annuler(Request $requete, array $parametres): Response
    {
        $portee = $this->portee($requete);
        $depot = $this->c->factures($portee);
        $f = $this->emise($requete, $parametres);
        $quand = $this->c->horloge()->now();
        if (!$depot->annuler($f['id'], $quand)) {
            throw new Conflit('Cette facture est déjà annulée.');
        }
        if ($requete->request->getString('refaire') !== '1') {
            Flash::dire($requete, 'info', 'Facture ' . $f['numero'] . ' annulée');
            return new RedirectResponse('/factures/' . $f['id'] . '/apercu', 303);
        }
        $date = $this->aujourdhui();
        $delai = (int) (new \DateTimeImmutable($f['date_facture']))->diff(new \DateTimeImmutable($f['echeance']))->days;
        $lignes = array_map(static fn (array $l): array => [
            'designation' => $l['designation'], 'quantite_milliemes' => $l['quantite_milliemes'], 'unite' => $l['unite'],
            'prix_centimes' => $l['prix_centimes'], 'tva_pour_dix_mille' => $l['tva_pour_dix_mille'],
            'categorie' => $l['categorie'], 'produit_id' => $l['produit_id'],
        ], $depot->lignes($f['id']));
        $id = $depot->creer([
            'offre_id' => $f['offre_id'], 'numero' => null, 'genre' => $f['genre'],
            'acompte_rang' => $f['acompte_rang'], 'acompte_nombre' => $f['acompte_nombre'], 'acompte_pourcent' => $f['acompte_pourcent'],
            'date_facture' => $date, 'echeance' => $date->modify("+$delai days"),
            'titre' => $f['titre'], 'client_nom' => $f['client_nom'], 'client_adresse' => $f['client_adresse'],
            'client_postale' => Adresse::depuisBase($f, 'client_'), 'refaite_depuis' => $f['id'],
        ], $lignes, $quand);
        Flash::dire($requete, 'info', 'Facture ' . $f['numero'] . ' annulée ; corrigez la nouvelle, puis « Créer la facture »');
        return new RedirectResponse('/factures/' . $id, 303);
    }

    /**
     * Le PDF en deux rendus (décision de Xavier du 2026-11-12) : le premier garde 110 mm en bas de page (la section et
     * la consigne « À détacher ») ; s'il tient sur une page, la section va au bas de la page 1, sinon sur une page à part.
     *
     * @param array<string, mixed> $vue
     */
    private function rendrePdf(array $vue): string
    {
        if ($vue['qr'] === null) {
            return OffreControleur::rendrePdf($this->c, $vue);
        }
        $pdf = $this->c->documentPdf();
        $place = $pdf->pages(OffreControleur::htmlPdf($this->c, ['qr_place' => 'essai'] + $vue)) === 1 ? 'bas' : 'apart';
        return $pdf->rendre(OffreControleur::htmlPdf($this->c, ['qr_place' => $place] + $vue));
    }

    /**
     * La section de paiement, ou la raison de son absence. Brouillon : IBAN des réglages, référence calculée comme elle
     * le sera à l'émission ; facture émise ou annulée : IBAN et référence figés à l'émission.
     *
     * Un brouillon sans numéro montre celui qu'il prendra ($numero).
     *
     * @param Facture $f
     */
    private static function section(array $f, string $numero, Reglages $r, int $aPayer): SectionPaiement|string
    {
        $brouillon = $f['statut'] === DepotFactures::BROUILLON;
        $iban = $brouillon ? $r->iban : (string) $f['iban'];
        // L'aperçu d'une facture pas encore créée (id 0) n'a pas d'identifiant, dont la référence QR a besoin : il en
        // prend un provisoire. Sa référence n'est qu'indicative, sous le filigrane « Aperçu » ; la vraie se fige à la création.
        $reference = $brouillon ? ($iban === '' ? null : QrFacture::reference($iban, max(1, $f['id']), $numero)) : $f['reference'];
        return QrFacture::section($r->entreprise, $r->adresse, $iban, $reference, $numero, $aPayer, $f['client_nom'], Adresse::depuisBase($f, 'client_'));
    }

    /**
     * Ce qui empêche d'émettre : aucune ligne, une ligne sans prix, un montant à payer négatif.
     *
     * @param array<string, mixed> $d
     * @return list<string>
     */
    private static function raisonsDeNePasEmettre(array $d): array
    {
        /** @var array{a_payer: int} $montants */
        $montants = $d['montants'];
        /** @var int $manque */
        $manque = $d['manque'];
        $raisons = [];
        if ($d['groupes'] === []) {
            $raisons[] = "La facture n'a aucune ligne.";
        }
        if ($manque > 0) {
            $raisons[] = $manque . ($manque > 1 ? ' lignes sans prix' : ' ligne sans prix') . ' : complétez-les avant d\'émettre.';
        }
        if ($montants['a_payer'] < 0) {
            $raisons[] = 'Le montant à payer est négatif : les acomptes émis dépassent le total.';
        }
        return $raisons;
    }

    /**
     * Les données du document : celles de l'écran, plus la marque et le logo (par son adresse : la CSP refuse les
     * images en data: à l'écran ; le PDF le remplace).
     *
     * @param Facture $f
     * @return array<string, mixed>
     */
    private function vueDocument(Request $requete, array $f): array
    {
        $m = $this->c->reglages($this->portee($requete))->lireMarque();
        return $this->donnees($requete, $f) + ['m' => $m, 'logo_src' => $m->logo === null ? null : '/reglages/logo?v=' . $m->logo];
    }

    /** @param array<string, string> $parametres */
    public function entete(Request $requete, array $parametres): Response
    {
        $f = $this->brouillon($requete, $parametres);
        $champs = ['numero' => $f['numero'] ?? '', 'date' => $f['date_facture'], 'echeance' => $f['echeance']];
        return $this->formulaireEntete($f, $champs, [], 200);
    }

    /** @param array<string, string> $parametres */
    public function modifierEntete(Request $requete, array $parametres): Response
    {
        $f = $this->brouillon($requete, $parametres);
        $r = $requete->request;
        $champs = ['numero' => $r->getString('numero'), 'date' => $r->getString('date'), 'echeance' => $r->getString('echeance')];
        // Numéro laissé vide : le brouillon prendra le suivant à la création (E5). Le proposé ne sert qu'à valider le reste.
        $sansNumero = trim($champs['numero']) === '';
        [$entete, $erreurs] = $this->lireEntete($requete, $sansNumero ? ['numero' => $this->numeroPropose($requete)] + $champs : $champs, $f['id']);
        if ($entete === null || $erreurs !== []) {
            return $this->formulaireEntete($f, $champs, $erreurs, 422);
        }
        try {
            $this->c->factures($this->portee($requete))->modifierEntete($f['id'], $sansNumero ? null : $entete['numero'], $entete['date'], $entete['echeance'], $this->c->horloge()->now());
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            return $this->formulaireEntete($f, $champs, ['numero' => 'Ce numéro est déjà pris par une autre facture.'], 422);
        }
        Flash::dire($requete, 'info', 'Facture enregistrée');
        return new RedirectResponse('/factures/' . $f['id'], 303);
    }

    /**
     * Efface le brouillon : son numéro se libère. Une facture émise ne s'efface jamais.
     *
     * @param array<string, string> $parametres
     */
    public function supprimer(Request $requete, array $parametres): Response
    {
        $f = $this->brouillon($requete, $parametres);
        $this->c->factures($this->portee($requete))->supprimerBrouillon($f['id']);
        Flash::dire($requete, 'info', 'Brouillon de facture supprimé');
        return new RedirectResponse('/factures', 303);
    }

    /**
     * Tout ce que montrent l'écran de la facture et son document.
     *
     * @param Facture $f
     * @return array<string, mixed>
     */
    public function donnees(Request $requete, array $f): array
    {
        return $this->donneesDe($requete, $f, $this->c->factures($this->portee($requete))->lignes($f['id']));
    }

    /**
     * Les mêmes données pour des lignes données : celles de la base, ou celles d'une facture pas encore créée (aperçu).
     *
     * @param Facture $f
     * @param list<array{designation: string, quantite_milliemes: int, unite: string, prix_centimes: int, tva_pour_dix_mille: int|null, categorie: string|null, produit_id: int|null, id: int, rang: int, a_verifier: int, entendu: null, tva_ligne: int|null}> $lignes
     * @return array<string, mixed>
     */
    private function donneesDe(Request $requete, array $f, array $lignes): array
    {
        $portee = $this->portee($requete);
        $depot = $this->c->factures($portee);
        $r = $this->c->reglages($portee)->lire();
        $montants = Montants::de($f, $lignes, $r, $depot->dejaFacture($f['offre_id']));
        $groupes = OffreControleur::grouper($lignes, $montants['lignes']);
        if ($f['genre'] === 'acompte') {
            foreach ($groupes as $i => $g) {
                $groupes[$i]['nom'] = $g['nom'] === OffreControleur::HORS_CATALOGUE ? 'Acompte' : $g['nom'];
            }
        }
        $numero = $f['numero'] ?? $this->numeroPropose($requete);
        $qr = self::section($f, $numero, $r, $montants['a_payer']);
        return [
            'f' => $f,
            // Le numéro qu'un brouillon sans numéro prendra à la création, s'il était créé maintenant.
            'numero_prevu' => $numero,
            'groupes' => $groupes,
            'montants' => $montants,
            'r' => $r,
            'qr' => $qr instanceof SectionPaiement ? $qr : null,
            'qr_raison' => is_string($qr) ? $qr : null,
            'qr_lien' => $qr !== QrFacture::RIEN_A_PAYER,
            'qr_place' => null,
            'pdf' => false,
            'modifiable' => $f['statut'] === DepotFactures::BROUILLON,
            'etat' => self::etat($f, $this->aujourdhui()->format('Y-m-d')),
            'manque' => count(array_filter($lignes, static fn (array $l): bool => $l['prix_centimes'] === 0)),
        ];
    }

    /**
     * L'état montré dans la liste et sur la facture.
     *
     * @param Facture $f
     * @return array{texte: string, classe: string}
     */
    public static function etat(array $f, string $aujourdhui): array
    {
        return match (true) {
            $f['statut'] === DepotFactures::BROUILLON => ['texte' => 'Brouillon', 'classe' => 'pret'],
            $f['statut'] === DepotFactures::ANNULEE => ['texte' => 'Annulée', 'classe' => 'annulee'],
            $f['echeance'] < $aujourdhui => ['texte' => 'Échue le ' . self::jour($f['echeance']), 'classe' => 'echec'],
            default => ['texte' => 'Émise, payable jusqu\'au ' . self::jour($f['echeance']), 'classe' => 'relue'],
        };
    }

    private static function jour(string $date): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $d === false ? $date : $d->format('d.m.Y');
    }

    /** Dernière ligne de l'adresse sans le numéro postal. */
    private static function localite(?string $adresse): string
    {
        $lignes = $adresse === null || $adresse === '' ? [] : explode("\n", $adresse);
        return trim((string) preg_replace('/^(CH-)?\d{4}\s+/', '', trim((string) end($lignes))));
    }

    /**
     * Le nom du PDF d'une facture émise (« Facture-2026-001.pdf »).
     *
     * @param Facture $f
     */
    private static function fichier(array $f): string
    {
        return 'Facture-' . preg_replace('/[^A-Za-z0-9._-]/', '-', $f['numero'] ?? 'brouillon') . '.pdf';
    }

    private function portee(Request $requete): Portee
    {
        return new Portee($requete->attributes->getInt('_artisan'));
    }

    private function aujourdhui(): \DateTimeImmutable
    {
        return $this->c->horloge()->now()->setTimezone(new \DateTimeZone(self::FUSEAU))->setTime(0, 0);
    }

    /**
     * @param array<string, string> $parametres
     * @return Offre
     */
    private function offre(Request $requete, array $parametres): array
    {
        return $this->c->offres($this->portee($requete))->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
    }

    /**
     * @param array<string, string> $parametres
     * @return Facture
     */
    private function facture(Request $requete, array $parametres): array
    {
        return $this->c->factures($this->portee($requete))->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
    }

    /**
     * @param array<string, string> $parametres
     * @return Facture
     */
    private function brouillon(Request $requete, array $parametres): array
    {
        $f = $this->facture($requete, $parametres);
        if ($f['statut'] !== DepotFactures::BROUILLON) {
            throw new Conflit('Cette facture est émise : elle ne se modifie plus.');
        }
        return $f;
    }

    /**
     * Une facture émise, qui s'annule. Un acompte déjà déduit par une finale émise attend l'annulation de celle-ci.
     *
     * @param array<string, string> $parametres
     * @return Facture
     */
    private function emise(Request $requete, array $parametres): array
    {
        $f = $this->facture($requete, $parametres);
        if ($f['statut'] !== DepotFactures::EMISE) {
            throw new Conflit($f['statut'] === DepotFactures::BROUILLON ? "Un brouillon ne s'annule pas : il se corrige ou se supprime." : 'Cette facture est déjà annulée.');
        }
        if ($f['genre'] === 'acompte' && $this->c->factures($this->portee($requete))->finaleEmise($f['offre_id'])) {
            throw new Conflit("La facture finale de l'offre déduit cet acompte : annulez-la d'abord.");
        }
        return $f;
    }

    /**
     * @param Offre $offre
     * @return list<Genre>
     */
    private function possibles(Request $requete, array $offre): array
    {
        $portee = $this->portee($requete);
        $r = $this->c->reglages($portee)->lire();
        $depot = $this->c->factures($portee);
        $tranches = $r->paiementType === 'acomptes' ? $r->paiementAcomptes : [];
        return PlanAcomptes::possibles($tranches, $depot->acomptesFaits($offre['id']), $depot->finaleFaite($offre['id']));
    }

    private function numeroPropose(Request $requete): string
    {
        $depot = $this->c->factures($this->portee($requete));
        return Numerotation::suivant($depot->dernierNumero(), (int) $this->aujourdhui()->format('Y'), static fn (string $n): bool => $depot->numeroPris($n));
    }

    /**
     * Numéro, date et échéance tapés ; erreurs par champ.
     *
     * @param array<string, string> $champs
     * @return array{array{numero: string, date: \DateTimeImmutable, echeance: \DateTimeImmutable}|null, array<string, string>}
     */
    private function lireEntete(Request $requete, array $champs, ?int $sauf): array
    {
        $erreurs = [];
        $numero = Numerotation::lire($champs['numero']);
        if ($numero === null) {
            $erreurs['numero'] = 'Lettres, chiffres et - / . _ seulement, 20 caractères au plus, par exemple 2026-001.';
        } elseif ($this->c->factures($this->portee($requete))->numeroPris($numero, $sauf)) {
            $erreurs['numero'] = 'Ce numéro est déjà pris par une autre facture.';
        }
        $date = self::date($champs['date']);
        if ($date === null) {
            $erreurs['date'] = 'Une date, par exemple 13.11.2026.';
        }
        $echeance = self::date($champs['echeance']);
        if ($echeance === null) {
            $erreurs['echeance'] = 'Une date, par exemple 13.12.2026.';
        } elseif ($date !== null && $echeance < $date) {
            $erreurs['echeance'] = "L'échéance suit la date de la facture.";
        }
        if ($numero === null || $date === null || $echeance === null || $erreurs !== []) {
            return [null, $erreurs];
        }
        return [['numero' => $numero, 'date' => $date, 'echeance' => $echeance], []];
    }

    /** « 2026-11-13 » (champ date du navigateur) ou « 13.11.2026 » (navigateur sans champ date). */
    private static function date(string $saisie): ?\DateTimeImmutable
    {
        $t = trim($saisie);
        foreach (['!Y-m-d', '!d.m.Y'] as $forme) {
            $d = \DateTimeImmutable::createFromFormat($forme, $t);
            if ($d !== false && $d->format(substr($forme, 1)) === $t && (int) $d->format('Y') >= 2000 && (int) $d->format('Y') <= 2100) {
                return $d;
            }
        }
        return null;
    }

    /**
     * @param Offre $offre
     * @param list<Genre> $possibles
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     */
    private function formulaireNouvelle(Request $requete, array $offre, array $possibles, array $champs, array $erreurs, int $code): Response
    {
        $portee = $this->portee($requete);
        $choix = [];
        foreach ($possibles as $p) {
            $choix[] = $p + ['titre' => $p['genre'] === 'acompte' ? 'Acompte ' . $p['rang'] . ($p['nombre'] === null ? '' : ' sur ' . $p['nombre']) : 'Facture finale'];
        }
        $plan = array_values(array_filter($possibles, static fn (array $p): bool => $p['genre'] === 'acompte'))[0]['pourcent'] ?? null;
        // L'aperçu des choix tels qu'ils sont, s'ils se lisent ; sinon la feuille montre les erreurs sans aperçu.
        $apercu = null;
        if ($offre['client_id'] !== null && $possibles !== []) {
            [$pret] = $this->lireFacturer($requete, $offre, $possibles, $champs);
            $apercu = $pret === null ? null : $this->vueApercu($requete, $offre, $pret);
        }
        return new Response($this->c->twig()->render('facturer.html.twig', [
            'offre' => $offre,
            'choix' => $choix,
            'pourcentages' => PlanAcomptes::pourcentages($plan),
            'factures' => $this->c->factures($portee)->deOffre($offre['id']),
            'deja' => $this->c->factures($portee)->dejaFacture($offre['id']),
            'champs' => $champs + ['montant' => ''],
            'erreurs' => $erreurs,
            'apercu' => $apercu,
            'm' => $this->c->reglages($portee)->lireMarque(),
        ]), $code);
    }

    /**
     * @param Facture $f
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     */
    private function formulaireEntete(array $f, array $champs, array $erreurs, int $code): Response
    {
        return new Response($this->c->twig()->render('facture_entete.html.twig', ['f' => $f, 'champs' => $champs, 'erreurs' => $erreurs]), $code);
    }
}
