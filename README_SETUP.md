# Configuration du Backend

## Installation des dépendances

```bash
cd eventsystem
composer install
```

## Configuration de la base de données

### Option 1 : Configuration automatique (recommandé)

Utilisez le script interactif pour configurer MySQL/MariaDB :

```bash
./setup-mysql.sh
```

Le script vous demandera :
- Nom d'utilisateur MySQL
- Mot de passe MySQL
- Hôte (par défaut: 127.0.0.1)
- Port (par défaut: 3306)
- Nom de la base de données (par défaut: event_orientation)
- Version MySQL/MariaDB (par défaut: 8.0)

### Option 2 : Configuration manuelle

1. Modifier le fichier `.env` avec vos identifiants MySQL/MariaDB :

```env
DATABASE_URL="mysql://USERNAME:PASSWORD@127.0.0.1:3306/DB_NAME?serverVersion=8.0&charset=utf8mb4"
```

**Exemples :**
   - Pour MySQL 8.0 : `DATABASE_URL="mysql://root:VOTRE_MOT_DE_PASSE@127.0.0.1:3306/event_orientation?serverVersion=8.0&charset=utf8mb4"`
   - Pour MariaDB 10.11 : `DATABASE_URL="mysql://root:VOTRE_MOT_DE_PASSE@127.0.0.1:3306/event_orientation?serverVersion=10.11&charset=utf8mb4"`

3. Créer la base de données et les tables :
```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
```

**Note :** Si vous utilisez le script `setup-mysql.sh`, ces étapes sont effectuées automatiquement.

## Créer un utilisateur de test

### Utilisateur normal
```bash
php bin/console app:create-user test@example.com password123
```

### Utilisateur administrateur
```bash
php bin/console app:create-user admin@example.com admin123 --admin
```

### Avec un nom personnalisé
```bash
php bin/console app:create-user john@example.com password123 --name="John Doe"
```

## Commandes utiles

- Créer la base de données : `php bin/console doctrine:database:create`
- Créer les tables : `php bin/console doctrine:schema:create`
- Supprimer la base de données : `php bin/console doctrine:database:drop --force`
- Mettre à jour le schéma : `php bin/console doctrine:schema:update --force`
- Créer une migration : `php bin/console make:migration`
- Exécuter les migrations : `php bin/console doctrine:migrations:migrate`

## Utilisateurs de test par défaut

Après avoir créé la base de données, vous pouvez créer ces utilisateurs de test :

```bash
# Utilisateur normal
php bin/console app:create-user user@test.com password123 --name="User Test"

# Administrateur
php bin/console app:create-user admin@test.com admin123 --name="Admin Test" --admin
```

## Notes

- Les mots de passe sont automatiquement hashés avec l'algorithme configuré dans `security.yaml`
- Les utilisateurs sont créés avec le rôle `ROLE_USER` par défaut
- Les administrateurs ont les rôles `ROLE_ADMIN` et `ROLE_USER`

