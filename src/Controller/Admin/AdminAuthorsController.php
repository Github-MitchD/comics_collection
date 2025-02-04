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
     * Affiche la page admin affichant la liste des auteurs
     * 
     * @param Request $request La requête HTTP
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-auteurs', name: 'admin_authors')]
    public function authors(Request $request): Response
    {
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

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
     * Affiche la page admin affichant les détails d'un auteur
     * 
     * @param Request $request La requête HTTP
     * @param string $slug Le slug de l'auteur
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-auteurs/details/{slug}', name: 'admin_authors_show', methods: ['GET'])]
    public function showAuthor(Request $request, string $slug): Response
    {
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

        $apiUrl = 'http://localhost:8989/authors/name/' . $slug;

        $response = $this->client->request('GET', $apiUrl);
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            // Récupère les données JSON
            $author = $response->toArray(); // renvoie un tableau associatif
        } else {
            $errorContent = $response->getContent(false);
            $this->addFlash('danger', 'Erreur API Node: ' . $errorContent);
            return $this->redirectToRoute('admin_authors');
        }
        return $this->render('admin/authors/show.html.twig', [
            'data' => $author,
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
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();
        
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
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

        $lastname = $request->request->get('lastname');
        $firstname = $request->request->get('firstname');
        $birthdate = $request->request->get('birthdate');
        $website = $request->request->get('website');
        $biography = $request->request->get('biography');

        if (empty($lastname) || empty($firstname)) {
            $this->addFlash('danger', 'Veuillez renseigner un nom et un prénom.');
            return $this->redirectToRoute('admin_authors_add');
        }
        $fullname = trim($firstname . ' ' . $lastname);
        $slug = transliterator_transliterate('Any-Latin; Latin-ASCII', $fullname);
        // Remplace les espaces et caractères spéciaux par des tirets
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $slug);
        // Supprime les tirets en début et fin de chaîne
        $slug = trim($slug, '-');
        // Convertit en minuscules
        $slug = strtolower($slug);

        // Récupére le fichier (Symfony stocke ça dans $request->files)
        $profileImage = $request->files->get('profileImage');
        if (!$profileImage) {
            $this->addFlash('danger', 'Aucune image n’a été transmise.');
            return $this->redirectToRoute('admin_authors_add');
        }  

        // Vérification du type d'image et extension
        $validExtensions = ['jpeg', 'jpg', 'png'];
        $extension = $profileImage->getClientOriginalExtension();

        if (!in_array(strtolower($extension), $validExtensions)) {
            $this->addFlash('danger', 'Format d\'image non supporté.');
            return $this->redirectToRoute('admin_authors_add');
        }    
        // Renomme le fichier avec une extension appropriée
        $tempFilePath = $profileImage->getPathname();
        $newFilePath = tempnam(sys_get_temp_dir(), 'author_') . '.' . $extension;
        rename($tempFilePath, $newFilePath);

        // Envoie un multipart/form-data, donc on doit utiliser 'body' avec un tableau de champs et de fichiers
        try {
            $response = $this->client->request('POST', 'http://localhost:8989/authors', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $request->getSession()->get('comics_collection_jwt_token'),
                ],
                'body' => [
                    'name' => strip_tags($fullname), // strip_tags Enlève les balises HTML
                    'slug' => strip_tags($slug),
                    'birthdate' => strip_tags($birthdate),
                    'bio' => strip_tags($biography),
                    'website' => strip_tags($website),
                    'image' => fopen($newFilePath, 'r'),
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

        return $this->redirectToRoute('admin_authors');
    }

    /**
     * Affiche le formulaire de modification d'un auteur
     *
     * @param Request $request La requête HTTP
     * @param string $slug Le slug de l'auteur à modifier
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-auteurs/form/{slug}', name: 'admin_authors_edit', methods: ['GET'])]
    public function editAuthor(Request $request, string $slug): Response
    {
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

        // Récupère les informations de l'auteur via l'API
        $apiUrl = 'http://localhost:8989/authors/name/' . $slug;
        $response = $this->client->request('GET', $apiUrl);
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 300) {
            // Récupère les données de l'auteur
            $author = $response->toArray(); // Tableau associatif

            // Découpe le name en firstname et lastname
            $fullname = $author['name'];
            $nameParts = explode(' ', $fullname);
            $firstname = array_shift($nameParts);
            $lastname = implode(' ', $nameParts);

            // Ajoute les firstname et lastname aux données de l'auteur
            $author['firstname'] = $firstname;
            $author['lastname'] = $lastname;
        } else {
            $errorContent = $response->getContent(false);
            $this->addFlash('danger', 'Erreur API Node: ' . $errorContent);
            return $this->redirectToRoute('admin_authors');
        }
        return $this->render('admin/authors/edit.html.twig', [
            'data' => $author,
            'secondsLeft' => $timeLeft['secondsLeft'],
            'minutesLeft' => $timeLeft['minutesLeft'],
            'hoursLeft'   => $timeLeft['hoursLeft'],
        ]);
    }        

    /**
     * Traite la requête de modification d'un auteur
     *
     * @param Request $request La requête HTTP
     * @param int $id L'identifiant de l'auteur à modifier
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-auteurs/modifier/{id}', name: 'admin_authors_edit_post', methods: ['POST'])]
    public function editAuthorPost(Request $request, int $id): Response
    {
        // Vérification du token
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

        // Récupération des champs
        $lastname = $request->request->get('lastname');
        $firstname = $request->request->get('firstname');
        $birthdate = $request->request->get('birthdate');
        $website = $request->request->get('website');
        $biography = $request->request->get('biography');
        $profileImage = $request->files->get('profileImage');

        // Vérifications minimales
        if (empty($lastname) || empty($firstname)) {
            $this->addFlash('danger', 'Veuillez renseigner un nom et un prénom.');
            return $this->redirect($request->headers->get('referer'));
        }

        // Construction des champs dérivés
        $fullname = trim($firstname . ' ' . $lastname);
        $newSlug = strtolower(str_replace(' ', '-', $fullname));

        // Préparation des données de base
        $data = [
            'name'     => htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8'),
            'slug'     => htmlspecialchars($newSlug, ENT_QUOTES, 'UTF-8'),
            'birthdate' => htmlspecialchars($birthdate, ENT_QUOTES, 'UTF-8'),
            'bio'      => htmlspecialchars($biography, ENT_QUOTES, 'UTF-8'),
            'website'  => htmlspecialchars($website, ENT_QUOTES, 'UTF-8'),
        ];

        try {
            if ($profileImage) {
                // Cas avec image : on prépare l’upload
                $validExtensions = ['jpeg', 'jpg', 'png'];
                $extension = $profileImage->getClientOriginalExtension();
                if (!in_array(strtolower($extension), $validExtensions)) {
                    $this->addFlash('danger', 'Le fichier doit être une image de type jpg, jpeg ou png');
                    return $this->redirectToRoute('admin_authors_edit', ['id' => $id]);
                }

                // On déplace l’image vers un fichier temporaire
                $tempFilePath = $profileImage->getPathname();
                $newFilePath = tempnam(sys_get_temp_dir(), 'author_') . '.' . $extension;
                rename($tempFilePath, $newFilePath);

                // On ouvre le fichier pour le multi-part
                $imageResource = fopen($newFilePath, 'r');

                // Requête en multipart
                $response = $this->client->request('PUT', 'http://localhost:8989/authors/id/withImage/' . $id, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $request->getSession()->get('comics_collection_jwt_token'),
                    ],
                    'body' => array_merge($data, [
                        'image' => $imageResource
                    ]),
                ]);
            } else {
                // Cas sans image
                $response = $this->client->request('PUT', 'http://localhost:8989/authors/id/withoutImage/' . $id, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $request->getSession()->get('comics_collection_jwt_token'),
                    ],
                    'json' => $data,
                ]);
            }

            // Vérification du statut
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                $this->addFlash('success', sprintf('Auteur "%s" mis à jour avec succès.', $fullname));
            } else {
                $errorContent = $response->getContent(false);
                $this->addFlash('danger', 'Erreur API Node: ' . $errorContent);
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Impossible de contacter l’API Node: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_authors');
    }

    /**
     * Supprime un auteur
     *
     * @param Request $request La requête HTTP
     * @param int $id L'identifiant de l'auteur à supprimer
     * @return Response La réponse HTTP
     */
    #[Route('/admin/les-auteurs/supprimer/{id}', name: 'admin_authors_delete', methods: ['POST'])]
    public function deleteAuthor(Request $request, int $id): Response
    {
        $timeLeft = $this->checkTokenAndRedirectIfNeeded($request);
        if (is_null($timeLeft)) return new Response();

        // Vérification du token CSRF
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_author_'.$id, $submittedToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $response = $this->client->request('DELETE', 'http://localhost:8989/authors/' . $id, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $request->getSession()->get('comics_collection_jwt_token'),
                ],
            ]);

            // Vérification du statut
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                $this->addFlash('success', 'Auteur supprimé avec succès.');
            } else {
                $errorContent = $response->getContent(false);
                $this->addFlash('danger', 'Erreur API Node: ' . $errorContent);
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Impossible de contacter l’API Node: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_authors');
    }
}
