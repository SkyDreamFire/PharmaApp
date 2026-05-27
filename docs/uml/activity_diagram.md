# 📈 Diagramme d'Activité (Activity Diagram)

Ce diagramme d'activité détaille le flux opérationnel et logique lors de **la validation d'une vente et de la sortie intelligente de stock** selon le protocole **FEFO** (*First Expired, First Out*). Il décrit à la fois les actions côté client (Caissier) et les processus décisionnels côté serveur (PHP/MySQL).

---

## 🧜‍♂️ Diagramme Mermaid

```mermaid
flowchart TD
    %% Définitions de style
    classDef start_end fill:#1e293b,stroke:#0f172a,stroke-width:2px,color:#fff,rx:20px,ry:20px;
    classDef action fill:#f8fafc,stroke:#3b82f6,stroke-width:1.5px,color:#0f172a;
    classDef decision fill:#fffbeb,stroke:#d97706,stroke-width:1.5px,color:#78350f,shape:diamond;
    classDef storage fill:#ecfdf5,stroke:#10b981,stroke-width:1.5px,color:#064e3b;

    Start([🟢 Début du processus de vente]):::start_end
    
    %% Section Client
    subgraph Côté Caissier (Front-End)
        A1[Rechercher un médicament dans la liste]:::action
        A2[Cliquer sur "Ajouter au Panier"]:::action
        
        D1{Stock total cumulé<br/>suffisant ?}:::decision
        
        A3[Afficher une alerte de stock insuffisant]:::action
        A4[Mettre à jour le panier & recalculer le total]:::action
        A5[Optionnel: Associer un client et saisir une remise]:::action
        A6[Sélectionner le mode de paiement]:::action
        A7[Cliquer sur "Valider la Facture" & Confirmer]:::action
    end

    %% Section Serveur
    subgraph Côté Serveur (Back-End PHP & FEFO)
        S1[Recevoir la requête POST et décoder le JSON]:::action
        S2[Débuter la transaction SQL PDO]:::action
        S3[Générer le N° de facture unique]:::action
        S4[Insérer l'entête dans la table 'ventes']:::action
        S5[Récupérer le 'vente_id' généré]:::storage
        
        %% Début de boucle
        LoopStart{Pour chaque article<br/>dans le panier ?}:::decision
        
        S6[Insérer la ligne dans 'vente_details']:::action
        S7[Récupérer les lots du médicament de la BDD]:::action
        
        D2{Quel lot expire<br/>en premier ? <br/>FEFO}:::decision
        
        %% FEFO Branche Ancien Stock
        FEFO_A1[Retirer de l'ancien stock]:::action
        D_StockA{Reste-t-il de la<br/>quantité à sortir ?}:::decision
        FEFO_A2[Retirer le reste du nouveau stock]:::action
        
        %% FEFO Branche Nouveau Stock
        FEFO_N1[Retirer du nouveau stock]:::action
        D_StockN{Reste-t-il de la<br/>quantité à sortir ?}:::decision
        FEFO_N2[Retirer le reste de l'ancien stock]:::action
        
        S8[Calculer et mettre à jour stock_actuel]:::action
        S9[Mettre à jour la table 'medicaments']:::action
        S10[Enregistrer la ligne dans 'stock_mouvements']:::storage
        
        %% Fin de boucle / validation
        LoopEnd{Une erreur est-elle<br/>survenue ?}:::decision
        
        S_Rollback[Exécuter ROLLBACK<br/>Annuler les écritures]:::action
        S_Commit[Exécuter COMMIT<br/>Valider les écritures]:::action
        
        A8[Ouvrir l'onglet de reçu de facture<br/>api/ventes.php?action=print]:::action
    end

    End([🔴 Fin du processus]):::start_end

    %% Enchaînements
    Start --> A1
    A1 --> A2
    A2 --> D1
    D1 -- Non --> A3
    A3 --> A1
    D1 -- Oui --> A4
    A4 --> A5
    A5 --> A6
    A6 --> A7
    
    A7 --> S1
    S1 --> S2
    S2 --> S3
    S3 --> S4
    S4 --> S5
    S5 --> LoopStart
    
    %% Boucle
    LoopStart -- Ligne suivante --> S6
    S6 --> S7
    S7 --> D2
    
    %% Condition FEFO Ancien
    D2 -- Ancien Stock Expirant d'abord --> FEFO_A1
    FEFO_A1 --> D_StockA
    D_StockA -- Oui --> FEFO_A2
    D_StockA -- Non --> S8
    FEFO_A2 --> S8
    
    %% Condition FEFO Nouveau
    D2 -- Nouveau Stock Expirant d'abord --> FEFO_N1
    FEFO_N1 --> D_StockN
    D_StockN -- Oui --> FEFO_N2
    D_StockN -- Non --> S8
    FEFO_N2 --> S8
    
    S8 --> S9
    S9 --> S10
    S10 --> LoopStart
    
    %% Fin de boucle
    LoopStart -- Plus de lignes --> LoopEnd
    
    LoopEnd -- Oui --> S_Rollback
    S_Rollback --> A3
    
    LoopEnd -- Non --> S_Commit
    S_Commit --> A8
    A8 --> End

    %% Styles supplémentaires pour le rendu
    style Côté Caissier (Front-End) fill:#eff6ff,stroke:#bfdbfe,stroke-width:2px;
    style Côté Serveur (Back-End PHP & FEFO) fill:#fafafa,stroke:#e4e4e7,stroke-width:2px;
```

---

## 📝 Explication de l'Algorithme de Sortie FEFO

Dans le diagramme d'activité ci-dessus, le bloc décisionnel **"Quel lot expire en premier ? FEFO"** illustre le cœur du traitement de déstockage :

1. **Entrée** : Une quantité demandée $Q$ pour un médicament $M$.
2. **Récupération des Données** :
   Le système lit les valeurs en BDD :
   - `ancien_stock` (Quantité du premier lot $Q_{A}$) et sa date `date_expiration_ancien` ($T_{A}$).
   - `nouveau_stock` (Quantité du second lot $Q_{N}$) et sa date `date_expiration_nouveau` ($T_{N}$).
3. **Logique Comparative** :
   - Si les deux stocks contiennent des produits, le système compare $T_{A}$ et $T_{N}$.
   - Si $T_{A} \le T_{N}$ : L'ancien lot expire en premier. Le système affecte la priorité à l'ancien stock.
     - On sort $\min(Q, Q_{A})$ de l'ancien stock.
     - S'il reste une quantité à sortir (c'est-à-dire si $Q > Q_{A}$), on sort le reste ($Q - Q_{A}$) du nouveau stock.
   - Si $T_{N} < T_{A}$ : Le nouveau lot expire en premier. Le système affecte la priorité au nouveau stock.
     - On sort $\min(Q, Q_{N})$ du nouveau stock.
     - S'il reste une quantité à sortir (si $Q > Q_{N}$), on sort le reste ($Q - Q_{N}$) de l'ancien stock.
4. **Enregistrement physique** :
   Le stock global `stock_actuel` est mis à jour ($Q_{A} + Q_{N} - Q$), et les nouvelles valeurs de `ancien_stock` et `nouveau_stock` sont sauvegardées. Enfin, un mouvement de stock historise la transaction.
