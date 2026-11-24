<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Compte\DoublonProspect;
use OffreGen\Compte\Prospects;
use OffreGen\Compte\Role;
use OffreGen\Donnees\Dates;
use OffreGen\Donnees\PorteeEquipe;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La rubrique « Prospects » de l'administration (docs/plans/2026-11-24_prospects, maquette validée par Xavier le
 * 2026-11-24). Le noyau a vérifié le rôle ; les règles (qui voit quoi, qui écrit, doublons, plafond) sont dans
 * Compte\Prospects. Ici : lire la requête, appeler la règle, préparer les mots de l'écran.
 */
final class ProspectControleur
{
    private const CHAMPS = ['entreprise', 'localite', 'metier', 'metier_saisi', 'telephone', 'courriel', 'contact', 'origine', 'ide', 'pas_texte', 'pas_date'];
    private const SORTES = ['demo' => 'Démo', 'essai' => 'Essai', 'client' => 'Client'];
    private const MOTIFS = ['ide' => 'même numéro IDE', 'telephone' => 'même téléphone', 'courriel' => 'même courriel', 'nom' => 'même nom, même localité'];

    public function __construct(private readonly Conteneur $c)
    {
    }

    public function liste(Request $requete): Response
    {
        return $this->page('admin/prospects.html.twig', ['titre' => 'Prospects'] + $this->donneesListe($requete->query->getString('vue'), $requete->query->getString('q')));
    }

    /** Chercher avant de créer : la création ne se propose qu'après les fiches et les comptes qui ressemblent. */
    public function chercher(Request $requete): Response
    {
        $q = mb_substr(trim($requete->query->getString('q')), 0, 80);
        $trouve = $this->prospects()->chercher($this->acteur(), $this->toutVoir(), $q);
        return $this->page('admin/prospect_chercher.html.twig', [
            'titre' => 'Ajouter un prospect',
            'q' => $q,
            'cherche' => mb_strlen($q) >= 3,
            'lignes' => array_map($this->ligne(...), $trouve['fiches']),
            'comptes' => array_map(self::compte(...), $trouve['comptes']),
        ]);
    }

    public function nouveau(Request $requete): Response
    {
        return $this->formulaire(null, ['entreprise' => mb_substr(trim($requete->query->getString('nom')), 0, 120)], null);
    }

    public function creer(Request $requete): Response
    {
        $saisie = self::saisie($requete);
        $prend = $requete->request->getString('prend') === '1';
        try {
            $id = $this->prospects()->creer($this->acteur(), $saisie, $prend, $requete->request->getString('confirme') === '1');
        } catch (DoublonProspect $d) {
            return $this->doublon($d, $saisie, $prend, '/admin/prospects');
        } catch (\InvalidArgumentException | \OverflowException $e) {
            return $this->formulaire(null, $saisie + ['prend' => $prend ? '1' : ''], $e->getMessage(), 422);
        }
        Flash::dire($requete, 'info', 'Prospect enregistré.');
        return new RedirectResponse("/admin/prospects/$id", 303);
    }

    /** @param array<string, string> $p */
    public function fiche(array $p, ?string $erreur = null, int $code = 200): Response
    {
        $id = self::id($p);
        $prospects = $this->prospects();
        try {
            $vue = $prospects->vue($this->acteur(), $this->toutVoir(), $prospects->fiche($id));
        } catch (\DomainException) {
            throw new PageIntrouvable();
        }
        $ligne = $this->ligne($vue);
        $notes = [];
        foreach ($prospects->notes($this->acteur(), $this->toutVoir(), $id) as $n) {
            $notes[] = $n + ['jour' => Dates::depuisBase($n['cree_le'])->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('d.m')];
        }
        $aujourdhui = new \DateTimeImmutable($prospects->aujourdhui());
        return $this->page('admin/prospect.html.twig', [
            'titre' => $ligne['entreprise'],
            'p' => $ligne,
            'notes' => $notes,
            'etats' => Prospects::ETATS,
            'origines' => Prospects::ORIGINES,
            'erreur' => $erreur,
            'aujourdhui' => $aujourdhui->format('Y-m-d'),
            'date_max' => $aujourdhui->modify('+' . Prospects::JOURS_PAS . ' days')->format('Y-m-d'),
            'peut_fusionner' => $this->role() === Role::Administrateur,
            // Sur PC, la liste reste à côté de la fiche.
        ] + $this->donneesListe($this->toutVoir() ? 'tous' : 'miens', ''), $code);
    }

