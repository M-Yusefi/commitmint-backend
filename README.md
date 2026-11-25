<h1><img src="assets/logo.jpg" alt="CommitMint-logo" width="48" height="48" align="middle"> CommitMint</h1>

CommitMint is een mobile applicatie die mensen helpt om persoonlijke doelen te behalen door financiële inzet te combineren met persoonlijke verantwoordelijkheid.

## Het idee achter CommitMint

Gebruikers kunnen doelen instellen voor verschillende onderdelen van hun leven, zoals sporten, voeding, drinken en studeren. Per doel kiezen ze hoe vaak ze een activiteit willen uitvoeren, hoe lang ze dit willen volhouden en hoeveel geld ze willen inzetten.

Wanneer een gebruiker een geplande activiteit uitvoert en incheckt, krijgt diegene een deel van de inzet terug. Houdt de gebruiker zich niet aan de gemaakte afspraken, dan verliest diegene een deel van de inzet. Het verloren bedrag gaat naar een goed doel dat de gebruiker zelf heeft gekozen.

Het idee is om gebruikers extra financiële motivatie te geven om consequent aan hun doelen te werken. Ook wanneer iemand een doel niet behaalt, kan de inzet nog iets positiefs opleveren.

## Mijn doel met dit project

Met dit project wilde ik een mobile applicatie ontwikkelen en ervaring opdoen met het bouwen van een volledige applicatie: van de mobiele gebruikersinterface tot een online backend en de API-verbinding ertussen. Daarbij wilde ik leren hoe ik een backend in Laravel kan ontwikkelen en deze kan koppelen aan een frontend die in FlutterFlow is gemaakt.

## Architectuur en technologie

- **Mobiele frontend:** FlutterFlow
- **Backend:** Laravel 12 en PHP 8.2
- **Communicatie:** REST API; de FlutterFlow-app maakt verbinding met de online Laravel-backend via API-aanroepen
- **Authenticatie:** Laravel Sanctum
- **Database:** SQLite voor de lokale Laravel-configuratie
- **Versiebeheer:** Git en GitHub

Dit repository bevat de Laravel-backend. De FlutterFlow-frontend is apart ontwikkeld en communiceert met de backend via de API.

## Android-app testen

Download de APK naar een Android-apparaat en open het bestand om de app te installeren:

[**Download CommitMint voor Android (APK)**](assets/CommitMint.apk)

Android kan vragen om toestemming om apps van buiten de Play Store te installeren. De APK is bedoeld om de app te testen.

## Screenshots

| Aanmelden | Registreren |
|:---:|:---:|
| <img src="assets/screenshots/Screenshot%202026-11-25%20113524.png" alt="CommitMint-aanmeldscherm" width="220"> | <img src="assets/screenshots/Screenshot%202026-11-25%20113538.png" alt="CommitMint-registratiescherm" width="220"> |

| Startscherm zonder doel | Een doel aanmaken |
|:---:|:---:|
| <img src="assets/screenshots/Screenshot%202026-11-25%20113654.png" alt="Startscherm zonder actief doel" width="220"> | <img src="assets/screenshots/Screenshot%202026-11-25%20113725.png" alt="Doel en financiële inzet instellen" width="220"> |

| Doelgeschiedenis | Details van een doel |
|:---:|:---:|
| <img src="assets/screenshots/Screenshot%202026-11-25%20113709.png" alt="Overzicht van voltooide doelen" width="220"> | <img src="assets/screenshots/Screenshot%202026-11-25%20113716.png" alt="Statistieken en details van een doel" width="220"> |

| Instellingen | Check-in beoordelen |
|:---:|:---:|
| <img src="assets/screenshots/Screenshot%202026-11-25%20113736.png" alt="App-instellingen" width="220"> | <img src="assets/screenshots/Screenshot%202026-11-25%20113811.png" alt="Productiviteit tijdens een check-in beoordelen" width="220"> |

| Check-in bevestigen | Startscherm met actief doel |
|:---:|:---:|
| <img src="assets/screenshots/Screenshot%202026-11-25%20113823.png" alt="Check-in controleren en bevestigen" width="220"> | <img src="assets/screenshots/Screenshot%202026-11-25%20115836.png" alt="Actief doel en voortgang op het startscherm" width="220"> |

| Profielinstellingen | Veelgestelde vragen |
|:---:|:---:|
| <img src="assets/screenshots/Screenshot%202026-11-25%20115853.png" alt="Profiel- en accountinstellingen" width="220"> | <img src="assets/screenshots/Screenshot%202026-11-25%20115912.png" alt="Veelgestelde vragen in CommitMint" width="220"> |

## Projectgegevens

- **Student:** Mehrad Yusefi
- **Studentnummer:** 97101708
- **Projectnaam:** CommitMint
- **Periode:** gestart op 16-03-2026

## Leerdoelen

- Een Laravel-backend ontwikkelen.
- Een REST API ontwerpen, ontwikkelen en gebruiken.
- Een database ontwerpen en gebruiken.
- Een Laravel-backend koppelen aan een FlutterFlow-app.
- Gebruikersauthenticatie implementeren.
- Functionaliteiten testen en fouten oplossen.
- Werken met Git en GitHub.

## Randvoorwaarden

- Persoonsgegevens moeten volgens de AVG worden verwerkt.
- Gebruikersgegevens en wachtwoorden moeten veilig worden opgeslagen.
- Gebruikte libraries en externe bronnen moeten aan hun licenties voldoen.
- De voorwaarden voor het terugkrijgen en verliezen van geld moeten duidelijk zijn.
- De verwerking en donatie van geld moeten voldoen aan de geldende wet- en regelgeving.
