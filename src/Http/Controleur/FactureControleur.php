<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Adresse\Adresse;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotFactures;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Facture\Montants;
use OffreGen\Facture\Numerotation;
use OffreGen\Facture\PlanAcomptes;
use OffreGen\Http\Conflit;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
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

    /** @param array<string, string> $parametres */
    public function nouvelle(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $possibles = $this->possibles($requete, $offre);
        $date = $this->aujourdhui();
        $genre = $possibles[0]['genre'] ?? 'finale';
        $champs = [
            'genre' => $genre,
            'numero' => $this->numeroPropose($requete),
            'date' => $date->format('Y-m-d'),
            'echeance' => PlanAcomptes::echeance($this->c->reglages($this->portee($requete))->lire(), $genre, $date)->format('Y-m-d'),
        ];
        return $this->formulaireNouvelle($requete, $offre, $possibles, $champs, [], 200);
    }

    /** @param array<string, string> $parametres */
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
        $champs = ['genre' => $r->getString('genre'), 'numero' => $r->getString('numero'), 'date' => $r->getString('date'), 'echeance' => $r->getString('echeance')];
        [$entete, $erreurs] = $this->lireEntete($requete, $champs, null);
        $choisi = null;
        foreach ($possibles as $p) {
            if ($p['genre'] === $champs['genre']) {
                $choisi = $p;
            }
        }
        if ($choisi === null) {
            $erreurs['genre'] = 'Choisissez la facture à faire.';
        }
        if ($entete === null || $choisi === null || $erreurs !== []) {
            return $this->formulaireNouvelle($requete, $offre, $possibles, $champs, $erreurs, 422);
        }

        $lignesOffre = $this->c->offres($portee)->lignes($offre['id']);
        if ($choisi['genre'] === 'acompte') {
            $reglages = $this->c->reglages($portee)->lire();
            $ht = Totaux::calculer($lignesOffre, $reglages->tvaPourDixMille, $reglages->arrondiCinqCentimes)['ht'];
            $lignes = [[
                'designation' => PlanAcomptes::designation((int) $choisi['rang'], (int) $choisi['nombre'], (int) $choisi['pourcent'], $offre['numero']),
                'quantite_milliemes' => 1000, 'unite' => 'forfait', 'prix_centimes' => PlanAcomptes::montant($ht, (int) $choisi['pourcent']),
                'categorie' => null, 'produit_id' => null,
            ]];
        } else {
            $lignes = array_map(static fn (array $l): array => [
                'designation' => $l['designation'], 'quantite_milliemes' => $l['quantite_milliemes'], 'unite' => $l['unite'],
                'prix_centimes' => $l['prix_centimes'], 'categorie' => $l['categorie'], 'produit_id' => $l['produit_id'],
            ], $lignesOffre);
        }
        // L'adresse du client en champs, pour la section de paiement ; vide pour une fiche d'avant les champs séparés.
        $client = $this->c->clients($portee)->parId($offre['client_id']);
        try {
            $id = $this->c->factures($portee)->creer([
                'offre_id' => $offre['id'], 'numero' => $entete['numero'], 'genre' => $choisi['genre'],
                'acompte_rang' => $choisi['rang'], 'acompte_nombre' => $choisi['nombre'], 'acompte_pourcent' => $choisi['pourcent'],
                'date_facture' => $entete['date'], 'echeance' => $entete['echeance'],
                'titre' => $offre['titre'], 'client_nom' => (string) $offre['client_nom'], 'client_adresse' => $offre['client_adresse'],
                'client_postale' => $client === null ? new Adresse() : DepotClients::adresse($client),
            ], $lignes, $this->c->horloge()->now());
        } catch (\PDOException $e) {
            // Deux factures créées au même instant avec le même numéro : l'index unique tranche.
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            return $this->formulaireNouvelle($requete, $offre, $possibles, $champs, ['numero' => 'Ce numéro est déjà pris par une autre facture.'], 422);
        }
        Flash::dire($requete, 'info', 'Facture ' . $entete['numero'] . ' créée');
        return new RedirectResponse('/factures/' . $id, 303);
    }

    /** @param array<string, string> $parametres */
    public function afficher(Request $requete, array $parametres): Response
    {
        return new Response($this->c->twig()->render('facture.html.twig', $this->donnees($requete, $this->facture($requete, $parametres))));
    }

    /**
     * L'aperçu de la facture, dans la marque de l'artisan (même modèle et même couleur que l'offre).
     *
     * @param array<string, string> $parametres
     */
    public function document(Request $requete, array $parametres): Response
    {
        return new Response($this->c->twig()->render('facture_document.html.twig', $this->vueDocument($requete, $this->facture($requete, $parametres))));
    }

    /**
     * « Émettre la facture ? » : ce qui sera figé, ou ce qui l'empêche. Page sans JavaScript, comme la suppression
     * d'un produit du catalogue.
     *
     * @param array<string, string> $parametres
     */
    public function confirmationEmission(Request $requete, array $parametres): Response
    {
        $f = $this->brouillon($requete, $parametres);
        $d = $this->donnees($requete, $f);
        return new Response($this->c->twig()->render('facture_emettre.html.twig', $d + ['raisons' => self::raisonsDeNePasEmettre($d)]));
    }

    /**
     * Fige la facture (taux, arrondi, IBAN, montants), rend son PDF et le garde : c'est ce PDF qu'on servira ensuite,
     * quels que soient les réglages d'alors.
     *
     * @param array<string, string> $parametres
     */
    public function emettre(Request $requete, array $parametres): Response
    {
        $portee = $this->portee($requete);
        $f = $this->brouillon($requete, $parametres);
        $d = $this->donnees($requete, $f);
        $raisons = self::raisonsDeNePasEmettre($d);
        if ($raisons !== []) {
            throw new Conflit($raisons[0]);
        }
        /** @var array{ht: int, tva: int, arrondi: int, ttc: int, taux: int, deja: int, a_payer: int} $montants */
        $montants = $d['montants'];
        $r = $this->c->reglages($portee)->lire();
        $fige = [
            'tva_pour_dix_mille' => $montants['taux'], 'arrondi_cinq' => $r->arrondiCinqCentimes ? 1 : 0,
            'iban' => $r->iban === '' ? null : $r->iban, 'reference' => null,
            'ht' => $montants['ht'], 'tva' => $montants['tva'], 'arrondi' => $montants['arrondi'], 'ttc' => $montants['ttc'],
            'deja_facture' => $montants['deja'], 'a_payer' => $montants['a_payer'],
        ];
        $quand = $this->c->horloge()->now();
        // Le PDF se rend avec les valeurs figées, exactement comme on le relira.
        $emise = array_merge($f, $fige, ['statut' => DepotFactures::EMISE, 'emise_le' => $quand->format('Y-m-d H:i:s')]);
        $pdf = OffreControleur::rendrePdf($this->c, $this->vueDocument($requete, $emise));
        if (!$this->c->factures($portee)->emettre($f['id'], $fige, $pdf, $quand)) {
            throw new Conflit('Cette facture est déjà émise.');
        }
        Flash::dire($requete, 'info', 'Facture ' . $f['numero'] . ' émise');
        return new RedirectResponse('/factures/' . $f['id'] . '/document', 303);
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
            'Content-Disposition' => 'inline; filename="Facture-' . preg_replace('/[^A-Za-z0-9._-]/', '-', $f['numero']) . '.pdf"',
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
            return new RedirectResponse('/factures/' . $f['id'] . '/document', 303);
        }
        $date = $this->aujourdhui();
        $delai = (int) (new \DateTimeImmutable($f['date_facture']))->diff(new \DateTimeImmutable($f['echeance']))->days;
        $lignes = array_map(static fn (array $l): array => [
            'designation' => $l['designation'], 'quantite_milliemes' => $l['quantite_milliemes'], 'unite' => $l['unite'],
            'prix_centimes' => $l['prix_centimes'], 'categorie' => $l['categorie'], 'produit_id' => $l['produit_id'],
        ], $depot->lignes($f['id']));
        $id = $depot->creer([
            'offre_id' => $f['offre_id'], 'numero' => $this->numeroPropose($requete), 'genre' => $f['genre'],
            'acompte_rang' => $f['acompte_rang'], 'acompte_nombre' => $f['acompte_nombre'], 'acompte_pourcent' => $f['acompte_pourcent'],
            'date_facture' => $date, 'echeance' => $date->modify("+$delai days"),
            'titre' => $f['titre'], 'client_nom' => $f['client_nom'], 'client_adresse' => $f['client_adresse'],
            'client_postale' => Adresse::depuisBase($f, 'client_'), 'refaite_depuis' => $f['id'],
        ], $lignes, $quand);
        Flash::dire($requete, 'info', 'Facture ' . $f['numero'] . ' annulée ; corrigez la nouvelle, puis émettez-la');
        return new RedirectResponse('/factures/' . $id, 303);
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
        $champs = ['numero' => $f['numero'], 'date' => $f['date_facture'], 'echeance' => $f['echeance']];
        return $this->formulaireEntete($f, $champs, [], 200);
    }

    /** @param array<string, string> $parametres */
    public function modifierEntete(Request $requete, array $parametres): Response
    {
        $f = $this->brouillon($requete, $parametres);
        $r = $requete->request;
        $champs = ['numero' => $r->getString('numero'), 'date' => $r->getString('date'), 'echeance' => $r->getString('echeance')];
        [$entete, $erreurs] = $this->lireEntete($requete, $champs, $f['id']);
        if ($entete === null || $erreurs !== []) {
            return $this->formulaireEntete($f, $champs, $erreurs, 422);
        }
        try {
            $this->c->factures($this->portee($requete))->modifierEntete($f['id'], $entete['numero'], $entete['date'], $entete['echeance'], $this->c->horloge()->now());
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
        Flash::dire($requete, 'info', 'Brouillon ' . $f['numero'] . ' supprimé');
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
        $portee = $this->portee($requete);
        $depot = $this->c->factures($portee);
        $r = $this->c->reglages($portee)->lire();
        $lignes = $depot->lignes($f['id']);
        $montants = Montants::de($f, $lignes, $r, $depot->dejaFacture($f['offre_id']));
        $groupes = OffreControleur::grouper($lignes, $montants['lignes']);
        if ($f['genre'] === 'acompte') {
            foreach ($groupes as $i => $g) {
                $groupes[$i]['nom'] = $g['nom'] === OffreControleur::HORS_CATALOGUE ? 'Acompte' : $g['nom'];
            }
        }
        return [
            'f' => $f,
            'groupes' => $groupes,
            'montants' => $montants,
            'r' => $r,
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
        $r = $this->c->reglages($portee)->lire();
        $ht = Totaux::calculer($this->c->offres($portee)->lignes($offre['id']), $r->tvaPourDixMille, $r->arrondiCinqCentimes)['ht'];
        $choix = [];
        foreach ($possibles as $p) {
            $choix[] = $p + ['titre' => $p['genre'] === 'acompte'
                ? sprintf("Acompte %d sur %d (%d\u{00A0}%%)", $p['rang'], $p['nombre'], $p['pourcent'])
                : 'Facture finale', 'ht' => $p['genre'] === 'acompte' ? PlanAcomptes::montant($ht, (int) $p['pourcent']) : $ht];
        }
        return new Response($this->c->twig()->render('facturer.html.twig', [
            'offre' => $offre,
            'choix' => $choix,
            'factures' => $this->c->factures($portee)->deOffre($offre['id']),
            'deja' => $this->c->factures($portee)->dejaFacture($offre['id']),
            'nb_lignes' => count($this->c->offres($portee)->lignes($offre['id'])),
            'champs' => $champs,
            'erreurs' => $erreurs,
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
