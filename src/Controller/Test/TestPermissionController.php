<?php

namespace App\Controller\Test;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Exemple minimal montrant comment gater une route avec une permission
 * granulaire — voir .doc/permissions.md. "test.view" doit exister dans le
 * catalogue (table permission) et être accordée à l'utilisateur pour que
 * cette route réponde 200 ; sinon 403. À supprimer une fois son rôle
 * pédagogique rempli, ce n'est pas un controller métier.
 */
class TestPermissionController extends AbstractController
{
    // "tyrolium.test.view" -> PERMS_TYROLIUM_TEST_VIEW, voir Permission::toRole().
    #[IsGranted('PERMS_TYROLIUM_TEST_VIEW')]
    #[Route('/test/permission/get-view', name: 'test_permission_get_view', methods: ['GET'])]
    public function getView(): JsonResponse
    {
        return apiSuccess(message: 'permission fonctionnel');
    }
}
