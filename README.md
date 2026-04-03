# WeShare - Partagez vos photos d'événements

WeShare est une application web qui permet à des groupes de partager facilement leurs photos d'événements. Créez un groupe, partagez le lien, et tout le monde peut ajouter ses photos !

## Fonctionnalités

- ✅ **Création de groupes** : Connectez-vous avec Google et créez un groupe pour votre événement
- ✅ **Partage simple** : Un code unique à 8 caractères pour inviter vos amis
- ✅ **Ajout de photos** : Chaque participant peut ajouter ses photos
- ✅ **Identification** : Voir qui a ajouté chaque photo
- ✅ **Sans inscription** : Les invités n'ont besoin que de leur nom pour participer

## Prérequis

- PHP 8.0 ou supérieur
- MySQL 5.7 ou supérieur
- Composer
- Un serveur web (Apache, Nginx, ou PHP built-in server)

## Installation

### 1. Cloner le projet

```bash
git clone <votre-repo>
cd WeShare
```

### 2. Installer les dépendances PHP

```bash
cd php-api
composer install
```

### 3. Configurer l'environnement

Copiez le fichier `.env.example` vers `.env` et configurez vos paramètres :

```bash
cp .env.example .env
```

Éditez le fichier `.env` :

```env
# Base de données
DB_HOST=localhost
DB_NAME=weshare
DB_USER=root
DB_PASSWORD=votre_mot_de_passe

# JWT
JWT_SECRET=changez-cette-cle-secrete-en-production

# Google OAuth (optionnel pour développement)
GOOGLE_CLIENT_ID=votre-client-id
GOOGLE_CLIENT_SECRET=votre-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/auth/google/callback
```

### 4. Créer la base de données

Créez d'abord la base de données MySQL :

```sql
CREATE DATABASE weshare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Puis exécutez les migrations :

```bash
cd php-api
php Migrations/migrate.php
```

### 5. Configuration Google OAuth (Optionnel)

Pour activer la connexion Google :

1. Allez sur [Google Cloud Console](https://console.cloud.google.com/)
2. Créez un nouveau projet ou sélectionnez un existant
3. Activez l'API Google+
4. Créez des identifiants OAuth 2.0
5. Ajoutez `http://localhost:8000/api/auth/google/callback` aux URIs de redirection autorisées
6. Copiez le Client ID et Client Secret dans votre fichier `.env`

## Lancement du projet

### Méthode 1 : Serveur PHP intégré (Développement)

Depuis le répertoire racine du projet :

```bash
# Démarrer le serveur API (port 8000)
php -S localhost:8000 -t php-api

# Dans un autre terminal, démarrer le serveur frontend (port 8080)
php -S localhost:8080 -t public
```

Accédez à l'application sur : http://localhost:8080

### Méthode 2 : Apache/Nginx (Production)

Configurez votre serveur web pour :
- Pointer le domaine principal vers le dossier `public/`
- Créer un sous-domaine ou un path `/api` pointant vers `php-api/`

Exemple de configuration Apache :

```apache
# Frontend
<VirtualHost *:80>
    ServerName weshare.local
    DocumentRoot /path/to/WeShare/public
</VirtualHost>

# API
<VirtualHost *:8000>
    ServerName weshare.local
    DocumentRoot /path/to/WeShare/php-api

    <Directory /path/to/WeShare/php-api>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## Utilisation

1. **Créer un groupe** :
   - Cliquez sur "Créer un groupe"
   - Connectez-vous avec Google
   - Donnez un nom à votre événement
   - Récupérez le code de partage

2. **Rejoindre un groupe** :
   - Cliquez sur "Rejoindre un groupe"
   - Entrez le code partagé
   - Entrez votre nom (pas besoin de compte Google)

3. **Ajouter des photos** :
   - Une fois dans le groupe, cliquez sur "Ajouter une photo"
   - Sélectionnez vos photos (glisser-déposer supporté)
   - Les photos sont automatiquement identifiées avec votre nom

## Structure du projet

```
WeShare/
├── php-api/               # Backend API PHP
│   ├── src/
│   │   ├── Api/          # Controllers et Middleware
│   │   ├── Models/       # Modèles de données
│   │   ├── Services/     # Services métier
│   │   ├── Lib/          # Bibliothèques utilitaires
│   │   └── Config/       # Configuration
│   ├── Migrations/       # Scripts SQL
│   ├── uploads/          # Photos uploadées
│   └── index.php         # Point d'entrée API
│
├── public/               # Frontend
│   ├── css/             # Styles
│   ├── js/              # JavaScript
│   ├── index.html       # Page d'accueil
│   └── group.html       # Page du groupe
│
└── README.md
```

## API Endpoints

- `GET /api` - Informations sur l'API
- `GET /api/auth/google/login` - Initier connexion Google
- `GET /api/auth/google/callback` - Callback Google OAuth
- `POST /api/auth/join` - Rejoindre un groupe
- `GET /api/auth/me` - Infos utilisateur connecté
- `POST /api/groups` - Créer un groupe
- `GET /api/groups/:code` - Obtenir infos groupe
- `POST /api/photos` - Upload photo
- `GET /api/groups/:id/photos` - Liste photos du groupe
- `DELETE /api/photos/:id` - Supprimer photo

## Sécurité

- Authentification JWT pour toutes les routes protégées
- Validation stricte des entrées utilisateur
- Protection contre les injections SQL (requêtes préparées)
- CORS configuré pour l'environnement de production
- Upload de fichiers limité aux formats image

## Développement

Pour contribuer au projet :

1. Créez une branche pour votre fonctionnalité
2. Respectez les conventions de code définies dans `CLAUDE.md`
3. Testez vos modifications
4. Créez une Pull Request

## Support

Pour toute question ou problème, créez une issue sur le repository.

## Licence

Ce projet est sous licence MIT.