<?php

declare(strict_types=1);

namespace OffreGen\Facture;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use OffreGen\Adresse\Adresse;
use OffreGen\Compte\Iban;
use Sprain\SwissQrBill\DataGroup\Element\AdditionalInformation;
use Sprain\SwissQrBill\DataGroup\Element\CreditorInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentReference;
use Sprain\SwissQrBill\DataGroup\Element\StructuredAddress;
use Sprain\SwissQrBill\QrBill;
use Sprain\SwissQrBill\Reference\QrPaymentReferenceGenerator;
use Sprain\SwissQrBill\Reference\RfCreditorReferenceGenerator;

/**
 * La QR-facture (norme SIX, version 2.3) : référence, charge utile validée, dessin du code.
 *
 * - Adresses structurées (type S) seulement : le type K n'est plus permis à l'émission depuis le 21.11.2025.
 * - La charge utile et sa validation viennent de sprain/swiss-qr-bill. Son dessin, lui, appelle GD pour la croix
 *   suisse ; GD manque : le code est dessiné ici par bacon (niveau de correction M), la croix ajoutée en SVG.
 * - 174 unités pour 46 mm : dompdf lit le viewBox d'un SVG en pixels CSS (96 dpi).
 * - Un client dont l'adresse ne passe pas la norme est omis : « Payable par » reste un cadre vide (permis).
 *   Le créancier, lui, est obligatoire : sans lui, pas de section.
 */
final class QrFacture
{
    public const SANS_IBAN = "Sans IBAN, la facture n'a pas de section de paiement";
    public const SANS_ADRESSE = "Sans votre NPA et votre localité, la facture n'a pas de section de paiement";
    public const SANS_NOM = "Sans le nom de votre entreprise, la facture n'a pas de section de paiement";
    public const RIEN_A_PAYER = "Rien à payer\u{00A0}: la facture n'a pas de section de paiement";
    public const REFUSEE = "La norme de la QR-facture refuse votre nom, votre adresse ou votre IBAN\u{00A0}: la facture n'a pas de section de paiement";

    /** Côté du code en pixels CSS : 46 mm à 96 dpi. */
    public const COTE = 174;

    private const PX_PAR_MM = 96 / 25.4;

