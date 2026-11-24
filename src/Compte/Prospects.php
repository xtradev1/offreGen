<?php

declare(strict_types=1);

namespace OffreGen\Compte;

use OffreGen\Catalogue\CatalogueFictif;
use OffreGen\Catalogue\Recherche;
use OffreGen\Donnees\Base;
use OffreGen\Donnees\Dates;
use OffreGen\Donnees\DepotProspects;
use OffreGen\Donnees\JournalAdmin;
use OffreGen\Donnees\PorteeEquipe;
use Psr\Clock\ClockInterface;

/**
 * Le catalogue des prospects (docs/plans/2026-11-24_prospects ; décisions de Xavier du 2026-11-24). Un catalogue pour
 * que les commerciaux s'organisent entre eux, sans ordres : chacun prend lui-même une fiche, personne ne l'attribue.
 * La liste informe, elle ne réserve rien : la commission reste réglée par le code tapé à l'inscription (C1).
 *
 * - P2 = A : un collègue voit le nom, la localité, le métier, qui suit et depuis quand ; ni téléphone, ni notes.
 * - Une fiche est tenue tant que celui qui la suit ne la lâche pas, sans plafond (Xavier, 2026-11-24, « pour
 *   l'instant » : remplace P3 = B, la libération 7 jours après un pas manqué, et le plafond de 30 fiches). Elle se
 *   libère aussi quand il quitte l'équipe.
 * - P6 = A : les notes suivent la fiche quand elle change de main.
 * - Seul celui qui tient la fiche y écrit. L'administrateur et le modérateur lisent tout.
 *
 * @phpstan-import-type Fiche from DepotProspects
 * @phpstan-import-type Champs from DepotProspects
 */
final class Prospects
{
    public const ETATS = ['a_contacter' => 'À contacter', 'contacte' => 'Contacté', 'rendez_vous' => 'Rendez-vous', 'plus_tard' => 'Plus tard', 'pas_interesse' => 'Pas intéressé'];
    public const ORIGINES = ['salon' => 'Salon', 'grossiste' => 'Grossiste', 'recommande' => 'Recommandé', 'autre' => 'Autre'];
    /** Un prochain pas se date dans l'année : une borne contre la faute de frappe (« 2062 »), sans autre rôle. */
    public const JOURS_PAS = 365;
    public const NOTE_MAX = 1000;
    /** Les formes juridiques, retirées du nom pour rapprocher « Peinture Roux Sàrl » de « Peinture Roux ». */
    private const FORMES = ['sarl', 'sa', 'gmbh', 'ag', 'sagl', 'snc'];

    public function __construct(
        private readonly Base $base,
        private readonly DepotProspects $depot,
        private readonly Demos $demos,
        private readonly JournalAdmin $journal,
        private readonly ClockInterface $horloge,
    ) {
    }

