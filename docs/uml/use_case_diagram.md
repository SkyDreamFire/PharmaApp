# 📊 Diagramme de Cas d'Utilisation (Use Case Diagram)

Ce diagramme modélise les fonctionnalités du système **PharmaApp** du point de vue des acteurs et utilisateurs. Il inclut désormais le nouveau rôle **Magasinier**.

---

## 🧜‍♂️ Diagramme Mermaid

```mermaid
graph LR
    %% Définition des styles visuels
    classDef actor fill:#1e293b,stroke:#3b82f6,stroke-width:2px,color:#fff,rx:8px,ry:8px;
    classDef usecase fill:#f8fafc,stroke:#10b981,stroke-width:1.5px,color:#0f172a,rx:20px,ry:20px;
    classDef system fill:#f1f5f9,stroke:#64748b,stroke-width:2px,stroke-dasharray: 5 5;

    %% Acteurs
    Directeur["👤 Directeur (Admin)"]:::actor
    Magasinier["👤 Magasinier"]:::actor
    Caissier["👤 Caissier"]:::actor

    %% Système
    subgraph Système PharmaApp
        direction TB
        
        %% Authentification
        UC_Auth("🔑 S'authentifier"):::usecase
        
        %% Fonctionnalités Caissier
        UC_Consult("💊 Consulter le catalogue"):::usecase
        UC_Client("👥 Gérer les clients"):::usecase
        UC_Vente("🧾 Créer une facture / Vente"):::usecase
        UC_Print("🖨️ Imprimer un reçu"):::usecase
        
        %% Fonctionnalités Magasinier (et Directeur)
        UC_StockMvt("📦 Gérer les ravitaillements et mouvements de stock"):::usecase
        UC_MedMag("💊 Gérer les médicaments (Ajouter/Modifier/Supprimer)"):::usecase
        UC_Fourn("🏢 Gérer les fournisseurs"):::usecase
        UC_Commande("📝 Générer des commandes fournisseurs"):::usecase
        
        %% Fonctionnalités Directeur
        UC_Cat("🏢 Gérer les catégories"):::usecase
        UC_Staff("👥 Gérer le personnel"):::usecase
        UC_Stats("📊 Consulter l'historique et stats"):::usecase
        UC_Alert("⚠️ Surveiller les alertes de stock"):::usecase
    end

    %% Héritage de rôles (Le Directeur possède toutes les permissions)
    Directeur -->|Hérite des droits de| Caissier
    Directeur -->|Hérite des droits de| Magasinier

    %% Liens Caissier
    Caissier --> UC_Auth
    Caissier --> UC_Consult
    Caissier --> UC_Client
    Caissier --> UC_Vente

    %% Liens Magasinier
    Magasinier --> UC_Auth
    Magasinier --> UC_StockMvt
    Magasinier --> UC_MedMag
    Magasinier --> UC_Fourn
    Magasinier --> UC_Commande

    %% Liens spécifiques Directeur
    Directeur --> UC_Cat
    Directeur --> UC_Staff
    Directeur --> UC_Stats
    Directeur --> UC_Alert

    %% Relations d'inclusion (Include)
    UC_Vente -.->|include| UC_Print

    style Système PharmaApp fill:#f8fafc,stroke:#cbd5e1,stroke-width:2px;
```

---

## 📝 Description des Cas d'Utilisation

### 👥 Acteurs
1. **Caissier** : Le rôle opérationnel. Il s'occupe des interactions directes avec les clients au comptoir (vente, facturation, encaissement).
2. **Magasinier** : Le rôle logistique. Il s'occupe des ravitaillements de stock des médicaments, gère le catalogue des médicaments, les fournisseurs, et génère les commandes.
3. **Directeur (Admin)** : Le rôle de gestion administrative et de supervision. Il hérite de toutes les capacités du Caissier et du Magasinier. Il possède le contrôle exclusif sur le personnel, les catégories et rapports financiers.

### 🎯 Cas d'Utilisation Clés
* **🔑 S'authentifier** : Premier niveau d'accès. L'application utilise `session_start()` et stocke le `user_id` et le `user_role` après vérification du hachage du mot de passe (`password_verify`).
* **🧾 Créer une facture / Enregistrer une vente** : Le caissier sélectionne des médicaments et les ajoute à un panier virtuel.
* **📦 Gérer les ravitaillements et mouvements de stock** : Permet au Magasinier ou Directeur d'enregistrer les entrées fournisseurs (FEFO), sorties et ajustements.
* **📝 Générer des commandes fournisseurs** : Le Magasinier (ou Directeur) sélectionne des médicaments en fonction du stock, associe un fournisseur et génère une commande (avec statuts : en_attente, confirmee, livree, annulee).