    /** @param array<string, string> $p */
    public function modifierFormulaire(array $p): Response
    {
        $id = self::id($p);
        $fiche = $this->fichePourEcrire($id);
        $saisie = [];
        foreach (self::CHAMPS as $champ) {
            $saisie[$champ] = is_string($fiche[$champ] ?? null) ? $fiche[$champ] : '';
        }
        $saisie['localite'] = trim((is_string($fiche['npa'] ?? null) ? $fiche['npa'] : '') . ' ' . (is_string($fiche['localite'] ?? null) ? $fiche['localite'] : ''));
        return $this->formulaire($id, $saisie, null);
    }

    /** @param array<string, string> $p */
    public function modifier(Request $requete, array $p): Response
    {
        $id = self::id($p);
        $saisie = self::saisie($requete);
        try {
            $this->prospects()->modifier($this->acteur(), $id, $saisie, $requete->request->getString('confirme') === '1');
        } catch (\DomainException) {
            throw new PageIntrouvable();
        } catch (DoublonProspect $d) {
            return $this->doublon($d, $saisie, false, "/admin/prospects/$id");
        } catch (\InvalidArgumentException $e) {
            return $this->formulaire($id, $saisie, $e->getMessage(), 422);
        }
        Flash::dire($requete, 'info', 'Enregistré.');
        return new RedirectResponse("/admin/prospects/$id", 303);
    }

    /** @param array<string, string> $p */
    public function prendre(Request $requete, array $p): Response
    {
        $id = self::id($p);
        try {
            $this->prospects()->fiche($id);
        } catch (\DomainException) {
            throw new PageIntrouvable();
        }
        try {
            $this->prospects()->prendre($this->acteur(), $id);
        } catch (\DomainException) {
            // La fiche existe et quelqu'un la tient : un collègue l'a prise entre-temps. Rien n'est écrit, on le dit.
            Flash::dire($requete, 'erreur', "Ce prospect est déjà suivi\u{00A0}: il n'est plus libre.");
            return new RedirectResponse("/admin/prospects/$id", 303);
        } catch (\OverflowException $e) {
            return $this->fiche($p, $e->getMessage(), 422);
        }
        Flash::dire($requete, 'info', 'Ce prospect est à vous.');
        return new RedirectResponse("/admin/prospects/$id", 303);
    }

    /** @param array<string, string> $p */
    public function lacher(Request $requete, array $p): Response
    {
        $reponse = $this->geste($requete, $p, fn (int $id) => $this->prospects()->lacher($this->acteur(), $id), 'Prospect lâché : il est libre.');
        return $reponse instanceof RedirectResponse ? new RedirectResponse('/admin/prospects', 303) : $reponse;
    }

    /** @param array<string, string> $p */
    public function etat(Request $requete, array $p): Response
    {
        return $this->geste($requete, $p, fn (int $id) => $this->prospects()->changerEtat($this->acteur(), $id, $requete->request->getString('etat')), 'Enregistré.');
    }

    /** @param array<string, string> $p */
    public function pas(Request $requete, array $p): Response
    {
        return $this->geste($requete, $p, fn (int $id) => $this->prospects()->poserPas($this->acteur(), $id, $requete->request->getString('texte'), $requete->request->getString('date')), 'Prochain pas enregistré.');
    }

    /** @param array<string, string> $p */
    public function pasFait(Request $requete, array $p): Response
    {
        return $this->geste($requete, $p, fn (int $id) => $this->prospects()->pasFait($this->acteur(), $id), 'Noté.');
    }

    /** @param array<string, string> $p */
    public function note(Request $requete, array $p): Response
    {
        return $this->geste($requete, $p, fn (int $id) => $this->prospects()->noter($this->acteur(), $id, $requete->request->getString('texte')), 'Note ajoutée.');
    }

