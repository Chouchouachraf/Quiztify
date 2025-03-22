# QuizTify 🎓

## Description

QuizTify est une application web complète de gestion d'examens en ligne, conçue pour permettre aux étudiants de passer leurs examens de manière professionnelle. Développée avec HTML, CSS, Bootstrap, PHP et JavaScript, cette plateforme offre une expérience utilisateur intuitive avec des interfaces spécifiques pour les administrateurs, les enseignants et les étudiants.

## Caractéristiques principales

### Interface Administrateur
- **Gestion des utilisateurs**: Création, modification et suppression des comptes étudiants et enseignants
- **Gestion des classes**: Configuration et suivi des classes virtuelles
- **Gestion des examens**: Contrôle complet du cycle de vie des examens
- **Tableau de bord analytique**: Statistiques en temps réel sur l'activité de la plateforme
- **Sécurité renforcée**: Contrôle d'accès robuste avec chiffrement des mots de passe

### Interface Enseignant
- **Tableau de bord**: Analyse en temps réel des examens, classes et tentatives
- **Gestion des classes**: Organisation des classes virtuelles par département
- **Création d'examens**: Constructeur d'examens multi-étapes avec différents types de questions
- **Évaluation et notation**: Notation automatisée avec possibilité de correction manuelle
- **Rapports et analyses**: Tableaux de bord complets de performance des examens

### Interface Étudiant
- **Examens disponibles**: Affichage en temps réel des examens et délais
- **Environnement d'examen sécurisé**: Détection de triche et chronomètre en temps réel
- **Feedback immédiat**: Affichage immédiat des résultats après l'examen
- **Suivi de performance**: Historique des tentatives et progression
- **Mode détente**: Jeu Tetris intégré pour réduire le stress

## Fonctionnalités techniques
- Mode clair/sombre pour une meilleure accessibilité
- Design responsive adapté à tous les appareils
- Système de rôles (admin, enseignant, étudiant)
- Sécurité renforcée contre les vulnérabilités web courantes
- Interface utilisateur intuitive et conviviale

## Installation

1. Clonez ce dépôt
   ```
   git clone https://github.com/votre-username/quiztify.git
   ```

2. Importez la base de données dans MySQL
   ```
   mysql -u username -p nom_de_base < database/quiztify.sql
   ```

3. Configurez les paramètres de connexion dans `config/database.php`

4. Lancez l'application sur votre serveur PHP

## Captures d'écran
*(À ajouter - Insérez ici des captures d'écran des différentes interfaces)*

## Technologies utilisées
- HTML5
- CSS3
- Bootstrap
- PHP
- JavaScript
- MySQL

## Prérequis
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Serveur web (Apache, Nginx)

## Contribution
Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou à soumettre une pull request.

## Licence
Ce projet est sous licence MIT - voir le fichier LICENSE pour plus de détails.

## Contact
Pour toute question ou suggestion, n'hésitez pas à me contacter.

---

*QuizTify - Mon premier projet en tant qu'étudiant développeur full-stack*