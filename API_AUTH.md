# API d'Authentification

## Endpoints

### POST `/apis/auth/login`

Authentifie un utilisateur avec email et mot de passe.

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response Success (200):**
```json
{
  "success": true,
  "message": "Connexion réussie",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": "user_example",
      "email": "user@example.com",
      "name": "user",
      "roles": ["ROLE_USER", "ROLE_AUTHENTICATED"]
    }
  }
}
```

**Response Error (400/401):**
```json
{
  "success": false,
  "message": "Email et mot de passe requis",
  "errors": ["Les champs email et mot de passe sont obligatoires"]
}
```

---

### GET `/apis/auth/me`

Récupère les informations de l'utilisateur authentifié.

**Headers:**
```
Authorization: Bearer {token}
```

**Response Success (200):**
```json
{
  "success": true,
  "data": {
    "id": "user_example",
    "email": "user@example.com",
    "name": "user",
    "roles": ["ROLE_USER", "ROLE_AUTHENTICATED"]
  }
}
```

**Response Error (401):**
```json
{
  "success": false,
  "message": "Token expiré ou invalide"
}
```

---

### POST `/apis/auth/logout`

Déconnexion (le token est supprimé côté client).

**Response Success (200):**
```json
{
  "success": true,
  "message": "Déconnexion réussie"
}
```

---

## Notes

- Le token JWT est actuellement encodé en base64 (temporaire)
- En production, utiliser une vraie bibliothèque JWT (lexik/jwt-authentication-bundle)
- Le token expire après 24 heures
- Les endpoints nécessitent le préfixe `/apis` dans l'URL

## TODO

- [ ] Intégrer une vraie bibliothèque JWT
- [ ] Ajouter la vérification contre une base de données
- [ ] Implémenter le hashage des mots de passe
- [ ] Ajouter la gestion des rôles et permissions
- [ ] Ajouter le refresh token

