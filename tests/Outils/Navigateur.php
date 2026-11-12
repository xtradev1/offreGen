<?php

declare(strict_types=1);

namespace OffreGen\Tests\Outils;

use OffreGen\Http\Conteneur;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Un navigateur de test : il garde sa session d'une requête à l'autre et reprend le jeton CSRF
 * dans la dernière page reçue, comme un vrai navigateur qui envoie le formulaire affiché.
 */
final class Navigateur
{
    public readonly Session $session;
    private string $dernierePage = '';

    public function __construct(public readonly Conteneur $c, private readonly string $ip = '127.0.0.1')
    {
        $this->session = new Session(new MockArraySessionStorage());
    }

    public function get(string $chemin): Response
    {
        return $this->envoyer(Request::create($chemin, 'GET', server: ['REMOTE_ADDR' => $this->ip]));
    }

    /**
     * @param array<string, string> $champs sans _csrf, il est repris de la dernière page
     * @param array<string, UploadedFile> $fichiers
     */
    public function post(string $chemin, array $champs, array $fichiers = [], bool $avecJeton = true, bool $json = false): Response
    {
        if ($avecJeton && !isset($champs['_csrf'])) {
            $champs['_csrf'] = $this->jeton();
        }
        // $json : comme l'enregistrement automatique d'app.js, qui demande une réponse en JSON.
        $serveur = ['REMOTE_ADDR' => $this->ip] + ($json ? ['HTTP_ACCEPT' => 'application/json'] : []);
        $page = $this->dernierePage;
        $reponse = $this->envoyer(Request::create($chemin, 'POST', $champs, files: $fichiers, server: $serveur));
        if ($json) {
            // Le JSON n'a pas de formulaire : le jeton de la page affichée reste valable, comme dans le navigateur.
            $this->dernierePage = $page;
        }
        return $reponse;
    }

    public function connecter(string $courriel, string $motDePasse = Jeu::MOT_DE_PASSE): Response
    {
        $this->get('/connexion');
        return $this->post('/connexion', ['courriel' => $courriel, 'mot_de_passe' => $motDePasse]);
    }

    public function jeton(): string
    {
        if (preg_match('/name="_csrf" value="([^"]+)"/', $this->dernierePage, $m) !== 1) {
            throw new \LogicException('Aucun jeton CSRF dans la dernière page : faire un GET du formulaire avant.');
        }
        return $m[1];
    }

    private function envoyer(Request $requete): Response
    {
        $requete->setSession($this->session);
        $reponse = $this->c->noyau()->traiter($requete);
        $this->dernierePage = (string) $reponse->getContent();
        return $reponse;
    }
}
