<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class Useritium/UseritiumDriveController extends AbstractController
{
    #[Route('/useritium/useritium/drive', name: 'app_useritium_useritium_drive')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Useritium/UseritiumDriveController.php',
        ]);
    }
}
