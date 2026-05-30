# 📐 Diagramme de Classes (Class Diagram)

Ce diagramme de classes représente la structure logique du domaine et de la persistance de **FIANGEP Pharma**. Bien que le projet soit développé en PHP natif procédural/hybride, la base de données relationnelle et la structure d'accès aux données (par le biais du Singleton `Database`) sont modélisables de manière orientée objet, facilitant la compréhension des entités, de leurs attributs et de leurs interrelations.

---

## 🧜‍♂️ Diagramme Mermaid

```mermaid
classDiagram
    direction TB

    
    %% Classes du Domaine (Entités)
    class Utilisateur {
        +int id
        +string username
        +string email
        +string password
        +string nom
        +string prenom
        +string role
        +bool actif
        +datetime date_creation
    }

    class Client {
        +int id
        +string nom
        +string prenom
        +string telephone
        +string email
        +bool actif
        +datetime date_creation
    }

    class Categorie {
        +int id
        +string nom
        +string description
    }

    class Fournisseur {
        +int id
        +string nom
        +string telephone
        +string email
        +string adresse
    }

    class Medicament {
        +int id
        +string code_barre
        +string nom
        +string description
        +int categorie_id
        +int fournisseur_id
        +float prix_achat
        +float prix_vente
        +int stock_actuel
        +int stock_min
        +int ancien_stock
        +int nouveau_stock
        +date date_expiration_ancien
        +date date_expiration_nouveau
        +bool actif
        +datetime date_creation
        +getStockTotal() int
        +getDateExpirationActive() date
    }

    class StockMouvement {
        +int id
        +int medicament_id
        +string type_mouvement
        +int quantite
        +int quantite_avant
        +int quantite_apres
        +int user_id
        +string motif
        +datetime date_mouvement
    }

    class Vente {
        +int id
        +string numero_facture
        +int client_id
        +int user_id
        +datetime date_vente
        +float montant_total
        +float remise
        +string mode_paiement
        +string notes
    }

    class VenteDetail {
        +int id
        +int vente_id
        +int medicament_id
        +int quantite
        +float prix_unitaire
        +float sous_total
    }

    class CommandeFournisseur {
        +int id
        +string numero_commande
        +int fournisseur_id
        +int user_id
        +datetime date_commande
        +string statut
        +float montant_total
        +string notes
    }

    class CommandeFournisseurDetail {
        +int id
        +int commande_id
        +int medicament_id
        +int quantite
        +float prix_unitaire
        +float sous_total
    }

    

    %% Associations métier entre classes
    Categorie "1" --> "*" Medicament : "regroupe"
    Fournisseur "1" --> "*" Medicament : "fournit"
    
    Medicament "1" --> "*" StockMouvement : "concerne"
    Utilisateur "1" --> "*" StockMouvement : "enregistre"

    Utilisateur "1" --> "*" Vente : "encaisse"
    Client "0..1" --> "*" Vente : "achète"
    Vente "1" *-- "*" VenteDetail : "se compose de"
    Medicament "1" --> "*" VenteDetail : "figure dans"

    Fournisseur "1" --> "*" CommandeFournisseur : "reçoit"
    Utilisateur "1" --> "*" CommandeFournisseur : "génère"
    CommandeFournisseur "1" *-- "*" CommandeFournisseurDetail : "se compose de"
    Medicament "1" --> "*" CommandeFournisseurDetail : "figure dans"
```

---

## 📝 Description des Classes et Relations

### 1. Le Connecteur de Données (`Database`)
Cette classe implémente le patron de conception **Singleton** (constructeur privé `-Database()` et méthode statique d'accès `+getInstance()`). Elle encapsule la connexion PDO brute et fournit des méthodes d'abstraction de haut niveau (`select()`, `execute()`) pour sécuriser les appels en utilisant systématiquement des requêtes préparées avec typage des variables d'entrées. Elle intègre également la gestion transactionnelle (`beginTransaction()`, `commit()`, `rollback()`) indispensable lors de la facturation multi-articles.

### 2. Le Bloc Médicaments et Logistique (`Medicament`, `Categorie`, `Fournisseur`)
* **`Medicament`** : L'entité centrale de l'application. Elle contient à la fois les informations de base (prix, code-barres, seuils minimums) et les attributs du **double lot de stock** (`ancien_stock`, `nouveau_stock`, `date_expiration_ancien`, `date_expiration_nouveau`) qui permettent au moteur FEFO de calculer la priorité de déstockage.
* **`Categorie`** : Permet de classifier les produits (ex : Antibiotiques, Antalgiques, Consommables).
* **`Fournisseur`** : Retranché dans la base pour tracer la provenance des lots de réapprovisionnement.

### 3. Le Bloc Commandes Fournisseurs (`CommandeFournisseur`, `CommandeFournisseurDetail`)
* **`CommandeFournisseur`** : Représente une commande générée pour un fournisseur par un Magasinier ou un Directeur. Elle possède un statut (en_attente, confirmee, livree, annulee) permettant le suivi.
* **`CommandeFournisseurDetail`** : Lignes détaillées d'une commande passée au fournisseur.

### 4. Le Bloc Facturation et Mouvements (`Vente`, `VenteDetail`, `Client`, `StockMouvement`)
* **`Vente`** : Représente la facture finale globale. Elle est liée de manière forte et obligatoire à un **`Utilisateur`** (le Caissier ou Directeur qui a initié l'encaissement) et de manière optionnelle à un **`Client`** (permettant la vente directe anonyme).
* **`VenteDetail`** : Ligne d'écriture détaillée de la facture. C'est une table de jonction associative avec composition forte (`*--`) reliant la vente globale et les différents médicaments achetés.
* **`StockMouvement`** : Traceur historique. Chaque opération sur le stock (qu'elle soit issue d'une vente FEFO, d'une réception de commande, ou d'un ajustement manuel de stock) produit une instance de mouvement indiquant la variation exacte de stock (`quantite_avant` $\rightarrow$ `quantite_apres`).
