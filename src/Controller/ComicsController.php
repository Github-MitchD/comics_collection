<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Contrôleur pour les comics
 */
final class ComicsController extends AbstractController
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

    /**
     * Affiche la page listant les comics
     * 
     * @param Request $request La requête HTTP
     * @return Response La réponse HTTP
     */
    #[Route('/comics', name: 'app_comics')]
    public function index(Request $request): Response
    {
        $page = $request->query->get('page', 1);
        $limit = 10;
        $response = $this->client->request('GET', self::COMICS_API_URL.'/comics', [
            'query' => [
                'page' => $page,
                'limit' => $limit
            ]
        ]);       
        $data = $response->toArray();
        return $this->render('comics/index.html.twig', [
            'data' => $data,
            'currentPage' => $page,
            'totalPages' => $data['pages']
        ]);
    }

    /**
     * Affiche la page affichant les détails d'un comic
     * 
     * @param Request $request La requête HTTP
     * @param string $slug Le slug du comic
     * @return Response La réponse HTTP
     */
    #[Route('/les-comics/details/{slug}', name: 'app_comics_show', methods: ['GET'])]
    public function showComic(string $slug): Response
    {
        $apiUrl = 'http://localhost:8989/comics/title/' . $slug;

        $response = $this->client->request('GET', $apiUrl);
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            // Récupère les données JSON
            $comic = $response->toArray(); // renvoie un tableau associatif
        } else {
            $errorContent = $response->getContent(false);
            $this->addFlash('danger', 'Erreur API Node: ' . $errorContent);
            return $this->redirectToRoute('admin_comics');
        }
        return $this->render('comics/show.html.twig', [
            'data' => $comic
        ]);
    }
}
