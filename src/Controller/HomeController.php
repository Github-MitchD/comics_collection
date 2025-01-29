<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Contrôleur de la page d'accueil
 */
final class HomeController extends AbstractController
{
    private const COMICS_API_URL = 'http://localhost:8989';
    private HttpClientInterface $client;

    /**
     * Constructeur avec injection de dépendance du client HTTP.
     *
     * @param HttpClientInterface $client Le client HTTP.
     */
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Affiche la page d'accueil
     *
     * @return Response La réponse HTTP
     */
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        try {
            $response = $this->client->request('GET', self::COMICS_API_URL.'/comics');
            $data = $response->toArray();
        } catch (TransportExceptionInterface | ClientExceptionInterface | ServerExceptionInterface $e) {
            // Gére les exceptions et retourne une réponse appropriée
            return $this->render('error.html.twig', [
                'message' => 'Erreur lors de la récupération des données des comics.',
            ]);
        }

        return $this->render('home/index.html.twig', [
            'data' => $data,
        ]);
    }
}
