<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Classe permettant de vérifier si le token JWT est présent, s'il est expiré ou s'il est valide
 */
class TokenChecker
{
    /**
     * Vérifie si le token JWT est présent, s'il est expiré ou s'il est valide
     * 
     * @param SessionInterface $session
     * @return array|null
     */
    public function checkTokenAndGetRemainingTime(SessionInterface $session): ?array
    {
        $expiresAtString = $session->get('comics_collection_jwt_expiresAt');
        
        if (!$expiresAtString) {
            return ['status' => 'not_present'];
        }

        $expiresAt = new \DateTime($expiresAtString);
        $now = new \DateTime();
        if ($now > $expiresAt) {
            $session->remove('comics_collection_jwt_token');
            $session->remove('comics_collection_jwt_expiresAt');
            return ['status' => 'expired'];
        }

        $remaining = $expiresAt->diff(new \DateTime());

        return [
            'status' => 'valid',
            'secondsLeft' => $remaining->s,
            'minutesLeft' => $remaining->i,
            'hoursLeft'   => $remaining->h,
        ];
    }
}
