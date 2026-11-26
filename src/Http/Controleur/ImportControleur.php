<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Audio\AudioRefuse;
use OffreGen\Audio\AudioValide;
use OffreGen\Audio\DureeAudio;
use OffreGen\Audio\Uuid;
use OffreGen\Audio\ValidateurAudio;
use OffreGen\Compte\Demos;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Systeme\Chronometre;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ImportControleur
{
    /** Le refus d'une démo finie, repris par la feuille de dictée (_nouvelle_offre.html.twig, data-epuise). */
    public const DEMO_TERMINEE = "Votre démo est terminée. Pour dicter ou importer de nouveau, passez à l'essai.";

    /** Le navigateur s'arrête pile à la limite ; le fichier qu'il produit peut la dépasser d'une fraction de seconde. */

    /** Suites d'une dictée continuée, en plus du début : quatre coupures rattrapées, c'est déjà beaucoup. */
    public const SUITES_MAX = 4;

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function afficher(Request $requete): Response
    {
        return $this->page(null, 200);
    }

    public function envoyer(Request $requete): Response
    {
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $enregistrements = $this->c->enregistrements($portee);

        // Une dictée déjà reçue (réponse du premier envoi perdue en route) : renvoyée à son enregistrement, avant
        // les plafonds, qui la compteraient deux fois (revue du 2026-11-18, dictée à l'abri).
        $cle = $requete->request->getString('cle');
        $cle = preg_match('/^\d{10,16}-[a-z0-9]{1,12}$/', $cle) === 1 ? $cle : null;
        $deja = $cle === null ? null : $enregistrements->uuidParCle($cle);
        if ($deja !== null) {
            return new RedirectResponse('/enregistrements/' . $deja, 303);
        }

        $artisan = $this->c->artisans()->parId($portee->artisanId);
        // Une démo finie ne dicte plus (chantier onboarding, lot 2) : 410, le message que la feuille de dictée affiche.
        if ($artisan !== null && $artisan['demo'] === 1 && Demos::terminee($artisan['demo_fin'], $this->c->horloge()->now())) {
            return $this->page(self::DEMO_TERMINEE, 410);
        }
        if ($artisan !== null && $artisan['demo'] === 1 && $artisan['plafond_jour'] !== null
            && $enregistrements->recusDepuis($this->debutDuJour()) >= $artisan['plafond_jour']) {
            return $this->page("Ce compte de démonstration a atteint ses {$artisan['plafond_jour']} imports du jour. Réessayez demain.", 429);
        }
        $essai = $this->c->plafondEssai($portee->artisanId);
        if ($essai !== null && $essai->epuise()) {
            return $this->page($essai->refus(), 429);
        }

        $fichier = $requete->files->get('audio');
        if (!$fichier instanceof UploadedFile) {
            return $this->page('Choisissez un fichier audio.', 422);
        }
        // Une dictée continuée « à la suite » (recette AB-1, Xavier, 2026-11-24) : la suite arrive en fichiers à part,
        // dans le même envoi. Toutes les parties sont contrôlées avant d'en ranger une seule.
        $suites = $requete->files->has('suite') ? $requete->files->get('suite') : [];
        if (!is_array($suites) || !array_is_list($suites) || array_filter($suites, static fn (mixed $f): bool => !$f instanceof UploadedFile) !== []) {
            return $this->page("L'envoi a été interrompu. Réessayez.", 422);
        }
        if (count($suites) > self::SUITES_MAX) {
            return $this->page('Une dictée compte ' . (self::SUITES_MAX + 1) . ' parties au plus.', 422);
        }
        /** @var list<UploadedFile> $parties */
        $parties = [$fichier, ...$suites];
        try {
            $audios = array_map(fn (UploadedFile $f): AudioValide => $this->c->validateurAudio()->valider($f), $parties);
        } catch (AudioRefuse $e) {
            return $this->page($e->getMessage(), 422);
        }
        // Règle de Xavier (2026-11-12) : moins de 5 secondes, rien n'est gardé. Durée illisible : accepté.
        // Une dictée en parties se compte entière.
        $minimum = (int) $this->c->parametre('DUREE_MIN_SECONDES', '5');
        $duree = 0.0;
        foreach ($parties as $partie) {
            $s = $this->c->dureeAudio()->secondes($partie->getPathname());
            $duree = $s === null || $duree === null ? null : $duree + $s;
        }
        if ($duree === null) {
            $this->c->journal()->warning('offreGen, import : durée illisible par ffprobe, minimum non vérifié.');
        } elseif ($duree < $minimum) {
            return $this->page("Enregistrement non pris en compte : $minimum secondes au minimum.", 422);
        } elseif ($duree > $this->c->dureeMaxSecondes() + DureeAudio::TOLERANCE_SECONDES) {
            return $this->page('Enregistrement trop long : ' . self::enClair($this->c->dureeMaxSecondes()) . ' au maximum.', 422);
        }

        $uuid = Uuid::v4();
        $stockage = $this->c->stockageAudio();
        $ranges = [];
        try {
            foreach ($audios as $i => $audio) {
                $ranges[] = $stockage->ranger($portee->artisanId, $uuid, $audio, $i + 1);
            }
            $taille = array_sum(array_map(static fn (AudioValide $a): int => $a->taille, $audios));
            // Sous-chantier 31 : navigateur, origine et durée (mesurée ici quand ffprobe tourne, sinon celle du téléphone).
            $dureeMs = $duree !== null ? (int) round($duree * 1000) : self::entier($requete->request->getString('duree_ms'), 1, 2 * 3600 * 1000);
            $origine = in_array($requete->request->getString('origine'), ['micro', 'fichier'], true) ? $requete->request->getString('origine') : null;
            $id = $enregistrements->creer($uuid, $fichier->getClientOriginalName(), $audios[0]->mime, $taille, $ranges[0], $this->c->horloge()->now(), $cle,
                array_slice($ranges, 1), $requete->headers->get('User-Agent'), $origine, $dureeMs);
        } catch (\Throwable $e) {
            // Sans ligne en base, les fichiers ne seraient jamais traités ni purgés.
            foreach ($ranges as $relatif) {
                $stockage->supprimer($relatif);
            }
            throw $e;
        }

        $this->c->programmerTraitement($uuid);
        $this->noterReception($requete, $id);
        return new RedirectResponse('/enregistrements/' . $uuid, 303);
    }

    /**
     * L'étape « réception » du chronomètre : de l'arrivée de la requête à la réponse. Sa durée vient de l'horloge du
     * processus (REQUEST_TIME_FLOAT), ses heures de l'horloge de l'application. Jamais bloquante pour l'import.
     */
    private function noterReception(Request $requete, int $enregistrementId): void
    {
        try {
            $arrivee = $requete->server->get('REQUEST_TIME_FLOAT');
            $ms = is_float($arrivee) ? max(0, (int) round((microtime(true) - $arrivee) * 1000)) : 0;
            $fin = $this->c->horloge()->now();
            $this->c->etapes()->ajouter($enregistrementId, null, 1, 'reception', Chronometre::WEB, $fin->modify("-$ms milliseconds"), $fin);
        } catch (\Throwable $e) {
            $this->c->journal()->warning('offreGen, chronomètre de la réception : ' . $e::class);
        }
    }

    /** Un entier de texte entre deux bornes, ou null. */
    public static function entier(string $texte, int $min, int $max): ?int
    {
        if (preg_match('/^\d{1,10}$/', $texte) !== 1) {
            return null;
        }
        $n = (int) $texte;
        return $n >= $min && $n <= $max ? $n : null;
    }

    /** 3600 : « 1 heure » ; 1800 : « 30 minutes » ; 5 : « 5 secondes ». */
    public static function enClair(int $secondes): string
    {
        [$n, $unite] = match (true) {
            $secondes % 3600 === 0 => [intdiv($secondes, 3600), 'heure'],
            $secondes % 60 === 0 => [intdiv($secondes, 60), 'minute'],
            default => [$secondes, 'seconde'],
        };
        return $n . ' ' . $unite . ($n > 1 ? 's' : '');
    }

    /** Minuit à Zurich, exprimé en UTC : le plafond démo se compte par jour suisse. */
    private function debutDuJour(): \DateTimeImmutable
    {
        return $this->c->horloge()->now()
            ->setTimezone(new \DateTimeZone('Europe/Zurich'))
            ->setTime(0, 0)
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    private function page(?string $erreur, int $code): Response
    {
        return new Response($this->c->twig()->render('import.html.twig', [
            'erreur' => $erreur,
            'formats' => ValidateurAudio::FORMATS,
            'taille_max_mo' => intdiv(ValidateurAudio::TAILLE_MAX, 1024 * 1024),
        ]), $code);
    }
}
