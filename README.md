# 🏥 Pharmacie Management System

Une application web complète pour la gestion d'une pharmacie, développée avec PHP natif, MySQL et Bootstrap 5.

## 📋 Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation avec XAMPP](#installation-avec-xampp)
- [Structure du projet](#structure-du-projet)
- [Utilisation](#utilisation)
- [Comptes de démonstration](#comptes-de-démonstration)
- [Technologies utilisées](#technologies-utilisées)

## ✨ Fonctionnalités

### 🔐 Authentification et Rôles
- **Directeur** : Accès complet à toutes les fonctionnalités
- **Caissier** : Accès limité aux ventes, clients, stock et facturation

### 💊 Gestion des Médicaments
- CRUD complet des médicaments
- Gestion des catégories et fournisseurs
- Suivi des stocks avec alertes automatiques
- Gestion des dates d'expiration
- Code-barres et prix d'achat/vente

### 📊 Gestion des Stocks
- Mouvements de stock (entrées/sorties/ajustements)
- Alertes de stock faible
- Historique des mouvements
- Seuils de stock minimum configurables

### 🧾 Facturation et Ventes
- Création de factures
- Gestion des clients
- Historique des ventes
- Différents modes de paiement
- Génération de numéros de facture automatiques

### 👥 Gestion du Personnel (Directeur uniquement)
- Création et gestion des utilisateurs
- Attribution des rôles
- Historique des activités

### 🏢 Gestion des Fournisseurs (Directeur uniquement)
- CRUD des fournisseurs
- Informations de contact complètes

### 📱 Interface Responsive
- Design moderne avec Bootstrap 5
- Mode sombre automatique
- Interface adaptée mobile/tablette/desktop
- Composants réutilisables

## 🔧 Prérequis

- **XAMPP** (Apache + MySQL + PHP 7.4+)
- Navigateur web moderne
- 500 MB d'espace disque libre

## 🚀 Installation avec XAMPP

### Étape 1 : Télécharger et installer XAMPP
1. Téléchargez XAMPP depuis [https://www.apachefriends.org](https://www.apachefriends.org)
2. Installez XAMPP dans le répertoire par défaut (`C:\xampp` sur Windows)

### Étape 2 : Démarrer les services
1. Ouvrez le **XAMPP Control Panel**
2. Démarrez **Apache** et **MySQL**
3. Vérifiez que les services sont en cours d'exécution (voyants verts)

### Étape 3 : Configurer la base de données
1. Ouvrez votre navigateur et allez sur `http://localhost/phpmyadmin`
2. Créez une nouvelle base de données nommée `pharma_db`
3. Importez le fichier `database.sql` fourni :
   - Cliquez sur la base `pharma_db`
   - Allez dans l'onglet **Importer**
   - Sélectionnez le fichier `database.sql`
   - Cliquez sur **Exécuter**

### Étape 4 : Installer l'application
1. Copiez le dossier `pharma-app` dans le répertoire `htdocs` de XAMPP :
   ```
   C:\xampp\htdocs\pharma-app\
   ```

2. Vérifiez que la structure est correcte :
   ```
   C:\xampp\htdocs\pharma-app\
   ├── assets/
   ├── auth/
   ├── admin/
   ├── caissier/
   ├── includes/
   ├── index.php
   └── README.md
   ```

### Étape 5 : Configuration de la base de données
1. Ouvrez le fichier `includes/db.php`
2. Vérifiez la configuration de connexion :
   ```php
   private $host = 'localhost';
   private $dbname = 'pharma_db';
   private $username = 'root';
   private $password = '';
   ```

### Étape 6 : Accéder à l'application
1. Ouvrez votre navigateur
2. Allez sur `http://localhost/pharma-app`
3. Vous serez automatiquement redirigé vers la page de connexion

## 🏗️ Structure du projet

```
pharma-app/
│
├── assets/                  # Ressources statiques
│   ├── css/
│   │   └── style.css       # Styles personnalisés
│   └── js/
│       └── app.js          # JavaScript principal
│
├── includes/               # Fichiers partagés
│   ├── db.php             # Connexion base de données
│   ├── functions.php      # Fonctions utilitaires
│   ├── header.php         # En-tête HTML
│   └── footer.php         # Pied de page HTML
│
├── auth/                  # Authentification
│   ├── login.php         # Page de connexion
│   └── logout.php        # Déconnexion
│
├── admin/                # Interface Directeur
│   ├── dashboard.php     # Tableau de bord
│   ├── produits.php      # Gestion médicaments
│   ├── fournisseurs.php  # Gestion fournisseurs
│   ├── personnel.php     # Gestion personnel
│   ├── ventes.php        # Vue des ventes
│   └── stock_alert.php   # Alertes de stock
│
├── caissier/             # Interface Caissier
│   ├── dashboard.php     # Tableau de bord
│   ├── produits.php      # Consultation médicaments
│   ├── clients.php       # Gestion clients
│   ├── facture.php       # Facturation
│   └── stock_mouvement.php # Mouvements stock
│
├── database.sql          # Script de création BDD
├── index.php            # Page d'accueil
└── README.md           # Documentation
```

## 🎯 Utilisation

### Connexion
1. Accédez à `http://localhost/pharma-app`
2. Utilisez les identifiants de démonstration (voir section suivante)
3. Vous serez redirigé vers le dashboard approprié selon votre rôle

### Fonctionnalités Directeur
- **Dashboard** : Vue d'ensemble complète avec statistiques
- **Médicaments** : Ajouter, modifier, supprimer des médicaments
- **Fournisseurs** : Gérer les fournisseurs et leurs informations
- **Personnel** : Créer et gérer les comptes utilisateurs
- **Ventes** : Consulter toutes les ventes et statistiques
- **Alertes** : Surveiller les stocks faibles

### Fonctionnalités Caissier
- **Dashboard** : Vue des ventes personnelles et alertes
- **Médicaments** : Consulter le catalogue des médicaments
- **Clients** : Ajouter et gérer les clients
- **Facturation** : Créer des factures et gérer les ventes
- **Stock** : Enregistrer les mouvements de stock

## 👤 Comptes de démonstration

### Directeur
- **Identifiant** : `admin`
- **Mot de passe** : `password`
- **Email** : `admin@pharmacie.com`

### Caissier
- **Identifiant** : `caissier1`
- **Mot de passe** : `password`
- **Email** : `caissier@pharmacie.com`

## 🛠️ Technologies utilisées

### Frontend
- **HTML5** : Structure sémantique
- **CSS3** : Styles avancés avec variables CSS
- **Bootstrap 5** : Framework CSS responsive
- **JavaScript** : Interactivité et validation

### Backend
- **PHP 7.4+** : Programmation orientée objet
- **MySQL** : Base de données relationnelle
- **Sessions PHP** : Gestion de l'authentification

### Sécurité
- **Password hashing** : Mots de passe hachés avec `password_hash()`
- **Requêtes préparées** : Protection contre l'injection SQL
- **Validation** : Côté client et serveur
- **Sessions sécurisées** : Gestion des rôles et permissions

## 🔒 Sécurité

L'application implémente plusieurs mesures de sécurité :
- Hachage des mots de passe avec `password_hash()`
- Requêtes préparées pour éviter l'injection SQL
- Validation des données en entrée
- Gestion des sessions sécurisées
- Contrôle d'accès basé sur les rôles
- Protection CSRF (optionnelle)

## 📱 Responsive Design

L'application est entièrement responsive et s'adapte à :
- **Mobile** : < 768px
- **Tablette** : 768px - 1024px  
- **Desktop** : > 1024px

## 🎨 Personnalisation

### Thèmes
L'application supporte automatiquement :
- Mode clair (par défaut)
- Mode sombre (selon les préférences système)

### Couleurs
Les couleurs principales peuvent être modifiées dans `assets/css/style.css` :
```css
:root {
    --primary-color: #0d6efd;
    --success-color: #198754;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
}
```

## 🐛 Dépannage

### Erreur de connexion à la base de données
1. Vérifiez que MySQL est démarré dans XAMPP
2. Confirmez que la base `pharma_db` existe
3. Vérifiez les identifiants dans `includes/db.php`

### Page blanche
1. Activez l'affichage des erreurs PHP dans `php.ini`
2. Vérifiez les logs d'erreur Apache
3. Assurez-vous que tous les fichiers sont présents

### Problèmes de permissions
Sur Linux/Mac, définissez les permissions appropriées :
```bash
chmod -R 755 /opt/lampp/htdocs/pharma-app
```

## 📞 Support

Pour toute question ou problème :
1. Vérifiez d'abord cette documentation
2. Consultez les logs d'erreur XAMPP
3. Assurez-vous que votre configuration XAMPP est correcte

## 📄 Licence

Ce projet est développé à des fins éducatives et de démonstration.

---

🚀 **Prêt à démarrer ?** Suivez les étapes d'installation et commencez à gérer votre pharmacie !