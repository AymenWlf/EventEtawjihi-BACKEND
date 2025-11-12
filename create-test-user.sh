#!/bin/bash

# Script pour créer un utilisateur de test
# Usage: ./create-test-user.sh

echo "Création d'un utilisateur de test..."

# Créer un utilisateur normal
php bin/console app:create-user user@test.com password123 --name="User Test"

# Créer un administrateur
php bin/console app:create-user admin@test.com admin123 --name="Admin Test" --admin

echo "Utilisateurs de test créés !"
echo ""
echo "Utilisateurs disponibles :"
echo "  - Email: user@test.com, Password: password123"
echo "  - Email: admin@test.com, Password: admin123 (Admin)"

