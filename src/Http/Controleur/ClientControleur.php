<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Client\Rapprochement;
use OffreGen\Client\SaisieClient;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use OffreGen\Offre\SaisieRefusee;
use OffreGen\Offre\Totaux;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Onglet Clients (maquette validée par Xavier le 2026-11-12) : la liste, la fiche qui monte par-dessus,
 * et la fenêtre « Client de l'offre » (variante B). Une fiche naît quand l'artisan la crée ou confirme
 * le nom dicté ; elle ne se supprime que sans offre.
 *
 * @phpstan-import-type Client from DepotClients
 * @phpstan-import-type Offre from DepotOffres
 */
final class ClientControleur
{
    private const CHAMPS = ['nom', 'adresse', 'telephone', 'courriel'];

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function liste(Request $requete): Response
    {
        $clients = $this->clients($requete)->tous();
        return new Response($this->c->twig()->render('clients.html.twig', [
            'clients' => array_map(static fn (array $k): array => $k + [
                'texte' => self::texte($k['nom'] . ' ' . self::localite($k['adresse'])),
                'localite' => self::localite($k['adresse']),
                'lettre' => mb_strtoupper(mb_substr(self::texte(Rapprochement::cleDeTri($k['nom'])), 0, 1)),
                'initiales' => mb_strtoupper(mb_substr(Rapprochement::cleDeTri($k['nom']), 0, 2)),
            ], $clients),
        ]));
    }

    /** Nouvelle fiche ; avec ?offre=N, la fiche créée devient le client de cette offre. */
    public function nouveau(Request $requete): Response
    {
        $offre = $this->offreDemandee($requete, $requete->query->getString('offre'));
        $champs = array_fill_keys(self::CHAMPS, '');
        $champs['nom'] = mb_substr($requete->query->getString('nom'), 0, 190);
        return $this->fiche($requete, null, $champs, [], 200, $offre);
    }

    public function creer(Request $requete): Response
    {
        $offre = $this->offreDemandee($requete, $requete->request->getString('offre'));
        $champs = self::champs($requete);
        try {
            $f = SaisieClient::lire($champs);
        } catch (SaisieRefusee $e) {
            return $this->fiche($requete, null, $champs, $e->erreurs, 422, $offre);
        }
        $maintenant = $this->c->horloge()->now();
        $id = $this->clients($requete)->creer($f, $maintenant);
        if ($offre !== null) {
            $this->offres($requete)->rattacherClient($offre['id'], $id, $maintenant);
            Flash::dire($requete, 'info', 'Fiche ' . Flash::cite($f['nom']) . ' créée pour cette offre');
            return new RedirectResponse('/offres/' . $offre['id'], 303);
        }
        Flash::dire($requete, 'info', 'Fiche ' . Flash::cite($f['nom']) . ' créée');
        return new RedirectResponse('/clients#k' . $id, 303);
    }

    /** @param array<string, string> $parametres */
    public function afficher(Request $requete, array $parametres): Response
    {
        $k = $this->client($requete, $parametres);
        return $this->fiche($requete, $k, ['nom' => $k['nom'], 'adresse' => $k['adresse'] ?? '', 'telephone' => $k['telephone'] ?? '', 'courriel' => $k['courriel'] ?? ''], [], 200, null);
    }

    /**
     * Enregistrement de la fiche : en JSON pour l'enregistrement au fil de la saisie, sinon retour à la liste.
     *
     * @param array<string, string> $parametres
     */
    public function modifier(Request $requete, array $parametres): Response
    {
        $k = $this->client($requete, $parametres);
        $json = str_contains((string) $requete->headers->get('Accept'), 'application/json');
        $champs = self::champs($requete);
        try {
            $f = SaisieClient::lire($champs);
        } catch (SaisieRefusee $e) {
            return $json ? new JsonResponse(['ok' => false, 'erreurs' => $e->erreurs], 422) : $this->fiche($requete, $k, $champs, $e->erreurs, 422, null);
        }
        $this->clients($requete)->modifier($k['id'], $f, $this->c->horloge()->now());
        if ($json) {
            return new JsonResponse(['ok' => true]);
        }
        Flash::dire($requete, 'info', 'Fiche ' . Flash::cite($f['nom']) . ' enregistrée');
        return new RedirectResponse('/clients#k' . $k['id'], 303);
    }

    /** @param array<string, string> $parametres */
    public function supprimer(Request $requete, array $parametres): Response
    {
        $k = $this->client($requete, $parametres);
        if (!$this->clients($requete)->supprimer($k['id'])) {
            Flash::dire($requete, 'erreur','Ce client a des offres : sa fiche reste, pour que son nom reste sur elles.');
            return new RedirectResponse('/clients/' . $k['id'], 303);
        }
        Flash::dire($requete, 'info', 'Fiche ' . Flash::cite($k['nom']) . ' supprimée');
        return new RedirectResponse('/clients', 303);
    }

