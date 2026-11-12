<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Audio\Uuid;
use OffreGen\Donnees\Portee;
use OffreGen\Donnees\Statut;
use OffreGen\Http\Conteneur;
use OffreGen\Http\PageIntrouvable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnregistrementControleur
{
    /** Étapes montrées à l'artisan, dans l'ordre. */
    private const ETAPES = [
        'Envoi de l\'enregistrement',
        'Transcription',
        'Lecture des postes et recherche dans votre catalogue',
    ];

    /** Nombre d'étapes faites pour chaque statut. Un échec garde les étapes faites avant lui (lues ailleurs). */
    private const FAITES = [Statut::RECU => 1, Statut::TRANSCRIPTION => 1, Statut::TRANSCRIT => 2, Statut::PRET => 3];

    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $parametres */
    public function afficher(Request $requete, array $parametres): Response
    {
        $uuid = $parametres['uuid'] ?? '';
        if (!Uuid::valide($uuid)) {
            throw new PageIntrouvable();
        }
        $portee = new Portee($requete->attributes->getInt('_artisan'));
        $e = $this->c->enregistrements($portee)->parUuid($uuid) ?? throw new PageIntrouvable();

        $echec = $e['statut'] === Statut::ECHEC;
        $faites = self::FAITES[$e['statut']] ?? 1;
        $etapes = [];
        foreach (self::ETAPES as $i => $libelle) {
            $etat = $i < $faites ? 'done' : ($i === $faites && !$echec ? 'cur' : 'todo');
            $etapes[] = ['libelle' => $libelle, 'etat' => $etat];
        }

        return new Response($this->c->twig()->render('enregistrement.html.twig', [
            'e' => $e,
            'etapes' => $etapes,
            'fini' => Statut::estFinal($e['statut']),
            'echec' => $echec,
        ]));
    }
}
