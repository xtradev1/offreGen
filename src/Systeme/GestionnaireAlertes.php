<?php

declare(strict_types=1);

namespace OffreGen\Systeme;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Branche les alertes sur le journal : toute entrée de niveau « error » ou plus part par courriel (Alertes).
 * Les alertes ne sont construites qu'à la première erreur : une faute dans les réglages de messagerie ne fait pas
 * tomber le journal, donc les pages (revue du 2026-11-18). Un échec, de construction ou d'envoi, ne remonte pas dans
 * le journal de l'application, où il tournerait en rond : il part au journal de PHP (error_log), ou au secours donné.
 */
final class GestionnaireAlertes extends AbstractProcessingHandler
{
    /** @var \Closure(string): void */
    private readonly \Closure $secours;

    private ?Alertes $alertes = null;

    /**
     * @param \Closure(): Alertes $fabrique
     * @param (\Closure(string): void)|null $secours
     */
    public function __construct(private readonly \Closure $fabrique, ?\Closure $secours = null)
    {
        parent::__construct(Level::Error);
        $this->secours = $secours ?? static function (string $message): void {
            error_log($message);
        };
    }

    protected function write(LogRecord $record): void
    {
        try {
            $this->alertes ??= ($this->fabrique)();
            $this->alertes->alerter($record->message);
        } catch (\Throwable $e) {
            ($this->secours)('offreGen, alerte non envoyée : ' . $e->getMessage());
        }
    }
}
