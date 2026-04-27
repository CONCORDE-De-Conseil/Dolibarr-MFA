# MFA POUR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

Module d'authentification multifacteur pour Dolibarr utilisant des applications d'authentification compatibles TOTP telles que Google Authenticator, Microsoft Authenticator, Authy ou FreeOTP.

## Captures d'écran

Pour ce module, plusieurs captures sont préférables à une seule image.

- `img/screenshot_mfa_user_setup.jpg` : fiche utilisateur avec `Setup MFA`, QR code, secret et champ de vérification
- `img/screenshot_mfa_login_challenge.jpg` : page de connexion affichant la demande du code MFA
- `img/screenshot_mfa_attempt_history.jpg` : page d'administration avec l'historique des tentatives et les actions de réinitialisation

## Vue d'ensemble

Ce module ajoute une deuxième étape d'authentification après la vérification standard du mot de passe Dolibarr.
Lorsque la MFA est activée pour un utilisateur :

- l'utilisateur saisit son identifiant et son mot de passe habituels
- Dolibarr demande un code TOTP à 6 chiffres
- l'accès est accordé uniquement après validation du code OTP

Le module fournit également :

- l'enrôlement MFA depuis la fiche utilisateur Dolibarr
- le provisionnement par QR code pour les applications d'authentification
- les actions d'activation et de désactivation par utilisateur
- le suivi des tentatives échouées pour la connexion et pour la configuration
- une page d'administration pour consulter les blocages et réinitialiser les utilisateurs bloqués

## Fonctionnalités

- Génération de secret TOTP compatible avec les applications d'authentification standard
- URI de provisionnement par QR code pour une activation rapide
- Défi MFA à la connexion pour les utilisateurs ayant la MFA activée
- Interface de fiche utilisateur pour enrôler et activer la MFA
- Interface d'administration pour l'historique des tentatives et la réinitialisation
- Actions protégées contre les attaques CSRF
- Gestion du blocage après plusieurs codes MFA invalides

## Installation

Prérequis : Dolibarr ERP & CRM doit déjà être installé.
Important : définissez `$dolibarr_main_authentication = 'mfa';` dans `conf.php`.

Pour toute demande de projet ou d'assistance au déploiement :

- Contact : [contact@concorde.tn](mailto:contact@concorde.tn)
- Développeur : [ali.werghemmi@concorde.tn](mailto:ali.werghemmi@concorde.tn)
- Site web : [https://www.concorde.tn](https://www.concorde.tn)

### Depuis un paquet ZIP

Si vous disposez d'une archive telle que `module_mfa-1.0.zip` :

1. Connectez-vous à Dolibarr en tant que super-administrateur.
2. Allez dans `Accueil > Configuration > Modules/Applications > Déployer/Installer un module externe`.
3. Téléversez l'archive.
4. Activez le module `MFA` dans la liste des modules.

### Installation manuelle

Copiez le répertoire `mfa` dans le dossier des modules personnalisés de Dolibarr :

```bash
htdocs/custom/mfa
```

Puis :

1. Connectez-vous à Dolibarr en tant que super-administrateur.
2. Ouvrez `Accueil > Configuration > Modules/Applications`.
3. Activez le module `MFA`.
4. Si vous mettez à jour une installation existante, lancez la mise à jour du module afin de créer les tables d'historique des tentatives MFA.

## Utilisation

### Enrôler un utilisateur

1. Ouvrez la fiche de l'utilisateur concerné.
2. Cliquez sur `Setup MFA`.
3. Scannez le QR code avec une application d'authentification.
4. Saisissez le code de vérification à 6 chiffres.
5. Cliquez sur `Verify and Enable`.

### Flux de connexion

1. L'utilisateur saisit son identifiant et son mot de passe comme d'habitude.
2. Si la MFA est activée, Dolibarr demande le code de l'application d'authentification.
3. Après vérification réussie, l'utilisateur est connecté.

### Réinitialiser un utilisateur bloqué

1. Connectez-vous en tant qu'administrateur.
2. Ouvrez la page d'administration de l'historique des tentatives MFA.
3. Recherchez l'utilisateur concerné dans la liste des états courants.
4. Cliquez sur `Reset` pour effacer le blocage ou l'état des tentatives échouées.

## Historique des tentatives

Le module conserve les informations persistantes sur les échecs et les blocages MFA pour :

- les échecs de vérification lors de la connexion
- les échecs de vérification lors de la configuration MFA

Les administrateurs peuvent consulter :

- les compteurs actuels de tentatives échouées
- les dates de fin de blocage
- les adresses IP source
- l'historique récent des événements d'échec, de blocage, de réinitialisation et de succès

## Traductions

Les traductions peuvent être ajustées dans :

```text
langs/en_US/mfa.lang
langs/fr_FR/mfa.lang
langs/ar_SA/mfa.lang
```

## Propriété

Copyright (C) 2026 CONCORDE de Conseil [contact@concorde.tn](mailto:contact@concorde.tn)

Copyright (C) 2026 Ali WERGHEMMI [ali.werghemmi@concorde.tn](mailto:ali.werghemmi@concorde.tn)

Site web de l'entreprise : [https://www.concorde.tn](https://www.concorde.tn)

## Licence

### Code principal

GPLv3 ou toute version ultérieure. Voir [COPYING](COPYING).

### Documentation

Ce README et la documentation du module sont distribués avec le dépôt du module pour son utilisation et sa maintenance.