    /**
     * « Client de l'offre » : le nom dicté à confirmer, les fiches qui lui ressemblent, puis toutes les fiches.
     *
     * @param array<string, string> $parametres
     */
    public function choixPourOffre(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $clients = $this->clients($requete)->tous();
        $proches = [];
        if ($offre['client_id'] === null && $offre['client_dicte'] !== null) {
            foreach ($clients as $k) {
                $s = Rapprochement::ressemblance($offre['client_dicte'], $k['nom']);
                if ($s >= 0.5) {
                    $proches[] = $k + ['ressemblance' => $s, 'localite' => self::localite($k['adresse'])];
                }
            }
            usort($proches, static fn (array $x, array $y): int => $y['ressemblance'] <=> $x['ressemblance']);
            $proches = array_slice($proches, 0, 3);
        }
        return new Response($this->c->twig()->render('offre_client.html.twig', [
            'offre' => $offre,
            'proches' => $proches,
            'clients' => array_map(static fn (array $k): array => $k + [
                'localite' => self::localite($k['adresse']),
                'texte' => self::texte($k['nom'] . ' ' . self::localite($k['adresse'])),
            ], $clients),
        ]));
    }

    /**
     * Rattache l'offre à une fiche (client=N), ou crée la fiche du nom dicté (creer=1).
     *
     * @param array<string, string> $parametres
     */
    public function choisirPourOffre(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $maintenant = $this->c->horloge()->now();
        if ($requete->request->getString('creer') === '1') {
            if ($offre['client_dicte'] === null) {
                return new RedirectResponse('/offres/' . $offre['id'] . '/client', 303);
            }
            $f = ['nom' => mb_substr($offre['client_dicte'], 0, 190), 'adresse' => $offre['lieu_dicte'], 'telephone' => null, 'courriel' => null];
            $id = $this->clients($requete)->creer($f, $maintenant);
            $this->offres($requete)->rattacherClient($offre['id'], $id, $maintenant);
            Flash::dire($requete, 'info', 'Fiche ' . Flash::cite($f['nom']) . ' créée');
            return new RedirectResponse('/offres/' . $offre['id'], 303);
        }
        $clientId = Parametres::id(['client' => $requete->request->getString('client')], 'client');
        $k = $this->clients($requete)->parId($clientId) ?? throw new PageIntrouvable();
        $this->offres($requete)->rattacherClient($offre['id'], $k['id'], $maintenant);
        Flash::dire($requete, 'info', 'Client de l\'offre' . "\u{00A0}: " . $k['nom']);
        return new RedirectResponse('/offres/' . $offre['id'], 303);
    }

    /**
     * @param Client|null $k la fiche modifiée, null pour une nouvelle
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     * @param Offre|null $offre l'offre qui recevra la nouvelle fiche
     */
    private function fiche(Request $requete, ?array $k, array $champs, array $erreurs, int $code, ?array $offre): Response
    {
        $offres = [];
        if ($k !== null) {
            $leurs = $this->clients($requete)->offres($k['id']);
            $r = $this->c->reglages(self::portee($requete))->lire();
            $lignes = $this->offres($requete)->lignesDe(array_column($leurs, 'id'));
            foreach ($leurs as $o) {
                $offres[] = $o + ['total' => Totaux::calculer($lignes[$o['id']] ?? [], $r->tvaPourDixMille, $r->arrondiCinqCentimes)['ttc']];
            }
        }
        return new Response($this->c->twig()->render('client.html.twig', [
            'k' => $k,
            'champs' => $champs,
            'erreurs' => $erreurs,
            'offres' => $offres,
            'offre' => $offre,
        ]), $code);
    }

    /** La localité d'une adresse suisse : la dernière ligne, sans le NPA. */
    public static function localite(?string $adresse): string
    {
        $lignes = array_values(array_filter(array_map('trim', explode("\n", (string) $adresse)), static fn (string $l): bool => $l !== ''));
        $derniere = $lignes === [] ? '' : $lignes[count($lignes) - 1];
        return trim((string) preg_replace('/^\d{4}\s*/', '', $derniere));
    }

    /** Texte de recherche : minuscules sans accents, comme normaliser() dans app.js. */
    private static function texte(string $s): string
    {
        $ascii = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        return $ascii instanceof \Transliterator ? (string) $ascii->transliterate($s) : mb_strtolower($s);
    }

    private static function portee(Request $requete): Portee
    {
        return new Portee($requete->attributes->getInt('_artisan'));
    }

    private function clients(Request $requete): DepotClients
    {
        return $this->c->clients(self::portee($requete));
    }

    private function offres(Request $requete): DepotOffres
    {
        return $this->c->offres(self::portee($requete));
    }

    /**
     * @param array<string, string> $parametres
     * @return Client
     */
    private function client(Request $requete, array $parametres): array
    {
        return $this->clients($requete)->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
    }

    /**
     * @param array<string, string> $parametres
     * @return Offre
     */
    private function offre(Request $requete, array $parametres): array
    {
        return $this->offres($requete)->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
    }

    /** @return Offre|null l'offre demandée (?offre=N), à l'artisan ; aucune si le champ est vide */
    private function offreDemandee(Request $requete, string $id): ?array
    {
        return $id === '' ? null : $this->offre($requete, ['id' => $id]);
    }

    /** @return array<string, string> */
    private static function champs(Request $requete): array
    {
        $champs = [];
        foreach (self::CHAMPS as $nom) {
            $champs[$nom] = $requete->request->getString($nom);
        }
        return $champs;
    }
}
