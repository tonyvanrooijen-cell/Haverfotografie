# Haverfotografie deployment

Projectversie: **1.02** (zie `VERSION`).

De webimage gebruikt PHP **8.2.35** met Apache, inclusief de PHP-beveiligingsfixes
van 21 oktober 2026. De deploymentbuild haalt de basisimages opnieuw op.
Een draaiende container wordt pas bijgewerkt na herbouw en vervanging;
alleen de broncode bijwerken verandert de actieve PHP-versie niet.

Een push naar de standaardbranch bouwt de Docker-image, publiceert deze naar
`ghcr.io/<owner>/<repository>` en deployt de exacte commit via SSH. De workflow
kan ook handmatig worden gestart via Actions op de standaardbranch.

## GitHub Actions secrets

Stel onder Settings > Secrets and variables > Actions deze repository secrets in:

| Naam | Waarde |
| --- | --- |
| `DEPLOY_HOST` | `128.140.127.8` |
| `DEPLOY_PATH` | `/root/git/images/Haverfotografie` |
| `DEPLOY_USER` | SSH-gebruiker met toegang tot deze map en Docker, doorgaans `root` voor dit pad |
| `DEPLOY_SSH_KEY` | Volledige private SSH-key zonder passphrase; publieke sleutel in authorized_keys op de server |
| `GHCR_USERNAME` | GitHub-gebruikersnaam van de eigenaar van het token |
| `GHCR_TOKEN` | Personal access token (classic) met read:packages en toegang tot het package |

Het publiceren gebruikt de automatische `GITHUB_TOKEN` met packages:write.

## Server

De server moet Bash, tar, Docker Engine en Docker Compose v2 met ondersteuning
voor `up --wait` hebben. SSH gebruikt poort 22. De workflow maakt de deploymentmap
aan en kopieert `deploy.sh` en `docker-compose.prod.yml` bij iedere deployment;
een git clone of git pull op de server is niet nodig.

De webcontainer luistert standaard op poort 8008, net als de lokale configuratie.
De bestaande reverse proxy kan die poort blijven gebruiken. Optioneel kunnen
`WEB_PORT` en `WEB_BIND_ADDRESS` in de `.env` op de server worden ingesteld.
Die `.env` wordt niet door de workflow overschreven of in de image opgenomen.

De PHP-applicatie gebruikt momenteel de bestaande, in de code ingestelde externe
database. Deze deployment start geen nieuwe database en wijzigt de verbinding niet.
Er worden geen databasevolumes verwijderd. Gebruik dezelfde deploymentmap en,
indien eerder ingesteld, dezelfde `COMPOSE_PROJECT_NAME` in de server-.env om
de bestaande webservice te vervangen. Een oude db-service blijft ongemoeid.

De healthcheck controleert of Apache luistert; hij controleert geen databaseverbinding.
De eerste echte deployment vereist dat de server de externe database kan bereiken.

## Handmatig deployen of terugrollen

Log op de server in bij GHCR en voer vanuit de deploymentmap uit:

```bash
APP_IMAGE=ghcr.io/<owner>/<repository>:<commit-sha> bash ./deploy.sh
```

Voor lokaal ontwikkelen blijft `docker compose up --build` beschikbaar via
`docker-compose.yml`.

## Welkomstteksten

Via het dashboardtabblad **Welkom/SEO** zijn de bovenregel, titel en beschrijving
over de welkomstfoto afzonderlijk instelbaar. De beschrijving ondersteunt opmaak.
Een leeg veld verbergt de betreffende tekst. Bij de eerste ingebruikname worden
de bestaande teksten overgenomen; daarna staan ze los van de hidden-shoot.
De instellingen worden opgeslagen in de bestaande tabel `config`.
In hetzelfde tabblad stel je de **SEO-titel homepagina** en **Meta description
homepagina** in. Deze worden op de homepagina uitgevoerd als `<title>` en
`<meta name="description">`, los van de zichtbare welkomstteksten. Een lege
SEO-titel valt terug op `HaverFotografie`.

## Shootaanvragen via IK WIL OOK

Stel in het dashboard onder **Configuratie** het ontvangstadres voor
shootaanvragen in. Een leeg adres schakelt het formulier uit. Bezoekers vullen
naam, e-mailadres, telefoonnummer, gewenste datum en tijd en een korte omschrijving in.
Alle velden zijn verplicht; dit wordt ook op de server gecontroleerd. De aanvraag
wordt per mail samengevat; antwoorden op die mail gaat naar de aanvrager.
Aanvragen worden eerst opgeslagen in `HaverFotografieRequests` in de bestaande
serverdatabase en daarna per mail doorgestuurd. De tabel wordt automatisch
aangemaakt. Het dashboard heeft tabbladen voor Shoots, Aanvragen, Bezoeken en
Configuratie. Aanvragen toont alle gegevens en de mailstatus, met 20 aanvragen
per pagina en de nieuwste bovenaan. Ook bij een mailfout blijft de aanvraag
bewaard. Aanvragen zijn geen bevestigde boekingen. Eerder uitsluitend per mail
verstuurde aanvragen worden niet automatisch teruggehaald.
De tijdkolom wordt automatisch toegevoegd aan bestaande tabellen; oude aanvragen
tonen 'Niet opgegeven'. Via 'Aanvraag verwijderen' kan een beheerder na bevestiging
een aanvraag definitief verwijderen.

De mail wordt via SMTP verstuurd met [PHPMailer](https://github.com/PHPMailer/PHPMailer).
Stel op de server in `.env` de gegevens van je mailprovider in:

```dotenv
SMTP_HOST=smtp.voorbeeld.nl
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=account@voorbeeld.nl
SMTP_PASSWORD=vul-hier-het-smtp-wachtwoord-in
SMTP_FROM=account@voorbeeld.nl
```

Gebruik een door de provider toegestaan afzenderadres. Voor poort 465 gebruik je
`SMTP_ENCRYPTION=ssl`. `none` is alleen bedoeld voor een lokale testmailserver.
Herbouw de image en maak de webcontainer opnieuw aan na configuratiewijzigingen.
De Docker-build installeert de vastgelegde Composer-afhankelijkheden automatisch.
Zonder werkende SMTP-instellingen blijft de aanvraag opgeslagen en toont het
dashboard 'Mail niet verstuurd'. De bezoeker krijgt een ontvangstbevestiging
zodra de aanvraag is opgeslagen. 'Mail aangeboden' betekent dat de SMTP-server
de mail heeft geaccepteerd; controleer bij ingebruikname ook de ontvangst en spammap.
