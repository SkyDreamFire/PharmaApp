# 🔄 Diagrammes de Séquence (Sequence Diagrams)

Ce document regroupe les diagrammes de séquence clés de l'application **FIANGEP Pharma**, illustrant la dynamique des interactions logiques du système.

---

## 1. Vente et Déstockage FEFO

Ce diagramme illustre la cinématique lors de la **création d'une facture de vente** par un **Caissier**, de l'interface JavaScript à la logique de déstockage **FEFO** (*First Expired, First Out*) et au reçu d'impression.

### 🧜‍♂️ Diagramme Mermaid

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

### 🔍 Analyse Technique du Flux
1. **Validation Front-End** : L'interface JS locale (`caissier/facture.php`) suit l'état du panier dans la variable globale `panier`. La fonction `ajouterAuPanier` s'assure qu'on ne dépasse pas le stock réel cumulé disponible (`stock_total = ancien_stock + nouveau_stock`).
2. **Soumission Sérialisée** : Pour éviter de multiples requêtes AJAX, l'application utilise une soumission classique de formulaire POST en injectant le panier sous forme de chaîne JSON dans un champ caché `<input type="hidden" name="articles">`.
3. **Transaction et Rétablissement** : La transaction PDO garantit l'atomicité. Si un médicament présente un défaut de stock ou si l'une des écritures de lignes échoue, l'exception est interceptée, et le bloc `catch` exécute un `$db->rollback()`.
4. **Ventilation FEFO dans la Boucle** : La fonction `effectuerSortieFEFO` récupère l'état courant en BDD et décide du lot prioritaire : si l'ancien stock expire avant, on retire en priorité de `ancien_stock`. Si la quantité demandée dépasse l'ancien stock, le reliquat est déduit de `nouveau_stock`.

---

## 2. Authentification et Session

Ce diagramme décrit la cinématique de **connexion d'un utilisateur** via `auth/login.php`, incluant la vérification CSRF, l'authentification sécurisée en base de données et la redirection par rôle.

### 🧜‍♂️ Diagramme Mermaid

```mermaid
sequenceDiagram
    autonumber
    actor U as 👤 Utilisateur
    participant F as 🖥️ Formulaire (login.php)
    participant C as 🧠 Fonctions Auth (functions.php)
    participant DB as 🗄️ Singleton Database (db.php)

    U->>F: Accède à la page de connexion
    activate F
    F->>C: isLoggedIn() (Vérifie si déjà connecté)
    activate C
    C-->>F: Retourne false
    deactivate C
    F->>F: Génère et stocke csrf_token dans $_SESSION
    F-->>U: Affiche la page de connexion (HTML)
    deactivate F

    U->>F: Remplit les identifiants & valide (POST)
    activate F
    
    F->>F: Valide le token CSRF (hash_equals)
    
    alt CSRF Invalide
        F-->>U: Affiche l'erreur CSRF (Veuillez actualiser)
    else CSRF Valide
        F->>DB: selectOne(SELECT ... FROM users WHERE username/email AND actif = 1)
        activate DB
        DB-->>F: Retourne les données de l'utilisateur (id, password_hash, role...)
        deactivate DB
        
        alt Utilisateur non trouvé
            F-->>U: Affiche "Identifiants ou mot de passe incorrect."
        else Utilisateur trouvé
            F->>F: password_verify(mot_de_passe, password_hash)
            
            alt Mot de passe incorrect
                F-->>U: Affiche "Identifiants ou mot de passe incorrect."
            else Mot de passe correct
                opt password_needs_rehash()
                    F->>DB: execute(UPDATE users SET password = newHash WHERE id)
                end
                
                F->>F: Initialise $_SESSION (user_id, user_role, prenom...)
                F->>C: redirectByRole()
                activate C
                C-->>U: Redirection HTTP (302 vers Dashboard)
                deactivate C
            end
        end
    end
    deactivate F
```

### 🔍 Analyse Technique du Flux
1. **Protection CSRF** : Pour se prémunir des attaques CSRF (Cross-Site Request Forgery), l'application génère un token aléatoire unique dans la session et l'inclut en champ masqué dans le formulaire de connexion. Ce token est vérifié avec `hash_equals()` pour éviter toute attaque de type canal auxiliaire ou timing.
2. **Authentification Hybride (Username / Email)** : La requête SQL cherche de manière transparente soit par le nom d'utilisateur, soit par l'email saisi, à condition que le compte soit marqué comme actif (`actif = 1`).
3. **Sécurité Cryptographique** : Les mots de passe ne sont jamais comparés en clair. L'application s'appuie sur `password_verify()` qui s'assure d'une vérification sécurisée contre le hash cryptographique (généralement Bcrypt). De plus, si l'algorithme par défaut de PHP évolue, `password_needs_rehash()` permet de réévaluer et de mettre à jour dynamiquement la clé de sécurité.
