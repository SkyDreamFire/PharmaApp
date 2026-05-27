# 🌐 Diagramme de Déploiement (Deployment Diagram)

Ce diagramme de déploiement illustre l'infrastructure matérielle et logicielle sur laquelle l'application **PharmaApp** est installée et exécutée. Il décrit la topologie physique sous forme d'une architecture **3-Tiers**, courante dans les environnements de type **WAMP** (Windows, Apache, MySQL, PHP) ou équivalents (XAMPP, Linux/LAMP, macOS/MAMP).

---

## 🧜‍♂️ Diagramme Mermaid

```mermaid
flowchart TB
    %% Définitions de styles
    classDef device fill:#eff6ff,stroke:#1d4ed8,stroke-width:2px,color:#1e3a8a,rx:8px,ry:8px;
    classDef nodeServer fill:#fdf4ff,stroke:#a21caf,stroke-width:2px,color:#4a044e,rx:8px,ry:8px;
    classDef runEnv fill:#faf5ff,stroke:#d946ef,stroke-width:1.5px,stroke-dasharray: 5 5,color:#701a75;
    classDef artifact fill:#fafafa,stroke:#52525b,stroke-width:1.5px,color:#18181b;
    classDef db fill:#ecfdf5,stroke:#047857,stroke-width:2px,color:#064e3b;

    %% Nœud Client
    subgraph Client_Device["💻 Nœud Matériel : Terminal Client (PC, Tablette, Mobile)"]
        direction LR
        Browser["🌐 Navigateur Web<br/>(Chrome, Firefox, Safari, Edge)"]:::device
    end

    %% Nœud Serveur Application (WAMP / Apache + PHP)
    subgraph App_Server["🖥️ Nœud Matériel : Serveur d'Application (Hôte WAMP/XAMPP)"]
        direction TB
        
        subgraph Web_Server["⚙️ Serveur Web Node : Apache HTTP Server"]
            direction TB
            PHPEngine["🚀 Environnement d'Exécution : PHP 7.4+ Runtime"]:::runEnv
            
            subgraph WebApp_Files["📦 Artefact : Fichiers Sources PharmaApp"]
                direction LR
                PHPFiles["📄 Scripts PHP (*.php)"]:::artifact
                JSFiles["📄 Scripts JS (*.js)"]:::artifact
                CSSFiles["📄 Feuilles CSS (*.css)"]:::artifact
            end
            
            WebApp_Files -.->|exécuté par| PHPEngine
        end
    end

    %% Nœud Serveur Base de données (MySQL)
    subgraph DB_Server["🗄️ Nœud Matériel : Serveur de Base de Données (MySQL / MariaDB Hôte)"]
        direction TB
        
        subgraph DBMS["🔌 SGBDR Node : MySQL Server Engine"]
            DBSchema[("🗃️ Base de données : pharma_db<br/>(Tables, Clés, Index, Contraintes)")]:::db
        end
    end

    %% Connexions physiques / protocoles de communication
    Browser ====>|Procolole : HTTP / HTTPS<br/>Ports : 80 / 443| Web_Server
    PHPEngine ====>|Protocole : TCP/IP (PDO Driver)<br/>Port par défaut : 3306 (localhost)| DBMS

    %% Styles des subgraphs globaux
    style Client_Device fill:#f8fafc,stroke:#3b82f6,stroke-width:2px;
    style App_Server fill:#fdf4ff,stroke:#c084fc,stroke-width:2px;
    style DB_Server fill:#f0fdf4,stroke:#4ade80,stroke-width:2px;
```

---

## 📝 Explication de l'Infrastructure et des Nœuds

### 1. Le Terminal Client (`Terminal Client`)
Il s'agit de tout appareil connecté (ordinateur de bureau de la pharmacie, tablette d'inventaire, smartphone du gérant) équipé d'un **navigateur moderne**. Aucune installation locale n'est requise côté client, ce qui rend la maintenance de l'application extrêmement agile (les mises à jour sont immédiatement disponibles pour tous les utilisateurs dès leur déploiement sur le serveur d'application).

### 2. Le Serveur d'Application (`Serveur d'Application`)
Hébergé sous un environnement WAMP ou XAMPP :
* **Serveur Web Apache** : Reçoit les requêtes HTTP/HTTPS du client, sert directement les fichiers statiques (les scripts JavaScript `assets/js/` et styles CSS `assets/css/`) et redirige les requêtes PHP vers l'interpréteur.
* **Moteur d'Exécution PHP 7.4+** : Compile et exécute les scripts côté serveur, traite les sessions de connexion et produit le code HTML dynamique renvoyé au client.

### 3. Le Serveur de Base de Données (`Serveur de Base de Données`)
Géré par l'instance **MySQL/MariaDB** :
* Dans une configuration locale de développement (comme configuré dans `includes/db.php`), le serveur MySQL tourne sur la même machine physique (`localhost` / `127.0.0.1`).
* En production, le nœud MySQL peut être dissocié sur un serveur de base de données dédié pour des raisons de performance et de sécurité renforcée, la liaison s'effectuant en réseau interne via le driver PDO en TCP/IP (port `3306`).
* Il héberge la base de données relationnelle structurée `pharma_db` qui maintient l'intégrité transactionnelle (ACID) indispensable pour assurer qu'aucune transaction de vente ne soit enregistrée si la mise à jour des stocks échoue.