    /** Le nom d'une entreprise, réduit à ce qui la distingue : sans accents, sans ponctuation, sans forme juridique. */
    public static function cleNom(string $nom): string
    {
        $mots = preg_split('/[^a-z0-9]+/', Recherche::normaliser($nom), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode(' ', array_values(array_filter($mots, static fn (string $m): bool => !in_array($m, self::FORMES, true))));
    }

    /** Un numéro de téléphone, réduit à ses chiffres à la façon suisse : « +41 79… » et « 0041 79… » valent « 079… ». */
    public static function cleTelephone(string $tel): string
    {
        $chiffres = (string) preg_replace('/\D/', '', $tel);
        if (str_starts_with($chiffres, '0041')) {
            return '0' . substr($chiffres, 4);
        }
        return str_starts_with($chiffres, '41') && strlen($chiffres) === 11 ? '0' . substr($chiffres, 2) : $chiffres;
    }

    /**
     * Un numéro IDE mis en forme : CHE-123.456.789. Seule la forme est contrôlée ; la clé de contrôle ne l'est pas.
     *
     * @return string|null null pour une saisie vide
     * @throws \InvalidArgumentException
     */
    public static function ide(string $saisi): ?string
    {
        $brut = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $saisi));
        if ($brut === '') {
            return null;
        }
        if (preg_match('/^CHE(\d{3})(\d{3})(\d{3})(TVA|MWST|IVA)?$/', $brut, $m) !== 1) {
            throw new \InvalidArgumentException("Vérifiez le numéro IDE. Exemple\u{00A0}: CHE-123.456.789.");
        }
        return "CHE-$m[1].$m[2].$m[3]";
    }

    /** Le jour à Zurich, AAAA-MM-JJ : les échéances se comptent en jours de calendrier. */
    public function aujourdhui(): string
    {
        return $this->maintenant()->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('Y-m-d');
    }

    /**
     * @param array<string, string> $saisie entreprise, localite, metier, metier_saisi, telephone, courriel, contact, origine, ide, pas_texte, pas_date
     * @param bool $prend celui qui saisit suit la fiche ; sinon elle naît libre
     * @param bool $confirme « C'est une autre entreprise » : la ressemblance est acceptée
     * @return int la fiche créée
     * @throws \InvalidArgumentException saisie refusée, avec la phrase à montrer
     * @throws DoublonProspect
     */
    public function creer(int $acteur, array $saisie, bool $prend, bool $confirme): int
    {
        $champs = self::champs($saisie);
        $pas = $this->pas($saisie['pas_texte'] ?? '', $saisie['pas_date'] ?? '', false);
        if ($pas !== null && !$prend) {
            throw new \InvalidArgumentException("Un prochain pas se pose sur un prospect que vous suivez\u{00A0}: cochez «\u{00A0}Je m'en occupe\u{00A0}», ou videz le prochain pas.");
        }
        $this->refuserDoublon($champs, null, $confirme);
        $maintenant = $this->maintenant();
        return $this->base->transaction(function () use ($champs, $pas, $prend, $acteur, $maintenant): int {
            $id = $this->depot->inserer($champs, $prend ? $acteur : null, $acteur, $maintenant);
            if ($pas !== null) {
                $this->depot->poserPas($id, $pas[0], $pas[1], $maintenant);
            }
            return $id;
        });
    }

    /**
     * @param array<string, string> $saisie
     * @throws \InvalidArgumentException
     * @throws DoublonProspect
     * @throws \DomainException l'acteur ne tient pas cette fiche
     */
    public function modifier(int $acteur, int $id, array $saisie, bool $confirme): void
    {
        $this->tenuePar($acteur, $id);
        $champs = self::champs($saisie);
        $this->refuserDoublon($champs, $id, $confirme);
        $this->depot->modifier($id, $champs, $this->maintenant());
    }

    /**
     * @return Fiche
     * @throws \DomainException fiche inconnue
     */
    public function fiche(int $id): array
    {
        return $this->depot->fiche($id) ?? throw new \DomainException('Fiche inconnue.');
    }

    /**
     * Ce qu'un lecteur voit d'une fiche. Entière pour celui qui la tient, pour qui lit tout, et quand elle est libre ;
     * réduite pour un collègue (P2 = A) : rien d'autre ne sort d'ici.
     *
     * @param Fiche $f
     * @return array{entiere: bool, ecrit: bool, fiche: array<string, mixed>}
     */
    public function vue(int $acteur, bool $toutVoir, array $f): array
    {
        $aujourdhui = $this->aujourdhui();
        $tenue = $f['tenue'] === 1;
        $ecrit = $tenue && $f['suivi_par'] === $acteur;
        $entiere = $toutVoir || $ecrit || !$tenue;
        $commun = [
            'compte' => self::sorteDeCompte($f),
            'a_moi' => $ecrit,
            // Libre parce que son suiveur a quitté l'équipe : il reste nommé, sans date. Lâchée : qui, et depuis quand.
            'avant' => $tenue ? null : ($f['suivi_par'] !== null ? $f['suiveur'] : $f['avant']),
            'libre_le' => $tenue || $f['suivi_par'] !== null || $f['libre_depuis'] === null ? null : self::jourDeZurich($f['libre_depuis']),
        ];
        if (!$entiere) {
            return ['entiere' => false, 'ecrit' => false, 'fiche' => $commun + [
                'id' => $f['id'], 'entreprise' => $f['entreprise'], 'localite' => $f['localite'], 'metier' => $f['metier'], 'metier_saisi' => $f['metier_saisi'],
                'suiveur' => $f['suiveur'], 'suivi_depuis' => $f['suivi_depuis'], 'derniere_action_le' => $f['derniere_action_le'], 'tenue' => 1,
            ]];
        }
        return ['entiere' => true, 'ecrit' => $ecrit, 'fiche' => $commun + $f + [
            'retard' => $f['pas_le'] !== null && $f['pas_le'] < $aujourdhui,
            'pas_aujourdhui' => $f['pas_le'] === $aujourdhui,
        ]];
    }

    /**
     * @param string $vue miens, libres, tous, ou retard (réservé à qui lit tout)
     * @return array{vue: string, fiches: list<array{entiere: bool, ecrit: bool, fiche: array<string, mixed>}>, nombres: array{miens: int, libres: int, tous: int, retard: int}}
     */
    public function liste(int $acteur, bool $toutVoir, string $vue, string $recherche): array
    {
        $vue = in_array($vue, ['miens', 'libres', 'tous'], true) || ($vue === 'retard' && $toutVoir) ? $vue : 'miens';
        $aujourdhui = $this->aujourdhui();
        $fiches = [];
        foreach ($this->depot->lister($vue, $acteur, self::mots($recherche), $aujourdhui) as $f) {
            $fiches[] = $this->vue($acteur, $toutVoir, $f);
        }
        return ['vue' => $vue, 'fiches' => $fiches, 'nombres' => $this->depot->compter($acteur, $aujourdhui)];
    }

    /**
     * Les notes d'une fiche, pour qui la voit entière ; aucune pour un collègue.
     *
     * @return list<array{texte: string, cree_le: string, auteur: string|null}>
     */
    public function notes(int $acteur, bool $toutVoir, int $id): array
    {
        return $this->vue($acteur, $toutVoir, $this->fiche($id))['entiere'] ? $this->depot->notes($id) : [];
    }

    /**
     * Chercher avant de créer : les fiches et les comptes dont le nom contient la recherche. Trois lettres au moins.
     *
     * @return array{fiches: list<array{entiere: bool, ecrit: bool, fiche: array<string, mixed>}>, comptes: list<array{id: int, entreprise: string, demo: int, offres_max: int|null, apporteur: string|null}>}
     */
    public function chercher(int $acteur, bool $toutVoir, string $recherche): array
    {
        // Un mot de trois lettres au moins : des lettres isolées (« a e i ») feraient l'inventaire des comptes des
        // collègues (revue du 2026-11-24, S2). Les comptes ne se cherchent que par ces mots-là.
        $mots = self::mots($recherche);
        $longs = array_values(array_filter($mots, static fn (string $m): bool => mb_strlen($m) >= 3));
        if ($longs === []) {
            return ['fiches' => [], 'comptes' => []];
        }
        $fiches = [];
        foreach (array_slice($this->depot->lister('tous', $acteur, $mots, $this->aujourdhui()), 0, 20) as $f) {
            $fiches[] = $this->vue($acteur, $toutVoir, $f);
        }
        return ['fiches' => $fiches, 'comptes' => $this->depot->chercherComptes($longs)];
    }

    /**
     * Les mots d'une recherche, réduits comme le nom d'une fiche : « Roux pein » trouve « Peinture Roux Sàrl ».
     *
     * @return list<string> quatre au plus
     */
    private static function mots(string $recherche): array
    {
        $cle = self::cleNom(mb_substr($recherche, 0, 80));
        return $cle === '' ? [] : array_slice(explode(' ', $cle), 0, 4);
    }

    /**
     * « Je prends » : une fiche que personne ne tient.
     *
     * @throws \DomainException fiche inconnue, ou tenue par quelqu'un
     */
    public function prendre(int $acteur, int $id): void
    {
        $fiche = $this->fiche($id);
        if ($fiche['tenue'] === 1) {
            throw new \DomainException('Cette fiche est déjà suivie.');
        }
        $maintenant = $this->maintenant();
        $this->base->transaction(function () use ($id, $acteur, $maintenant): void {
            if (!$this->depot->prendre($id, $acteur, $maintenant)) {
                throw new \DomainException('Cette fiche vient d\'être prise.');
            }
            $this->depot->noter($id, $acteur, 'Prospect pris.', $maintenant);
        });
    }

    /** @throws \DomainException */
    public function lacher(int $acteur, int $id): void
    {
        $fiche = $this->tenuePar($acteur, $id);
        if ($fiche['compte_id'] !== null) {
            throw new \DomainException('Une fiche liée à un compte ne se lâche pas.');
        }
        $maintenant = $this->maintenant();
        $this->base->transaction(function () use ($id, $acteur, $maintenant): void {
            $this->depot->noter($id, $acteur, 'Prospect lâché.', $maintenant);
            $this->depot->lacher($id, $acteur, $maintenant);
        });
    }

    /**
     * « Pas intéressé » classe la fiche : plus personne ne la tient, elle reste au catalogue.
     *
     * @throws \DomainException
     * @throws \InvalidArgumentException état inconnu
     */
    public function changerEtat(int $acteur, int $id, string $etat): void
    {
        $fiche = $this->tenuePar($acteur, $id);
        if (!isset(self::ETATS[$etat])) {
            throw new \InvalidArgumentException('Choisissez un état.');
        }
        $maintenant = $this->maintenant();
        $this->base->transaction(function () use ($id, $acteur, $etat, $fiche, $maintenant): void {
            $this->depot->changerEtat($id, $etat, $maintenant);
            if ($etat === 'pas_interesse' && $fiche['compte_id'] === null) {
                $this->depot->noter($id, $acteur, "Classé «\u{00A0}pas intéressé\u{00A0}».", $maintenant);
                $this->depot->lacher($id, $acteur, $maintenant);
            }
        });
    }

    /**
     * @param string $date AAAA-MM-JJ, d'aujourd'hui à dans un an
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function poserPas(int $acteur, int $id, string $texte, string $date): void
    {
        $this->tenuePar($acteur, $id);
        $pas = $this->pas($texte, $date, true) ?? throw new \LogicException('Pas attendu.');
        $this->depot->poserPas($id, $pas[0], $pas[1], $this->maintenant());
    }

    /**
     * « C'est fait » : le pas s'efface et laisse une note.
     *
     * @throws \DomainException
     */
    public function pasFait(int $acteur, int $id): void
    {
        $fiche = $this->tenuePar($acteur, $id);
        if ($fiche['pas_texte'] === null) {
            return;
        }
        $maintenant = $this->maintenant();
        $this->base->transaction(function () use ($id, $acteur, $fiche, $maintenant): void {
            $this->depot->noter($id, $acteur, "Fait\u{00A0}: {$fiche['pas_texte']}", $maintenant);
            $this->depot->poserPas($id, null, null, $maintenant);
        });
    }

    /**
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function noter(int $acteur, int $id, string $texte): void
    {
        $this->tenuePar($acteur, $id);
        $texte = trim(str_replace("\r\n", "\n", $texte));
        if ($texte === '') {
            throw new \InvalidArgumentException('Écrivez la note.');
        }
        if (mb_strlen($texte) > self::NOTE_MAX) {
            throw new \InvalidArgumentException('Cette note dépasse ' . self::NOTE_MAX . ' caractères. Raccourcissez-la.');
        }
        $this->depot->noter($id, $acteur, $texte, $this->maintenant());
    }

    /**
     * « Créer une démo » depuis la fiche : l'entreprise, le métier et le courriel sont repris, le compte créé reste
     * lié à la fiche, dont l'état se lit ensuite sur ce compte.
     *
     * @return int le compte de la démo
     * @throws \DomainException l'acteur ne tient pas la fiche, ou elle a déjà un compte
     * @throws \InvalidArgumentException métier manquant, courriel refusé
     * @throws \OverflowException limite de démos du commercial atteinte
     */
    public function creerDemo(int $acteur, Role $role, int $id): int
    {
        $fiche = $this->tenuePar($acteur, $id);
        if ($fiche['compte_id'] !== null) {
            throw new \DomainException('Cette fiche a déjà un compte.');
        }
        if ($fiche['metier'] === null) {
            throw new \InvalidArgumentException("Indiquez d'abord le métier sur la fiche\u{00A0}: la démo en reçoit le catalogue.");
        }
        $maintenant = $this->maintenant();
        return $this->base->transaction(function () use ($fiche, $acteur, $role, $id, $maintenant): int {
            $compte = $this->demos->creer(new PorteeEquipe($acteur, $role !== Role::Commercial), $role, $fiche['metier'], $fiche['metier_saisi'] ?? '', $fiche['entreprise'], $fiche['courriel'] ?? '');
            // Deux envois rapprochés : le second ne lie rien, sa démo s'annule avec la transaction (revue, C2).
            if (!$this->depot->lierCompte($id, $compte, $maintenant)) {
                throw new \DomainException('Cette fiche a déjà un compte.');
            }
            $this->depot->noter($id, $acteur, 'Démo créée.', $maintenant);
            return $compte;
        });
    }

    /**
     * Deux fiches d'un même artisan : la première reste, la seconde lui donne ses notes et ce qui lui manquait.
     * Réservé à l'administrateur (la route le garantit). La fusion ne retire rien à personne (revue du 2026-11-24, C1) :
     * deux fiches suivies par deux personnes ne se fusionnent pas ; une fiche libre qui absorbe une fiche tenue passe à
     * celui qui tenait l'autre.
     *
     * @throws \DomainException fiche inconnue, ou la même deux fois
     * @throws \InvalidArgumentException fusion refusée, avec la phrase à montrer
     */
    public function fusionner(int $acteur, int $garde, int $absorbee): void
    {
        if ($garde === $absorbee) {
            throw new \DomainException('Une fiche ne se fusionne pas avec elle-même.');
        }
        $a = $this->fiche($garde);
        $b = $this->fiche($absorbee);
        if ($a['compte_id'] !== null && $b['compte_id'] !== null) {
            throw new \InvalidArgumentException('Ces deux fiches ont chacune un compte : elles restent séparées.');
        }
        [$tenueA, $tenueB] = [$a['tenue'] === 1, $b['tenue'] === 1];
        if ($tenueA && $tenueB && $a['suivi_par'] !== $b['suivi_par']) {
            throw new \InvalidArgumentException("Ces deux fiches sont suivies par deux personnes, {$a['suiveur']} et {$b['suiveur']}. L'une des deux lâche la sienne, puis la fusion se fait.");
        }
        if ($tenueA && $b['compte_id'] !== null && $a['suivi_par'] !== $b['suivi_par']) {
            throw new \InvalidArgumentException("La fiche à absorber a un compte, et {$a['suiveur']} suit l'autre. Gardez plutôt la fiche qui a le compte.");
        }
        $reprendre = !$tenueA && $tenueB;
        $maintenant = $this->maintenant();
        $this->base->transaction(function () use ($acteur, $a, $b, $reprendre, $maintenant): void {
            $this->depot->fusionner($a['id'], $b['id'], $reprendre);
            $this->depot->noter($a['id'], $acteur, "Fusionnée avec la fiche «\u{00A0}{$b['entreprise']}\u{00A0}», {$b['localite']}.", $maintenant, false);
            // journal_admin.detail : 190 caractères.
            $this->journal->noter($acteur, null, 'prospect_fusionne', mb_substr("{$b['entreprise']} → {$a['entreprise']}", 0, 190), $maintenant);
        });
    }

    /**
     * La fiche, si l'acteur la tient aujourd'hui.
     *
     * @return Fiche
     * @throws \DomainException
     */
    private function tenuePar(int $acteur, int $id): array
    {
        $fiche = $this->fiche($id);
        if ($fiche['tenue'] !== 1 || $fiche['suivi_par'] !== $acteur) {
            throw new \DomainException('Vous ne suivez pas cette fiche.');
        }
        return $fiche;
    }

    /**
     * @param Champs $champs
     * @throws DoublonProspect
     */
    private function refuserDoublon(array $champs, ?int $sauf, bool $confirme): void
    {
        $fiches = $this->depot->ressemblants($champs, $sauf);
        $comptes = $this->depot->comptesRessemblants($champs['entreprise']);
        $ferme = in_array('ide', array_column($fiches, 'motif'), true);
        if ($ferme || (!$confirme && ($fiches !== [] || $comptes !== []))) {
            throw new DoublonProspect($fiches, $comptes, $ferme);
        }
    }

    /**
     * La saisie d'une fiche, contrôlée et mise en forme.
     *
     * @param array<string, string> $s
     * @return Champs
     * @throws \InvalidArgumentException
     */
    private static function champs(array $s): array
    {
        $entreprise = mb_substr(trim($s['entreprise'] ?? ''), 0, 120);
        if ($entreprise === '' || self::cleNom($entreprise) === '') {
            throw new \InvalidArgumentException("Indiquez le nom de l'entreprise.");
        }
        $lieu = trim((string) preg_replace('/\s+/u', ' ', $s['localite'] ?? ''));
        $npa = null;
        if (preg_match('/^(\d{4})\s+(.+)$/u', $lieu, $m) === 1) {
            [$npa, $lieu] = [$m[1], $m[2]];
        }
        if ($lieu === '' || mb_strlen($lieu) > 80) {
            throw new \InvalidArgumentException("Indiquez la localité. Exemple\u{00A0}: 2800 Delémont.");
        }
        $metier = ($s['metier'] ?? '') === '' ? null : $s['metier'];
        if ($metier !== null && !isset(CatalogueFictif::libelles()[$metier])) {
            throw new \InvalidArgumentException('Choisissez un métier de la liste.');
        }
        $metierSaisi = $metier === 'autre' ? mb_substr(trim($s['metier_saisi'] ?? ''), 0, 60) : '';
        $telephone = trim($s['telephone'] ?? '') === '' ? null : DemandesAppel::telephone($s['telephone']);
        $courriel = trim($s['courriel'] ?? '') === '' ? null : Demos::courriel($s['courriel']);
        $origine = ($s['origine'] ?? '') === '' ? null : $s['origine'];
        if ($origine !== null && !isset(self::ORIGINES[$origine])) {
            throw new \InvalidArgumentException('Choisissez où vous avez rencontré ce prospect.');
        }
        $contact = mb_substr(trim($s['contact'] ?? ''), 0, 120);
        return [
            'entreprise' => $entreprise, 'nom_cle' => mb_substr(self::cleNom($entreprise), 0, 140), 'npa' => $npa, 'localite' => $lieu,
            'metier' => $metier, 'metier_saisi' => $metierSaisi === '' ? null : $metierSaisi,
            'telephone' => $telephone, 'telephone_cle' => $telephone === null ? null : mb_substr(self::cleTelephone($telephone), 0, 16),
            'courriel' => $courriel, 'contact' => $contact === '' ? null : $contact, 'origine' => $origine, 'ide' => self::ide($s['ide'] ?? ''),
        ];
    }

    /**
     * Un prochain pas : son texte et son jour, d'aujourd'hui à dans un an.
     *
     * @return array{string, string}|null null quand rien n'est saisi et que le pas n'est pas exigé
     * @throws \InvalidArgumentException
     */
    private function pas(string $texte, string $date, bool $exige): ?array
    {
        $texte = mb_substr(trim($texte), 0, 120);
        $date = trim($date);
        if ($texte === '' && $date === '' && !$exige) {
            return null;
        }
        if ($texte === '') {
            throw new \InvalidArgumentException('Écrivez le prochain pas. Exemple : rappeler.');
        }
        $jour = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $aujourdhui = new \DateTimeImmutable($this->aujourdhui());
        $limite = $aujourdhui->modify('+' . self::JOURS_PAS . ' days');
        if ($jour === false || $jour->format('Y-m-d') !== $date || $jour < $aujourdhui || $jour > $limite) {
            throw new \InvalidArgumentException("Choisissez une date entre aujourd'hui et le " . $limite->format('d.m.Y') . '.');
        }
        return [$texte, $date];
    }

    /** @param Fiche $f */
    private static function sorteDeCompte(array $f): ?string
    {
        if ($f['compte_id'] === null || $f['c_demo'] === null) {
            return null;
        }
        return $f['c_demo'] === 1 ? 'demo' : ($f['c_offres_max'] === null ? 'client' : 'essai');
    }

    /** Le jour à Zurich d'un moment de la base (UTC). */
    private static function jourDeZurich(string $utc): string
    {
        return Dates::depuisBase($utc)->setTimezone(new \DateTimeZone('Europe/Zurich'))->format('Y-m-d');
    }

    private function maintenant(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($this->horloge->now());
    }
}
