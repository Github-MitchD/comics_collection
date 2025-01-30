<?php

namespace App\Controller\Admin;

use App\Service\TokenChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Contrôleur responsable de la gestion des auteurs
 */
final class AdminAuthorsController extends AbstractController
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
        $this->client =  $client;
        $this->tokenChecker = $tokenChecker;
    }

    /**
     * Affiche la page admin affichant la liste des auteurs
     * 
     * @param Request $request La requête HTTP
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-auteurs', name: 'admin_authors')]
    public function authors(Request $request): Response
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

        $response = $this->client->request('GET', 'http://localhost:8989/authors');
        $data = $response->toArray();

        return $this->render('admin/authors/index.html.twig', [
            'data' => $data,
            'secondsLeft' => $timeLeft['secondsLeft'],
            'minutesLeft' => $timeLeft['minutesLeft'],
            'hoursLeft'   => $timeLeft['hoursLeft'],
        ]);
    }

    /**
     * Affiche le formulaire d'ajout d'un auteur
     *
     * @param Request $request La requête HTTP
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-auteurs/formulaire', name: 'admin_authors_add')]
    public function addAuthor(Request $request): Response
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
        
        return $this->render('admin/authors/add.html.twig', [
            'secondsLeft' => $timeLeft['secondsLeft'],
            'minutesLeft' => $timeLeft['minutesLeft'],
            'hoursLeft'   => $timeLeft['hoursLeft'],
        ]);
    }
    
    /**
     * Traite la requête d'ajout d'un auteur
     *
     * @param Request $request La requête HTTP
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-auteurs/ajouter', name: 'admin_authors_add_post', methods: ['POST'])]
    public function addAuthorPost(Request $request): Response
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

        $lastname = $request->request->get('lastname');
        $firstname = $request->request->get('firstname');
        $birthdate = $request->request->get('birthdate');
        $website = $request->request->get('website');
        $biography = $request->request->get('biography');

        // Récupére le fichier (Symfony stocke ça dans $request->files)
        $profileImage = $request->files->get('profileImage');

        if (!$profileImage) {
            $this->addFlash('danger', 'Aucune image n’a été transmise.');
            return $this->redirectToRoute('admin_authors_add');
        }  
        if (!in_array($profileImage->getMimeType(), ['image/jpeg','image/jpg','image/png'])) {
            $this->addFlash('danger', 'Format d\'image non supporté.');
            return $this->redirectToRoute('admin_authors_add');
        }      
        if (empty($lastname) || empty($firstname)) {
            $this->addFlash('danger', 'Veuillez renseigner un nom et un prénom.');
            return $this->redirectToRoute('admin_authors_add');
        }
        
        $fullname = trim($firstname . ' ' . $lastname);
        $slug = strtolower(str_replace(' ', '-', $fullname));

        // Envoie un multipart/form-data, donc on doit utiliser 'body' avec un tableau de champs et de fichiers
        try {
            $response = $this->client->request('POST', 'http://localhost:8989/authors', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $request->getSession()->get('comics_collection_jwt_token'),
                ],
                'body' => [
                    'name' => htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8'),
                    'slug' => htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'),
                    'birthdate' => htmlspecialchars($birthdate, ENT_QUOTES, 'UTF-8'),
                    'bio' => htmlspecialchars($biography, ENT_QUOTES, 'UTF-8'),
                    'website' => htmlspecialchars($website, ENT_QUOTES, 'UTF-8'),
                    'image' => fopen($profileImage->getPathname(), 'r'),
                ],
            ]);

            // Vérifie la réponse
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                // Succès
                $data = $response->toArray(); // Décode le JSON en tableau associatif
                // $data contiendra l’objet "author" créé, selon ce que votre API renvoie
                $this->addFlash('success', sprintf('Auteur "%s" ajouté avec succès.', $fullname));
            } else {
                // Erreur renvoyée par l’API
                $errorContent = $response->getContent(false);
                $this->addFlash('danger', 'Erreur API Node: ' . $errorContent);
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Impossible de contacter l’API Node: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_authors_add');
    }
}
