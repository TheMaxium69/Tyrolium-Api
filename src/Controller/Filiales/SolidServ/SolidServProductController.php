<?php

namespace App\Controller\Filiales\SolidServ;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SolidServProductController extends AbstractController
{
    #[Route('/filiales/solid/serv/solid/serv/product', name: 'app_filiales_solid_serv_solid_serv_product')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Filiales/SolidServ/SolidServProductController.php',
        ]);
    }
}
