<?php

namespace App\Controller\SolidServ;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SolidServStatusController extends AbstractController
{
    #[Route('/filiales/solid/serv/solid/serv/status', name: 'app_filiales_solid_serv_solid_serv_status')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Filiales/SolidServ/SolidServStatusController.php',
        ]);
    }
}
