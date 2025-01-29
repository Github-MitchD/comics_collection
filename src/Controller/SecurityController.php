<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Contrôleur du système de sécurité (connexion/déconnexion)
 */
final class SecurityController extends AbstractController
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
     * Affiche le formulaire de connexion
     *
     * @param AuthenticationUtils $authenticationUtils Les utilitaires d'authentification
     * @return Response La réponse HTTP
     */
    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Récupére les erreurs d'authentification s'il y en a
        $error = $authenticationUtils->getLastAuthenticationError();

        // Dernier email saisi par l'utilisateur
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'error' => $error,
            'last_username' => $lastUsername,
        ]);
    }

    /**
     * Vérifie les informations de connexion
     *
     * @param Request $request La requête HTTP
     * @return Response La réponse HTTP
     */
    #[Route('/login_check', name: 'app_login_check', methods: ['POST'])]
    public function loginCheck(Request $request): Response
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        
        $response = $this->client->request('POST', self::COMICS_API_URL.'/login', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 5,
            'verify_peer' => true, // Vérifie le certificat 
        ]);

        // Vérifie si on a une réponse de type HTTP ou pas
        if ($response->getStatusCode() === 0) {
            // Le code 0 indique souvent que la requête n’a pas abouti (timeout, etc.)
            $this->addFlash('danger', 'Impossible de contacter le serveur d’API. Réessayez plus tard.');
            return $this->redirectToRoute('app_login');
        }
        
        $statusCode = $response->getStatusCode();

        // Gère les différents cas de figure
        if ($statusCode === 200) {
            // OK, l’API a validé l’authentification
            // Récupère le corps de la réponse (JSON) sous forme de tableau
            $data = $response->toArray();
            // Equivalent à json_decode($response->getContent(), true),
            // mais avec quelques vérifications en plus
            if (!isset($data['token'])) {
                $this->addFlash('error', 'Réponse invalide : pas de token renvoyé.');
                return $this->redirectToRoute('app_login');
            }
            $token = $data['token'];
            $message   = $data['message']   ?? 'Connexion réussie.'; // défaut si pas de message
            $expiresAtString = $data['expiresAt'];
            
            $request->getSession()->set('comics_collection_jwt_token', $token);

            // Stocke la chaine ISO8601 fournie par l'API en session (exemple: 2025-01-01T12:34:56.789Z)
            if($expiresAtString){
                $request->getSession()->set('comics_collection_jwt_expiresAt', $expiresAtString);
            }
            
            return $this->redirectToRoute('admin_dashboard');

        } elseif ($statusCode === 401) {
            // Identifiants invalides
            $this->addFlash('error', 'Email ou mot de passe incorrect.');
            return $this->redirectToRoute('app_login');

        } elseif ($statusCode === 500) {
            // Erreur interne côté API
            $this->addFlash('error', 'Erreur serveur (500) côté API.');
            return $this->redirectToRoute('app_login');

        } else {
            // Autre code HTTP non géré explicitement (403, 404, 422, etc.)
            $this->addFlash('error', 'Erreur inattendue : code ' . $statusCode);
            return $this->redirectToRoute('app_login');
        }
        return $this->redirectToRoute('app_login');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // TODO: implémenter la déconnexion
    }
}
