## Accounts plugin for GLPI

[![License](https://img.shields.io/badge/License-GNU%20v2-blue.svg?style=flat-square)](https://github.com/InfotelGLPI/accounts/blob/master/LICENSE)
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

**[Documentation complète en français →](docs/fr/index.md)**
