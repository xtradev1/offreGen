<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\Piece;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renommer une pièce d'une offre (sous-chantier 28, décision P5 de Xavier, 2026-11-17), depuis son titre : toutes ses
 * lignes suivent. Donner le nom d'une autre pièce de l'offre les réunit. « Général » ne se renomme pas : ce n'est pas
 * une pièce.
 *
 * @phpstan-import-type Offre from DepotOffres
 */
final class PieceControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $parametres */
    public function page(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $nom = $this->pieceExistante($requete, $offre['id'], $requete->query->getString('nom'));
        return $this->formulaire($offre, $nom, $nom, null, 200);
    }

    /** @param array<string, string> $parametres */
    public function renommer(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $ancienne = $this->pieceExistante($requete, $offre['id'], $requete->request->getString('ancienne'));
        $saisie = $requete->request->getString('nouvelle');
        $nouvelle = Piece::lire($saisie);
        if ($nouvelle === null) {
            return $this->formulaire($offre, $ancienne, $saisie, 'Indiquez le nom de la pièce (' . Piece::LONGUEUR_MAX . ' caractères au plus).', 422);
        }
        $offres = $this->offres($requete);
        $offres->renommerPiece($offre['id'], $ancienne, $nouvelle);
        $offres->marquerModifiee($offre['id'], $this->c->horloge()->now());
        Flash::dire($requete, 'info', 'Pièce renommée');
        return new RedirectResponse('/offres/' . $offre['id'], 303);
    }

    /** Le nom tel que l'offre l'écrit ; 404 si l'offre n'a pas cette pièce. */
    private function pieceExistante(Request $requete, int $offreId, string $nom): string
    {
        foreach ($this->offres($requete)->lignes($offreId) as $l) {
            if ($l['piece'] !== null && Piece::cle($l['piece']) === Piece::cle(trim($nom))) {
                return $l['piece'];
            }
        }
        throw new PageIntrouvable();
    }

    /**
     * @param array<string, string> $parametres
     * @return Offre
     */
    private function offre(Request $requete, array $parametres): array
    {
        return $this->offres($requete)->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
    }

    private function offres(Request $requete): DepotOffres
    {
        return $this->c->offres(new Portee($requete->attributes->getInt('_artisan')));
    }

    /** @param Offre $offre */
    private function formulaire(array $offre, string $ancienne, string $nouvelle, ?string $erreur, int $code): Response
    {
        return new Response($this->c->twig()->render('piece.html.twig', [
            'offre' => $offre, 'ancienne' => $ancienne, 'nouvelle' => $nouvelle, 'erreur' => $erreur, 'longueur' => Piece::LONGUEUR_MAX,
        ]), $code);
    }
}