    /**
     * « Créer une démo » depuis la fiche : le code QR de la démo s'affiche aussitôt, comme pour une démo ordinaire.
     *
     * @param array<string, string> $p
     */
    public function demo(Request $requete, array $p): Response
    {
        $id = self::id($p);
        try {
            $compte = $this->prospects()->creerDemo($this->acteur(), $this->role(), $id);
        } catch (\DomainException) {
            throw new PageIntrouvable();
        } catch (\InvalidArgumentException | \OverflowException $e) {
            return $this->fiche($p, $e->getMessage(), 422);
        }
        return new RedirectResponse("/admin/artisans/$compte/qr", 303);
    }

    /**
     * Les fiches avec lesquelles l'administrateur peut fusionner celle-ci.
     *
     * @param array<string, string> $p
     */
    public function fusionFormulaire(Request $requete, array $p, ?string $erreur = null, int $code = 200): Response
    {
        $id = self::id($p);
        try {
            $fiche = $this->prospects()->fiche($id);
        } catch (\DomainException) {
            throw new PageIntrouvable();
        }
        $q = mb_substr(trim($requete->query->getString('q')), 0, 80);
        // Sans recherche : les fiches qui portent les mêmes mots que celle-ci.
        $trouve = $this->prospects()->chercher($this->acteur(), true, $q === '' ? $fiche['entreprise'] : $q);
        $lignes = array_values(array_filter(array_map($this->ligne(...), $trouve['fiches']), static fn (array $l): bool => $l['id'] !== $id));
        return $this->page('admin/prospect_fusion.html.twig', ['titre' => 'Fusionner', 'p' => $this->ligne($this->prospects()->vue($this->acteur(), true, $fiche)), 'q' => $q, 'lignes' => $lignes, 'erreur' => $erreur], $code);
    }

    /** @param array<string, string> $p */
    public function fusion(Request $requete, array $p): Response
    {
        $id = self::id($p);
        $avec = $requete->request->getString('avec');
        if (preg_match('/^[1-9]\d{0,9}$/', $avec) !== 1) {
            throw new PageIntrouvable();
        }
        try {
            $this->prospects()->fusionner($this->acteur(), $id, (int) $avec);
        } catch (\DomainException) {
            throw new PageIntrouvable();
        } catch (\InvalidArgumentException $e) {
            return $this->fusionFormulaire($requete, $p, $e->getMessage(), 422);
        }
        Flash::dire($requete, 'info', 'Les deux fiches n\'en font plus qu\'une.');
        return new RedirectResponse("/admin/prospects/$id", 303);
    }

    /**
     * Un geste sur une fiche : hors droit, la fiche n'existe pas (404) ; une saisie refusée se dit sur la fiche (422).
     *
     * @param array<string, string> $p
     * @param \Closure(int): void $geste
     */
    private function geste(Request $requete, array $p, \Closure $geste, string $dit): Response
    {
        $id = self::id($p);
        try {
            $geste($id);
        } catch (\DomainException) {
            throw new PageIntrouvable();
        } catch (\InvalidArgumentException | \OverflowException $e) {
            return $this->fiche($p, $e->getMessage(), 422);
        }
        Flash::dire($requete, 'info', $dit);
        return new RedirectResponse("/admin/prospects/$id", 303);
    }

    /**
     * @param array<string, string> $saisie
     */
    private function formulaire(?int $id, array $saisie, ?string $erreur, int $code = 200): Response
    {
        $aujourdhui = new \DateTimeImmutable($this->prospects()->aujourdhui());
        $vides = array_fill_keys(self::CHAMPS, '') + ['prend' => '1'];
        return $this->page('admin/prospect_formulaire.html.twig', [
            'titre' => $id === null ? 'Nouveau prospect' : 'Modifier la fiche',
            'id' => $id,
            'saisie' => $saisie + $vides,
            'metiers' => CatalogueFictif::libelles(),
            'origines' => Prospects::ORIGINES,
            'erreur' => $erreur,
            'aujourdhui' => $aujourdhui->format('Y-m-d'),
            'date_max' => $aujourdhui->modify('+' . Prospects::JOURS_PAS . ' days')->format('Y-m-d'),
        ], $code);
    }

