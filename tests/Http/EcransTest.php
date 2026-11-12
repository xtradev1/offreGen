<?php

declare(strict_types=1);

namespace OffreGen\Tests\Http;

use OffreGen\Compte\Reglages;
use OffreGen\Donnees\DepotEnregistrements;
use OffreGen\Donnees\DepotClients;
use OffreGen\Donnees\DepotOffres;
use OffreGen\Donnees\Portee;
use OffreGen\Offre\LigneExtraite;
use OffreGen\Tests\Outils\BaseDeTest;
use OffreGen\Tests\Outils\Jeu;
use OffreGen\Tests\Outils\Monde;
use OffreGen\Tests\Outils\Navigateur;
use PHPUnit\Framework\TestCase;

/**
 * Les écrans du sous-chantier 11 : réglages, catalogue, offre modifiable. Chaque écriture passe
 * par le formulaire affiché (jeton repris de la page), comme le ferait le navigateur.
 * Jeu : une ligne « Peinture murale a », 12,5 m² à 18.50, catégorie « Peinture ».
 */
final class EcransTest extends TestCase
{
    private Jeu $jeu;
    private Navigateur $a;
    private int $ligne;
    private string $offre;

    protected function setUp(): void
    {
        $this->jeu = new Jeu();
        $this->a = new Navigateur(Monde::conteneur());
        $this->a->connecter('a@exemple.ch');
        $this->offre = '/offres/' . $this->jeu->offre['a'];
        $this->ligne = (int) BaseDeTest::ligne("SELECT id FROM ligne WHERE offre_id = {$this->jeu->offre['a']}")['id'];
    }

    /** @return array<string, mixed> */
    private function ligneEnBase(?int $id = null): array
    {
        return BaseDeTest::ligne('SELECT * FROM ligne WHERE id = ' . ($id ?? $this->ligne));
    }

    private function page(string $chemin): string
    {
        $reponse = $this->a->get($chemin);
        self::assertSame(200, $reponse->getStatusCode(), $chemin);
        return (string) $reponse->getContent();
    }

    // Réglages

    public function testNonAssujettiRetireLaTvaDeLOffre(): void
    {
        $this->page('/reglages');
        self::assertSame(303, $this->a->post('/reglages', ['entreprise' => 'Peinture Exemple', 'tva' => '0', 'arrondi' => '1', 'validite_jours' => '30', 'paiement_jours' => '30'])->getStatusCode());
        self::assertStringContainsString('Réglages enregistrés', $this->page('/reglages'));
        self::assertSame(['entreprise' => 'Peinture Exemple', 'tva_pour_dix_mille' => 0, 'arrondi_cinq_centimes' => 1],
            BaseDeTest::ligne("SELECT entreprise, tva_pour_dix_mille, arrondi_cinq_centimes FROM artisan WHERE id = {$this->jeu->a}"));
        $offre = $this->page($this->offre);
        // 231.25 sans TVA ; arrondi aux 5 centimes : 231.25.
        self::assertStringNotContainsString('TVA', $offre);
        self::assertStringContainsString("CHF\u{00A0}231.25", $offre);
    }

    public function testSansArrondiLeTotalResteAuCentime(): void
    {
        $this->page('/reglages');
        $this->a->post('/reglages', ['entreprise' => 'Entreprise A', 'tva' => '810', 'arrondi' => '0']);
        // 231.25 + 18.73 = 249.98, sans la ligne « Arrondi ».
        $offre = $this->page($this->offre);
        self::assertStringContainsString("CHF\u{00A0}249.98", $offre);
        self::assertStringNotContainsString('Arrondi', $offre);
    }

    public function testReglageFauxRefuseSansRienChanger(): void
    {
        $this->page('/reglages');
        self::assertSame(422, $this->a->post('/reglages', ['entreprise' => '  ', 'tva' => '500', 'arrondi' => '1'])->getStatusCode());
        self::assertSame(['entreprise' => 'Entreprise A', 'tva_pour_dix_mille' => 810],
            BaseDeTest::ligne("SELECT entreprise, tva_pour_dix_mille FROM artisan WHERE id = {$this->jeu->a}"));
    }

    // Catalogue

