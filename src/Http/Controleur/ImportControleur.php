<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Audio\AudioRefuse;
use OffreGen\Audio\Uuid;
use OffreGen\Audio\ValidateurAudio;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ImportControleur
{
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

        $artisan = $this->c->artisans()->parId($portee->artisanId);
        if ($artisan !== null && $artisan['demo'] === 1 && $artisan['plafond_jour'] !== null
            && $enregistrements->recusDepuis($this->debutDuJour()) >= $artisan['plafond_jour']) {
            return $this->page("Ce compte de démonstration a atteint ses {$artisan['plafond_jour']} imports du jour. Réessayez demain.", 429);
        }

        $fichier = $requete->files->get('audio');
        if (!$fichier instanceof UploadedFile) {
            return $this->page('Choisissez un fichier audio.', 422);
        }
        try {
            $audio = $this->c->validateurAudio()->valider($fichier);
        } catch (AudioRefuse $e) {
            return $this->page($e->getMessage(), 422);
        }
        // Règle de Xavier (2026-11-12) : moins de 5 secondes, rien n'est gardé. Durée illisible : accepté.
        $minimum = (int) $this->c->parametre('DUREE_MIN_SECONDES', '5');
        $duree = $this->c->dureeAudio()->secondes($fichier->getPathname());
        if ($duree === null) {
            $this->c->journal()->warning('offreGen, import : durée illisible par ffprobe, minimum non vérifié.');
        } elseif ($duree < $minimum) {
            return $this->page("Enregistrement non pris en compte : $minimum secondes au minimum.", 422);
        }

        $uuid = Uuid::v4();
        $stockage = $this->c->stockageAudio();
        $relatif = $stockage->ranger($portee->artisanId, $uuid, $audio);
        try {
            $enregistrements->creer($uuid, $fichier->getClientOriginalName(), $audio->mime, $audio->taille, $relatif, $this->c->horloge()->now());
        } catch (\Throwable $e) {
            // Sans ligne en base, le fichier ne serait jamais traité ni purgé.
            $stockage->supprimer($relatif);
            throw $e;
        }

        $this->c->programmerTraitement($uuid);
        return new RedirectResponse('/enregistrements/' . $uuid, 303);
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
