<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /**
     * Renders the React application shell.
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function __invoke(): Response
    {
        $response = $this->render('home/index.html.twig');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
