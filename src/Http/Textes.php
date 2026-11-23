<?php

declare(strict_types=1);

namespace OffreGen\Http;

/**
 * Les textes de l'onboarding (chantier 2026-11-21_onboarding, PLAN.md, lot 1bis), validés par Xavier dans la page des
 * textes puis versés dans config/textes-onboarding.json, insécables comprises (décision O20 : la page des textes,
 * puis le code).
 *
 * Un texte peut porter du gras (<b>) : il vient du dépôt, jamais d'une saisie, et sort tel quel. Ses variables
 * ({prenom}, {jours}) sont échappées. Un identifiant inconnu est une faute de gabarit : exception.
 */
final class Textes
{
    /** @var array<string, string>|null */
    private ?array $textes = null;

    public function __construct(private readonly string $fichier)
    {
    }

    /** @param array<string, string|int> $variables */
    public function texte(string $id, array $variables = []): string
    {
        $texte = $this->tous()[$id] ?? throw new \OutOfBoundsException("Texte inconnu : $id");
        $remplacements = [];
        foreach ($variables as $cle => $valeur) {
            $remplacements['{' . $cle . '}'] = htmlspecialchars((string) $valeur, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return strtr($texte, $remplacements);
    }

    /** @return array<string, string> */
    public function tous(): array
    {
        if ($this->textes === null) {
            $brut = json_decode((string) file_get_contents($this->fichier), true, 4, JSON_THROW_ON_ERROR);
            $this->textes = [];
            /** @var list<array{id: string, texte: string}> $brut */
            foreach ($brut as $t) {
                $this->textes[$t['id']] = $t['texte'];
            }
        }
        return $this->textes;
    }
}
