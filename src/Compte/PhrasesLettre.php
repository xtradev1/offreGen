<?php

declare(strict_types=1);

namespace OffreGen\Compte;

/**
 * Les phrases du document, réglées par l'artisan dans « Ma marque ». Le nom vient du premier réglage, celui du modèle
 * Lettre (maquette MAQUETTE-lettre-phrases.html, réponses de Xavier du 2026-11-20) : L2, la phrase de l'artisan, puis
 * la visite, le chantier ou le numéro de l'offre ; L3, formule d'appel la même pour tous ou selon le client ; L5, début
 * et fin pour l'offre et pour la facture ; L6, trois tournures par phrase, la première étant celle d'avant ce réglage.
 *
 * Puis les phrases que le document tire de l'offre (MAQUETTE-lettre-phrases-auto.html, réponses du 2026-11-23) : M1 A,
 * l'artisan réécrit la phrase entière, des pastilles entre crochets y écrivent la donnée ; M2 A, une phrase vidée
 * reprend celle de l'application ; M4 B, dans les douze modèles, et la formule d'appel, le début et la fin aussi, au
 * choix de l'artisan (partout) ; M5 A, la phrase d'une facture annulée reste fixe (le gabarit l'écrit lui-même).
 *
 * Une phrase vide, ou égale à celle de l'application, n'est pas recopiée : elle suivra si celle-ci change.
 */
