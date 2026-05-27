# 🔄 Diagramme de Séquence (Sequence Diagram)

Ce diagramme de séquence illustre la cinématique d'interaction lors de la **création d'une facture de vente** par un **Caissier**, depuis l'interface utilisateur JavaScript jusqu'à l'exécution de la logique de déstockage **FEFO** (*First Expired, First Out*) et la génération du reçu d'impression.

---

## 🧜‍♂️ Diagramme Mermaid

```mermaid
sequenceDiagram
    autonumber
    actor C as 👤 Caissier
    participant I as 🖥️ Interface Facture (JS/HTML)
    participant P as ⚙️ Contrôleur PHP (facture.php)
    participant FEFO as 🧠 Logique FEFO (functions.php)
    participant DB as 🗄️ Singleton Database (db.php)
    participant PrintAPI as 🖨️ API d'Impression (ventes.php)

    C->>I: Sélectionne un médicament & clique sur "Ajouter"
    Note over I: Valide la quantité par rapport<br/>au stock local disponible
    I-->>C: Affiche le panier mis à jour (HTML)

    C->>I: Sélectionne le client, paiement et clique sur "Valider"
    I->>C: Boîte de dialogue de confirmation (JS confirm)
    C->>I: Confirme la validation

    Note over I: Crée un formulaire virtuel en JS<br/>et sérialise les articles en JSON
    I->>P: POST caissier/facture.php (action = create_facture)
    activate P
    
    P->>DB: beginTransaction() (Démarre la transaction SQL)
    
    P->>P: generateInvoiceNumber() (Génère le N° unique ex: FAC202605270001)
    
    P->>DB: execute(INSERT INTO ventes ...) (Enregistre l'entête de vente)
    
    P->>DB: lastInsertId() (Récupère l'ID généré)
    DB-->>P: Retourne vente_id
    
    loop Pour chaque article du panier
        P->>DB: execute(INSERT INTO vente_details ...) (Enregistre la ligne de facture)
        
        P->>FEFO: effectuerSortieFEFO(db, medicament_id, quantite, user_id, motif)
        activate FEFO
        
        FEFO->>DB: select(SELECT * FROM medicaments WHERE id = :id)
        DB-->>FEFO: Retourne ancien_stock, nouveau_stock et dates d'expiration
        
        FEFO->>FEFO: determinerStockPrioritaire(medicament)
        Note over FEFO: Compare date_expiration_ancien<br/>et date_expiration_nouveau.<br/>Sélectionne le lot expirant en premier.
        
        FEFO->>FEFO: Calcule la ventilation des stocks (Ancien vs Nouveau lot)
        
        FEFO->>DB: execute(UPDATE medicaments SET stocks ...) (Met à jour les lots)
        
        FEFO->>DB: execute(INSERT INTO stock_mouvements ...) (Historise la sortie)
        
        FEFO-->>P: Succès (true)
        deactivate FEFO
    end
    
    P->>DB: commit() (Valide la transaction SQL)
    P-->>I: HTML de réponse + script JS d'ouverture d'impression
    deactivate P
    
    Note over I: Déclenche window.open() dans un nouvel onglet
    I->>PrintAPI: GET api/ventes.php?action=print&id=vente_id
    activate PrintAPI
    PrintAPI->>DB: select(...) (Récupère entête + lignes de la vente)
    DB-->>PrintAPI: Données de la facture
    PrintAPI-->>C: Affiche le reçu thermique à l'écran (PDF / Impression)
    deactivate PrintAPI
```

---

## 🔍 Analyse Technique du Flux

1. **Validation Front-End (Étapes 1-2)** :
   L'interface JS locale (`caissier/facture.php`) suit l'état du panier dans la variable globale `panier`. La fonction `ajouterAuPanier` s'assure qu'on ne dépasse pas le stock réel cumulé disponible (`stock_total = ancien_stock + nouveau_stock`).

2. **Soumission Sérialisée (Étape 6)** :
   Pour éviter de multiples requêtes AJAX asynchrones complexes, l'application utilise une soumission classique de formulaire POST en injectant le panier sous forme de chaîne JSON dans un champ caché `<input type="hidden" name="articles">`.

3. **Transaction et Rétablissement (Étapes 7-21)** :
   La transaction PDO garantit l'atomicité. Si un médicament présente un défaut de stock ou si l'une des écritures de lignes échoue, l'exception est interceptée, et le bloc `catch` exécute un `$db->rollback()` pour annuler toutes les modifications précédentes (comme la création de la ligne `ventes` ou des premiers détails).

4. **Ventilation FEFO dans la Boucle (Étapes 13-19)** :
   C'est le cœur de l'intelligence métier. La fonction `effectuerSortieFEFO` récupère l'état courant en BDD et décide du lot prioritaire.
   * Si l'ancien stock expire avant, on retire en priorité de `ancien_stock`. Si la quantité demandée dépasse l'ancien stock, le reliquat est déduit de `nouveau_stock`.
   * Cette mise à jour est immédiatement répercutée via un `UPDATE` en SQL, et un mouvement de stock avec le type `sortie` est tracé.
