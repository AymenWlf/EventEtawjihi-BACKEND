<?php

namespace App\Controller;

use App\Entity\OrientationTest;
use App\Entity\User;
use App\Repository\OrientationTestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/apis/orientation-test', name: 'api_orientation_test_')]
class OrientationTestController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrientationTestRepository $testRepository
    ) {
    }

    #[Route('/start', name: 'start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $data = json_decode($request->getContent(), true);
        $selectedLanguage = $data['selectedLanguage'] ?? 'fr';

        // Vérifier s'il existe déjà un test actif
        $activeTest = $this->testRepository->findActiveTestByUser($user);
        
        if ($activeTest) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Test existant récupéré',
                'uuid' => $activeTest->getUuid(),
                'isCompleted' => $activeTest->isCompleted(),
                'data' => $this->formatTestData($activeTest)
            ]);
        }

        // Créer un nouveau test
        $test = new OrientationTest();
        $test->setUser($user);
        $test->setLanguage($selectedLanguage);
        $test->setTestType('welcome');
        
        // Initialiser metadata
        $metadata = [
            'selectedLanguage' => $selectedLanguage,
            'startedAt' => $test->getStartedAt()->format('c'),
            'stepDurations' => [],
            'version' => '1.0'
        ];
        $test->setMetadata($metadata);
        
        // Initialiser currentStep avec welcome
        $currentStep = [
            'selectedLanguage' => $selectedLanguage,
            'session' => [
                'testType' => 'welcome',
                'startedAt' => $test->getStartedAt()->format('c'),
                'completedAt' => null,
                'duration' => 0,
                'language' => $selectedLanguage,
                'totalQuestions' => 0,
                'questions' => []
            ]
        ];
        $test->setCurrentStep($currentStep);

        $this->entityManager->persist($test);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Test d\'orientation démarré avec succès',
            'uuid' => $test->getUuid(),
            'isCompleted' => false,
            'data' => $this->formatTestData($test)
        ]);
    }

    #[Route('/my-test', name: 'my_test', methods: ['GET'])]
    public function myTest(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $test = $this->testRepository->findLatestTestByUser($user);

        if (!$test) {
            return new JsonResponse([
                'success' => true,
                'hasTest' => false,
                'message' => 'Aucun test trouvé'
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'hasTest' => true,
            'data' => $this->formatTestData($test)
        ]);
    }

    #[Route('/resume', name: 'resume', methods: ['GET'])]
    public function resume(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $test = $this->testRepository->findActiveTestByUser($user);

        if (!$test) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucun test actif trouvé'
            ], 404);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Test récupéré avec succès',
            'uuid' => $test->getUuid(),
            'isCompleted' => $test->isCompleted(),
            'data' => $this->formatTestData($test)
        ]);
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function reset(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $activeTest = $this->testRepository->findActiveTestByUser($user);
        
        if ($activeTest) {
            $this->entityManager->remove($activeTest);
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Test réinitialisé avec succès'
        ]);
    }

    #[Route('/save-step', name: 'save_step', methods: ['POST'])]
    public function saveStep(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $data = json_decode($request->getContent(), true);
        $stepName = $data['stepName'] ?? null;
        $stepData = $data['stepData'] ?? null;
        $stepNumber = $data['stepNumber'] ?? null;
        $duration = $data['duration'] ?? null;

        if (!$stepName || !$stepData) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Données de l\'étape manquantes'
            ], 400);
        }

        // Récupérer ou créer le test actif
        $test = $this->testRepository->findActiveTestByUser($user);
        
        if (!$test) {
            $test = new OrientationTest();
            $test->setUser($user);
            $test->setLanguage($data['selectedLanguage'] ?? 'fr');
            $test->setTestType($stepName);
            
            $metadata = [
                'selectedLanguage' => $test->getLanguage(),
                'startedAt' => $test->getStartedAt()->format('c'),
                'stepDurations' => [],
                'version' => '1.0'
            ];
            $test->setMetadata($metadata);
            $this->entityManager->persist($test);
        }

        // Mettre à jour le testType avec le nom de l'étape courante
        $test->setTestType($stepName);

        // Récupérer le currentStep existant pour fusionner les données
        $currentStep = $test->getCurrentStep() ?? [];
        
        // Mettre à jour la session pour l'étape courante
        $currentStep['selectedLanguage'] = $test->getLanguage();
        $currentStep['session'] = [
            'testType' => $stepName,
            'startedAt' => $test->getStartedAt()->format('c'),
            'completedAt' => (new \DateTimeImmutable())->format('c'),
            'duration' => $duration ?? 0,
            'language' => $test->getLanguage(),
            'totalQuestions' => $stepData['totalQuestions'] ?? $stepData['session']['totalQuestions'] ?? 0,
            'questions' => $stepData['questions'] ?? $stepData['session']['questions'] ?? []
        ];
        
        // Ajouter les données spécifiques de l'étape (ex: personalInfo, riasec, etc.)
        // Pour chaque étape, stocker les données sous le nom de l'étape dans currentStep
        // IMPORTANT: Fusionner avec les données existantes au lieu de les remplacer
        foreach ($stepData as $key => $value) {
            if (!in_array($key, ['timestamp', 'totalQuestions', 'questions', 'session'])) {
                // Stocker les données sous le nom de l'étape (ex: careerCompatibility pour careers)
                if ($stepName === 'careerCompatibility' && $key === 'careers') {
                    $currentStep['careerCompatibility'] = $value;
                } elseif ($stepName === 'languageSkills' && $key === 'languages') {
                    // Pour languageSkills, stocker dans 'languages' au lieu de 'languageSkills'
                    $currentStep['languages'] = $value;
                } else {
                    $currentStep[$key] = $value;
                }
            }
        }
        
        $test->setCurrentStep($currentStep);

        // Mettre à jour metadata avec stepDurations
        $metadata = $test->getMetadata() ?? [
            'selectedLanguage' => $test->getLanguage(),
            'startedAt' => $test->getStartedAt()->format('c'),
            'stepDurations' => [],
            'version' => '1.0'
        ];
        
        if (!isset($metadata['stepDurations'])) {
            $metadata['stepDurations'] = [];
        }
        
        $metadata['stepDurations'][$stepName] = $duration ?? 0;
        $test->setMetadata($metadata);

        // Mettre à jour totalQuestions
        if (isset($stepData['totalQuestions'])) {
            $test->setTotalQuestions(($test->getTotalQuestions() ?? 0) + $stepData['totalQuestions']);
        }

        // Si c'est l'étape personalInfo, mettre à jour les informations de l'utilisateur
        if ($stepName === 'personalInfo' && isset($stepData['personalInfo'])) {
            $personalInfo = $stepData['personalInfo'];
            
            // Mettre à jour le téléphone si fourni
            if (isset($personalInfo['phoneNumber']) && !empty($personalInfo['phoneNumber'])) {
                $user->setTelephone($personalInfo['phoneNumber']);
            }
            
            // Mettre à jour le WhatsApp si fourni
            if (isset($personalInfo['whatsappNumber']) && !empty($personalInfo['whatsappNumber'])) {
                $user->setWhatsappNumber($personalInfo['whatsappNumber']);
            }
            
            // Mettre à jour le prénom et nom si fournis
            if (isset($personalInfo['firstName']) && !empty($personalInfo['firstName'])) {
                $user->setFirstName($personalInfo['firstName']);
            }
            if (isset($personalInfo['lastName']) && !empty($personalInfo['lastName'])) {
                $user->setLastName($personalInfo['lastName']);
            }
            if (isset($personalInfo['age']) && !empty($personalInfo['age'])) {
                $user->setAge((int) $personalInfo['age']);
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Étape sauvegardée avec succès',
            'uuid' => $test->getUuid()
        ]);
    }

    #[Route('/completed', name: 'completed', methods: ['POST'])]
    public function completed(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        $test = $this->testRepository->findActiveTestByUser($user);

        if (!$test) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucun test actif trouvé'
            ], 404);
        }

        $test->setIsCompleted(true);
        
        // Mettre à jour metadata avec completedAt
        $metadata = $test->getMetadata() ?? [];
        $metadata['completedAt'] = $test->getCompletedAt()->format('c');
        $test->setMetadata($metadata);
        
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Test marqué comme terminé',
            'data' => $this->formatTestData($test)
        ]);
    }

    private function formatTestData(OrientationTest $test): array
    {
        $metadata = $test->getMetadata() ?? [];
        $currentStep = $test->getCurrentStep() ?? [];
        
        // Construire la structure attendue par le frontend
        $data = [
            'uuid' => $test->getUuid(),
            'selectedLanguage' => $test->getLanguage(),
            'isCompleted' => $test->isCompleted(),
            'currentStepId' => $test->getTestType(),
            'testMetadata' => $metadata
        ];

        // Ajouter currentStep avec les données de l'étape courante
        if (!empty($currentStep)) {
            $data['currentStep'] = $currentStep;
            
            // Extraire les données spécifiques de chaque étape depuis currentStep
            // IMPORTANT: Extraire toutes les étapes, même si elles ne sont pas dans l'étape courante
            if (isset($currentStep['personalInfo'])) {
                // Si personalInfo contient un sous-objet personalInfo, l'extraire
                if (isset($currentStep['personalInfo']['personalInfo']) && is_array($currentStep['personalInfo']['personalInfo'])) {
                    $data['personalInfo'] = $currentStep['personalInfo']['personalInfo'];
                } else {
                    $data['personalInfo'] = $currentStep['personalInfo'];
                }
            }
            if (isset($currentStep['riasec'])) {
                // Si riasec contient un sous-objet riasec, l'extraire
                if (isset($currentStep['riasec']['riasec']) && is_array($currentStep['riasec']['riasec'])) {
                    $data['riasecScores'] = $currentStep['riasec']['riasec'];
                } else {
                    $data['riasecScores'] = $currentStep['riasec'];
                }
            }
            if (isset($currentStep['personality'])) {
                // Si personality contient un sous-objet personality, l'extraire
                if (isset($currentStep['personality']['personality']) && is_array($currentStep['personality']['personality'])) {
                    $data['personalityScores'] = $currentStep['personality']['personality'];
                } else {
                    $data['personalityScores'] = $currentStep['personality'];
                }
            }
            if (isset($currentStep['interests'])) {
                // Si interests contient un sous-objet interests, l'extraire
                if (isset($currentStep['interests']['interests']) && is_array($currentStep['interests']['interests'])) {
                    $data['academicInterests'] = $currentStep['interests']['interests'];
                } else {
                    $data['academicInterests'] = $currentStep['interests'];
                }
            }
            if (isset($currentStep['careerCompatibility'])) {
                $data['careerCompatibility'] = $currentStep['careerCompatibility'];
            } elseif (isset($currentStep['careers'])) {
                // Support pour l'ancienne structure
                $data['careerCompatibility'] = $currentStep['careers'];
            }
            if (isset($currentStep['constraints'])) {
                // Si constraints contient un sous-objet constraints, l'extraire
                if (isset($currentStep['constraints']['constraints']) && is_array($currentStep['constraints']['constraints'])) {
                    $data['constraints'] = $currentStep['constraints']['constraints'];
                } else {
                    $data['constraints'] = $currentStep['constraints'];
                }
            }
            // Pour languageSkills, vérifier aussi 'languages' (structure directe)
            if (isset($currentStep['languages'])) {
                $data['languageSkills'] = $currentStep['languages'];
            } elseif (isset($currentStep['languageSkills'])) {
                // Si c'est dans languageSkills, extraire languages si présent
                if (isset($currentStep['languageSkills']['languages']) && is_array($currentStep['languageSkills']['languages'])) {
                    $data['languageSkills'] = $currentStep['languageSkills']['languages'];
                } else {
                    $data['languageSkills'] = $currentStep['languageSkills'];
                }
            }
        }

        return $data;
    }
}
