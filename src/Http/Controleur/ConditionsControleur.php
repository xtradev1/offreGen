<?php

declare(strict_types=1);

namespace OffreGen\Http\Controleur;

use OffreGen\Compte\Conditions;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Http\Conteneur;
use OffreGen\Http\Flash;
use OffreGen\Http\PageIntrouvable;
use OffreGen\Http\Parametres;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ce qui est propre à une offre en dehors de ses lignes (D5 A, D6 A, D16 A, 2026-11-21) : ses conditions (validité,
 * paiement, escompte), remplies avec celles du compte, et ses textes avant et après les lignes. Changées ici, elles ne
 * valent que pour cette offre (D7 B : pas de « pour toutes mes offres »).
 *
 * @phpstan-import-type Offre from DepotOffres
 */
final class ConditionsControleur
{
    /** Les champs du formulaire des conditions, ceux de Réglages. */
    private const CHAMPS = ['validite_choix', 'validite_jours', 'paiement_type', 'paiement_jours_choix', 'paiement_jours',
        'paiement_acomptes_choix', 'paiement_acomptes', 'escompte_pourcent', 'escompte_jours'];

    public const TEXTE_MAX = 1000;

    public function __construct(private readonly Conteneur $c)
    {
    }

    /** @param array<string, string> $parametres */
    public function fenetre(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $r = $this->c->reglages($this->portee($requete))->lire()->pourOffre($offre['conditions']);
        return $this->formulaire($offre, $r->conditions()->versChamps(), [], 200, $r->conditionsPaiement());
    }

    /** @param array<string, string> $parametres */
    public function enregistrer(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $champs = [];
        foreach (self::CHAMPS as $nom) {
            $champs[$nom] = $requete->request->getString($nom);
        }
        [$conditions, $erreurs] = Conditions::depuisSaisie($champs);
        if ($conditions === null) {
            return $this->formulaire($offre, $champs, $erreurs, 422, '');
        }
        // Rien de changé : rien n'est écrit (l'offre continue de suivre le compte, et ne se dit pas modifiée depuis l'envoi).
        if ($conditions == $this->c->reglages($this->portee($requete))->lire()->pourOffre($offre['conditions'])->conditions()) {
            return new RedirectResponse('/offres/' . $offre['id'], 303);
        }
        $this->offres($requete)->changerConditions($offre['id'], $conditions, $this->c->horloge()->now());
        Flash::dire($requete, 'info', 'Conditions de l\'offre enregistrées');
        return new RedirectResponse('/offres/' . $offre['id'], 303);
    }

    /**
     * Le texte avant ou après les lignes. Envoyé seul quand le champ change (app.js), ou par son bouton sans JavaScript.
     *
     * @param array<string, string> $parametres
     */
    public function texte(Request $requete, array $parametres): Response
    {
        $offre = $this->offre($requete, $parametres);
        $ou = $requete->request->getString('ou');
        if ($ou !== 'avant' && $ou !== 'apres') {
            throw new PageIntrouvable();
        }
        $texte = self::texteLu($requete->request->getString('texte'));
        if ($texte === null) {
            // 422 : l'envoi automatique (app.js) affiche « Non enregistré » ; le champ limite déjà la saisie à 1000.
            return new Response('Texte de ' . self::TEXTE_MAX . " caractères au plus\u{00A0}: il n'est pas enregistré.", 422);
        }
        $this->offres($requete)->changerTexte($offre['id'], $ou, $texte, $this->c->horloge()->now());
        return new RedirectResponse('/offres/' . $offre['id'], 303);
    }

    /** Fins de ligne unifiées, espaces des bords retirés, trois lignes vides au plus ; null s'il est trop long. */
    public static function texteLu(string $saisie): ?string
    {
        $t = trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", $saisie)));
        return mb_strlen($t) > self::TEXTE_MAX ? null : $t;
    }

    /**
     * @param Offre $offre
     * @param array<string, string> $champs
     * @param array<string, string> $erreurs
     */
    private function formulaire(array $offre, array $champs, array $erreurs, int $code, string $phrase): Response
    {
        return new Response($this->c->twig()->render('conditions_offre.html.twig', [
            'offre' => $offre, 'champs' => $champs, 'erreurs' => $erreurs, 'phrase' => $phrase,
            'validites' => Conditions::VALIDITES_PROPOSEES, 'types' => Conditions::TYPES_PAIEMENT, 'delais' => Conditions::DELAIS_PROPOSES,
            'acomptes' => array_map(static fn (array $a): string => implode(',', $a), Conditions::ACOMPTES_PROPOSES),
        ]), $code);
    }

    private function portee(Request $requete): Portee
    {
        return new Portee($requete->attributes->getInt('_artisan'));
    }

    private function offres(Request $requete): DepotOffres
    {
        return $this->c->offres($this->portee($requete));
    }

    /**
     * @param array<string, string> $parametres
     * @return Offre
     */
    private function offre(Request $requete, array $parametres): array
    {
        return $this->offres($requete)->parId(Parametres::id($parametres, 'id')) ?? throw new PageIntrouvable();
    }
}
