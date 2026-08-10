<?php

namespace App\Controller\Tyrolium;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TyroliumPrestationController extends AbstractController
{
    #[Route('/tyrolium/tyrolium/prestation', name: 'app_tyrolium_tyrolium_prestation')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Tyrolium/TyroliumPrestationController.php',
        ]);
    }
}
