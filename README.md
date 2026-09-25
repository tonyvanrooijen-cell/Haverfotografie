# Haverfotografie deployment

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
