# Structure de la Base de Données - Test d'Orientation

## Table `orientation_test`

### Champs

| Champ | Type | Description |
|-------|------|-------------|
| `id` | INTEGER | Identifiant unique (auto-increment) |
| `user_id` | INTEGER | ID de l'utilisateur (FK vers `user`) |
| `uuid` | VARCHAR(36) | UUID unique du test (format: `0x...`) |
| `test_type` | VARCHAR(50) | Type de test actuel (ex: 'welcome', 'personalInfo', 'riasec') |
| `started_at` | DATETIME_IMMUTABLE | Date de début du test |
| `completed_at` | DATETIME_IMMUTABLE (nullable) | Date de fin du test |
| `duration` | INTEGER (nullable) | Durée totale en secondes |
| `language` | VARCHAR(2) | Langue du test ('fr' ou 'ar') |
| `total_questions` | INTEGER (nullable) | Nombre total de questions répondues |
| `metadata` | JSON (nullable) | Métadonnées du test |
| `current_step` | JSON (nullable) | Données de l'étape courante |
| `is_completed` | BOOLEAN | Statut de complétion |
| `created_at` | DATETIME_IMMUTABLE | Date de création |
| `updated_at` | DATETIME_IMMUTABLE (nullable) | Date de mise à jour |

### Structure JSON `metadata`

```json
{
  "selectedLanguage": "fr",
  "startedAt": "2025-09-06T16:57:48+00:00",
  "stepDurations": {
    "welcome": 0,
    "personalInfo": 0,
    "riasec": 6017,
    "personality": 35179,
    "aptitude": 95485,
    "interests": 47933,
    "careerCompatibility": 41054,
    "constraints": 23952,
    "languageSkills": 22164
  },
  "version": "1.0",
  "completedAt": "2025-09-06T18:10:12+00:00"
}
```

### Structure JSON `current_step`

```json
{
  "selectedLanguage": "fr",
  "session": {
    "testType": "welcome",
    "startedAt": "2025-09-06T16:57:48+00:00",
    "completedAt": "2025-09-06T16:57:48+00:00",
    "duration": 0,
    "language": "fr",
    "totalQuestions": 0,
    "questions": []
  },
  "personalInfo": { ... },  // Si étape personalInfo
  "riasec": { ... },         // Si étape riasec
  "personality": { ... },    // Si étape personality
  // etc.
}
```

## Différences avec l'ancienne structure

### Ancienne structure (supprimée)
- Table séparée `orientation_test_step` pour chaque étape
- Champs individuels : `selectedLanguage`, `currentStep` (string), `version`
- Relation OneToMany entre `OrientationTest` et `OrientationTestStep`

### Nouvelle structure (actuelle)
- Une seule table `orientation_test`
- Toutes les données dans `metadata` (JSON) et `current_step` (JSON)
- Pas de table séparée pour les étapes
- Format UUID : `0x` suivi de 32 caractères hexadécimaux
- Champ `test_type` pour indiquer l'étape courante
- Champ `duration` au lieu de `total_duration`
- Champ `language` au lieu de `selected_language`

## Avantages de la nouvelle structure

1. **Simplicité** : Une seule table au lieu de deux
2. **Flexibilité** : Structure JSON permet d'ajouter facilement de nouveaux champs
3. **Performance** : Moins de jointures nécessaires
4. **Compatibilité** : Correspond à la structure existante du système similaire

