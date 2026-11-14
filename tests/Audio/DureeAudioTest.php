<?php

declare(strict_types=1);

namespace OffreGen\Tests\Audio;

use OffreGen\Audio\DureeAudio;
use PHPUnit\Framework\TestCase;

final class DureeAudioTest extends TestCase
{
    private static function fixture(string $nom): string
    {
        return dirname(__DIR__) . '/fixtures/audio/' . $nom;
    }

    public function testDureeAnnonceeDansLEnTete(): void
    {
        self::assertEqualsWithDelta(1.0, (new DureeAudio())->secondes(self::fixture('visite.m4a')), 0.05);
        self::assertEqualsWithDelta(27.87, (new DureeAudio())->secondes(self::fixture('dictee-peintre.ogg')), 0.05);
    }

    public function testWebmDuNavigateurSansDureeAnnoncee(): void
    {
        // MediaRecorder écrit un WebM « en direct », sans durée dans l'en-tête (constaté le 2026-11-12) :
        // ces deux fichiers de synthèse sont faits pareil (ffmpeg -f webm -live 1).
        self::assertEqualsWithDelta(2.0, (new DureeAudio())->secondes(self::fixture('court-sans-duree.webm')), 0.05);
        self::assertEqualsWithDelta(6.0, (new DureeAudio())->secondes(self::fixture('six-secondes-sans-duree.webm')), 0.05);
    }

    public function testIlliblesDonnentNull(): void
    {
        self::assertNull((new DureeAudio())->secondes(self::fixture('texte.m4a')));
        self::assertNull((new DureeAudio('/chemin/absent/ffprobe'))->secondes(self::fixture('visite.m4a')));
    }
}
