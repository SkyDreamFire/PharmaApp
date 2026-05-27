# 🏛️ Architecture et Diagrammes UML - PharmaApp

Bienvenue dans le dossier de documentation d'architecture et de modélisation de **PharmaApp**. Ce dossier contient l'ensemble des diagrammes UML décrivant le fonctionnement, la structure et le déploiement de l'application de gestion de pharmacie.

Tous les diagrammes sont réalisés avec **Mermaid.js**, ce qui permet de les visualiser directement sous forme graphique dans vos outils favoris (VS Code avec extension Mermaid, GitHub, GitLab, etc.) tout en conservant une source textuelle facile à maintenir.

---

## 📋 Index des Diagrammes UML

Voici la liste des diagrammes disponibles, chacun se focalisant sur un aspect particulier du système :

| Diagramme | Type | Description | Lien |
| :--- | :--- | :--- | :--- |
| **Cas d'Utilisation** | Comportemental | Modélise les rôles et permissions des acteurs réels de l'application (**Directeur** et **Caissier**). | 🔗 [use_case_diagram.md](file:///c:/wamp64/www/pharma-app/docs/uml/use_case_diagram.md) |
| **Séquence** | Interaction | Illustre la dynamique de création d'une facture de vente et la logique d'ordonnancement FEFO (*First Expired, First Out*). | 🔗 [sequence_diagram.md](file:///c:/wamp64/www/pharma-app/docs/uml/sequence_diagram.md) |
| **Activité** | Comportemental | Décrit le flux logique décisionnel de validation d'un panier et de déduction intelligente des stocks. | 🔗 [activity_diagram.md](file:///c:/wamp64/www/pharma-app/docs/uml/activity_diagram.md) |
| **Composants** | Structurel | Représente la structure modulaire de l'application selon le modèle MVC/3-Tiers (PHP/Bootstrap/MySQL). | 🔗 [component_diagram.md](file:///c:/wamp64/www/pharma-app/docs/uml/component_diagram.md) |
| **Classes** | Structurel | Modélise les entités de données et leurs relations, ainsi que le connecteur de base de données Singleton. | 🔗 [class_diagram.md](file:///c:/wamp64/www/pharma-app/docs/uml/class_diagram.md) |
| **Déploiement** | Structurel | Représente la configuration physique et matérielle de l'application (Client, Apache + PHP, MySQL). | 🔗 [deployment_diagram.md](file:///c:/wamp64/www/pharma-app/docs/uml/deployment_diagram.md) |
| **État-Transition** | Comportemental | Suit le cycle de vie complet et les états d'un médicament dans le stock (disponible, périmé, faible, etc.). | 🔗 [state_diagram.md](file:///c:/wamp64/www/pharma-app/docs/uml/state_diagram.md) |

---

## 🛠️ Rappel sur le Système FEFO (*First Expired, First Out*)

L'une des fonctionnalités phares de **PharmaApp** est la gestion intelligente du stock par le principe du **FEFO**. Contrairement à un FIFO classique, le système sélectionne en priorité les médicaments dont la **date d'expiration est la plus proche**, quel que soit leur ordre d'arrivée physique dans la pharmacie.

Cette logique est modélisée en détail dans les diagrammes de **Séquence**, d'**Activité** et d'**État-Transition**.
