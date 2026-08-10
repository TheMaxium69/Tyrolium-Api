<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class Useritium/UseritiumDashboardController extends AbstractController
{
    #[Route('/useritium/useritium/dashboard', name: 'app_useritium_useritium_dashboard')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Useritium/UseritiumDashboardController.php',
        ]);
    }
}
