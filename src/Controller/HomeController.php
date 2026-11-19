<?php

namespace App\Controller;

use App\Archive\HttpUploadLimits;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /**
     * Renders the React application shell.
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function __invoke(HttpUploadLimits $uploadLimits): Response
    {
        $response = $this->render('home/index.html.twig', ['maxFiles' => $uploadLimits->maxFiles()]);
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
