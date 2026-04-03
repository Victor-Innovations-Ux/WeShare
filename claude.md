# Projet : WeGo – Décider en groupe où partir

## Objectif
Créer une application web permettant à un groupe de se partager les photos liées à un évènement.

## Stack Technique
- **Backend** : PHP 8.0+ (API REST)
- **Base de données** : fichier db stocké sur le serveur (bdd mysql)
- **Authentification** : JWT + Google OAuth 2.0
- **Frontend** : Vanilla JavaScript modulaire

## Directives d'Architecture

### Structure Backend (php-api/src/)
- **Api/** : Controllers (logique de routage) et Middleware (auth, CORS)
- **Models/** : Active Record patterns pour entités métier (User, Group, etc.)
- **Services/** : Logique métier pure ( AuthService, SessionService)
- **Lib/** : Utilitaires génériques (Database, Router, Validator, Request, Response)
- **Migrations/** : Scripts SQL versionnés + seeding
- **Config/** : Configuration centralisée (.env, constantes)

### Principes de Séparation
- **Controllers** : Validation inputs, orchestration, retour responses HTTP
- **Models** : Accès base de données (CRUD), logique d'entité simple
- **Services** : Algorithmes métier complexes (calculs, agrégations)
- **Lib** : Fonctions réutilisables sans couplage métier

### Conventions PHP
- Classes : `PascalCase` (ex: `AuthController`, `MatchingService`)
- Méthodes/fonctions : `camelCase` (ex: `getUserById`, `calculateMatch`)
- Fichiers : `PascalCase.php` (nom de classe = nom de fichier)
- Constantes : `UPPER_SNAKE_CASE`
- Tables SQL : `snake_case` (ex: `user_groups`, `quiz_answers`)

### Standards de Code
- Pas de code dupliqué - extraire dans `/Lib` ou Services
- Commentaires concis et pertinents (intention, pas paraphrase)
- Requêtes préparées PDO systématiques (protection SQL injection)
- Validation stricte des inputs (côté Controller via Validator)
- Type hints PHP 8+ sur toutes les signatures de méthodes
- Return types explicites sur toutes les méthodes publiques

### API REST
- Routes organisées par domaine : `/api/auth`, `/api/groups`, `/api/quiz`, `/api/results`
- Format JSON strict pour requêtes et réponses
- Codes HTTP sémantiques (200, 201, 400, 401, 403, 404, 500)
- Middleware d'authentification JWT sur routes protégées
- CORS configuré pour frontend mobile
- Pas de libs superflues ni d'abstractions inutiles

### Sécurité
- JWT signé avec secret robuste (.env)
- Tokens stockés côté client (localStorage ou cookies HttpOnly)
- Validation systématique des inputs utilisateur
- Protection contre CSRF (SameSite cookies si applicable)
- Headers de sécurité (X-Content-Type-Options, X-Frame-Options)
- Pas de données sensibles dans les logs

## Règles de Dev
- **PR > commits directs sur main** (sauf hotfix critiques)
- Chaque endpoint accompagné d'exemples de requête/réponse dans `/docs`
- Logique de matching encapsulée dans `MatchingService` (testable, indépendant)
- Aucune fonction monolithique dans les Controllers - tout doit être factorisé
- Aucun code mort, aucune dépendance inutilisée : nettoyage systématique
- Migrations SQL versionnées (jamais de modifications directes en DB)
- Chaque Service/Model doit avoir une responsabilité unique et claire

## Documentation
- README.md à jour à chaque évolution majeure
- Documentation API REST dans `/php-api/docs/` (exemples curl)
- Schéma de base de données dans `/php-api/docs/database-schema.md`
- Commentaires de haut niveau uniquement (intention, pas explication triviale)
- Variables d'environnement documentées dans `.env.example`

## Philosophie
> Minimalisme technique, clarté structurelle, cohérence de code.
> Chaque ligne doit avoir une raison d'exister.

**Valeurs fondamentales** :
- Code maintenable > cleverness
- Séparation des responsabilités > monolithisme
- Validation stricte > confiance aveugle
- Documentation concise > verbosité
- Testabilité > couplage