    public function testCatalogueMontreLesProduitsEtLaRechercheMasqueLesAutres(): void
    {
        $page = $this->page('/catalogue');
        self::assertMatchesRegularExpression('/<a class="crow" id="p\d+" href="\/catalogue\/\d+" data-en-feuille data-texte="peinture murale a peinture">/', $page);
        self::assertStringContainsString('18.50', $page);
        self::assertStringContainsString('aria-current="page">', $page);
        $cherche = $this->page('/catalogue?q=MURALE');
        self::assertMatchesRegularExpression('/data-texte="peinture murale a peinture">/', $cherche);
        $rien = $this->page('/catalogue?q=fa%C3%A7ade');
        self::assertMatchesRegularExpression('/data-texte="peinture murale a peinture" hidden>/', $rien);
        self::assertMatchesRegularExpression('/<p class="vide" data-aucun>/', $rien);
    }

    public function testCreerUnProduitPuisLeModifier(): void
    {
        $this->page('/catalogue/nouveau');
        $champs = ['designation' => 'Crépi rustique', 'unite' => 'm²', 'prix' => '34,50', 'categorie' => 'Peinture', 'nouvelle_categorie' => 'Façade', 'synonymes' => 'crépi, rustique'];
        $reponse = $this->a->post('/catalogue', $champs);
        self::assertSame(303, $reponse->getStatusCode());
        $p = BaseDeTest::ligne("SELECT id, categorie, prix_centimes, synonymes FROM produit WHERE designation = 'Crépi rustique' AND artisan_id = {$this->jeu->a}");
        self::assertSame(['categorie' => 'Façade', 'prix_centimes' => 3450, 'synonymes' => 'crépi, rustique'], array_diff_key($p, ['id' => 0]));
        self::assertSame('/catalogue#p' . $p['id'], $reponse->headers->get('Location'));

        $this->page('/catalogue/' . $p['id']);
        self::assertSame(303, $this->a->post('/catalogue/' . $p['id'], ['prix' => ''] + $champs)->getStatusCode());
        self::assertSame(0, BaseDeTest::ligne("SELECT prix_centimes FROM produit WHERE id = {$p['id']}")['prix_centimes']);
        self::assertStringContainsString('Prix&nbsp;?', $this->page('/catalogue'));
    }

    public function testProduitFauxRefuseEtRienNEstCree(): void
    {
        $this->page('/catalogue/nouveau');
        $reponse = $this->a->post('/catalogue', ['designation' => 'Crépi', 'unite' => 'pce', 'prix' => 'cher', 'categorie' => 'Peinture', 'nouvelle_categorie' => '', 'synonymes' => '']);
        self::assertSame(422, $reponse->getStatusCode());
        self::assertStringContainsString('aria-invalid="true" aria-describedby="err-prix"', (string) $reponse->getContent());
        self::assertSame(0, BaseDeTest::compter('produit', "designation = 'Crépi'"));
    }

    public function testSupprimerUnProduitGardeLaLigneDeLOffre(): void
    {
        $id = $this->jeu->produit['a'];
        $this->page("/catalogue/$id/supprimer");
        self::assertSame(303, $this->a->post("/catalogue/$id/supprimer", [])->getStatusCode());
        self::assertSame(0, BaseDeTest::compter('produit', "id = $id"));
        $l = $this->ligneEnBase();
        self::assertSame(['designation' => 'Peinture murale a', 'prix_centimes' => 1850, 'produit_id' => null],
            ['designation' => $l['designation'], 'prix_centimes' => $l['prix_centimes'], 'produit_id' => $l['produit_id']]);
        self::assertStringContainsString('Hors catalogue', $this->page($this->offre));
    }

    // Offre

    public function testOffreGroupeeParCategorieSansRappelDeLaDictee(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET entendu = 'les murs, douze et demi' WHERE id = {$this->ligne}");
        $page = $this->page($this->offre);
        self::assertStringContainsString('<h5>Peinture</h5>', $page);
        self::assertStringContainsString('name="quantite" value="12.5"', $page);
        self::assertStringContainsString('name="prix" value="18.50"', $page);
        self::assertStringNotContainsString('douze et demi', $page);
        self::assertStringNotContainsString('Entendu', $page);
    }

