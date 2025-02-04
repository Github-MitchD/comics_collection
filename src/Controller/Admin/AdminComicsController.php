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
        $limit = 6;
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

    /**
     * Affiche le formulaire de modification d'un comic
     * 
     * @param Request $request La requête HTTP
     * @param string $slug Le slug du comic
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-comics/form/{slug}', name: 'admin_comics_edit', methods: ['GET'])]
    public function editComic(Request $request, string $slug): Response
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

        $response = $this->client->request('GET', 'http://localhost:8989/authors');        
        $authors = $response->toArray();

        return $this->render('admin/comics/edit.html.twig', [
            'data' => $comic,
            'authors' => $authors['authors'],
            'secondsLeft' => $timeLeft['secondsLeft'],
            'minutesLeft' => $timeLeft['minutesLeft'],
            'hoursLeft'   => $timeLeft['hoursLeft'],
        ]);
    }

    /**
     * Traite le formulaire de modification d'un comic
     * 
     * @param Request $request La requête HTTP
     * @param int $id L'identifiant du comic à modifier
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-comics/modifier/{id}', name: 'admin_comics_edit_post', methods: ['POST'])]
    public function editComicPost(Request $request, int $id): Response
    {
        // Vérification du token
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

        // Récupération des champs du formulaire
        $title = $request->request->get('title');
        $collection = $request->request->get('collection');
        $tome = $request->request->get('tome');
        $authorId = $request->request->get('authorId');
        $description = $request->request->get('description');
        $frontCover = $request->files->get('frontCover');

        // Supprime les accents
        $slug = transliterator_transliterate('Any-Latin; Latin-ASCII', $title);
        // Remplace les espaces et caractères spéciaux par des tirets
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $slug);
        // Supprime les tirets en début et fin de chaîne
        $slug = trim($slug, '-');
        // Convertit en minuscules
        $slug = strtolower($slug);

        // Préparation des données de base
        $data = [
            'title' => strip_tags($title),
            'slug' => strip_tags($slug),
            'collection' => strip_tags($collection),
            'tome' => strip_tags($tome),
            'description' => strip_tags($description),
            'authorId' => intval($authorId)
        ];

        try {
            if ($frontCover) {
                // Cas avec image : on prépare l’upload
                $validExtensions = ['jpg', 'jpeg', 'png'];
                $extension = $frontCover->getClientOriginalExtension();
                if (!in_array(strtolower($extension), $validExtensions)) {
                    $this->addFlash('danger', 'Le fichier doit être une image de type jpg, jpeg ou png');
                    return $this->redirectToRoute('admin_comics_edit', ['id' => $id]);
                }

                // Renomme le fichier
                $tempFilePath = $frontCover->getPathname();
                $newFilePath = tempnam(sys_get_temp_dir(), 'comic_') . '.' . $extension;
                rename($tempFilePath, $newFilePath);

                // On ouvre le fichier pour le multi-part
                $imageResource = fopen($newFilePath, 'r');

                // Envoie un multipart/form-data, donc on doit utiliser 'body' avec un tableau de champs et de fichiers
                $response = $this->client->request('PUT', 'http://localhost:8989/comics/id/withImage/' . $id, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $request->getSession()->get('comics_collection_jwt_token'),
                    ],
                    'body' => array_merge($data, [
                        'frontCover' => $imageResource
                    ]),
                ]);
            } else {
                // Cas sans image : on envoie les données de base
                $response = $this->client->request('PUT', 'http://localhost:8989/comics/id/withoutImage/' . $id, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $request->getSession()->get('comics_collection_jwt_token'),
                    ],
                    'json' => $data,
                ]);
            }

            // Vérifie la réponse
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                // Succès
                $this->addFlash('success', sprintf('Comic "%s" mis à jour avec succès.', $title));
                return $this->redirectToRoute('admin_comics');
            } else {
                // Erreur renvoyée par l’API
                $errorContent = $response->getContent(false);
                $this->addFlash('danger', 'Erreur API Node: ' . $errorContent);
                return $this->redirectToRoute('admin_comics_edit', ['id' => $id]);
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Impossible de traiter l’image: ' . $e->getMessage());
            return $this->redirectToRoute('admin_comics_edit', ['id' => $id]);
        }
        return $this->redirectToRoute('admin_comics');
    }

    /**
     * Supprime un comic
     * 
     * @param Request $request La requête HTTP
     * @param int $id L'identifiant du comic à supprimer
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-comics/supprimer/{id}', name: 'admin_comics_delete', methods: ['POST'])]
    public function deleteComic(Request $request, int $id): Response
    {
        // Vérification du token
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

        // Vérification du token CSRF
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_comic_'.$id, $submittedToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $response = $this->client->request('DELETE', 'http://localhost:8989/comics/' . $id, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $request->getSession()->get('comics_collection_jwt_token'),
                ],
            ]);

            // Vérifie la réponse
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                // Succès
                $this->addFlash('success', 'Comic supprimé avec succès.');
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
