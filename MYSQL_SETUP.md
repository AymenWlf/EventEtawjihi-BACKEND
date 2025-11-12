# Configuration MySQL/MariaDB

## Configuration de la base de données

1. **Modifier le fichier `.env`** avec vos identifiants MySQL/MariaDB :

```env
DATABASE_URL="mysql://USERNAME:PASSWORD@127.0.0.1:3306/DB_NAME?serverVersion=8.0&charset=utf8mb4"
```

### Exemples de configuration :

**Pour MySQL 8.0 :**
```env
DATABASE_URL="mysql://root:VOTRE_MOT_DE_PASSE@127.0.0.1:3306/event_orientation?serverVersion=8.0&charset=utf8mb4"
```

**Pour MariaDB 10.11 :**
```env
DATABASE_URL="mysql://root:VOTRE_MOT_DE_PASSE@127.0.0.1:3306/event_orientation?serverVersion=10.11&charset=utf8mb4"
```

**Avec un utilisateur personnalisé :**
```env
DATABASE_URL="mysql://mon_user:mon_password@127.0.0.1:3306/event_orientation?serverVersion=8.0&charset=utf8mb4"
```

## Créer la base de données

Une fois le fichier `.env` configuré avec les bons identifiants :

```bash
# Créer la base de données
php bin/console doctrine:database:create

# Créer les tables
php bin/console doctrine:schema:create

# Créer un utilisateur de test
php bin/console app:create-user user@test.com password123 --name="User Test"

# Créer un administrateur
php bin/console app:create-user admin@test.com admin123 --name="Admin Test" --admin
```

## Vérifier la connexion

Pour tester la connexion MySQL :

```bash
php bin/console doctrine:query:sql "SELECT 1"
```

## Notes importantes

- Remplacez `USERNAME`, `PASSWORD` et `DB_NAME` par vos valeurs réelles
- Assurez-vous que MySQL/MariaDB est démarré
- La base de données sera créée automatiquement si l'utilisateur a les permissions
- Si vous utilisez un port différent, modifiez `3306` dans l'URL

