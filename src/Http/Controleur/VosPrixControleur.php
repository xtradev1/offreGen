<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Donnees\DepotProduits;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\Formats;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\Saisie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * « Vos prix » (décisions de Xavier du 2026-11-19, docs/plans/2026-11-19_metiers-demo) : les produits sans prix,
 * un à la fois, dans l'ordre du catalogue. « Suivant » enregistre le prix tapé ; « Reprendre » enregistre le prix
 * de la démonstration et avance ; « Passer » laisse à 0 ; « Je ne fais pas ça » retire le produit. « Reprendre »
 * et le retrait s'annulent depuis le message du bas. Même patron que la relecture d'une offre : aucun appel au modèle.
 *
 * Le passage en cours (prix faits, produits passés, retraits, dernier geste annulable) vit dans la session :
 * ouvrir l'écran sans « suite » en commence un autre.
 *
 * @phpstan-import-type Produit from DepotProduits
 * @phpstan-type Etat array{faits: int, passes: list<int>, retires: int, annulable: array<string, mixed>|null}
 */
final class VosPrixControleur
{
    private const CLE = 'vos_prix';
    /** « Plus tard » : le bandeau de l'accueil devient un rappel d'une ligne, jusqu'à la fin de la session. */
    public const PLUS_TARD = 'vos_prix_plus_tard';

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function afficher(Request $requete): Response
    {
        $session = $requete->getSession();
        if (!$requete->query->getBoolean('suite')) {
            $session->remove(self::CLE);
        }
        $etat = self::etat($session);
        $sansPrix = $this->depot($requete)->sansPrix();
        $restants = self::restants($sansPrix, $etat);
        if ($restants === []) {
            return new Response($this->c->twig()->render('vos_prix.html.twig', [
                'p' => null,
                'bilan' => ['faits' => $etat['faits'], 'reste' => count($sansPrix), 'retires' => $etat['retires'],
                            'rien' => $etat['faits'] + count($etat['passes']) + $etat['retires'] === 0],
            ]));
        }
        $voulu = $requete->query->getInt('p');
        $p = $restants[0];
        foreach ($restants as $r) {
            if ($r['id'] === $voulu) {
                $p = $r;
            }
        }
        return $this->carte($p, $etat, count($restants), '', false, 200);
    }

    /** @param array<string, string> $parametres */
    public function agir(Request $requete, array $parametres): Response
    {
        $depot = $this->depot($requete);
        $p = $depot->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
        $session = $requete->getSession();
        $etat = self::etat($session);
        $restants = self::restants($depot->sansPrix(), $etat);
        $suivant = self::apres($restants, $p['id']);
        if ($p['prix_centimes'] !== 0) {
            // Déjà chiffré (formulaire envoyé deux fois) : rien à refaire.
            return self::vers($suivant);
        }
        $saisie = mb_substr($requete->request->getString('prix'), 0, 20);
        $cite = Flash::cite($p['designation']);
        switch ($requete->request->getString('action')) {
            case 'suivant':
                $prix = Saisie::centimes($saisie);
                if ($prix === null || $prix === 0) {
                    return $this->carte($p, $etat, count($restants), $saisie, true, 422);
                }
                $depot->fixerPrix($p['id'], $prix);
                $etat['faits']++;
                $etat['annulable'] = null;
                break;
            case 'reprendre':
                $exemple = $p['prix_exemple_centimes'] ?? 0;
                if ($exemple <= 0) {
                    return $this->carte($p, $etat, count($restants), $saisie, true, 422);
                }
                $depot->fixerPrix($p['id'], $exemple);
                $etat['faits']++;
                $etat['annulable'] = ['genre' => 'repris', 'id' => $p['id'], 'exemple' => $exemple];
                Flash::dire($requete, 'annuler', ['action' => '/vos-prix/annuler', 'texte' => $cite . "\u{00A0}: prix repris, " . Formats::montant($exemple)]);
                break;
            case 'passer':
                $etat['passes'][] = $p['id'];
                $etat['annulable'] = null;
                break;
            case 'retirer':
                $depot->supprimer($p['id']);
                $etat['retires']++;
                $etat['annulable'] = ['genre' => 'retire'] + array_diff_key($p, ['id' => 0]);
                Flash::dire($requete, 'annuler', ['action' => '/vos-prix/annuler', 'texte' => $cite . ' retiré du catalogue']);
                break;
            default:
                return $this->carte($p, $etat, count($restants), $saisie, true, 422);
        }
        $session->set(self::CLE, $etat);
        return self::vers($suivant);
    }

