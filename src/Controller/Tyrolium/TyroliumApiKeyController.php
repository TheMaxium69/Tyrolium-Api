<?php

namespace App\Controller\Tyrolium;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TyroliumApiKeyController extends AbstractController
{
    #[Route('/tyrolium/tyrolium/api/key', name: 'app_tyrolium_tyrolium_api_key')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Tyrolium/TyroliumApiKeyController.php',
        ]);
    }
}
