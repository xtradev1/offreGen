# Offerto : technique (relevé par la session principale, 2026-11-18)

Sources : en-têtes HTTP et HTML de https://getofferto.ch/ (curl), page https://getofferto.ch/datenschutz (« Stand: Oktober 2026 »), DNS.

## Lu à la source
- Site et application : Next.js (chemins `/_next/`, Turbopack), hébergé chez Vercel (`Server: Vercel`, exécution à Francfort : `X-Vercel-Id: fra1`, confirmé par la Datenschutz : « Ausführung der Anwendung in Frankfurt, Deutschland »).
- Base de données, fichiers et connexion : Supabase (CSP `connect-src ... supabase.co`), « Rechenzentrum in Zürich, Schweiz (AWS eu-central-2) ».
- Courriels : Resend, « Region Irland (EU) ».
- IA : « Anthropic PBC, USA, KI-Verarbeitung (Sprachmodell Claude). Auslesen hochgeladener Offerten und Erstellen von Offerten-Entwürfen aus Anfragen. »
- Transfert à l'étranger assumé : « Zur Erbringung des Dienstes werden Daten jedoch auch in der EU (Deutschland, Irland) und in den USA bearbeitet, insbesondere bei der KI-Verarbeitung durch Anthropic. »
- Connexion par lien envoyé par courriel (pas de mot de passe). Aucun traceur.
- Envoi des offres depuis le Gmail de l'artisan (`gmail.send`). Liaison bexio (contacts, copie des offres et factures, offres des 24 derniers mois pour le « Preisbuch »).
- Factures avec « QR-Zahlteil » (IBAN de l'entreprise enregistré).
- Acceptation en ligne : nom, heure et IP gardés comme preuve ; nombre d'ouvertures de l'offre suivi.
- Programme partenaires : « Partner-Link ... (z.B. Ihres Treuhänders oder Verbands) », cookie 90 jours ; le partenaire voit le nom, le lieu, la date de début et l'état (essai, payant, résilié).
- Dix pages par métier : `/fuer/maler`, `gipser`, `sanitaer`, `heizung`, `elektro`, `gartenbau`, `plattenleger`, `schreiner`, `dachdecker`, `metallbau`. Autres pages : `/partner`, `/ausprobieren`, `/start`, `/login`.
- Pas de manifeste d'application web (`/manifest.webmanifest` et `/manifest.json` : 404), aucun lien App Store ou Google Play dans la page d'accueil.
- En-tête `Permissions-Policy: microphone=(self), camera=()` : micro autorisé dans le navigateur, caméra interdite.

## Déduit, non vérifié
- Aucun fournisseur de transcription de la voix n'est déclaré parmi les sous-traitants, et la CSP n'autorise que leur domaine et Supabase. La dictée passe donc probablement par la reconnaissance vocale du navigateur ou du clavier du téléphone, qui rend du texte ; pas d'enregistrement audio long traité côté serveur. À vérifier en ouvrant un compte d'essai.
- `camera=()` : pas de prise de photo dans l'application à ce jour.