    /** Défait le dernier « Reprendre » ou le dernier retrait, puis rouvre ce produit. */
    public function annuler(Request $requete): Response
    {
        $session = $requete->getSession();
        $etat = self::etat($session);
        $a = $etat['annulable'];
        $etat['annulable'] = null;
        $cible = null;
        $depot = $this->depot($requete);
        if ($a !== null && ($a['genre'] ?? null) === 'repris' && is_int($a['id'] ?? null) && is_int($a['exemple'] ?? null)) {
            if ($depot->remettreExemple($a['id'], $a['exemple'])) {
                $etat['faits'] = max(0, $etat['faits'] - 1);
                $cible = $a['id'];
            }
        } elseif ($a !== null && ($a['genre'] ?? null) === 'retire') {
            // Le produit revient avec un nouvel identifiant : les lignes d'offre qui le citaient gardent leur texte
            // et leur prix, sans leur lien au catalogue (décision fragile 3 du plan).
            $exemple = $a['prix_exemple_centimes'] ?? null;
            $tva = $a['tva_pour_dix_mille'] ?? null;
            $synonymes = $a['synonymes'] ?? null;
            $cible = $depot->creer([
                'categorie' => self::texte($a['categorie'] ?? null),
                'designation' => self::texte($a['designation'] ?? null),
                'unite' => self::texte($a['unite'] ?? null),
                'prix_centimes' => 0,
                'tva_pour_dix_mille' => is_int($tva) ? $tva : null,
                'synonymes' => is_string($synonymes) ? $synonymes : null,
                'prix_exemple_centimes' => is_int($exemple) ? $exemple : null,
            ]);
            $etat['retires'] = max(0, $etat['retires'] - 1);
        }
        $session->set(self::CLE, $etat);
        return self::vers($cible);
    }

    public function plusTard(Request $requete): Response
    {
        $requete->getSession()->set(self::PLUS_TARD, true);
        return new RedirectResponse('/', 303);
    }

    private function depot(Request $requete): DepotProduits
    {
        return $this->c->produits(new Portee($requete->attributes->getInt('_artisan')));
    }

    /**
     * @param Produit $p
     * @param Etat $etat
     */
    private function carte(array $p, array $etat, int $restants, string $saisie, bool $erreur, int $code): Response
    {
        $faits = $etat['faits'] + count($etat['passes']) + $etat['retires'];
        return new Response($this->c->twig()->render('vos_prix.html.twig', [
            'p' => $p,
            'n' => $faits + 1,
            'total' => $faits + max(1, $restants),
            'saisie' => $saisie,
            'erreur' => $erreur,
            'bilan' => null,
        ]), $code);
    }

    /** @return Etat */
    private static function etat(SessionInterface $session): array
    {
        $e = $session->get(self::CLE);
        $e = is_array($e) ? $e : [];
        $passes = [];
        foreach (is_array($e['passes'] ?? null) ? $e['passes'] : [] as $id) {
            if (is_int($id)) {
                $passes[] = $id;
            }
        }
        $annulable = [];
        foreach (is_array($e['annulable'] ?? null) ? $e['annulable'] : [] as $cle => $valeur) {
            $annulable[(string) $cle] = $valeur;
        }
        return [
            'faits' => is_int($e['faits'] ?? null) ? $e['faits'] : 0,
            'passes' => $passes,
            'retires' => is_int($e['retires'] ?? null) ? $e['retires'] : 0,
            'annulable' => $annulable === [] ? null : $annulable,
        ];
    }

    /**
     * @param list<Produit> $sansPrix
     * @param Etat $etat
     * @return list<Produit> les produits sans prix que ce passage n'a pas encore passés
     */
    private static function restants(array $sansPrix, array $etat): array
    {
        return array_values(array_filter($sansPrix, static fn (array $p): bool => !in_array($p['id'], $etat['passes'], true)));
    }

    /**
     * @param list<Produit> $restants
     * @return int|null le produit qui suit $id dans la liste ; après le dernier, le premier ; null s'il n'en reste aucun
     */
    private static function apres(array $restants, int $id): ?int
    {
        $ids = array_column($restants, 'id');
        $rang = array_search($id, $ids, true);
        $autres = array_values(array_filter($ids, static fn (int $i): bool => $i !== $id));
        if ($autres === []) {
            return null;
        }
        return $rang === false ? $autres[0] : ($ids[$rang + 1] ?? $autres[0]);
    }

    private static function vers(?int $id): RedirectResponse
    {
        return new RedirectResponse($id === null ? '/vos-prix?suite=1' : "/vos-prix?p=$id&suite=1", 303);
    }

    private static function texte(mixed $valeur): string
    {
        return is_string($valeur) ? $valeur : '';
    }
}