    /**
     * L'avertissement de doublon : les fiches et les comptes qui ressemblent, et la saisie gardée pour confirmer.
     *
     * @param array<string, string> $saisie
     */
    private function doublon(DoublonProspect $d, array $saisie, bool $prend, string $action): Response
    {
        $lignes = [];
        foreach ($d->fiches as $f) {
            try {
                $vue = $this->prospects()->vue($this->acteur(), $this->toutVoir(), $this->prospects()->fiche($f['id']));
            } catch (\DomainException) {
                continue;
            }
            $lignes[] = $this->ligne($vue) + ['motif' => self::MOTIFS[$f['motif']] ?? ''];
        }
        return $this->page('admin/prospect_doublon.html.twig', [
            'titre' => $d->ferme ? 'Cette entreprise est déjà notée' : 'Ce prospect est peut-être déjà noté',
            'ferme' => $d->ferme,
            'lignes' => $lignes,
            'comptes' => array_map(self::compte(...), $d->comptes),
            'saisie' => $saisie + ['prend' => $prend ? '1' : ''],
            'action' => $action,
        ]);
    }

    /**
     * La liste d'une vue, prête pour l'écran : les fiches, les nombres des onglets, les pas dus.
     *
     * @return array<string, mixed>
     */
    private function donneesListe(string $vue, string $q): array
    {
        $q = mb_substr(trim($q), 0, 80);
        $liste = $this->prospects()->liste($this->acteur(), $this->toutVoir(), $vue, $q);
        $lignes = array_map($this->ligne(...), $liste['fiches']);
        return [
            'vue' => $liste['vue'],
            'q' => $q,
            'lignes' => $lignes,
            'nombres' => $liste['nombres'],
            'plafond' => Prospects::PLAFOND,
            'tout_voir' => $this->toutVoir(),
            // « À faire » : les pas dus ou en retard de « Les miens ».
            'a_faire' => $liste['vue'] === 'miens' ? array_values(array_filter($lignes, static fn (array $l): bool => ($l['retard'] ?? false) || ($l['pas_aujourdhui'] ?? false))) : [],
        ];
    }

    /**
     * Une fiche prête pour l'écran : ses dates dites comme on les lit, son métier en clair.
     *
     * @param array{entiere: bool, ecrit: bool, fiche: array<string, mixed>} $vue
     * @return array<string, mixed>
     */
    private function ligne(array $vue): array
    {
        $f = $vue['fiche'];
        $lu = static fn (string $cle): ?string => is_string($f[$cle] ?? null) ? $f[$cle] : null;
        $metier = $lu('metier_saisi') ?? ($lu('metier') === null || $lu('metier') === 'autre' ? null : (CatalogueFictif::libelles()[$lu('metier')] ?? null));
        $compte = $lu('compte');
        $tel = $lu('telephone');
        return $f + [
            'entiere' => $vue['entiere'],
            'ecrit' => $vue['ecrit'],
            'metier_nom' => $metier,
            'compte_nom' => $compte === null ? null : self::SORTES[$compte],
            'pas_quand' => $lu('pas_le') === null ? null : $this->jour($lu('pas_le')),
            'libre_quand' => $lu('libre_le') === null ? null : (new \DateTimeImmutable($lu('libre_le')))->format('d.m'),
            'suivi_quand' => $lu('suivi_depuis') === null ? null : Dates::depuisBase($lu('suivi_depuis'))->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('d.m.Y'),
            'action_quand' => $lu('derniere_action_le') === null ? null : Dates::depuisBase($lu('derniere_action_le'))->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('d.m.Y'),
            'echeance_quand' => $lu('echeance') === null ? null : (new \DateTimeImmutable($lu('echeance')))->modify('+1 day')->format('d.m.Y'),
            'tel' => $tel === null ? null : (string) preg_replace('/[^0-9+]/', '', $tel),
            'cree_quand' => $lu('cree_le') === null ? null : Dates::depuisBase($lu('cree_le'))->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('d.m'),
            'etat_nom' => Prospects::ETATS[$lu('etat') ?? ''] ?? null,
            // Une fiche réduite n'a pas ces champs : les gabarits les lisent vides.
        ] + array_fill_keys(['etat', 'telephone', 'courriel', 'contact', 'origine', 'ide', 'pas_texte', 'pas_le', 'createur', 'compte_id', 'libre_dans', 'npa'], null) + ['retard' => false, 'pas_aujourdhui' => false];
    }

