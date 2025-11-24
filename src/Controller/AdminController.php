<?php

namespace App\Controller;

use App\Entity\OrientationTest;
use App\Entity\User;
use App\Repository\OrientationTestRepository;
use App\Repository\UserRepository;
use App\Service\QrCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\RoundBlockSizeMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/apis/admin', name: 'api_admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private OrientationTestRepository $testRepository,
        private EntityManagerInterface $entityManager,
        private QrCodeService $qrCodeService
    ) {
    }

    #[Route('/users', name: 'users_list', methods: ['GET'])]
    public function listUsers(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));
        $search = $request->query->get('search', '');
        $offset = ($page - 1) * $limit;

        $qb = $this->userRepository->createQueryBuilder('u');

        // Exclure les membres du staff de la liste des utilisateurs normaux
        $qb->where('u.isStaff = false');

        // Recherche
        if (!empty($search)) {
            $qb->andWhere('(u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search OR u.telephone LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $total = (int) $qb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        // Récupérer les utilisateurs
        $users = $qb->select('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $usersData = [];
        foreach ($users as $user) {
            $test = $this->testRepository->findLatestTestByUser($user);
            $usersData[] = $this->formatUserData($user, $test);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $usersData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    }

    #[Route('/users', name: 'user_create', methods: ['POST'])]
    public function createUser(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validation
        if (empty($data['email']) || empty($data['password'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Email et mot de passe sont obligatoires'
            ], 400);
        }

        // Vérifier si l'email existe déjà
        $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Un utilisateur avec cet email existe déjà'
            ], 400);
        }

        // Créer le nouvel utilisateur
        $user = new User();
        $user->setEmail($data['email']);
        $plainPassword = $data['password'];
        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        $user->setPasswordPlain($plainPassword); // Stocker le mot de passe en clair
        $user->setFirstName($data['firstName'] ?? null);
        $user->setLastName($data['lastName'] ?? null);
        $user->setTelephone($data['telephone'] ?? null);
        $user->setWhatsappNumber($data['whatsappNumber'] ?? null);
        if (isset($data['age'])) {
            $user->setAge((int) $data['age']);
        }
        $user->setIsStaff(false);
        $user->setIsSuperAdmin(false);
        $user->setRoles(['ROLE_USER']);

        // Générer le QR code
        $user->generateQrCode();

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Générer le code utilisateur après la persistance (pour avoir l'ID)
        $user->generateUserCode();
        $this->entityManager->flush();

        $test = $this->testRepository->findLatestTestByUser($user);

        return new JsonResponse([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => $this->formatUserData($user, $test)
        ], 201);
    }

    #[Route('/users/{id}', name: 'user_get', methods: ['GET'])]
    public function getUserDetails(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $test = $this->testRepository->findLatestTestByUser($user);

        return new JsonResponse([
            'success' => true,
            'data' => $this->formatUserData($user, $test)
        ]);
    }

    #[Route('/users/{id}', name: 'user_update', methods: ['PUT'])]
    public function updateUser(int $id, Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }
        if (isset($data['email'])) {
            // Vérifier si l'email existe déjà pour un autre utilisateur
            $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Un utilisateur avec cet email existe déjà'
                ], 400);
            }
            $user->setEmail($data['email']);
        }
        if (isset($data['telephone'])) {
            $user->setTelephone($data['telephone']);
        }
        if (isset($data['whatsappNumber'])) {
            $user->setWhatsappNumber($data['whatsappNumber']);
        }
        if (isset($data['age'])) {
            $user->setAge($data['age']);
        }
        if (isset($data['password']) && !empty($data['password'])) {
            // Mettre à jour le mot de passe si fourni
            $plainPassword = $data['password'];
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $user->setPasswordPlain($plainPassword); // Stocker le mot de passe en clair
        }

        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $test = $this->testRepository->findLatestTestByUser($user);

        return new JsonResponse([
            'success' => true,
            'message' => 'Utilisateur mis à jour avec succès',
            'data' => $this->formatUserData($user, $test)
        ]);
    }

    #[Route('/users/{id}/test-status', name: 'user_test_status', methods: ['GET'])]
    public function getTestStatus(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $test = $this->testRepository->findLatestTestByUser($user);

        if (!$test) {
            return new JsonResponse([
                'success' => true,
                'hasTest' => false,
                'message' => 'Aucun test trouvé'
            ]);
        }

        // Vérifier les étapes complétées
        $completedSteps = $this->getCompletedSteps($test);
        $requiredSteps = [
            'personalInfo',
            'riasec',
            'personality',
            'interests',
            'careerCompatibility',
            'constraints',
            'languageSkills'
        ];
        $allStepsCompleted = count($completedSteps) === count($requiredSteps);

        $testData = $this->formatTestData($test);
        
        // Ajouter les informations sur les étapes complétées
        $testData['completedSteps'] = $completedSteps;
        $testData['allStepsCompleted'] = $allStepsCompleted;
        $testData['currentStepId'] = $test->getTestType();

        return new JsonResponse([
            'success' => true,
            'hasTest' => true,
            'data' => $testData
        ]);
    }

    #[Route('/users/{id}/report', name: 'user_report', methods: ['GET'])]
    public function getReport(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $test = $this->testRepository->findLatestTestByUser($user);

        if (!$test) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucun test trouvé pour cet utilisateur'
            ], 404);
        }

        // Vérifier que toutes les étapes sont complétées
        // Peu importe le flag isCompleted() ou l'étape actuelle
        $completedSteps = $this->getCompletedSteps($test);
        $requiredSteps = [
            'personalInfo',
            'riasec',
            'personality',
            'interests',
            'careerCompatibility',
            'constraints',
            'languageSkills'
        ];
        $allStepsCompleted = count($completedSteps) === count($requiredSteps);

        if (!$allStepsCompleted) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le test n\'est pas encore finalisé. Étapes complétées: ' . count($completedSteps) . '/' . count($requiredSteps),
                'completedSteps' => $completedSteps,
                'requiredSteps' => $requiredSteps
            ], 400);
        }

        $reportData = $this->formatTestData($test);
        
        // Ajouter les informations de l'utilisateur
        $reportData['user'] = [
            'id' => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'age' => $user->getAge(),
            'telephone' => $user->getTelephone()
        ];

        return new JsonResponse([
            'success' => true,
            'data' => $reportData
        ]);
    }

    #[Route('/users/{id}/presence', name: 'user_presence', methods: ['PUT'])]
    public function updatePresence(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);
        $isPresent = $data['isPresent'] ?? false;

        $user->setIsPresent((bool) $isPresent);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Présence mise à jour avec succès',
            'data' => [
                'id' => $user->getId(),
                'isPresent' => $user->isPresent()
            ]
        ]);
    }

    #[Route('/users/{id}/qr-code', name: 'user_qr_code', methods: ['GET'])]
    public function getQrCode(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        // Générer le QR code s'il n'existe pas
        if (!$user->getQrCode()) {
            $this->qrCodeService->generateQrCode($user);
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'qrCode' => $user->getQrCode()
            ]
        ]);
    }

    #[Route('/users/{id}/qr-code-pdf', name: 'user_qr_code_pdf', methods: ['GET'])]
    public function downloadQrCodePdf(int $id): Response
    {
        $user = $this->userRepository->find($id);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        // Générer le QR code s'il n'existe pas
        if (!$user->getQrCode()) {
            $this->qrCodeService->generateQrCode($user);
            $this->entityManager->flush();
        }

        // Générer le code utilisateur s'il n'existe pas
        if (!$user->getUserCode()) {
            $user->generateUserCode();
            $this->entityManager->flush();
        }

        $qrCodeData = $user->getQrCode();
        $userName = trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? ''));
        if (empty($userName)) {
            $userName = $user->getEmail();
        }
        $userCode = $user->getUserCode() ?: sprintf('ET-%04d', $user->getId());

        try {
            // Générer le QR code en PNG avec la nouvelle API
            $writer = new PngWriter();
            
            $qrCode = new QrCode(
                data: $qrCodeData,
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 500,
                margin: 2,
                roundBlockSizeMode: RoundBlockSizeMode::Margin,
                foregroundColor: new Color(0, 0, 0),
                backgroundColor: new Color(255, 255, 255)
            );
            
            $result = $writer->write($qrCode);
            $qrCodeImageData = $result->getString();

            // Créer un PDF avec TCPDF
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator('E-Tawjihi');
            $pdf->SetAuthor('E-Tawjihi');
            $pdf->SetTitle('Carte d\'Invitation - ' . $userName);
            $pdf->SetSubject('Carte d\'invitation pour l\'événement');
            
            // Supprimer les en-têtes et pieds de page
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Marges
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(false);
            
            // Ajouter une page
            $pdf->AddPage();
            
            $pageWidth = $pdf->getPageWidth() - 20; // Largeur disponible (marges déduites)
            $currentY = 10;
            
            // Logo E-Tawjihi en haut (centré)
            try {
                $logoUrl = 'https://cdn.e-tawjihi.ma/logo-rectantgle-simple-nobg.png';
                $logoData = @file_get_contents($logoUrl);
                if ($logoData) {
                    $logoSize = 40; // mm
                    $logoX = ($pdf->getPageWidth() - $logoSize) / 2;
                    $pdf->Image('@' . $logoData, $logoX, $currentY, $logoSize, 0, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
                    $currentY += 15;
                }
            } catch (\Exception $e) {
                // Si le logo ne peut pas être chargé, continuer sans
            }
            
            $currentY += 5;
            
            // En-tête avec fond bleu (gradient simulé avec couleur bleue)
            $headerHeight = 25;
            $pdf->SetFillColor(37, 99, 235); // Bleu E-Tawjihi (blue-600)
            $pdf->Rect(10, $currentY, $pageWidth, $headerHeight, 'F');
            
            // Texte "Carte d'Invitation" en blanc
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('helvetica', 'B', 20);
            $pdf->SetY($currentY + 5);
            $pdf->Cell(0, 8, 'Carte d\'Invitation', 0, 1, 'C', false, '', 0, false, 'T', 'M');
            
            // Sous-titre
            $pdf->SetFont('helvetica', '', 12);
            $pdf->SetY($currentY + 15);
            // Remplacer les caractères spéciaux non supportés par TCPDF
            $subtitle = 'Forum National de la Smart Orientation - 1ere Edition';
            $pdf->Cell(0, 6, $subtitle, 0, 1, 'C', false, '', 0, false, 'T', 'M');
            
            $currentY += $headerHeight + 10;
            
            // Informations de l'invité (centré)
            // Nom Prénom
            $fullName = trim(($user->getLastName() ?? '') . ' ' . ($user->getFirstName() ?? ''));
            if (empty($fullName)) {
                $fullName = $userName;
            }
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetY($currentY);
            $pdf->Cell(0, 8, $fullName, 0, 1, 'C');
            
            $currentY += 8;
            
            // Code utilisateur
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(0, 0, 0); // Noir au lieu de bleu
            $pdf->SetY($currentY);
            $pdf->Cell(0, 6, $userCode, 0, 1, 'C');
            $currentY += 6;
            
            // Email en gris
            if ($user->getEmail()) {
                $pdf->SetFont('helvetica', '', 12);
                $pdf->SetTextColor(107, 114, 128); // gray-500
                $pdf->SetY($currentY);
                $pdf->Cell(0, 6, $user->getEmail(), 0, 1, 'C');
                $currentY += 6;
            }
            
            $currentY += 10;
            
            // Section Date et Lieu avec fond bleu clair
            $dateLieuMargin = 10; // Marges gauche et droite
            $dateLieuWidth = $pageWidth - ($dateLieuMargin * 2);
            $dateLieuX = 10 + $dateLieuMargin;
            
            // Calculer la hauteur nécessaire pour le contenu
            $dateText = '04 decembre 2025';
            $lieuText = 'Hotel Palm Plaza, Marrakech';
            
            // Calculer la largeur maximale du texte pour éviter les débordements
            $pdf->SetFont('helvetica', 'B', 14);
            $dateTextWidth = $pdf->GetStringWidth($dateText);
            $lieuTextWidth = $pdf->GetStringWidth($lieuText);
            $maxTextWidth = max($dateTextWidth, $lieuTextWidth);
            
            // Ajuster la largeur si nécessaire
            if ($maxTextWidth > ($dateLieuWidth - 10)) {
                $dateLieuWidth = $maxTextWidth + 10;
                $dateLieuX = ($pdf->getPageWidth() - $dateLieuWidth) / 2;
            }
            
            // Hauteur de la section (avec padding)
            $dateLieuHeight = 35;
            
            // Fond bleu clair
            $pdf->SetFillColor(239, 246, 255); // blue-50
            $pdf->Rect($dateLieuX, $currentY, $dateLieuWidth, $dateLieuHeight, 'F');
            
            // Date
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetY($currentY + 5);
            $pdf->SetX($dateLieuX);
            $pdf->Cell($dateLieuWidth, 6, 'Date', 0, 1, 'C');
            
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetY($currentY + 12);
            $pdf->SetX($dateLieuX);
            // Utiliser MultiCell pour éviter les débordements avec centrage
            $pdf->MultiCell($dateLieuWidth, 7, $dateText, 0, 'C', false, 1, $dateLieuX, $currentY + 12, true, 0, false, true, 0, 'M');
            
            // Lieu
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetY($currentY + 22);
            $pdf->SetX($dateLieuX);
            $pdf->Cell($dateLieuWidth, 6, 'Lieu', 0, 1, 'C');
            
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetY($currentY + 29);
            $pdf->SetX($dateLieuX);
            // Utiliser MultiCell pour éviter les débordements avec centrage
            $pdf->MultiCell($dateLieuWidth, 7, $lieuText, 0, 'C', false, 1, $dateLieuX, $currentY + 29, true, 0, false, true, 0, 'M');
            
            $currentY += $dateLieuHeight + 15;
            
            // Section QR Code
            $qrCodeTitleY = $currentY;
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetY($qrCodeTitleY);
            $pdf->Cell(0, 8, 'QR Code d\'Invitation', 0, 1, 'C');
            
            $currentY += 8;
            
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(75, 85, 99); // gray-600
            $pdf->SetY($currentY);
            $pdf->Cell(0, 6, 'Présentez ce QR code lors de l\'enregistrement', 0, 1, 'C');
            
            $currentY += 10;
            
            // QR Code avec bordure
            $qrSize = 80; // mm - Grande taille pour faciliter le scan
            $qrX = ($pdf->getPageWidth() - $qrSize) / 2;
            
            // Bordure autour du QR code
            $borderSize = 5;
            $pdf->SetFillColor(209, 213, 219); // gray-300
            $pdf->Rect($qrX - $borderSize, $currentY - $borderSize, $qrSize + ($borderSize * 2), $qrSize + ($borderSize * 2), 'F');
            
            // Fond blanc pour le QR code
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($qrX, $currentY, $qrSize, $qrSize, 'F');
            
            // Image QR code
            $pdf->Image('@' . $qrCodeImageData, $qrX, $currentY, $qrSize, $qrSize, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
            
            $currentY += $qrSize + ($borderSize * 2) + 8;
            
            // Code texte sous le QR
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(107, 114, 128); // gray-500
            $pdf->SetY($currentY);
            $codeText = 'Code: ' . substr($qrCodeData, 0, 20) . '...';
            $pdf->Cell(0, 5, $codeText, 0, 1, 'C');
            
            $currentY += 10;
            
            // Instructions en jaune
            $instructionHeight = 15;
            $pdf->SetFillColor(254, 252, 232); // yellow-50
            $pdf->Rect(10, $currentY, $pageWidth, $instructionHeight, 'F');
            
            $pdf->SetTextColor(133, 77, 14); // yellow-800
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetY($currentY + 4);
            // Remplacer l'emoji par un symbole supporté
            $instructionText = '[!] Ce QR code est obligatoire pour le check-in a l\'evenement. Veuillez le telecharger et le presenter a votre arrivee.';
            $pdf->MultiCell($pageWidth, 5, $instructionText, 0, 'C', false, 1, 10, $currentY + 4);
            
            $currentY += $instructionHeight + 10;
            
            // Footer avec fond gris clair
            $footerHeight = 12;
            $pdf->SetFillColor(249, 250, 251); // gray-50
            $pdf->Rect(10, $currentY, $pageWidth, $footerHeight, 'F');
            
            $pdf->SetTextColor(75, 85, 99); // gray-600
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetY($currentY + 4);
            $pdf->Cell(0, 5, 'E-TAWJIHI - ORIENTATION IA | 100% Maroc, 100% Orientation', 0, 1, 'C');
            
            // Générer le PDF
            $pdfContent = $pdf->Output('', 'S');
            
            $response = new Response($pdfContent);
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'attachment; filename="invitation-' . preg_replace('/[^a-z0-9]/i', '-', strtolower($userName)) . '.pdf"');
            
            return $response;
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/qr-scan/{qrCode}', name: 'qr_scan', methods: ['POST'])]
    public function scanQrCode(string $qrCode): JsonResponse
    {
        $userId = $this->qrCodeService->parseQrCode($qrCode);
        
        if (!$userId) {
            return new JsonResponse([
                'success' => false,
                'message' => 'QR code invalide'
            ], 400);
        }

        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }

        // Générer le code utilisateur s'il n'existe pas
        if (!$user->getUserCode()) {
            $user->generateUserCode();
            $this->entityManager->flush();
        }

        // Mettre à jour la présence
        $user->setIsPresent(true);
        $this->entityManager->flush();

        $test = $this->testRepository->findLatestTestByUser($user);
        
        // Vérifier que toutes les étapes sont complétées
        $completedSteps = $this->getCompletedSteps($test);
        $requiredSteps = [
            'personalInfo',
            'riasec',
            'personality',
            'interests',
            'careerCompatibility',
            'constraints',
            'languageSkills'
        ];
        $allStepsCompleted = count($completedSteps) === count($requiredSteps);

        // Déterminer le profil dominant et sa couleur si le test est complété
        // Basé uniquement sur les étapes complétées
        $dominantProfile = null;
        if ($test && $allStepsCompleted) {
            $testData = $this->formatTestData($test);
            if (isset($testData['riasecScores']['scores'])) {
                $scores = $testData['riasecScores']['scores'];
                $maxScore = -1;
                $dominantType = null;
                $riasecMapping = [
                    'R' => ['R', 'Realiste', 'Réaliste'],
                    'I' => ['I', 'Investigateur'],
                    'A' => ['A', 'Artistique'],
                    'S' => ['S', 'Social'],
                    'E' => ['E', 'Entreprenant'],
                    'C' => ['C', 'Conventionnel']
                ];
                
                foreach ($riasecMapping as $type => $keys) {
                    foreach ($keys as $key) {
                        if (isset($scores[$key]) && $scores[$key] > $maxScore) {
                            $maxScore = $scores[$key];
                            $dominantType = $type;
                        }
                    }
                }
                
                if ($dominantType) {
                    $dominantProfile = $dominantType;
                }
            }
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->getId(),
                    'userCode' => $user->getUserCode(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'email' => $user->getEmail()
                ],
                'test' => [
                    'hasTest' => $test !== null,
                    'isCompleted' => $allStepsCompleted, // Basé uniquement sur les étapes complétées
                    'hasReport' => $allStepsCompleted, // Basé uniquement sur les étapes complétées
                    'completedSteps' => $completedSteps,
                    'allStepsCompleted' => $allStepsCompleted,
                    'dominantProfile' => $dominantProfile
                ],
                'presence' => [
                    'isPresent' => true,
                    'updatedAt' => (new \DateTimeImmutable())->format('c')
                ]
            ]
        ]);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        $totalUsers = $this->userRepository->count([]);
        
        $usersWithTests = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.id)')
            ->innerJoin('App\Entity\OrientationTest', 'ot', 'WITH', 'ot.user = u')
            ->getQuery()
            ->getSingleScalarResult();

        // Compter les tests complétés en fonction des étapes complétées, pas du flag isCompleted
        $allTests = $this->testRepository->findAll();
        $completedTests = 0;
        $requiredSteps = [
            'personalInfo',
            'riasec',
            'personality',
            'interests',
            'careerCompatibility',
            'constraints',
            'languageSkills'
        ];
        
        foreach ($allTests as $test) {
            $completedSteps = $this->getCompletedSteps($test);
            if (count($completedSteps) === count($requiredSteps)) {
                $completedTests++;
            }
        }

        $presentUsers = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isPresent = true')
            ->getQuery()
            ->getSingleScalarResult();

        $absentUsers = $totalUsers - $presentUsers;

        return new JsonResponse([
            'success' => true,
            'data' => [
                'totalUsers' => (int) $totalUsers,
                'usersWithTests' => (int) $usersWithTests,
                'completedTests' => (int) $completedTests,
                'presentUsers' => (int) $presentUsers,
                'absentUsers' => (int) $absentUsers,
                'testCompletionRate' => $totalUsers > 0 ? round(($completedTests / $totalUsers) * 100, 2) : 0,
                'presenceRate' => $totalUsers > 0 ? round(($presentUsers / $totalUsers) * 100, 2) : 0
            ]
        ]);
    }

    private function getCompletedSteps(?OrientationTest $test): array
    {
        $requiredSteps = [
            'personalInfo',
            'riasec',
            'personality',
            'interests',
            'careerCompatibility',
            'constraints',
            'languageSkills'
        ];

        $completedSteps = [];
        
        if (!$test) {
            return $completedSteps;
        }

        $currentStep = $test->getCurrentStep() ?? [];
        
        // Vérifier chaque étape requise
        foreach ($requiredSteps as $step) {
            $isCompleted = false;
            
            switch ($step) {
                case 'personalInfo':
                    $isCompleted = isset($currentStep['personalInfo']) && !empty($currentStep['personalInfo']);
                    break;
                case 'riasec':
                    $isCompleted = isset($currentStep['riasec']) && !empty($currentStep['riasec']);
                    break;
                case 'personality':
                    $isCompleted = isset($currentStep['personality']) && !empty($currentStep['personality']);
                    break;
                case 'interests':
                    $isCompleted = isset($currentStep['interests']) && !empty($currentStep['interests']);
                    break;
                case 'careerCompatibility':
                    $isCompleted = (isset($currentStep['careerCompatibility']) && !empty($currentStep['careerCompatibility'])) ||
                                   (isset($currentStep['careers']) && !empty($currentStep['careers']));
                    break;
                case 'constraints':
                    $isCompleted = isset($currentStep['constraints']) && !empty($currentStep['constraints']);
                    break;
                case 'languageSkills':
                    $isCompleted = (isset($currentStep['languageSkills']) && !empty($currentStep['languageSkills'])) ||
                                   (isset($currentStep['languages']) && !empty($currentStep['languages']));
                    break;
            }
            
            if ($isCompleted) {
                $completedSteps[] = $step;
            }
        }
        
        return $completedSteps;
    }

    private function formatUserData(User $user, ?OrientationTest $test): array
    {
        $testStatus = 'non_commencé';
        $currentStep = null;
        $dominantProfile = null;
        $completedSteps = [];
        $allStepsCompleted = false;

        if ($test) {
            // Vérifier les étapes complétées
            $completedSteps = $this->getCompletedSteps($test);
            $requiredSteps = [
                'personalInfo',
                'riasec',
                'personality',
                'interests',
                'careerCompatibility',
                'constraints',
                'languageSkills'
            ];
            $allStepsCompleted = count($completedSteps) === count($requiredSteps);
            
            // Le test est considéré comme terminé UNIQUEMENT si toutes les étapes sont complétées
            // Peu importe l'étape actuelle ou le flag isCompleted()
            if ($allStepsCompleted) {
                $testStatus = 'finalisé';
                // Calculer le profil dominant depuis les scores RIASEC
                $testData = $this->formatTestData($test);
                if (isset($testData['riasecScores']['scores'])) {
                    $scores = $testData['riasecScores']['scores'];
                    // Trouver le score maximum
                    $maxScore = -1;
                    $dominantType = null;
                    $riasecMapping = [
                        'R' => ['R', 'Realiste', 'Réaliste'],
                        'I' => ['I', 'Investigateur'],
                        'A' => ['A', 'Artistique'],
                        'S' => ['S', 'Social'],
                        'E' => ['E', 'Entreprenant'],
                        'C' => ['C', 'Conventionnel']
                    ];
                    
                    foreach ($riasecMapping as $type => $keys) {
                        foreach ($keys as $key) {
                            if (isset($scores[$key]) && $scores[$key] > $maxScore) {
                                $maxScore = $scores[$key];
                                $dominantType = $type;
                            }
                        }
                    }
                    
                    if ($dominantType) {
                        $dominantProfile = $dominantType;
                    }
                }
            } else if (count($completedSteps) > 0) {
                $testStatus = 'en_cours';
                $currentStep = $test->getTestType();
            }
        }

        // Générer le code utilisateur s'il n'existe pas
        if (!$user->getUserCode()) {
            $user->generateUserCode();
            $this->entityManager->flush();
        }

        return [
            'id' => $user->getId(),
            'userCode' => $user->getUserCode(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'telephone' => $user->getTelephone(),
            'whatsappNumber' => $user->getWhatsappNumber(),
            'age' => $user->getAge(),
            'createdAt' => $user->getCreatedAt()?->format('c'),
            'lastLoginAt' => $user->getLastLoginAt()?->format('c'),
            'isPresent' => $user->isPresent(),
            'qrCode' => $user->getQrCode(),
            'passwordPlain' => $user->getPasswordPlain(), // Mot de passe en clair pour l'invitation
            'testStatus' => $testStatus,
            'currentStep' => $currentStep,
            'testCompleted' => $allStepsCompleted, // Basé uniquement sur les étapes complétées
            'completedSteps' => $completedSteps,
            'allStepsCompleted' => $allStepsCompleted,
            'dominantProfile' => $dominantProfile,
            'isStaff' => $user->isStaff(),
            'isSuperAdmin' => $user->isSuperAdmin()
        ];
    }

    private function formatTestData(OrientationTest $test): array
    {
        $metadata = $test->getMetadata() ?? [];
        $currentStep = $test->getCurrentStep() ?? [];
        
        $data = [
            'uuid' => $test->getUuid(),
            'selectedLanguage' => $test->getLanguage(),
            'isCompleted' => $test->isCompleted(),
            'currentStepId' => $test->getTestType(),
            'testMetadata' => $metadata
        ];

        if (!empty($currentStep)) {
            $data['currentStep'] = $currentStep;
            
            if (isset($currentStep['personalInfo'])) {
                if (isset($currentStep['personalInfo']['personalInfo']) && is_array($currentStep['personalInfo']['personalInfo'])) {
                    $data['personalInfo'] = $currentStep['personalInfo']['personalInfo'];
                } else {
                    $data['personalInfo'] = $currentStep['personalInfo'];
                }
            }
            if (isset($currentStep['riasec'])) {
                if (isset($currentStep['riasec']['riasec']) && is_array($currentStep['riasec']['riasec'])) {
                    $data['riasecScores'] = $currentStep['riasec']['riasec'];
                } else {
                    $data['riasecScores'] = $currentStep['riasec'];
                }
            }
            if (isset($currentStep['personality'])) {
                if (isset($currentStep['personality']['personality']) && is_array($currentStep['personality']['personality'])) {
                    $data['personalityScores'] = $currentStep['personality']['personality'];
                } else {
                    $data['personalityScores'] = $currentStep['personality'];
                }
            }
            if (isset($currentStep['interests'])) {
                if (isset($currentStep['interests']['interests']) && is_array($currentStep['interests']['interests'])) {
                    $data['academicInterests'] = $currentStep['interests']['interests'];
                } else {
                    $data['academicInterests'] = $currentStep['interests'];
                }
            }
            if (isset($currentStep['careerCompatibility'])) {
                $data['careerCompatibility'] = $currentStep['careerCompatibility'];
            } elseif (isset($currentStep['careers'])) {
                $data['careerCompatibility'] = $currentStep['careers'];
            }
            if (isset($currentStep['constraints'])) {
                if (isset($currentStep['constraints']['constraints']) && is_array($currentStep['constraints']['constraints'])) {
                    $data['constraints'] = $currentStep['constraints']['constraints'];
                } else {
                    $data['constraints'] = $currentStep['constraints'];
                }
            }
            if (isset($currentStep['languages'])) {
                $data['languageSkills'] = $currentStep['languages'];
            } elseif (isset($currentStep['languageSkills'])) {
                if (isset($currentStep['languageSkills']['languages']) && is_array($currentStep['languageSkills']['languages'])) {
                    $data['languageSkills'] = $currentStep['languageSkills']['languages'];
                } else {
                    $data['languageSkills'] = $currentStep['languageSkills'];
                }
            }

            // Si le test est complété, calculer l'analyse
            if ($test->isCompleted()) {
                // L'analyse devrait être calculée côté frontend, mais on peut aussi la calculer ici
                // Pour l'instant, on laisse le frontend le faire
            }
        }

        return $data;
    }

    // ============================================
    // STAFF MANAGEMENT ENDPOINTS
    // ============================================

    #[Route('/staff', name: 'staff_list', methods: ['GET'])]
    public function listStaff(Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur est super admin
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User || !$currentUser->isSuperAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Accès refusé. Seuls les super administrateurs peuvent gérer le staff.'
            ], 403);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 20)));
        $search = $request->query->get('search', '');
        $offset = ($page - 1) * $limit;

        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.isStaff = true');

        // Recherche
        if (!empty($search)) {
            $qb->andWhere('(u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search OR u.telephone LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        $total = (int) $qb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        // Récupérer les membres du staff
        $staff = $qb->select('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $staffData = [];
        foreach ($staff as $member) {
            $staffData[] = [
                'id' => $member->getId(),
                'firstName' => $member->getFirstName(),
                'lastName' => $member->getLastName(),
                'email' => $member->getEmail(),
                'telephone' => $member->getTelephone(),
                'createdAt' => $member->getCreatedAt()?->format('c'),
                'lastLoginAt' => $member->getLastLoginAt()?->format('c'),
                'isStaff' => $member->isStaff(),
                'isSuperAdmin' => $member->isSuperAdmin(),
                'roles' => $member->getRoles()
            ];
        }

        return new JsonResponse([
            'success' => true,
            'data' => $staffData,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $limit)
            ]
        ]);
    }

    #[Route('/staff', name: 'staff_create', methods: ['POST'])]
    public function createStaff(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        // Vérifier que l'utilisateur est super admin
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User || !$currentUser->isSuperAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Accès refusé. Seuls les super administrateurs peuvent créer des membres du staff.'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        // Validation
        if (empty($data['email']) || empty($data['password'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Email et mot de passe requis'
            ], 400);
        }

        // Vérifier si l'email existe déjà
        $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Un utilisateur avec cet email existe déjà'
            ], 400);
        }

        // Créer le nouveau membre du staff
        $staff = new User();
        $staff->setEmail($data['email']);
        $staff->setPassword($passwordHasher->hashPassword($staff, $data['password']));
        $staff->setFirstName($data['firstName'] ?? null);
        $staff->setLastName($data['lastName'] ?? null);
        $staff->setTelephone($data['telephone'] ?? null);
        $staff->setIsStaff(true);
        $staff->setIsSuperAdmin(false);
        $staff->setRoles(['ROLE_ADMIN']);

        $this->entityManager->persist($staff);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Membre du staff créé avec succès',
            'data' => [
                'id' => $staff->getId(),
                'firstName' => $staff->getFirstName(),
                'lastName' => $staff->getLastName(),
                'email' => $staff->getEmail(),
                'telephone' => $staff->getTelephone(),
                'isStaff' => $staff->isStaff(),
                'isSuperAdmin' => $staff->isSuperAdmin()
            ]
        ], 201);
    }

    #[Route('/staff/{id}', name: 'staff_update', methods: ['PUT'])]
    public function updateStaff(int $id, Request $request): JsonResponse
    {
        // Vérifier que l'utilisateur est super admin
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User || !$currentUser->isSuperAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Accès refusé. Seuls les super administrateurs peuvent modifier les membres du staff.'
            ], 403);
        }

        $staff = $this->userRepository->find($id);
        
        if (!$staff) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Membre du staff non trouvé'
            ], 404);
        }

        if (!$staff->isStaff()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Cet utilisateur n\'est pas un membre du staff'
            ], 400);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['firstName'])) {
            $staff->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $staff->setLastName($data['lastName']);
        }
        if (isset($data['email'])) {
            // Vérifier si l'email existe déjà pour un autre utilisateur
            $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
            if ($existingUser && $existingUser->getId() !== $staff->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Un utilisateur avec cet email existe déjà'
                ], 400);
            }
            $staff->setEmail($data['email']);
        }
        if (isset($data['telephone'])) {
            $staff->setTelephone($data['telephone']);
        }
        if (isset($data['isSuperAdmin'])) {
            // Seul un super admin peut modifier le statut super admin
            $staff->setIsSuperAdmin((bool) $data['isSuperAdmin']);
        }

        $staff->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Membre du staff mis à jour avec succès',
            'data' => [
                'id' => $staff->getId(),
                'firstName' => $staff->getFirstName(),
                'lastName' => $staff->getLastName(),
                'email' => $staff->getEmail(),
                'telephone' => $staff->getTelephone(),
                'isStaff' => $staff->isStaff(),
                'isSuperAdmin' => $staff->isSuperAdmin()
            ]
        ]);
    }
}

