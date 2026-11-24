<?php
// Les courriels qu'un artisan reçoit, rendus par la classe Courriel de la branche, avec des données d'exemple.
// Rien n'est envoyé : enHtml() seulement. Les trois courriels de LiensAcces sont recopiés de src/Compte/LiensAcces.php
// (lignes 65, 84 et 224 au commit 6708161), car leur construction lit la base.
require '/home/xavie/offreGen-relecture/vendor/autoload.php';

use OffreGen\Systeme\Courriel;

$tz = new DateTimeZone('Europe/Zurich');
$quand = new DateTimeImmutable('2026-11-24 14:35', $tz);
$fin = new DateTimeImmutable('2026-11-28 18:00', $tz);
// Le serveur local : le symbole du courriel se charge de là, jamais de la production.
$site = 'http://localhost:8010';
$app = 'Dicta';
$lien = 'http://localhost:8010/lien-d-exemple';

$liste = [
    ['acces', 'Compte ouvert : choisir son mot de passe', new Courriel(
        "Votre accès à $app",
        "Votre compte $app est ouvert",
        "Un compte est ouvert pour Peinture Morand avec votre adresse. Choisissez votre mot de passe dans les 7\u{00A0}jours\u{00A0}:",
        'Choisir mon mot de passe',
        $lien,
        ["Le lien sert une seule fois. Passé ce délai, demandez-en un nouveau avec «\u{00A0}Mot de passe oublié\u{00A0}», sur la page de connexion."],
    )],
    ['oubli', 'Mot de passe oublié', new Courriel(
        "Choisir un nouveau mot de passe pour $app",
        'Nouveau mot de passe',
        "Pour choisir un nouveau mot de passe, ouvrez ce lien dans l'heure\u{00A0}:",
        'Choisir mon mot de passe',
        $lien,
        ['Le lien sert une seule fois.'],
        "Vous n'avez rien demandé\u{00A0}? Ignorez ce courriel, votre mot de passe ne change pas.",
    )],
    ['mot-de-passe-change', 'Mot de passe changé', Courriel::motDePasseChange($quand, $lien)],
    ['demo-retour', 'Après l’entrée dans la démo : le lien pour y revenir', new Courriel(
        "Votre démo $app",
        "Votre démo reste ouverte jusqu'au " . Courriel::moment($fin),
        "Pour y revenir, sur votre téléphone ou sur un ordinateur\u{00A0}:",
        'Revenir dans ma démo',
        $lien,
        ["Ce lien ouvre votre démo sans mot de passe\u{00A0}: gardez-le pour vous."],
        "Vous n'avez pas essayé $app\u{00A0}? Ignorez ce courriel.",
        'Julie Rossel',
    )],
    ['rappel-2-jours', 'Rappel : la démo se termine dans 2 jours', Courriel::rappelDemo(false, $fin, $lien, 'Julie Rossel')],
    ['rappel-dernier', 'Dernier rappel : 12 heures avant la fin', Courriel::rappelDemo(true, $fin, $lien, 'Julie Rossel')],
    ['offre-prete', 'L’offre dictée est prête', Courriel::offrePrete($lien)],
    ['dictee-longue', 'Dictée trop longue', Courriel::dicteeEchouee($quand, $lien, 15)],
    ['dictee-echec', 'Dictée qui n’a pas pu être traitée', Courriel::dicteeEchouee($quand, $lien)],
];

$sortie = [];
foreach ($liste as [$id, $titre, $c]) {
    $champs = array_values(array_filter([
        ['Objet', $c->sujet], ['Titre', $c->titre], ['Texte', $c->texte], ['Bouton', $c->bouton],
        ...array_map(static fn (string $t): array => ['Texte sous le bouton', $t], $c->apres),
        ['Sous le texte', (new ReflectionClassConstant(Courriel::class, 'SECOURS'))->getValue()],
        ['Pied', $c->pied], ['Signature', $c->signataire === null ? $app : $c->signataire . ' / ' . $app],
    ], static fn (array $x): bool => $x[1] !== null && $x[1] !== ''));
    $sortie[] = ['id' => $id, 'titre' => $titre, 'champs' => $champs, 'html' => $c->enHtml($app, $site)];
}
echo json_encode($sortie, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
