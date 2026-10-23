## Accounts plugin for GLPI

[![License](https://img.shields.io/badge/License-GNU%20GPL%20v3-blue.svg?style=flat-square)](https://github.com/InfotelGLPI/accounts/blob/master/LICENSE)
[![Web](https://img.shields.io/badge/Web-Infotel-blue.svg?style=flat-square)](https://blogglpi.infotel.com)
[![Translate](https://img.shields.io/badge/Translate-Transifex-cyan)](https://explore.transifex.com/infotelGLPI/GLPI_accounts/)

OldName: **compte**

---

### English

This plugin enables you to manage the accounts of your network and associate them with elements of the inventory.

* **Account details**: login, AES-256-CTR encrypted password, affected user, creation and expiration dates, status, location, group, technician in charge.
* **Password encryption**: passwords are encrypted and decrypted client-side (JavaScript) using a fingerprint-derived AES key — the plaintext never transits unencrypted.
* **Fingerprints and encryption keys**: manage shared secrets used to derive encryption keys, with a dedicated right (`plugin_accounts_hash`).
* **TOTP support**: optionally store a two-factor authentication secret (RFC 6238) alongside the password.
* **Inventory association**: link accounts to any GLPI object (computer, network equipment, software, contract, entity…).
* **Helpdesk interface**: accounts can be consulted and associated with tickets from the simplified interface.
* **Expiration alerts**: automatic task sends e-mail notifications for expired or soon-to-expire accounts.

#### Choosing the encryption key

Client-side encryption means the browser has to be given what it needs to decrypt: the
PBKDF2 key verifier and the ciphertexts are served to **every user allowed to read an
account of the entity**. That is inherent to the design, not a defect — but it does make the
master key an offline-attackable secret rather than one only an administrator ever sees.
Three consequences worth acting on:

* **Use a long, randomly generated passphrase.** The verifier is salted and stretched over
  100 000 PBKDF2-SHA256 iterations, which slows a dictionary attack down but does not save a
  weak or reused key.
* **One key protects a whole entity.** Anyone who recovers it reads every account of that
  entity, including the ones they were not allowed to open individually. Separate entities
  that must not share secrets, and give each its own fingerprint.
* **Grant `plugin_accounts` read access sparingly.** The right to read one account is the
  right to collect the material for that attack.

**[Full English documentation →](docs/en/index.md)**

---

### Français

Ce plugin vous permet de gérer les comptes de votre réseau et de les associer à des éléments de l'inventaire.

* **Détail d'un compte** : login, mot de passe chiffré AES-256-CTR, utilisateur affecté, dates de création et d'expiration, statut, emplacement, groupe, technicien en charge.
* **Chiffrement des mots de passe** : chiffrement et déchiffrement côté client (JavaScript) via une clé AES dérivée d'une empreinte — le mot de passe en clair ne transite jamais.
* **Empreintes et clés de chiffrement** : gestion des secrets partagés servant à dériver les clés AES, avec un droit dédié (`plugin_accounts_hash`).
* **Support TOTP** : stockage optionnel d'un secret d'authentification à deux facteurs (RFC 6238).
* **Association à l'inventaire** : liaisons avec n'importe quel objet GLPI (ordinateur, équipement réseau, logiciel, contrat, entité…).
* **Interface helpdesk** : les comptes sont consultables et associables à des tickets depuis l'interface simplifiée.
* **Alertes d'expiration** : tâche automatique d'envoi de mails pour les comptes expirés ou bientôt expirés.

#### Choix de la clé de chiffrement

Le chiffrement côté client suppose de remettre au navigateur de quoi déchiffrer : le
vérificateur PBKDF2 et les cryptogrammes sont servis à **tout utilisateur autorisé à lire un
compte de l'entité**. C'est inhérent au modèle, pas un défaut — mais cela fait de la clé
maîtresse un secret attaquable hors ligne, et non un secret que seul un administrateur voit
passer. Trois conséquences à prendre au sérieux :

* **Utilisez une phrase de passe longue et générée aléatoirement.** Le vérificateur est salé
  et étiré sur 100 000 itérations PBKDF2-SHA256, ce qui ralentit une attaque par
  dictionnaire mais ne rattrape pas une clé faible ou réutilisée.
* **Une seule clé protège toute une entité.** Qui la retrouve lit l'ensemble des comptes de
  cette entité, y compris ceux qu'il n'avait pas le droit d'ouvrir individuellement.
  Cloisonnez les entités qui ne doivent pas partager leurs secrets, avec une empreinte
  propre à chacune.
* **N'accordez le droit de lecture `plugin_accounts` qu'à bon escient.** Pouvoir lire un
  compte, c'est pouvoir réunir le matériel de cette attaque.

**[Documentation complète en français →](docs/fr/index.md)**