    public function testCorrigerLaQuantiteRetireAVerifierEtChangeLeTotal(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1 WHERE id = {$this->ligne}");
        $this->page($this->offre);
        $reponse = $this->a->post("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '20,5']);
        self::assertSame("{$this->offre}#l{$this->ligne}", $reponse->headers->get('Location'));
        $l = $this->ligneEnBase();
        self::assertSame([20500, 0, 1850], [$l['quantite_milliemes'], $l['a_verifier'], $l['prix_centimes']]);
        // 20,5 × 18.50 = 379.25 ; TVA 30.71925 → 30.72 ; 409.97 → 409.95.
        self::assertStringContainsString("CHF\u{00A0}409.95", $this->page($this->offre));
    }

    public function testQuantiteFausseRefuseeAvecUnMessage(): void
    {
        $this->page($this->offre);
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '0'])->getStatusCode());
        self::assertSame(12500, $this->ligneEnBase()['quantite_milliemes']);
        self::assertStringContainsString('Rien n&#039;a été modifié.', $this->page($this->offre));
    }

    public function testPrixEffaceDevientPrixManquant(): void
    {
        $this->page($this->offre);
        $this->a->post("{$this->offre}/lignes/{$this->ligne}", ['prix' => '']);
        self::assertSame(0, $this->ligneEnBase()['prix_centimes']);
        self::assertStringContainsString('Prix manquant', $this->page($this->offre));
    }

    public function testCEstJusteRetireAVerifierSansRienChanger(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET a_verifier = 1 WHERE id = {$this->ligne}");
        self::assertStringContainsString("C'est juste", $this->page($this->offre));
        $this->a->post("{$this->offre}/lignes/{$this->ligne}/juste", []);
        $l = $this->ligneEnBase();
        self::assertSame([0, 12500, 1850], [$l['a_verifier'], $l['quantite_milliemes'], $l['prix_centimes']]);
    }

    public function testSupprimerPuisAnnuler(): void
    {
        $this->page($this->offre);
        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->ligne}/supprimer", [])->getStatusCode());
        $apres = $this->page($this->offre);
        self::assertStringNotContainsString('name="quantite"', $apres);
        self::assertStringContainsString("action=\"{$this->offre}/lignes/{$this->ligne}/restaurer\"", $apres);
        self::assertStringContainsString("CHF\u{00A0}0.00", $apres);
        self::assertNotNull($this->ligneEnBase()['supprimee_le']);

        self::assertSame(303, $this->a->post("{$this->offre}/lignes/{$this->ligne}/restaurer", [])->getStatusCode());
        self::assertNull($this->ligneEnBase()['supprimee_le']);
        self::assertStringContainsString("CHF\u{00A0}250.00", $this->page($this->offre));
    }

    public function testAjouterUnProduitDuCatalogueOuvreSaQuantite(): void
    {
        $this->page("{$this->offre}/ajouter");
        $reponse = $this->a->post("{$this->offre}/lignes", ['produit' => (string) $this->jeu->produit['a']]);
        $nouvelle = (int) BaseDeTest::ligne("SELECT MAX(id) AS id FROM ligne WHERE offre_id = {$this->jeu->offre['a']}")['id'];
        self::assertSame("{$this->offre}#l$nouvelle", $reponse->headers->get('Location'));
        $l = $this->ligneEnBase($nouvelle);
        self::assertSame([2, 1000, 1850, 0, $this->jeu->produit['a']], [$l['rang'], $l['quantite_milliemes'], $l['prix_centimes'], $l['a_verifier'], $l['produit_id']]);
        self::assertStringContainsString("data-focus-ligne=\"$nouvelle\"", $this->page($this->offre));
    }

    public function testLigneLibreGardeeAuCatalogue(): void
    {
        $this->page("{$this->offre}/ajouter");
        $champs = ['designation' => 'Échafaudage roulant', 'quantite' => '2', 'unite' => 'forfait', 'prix' => '150', 'garder' => '1', 'categorie' => 'Peinture', 'nouvelle_categorie' => 'Location'];
        self::assertSame(303, $this->a->post("{$this->offre}/lignes", $champs)->getStatusCode());
        $p = BaseDeTest::ligne("SELECT id, categorie, prix_centimes FROM produit WHERE designation = 'Échafaudage roulant' AND artisan_id = {$this->jeu->a}");
        $l = BaseDeTest::ligne("SELECT produit_id, quantite_milliemes FROM ligne WHERE designation = 'Échafaudage roulant'");
        self::assertSame(['Location', 15000, $p['id'], 2000], [$p['categorie'], $p['prix_centimes'], $l['produit_id'], $l['quantite_milliemes']]);
        self::assertStringContainsString('<h5>Location</h5>', $this->page($this->offre));
    }

    public function testLigneLibreSansPrixResteHorsCatalogue(): void
    {
        $this->page("{$this->offre}/ajouter");
        $this->a->post("{$this->offre}/lignes", ['designation' => 'Moquette escalier', 'quantite' => '1', 'unite' => 'forfait', 'prix' => '']);
        $l = BaseDeTest::ligne("SELECT produit_id, prix_centimes FROM ligne WHERE designation = 'Moquette escalier'");
        self::assertSame([null, 0], [$l['produit_id'], $l['prix_centimes']]);
        self::assertSame(1, BaseDeTest::compter('produit', "artisan_id = {$this->jeu->a}"));
        $page = $this->page($this->offre);
        self::assertStringContainsString('<h5>Hors catalogue</h5>', $page);
        self::assertStringContainsString('Prix manquant', $page);
    }

    public function testPageDeLaLigneChangeDesignationEtUnite(): void
    {
        $this->page("{$this->offre}/lignes/{$this->ligne}");
        $reponse = $this->a->post("{$this->offre}/lignes/{$this->ligne}", ['designation' => 'Murs du salon', 'quantite' => '12.5', 'unite' => 'forfait', 'prix' => '18.50']);
        self::assertSame(303, $reponse->getStatusCode());
        $l = $this->ligneEnBase();
        self::assertSame(['Murs du salon', 'forfait'], [$l['designation'], $l['unite']]);
    }

    public function testPageDeLaLigneFausseRestaureLaSaisie(): void
    {
        $this->page("{$this->offre}/lignes/{$this->ligne}");
        $reponse = $this->a->post("{$this->offre}/lignes/{$this->ligne}", ['designation' => 'Murs', 'quantite' => 'beaucoup', 'unite' => 'm²', 'prix' => '18.50']);
        self::assertSame(422, $reponse->getStatusCode());
        self::assertStringContainsString('value="beaucoup"', (string) $reponse->getContent());
        self::assertSame('Peinture murale a', $this->ligneEnBase()['designation']);
    }

    public function testNouvelleOffreEstDeuxBoutonsRondsParlerEtImporter(): void
    {
        $accueil = $this->page('/');
        // Variante B, choisie par Xavier : pas de « + », le micro à gauche puis l'import, sans texte.
        self::assertStringNotContainsString('data-fab', $accueil);
        self::assertMatchesRegularExpression('#<button class="pastille micro" type="button" aria-label="Parler" data-enregistrer hidden>.*</button>\s*<a class="pastille" href="/import" aria-label="Importer un fichier" data-importer>#s', $accueil);
        // Rappel de l'accord du client retiré de l'écran à la demande de Xavier (2026-11-12).
        self::assertStringNotContainsString('demandez son accord', $accueil);
        self::assertStringContainsString('<div class="feuille-fond" data-feuille hidden>', $accueil);
        // Chaque état n'existe qu'une fois, et le formulaire de repli n'apparaît pas sur l'accueil.
        self::assertSame(1, substr_count($accueil, 'data-etat="enregistrement"'));
        self::assertStringNotContainsString('<noscript>', $accueil);
        // Sans JavaScript, l'import mène à la page d'import, qui marche seule.
        $import = $this->page('/import');
        self::assertStringContainsString('<noscript>', $import);
        self::assertStringContainsString('<label class="rond" for="audio">', $import);
        self::assertSame(1, substr_count($import, 'data-etat="enregistrement"'));
    }

    public function testLaFicheProduitSOuvreParDessusLaListe(): void
    {
        BaseDeTest::pdo()->exec("INSERT INTO produit (artisan_id, categorie, designation, unite, prix_centimes) VALUES ({$this->jeu->a}, 'Sols', 'Parquet', 'm²', 9000)");
        $liste = $this->page('/catalogue');
        self::assertStringContainsString('<div class="feuille-fond" data-feuille-page hidden>', $liste);
        self::assertMatchesRegularExpression('#<a class="crow" id="p\d+" href="/catalogue/\d+" data-en-feuille data-texte=#', $liste);
        self::assertStringContainsString('href="/catalogue/nouveau" data-en-feuille>', $liste);
        // Catégorie choisie : les autres produits restent cachés même quand on tape dans la recherche.
        $sols = $this->page('/catalogue?cat=Sols');
        self::assertMatchesRegularExpression('#data-texte="[^"]*peinture[^"]*" data-hors-cat hidden>#', $sols);
        self::assertDoesNotMatchRegularExpression('#data-texte="parquet[^"]*" data-hors-cat#', $sols);
        // La fiche garde quatre champs visibles ; le reste est replié.
        $fiche = $this->page('/catalogue/nouveau');
        self::assertStringContainsString('<h1>Nouveau produit</h1>', $fiche);
        self::assertStringContainsString('data-premier', $fiche);
        self::assertMatchesRegularExpression('#<details class="plus">\s*<summary>Nouvelle catégorie, mots de la dictée</summary>#', $fiche);
        self::assertStringContainsString('<h1>Modifier le produit</h1>', $this->page('/catalogue/' . $this->jeu->produit['a']));
    }

    // Sous-chantier 12 : sauvegarde, en-tête, document

    public function testCorrectionDateLOffreEtLaRemonteDansLaListe(): void
    {
        $this->page($this->offre);
        $this->a->post("{$this->offre}/lignes/{$this->ligne}", ['quantite' => '3']);
        self::assertSame('2026-11-07 10:00:00', BaseDeTest::ligne("SELECT modifiee_le FROM offre WHERE id = {$this->jeu->offre['a']}")['modifiee_le']);
        self::assertStringContainsString('modifiée le 07.11.2026 11:00', $this->page('/'));
        self::assertStringContainsString('Enregistrée le 07.11.2026 11:00', $this->page($this->offre));
    }

    public function testNumeroDOffrePropreAChaqueArtisan(): void
    {
        $n = BaseDeTest::ligne("SELECT a.numero AS a, b.numero AS b FROM offre a, offre b WHERE a.id = {$this->jeu->offre['a']} AND b.id = {$this->jeu->offre['b']}");
        self::assertSame(['a' => 1, 'b' => 1], $n);
        self::assertStringContainsString('<h1>Offre 1</h1>', $this->page($this->offre));
    }

    /** L'en-tête ne porte plus que l'objet : le client se choisit dans sa fenêtre (sous-chantier 17). */
    public function testEnteteObjetSeul(): void
    {
        $offre = $this->page($this->offre);
        self::assertStringContainsString('Choisir le client', $offre);
        self::assertStringContainsString('href="' . $this->offre . '/client" data-en-feuille', $offre);
        $entete = $this->page("{$this->offre}/modifier");
        self::assertStringNotContainsString('name="client_nom"', $entete);
        self::assertSame(303, $this->a->post("{$this->offre}/modifier", ['titre' => ' Salon  et cuisine '])->getStatusCode());
        self::assertSame('Salon et cuisine', BaseDeTest::ligne("SELECT titre FROM offre WHERE id = {$this->jeu->offre['a']}")['titre']);
    }

    public function testEnteteSansObjetRefuse(): void
    {
        $this->page("{$this->offre}/modifier");
        self::assertSame(422, $this->a->post("{$this->offre}/modifier", ['titre' => ' '])->getStatusCode());
        self::assertSame('Offre a', BaseDeTest::ligne("SELECT titre FROM offre WHERE id = {$this->jeu->offre['a']}")['titre']);
    }

    // Sous-chantier 21 : liste des offres (réponses de Xavier du 2026-11-12)

    /**
     * Deux offres de plus pour A : Façade (1000.00, client Zoé), Volets (50.00 à vérifier, client Albert).
     *
     * @return array{int, int}
     */
    private function deuxOffresDePlus(): array
    {
        $depot = new DepotOffres($this->jeu->base, new Portee($this->jeu->a));
        $clients = new DepotClients($this->jeu->base, new Portee($this->jeu->a));
        $fiche = static fn (string $nom): int => $clients->creer(['nom' => $nom, 'adresse' => null, 'telephone' => null, 'courriel' => null], new \DateTimeImmutable('2026-11-07'));
        $facade = $depot->creerExemple('Façade', [new LigneExtraite('Façade', 1000, 'forfait', 100000, false, null)], $this->jeu->maintenant, $fiche('Zoé Modèle'));
        $volets = $depot->creerExemple('Volets', [new LigneExtraite('Volets', 1000, 'forfait', 5000, true, null)], $this->jeu->maintenant, $fiche('Albert Essai'));
        return [$facade, $volets];
    }

    /** Les titres de la section « Offres », dans l'ordre de la page (archives exclues). */
    private function ordre(string $page): string
    {
        $offres = explode('data-archives', $page)[0];
        preg_match_all('#<b class="titre">([^<]+)</b>#', $offres, $m);
        return implode(', ', $m[1]);
    }

    public function testListeTrieeDeQuatreFacons(): void
    {
        $this->deuxOffresDePlus();
        self::assertSame('Façade, Offre a, Volets', $this->ordre($this->page('/?tri=montant')));
        // Client de A à Z, l'offre sans client à la fin.
        self::assertSame('Volets, Façade, Offre a', $this->ordre($this->page('/?tri=client')));
        self::assertStringStartsWith('Volets', $this->ordre($this->page('/?tri=relire')));
        $page = $this->page('/?tri=montant');
        self::assertStringContainsString('href="/?tri=montant" aria-current="true"', $page);
        // Tri inconnu : l'ordre par défaut, sans erreur.
        self::assertStringContainsString('href="/" aria-current="true"', $this->page('/?tri=<script>'));
    }

    public function testArchiverEtSupprimerGardentLeCompteDuMois(): void
    {
        [$facade] = $this->deuxOffresDePlus();
        self::assertStringContainsString('3 offres ce mois', $this->page('/'));

        self::assertSame(303, $this->a->post("/offres/$facade/archiver", [])->getStatusCode());
        $page = $this->page('/');
        self::assertStringNotContainsString('Façade', $this->ordre($page));
        self::assertMatchesRegularExpression('#data-archives.*Archives.*1.*Façade#s', $page);
        self::assertStringContainsString("action=\"/offres/$facade/restaurer\"", $page);
        self::assertStringContainsString('3 offres ce mois', $page);

        $this->a->post("/offres/$facade/restaurer", []);
        self::assertStringContainsString('Façade', $this->ordre($this->page('/')));

        self::assertSame(303, $this->a->post("/offres/$facade/supprimer", [])->getStatusCode());
        $page = $this->page('/');
        self::assertStringNotContainsString('Façade</b>', $page);
        self::assertStringContainsString('3 offres ce mois', $page);
        self::assertStringContainsString("«\u{00A0}Façade\u{00A0}» supprimée", $page);
        self::assertSame(404, $this->a->get("/offres/$facade")->getStatusCode());
        // « Annuler » la rend.
        $this->page('/');
        $this->a->post("/offres/$facade/restaurer", []);
        self::assertSame(200, $this->a->get("/offres/$facade")->getStatusCode());
    }

    public function testUnEchecNeComptePasSeRetireOuSeRelance(): void
    {
        $depot = new DepotEnregistrements($this->jeu->base, new Portee($this->jeu->a));
        $muet = '33333333-3333-4333-8333-333333333333';
        $transcrit = '44444444-4444-4444-8444-444444444444';
        $depot->creer($muet, 'visite.webm', 'audio/webm', 10, "{$this->jeu->a}/$muet.webm", $this->jeu->maintenant);
        $depot->creer($transcrit, 'visite2.webm', 'audio/webm', 10, "{$this->jeu->a}/$transcrit.webm", $this->jeu->maintenant);
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'echec', tentatives = 5, erreur = 'x' WHERE uuid IN ('$muet', '$transcrit')");
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET transcription = 'texte' WHERE uuid = '$transcrit'");

        $page = $this->page('/');
        self::assertSame(2, substr_count($page, 'Échec · ne compte pas dans vos offres'));
        self::assertStringContainsString('1 offre ce mois', $page);

        self::assertSame(303, $this->a->post("/enregistrements/$muet/retirer", [])->getStatusCode());
        self::assertSame(1, substr_count($this->page('/'), 'Échec · ne compte pas dans vos offres'));

        // Relancé là où il s'était arrêté : la transcription faite n'est pas refaite (ni payée deux fois).
        $reponse = $this->a->post("/enregistrements/$transcrit/reessayer", []);
        self::assertSame("/enregistrements/$transcrit", $reponse->headers->get('Location'));
        self::assertSame(['statut' => 'transcrit', 'tentatives' => 0, 'erreur' => null],
            BaseDeTest::ligne("SELECT statut, tentatives, erreur FROM enregistrement WHERE uuid = '$transcrit'"));
    }

    public function testLaPreparationAvanceEtapeParEtape(): void
    {
        $uuid = $this->jeu->uuid['a'];
        $page = $this->page("/enregistrements/$uuid");
        // Reçu : l'envoi est fait, la transcription en cours ; app.js interroge l'état de cet enregistrement.
        self::assertMatchesRegularExpression('#<ol class="etapes" data-etapes data-uuid="' . $uuid . '">\s*<li class="done">.*Envoi de l&\#039;enregistrement.*<li class="cur">.*Transcription de la dictée.*<li class="todo">.*Création de l&\#039;offre.*<li class="todo">.*Offre prête#s', $page);
        self::assertStringContainsString('<noscript><meta http-equiv="refresh" content="5"></noscript>', $page);

        $etat = $this->a->get("/enregistrements/$uuid/etat");
        self::assertSame('no-store, private', $etat->headers->get('Cache-Control'));
        self::assertSame(['statut' => 'recu', 'faites' => 1, 'echec' => false, 'essais' => 0, 'offre' => null], json_decode((string) $etat->getContent(), true));

        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'transcrit' WHERE uuid = '$uuid'");
        self::assertSame(2, json_decode((string) $this->a->get("/enregistrements/$uuid/etat")->getContent(), true)['faites']);
        BaseDeTest::pdo()->exec("UPDATE enregistrement SET statut = 'pret' WHERE uuid = '$uuid'");
        self::assertSame(['statut' => 'pret', 'faites' => 4, 'echec' => false, 'essais' => 0, 'offre' => $this->jeu->offre['a']],
            json_decode((string) $this->a->get("/enregistrements/$uuid/etat")->getContent(), true));
        // Prête : plus de suivi sur la page, le bouton mène à l'offre.
        $page = $this->page("/enregistrements/$uuid");
        self::assertStringNotContainsString('data-uuid', $page);
        self::assertStringContainsString('href="/offres/' . $this->jeu->offre['a'] . '">Voir l\'offre', $page);
    }

    public function testValiditeEtPaiementSeChoisissentDUnToucher(): void
    {
        $page = $this->page('/reglages/conditions');
        // Valeurs par défaut cochées : 30 jours de validité, paiement à 30 jours.
        self::assertStringContainsString('name="validite_choix" value="30" checked', $page);
        self::assertStringContainsString('name="validite_choix" value="autre">Autre', $page);
        self::assertStringNotContainsString('value="90"', $page);
        self::assertStringContainsString('name="paiement_type" value="jours" checked', $page);
        self::assertStringContainsString('name="paiement_jours_choix" value="30" checked', $page);
        self::assertStringContainsString('<span data-phrase>À 30 jours dès la fin des travaux.</span>', $page);

        // Acomptes « Autre » : enregistrés, cochés au retour, et la phrase va dans le document.
        self::assertSame(303, $this->a->post('/reglages', ['entreprise' => 'Peinture Exemple', 'tva' => '810', 'arrondi' => '1',
            'validite_choix' => 'autre', 'validite_jours' => '45', 'paiement_type' => 'acomptes',
            'paiement_acomptes_choix' => 'autre', 'paiement_acomptes' => '20, 30, 50'])->getStatusCode());
        self::assertSame(['validite_jours' => 45, 'paiement_type' => 'acomptes', 'paiement_acomptes' => '20,30,50'],
            BaseDeTest::ligne("SELECT validite_jours, paiement_type, paiement_acomptes FROM artisan WHERE id = {$this->jeu->a}"));
        $page = $this->page('/reglages/conditions');
        self::assertStringContainsString('name="validite_choix" value="autre" checked', $page);
        self::assertStringContainsString('name="paiement_acomptes_choix" value="autre" checked', $page);
        self::assertStringContainsString('value="20, 30, 50"', $page);
        $phrase = "20\u{00A0}% à la commande, 30\u{00A0}% en cours de travaux, 50\u{00A0}% à la fin des travaux.";
        self::assertStringContainsString($phrase, $page);
        self::assertStringContainsString($phrase, $this->page($this->offre . '/document'));

        // Pas d'offre sans validité ; des acomptes qui ne font pas 100 sont refusés, rien n'est changé.
        $this->page('/reglages');
        $refus = $this->a->post('/reglages', ['entreprise' => 'Peinture Exemple', 'tva' => '810', 'validite_choix' => 'autre', 'validite_jours' => '0',
            'paiement_type' => 'acomptes', 'paiement_acomptes_choix' => 'autre', 'paiement_acomptes' => '30, 30']);
        self::assertSame(422, $refus->getStatusCode());
        self::assertStringContainsString("L&#039;offre doit avoir une durée de validité", (string) $refus->getContent());
        self::assertStringContainsString('De 2 à 4 acomptes qui font 100', (string) $refus->getContent());
        self::assertSame(45, BaseDeTest::ligne("SELECT validite_jours FROM artisan WHERE id = {$this->jeu->a}")['validite_jours']);
    }

    public function testReglagesDeLEnteteNormalisesEtRefuses(): void
    {
        $this->page('/reglages');
        $champs = ['entreprise' => 'Peinture A', 'adresse' => "Rue 1\n2800 Delémont", 'telephone' => '032 422 00 00', 'courriel_contact' => 'info@exemple.ch',
                   'ide' => 'che 123 456 789 tva', 'tva' => '810', 'arrondi' => '1', 'validite_jours' => '45', 'paiement_jours' => '30'];
        self::assertSame(303, $this->a->post('/reglages', $champs)->getStatusCode());
        self::assertSame(['ide' => 'CHE-123.456.789', 'validite_jours' => 45, 'adresse' => "Rue 1\n2800 Delémont"],
            BaseDeTest::ligne("SELECT ide, validite_jours, adresse FROM artisan WHERE id = {$this->jeu->a}"));
        $this->page('/reglages');
        $reponse = $this->a->post('/reglages', ['ide' => 'CHE-12', 'courriel_contact' => 'pas un courriel', 'validite_jours' => '0'] + $champs);
        self::assertSame(422, $reponse->getStatusCode());
        foreach (['ide', 'courriel_contact', 'validite_jours'] as $champ) {
            self::assertStringContainsString("aria-describedby=\"err-$champ\"", (string) $reponse->getContent(), $champ);
        }
        self::assertSame('CHE-123.456.789', BaseDeTest::ligne("SELECT ide FROM artisan WHERE id = {$this->jeu->a}")['ide']);
    }

    public function testDocumentDeLOffre(): void
    {
        BaseDeTest::pdo()->exec("UPDATE artisan SET adresse = 'Rue 1\n2800 Delémont', ide = 'CHE-123.456.789', validite_jours = 30 WHERE id = {$this->jeu->a}");
        // Nom et lieu dictés, pas encore confirmés : le document les porte déjà.
        BaseDeTest::pdo()->exec("UPDATE offre SET client_dicte = 'Madame Exemple', lieu_dicte = 'Rue du Test 4' WHERE id = {$this->jeu->offre['a']}");
        $doc = $this->page("{$this->offre}/document");
        foreach (['Entreprise A', 'Offre n°&nbsp;1', 'Delémont, le 07.11.2026', 'Madame Exemple<br>Rue du Test 4', 'Peinture murale a',
                  '12.5&nbsp;m²', '231.25', 'TVA 8,1', "CHF\u{00A0}250.00", "valable jusqu'au 07.12.2026", 'CHE-123.456.789 TVA'] as $attendu) {
            self::assertStringContainsString($attendu, $doc);
        }
        self::assertStringNotContainsString('À vérifier', $doc);
    }

    public function testDocumentSignaleCeQuiManque(): void
    {
        BaseDeTest::pdo()->exec("UPDATE ligne SET prix_centimes = 0 WHERE id = {$this->ligne}");
        $doc = $this->page("{$this->offre}/document");
        self::assertStringContainsString('1 ligne sans prix', $doc);
        self::assertStringContainsString('Client à compléter', $doc);
        self::assertStringContainsString('Votre adresse manque', $doc);
    }

    public function testPdfDeLOffre(): void
    {
        $reponse = $this->a->get("{$this->offre}/pdf");
        self::assertSame(200, $reponse->getStatusCode());
        self::assertSame('application/pdf', $reponse->headers->get('Content-Type'));
        self::assertSame('inline; filename="Offre-1.pdf"', $reponse->headers->get('Content-Disposition'));
        self::assertStringStartsWith('%PDF-', (string) $reponse->getContent());
        self::assertGreaterThan(2000, strlen((string) $reponse->getContent()));
    }
}
