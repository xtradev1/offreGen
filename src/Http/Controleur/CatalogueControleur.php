<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\Recherche;
use OffreGen\Catalogue\SaisieProduit;
use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\Formats;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\Saisie;
use OffreGen\Offre\SaisieRefusee;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onglet Catalogue : consulter, chercher, ajouter, modifier et supprimer les produits de l'artisan.
 *
 * @phpstan-import-type Produit from DepotProduits
 */
final class CatalogueControleur
{
    public function __construct(private readonly Conteneur $c)
    {
    }

    /**
     * Tous les produits sont rendus ; ceux qu'écarte la recherche portent « hidden ». Ainsi app.js
     * peut filtrer à la frappe sans recharger, et la page marche aussi sans JavaScript.
     */
    public function liste(Request $requete): Response
    {
        $produits = $this->depot($requete)->tous();
        $q = mb_substr($requete->query->getString('q'), 0, 100);
        $cat = mb_substr($requete->query->getString('cat'), 0, 100);
        $gardes = array_column(Recherche::filtrer($produits, $q, $cat), 'id', 'id');
        $groupes = [];
        foreach ($produits as $p) {
            $groupes[$p['categorie']][] = $p + ['texte' => Recherche::texte($p), 'visible' => isset($gardes[$p['id']])];
        }
        return new Response($this->c->twig()->render('catalogue.html.twig', [
            'groupes' => $groupes,
            'nombre' => count($produits),
            'trouves' => count($gardes),
            'q' => $q,
            'cat' => $cat,
        ]));
    }

    public function nouveau(Request $requete): Response
    {
        $champs = self::vide();
        $champs['designation'] = mb_substr($requete->query->getString('q'), 0, 255);
        $champs['categorie'] = mb_substr($requete->query->getString('cat'), 0, 100);
        return $this->formulaire($requete, null, $champs, [], 200);
    }

    public function creer(Request $requete): Response
    {
        $champs = self::champs($requete);
        try {
            $produit = SaisieProduit::lire($champs);
        } catch (SaisieRefusee $e) {
            return $this->formulaire($requete, null, $champs, $e->erreurs, 422);
        }
        $id = $this->depot($requete)->creer($produit);
        Flash::dire($requete, 'info', Flash::cite($produit['designation']) . ' ajouté au catalogue');
        return new RedirectResponse('/catalogue#p' . $id, 303);
    }

    /** @param array<string, string> $parametres */
    public function fiche(Request $requete, array $parametres): Response
    {
        $p = $this->produit($requete, $parametres);
        $champs = ['designation' => $p['designation'], 'unite' => $p['unite'], 'prix' => Formats::saisiePrix($p['prix_centimes']),
                   'categorie' => $p['categorie'], 'nouvelle_categorie' => '', 'synonymes' => $p['synonymes'] ?? ''];
        return $this->formulaire($requete, $p, $champs, [], 200);
    }

    /** @param array<string, string> $parametres */
    public function modifier(Request $requete, array $parametres): Response
    {
        $p = $this->produit($requete, $parametres);
        $champs = self::champs($requete);
        try {
            $produit = SaisieProduit::lire($champs, $p['unite']);
        } catch (SaisieRefusee $e) {
            return $this->formulaire($requete, $p, $champs, $e->erreurs, 422);
        }
        $this->depot($requete)->modifier($p['id'], $produit);
        Flash::dire($requete, 'info', Flash::cite($produit['designation']) . ' enregistré');
        return new RedirectResponse('/catalogue#p' . $p['id'], 303);
    }

    /** @param array<string, string> $parametres */
    public function confirmerSuppression(Request $requete, array $parametres): Response
    {
        return new Response($this->c->twig()->render('produit_supprimer.html.twig', ['p' => $this->produit($requete, $parametres)]));
    }

    /** @param array<string, string> $parametres */
    public function supprimer(Request $requete, array $parametres): Response
    {
        $p = $this->produit($requete, $parametres);
        $this->depot($requete)->supprimer($p['id']);
        Flash::dire($requete, 'info', Flash::cite($p['designation']) . ' supprimé du catalogue');
        return new RedirectResponse('/catalogue', 303);
    }

    private function depot(Request $requete): DepotProduits
    {
        return $this->c->produits(new Portee($requete->attributes->getInt('_artisan')));
    }

    /**
     * @param array<string, string> $parametres
     * @return Produit
     */
    private function produit(Request $requete, array $parametres): array
    {
        return $this->depot($requete)->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
    }

    /** @return array{designation: string, unite: string, prix: string, categorie: string, nouvelle_categorie: string, synonymes: string} */
    private static function champs(Request $requete): array
    {
        $r = $requete->request;
        return ['designation' => $r->getString('designation'), 'unite' => $r->getString('unite'), 'prix' => $r->getString('prix'),
                'categorie' => $r->getString('categorie'), 'nouvelle_categorie' => $r->getString('nouvelle_categorie'),
                'synonymes' => $r->getString('synonymes')];
    }

    /** @return array{designation: string, unite: string, prix: string, categorie: string, nouvelle_categorie: string, synonymes: string} */
    private static function vide(): array
    {
        return ['designation' => '', 'unite' => 'pce', 'prix' => '', 'categorie' => '', 'nouvelle_categorie' => '', 'synonymes' => ''];
    }

    /**
     * @param Produit|null $p le produit modifié, null pour un nouveau
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     */
    private function formulaire(Request $requete, ?array $p, array $champs, array $erreurs, int $code): Response
    {
        $categories = $this->depot($requete)->categories();
        $unites = Saisie::UNITES;
        if ($p !== null && !in_array($p['unite'], $unites, true)) {
            $unites[] = $p['unite'];
        }
        return new Response($this->c->twig()->render('produit.html.twig', [
            'p' => $p,
            'champs' => $champs,
            'erreurs' => $erreurs,
            'categories' => $categories,
            'unites' => $unites,
        ]), $code);
    }
}
