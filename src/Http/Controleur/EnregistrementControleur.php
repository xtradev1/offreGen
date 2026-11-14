<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Audio\Uuid;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\Portee;
use OffreGen\Donnees\Statut;
use OffreGen\Http\Conteneur;
use OffreGen\Http\PageIntrouvable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suivi d'un enregistrement, de l'envoi à l'offre prête. La page se met à jour seule : app.js interroge
 * l'état chaque seconde et fait avancer les étapes (retour de Xavier du 2026-11-12).
 *
 * @phpstan-import-type Enregistrement from DepotEnregistrements
 */
final class EnregistrementControleur
{
    /** Étapes montrées à l'artisan, dans l'ordre. app.js les fait avancer avec le nombre d'étapes faites. */
    public const ETAPES = [
        'Envoi de l\'enregistrement',
        'Transcription de la dictée',
        'Création de l\'offre',
        'Offre prête',
    ];

    /** Nombre d'étapes faites pour chaque statut. Un échec garde l'envoi pour fait. */
    private const FAITES = [Statut::RECU => 1, Statut::TRANSCRIPTION => 1, Statut::TRANSCRIT => 2, Statut::PRET => 4, Statut::ECHEC => 1];

    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $parametres */
    public function afficher(Request $requete, array $parametres): Response
    {
        $e = $this->enregistrement($requete, $parametres);
        $echec = $e['statut'] === Statut::ECHEC;
        return new Response($this->c->twig()->render('enregistrement.html.twig', [
            'e' => $e,
            'etapes' => self::etapes(self::FAITES[$e['statut']] ?? 1, $echec),
            'fini' => Statut::estFinal($e['statut']),
            'echec' => $echec,
        ]));
    }

    /**
     * L'état, pour la page qui se met à jour. Ni texte d'erreur ni transcription : des nombres et l'offre.
     *
     * @param array<string, string> $parametres
     */
    public function etat(Request $requete, array $parametres): JsonResponse
    {
        $e = $this->enregistrement($requete, $parametres);
        $reponse = new JsonResponse([
            'statut' => $e['statut'],
            'faites' => self::FAITES[$e['statut']] ?? 1,
            'echec' => $e['statut'] === Statut::ECHEC,
            'essais' => $e['tentatives'],
            'offre' => $e['statut'] === Statut::PRET ? $e['offre_id'] : null,
        ]);
        $reponse->headers->set('Cache-Control', 'no-store');
        return $reponse;
    }

    /** @return list<array{libelle: string, etat: string}> fait, en cours, en échec ou à venir */
    public static function etapes(int $faites, bool $echec): array
    {
        $etapes = [];
        foreach (self::ETAPES as $i => $libelle) {
            $etat = $i < $faites ? 'done' : ($i === $faites ? ($echec ? 'bad' : 'cur') : 'todo');
            $etapes[] = ['libelle' => $libelle, 'etat' => $etat];
        }
        return $etapes;
    }

    /**
     * @param array<string, string> $parametres
     * @return Enregistrement
     */
    private function enregistrement(Request $requete, array $parametres): array
    {
        $uuid = $parametres['uuid'] ?? '';
        if (!Uuid::valide($uuid)) {
            throw new PageIntrouvable();
        }
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        return $this->c->enregistrements($portee)->parUuid($uuid) ?? throw new PageIntrouvable();
    }
}
