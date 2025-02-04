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
     * Vérifie le token et redirige si nécessaire
     *
     * @param Request $request La requête HTTP
     * @return array|null Le temps restant ou null si redirection
     */
    private function checkTokenAndRedirectIfNeeded(Request $request): ?array
    {
        $timeLeft = $this->tokenChecker->checkTokenAndGetRemainingTime($request->getSession());
        if ($timeLeft['status'] === 'not_present') {
            $this->addFlash('danger', 'Vous devez vous connecter pour accéder à cette page');
            return $this->redirectToRoute('app_login')->send();
        }
        if ($timeLeft['status'] === 'expired') {
            $this->addFlash('danger', 'Votre session a expiré, veuillez vous reconnecter');
            return $this->redirectToRoute('app_login')->send();
        }
        return $timeLeft;
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
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

        $page = $request->query->get('page', 1);
        $limit = 10;
        $response = $this->client->request('GET', 'http://localhost:8989/comics', [
            'query' => [
                'page' => $page,
                'limit' => $limit
            ]
        ]);       
        $data = $response->toArray();

        return $this->render('admin/comics/index.html.twig', [
            'data' => $data,
            'secondsLeft' => $timeLeft['secondsLeft'],
            'minutesLeft' => $timeLeft['minutesLeft'],
            'hoursLeft'   => $timeLeft['hoursLeft'],
            'currentPage' => $page,
            'totalPages' => $data['pages']
        ]);
    }

    /**
     * Affiche la page admin affichant les détails d'un comic
     * 
     * @param Request $request La requête HTTP
     * @param string $slug Le slug du comic
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-comics/details/{slug}', name: 'admin_comics_show', methods: ['GET'])]
    public function showComic(Request $request, string $slug): Response
    {
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

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
        return $this->render('admin/comics/show.html.twig', [
            'data' => $comic,
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
    #[Route('/admin/les-comics/formulaire', name: 'admin_comics_add')]
    public function addComic(Request $request): Response
    {
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

        $response = $this->client->request('GET', 'http://localhost:8989/authors');        
        $authors = $response->toArray();
        
        return $this->render('admin/comics/add.html.twig', [
            'authors' => $authors['authors'],
            'secondsLeft' => $timeLeft['secondsLeft'],
            'minutesLeft' => $timeLeft['minutesLeft'],
            'hoursLeft'   => $timeLeft['hoursLeft'],
        ]);
    }

    /**
     * Traite le formulaire d'ajout d'un comic
     * 
     * @param Request $request La requête HTTP
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-comics/ajouter', name: 'admin_comics_add_post', methods: ['POST'])]
    public function addComicPost(Request $request): Response
    {
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

        $title = $request->request->get('title');
        $slug = $request->request->get('slug');
        $collection = $request->request->get('collection');
        $tome = $request->request->get('tome');
        $description = $request->request->get('description');
        $authorId = $request->request->get('authorId');

        // Supprime les accents
        $slug = transliterator_transliterate('Any-Latin; Latin-ASCII', $title);
        // Remplace les espaces et caractères spéciaux par des tirets
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $slug);
        // Supprime les tirets en début et fin de chaîne
        $slug = trim($slug, '-');
        // Convertit en minuscules
        $slug = strtolower($slug);

        $frontCover = $request->files->get('frontCover');
        if (!$frontCover){
            $this->addFlash('danger', 'Vous devez ajouter une image de couverture');
            return $this->redirectToRoute('admin_comics_add');
        }

        // Vérification du type d'image et extension
        $validExtensions = ['jpg', 'jpeg', 'png'];
        $extension = $frontCover->getClientOriginalExtension();

        if (!in_array(strtolower($extension), $validExtensions)) {
            $this->addFlash('danger', 'Le fichier doit être une image de type jpg, jpeg ou png');
            return $this->redirectToRoute('admin_comics_add');
        }

        // Renomme le fichier
        $tempFilePath = $frontCover->getPathname();
        $newFilePath = tempnam(sys_get_temp_dir(), 'comic_') . '.' . $extension;
        rename($tempFilePath, $newFilePath);

        // Envoie un multipart/form-data, donc on doit utiliser 'body' avec un tableau de champs et de fichiers
        try {
            $response = $this->client->request('POST', 'http://localhost:8989/comics', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $request->getSession()->get('comics_collection_jwt_token'),
                ],
                'body' => [
                    'title' => strip_tags($title), // strip_tags Enlève les balises HTML
                    'slug' => strip_tags($slug),
                    'collection' => strip_tags($collection),
                    'tome' => strip_tags($tome),
                    'description' => strip_tags($description),
                    'authorId' => intval($authorId),
                    'frontCover' => fopen($newFilePath, 'r'),
                ],
            ]);

            // Vérifie la réponse
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                // Succès
                $data = $response->toArray(); // Décode le JSON en tableau associatif
                // $data contiendra l’objet "author" créé, selon ce que votre API renvoie
                $this->addFlash('success', sprintf('Comic "%s" ajouté avec succès.', $title));
            } else {
                // Erreur renvoyée par l’API
                $errorContent = $response->getContent(false);
                $this->addFlash('danger', 'Erreur API Node: ' . $errorContent);
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Impossible de contacter l’API Node: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_comics');
    }
}