    /**
     * La référence de paiement, sans espaces, figée à l'émission. QR-IBAN : référence QR de 27 chiffres tirée de
     * l'identifiant de la facture (la norme l'exige avec un QR-IBAN). IBAN ordinaire : référence RF (ISO 11649) bâtie
     * sur le numéro, lettres et chiffres seulement ; null si le numéro n'en a aucun (paiement sans référence).
     */
    public static function reference(string $iban, int $factureId, string $numero): ?string
    {
        if (Iban::estQrIban($iban)) {
            return QrPaymentReferenceGenerator::generate(null, (string) $factureId);
        }
        $base = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $numero));
        return $base === '' ? null : RfCreditorReferenceGenerator::generate($base);
    }

    /**
     * La section de paiement, ou la raison de son absence (une des constantes).
     *
     * @param int $aPayer montant à payer, en centimes
     */
    public static function section(string $entreprise, Adresse $adresse, string $iban, ?string $reference, string $numero, int $aPayer, string $clientNom, Adresse $client): SectionPaiement|string
    {
        $entreprise = trim($entreprise);
        if ($iban === '') {
            return self::SANS_IBAN;
        }
        if ($entreprise === '') {
            return self::SANS_NOM;
        }
        if ($adresse->npa === '' || $adresse->localite === '') {
            return self::SANS_ADRESSE;
        }
        if ($aPayer <= 0) {
            return self::RIEN_A_PAYER;
        }
        $creancier = self::structuree($entreprise, $adresse);
        $debiteur = trim($clientNom) === '' || $client->npa === '' || $client->localite === '' ? null : self::structuree(trim($clientNom), $client);
        $informations = 'Facture n° ' . $numero;
        $bill = self::bill($creancier, $debiteur, $iban, $reference, $aPayer, $informations);
        if ($bill->getViolations()->count() > 0 && $debiteur !== null) {
            $debiteur = null;
            $bill = self::bill($creancier, null, $iban, $reference, $aPayer, $informations);
        }
        if ($bill->getViolations()->count() > 0) {
            return self::REFUSEE;
        }
        $texte = $bill->getQrCode()->getText();
        $ref = $bill->getPaymentReference();
        return new SectionPaiement(
            texte: $texte,
            svg: self::svg($texte),
            compte: Iban::lisible($iban),
            creancier: self::lignes($creancier),
            reference: $ref?->getFormattedReference(),
            informations: $informations,
            debiteur: $debiteur === null ? null : self::lignes($debiteur),
            monnaie: 'CHF',
            montant: number_format($aPayer / 100, 2, '.', "\u{00A0}"),
        );
    }

    private static function bill(StructuredAddress $creancier, ?StructuredAddress $debiteur, string $iban, ?string $reference, int $aPayer, string $informations): QrBill
    {
        $type = match (true) {
            $reference === null => PaymentReference::TYPE_NON,
            str_starts_with($reference, 'RF') => PaymentReference::TYPE_SCOR,
            default => PaymentReference::TYPE_QR,
        };
        $bill = QrBill::create()
            ->setCreditor($creancier)
            ->setCreditorInformation(CreditorInformation::create($iban))
            ->setPaymentAmountInformation(PaymentAmountInformation::create('CHF', $aPayer / 100))
            ->setPaymentReference(PaymentReference::create($type, $reference))
            ->setAdditionalInformation(AdditionalInformation::create($informations));
        return $debiteur === null ? $bill : $bill->setUltimateDebtor($debiteur);
    }

    private static function structuree(string $nom, Adresse $a): StructuredAddress
    {
        return $a->rue === ''
            ? StructuredAddress::createWithoutStreet($nom, $a->npa, $a->localite, $a->pays)
            : StructuredAddress::createWithStreet($nom, $a->rue, $a->numero === '' ? null : $a->numero, $a->npa, $a->localite, $a->pays);
    }

    /** @return list<string> nom, rue et numéro, NPA et localité (« FR-75001 Paris » hors de Suisse) */
    private static function lignes(StructuredAddress $a): array
    {
        return array_values(array_filter(explode("\n", $a->getFullAddress()), static fn (string $l): bool => $l !== ''));
    }

    /** Le code en SVG : niveau de correction M, encodage UTF-8 (norme), croix suisse de 7 mm au centre. */
    public static function svg(string $texte): string
    {
        $matrice = Encoder::encode($texte, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $n = $matrice->getWidth();
        $module = self::COTE / $n;
        $modules = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrice->get($x, $y) === 1) {
                    // Un millième de plus : pas de fente blanche entre deux modules voisins au rendu.
                    $modules .= sprintf('<rect x="%.4f" y="%.4f" width="%.4f" height="%.4f"/>', $x * $module, $y * $module, $module + 0.001, $module + 0.001);
                }
            }
        }
        $k = self::COTE / 46;
        $c = self::COTE / 2;
        $rect = static fn (float $x, float $y, float $l, float $h, string $f): string => sprintf('<rect x="%.3f" y="%.3f" width="%.3f" height="%.3f" fill="%s"/>', $c + $x * $k, $c + $y * $k, $l * $k, $h * $k, $f);
        // Gabarit SIX : carré noir de 7 mm bordé de blanc, croix blanche.
        $croix = $rect(-3.8, -3.8, 7.6, 7.6, '#fff') . $rect(-3.5, -3.5, 7, 7, '#000') . $rect(-0.585, -1.945, 1.17, 3.89, '#fff') . $rect(-1.945, -0.585, 3.89, 1.17, '#fff');
        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %1$d %1$d"><rect width="%1$d" height="%1$d" fill="#fff"/><g fill="#000">%2$s</g>%3$s</svg>', self::COTE, $modules, $croix);
    }

    /** Cadre vide (« Payable par » à remplir à la main) : quatre coins de 3 mm, trait de 0,75 pt (norme). */
    public static function cadre(float $largeurMm, float $hauteurMm): string
    {
        $l = round($largeurMm * self::PX_PAR_MM, 1);
        $h = round($hauteurMm * self::PX_PAR_MM, 1);
        $c = round(3 * self::PX_PAR_MM, 1);
        $e = 0.5; // demi-trait : le coin reste dans le cadre
        $coins = [
            sprintf('M%s %s V%s H%s', $e, $c, $e, $c),
            sprintf('M%s %s H%s V%s', $l - $c, $e, $l - $e, $c),
            sprintf('M%s %s V%s H%s', $l - $e, $h - $c, $h - $e, $l - $c),
            sprintf('M%s %s H%s V%s', $c, $h - $e, $e, $h - $c),
        ];
        $chemins = implode('', array_map(static fn (string $d): string => '<path d="' . $d . '"/>', $coins));
        return sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%1$s" height="%2$s" viewBox="0 0 %1$s %2$s"><g fill="none" stroke="#000" stroke-width="1">%3$s</g></svg>', $l, $h, $chemins);
    }
}
