<?php

namespace App\Controller\Test;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Exemple pédagogique des permissions "parapluie" — voir .doc/permissions.md
 * section 3. 4 routes CRUD bidon, chacune gardée par sa propre permission
 * fine (test.example.{view,create,update,delete}). En liant
 * "test.example.manage" comme parapluie des 4 (voir tuto dans la doc), un
 * utilisateur qui n'a QUE "manage" passe quand même les 4 gates ci-dessous —
 * à tester toi-même via Postman une fois les permissions créées/liées/
 * accordées (section 2.9 de .doc/permissions.md). Pas un controller métier,
 * ne pas prendre pour modèle de convention de nommage.
 */
class TestExampleController extends AbstractController
{
    #[IsGranted('PERMS_TEST_EXAMPLE_VIEW')]
    #[Route('/test/example/get-view', name: 'test_example_get_view', methods: ['GET'])]
    public function getView(): JsonResponse
    {
        return apiSuccess(message: 'view fonctionnel');
    }

    #[IsGranted('PERMS_TEST_EXAMPLE_CREATE')]
    #[Route('/test/example/post-create', name: 'test_example_post_create', methods: ['POST'])]
    public function postCreate(): JsonResponse
    {
        return apiSuccess(message: 'create fonctionnel');
    }

    #[IsGranted('PERMS_TEST_EXAMPLE_UPDATE')]
    #[Route('/test/example/put-update', name: 'test_example_put_update', methods: ['PUT'])]
    public function putUpdate(): JsonResponse
    {
        return apiSuccess(message: 'update fonctionnel');
    }

    #[IsGranted('PERMS_TEST_EXAMPLE_DELETE')]
    #[Route('/test/example/delete-remove', name: 'test_example_delete_remove', methods: ['DELETE'])]
    public function deleteRemove(): JsonResponse
    {
        return apiSuccess(message: 'delete fonctionnel');
    }
}
