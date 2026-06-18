# Documentation — Plugin Accounts pour GLPI
 
**Licence :** GNU GPL v2+  
**Auteur :** Infotel (Xavier CAILLAUD, Franck WAECHTER)  
**Dépôt :** https://github.com/InfotelGLPI/accounts

---

## Table des matières

1. [Présentation](#présentation)
2. [Installation](#installation)
3. [Fonctionnalités](#fonctionnalités)
   - [Gestion des comptes](#gestion-des-comptes)
   - [Chiffrement des mots de passe](#chiffrement-des-mots-de-passe)
   - [Empreintes (Fingerprints)](#empreintes-fingerprints)
   - [Clés de chiffrement (AES)](#clés-de-chiffrement-aes)
   - [TOTP (authentification à deux facteurs)](#totp-authentification-à-deux-facteurs)
   - [Association aux éléments d'inventaire](#association-aux-éléments-dinventaire)
   - [Utilisation depuis le helpdesk](#utilisation-depuis-le-helpdesk)
   - [Alertes d'expiration](#alertes-dexpiration)
4. [Gestion des droits](#gestion-des-droits)
5. [Options de recherche](#options-de-recherche)
6. [Intégrations](#intégrations)
7. [Désinstallation](#désinstallation)

---

## Présentation

Le plugin **Accounts** (anciennement *compte*) permet de gérer les comptes réseau/applicatifs dans GLPI et de les associer aux éléments de l'inventaire. Il offre :

- Stockage sécurisé des identifiants (login, mot de passe **chiffré** AES-256-CTR)
- Association à n'importe quel objet GLPI (ordinateur, équipement réseau, logiciel, entité, contrat…)
- Gestion des dates de création et d'expiration avec **alertes par e-mail**
- Système d'**empreintes (fingerprints)** et de **clés de chiffrement** pour protéger les mots de passe
- Stockage optionnel d'un **secret TOTP** (authentification à deux facteurs)
- Accessible depuis l'**interface simplifiée** (helpdesk)
- Intégration avec le plugin **servicecatalog**

---

## Installation

1. Télécharger le plugin depuis [GitHub](https://github.com/InfotelGLPI/accounts) ou la marketplace GLPI.
2. Décompresser l'archive dans le dossier `plugins/` (ou `marketplace/`) de votre installation GLPI.
3. Se connecter à GLPI en tant qu'administrateur.
4. Aller dans **Configuration › Plugins**, cliquer sur **Installer** puis **Activer** pour *Accounts*.

---

## Fonctionnalités

### Gestion des comptes

Un **compte** regroupe les informations d'identification d'un accès :

| Champ | Description |
|-------|-------------|
| **Nom** | Libellé du compte |
| **Type** | Dropdown configurable (ex. : SSH, FTP, Windows, MySQL…) |
| **Statut** | Dropdown d'état du compte |
| **Login** | Identifiant de connexion |
| **Mot de passe** | Stocké chiffré (AES-256-CTR, déchiffrable côté client via JavaScript) |
| **Utilisateur affecté** | Utilisateur GLPI propriétaire du compte |
| **Groupe affecté** | Groupe GLPI associé |
| **Technicien en charge** | Technicien GLPI responsable |
| **Groupe en charge** | Groupe technicien responsable |
| **Date de création** | Date de création du compte |
| **Date d'expiration** | Date d'expiration (déclenche les alertes) |
| **Emplacement** | Localisation GLPI |
| **Entité / Sous-entités** | Visibilité par entité avec option récursive |
| **Visible depuis le helpdesk** | Rend le compte associable à un ticket |
| **Autres** | Champ texte libre |
| **Commentaires** | Bloc de commentaires |
| **Secret TOTP** | Secret chiffré pour l'authentification à deux facteurs |

Les comptes supportent l'**historique des modifications**, les **notes** (bloc-notes), et le **clonage**.

---

### Chiffrement des mots de passe

Les mots de passe sont chiffrés en **AES-256-CTR** avec une IV aléatoire (16 octets). Le chiffrement se fait côté client (JavaScript) avant la transmission, et le déchiffrement également côté client, de sorte que le mot de passe en clair ne transite jamais en clair vers le serveur.

**Format v2 (par défaut) :**
```
$v2$<IV en base64>$<texte chiffré en base64>
```

La clé de chiffrement utilisée est le **SHA-256 de l'empreinte (fingerprint)** choisie.

> L'ancien format v1 (base64 seul, via AES-CTR JavaScript) reste lisible en lecture seule pour la rétrocompatibilité.

---

### Empreintes (Fingerprints)

Une **empreinte** (`Hash`) est un secret partagé qui sert de base à la dérivation de la clé de chiffrement AES. Elle est stockée dans GLPI (table `glpi_plugin_accounts_hashes`) et protégée par le droit `plugin_accounts_hash`.

- Chaque compte peut être associé à une empreinte pour chiffrer/déchiffrer son mot de passe
- Sans empreinte, le mot de passe ne peut pas être déchiffré
- Les empreintes sont gérées depuis **Administration › Empreintes** (interface centrale uniquement)

---

### Clés de chiffrement (AES)

Les **clés AES** (`AesKey`) sont des clés de chiffrement stockées dans GLPI, elles-mêmes chiffrées via une empreinte. Elles permettent un niveau supplémentaire de protection pour les environnements nécessitant une gestion fine des accès.

Accès : **Administration › Clés de chiffrement**

---

### TOTP (authentification à deux facteurs)

Le champ **Secret TOTP** permet de stocker un secret d'authentification à deux facteurs (compatible RFC 6238 / Google Authenticator). Le secret est chiffré de la même façon que le mot de passe (AES-256-CTR avec empreinte).

---

### Association aux éléments d'inventaire

Un compte peut être associé à un ou plusieurs objets GLPI des types suivants :

- Ordinateur, Moniteur, Équipement réseau, Périphérique, Téléphone, Imprimante
- Logiciel, Licence logicielle
- Entité, Contrat, Fournisseur, Certificat, Cluster
- Appliance, Instance de base de données

L'association est bidirectionnelle : depuis la fiche compte ou depuis la fiche de l'objet, un onglet **Comptes** liste les comptes liés.

---

### Utilisation depuis le helpdesk

Si l'option **Visible depuis le helpdesk** est activée sur un compte, il peut être :
- Associé à un ticket depuis l'interface simplifiée
- Consulté par l'utilisateur via le menu latéral de l'interface simplifiée (si le plugin `servicecatalog` n'est pas actif)

Avec le plugin **servicecatalog** actif, les comptes sont intégrés dans la liste des éléments associables du catalogue de services.

---

### Alertes d'expiration

Le plugin enregistre une **tâche automatique** (`AccountsAlert`) qui envoie des notifications par e-mail pour :

| Événement | Déclencheur |
|-----------|-------------|
| **Comptes expirés** | Comptes dont la date d'expiration est dépassée (depuis N jours) |
| **Comptes bientôt expirés** | Comptes dont la date d'expiration approche (dans N jours) |
| **Nouveau compte** | À la création d'un compte |

**Configuration des alertes** : accessible depuis **Configuration › Actions automatiques › AccountsAlert › onglet Plugin Setup**

| Paramètre | Description |
|-----------|-------------|
| **Délai pour les comptes expirés** | Nombre de jours après expiration pour envoyer l'alerte |
| **Délai pour les comptes bientôt expirés** | Nombre de jours avant expiration pour prévenir |

Les destinataires possibles sont :
- L'utilisateur affecté au compte
- Le groupe affecté
- Le technicien en charge
- Le groupe en charge

---

## Gestion des droits

Accès : **Administration › Profils › [profil] › onglet Accounts**

| Droit | Description |
|-------|-------------|
| `plugin_accounts` | Accès complet aux comptes (lecture, écriture, suppression, admin) |
| `plugin_accounts_hash` | Gestion des empreintes et clés de chiffrement |
| `plugin_accounts_my_groups` | Voir les comptes des groupes auxquels l'utilisateur appartient |
| `plugin_accounts_my_tech_groups` | Voir les comptes des groupes techniciens de l'utilisateur |
| `plugin_accounts_see_all_users` | Voir tous les comptes (toutes entités, tous utilisateurs) |
| `plugin_accounts_open_ticket` | Rendre les comptes associables à un ticket |

À l'installation, le profil Super-Admin reçoit tous les droits.

---

## Options de recherche

Les colonnes suivantes sont disponibles dans les listes de comptes :

| ID | Colonne | Description |
|----|---------|-------------|
| 1 | Nom | Nom du compte (lien vers la fiche) |
| 2 | Type | Type de compte |
| 4 | Login | Identifiant de connexion |
| 5 | Date de création | Date de création |
| 6 | Date d'expiration | Date d'expiration |
| 7 | Commentaires | Commentaires |
| 8 | Éléments associés | Objets GLPI liés au compte |
| 9 | Autres | Champ texte libre |
| 10 | Statut | Statut du compte |
| 11 | Sous-entités | Visibilité récursive |
| 12 | Groupe | Groupe affecté |
| 13 | Associable à un ticket | Booléen helpdesk |
| 14 | Dernière mise à jour | Date de modification |
| 15 | Empreinte | Fingerprint associée |
| 16 | Utilisateur affecté | Utilisateur propriétaire |

---

## Intégrations

| Plugin | Description |
|--------|-------------|
| **servicecatalog** | Les comptes apparaissent dans le catalogue de services de l'interface simplifiée |
| **fields** | Ajout de champs personnalisés sur les fiches comptes |
| **datainjection** | Import en masse de comptes via fichier CSV |
| **manageentities** | Intégration dans la gestion des entités |

Le plugin enregistre également les comptes dans la **carte d'impact** GLPI (`impact_asset_types`).

---

## Désinstallation

1. Aller dans **Configuration › Plugins**.
2. Cliquer sur **Désactiver** puis **Désinstaller** pour *Accounts*.

> **Attention :** La désinstallation supprime toutes les tables du plugin et les données associées (comptes, empreintes, clés, associations).
