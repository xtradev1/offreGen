<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Audio\Uuid;
use OffreGen\Donnees\Dates;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\Portee;
use OffreGen\Donnees\Statut;
use OffreGen\Http\Conteneur;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Offre\Arrivee;
use OffreGen\Systeme\Chronometre;
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
    /**
     * Étapes montrées à l'artisan, dans l'ordre. app.js les fait avancer avec le nombre d'étapes faites. « Offre prête »
     * n'en est plus une (maquette v2 du parcours de la dictée, Xavier, 2026-11-26) : prête, l'offre s'ouvre.
     */
    public const ETAPES = [
        'Envoi de l\'enregistrement',
        'Transcription de la dictée',
        'Lignes de l\'offre',
    ];

    /** Nombre d'étapes faites pour chaque statut. Un échec garde l'envoi pour fait. */
    private const FAITES = [Statut::RECU => 1, Statut::TRANSCRIPTION => 1, Statut::TRANSCRIT => 2, Statut::PRET => 3, Statut::ECHEC => 1];

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
            'interrompu' => self::interrompu($e, $this->c->horloge()->now()),
            // Prête : où mène son bouton, et combien de lignes restent à relire (la même règle que la fenêtre de dictée).
            'arrivee' => $this->arrivee($requete, $e),
            // Même règle que Traiteur::prevenir : un compte de démonstration ne reçoit aucun courriel.
            'sans_courriel' => ($this->c->artisans()->parId($requete->attributes->getInt('_artisan'))['demo'] ?? 0) === 1,
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
        // L'artisan attend à l'écran : le traitement le saura et n'enverra pas de courriel « offre prête »
        // (Xavier, 2026-11-23 : « pas de spam sinon les gens ne lisent plus »).
        if ($e['statut'] !== Statut::PRET && $e['statut'] !== Statut::ECHEC) {
            $this->c->enregistrements(new Portee($requete->attributes->getInt('_artisan')))->noterSuivi($parametres['uuid'], $this->c->horloge()->now());
        }
        $reponse = new JsonResponse([
            'statut' => $e['statut'],
            'faites' => self::FAITES[$e['statut']] ?? 1,
            'echec' => $e['statut'] === Statut::ECHEC,
            'essais' => $e['tentatives'],
            'interrompu' => self::interrompu($e, $this->c->horloge()->now()),
            'offre' => $e['statut'] === Statut::PRET ? $e['offre_id'] : null,
        ] + $this->arrivee($requete, $e));
        $reponse->headers->set('Cache-Control', 'no-store');
        return $reponse;
    }

    /**
     * Où aller quand l'offre est prête (Offre\Arrivee), et de quoi la présenter sur l'écran « Offre prête » : son objet,
     * son client, ses lignes, celles à relire. Jamais le prix (Xavier, 2026-11-26). Tout est null tant qu'elle n'est pas là.
     *
     * @param Enregistrement $e
     * @return array{suite: string|null, a_relire: int|null, lignes: int|null, titre: string|null, client: string|null}
     */
    private function arrivee(Request $requete, array $e): array
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $depot = $this->c->offres($portee);
        $offre = $e['statut'] === Statut::PRET && $e['offre_id'] !== null ? $depot->parId($e['offre_id']) : null;
        if ($offre === null) {
            return ['suite' => null, 'a_relire' => null, 'lignes' => null, 'titre' => null, 'client' => null];
        }
        $lignes = $depot->lignes($offre['id']);
        $aRelire = Arrivee::compter($lignes);
        // Le guide de la démo et de l'essai finit sur la page de l'offre (ses repères, « Revenir à mon espace ») : tant
        // qu'il se montre, la dictée y mène comme avant.
        if ($this->c->reglages($portee)->guideAMontrer()) {
            return ['suite' => '/offres/' . $offre['id'], 'a_relire' => 0, 'lignes' => count($lignes), 'titre' => $offre['titre'], 'client' => $offre['client_nom']];
        }
        return ['suite' => Arrivee::adresse($offre, $aRelire, count($lignes)), 'a_relire' => $aRelire, 'lignes' => count($lignes),
                'titre' => $offre['titre'], 'client' => $offre['client_nom']];
    }

    /**
     * D6 de Xavier (2026-11-24) : le téléphone envoie, après coup, la durée de l'envoi du fichier qu'il a mesurée. Une
     * seule fois par dictée ; ses heures se déduisent du début de la réception, jamais de l'horloge du téléphone.
     *
     * @param array<string, string> $parametres
     */
    public function envoi(Request $requete, array $parametres): Response
    {
        $e = $this->enregistrement($requete, $parametres);
        $ms = ImportControleur::entier($requete->request->getString('envoi_ms'), 1, 3600 * 1000);
        if ($ms === null) {
            return new Response('', 422);
        }
        $etapes = $this->c->etapes();
        if ($etapes->debutDe($e['id'], 'envoi') === null) {
            $fin = $etapes->debutDe($e['id'], 'reception') ?? Dates::depuisBase($e['recu_le']);
            $etapes->ajouter($e['id'], null, 1, 'envoi', Chronometre::TELEPHONE, $fin->modify("-$ms milliseconds"), $fin);
        }
        return new Response('', 204);
    }

    /**
     * Le traitement s'est arrêté en route (D5 de Xavier, 2026-11-24) : la dictée n'est pas finie et son verrou a expiré
     * sans être rendu. Le pouls le prolonge tant que le traitement vit ; un appel lent ne le fait donc pas expirer. Le cron
     * reprend la dictée dans la minute, le message disparaît alors.
     *
     * @param array{statut: string, verrou_jusqu_a: string|null} $e
     */
    public static function interrompu(array $e, \DateTimeImmutable $maintenant): bool
    {
        return !Statut::estFinal($e['statut']) && $e['verrou_jusqu_a'] !== null && Dates::depuisBase($e['verrou_jusqu_a']) < $maintenant;
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
