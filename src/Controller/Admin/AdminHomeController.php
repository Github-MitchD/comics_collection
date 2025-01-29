<?php

namespace App\Controller\Admin;

use App\Service\TokenChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Contrôleur de la page d'accueil de l'administration
 */
final class AdminHomeController extends AbstractController
{
    private TokenChecker $tokenChecker;

    /**
     * Constructeur avec injection de dépendance du service de vérification du token
     *
     * @param TokenChecker $tokenChecker Le service de vérification du token
     */
    public function __construct(TokenChecker $tokenChecker)
    {
        $this->tokenChecker = $tokenChecker;
    } 

    /**
     * Affiche la page d'accueil de l'administration
     *
     * @param Request $request La requête HTTP
     * @return Response La réponse HTTP
     */
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(Request $request): Response
    {
        $timeLeft = $this->tokenChecker->checkTokenAndGetRemainingTime($request->getSession());

        if ($timeLeft['status'] === 'not_present') {
            $this->addFlash('danger', 'Vous devez vous connecter pour accéder à cette page');
            return $this->redirectToRoute('app_login');
        }
        if ($timeLeft['status'] === 'expired') {
            $this->addFlash('danger', 'Votre session a expiré, veuillez vous reconnecter');
            return $this->redirectToRoute('app_login');
        };
        
        $token_api = $request->getSession()->get('comics_collection_jwt_token');

        return $this->render('admin/dashboard.html.twig', [
            'token_api' => $token_api,
            'secondsLeft' => $timeLeft['secondsLeft'],
            'minutesLeft' => $timeLeft['minutesLeft'],
            'hoursLeft'   => $timeLeft['hoursLeft'],
        ]);
    }
}
