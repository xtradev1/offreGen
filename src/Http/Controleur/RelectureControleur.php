<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Formats;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\Offerte;
use OffreGen\Offre\Saisie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relecture au pouce, une ligne à la fois (décision D5 de Xavier, maquette 24, 2026-11-15). À relire : une ligne
 * « à vérifier » ou sans prix, dans l'ordre de l'écran de l'offre. « C'est juste » enregistre la quantité et le prix
 * montrés et retire la marque ; la suivante vient d'elle-même. Aucun appel au modèle.
 *
 * @phpstan-import-type Ligne from DepotOffres
 */
final class RelectureControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $parametres */
    public function afficher(Request $requete, array $parametres): Response
    {
        $offre = $this->offres($requete)->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        $restantes = $this->aRelire($requete, $offre['id']);
        $l = $restantes[0] ?? null;
        return $this->rendre($offre, $l, count($restantes), $l === null ? [] : self::champs($l), [], 200);
    }

    /** @param array<string, string> $parametres */
    public function valider(Request $requete, array $parametres): Response
    {
        $depot = $this->offres($requete);
        $offre = $depot->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        $l = $depot->ligne($offre['id'], Parametres::id($parametres, 'ligne')) ?? throw new PageIntrouvable();
        $saisie = ['quantite' => $requete->request->getString('quantite'), 'prix' => $requete->request->getString('prix')];
        $erreurs = [];
        $quantite = Saisie::milliemes($saisie['quantite']);
        if ($quantite === null) {
            $erreurs['quantite'] = 'Quantité plus grande que 0, par exemple 12 ou 12.5.';
        }
        $prix = trim($saisie['prix']) === '' ? null : Saisie::centimes($saisie['prix']);
        if ($prix === null) {
            $erreurs['prix'] = "Indiquez le prix en francs, par exemple 28 ou 28.50\u{00A0}; 0 pour l'offrir.";
        }
        if ($quantite === null || $prix === null || $erreurs !== []) {
            return $this->rendre($offre, $l, count($this->aRelire($requete, $offre['id'])), $saisie, $erreurs, 422);
        }
        $depot->corrigerLigne($offre['id'], $l['id'], $l['designation'], $quantite, $l['unite'], $prix);
        $offerte = Offerte::apresSaisie(Offerte::est($l), $l['prix_centimes'], $saisie['prix'], $prix, null);
        if ($offerte !== Offerte::est($l)) {
            $depot->changerOfferte($offre['id'], $l['id'], $offerte);
        }
        $depot->confirmerLigne($offre['id'], $l['id']);
        $depot->marquerModifiee($offre['id'], $this->c->horloge()->now());
        return new RedirectResponse('/offres/' . $offre['id'] . '/relire', 303);
    }

    private function offres(Request $requete): DepotOffres
    {
        return $this->c->offres(new Portee($requete->attributes->getInt('_artisan')));
    }

    /** @return list<Ligne> les lignes à relire, dans l'ordre de l'écran de l'offre (par catégorie) */
    private function aRelire(Request $requete, int $offreId): array
    {
        $lignes = $this->offres($requete)->lignes($offreId);
        $ordre = [];
        foreach (OffreControleur::grouper($lignes, []) as $g) {
            foreach ($g['lignes'] as $r) {
                if ($r['l']['a_verifier'] === 1 || Offerte::prixManquant($r['l'])) {
                    $ordre[] = $r['l'];
                }
            }
        }
        return $ordre;
    }

    /**
     * @param Ligne $l
     * @return array{quantite: string, prix: string}
     */
    private static function champs(array $l): array
    {
        return ['quantite' => Formats::saisieQuantite($l['quantite_milliemes']), 'prix' => Formats::saisiePrixLigne($l)];
    }

    /**
     * @param array<string, mixed> $offre
     * @param Ligne|null $l
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     */
    private function rendre(array $offre, ?array $l, int $restantes, array $champs, array $erreurs, int $code): Response
    {
        return new Response($this->c->twig()->render('relecture.html.twig', [
            'offre' => $offre,
            'l' => $l,
            'restantes' => $restantes,
            'champs' => $champs,
            'erreurs' => $erreurs,
        ]), $code);
    }
}
