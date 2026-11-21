<?php

declare(strict_types=1);

namespace OffreGen\Compte;

/**
 * Les phrases du modèle Lettre, réglées par l'artisan dans « Ma marque » (maquette MAQUETTE-lettre-phrases.html du
 * chantier des modèles, réponses de Xavier du 2026-11-20). L1 : sous le modèle Lettre. L2 : la phrase de l'artisan ;
 * la visite, le chantier et le numéro de l'offre sont ajoutés après elle par le gabarit. L3 : formule d'appel la même
 * pour tous ou selon le client, au choix. L5 : l'offre et la facture ont chacune leur début et leur fin. L6 : trois
 * tournures proposées par phrase, la première étant celle d'avant ce réglage.
 *
 * Une phrase vide vaut la phrase actuelle : elle n'est pas recopiée, elle suivra si elle change.
 */
final readonly class PhrasesLettre
{
    public const LONGUEUR = 500;

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

    public function __construct(
        public bool $appelSelonClient = false,
        public string $offreDebut = '',
        public string $offreFin = '',
        public string $factureDebut = '',
        public string $factureFin = '',
    ) {
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
     */
    public function texte(string $cle, ?string $client): string
    {
        $reglee = match ($cle) {
            'offre_debut' => $this->offreDebut,
            'offre_fin' => $this->offreFin,
            'facture_debut' => $this->factureDebut,
            'facture_fin' => $this->factureFin,
        };
        $t = $reglee === '' ? self::ACTUELLES[$cle] : $reglee;
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
    public static function depuisSaisie(array $champs): array
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
            $textes[$cle] = $t === self::ACTUELLES[$cle] ? '' : $t;
        }
        if ($erreurs !== []) {
            return [null, $erreurs];
        }
        return [new self(($champs['lettre_appel'] ?? '') === 'client', $textes['offre_debut'], $textes['offre_fin'], $textes['facture_debut'], $textes['facture_fin']), []];
    }

    /** @return array<string, string> les champs de l'écran : chaque phrase telle que la lettre l'écrit, sans les insécables */
    public function versChamps(): array
    {
        return ['lettre_appel' => $this->appelSelonClient ? 'client' : 'fixe',
            'lettre_offre_debut' => $this->offreDebut === '' ? self::ACTUELLES['offre_debut'] : $this->offreDebut,
            'lettre_offre_fin' => $this->offreFin === '' ? self::ACTUELLES['offre_fin'] : $this->offreFin,
            'lettre_facture_debut' => $this->factureDebut === '' ? self::ACTUELLES['facture_debut'] : $this->factureDebut,
            'lettre_facture_fin' => $this->factureFin === '' ? self::ACTUELLES['facture_fin'] : $this->factureFin];
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
