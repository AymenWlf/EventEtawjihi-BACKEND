# API Test d'Orientation - Documentation

## Vue d'ensemble

Le backend a été créé pour synchroniser complètement le test d'orientation avec le frontend. Toutes les données sont sauvegardées en base de données et peuvent être récupérées à tout moment.

## Entités créées

### 1. OrientationTest
Entité principale représentant un test d'orientation complet.

**Champs:**
- `id` : Identifiant unique
- `user` : Relation avec l'utilisateur (User)
- `uuid` : UUID unique du test (généré automatiquement)
- `selectedLanguage` : Langue choisie ('fr' ou 'ar')
- `isCompleted` : Statut de complétion
- `startedAt` : Date de début
- `completedAt` : Date de fin
- `totalDuration` : Durée totale en secondes
- `currentStep` : Étape actuelle du test
- `version` : Version du test (ex: 'quick-1.0')
- `testMetadata` : Métadonnées JSON
- `steps` : Collection d'étapes (OrientationTestStep)

### 2. OrientationTestStep
Entité représentant une étape du test.

**Champs:**
- `id` : Identifiant unique
- `orientationTest` : Relation avec le test parent
- `stepName` : Nom de l'étape (ex: 'personalInfo', 'riasec', 'personality')
- `stepNumber` : Numéro de l'étape
- `stepData` : Données JSON de l'étape
- `duration` : Durée de l'étape en secondes
- `createdAt` : Date de création
- `updatedAt` : Date de mise à jour

## Endpoints API

### 1. POST `/apis/orientation-test/start`
Démarre un nouveau test d'orientation ou récupère un test actif existant.

**Request Body:**
```json
{
  "selectedLanguage": "fr"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Test d'orientation démarré avec succès",
  "uuid": "uuid-du-test",
  "isCompleted": false,
  "data": { ... }
}
```

### 2. GET `/apis/orientation-test/my-test`
Récupère le dernier test de l'utilisateur (actif ou terminé).

**Response:**
```json
{
  "success": true,
  "hasTest": true,
  "data": { ... }
}
```

### 3. GET `/apis/orientation-test/resume`
Récupère le test actif (non terminé) de l'utilisateur.

**Response:**
```json
{
  "success": true,
  "message": "Test récupéré avec succès",
  "uuid": "uuid-du-test",
  "isCompleted": false,
  "data": { ... }
}
```

### 4. POST `/apis/orientation-test/reset`
Réinitialise le test actif (supprime le test non terminé).

**Response:**
```json
{
  "success": true,
  "message": "Test réinitialisé avec succès"
}
```

### 5. POST `/apis/orientation-test/save-step`
Sauvegarde une étape du test.

**Request Body:**
```json
{
  "stepName": "personalInfo",
  "stepData": {
    "personalInfo": { ... },
    "timestamp": "2024-01-01T00:00:00Z"
  },
  "stepNumber": 1,
  "duration": 120
}
```

**Response:**
```json
{
  "success": true,
  "message": "Étape sauvegardée avec succès",
  "uuid": "uuid-du-test"
}
```

**Étapes supportées:**
- `personalInfo` : Informations personnelles
- `riasec` : Test RIASEC
- `personality` : Test de personnalité
- `interests` : Test d'intérêts académiques
- `careerCompatibility` : Compatibilité de carrière
- `constraints` : Contraintes et priorités
- `languageSkills` : Compétences linguistiques

### 6. POST `/apis/orientation-test/completed`
Marque le test comme terminé.

**Response:**
```json
{
  "success": true,
  "message": "Test marqué comme terminé",
  "data": { ... }
}
```

### 7. GET `/apis/user/profile`
Récupère le profil de l'utilisateur authentifié.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "1",
    "email": "user@test.com",
    "name": "User Test",
    "telephone": null,
    "prenom": null,
    "nom": null,
    "firstName": "User Test",
    "lastName": null,
    "roles": ["ROLE_USER"]
  }
}
```

## Structure des données

### Format de réponse `formatTestData()`

La fonction `formatTestData()` structure les données pour correspondre au format attendu par le frontend:

```json
{
  "uuid": "uuid-du-test",
  "selectedLanguage": "fr",
  "isCompleted": false,
  "currentStepId": "personalInfo",
  "testMetadata": {
    "selectedLanguage": "fr",
    "startedAt": "2024-01-01T00:00:00+00:00",
    "completedAt": null,
    "totalDuration": null,
    "version": "quick-1.0",
    "stepDurations": {
      "personalInfo": 120,
      "riasec": 300
    }
  },
  "personalInfo": { ... },
  "riasecScores": { ... },
  "personalityScores": { ... },
  "academicInterests": { ... },
  "careerCompatibility": { ... },
  "constraints": { ... },
  "languageSkills": { ... },
  "currentStep": { ... }
}
```

## Sécurité

Tous les endpoints nécessitent une authentification JWT. Le token doit être envoyé dans le header:
```
Authorization: Bearer {token}
```

## Base de données

Les tables suivantes ont été créées:
- `orientation_test` : Tests d'orientation
- `orientation_test_step` : Étapes des tests

## Notes importantes

1. **Un seul test actif par utilisateur** : Si un utilisateur démarre un nouveau test alors qu'il en a déjà un actif, le système retourne le test existant.

2. **Sauvegarde automatique** : Chaque étape est sauvegardée automatiquement lors de l'appel à `/save-step`.

3. **Reprise de test** : Un utilisateur peut reprendre son test à tout moment en utilisant `/resume` ou `/my-test`.

4. **UUID unique** : Chaque test a un UUID unique stocké dans `localStorage` côté frontend.

5. **Données JSON** : Toutes les données des étapes sont stockées en JSON dans la colonne `stepData`, permettant une flexibilité maximale.

## Prochaines étapes

- [ ] Ajouter des champs supplémentaires dans l'entité User (téléphone, prénom, nom)
- [ ] Implémenter la génération automatique du rapport d'analyse
- [ ] Ajouter des statistiques et analytics
- [ ] Implémenter l'export PDF du rapport
- [ ] Ajouter la gestion des versions de test

