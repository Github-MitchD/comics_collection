<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Contrôleur pour les auteurs
 */
final class AuthorsController extends AbstractController
{
    private const COMICS_API_URL = 'http://localhost:8989';
    private HttpClientInterface $client;

    /**
     * Constructeur avec injection de dépendance du client HTTP
     * 
     * @param HttpClientInterface $client Le client HTTP
     */
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }
    
    #[Route('/les-auteurs', name: 'app_authors')]
    public function index(Request $request): Response
    {
        $page = $request->query->get('page', 1);
        $limit = 10;
        $response = $this->client->request('GET', self::COMICS_API_URL.'/authors', [
            'query' => [
                'page' => $page,
                'limit' => $limit
            ]
        ]);       
        $data = $response->toArray();
        return $this->render('authors/index.html.twig', [
            'data' => $data,
            'currentPage' => $page,
            'totalPages' => $data['pages']
        ]);
    }

    #[Route('/les-auteurs/{slug}', name: 'app_author_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $response = $this->client->request('GET', self::COMICS_API_URL.'/authors/name/'.$slug);
        $data = $response->toArray();
        return $this->render('authors/show.html.twig', [
            'data' => $data
        ]);
    }
}
