<?php

namespace App\Controller\Tyrolium;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class TyroliumPermissionController extends AbstractController
{
    #[Route('/tyrolium/tyrolium/permission', name: 'app_tyrolium_tyrolium_permission')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Tyrolium/TyroliumPermissionController.php',
        ]);
    }
}
