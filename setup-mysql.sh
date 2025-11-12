#!/bin/bash

# Script pour configurer MySQL/MariaDB
# Usage: ./setup-mysql.sh

echo "=========================================="
echo "Configuration MySQL/MariaDB"
echo "=========================================="
echo ""

# Demander les informations de connexion
read -p "Nom d'utilisateur MySQL (défaut: root): " DB_USER
DB_USER=${DB_USER:-root}

read -sp "Mot de passe MySQL: " DB_PASS
echo ""

read -p "Hôte MySQL (défaut: 127.0.0.1): " DB_HOST
DB_HOST=${DB_HOST:-127.0.0.1}

read -p "Port MySQL (défaut: 3306): " DB_PORT
DB_PORT=${DB_PORT:-3306}

read -p "Nom de la base de données (défaut: event_orientation): " DB_NAME
DB_NAME=${DB_NAME:-event_orientation}

read -p "Version MySQL/MariaDB (défaut: 8.0 pour MySQL, 10.11 pour MariaDB): " DB_VERSION
DB_VERSION=${DB_VERSION:-8.0}

# Mettre à jour le fichier .env
echo ""
echo "Mise à jour du fichier .env..."

cat > .env << EOF
###> symfony/framework-bundle ###
APP_ENV=dev
APP_SECRET=your-secret-key-here-change-this-in-production
###< symfony/framework-bundle ###

###> doctrine/doctrine-bundle ###
DATABASE_URL="mysql://${DB_USER}:${DB_PASS}@${DB_HOST}:${DB_PORT}/${DB_NAME}?serverVersion=${DB_VERSION}&charset=utf8mb4"
###< doctrine/doctrine-bundle ###
EOF

echo "✅ Fichier .env mis à jour"
echo ""

# Tester la connexion
echo "Test de la connexion..."
php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1

if [ $? -eq 0 ]; then
    echo "✅ Connexion réussie !"
    echo ""
    
    # Créer la base de données
    echo "Création de la base de données..."
    php bin/console doctrine:database:create --if-not-exists
    
    if [ $? -eq 0 ]; then
        echo "✅ Base de données créée"
        echo ""
        
        # Créer le schéma
        echo "Création du schéma..."
        php bin/console doctrine:schema:create
        
        if [ $? -eq 0 ]; then
            echo "✅ Schéma créé"
            echo ""
            
            # Créer les utilisateurs de test
            echo "Création des utilisateurs de test..."
            php bin/console app:create-user user@test.com password123 --name="User Test" 2>/dev/null
            php bin/console app:create-user admin@test.com admin123 --name="Admin Test" --admin 2>/dev/null
            
            echo ""
            echo "=========================================="
            echo "✅ Configuration terminée avec succès !"
            echo "=========================================="
            echo ""
            echo "Utilisateurs de test créés :"
            echo "  - Email: user@test.com, Password: password123"
            echo "  - Email: admin@test.com, Password: admin123 (Admin)"
        else
            echo "❌ Erreur lors de la création du schéma"
        fi
    else
        echo "❌ Erreur lors de la création de la base de données"
    fi
else
    echo "❌ Erreur de connexion. Vérifiez vos identifiants dans le fichier .env"
    echo ""
    echo "Vous pouvez modifier manuellement le fichier .env et exécuter :"
    echo "  php bin/console doctrine:database:create"
    echo "  php bin/console doctrine:schema:create"
fi

