<?php

namespace App\Service;

use App\Entity\User;

class QrCodeService
{
    /**
     * Génère un QR code unique pour un utilisateur
     * Format: EVENT_USER_{userId}_{timestamp}_{random}
     */
    public function generateQrCode(User $user): string
    {
        if ($user->getQrCode()) {
            return $user->getQrCode();
        }

        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $userId = $user->getId() ?? 0;
        
        $qrCode = sprintf('EVENT_USER_%d_%s_%s', $userId, $timestamp, $random);
        
        $user->setQrCode($qrCode);
        
        return $qrCode;
    }

    /**
     * Parse un QR code et retourne l'ID utilisateur
     */
    public function parseQrCode(string $qrCode): ?int
    {
        if (preg_match('/^EVENT_USER_(\d+)_\d+_[a-f0-9]+$/', $qrCode, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
}

