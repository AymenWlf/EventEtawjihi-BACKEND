<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\RoundBlockSizeMode;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/apis', name: 'api_')]
class AuthController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Récupérer le contenu brut de la requête
        $content = $request->getContent();
        
        // Parser le JSON
        $data = json_decode($content, true);
        
        // Vérifier si le JSON est valide
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Téléphone ou mot de passe manquant',
                'errors' => ['Format JSON invalide']
            ], 400);
        }

        // Récupérer email/telephone et password
        $identifier = $data['email'] ?? $data['telephone'] ?? $data['téléphone'] ?? null;
        $password = $data['password'] ?? null;

        // Validation
        if (empty($identifier) || empty($password)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Téléphone ou mot de passe manquant',
                'errors' => ['Les champs téléphone/email et mot de passe sont obligatoires']
            ], 400);
        }

        // Nettoyer les valeurs
        $identifier = trim((string)$identifier);
        $password = trim((string)$password);

        // Chercher l'utilisateur par email (on traite le téléphone comme un email pour l'instant)
        $user = $this->userRepository->findOneBy(['email' => $identifier]);

        // Vérifier l'utilisateur et le mot de passe
        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Identifiants invalides',
                'errors' => ['Email/téléphone ou mot de passe incorrect']
            ], 401);
        }

        // Mettre à jour lastLoginAt
        $user->setLastLoginAt(new \DateTimeImmutable());
        
        // Générer le QR code s'il n'existe pas
        if (!$user->getQrCode()) {
            $user->generateQrCode();
        }
        
        $this->entityManager->flush();

        // Générer un token JWT avec Lexik JWT Authentication Bundle
        $token = $this->jwtManager->create($user);

        return new JsonResponse([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => (string) $user->getId(),
                    'email' => $user->getEmail(),
                    'name' => $user->getName() ?? explode('@', $user->getEmail())[0],
                    'roles' => $user->getRoles()
                ]
            ]
        ]);
    }

    #[Route('/auth/me', name: 'auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        // Récupérer l'utilisateur authentifié via JWT (géré par le firewall)
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        return new JsonResponse([
            'success' => true,
            'data' => [
                'id' => (string) $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName() ?? explode('@', $user->getEmail())[0],
                'roles' => $user->getRoles(),
                'isStaff' => $user->isStaff(),
                'isSuperAdmin' => $user->isSuperAdmin()
            ]
        ]);
    }

    #[Route('/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    #[Route('/auth/my-qr-code', name: 'auth_my_qr_code', methods: ['GET'])]
    public function getMyQrCode(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        // Générer le QR code s'il n'existe pas
        if (!$user->getQrCode()) {
            $user->generateQrCode();
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'success' => true,
            'data' => ['qrCode' => $user->getQrCode()]
        ]);
    }

    #[Route('/auth/my-qr-code-pdf', name: 'auth_my_qr_code_pdf', methods: ['GET'])]
    public function downloadMyQrCodePdf(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        // Générer le QR code s'il n'existe pas
        if (!$user->getQrCode()) {
            $user->generateQrCode();
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
}

