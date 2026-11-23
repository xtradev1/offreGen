<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

/**
 * Un courriel mis en forme (maquette v8, direction C validée par Xavier le 2026-11-23) : un bandeau anthracite au
 * nom du produit, un titre, une phrase, un bouton, le lien en clair dessous, un pied, une signature. Il part en deux
 * versions, texte et HTML : une messagerie qui n'affiche pas le HTML montre le texte.
 *
 * Le bandeau tient sans image : sa couleur est un fond de cellule et le nom du produit est du texte. Seul le symbole
 * est une image, que beaucoup de messageries bloquent. Pas de SVG : Gmail et Outlook ne l'affichent pas.
 */
final readonly class Courriel
{
    private const SECOURS = "Le bouton ne fonctionne pas\u{00A0}? Copiez ce lien dans votre navigateur\u{00A0}:";

    /**
     * @param list<string> $apres phrases placées sous le bouton
     * @param string|null $signataire la personne qui signe, au-dessus du nom du produit ; null : le produit seul
     */
    public function __construct(
        public string $sujet,
        public string $titre,
        public string $texte,
        public string $bouton,
        public string $lien,
        public array $apres = [],
        public ?string $pied = null,
        public ?string $signataire = null,
    ) {
    }

    /** Le courriel « offre prête », envoyé quand une offre sort sans que l'artisan soit devant son écran. */
    public static function offrePrete(string $lien): self
    {
        return new self(
            'Votre offre est prête',
            'Votre offre est prête',
            "L'offre préparée à partir de votre enregistrement est prête à relire.",
            'Relire mon offre',
            $lien,
        );
    }

    public function envoyer(Messagerie $messagerie, string $destinataire, string $nomApplication, string $adresseApplication): void
    {
        $texte = $this->enTexte($nomApplication);
        if ($messagerie instanceof MessagerieMiseEnForme) {
            $messagerie->envoyerMisEnForme($destinataire, $this->sujet, $texte, $this->enHtml($nomApplication, $adresseApplication));
            return;
        }
        $messagerie->envoyer($destinataire, $this->sujet, $texte);
    }

    /** Le lien est seul sur sa ligne : une messagerie en texte le rend cliquable, et il ne se coupe pas. */
    public function enTexte(string $nomApplication): string
    {
        $blocs = ['Bonjour,', self::phrase($this->titre), $this->texte . "\r\n" . $this->lien, ...$this->apres];
        if ($this->pied !== null) {
            $blocs[] = $this->pied;
        }
        $blocs[] = $this->signataire === null ? $nomApplication : $this->signataire . "\r\n" . $nomApplication;
        return implode("\r\n\r\n", $blocs);
    }

    public function enHtml(string $nomApplication, string $adresseApplication): string
    {
        $e = static fn (string $t): string => htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $police = 'Arial,Helvetica,sans-serif';
        $p = "margin:0 0 14px;font:16px/1.5 $police;color:#1F2328";
        $petit = "margin:0 0 14px;font:13px/1.5 $police;color:#4B5058";
        $apres = implode('', array_map(static fn (string $t): string => "<p style=\"$p\">{$e($t)}</p>", $this->apres));
        $pied = $this->pied === null ? '' : "<p style=\"$petit;padding-top:14px;border-top:1px solid #E3E0D8\">{$e($this->pied)}</p>";
        $signature = ($this->signataire === null ? '' : "<b style=\"color:#1F2328\">{$e($this->signataire)}</b><br>") . $e($nomApplication);
        $symbole = $e(rtrim($adresseApplication, '/') . '/img/dicta-icone-180.png');
        $secours = $e(self::SECOURS);
        return <<<HTML
            <!doctype html>
            <html lang="fr">
            <head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{$e($this->sujet)}</title></head>
            <body style="margin:0;padding:0;background:#F3EFE6">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F3EFE6"><tr><td align="center" style="padding:16px 10px">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background:#FFFFFF;border-radius:12px;overflow:hidden">
            <tr><td bgcolor="#23272D" style="background:#23272D;padding:14px 20px;border-bottom:4px solid #C4552D">
            <img src="$symbole" width="28" height="28" alt="" style="vertical-align:middle;border:0;border-radius:6px">
            <span style="font:700 20px $police;color:#FFFFFF;vertical-align:middle;padding-left:6px">{$e($nomApplication)}</span>
            </td></tr>
            <tr><td style="padding:22px 20px 8px">
            <h1 style="margin:0 0 12px;font:700 20px/1.25 $police;color:#23272D">{$e($this->titre)}</h1>
            <p style="$p">{$e($this->texte)}</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td bgcolor="#23272D" align="center" style="background:#23272D;border-radius:12px">
            <a href="{$e($this->lien)}" style="display:block;padding:15px 14px;font:700 16px $police;color:#FFFFFF;text-decoration:none">{$e($this->bouton)}</a>
            </td></tr></table>
            <div style="height:16px;line-height:16px;font-size:16px">&nbsp;</div>
            $apres
            <p style="$petit">$secours<br><a href="{$e($this->lien)}" style="color:#4B5058;word-break:break-all">{$e($this->lien)}</a></p>
            $pied
            <p style="$petit">$signature</p>
            </td></tr>
            </table>
            </td></tr></table>
            </body>
            </html>
            HTML;
    }

    /** « vendredi 27 novembre à 14 h 19 », à l'heure de Zurich (M4 de Xavier, 2026-11-23 : la date et l'heure). */
    public static function moment(\DateTimeInterface $quand): string
    {
        $f = new \IntlDateFormatter('fr_CH', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Europe/Zurich', null, "EEEE d MMMM 'à' H'\u{00A0}h\u{00A0}'mm");
        return (string) preg_replace('/ 1 /', ' 1er ', (string) $f->format($quand), 1);
    }

    /** Un titre devient une phrase dans la version texte. */
    private static function phrase(string $titre): string
    {
        return preg_match('/[.!?:]$/u', $titre) === 1 ? $titre : $titre . '.';
    }
}
