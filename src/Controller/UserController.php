<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/apis/user', name: 'api_user_')]
class UserController extends AbstractController
{
    #[Route('/profile', name: 'profile', methods: ['GET'])]
    public function profile(): JsonResponse
    {
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
                'telephone' => null, // À ajouter si vous avez un champ téléphone dans User
                'prenom' => null, // À ajouter si vous avez un champ prénom dans User
                'nom' => null, // À ajouter si vous avez un champ nom dans User
                'firstName' => $user->getName() ?? null,
                'lastName' => null,
                'roles' => $user->getRoles()
            ]
        ]);
    }
}