final readonly class PhrasesLettre
{
    public const LONGUEUR = 500;

    /**
     * Les phrases tirées de l'offre ou de la facture, dans l'ordre de l'écran. pastilles : les données que la phrase peut
     * écrire, en minuscules ; requises : celles sans lesquelles la phrase ne paraît pas, et que l'artisan doit garder.
     * Le nom et la phrase d'origine suivent l'activité du compte (nom(), origine()).
     */
    public const DOCUMENT = [
        'visite' => ['doc' => 'offre', 'nom' => 'Visite', 'quand' => "Si l'offre a une date de visite. Au début, après votre phrase.", 'pastilles' => ['date'], 'requises' => ['date']],
        'chantier' => ['doc' => 'offre', 'nom' => 'Chantier', 'quand' => "Si l'offre a une adresse. Au début de la Lettre ; les autres modèles l'écrivent dans leur case.", 'pastilles' => ['adresse'], 'requises' => ['adresse']],
        'debut' => ['doc' => 'offre', 'nom' => 'Début des travaux', 'quand' => "Si l'offre le dit. Dans les conditions.", 'pastilles' => ['début'], 'requises' => ['début']],
        'duree' => ['doc' => 'offre', 'nom' => 'Durée', 'quand' => "Si l'offre la dit. Dans les conditions.", 'pastilles' => ['durée'], 'requises' => ['durée']],
        'paiement' => ['doc' => 'offre', 'nom' => 'Paiement', 'quand' => "Toujours. La pastille écrit vos conditions de paiement, escompte compris.", 'pastilles' => ['paiement'], 'requises' => ['paiement']],
        'validite' => ['doc' => 'offre', 'nom' => 'Validité', 'quand' => 'Toujours.', 'pastilles' => ['date'], 'requises' => ['date']],
        'prix_tva' => ['doc' => 'offre', 'nom' => 'Prix, avec TVA', 'quand' => "Si l'offre compte la TVA.", 'pastilles' => [], 'requises' => []],
        'prix_sans' => ['doc' => 'offre', 'nom' => 'Prix, sans TVA', 'quand' => "Si l'offre ne compte pas de TVA.", 'pastilles' => [], 'requises' => []],
        'non_compris' => ['doc' => 'offre', 'nom' => 'Non compris', 'quand' => "Si l'offre dit ce qui n'est pas compris. Les autres modèles que Lettre gardent leur liste tant que vous ne réécrivez pas cette phrase.",
            'pastilles' => ["n'est pas ou ne sont pas", 'liste'], 'requises' => ['liste']],
        'reference' => ['doc' => 'facture', 'nom' => "Offre d'origine", 'quand' => "Toujours. Au début de la Lettre ; les autres modèles l'écrivent dans leur case.", 'pastilles' => ["n° de l'offre", "date de l'offre"], 'requises' => ["n° de l'offre"]],
        'payable' => ['doc' => 'facture', 'nom' => 'Échéance', 'quand' => "Toujours, sauf sur une facture annulée. La seconde pastille est vide sans section de paiement.", 'pastilles' => ['échéance', 'section de paiement'], 'requises' => ['échéance']],
        'merci' => ['doc' => 'facture', 'nom' => 'Remerciement', 'quand' => "Après l'échéance, quand le document n'a pas de phrase de fin.", 'pastilles' => [], 'requises' => []],
        'montants_tva' => ['doc' => 'facture', 'nom' => 'Montants, avec TVA', 'quand' => 'Si la facture compte la TVA.', 'pastilles' => [], 'requises' => []],
        'montants_sans' => ['doc' => 'facture', 'nom' => 'Montants, sans TVA', 'quand' => 'Si la facture ne compte pas de TVA.', 'pastilles' => [], 'requises' => []],
    ];

    /** Les phrases d'avant le réglage, et celles d'un compte qui n'a rien réglé. */
    public const ACTUELLES = [
        'offre_debut' => 'Nous vous remettons notre offre pour les travaux suivants.',
        'offre_fin' => 'Nous restons à votre disposition pour toute question et vous adressons, Madame, Monsieur, nos salutations les meilleures.',
        'facture_debut' => 'Nous vous adressons notre facture pour les travaux suivants.',
        'facture_fin' => 'Nous vous remercions de votre confiance et vous adressons, Madame, Monsieur, nos salutations les meilleures.',
    ];

    /** Trois tournures par phrase, proposées d'un toucher (L6, validées par Xavier le 2026-11-20). */
    public const TOURNURES = [
        'offre_debut' => [self::ACTUELLES['offre_debut'],
            'Nous avons le plaisir de vous soumettre notre offre pour les travaux suivants.',
            'Comme convenu, voici notre offre pour les travaux suivants.'],
        'offre_fin' => [self::ACTUELLES['offre_fin'],
            "Dans l'attente de votre réponse, nous vous prions d'agréer, Madame, Monsieur, nos salutations distinguées.",
            'Nous nous réjouissons de travailler pour vous et restons à votre disposition. Avec nos meilleures salutations.'],
        'facture_debut' => [self::ACTUELLES['facture_debut'],
            'Les travaux étant terminés, voici notre facture.',
            'Nous vous remercions de nous avoir confié ces travaux et vous adressons notre facture.'],
        'facture_fin' => [self::ACTUELLES['facture_fin'],
            'Merci de votre confiance. Nous restons à votre disposition pour tout futur projet.',
            'Avec nos remerciements et nos meilleures salutations.'],
    ];

    /** Les noms des phrases dans le formulaire. */
    private const CHAMPS = ['offre_debut' => 'lettre_offre_debut', 'offre_fin' => 'lettre_offre_fin', 'facture_debut' => 'lettre_facture_debut', 'facture_fin' => 'lettre_facture_fin'];

    private const APPEL = 'Madame, Monsieur';

    /**
     * @param array<string, string> $reecrites les phrases de DOCUMENT que l'artisan a réécrites, par clé
     * @param bool $partout la formule d'appel, le début et la fin dans les douze modèles, pas seulement dans Lettre
     */
    public function __construct(
        public bool $appelSelonClient = false,
        public string $offreDebut = '',
        public string $offreFin = '',
        public string $factureDebut = '',
        public string $factureFin = '',
        public array $reecrites = [],
        public bool $partout = false,
    ) {
    }

    /** Le nom de la phrase à l'écran, dans les mots de l'activité. */
    public static function nomPhrase(string $cle, Activite $activite = Activite::Batiment): string
    {
        $m = $activite->mots();
        return match ($cle) {
            'visite' => $m['date'],
            'chantier' => $m['doc_lieu'],
            'debut' => $m['debut'],
            default => self::DOCUMENT[$cle]['nom'],
        };
    }

    /** La phrase de l'application, pastilles comprises, dans les mots de l'activité. */
    public static function origine(string $cle, Activite $activite = Activite::Batiment): string
    {
        $m = $activite->mots();
        return $activite->dire(match ($cle) {
            'visite' => $m['lettre_date'] . ' [date].',
            'chantier' => $m['doc_lieu'] . ' : [adresse].',
            'debut' => $m['debut'] . ' : [début].',
            'duree' => 'Durée : [durée].',
            'paiement' => 'Paiement : [paiement].',
            'validite' => "Offre valable jusqu'au [date].",
            'prix_tva' => 'Prix en francs suisses, TVA comprise dans le total.',
            'prix_sans' => 'Prix en francs suisses.',
            'non_compris' => "[n'est pas ou ne sont pas] compris : [liste].",
            'reference' => "Selon notre offre n° [n° de l'offre] du [date de l'offre].",
            'payable' => "Payable jusqu'au [échéance] [section de paiement].",
            'merci' => 'Merci de votre confiance.',
            'montants_tva' => 'Montants en francs suisses, TVA comprise dans le total.',
            'montants_sans' => 'Montants en francs suisses.',
            default => throw new \InvalidArgumentException("Phrase inconnue : $cle"),
        });
    }

    /** Vrai si l'artisan a réécrit cette phrase. */
    public function estReecrite(string $cle): bool
    {
        return isset($this->reecrites[$cle]);
    }

    /**
     * La phrase du document avec la donnée de l'offre à la place de chaque pastille ; null si une pastille requise est
     * vide, la phrase ne paraît alors pas. Le texte rendu est brut : le gabarit l'échappe (filtre « phrase »).
     *
     * @param array<string, string|null> $valeurs pastille (en minuscules) => donnée
     */
    public function ecrire(string $cle, array $valeurs = [], Activite $activite = Activite::Batiment): ?string
    {
        foreach (self::DOCUMENT[$cle]['requises'] as $requise) {
            if (($valeurs[$requise] ?? '') === '') {
                return null;
            }
        }
        $t = $this->reecrites[$cle] ?? self::origine($cle, $activite);
        $t = (string) preg_replace_callback('/\[([^\]]*)\]/u', static function (array $m) use ($valeurs): string {
            $nom = mb_strtolower(trim($m[1]));
            return array_key_exists($nom, $valeurs) ? (string) $valeurs[$nom] : $m[0];
        }, $t);
        // Une pastille vide ne laisse ni double espace ni espace avant la ponctuation.
        $t = trim((string) preg_replace(['/ {2,}/u', '/ +([.,])/u'], [' ', '$1'], $t));
        $t = (string) preg_replace(['/ ([:;!?»])/u', '/« /u', '/\bn° /u'], ["\u{00A0}$1", "«\u{00A0}", "n°\u{00A0}"], $t);
        return mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }

    /**
     * Les deux pastilles de « Non compris » : l'accord, et la liste séparée par des points-virgules.
     *
     * @param list<string> $elements
     * @return array{"n'est pas ou ne sont pas": string, liste: string}
     */
    public static function nonCompris(array $elements): array
    {
        $elements = array_values(array_filter(array_map(static fn (string $x): string => rtrim(trim($x), '.'), $elements), static fn (string $x): bool => $x !== ''));
        return ["n'est pas ou ne sont pas" => count($elements) > 1 ? 'ne sont pas' : "n'est pas", 'liste' => implode(' ; ', $elements)];
    }

    /** Les phrases réécrites, pour la colonne artisan.phrases_document ; null s'il n'y en a pas. */
    public function reecritesJson(): ?string
    {
        return $this->reecrites === [] ? null : json_encode($this->reecrites, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Les phrases réécrites lues en base : une clé inconnue ou une valeur qui n'est pas un texte est laissée de côté.
     *
     * @return array<string, string>
     */
    public static function reecritesDepuisJson(?string $json): array
    {
        $lu = $json === null ? null : json_decode($json, true);
        if (!is_array($lu)) {
            return [];
        }
        $phrases = [];
        foreach (array_keys(self::DOCUMENT) as $cle) {
            if (isset($lu[$cle]) && is_string($lu[$cle]) && $lu[$cle] !== '') {
                $phrases[$cle] = $lu[$cle];
            }
        }
        return $phrases;
    }

    /**
     * La formule d'appel : « Madame, Monsieur, », ou selon le nom du client (« Madame, », « Monsieur, »). Le client est
     * null tant que l'offre n'en a pas (client à choisir).
     */
    public function appel(?string $client): string
    {
        return $this->nom($client) . ',';
    }

    /**
     * La phrase réglée (ou l'actuelle), avec ses espaces insécables. Selon le client, « Madame, Monsieur » y devient la
     * formule du client : la fin de la lettre suit la formule d'appel.
     *
     * @param 'offre_debut'|'offre_fin'|'facture_debut'|'facture_fin' $cle
     * @param Activite $activite celle du compte : elle donne ses mots à la phrase actuelle
     */
    public function texte(string $cle, ?string $client, Activite $activite = Activite::Batiment): string
    {
        $reglee = match ($cle) {
            'offre_debut' => $this->offreDebut,
            'offre_fin' => $this->offreFin,
            'facture_debut' => $this->factureDebut,
            'facture_fin' => $this->factureFin,
        };
        // La phrase de l'application suit l'activité du compte (« travaux » ou « prestations ») ; celle de l'artisan reste la sienne.
        $t = $reglee === '' ? $activite->dire(self::ACTUELLES[$cle]) : $reglee;
        $t = $this->appelSelonClient ? str_replace(self::APPEL, $this->nom($client), $t) : $t;
        return (string) preg_replace(['/ ([:;!?»])/u', '/« /u'], ["\u{00A0}$1", "«\u{00A0}"], $t);
    }

    /**
     * Les champs de « Ma marque » : lettre_appel (« client », sinon la même pour tous), lettre_offre_debut,
     * lettre_offre_fin, lettre_facture_debut, lettre_facture_fin. Un champ absent ou vide vaut la phrase actuelle.
     *
     * @param array<string, string> $champs
     * @return array{0: self|null, 1: array<string, string>} les phrases, ou les erreurs par champ
     */
    public static function depuisSaisie(array $champs, Activite $activite = Activite::Batiment): array
    {
        $erreurs = [];
        $textes = ['offre_debut' => '', 'offre_fin' => '', 'facture_debut' => '', 'facture_fin' => ''];
        foreach (self::CHAMPS as $cle => $champ) {
            $saisi = $champs[$champ] ?? '';
            if (!mb_check_encoding($saisi, 'UTF-8')) {
                $erreurs[$champ] = 'Ce texte contient des caractères illisibles.';
                continue;
            }
            $t = trim((string) preg_replace('/\s+/u', ' ', $saisi));
            if (mb_strlen($t) > self::LONGUEUR) {
                $erreurs[$champ] = self::LONGUEUR . ' caractères au plus.';
            }
            // La phrase actuelle, telle que l'écran la montre à cette activité, n'est pas recopiée.
            $textes[$cle] = $t === $activite->dire(self::ACTUELLES[$cle]) ? '' : $t;
        }
        // Les phrases tirées de l'offre : champ phrase_<clé>.
        $reecrites = [];
        foreach (self::DOCUMENT as $cle => $p) {
            $champ = 'phrase_' . $cle;
            $saisi = $champs[$champ] ?? '';
            if (!mb_check_encoding($saisi, 'UTF-8')) {
                $erreurs[$champ] = 'Ce texte contient des caractères illisibles.';
                continue;
            }
            $t = trim((string) preg_replace('/\s+/u', ' ', $saisi));
            if ($t === '' || $t === self::origine($cle, $activite)) {
                continue;
            }
            $erreur = self::erreurPastilles($t, $p['pastilles'], $p['requises']);
            if (mb_strlen($t) > self::LONGUEUR) {
                $erreur = self::LONGUEUR . ' caractères au plus.';
            }
            if ($erreur !== null) {
                $erreurs[$champ] = $erreur;
                continue;
            }
            $reecrites[$cle] = $t;
        }
        if ($erreurs !== []) {
            return [null, $erreurs];
        }
        return [new self(($champs['lettre_appel'] ?? '') === 'client', $textes['offre_debut'], $textes['offre_fin'], $textes['facture_debut'], $textes['facture_fin'],
            $reecrites, ($champs['lettre_partout'] ?? '') === '1'), []];
    }

    /**
     * Une pastille que la phrase ne connaît pas, ou une pastille requise retirée : la donnée de l'offre se perdrait.
     *
     * @param list<string> $pastilles
     * @param list<string> $requises
     */
    private static function erreurPastilles(string $t, array $pastilles, array $requises): ?string
    {
        preg_match_all('/\[([^\]]*)\]/u', $t, $trouvees);
        $noms = array_map(static fn (string $x): string => mb_strtolower(trim($x)), $trouvees[1]);
        foreach ($noms as $i => $nom) {
            if (!in_array($nom, $pastilles, true)) {
                $connues = $pastilles === [] ? "Cette phrase n'en a pas."
                    : (count($pastilles) > 1 ? 'Celles' : 'Celle') . " de cette phrase\u{00A0}: " . implode(', ', array_map(static fn (string $x): string => "[$x]", $pastilles)) . '.';
                return "Pastille inconnue\u{00A0}: [" . trim($trouvees[1][$i]) . "]. $connues";
            }
        }
        foreach ($requises as $requise) {
            if (!in_array($requise, $noms, true)) {
                return "Gardez la pastille [$requise]\u{00A0}: c'est elle qui écrit la donnée de l'offre.";
            }
        }
        return null;
    }

    /** @return array<string, string> les champs de l'écran : chaque phrase telle que la lettre l'écrit, sans les insécables */
    public function versChamps(Activite $activite = Activite::Batiment): array
    {
        $actuelles = self::actuelles($activite);
        $champs = ['lettre_appel' => $this->appelSelonClient ? 'client' : 'fixe',
            'lettre_offre_debut' => $this->offreDebut === '' ? $actuelles['offre_debut'] : $this->offreDebut,
            'lettre_offre_fin' => $this->offreFin === '' ? $actuelles['offre_fin'] : $this->offreFin,
            'lettre_facture_debut' => $this->factureDebut === '' ? $actuelles['facture_debut'] : $this->factureDebut,
            'lettre_facture_fin' => $this->factureFin === '' ? $actuelles['facture_fin'] : $this->factureFin,
            'lettre_partout' => $this->partout ? '1' : '0'];
        foreach (array_keys(self::DOCUMENT) as $cle) {
            $champs['phrase_' . $cle] = $this->reecrites[$cle] ?? self::origine($cle, $activite);
        }
        return $champs;
    }

    /**
     * Les phrases actuelles dans les mots de l'activité (Activite::dire) : celles d'un compte qui n'a rien réglé.
     *
     * @return array{offre_debut: string, offre_fin: string, facture_debut: string, facture_fin: string}
     */
    public static function actuelles(Activite $activite): array
    {
        return ['offre_debut' => $activite->dire(self::ACTUELLES['offre_debut']), 'offre_fin' => $activite->dire(self::ACTUELLES['offre_fin']),
            'facture_debut' => $activite->dire(self::ACTUELLES['facture_debut']), 'facture_fin' => $activite->dire(self::ACTUELLES['facture_fin'])];
    }

    /**
     * Les tournures proposées, dans les mots de l'activité.
     *
     * @return array<string, list<string>>
     */
    public static function tournures(Activite $activite): array
    {
        return array_map(static fn (array $phrases): array => array_map($activite->dire(...), $phrases), self::TOURNURES);
    }

    /** « Madame », « Monsieur », ou « Madame, Monsieur » : selon le début du nom du client, si l'artisan l'a choisi. */
    private function nom(?string $client): string
    {
        if (!$this->appelSelonClient) {
            return self::APPEL;
        }
        $c = trim($client ?? '');
        return match (true) {
            preg_match('/^(Madame|Mme)(\s|$)/u', $c) === 1 => 'Madame',
            preg_match('/^(Monsieur|M\.)(\s|$)/u', $c) === 1 => 'Monsieur',
            default => self::APPEL,
        };
    }
}
