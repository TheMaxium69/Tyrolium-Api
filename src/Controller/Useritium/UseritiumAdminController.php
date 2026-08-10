<?php

namespace App\Controller\Useritium;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class UseritiumAdminController extends AbstractController
{
    #[Route('/useritium/useritium/admin', name: 'app_useritium_useritium_admin')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Useritium/UseritiumAdminController.php',
        ]);
    }
}
