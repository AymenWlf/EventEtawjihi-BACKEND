<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/apis', name: 'api_')]
class AuthController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        // Récupérer les données JSON du body
        $content = $request->getContent();
        $data = [];
        
        // Parser le JSON
        if (!empty($content)) {
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $data = [];
            }
        }
        
        // Si pas de données JSON, essayer avec toArray() (Symfony 6.1+)
        if (empty($data)) {
            try {
                $data = $request->toArray();
            } catch (\Exception $e) {
                // Si toArray() échoue, essayer avec request->request (form-data)
                $data = $request->request->all();
            }
        }

        // Accepter soit "email" soit "telephone" ou "téléphone"
        $email = null;
        $password = null;
        
        if (is_array($data)) {
            $email = $data['email'] ?? $data['telephone'] ?? $data['téléphone'] ?? null;
            $password = $data['password'] ?? $data['mot_de_passe'] ?? $data['motDePasse'] ?? null;
        }

        // Nettoyer les valeurs (trim et convertir en string)
        if ($email !== null) {
            $email = trim((string)$email);
        }
        if ($password !== null) {
            $password = trim((string)$password);
        }

        // Validation
        if (empty($email) || empty($password)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Téléphone ou mot de passe manquant',
                'errors' => ['Les champs téléphone/email et mot de passe sont obligatoires']
            ], 400);
        }

        // Vérifier l'utilisateur dans la base de données
        // Pour l'instant, on traite le téléphone comme un email
        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Identifiants invalides',
                'errors' => ['Email ou mot de passe incorrect']
            ], 401);
        }

        // Générer un token JWT simple (base64 encodé pour l'instant)
        // TODO: Utiliser une vraie bibliothèque JWT (lexik/jwt-authentication-bundle)
        $tokenPayload = [
            'email' => $user->getEmail(),
            'sub' => (string) $user->getId(),
            'roles' => $user->getRoles(),
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 heures
        ];

        // Encoder en base64 (temporaire, utiliser une vraie lib JWT en production)
        $token = base64_encode(json_encode($tokenPayload));

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
    public function me(Request $request): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Token manquant'
            ], 401);
        }

        $token = substr($authHeader, 7);

        try {
            // Décoder le token
            $payload = json_decode(base64_decode($token), true);

            if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token expiré ou invalide'
                ], 401);
            }

            // Récupérer l'utilisateur depuis la base de données
            $userId = $payload['sub'] ?? null;
            if ($userId) {
                $user = $this->userRepository->find($userId);
                if ($user) {
                    return new JsonResponse([
                        'success' => true,
                        'data' => [
                            'id' => (string) $user->getId(),
                            'email' => $user->getEmail(),
                            'name' => $user->getName() ?? explode('@', $user->getEmail())[0],
                            'roles' => $user->getRoles()
                        ]
                    ]);
                }
            }

            // Fallback sur les données du token si l'utilisateur n'est pas trouvé
            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $payload['sub'] ?? null,
                    'email' => $payload['email'] ?? null,
                    'name' => explode('@', $payload['email'] ?? '')[0] ?? 'User',
                    'roles' => $payload['roles'] ?? []
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Token invalide'
            ], 401);
        }
    }

    #[Route('/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        // En JWT, la déconnexion se fait côté client en supprimant le token
        return new JsonResponse([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }
}

