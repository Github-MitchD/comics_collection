<?php

namespace App\Controller\Admin;

use App\Service\TokenChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Contrôleur responsable de la gestion des comics
 */
final class AdminComicsController extends AbstractController
{
    private HttpClientInterface $client;
    private TokenChecker $tokenChecker;

    /**
     * Constructeur avec injection de dépendance du client HTTP et du service de vérification du token
     *
     * @param HttpClientInterface $client Le client HTTP
     * @param TokenChecker $tokenChecker Le service de vérification du token
     */
    public function __construct(HttpClientInterface $client, TokenChecker $tokenChecker)
    {
        $this->client = $client;
        $this->tokenChecker = $tokenChecker;
    }    

    /**
     * Affiche la page admin affichant la liste des comics
     * 
     * @param Request $request La requête HTTP
     * @return Response La réponse HTTP
     */
    #[Route('/admin/comics', name: 'admin_comics')]
    public function comics(Request $request): Response
    {
        $timeLeft = $this->tokenChecker->checkTokenAndGetRemainingTime($request->getSession());

        if ($timeLeft['status'] === 'not_present') {
            $this->addFlash('danger', 'Vous devez vous connecter pour accéder à cette page');
            return $this->redirectToRoute('app_login');
        }
        if ($timeLeft['status'] === 'expired') {
            $this->addFlash('danger', 'Votre session a expiré, veuillez vous reconnecter');
            return $this->redirectToRoute('app_login');
        }
        
        $response = $this->client->request('GET', 'http://localhost:8989/comics');        
        $data = $response->toArray();

        return $this->render('admin/comics/index.html.twig', [
            'data' => $data,
            'secondsLeft' => $timeLeft['secondsLeft'],
            'minutesLeft' => $timeLeft['minutesLeft'],
            'hoursLeft'   => $timeLeft['hoursLeft'],
        ]);
    }

    /**
     * Affiche le formulaire d'ajout d'un comic
     *  
     * @param Request $request La requête HTTP
     * @return Response La réponse HTTP
     */
    #[Route('/admin/comics/add', name: 'admin_comics_add')]
    public function comicAdd(Request $request): Response
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

        $response = $this->client->request('GET', 'http://localhost:8989/authors');        
        $authors = $response->toArray();
        
        return $this->render('admin/comics/add.html.twig', [
            'authors' => $authors['authors'],
            'secondsLeft' => $timeLeft['secondsLeft'],
            'minutesLeft' => $timeLeft['minutesLeft'],
            'hoursLeft'   => $timeLeft['hoursLeft'],
        ]);
    }
}