    /**
     * @param array{id: int, entreprise: string, demo: int, offres_max: int|null, apporteur: string|null} $c
     * @return array{entreprise: string, sorte: string, apporteur: string|null}
     */
    private static function compte(array $c): array
    {
        return ['entreprise' => $c['entreprise'], 'sorte' => $c['demo'] === 1 ? 'Démo' : ($c['offres_max'] === null ? 'Client' : 'Essai'), 'apporteur' => $c['apporteur']];
    }

    /** Un jour comme on le dit : « aujourd'hui », « demain », « hier », sinon « jeudi 26.11 ». */
    private function jour(string $date): string
    {
        // Les deux jours dans le fuseau du formateur : le nom du jour ne dépend pas du fuseau du serveur.
        $utc = new \DateTimeZone('UTC');
        $aujourdhui = new \DateTimeImmutable($this->prospects()->aujourdhui(), $utc);
        $jour = new \DateTimeImmutable($date, $utc);
        $ecart = (int) $aujourdhui->diff($jour)->format('%r%a');
        if ($ecart >= -1 && $ecart <= 1) {
            return ['hier', "aujourd'hui", 'demain'][$ecart + 1];
        }
        $nom = new \IntlDateFormatter('fr_CH', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', null, 'EEEE');
        return $nom->format($jour) . ' ' . $jour->format('d.m');
    }

    /**
     * La fiche entière, pour qui la tient ; 404 sinon.
     *
     * @return array<string, mixed>
     */
    private function fichePourEcrire(int $id): array
    {
        try {
            $fiche = $this->prospects()->fiche($id);
        } catch (\DomainException) {
            throw new PageIntrouvable();
        }
        if (!$this->prospects()->vue($this->acteur(), $this->toutVoir(), $fiche)['ecrit']) {
            throw new PageIntrouvable();
        }
        return $fiche;
    }

    /** @return array<string, string> */
    private static function saisie(Request $requete): array
    {
        $saisie = [];
        foreach (self::CHAMPS as $champ) {
            $saisie[$champ] = mb_substr($requete->request->getString($champ), 0, 250);
        }
        return $saisie;
    }

    /** @param array<string, string> $p */
    private static function id(array $p): int
    {
        return preg_match('/^[1-9]\d{0,9}$/', $p['id'] ?? '') === 1 ? (int) $p['id'] : throw new PageIntrouvable();
    }

    /** @param array<string, mixed> $vars */
    private function page(string $gabarit, array $vars, int $code = 200): Response
    {
        $vars += ['actif' => 'prospects', 'role' => $this->role(), 'appels_en_attente' => $this->c->depotAppels()->nombreARappeler(new PorteeEquipe($this->acteur(), $this->toutVoir()))];
        $reponse = new Response($this->c->twig()->render($gabarit, $vars), $code);
        // Des noms, des numéros de téléphone, des notes : rien ne reste dans le cache du navigateur.
        $reponse->headers->set('Cache-Control', 'no-store');
        return $reponse;
    }

    private function prospects(): Prospects
    {
        return $this->c->prospects();
    }

    /** Le modérateur et l'administrateur lisent toutes les fiches (Xavier, 2026-11-24 : « dans un premier temps »). */
    private function toutVoir(): bool
    {
        return $this->role() !== Role::Commercial;
    }

    private function role(): Role
    {
        return $this->c->garde()->roleCourant() ?? throw new \LogicException("Route d'administration sans rôle.");
    }

    private function acteur(): int
    {
        return $this->c->garde()->artisanCourant() ?? throw new \LogicException("Route d'administration sans compte.");
    }
}
