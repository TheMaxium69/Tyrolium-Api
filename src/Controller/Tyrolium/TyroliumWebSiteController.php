<?php

namespace App\Controller\Tyrolium;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TyroliumWebSiteController extends AbstractController
{
    #[Route('/tyrolium/tyrolium/web/site', name: 'app_tyrolium_tyrolium_web_site')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Tyrolium/TyroliumWebSiteController.php',
        ]);
    }
}
