<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\Recherche;
use OffreGen\Catalogue\SaisieProduit;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conflit;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\Formats;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Une ligne d'offre et le catalogue (décision D6 de Xavier, maquette 24, 2026-11-15).
 *
 * - « Créer le produit » : une ligne hors catalogue devient un produit (désignation, unité, prix de la ligne, catégorie
 *   « Divers »), et la ligne s'y rattache. Un toucher.
 * - « Rattacher à un produit » : la ligne devient ce produit (désignation, unité, prix du catalogue, comme l'extraction
 *   le fait), sa quantité reste. Si la ligne était hors catalogue, le nom que l'IA lui avait donné devient un synonyme du
 *   produit : la prochaine dictée le reconnaîtra. Une ligne déjà au catalogue peut changer de produit (l'IA s'est
 *   trompée) sans rien ajouter aux synonymes : son nom est celui d'un autre produit.
 *
 * @phpstan-import-type Ligne from DepotOffres
 */
final class LigneCatalogueControleur
{
    public const CATEGORIE = 'Divers';

    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $parametres */
    public function creerProduit(Request $requete, array $parametres): Response
    {
        [$offre, $l] = $this->ligne($requete, $parametres);
        if ($l['produit_id'] !== null) {
            throw new Conflit('Cette ligne est déjà au catalogue.');
        }
        $produit = SaisieProduit::lire(['designation' => $l['designation'], 'unite' => $l['unite'],
            'prix' => $l['prix_centimes'] === 0 ? '' : Formats::saisiePrix($l['prix_centimes']),
            'categorie' => self::CATEGORIE, 'nouvelle_categorie' => '', 'synonymes' => ''], $l['unite']);
        $id = $this->c->produits($this->portee($requete))->creer($produit);
        $offres = $this->offres($requete);
        $offres->rattacherProduit($offre['id'], $l['id'], $id);
        $offres->marquerModifiee($offre['id'], $this->c->horloge()->now());
        Flash::dire($requete, 'info', Flash::cite($produit['designation']) . ' ajouté au catalogue');
        return new RedirectResponse('/offres/' . $offre['id'] . '#l' . $l['id'], 303);
    }

    /** @param array<string, string> $parametres */
    public function choix(Request $requete, array $parametres): Response
    {
        [$offre, $l] = $this->ligne($requete, $parametres);
        $tous = $this->c->produits($this->portee($requete))->tous();
        $q = $requete->query->has('q') ? $requete->query->getString('q') : self::premierMot($l['designation']);
        $gardes = array_column(Recherche::filtrer($tous, $q), 'id', 'id');
        $produits = [];
        foreach ($tous as $p) {
            if ($p['id'] !== $l['produit_id']) {
                $produits[] = $p + ['texte' => Recherche::texte($p), 'visible' => $gardes === [] || isset($gardes[$p['id']])];
            }
        }
        return new Response($this->c->twig()->render('rattacher.html.twig', [
            'offre' => $offre,
            'l' => $l,
            'q' => $q,
            'produits' => $produits,
            'aucun' => $gardes === [],
        ]));
    }

    /** @param array<string, string> $parametres */
    public function rattacher(Request $requete, array $parametres): Response
    {
        [$offre, $l] = $this->ligne($requete, $parametres);
        $catalogue = $this->c->produits($this->portee($requete));
        $p = $catalogue->parId(Parametres::id(['produit' => $requete->request->getString('produit')], 'produit')) ?? throw new PageIntrouvable();
        $offres = $this->offres($requete);
        $offres->corrigerLigne($offre['id'], $l['id'], $p['designation'], $l['quantite_milliemes'], $p['unite'], $p['prix_centimes']);
        $offres->rattacherProduit($offre['id'], $l['id'], $p['id']);
        if ($l['produit_id'] === null && mb_strtolower($l['designation']) !== mb_strtolower($p['designation'])) {
            $synonymes = SaisieProduit::synonymes(($p['synonymes'] ?? '') . ', ' . $l['designation']);
            if (mb_strlen($synonymes) <= SaisieProduit::SYNONYMES_MAX) {
                $catalogue->modifier($p['id'], ['categorie' => $p['categorie'], 'designation' => $p['designation'], 'unite' => $p['unite'],
                    'prix_centimes' => $p['prix_centimes'], 'synonymes' => $synonymes]);
            }
        }
        $offres->marquerModifiee($offre['id'], $this->c->horloge()->now());
        Flash::dire($requete, 'info', 'Ligne rattachée à ' . Flash::cite($p['designation']));
        return new RedirectResponse('/offres/' . $offre['id'] . '#l' . $l['id'], 303);
    }

    /** Le premier mot de trois lettres au moins : « Crépi de soubassement » cherche « Crépi ». */
    private static function premierMot(string $designation): string
    {
        foreach (preg_split('/[\s,;.\/()-]+/u', $designation) ?: [] as $mot) {
            if (mb_strlen($mot) >= 3) {
                return $mot;
            }
        }
        return '';
    }

    private function portee(Request $requete): Portee
    {
        return new Portee($requete->attributes->getInt('_artisan'));
    }

    private function offres(Request $requete): DepotOffres
    {
        return $this->c->offres($this->portee($requete));
    }

    /**
     * @param array<string, string> $parametres
     * @return array{array<string, mixed>&array{id: int}, Ligne}
     */
    private function ligne(Request $requete, array $parametres): array
    {
        $offres = $this->offres($requete);
        $offre = $offres->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        $l = $offres->ligne($offre['id'], Parametres::id($parametres, 'ligne')) ?? throw new PageIntrouvable();
        return [$offre, $l];
    }
}
