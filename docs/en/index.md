# Documentation — Accounts Plugin for GLPI

**License:** GNU GPL v3+  
**Author:** Infotel (Xavier CAILLAUD, Franck WAECHTER)  
**Repository:** https://github.com/InfotelGLPI/accounts

---

## Table of Contents

1. [Overview](#overview)
2. [Installation](#installation)
3. [Features](#features)
   - [Account Management](#account-management)
   - [Password Encryption](#password-encryption)
   - [Fingerprints](#fingerprints)
   - [AES Encryption Keys](#aes-encryption-keys)
   - [TOTP (Two-Factor Authentication)](#totp-two-factor-authentication)
   - [Association with Inventory Items](#association-with-inventory-items)
   - [Helpdesk Interface](#helpdesk-interface)
   - [Expiration Alerts](#expiration-alerts)
4. [Rights Management](#rights-management)
5. [Search Options](#search-options)
6. [Integrations](#integrations)
7. [Uninstallation](#uninstallation)

---

## Overview

The **Accounts** plugin (formerly *compte*) lets you manage network and application accounts in GLPI and associate them with inventory items. It provides:

- Secure storage of credentials (login, **AES-256-CTR encrypted** password)
- Association with any GLPI object (computer, network equipment, software, entity, contract…)
- Creation and expiration date management with **e-mail alerts**
- **Fingerprint** and **encryption key** system to protect passwords
- Optional storage of a **TOTP secret** (two-factor authentication)
- Available from the **simplified (helpdesk) interface**
- Integration with the **servicecatalog** plugin

---

## Installation

1. Download the plugin from [GitHub](https://github.com/InfotelGLPI/accounts) or the GLPI marketplace.
2. Extract the archive into the `plugins/` (or `marketplace/`) directory of your GLPI installation.
3. Log in to GLPI as an administrator.
4. Go to **Setup › Plugins**, then click **Install** and **Enable** for *Accounts*.

---

## Features

### Account Management

An **account** stores the credential information for an access point:

| Field | Description |
|-------|-------------|
| **Name** | Account label |
| **Type** | Configurable dropdown (e.g. SSH, FTP, Windows, MySQL…) |
| **Status** | Account state dropdown |
| **Login** | Connection identifier |
| **Password** | Stored encrypted (AES-256-CTR, decrypted client-side via JavaScript) |
| **Affected User** | GLPI user who owns the account |
| **Affected Group** | Associated GLPI group |
| **Technician in charge** | Responsible GLPI technician |
| **Group in charge** | Responsible technician group |
| **Creation date** | Date the account was created |
| **Expiration date** | Expiration date (triggers alerts) |
| **Location** | GLPI location |
| **Entity / Child entities** | Entity visibility with optional recursion |
| **Helpdesk visible** | Makes the account associable to a ticket |
| **Others** | Free-text field |
| **Comments** | Comment block |
| **TOTP Secret** | Encrypted secret for two-factor authentication |

Accounts support **modification history**, **notepad**, and **cloning**.

---

### Password Encryption

Passwords are encrypted in **AES-256-CTR** with a random IV (16 bytes). Encryption is performed **client-side** (JavaScript) before transmission, and decryption is also client-side, so the plaintext password never travels unencrypted to the server.

**v2 format (default):**
```
$v2$<IV in base64>$<ciphertext in base64>
```

The encryption key is the **SHA-256 hash of the chosen fingerprint**.

> The legacy v1 format (plain base64 via AES-CTR JavaScript) remains readable in read-only mode for backward compatibility.

---

### Fingerprints

A **fingerprint** (`Hash`) is a shared secret used as the basis for AES key derivation. It is stored in GLPI (table `glpi_plugin_accounts_hashes`) and protected by the `plugin_accounts_hash` right.

- Each account can be linked to a fingerprint to encrypt/decrypt its password
- Without a fingerprint, the password cannot be decrypted
- Fingerprints are managed from **Administration › Fingerprints** (central interface only)

---

### AES Encryption Keys

**AES keys** (`AesKey`) are encryption keys stored in GLPI, themselves encrypted via a fingerprint. They provide an additional layer of protection for environments requiring fine-grained access control.

Access: **Administration › Encryption keys**

---

### TOTP (Two-Factor Authentication)

The **TOTP Secret** field allows storing a two-factor authentication secret (RFC 6238 / Google Authenticator compatible). The secret is encrypted the same way as the password (AES-256-CTR with fingerprint).

---

### Association with Inventory Items

An account can be associated with one or more GLPI objects of the following types:

- Computer, Monitor, Network Equipment, Peripheral, Phone, Printer
- Software, Software License
- Entity, Contract, Supplier, Certificate, Cluster
- Appliance, Database Instance

The association is bidirectional: from the account record or from the object record, an **Accounts** tab lists the linked accounts.

---

### Helpdesk Interface

If the **Helpdesk visible** option is enabled on an account, it can be:
- Associated with a ticket from the simplified interface
- Viewed by the user via the simplified interface side menu (when the `servicecatalog` plugin is not active)

With the **servicecatalog** plugin active, accounts are integrated into the list of associable items in the service catalog.

---

### Expiration Alerts

The plugin registers an automatic task (`AccountsAlert`) that sends e-mail notifications for:

| Event | Trigger |
|-------|---------|
| **Expired accounts** | Accounts whose expiration date has passed (by N days) |
| **Accounts about to expire** | Accounts whose expiration date is approaching (in N days) |
| **New account** | When an account is created |

**Alert configuration**: accessible from **Setup › Automatic actions › AccountsAlert › Plugin Setup tab**

| Parameter | Description |
|-----------|-------------|
| **Delay for expired accounts** | Number of days after expiration before sending the alert |
| **Delay for accounts which expire** | Number of days before expiration to warn |

Available notification recipients:
- The user assigned to the account
- The assigned group
- The technician in charge
- The group in charge

---

## Rights Management

Access: **Administration › Profiles › [profile] › Accounts tab**

| Right | Description |
|-------|-------------|
| `plugin_accounts` | Full access to accounts (read, write, delete, admin) |
| `plugin_accounts_hash` | Manage fingerprints and encryption keys |
| `plugin_accounts_my_groups` | See accounts belonging to the user's groups |
| `plugin_accounts_my_tech_groups` | See accounts of the user's technician groups |
| `plugin_accounts_see_all_users` | See all accounts (all entities, all users) |
| `plugin_accounts_open_ticket` | Make accounts associable to a ticket |

At installation, the Super-Admin profile receives all rights.

---

## Search Options

The following columns are available in account lists:

| ID | Column | Description |
|----|--------|-------------|
| 1 | Name | Account name (link to record) |
| 2 | Type | Account type |
| 4 | Login | Connection identifier |
| 5 | Creation date | Date created |
| 6 | Expiration date | Date of expiration |
| 7 | Comments | Comments |
| 8 | Associated items | GLPI objects linked to the account |
| 9 | Others | Free-text field |
| 10 | Status | Account status |
| 11 | Child entities | Recursive visibility |
| 12 | Group | Assigned group |
| 13 | Associable to a ticket | Helpdesk boolean |
| 14 | Last update | Last modification date |
| 15 | Fingerprint | Associated fingerprint |
| 16 | Affected User | Account owner |

---

## Integrations

| Plugin | Description |
|--------|-------------|
| **servicecatalog** | Accounts appear in the service catalog of the simplified interface |
| **fields** | Add custom fields to account records |
| **datainjection** | Bulk import of accounts via CSV file |
| **manageentities** | Integration with entity management |

The plugin also registers accounts in the GLPI **impact map** (`impact_asset_types`).

---

## Uninstallation

1. Go to **Setup › Plugins**.
2. Click **Disable** then **Uninstall** for *Accounts*.

> **Warning:** Uninstalling removes all plugin tables and associated data (accounts, fingerprints, keys, associations).
