<?php

namespace App\Controller\Filiales\SolidServ;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SolidServProxmoxController extends AbstractController
{
    #[Route('/filiales/solid/serv/solid/serv/proxmox', name: 'app_filiales_solid_serv_solid_serv_proxmox')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/Filiales/SolidServ/SolidServProxmoxController.php',
        ]);
    }
}
