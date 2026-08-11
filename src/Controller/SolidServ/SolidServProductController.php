<?php

namespace App\Controller\SolidServ;

use App\Entity\SolidServProduct;
use App\Repository\SolidServProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SolidServProductController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SolidServProductRepository $productRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * GET /solidserv/product/get-all-product
     * Lister les produits du catalogue
     */
    #[Route('/solidserv/product/get-all-product', name: 'solidserv_product_get_all_product', methods: ['GET'])]
    public function getAllProduct(Request $request): JsonResponse
    {
        $includeUnlisted = $request->query->getBoolean('includeUnlisted', false);

        $products = $includeUnlisted
            ? $this->productRepository->findAll()
            : $this->productRepository->findPublicCatalog();

        return $this->json([
            'success' => true,
            'code' => 200,
            'message' => 'Catalogue des produits SolidServ récupéré.',
            'data' => $products,
            'errors' => null,
            'meta' => [
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'total' => count($products),
            ],
        ], 200, [], ['groups' => ['product:read']]);
    }

    /**
     * GET /solidserv/product/get-one-product
     * Récupérer un produit par son ID ou son Slug
     */
    #[Route('/solidserv/product/get-one-product', name: 'solidserv_product_get_one_product', methods: ['GET'])]
    public function getOneProduct(Request $request): JsonResponse
    {
        $id = $request->query->get('id');
        $slug = $request->query->get('slug');

        $product = null;
        if (null !== $id) {
            $product = $this->productRepository->find((int) $id);
        } elseif (null !== $slug) {
            $product = $this->productRepository->findOneBy(['slug' => $slug]);
        }

        if (null === $product) {
            return $this->json([
                'success' => false,
                'code' => 404,
                'message' => 'Le produit demandé n\'existe pas.',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        return $this->json([
            'success' => true,
            'code' => 200,
            'message' => 'Détails du produit récupérés.',
            'data' => $product,
            'errors' => null,
        ], 200, [], ['groups' => ['product:read']]);
    }

    /**
     * POST /solidserv/product/post-create-product
     * Créer un produit public ou sur-mesure (Réservé administrateurs/collaborateurs)
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/solidserv/product/post-create-product', name: 'solidserv_product_post_create_product', methods: ['POST'])]
    public function postCreateProduct(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $slugger = new AsciiSlugger();
        $name = $payload['name'] ?? '';
        $slug = isset($payload['slug']) && '' !== $payload['slug']
            ? (string) $payload['slug']
            : strtolower((string) $slugger->slug($name));

        $product = new SolidServProduct();
        $product->setName($name);
        $product->setSlug($slug);
        $product->setDescription($payload['description'] ?? null);
        $product->setPriceMonthly((string) ($payload['priceMonthly'] ?? '0.00'));
        $product->setIsPublic((bool) ($payload['isPublic'] ?? true));
        $product->setIsListed((bool) ($payload['isListed'] ?? true));
        $product->setIsActive((bool) ($payload['isActive'] ?? true));
        $product->setStock(isset($payload['stock']) ? (int) $payload['stock'] : null);
        $product->setIsOutOfStock((bool) ($payload['isOutOfStock'] ?? false));
        $product->setSpecs(isset($payload['specs']) && is_array($payload['specs']) ? $payload['specs'] : []);

        $errors = $this->validator->validate($product);
        if (count($errors) > 0) {
            $formattedErrors = [];
            foreach ($errors as $error) {
                $formattedErrors[] = [
                    'field' => $error->getPropertyPath(),
                    'message' => $error->getMessage(),
                ];
            }

            return $this->json([
                'success' => false,
                'code' => 422,
                'message' => 'Erreur de validation lors de la création du produit.',
                'data' => null,
                'errors' => $formattedErrors,
            ], 422);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'code' => 201,
            'message' => 'Produit SolidServ créé avec succès.',
            'data' => $product,
            'errors' => null,
        ], 201, [], ['groups' => ['product:read']]);
    }

    /**
     * PUT /solidserv/product/put-update-product
     * Mettre à jour un produit existant (Réservé administrateurs)
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/solidserv/product/put-update-product', name: 'solidserv_product_put_update_product', methods: ['PUT'])]
    public function putUpdateProduct(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $id = $payload['id'] ?? $request->query->get('id');

        if (null === $id) {
            return $this->json([
                'success' => false,
                'code' => 400,
                'message' => 'L\'ID du produit à modifier est obligatoire.',
                'data' => null,
            ], 400);
        }

        $product = $this->productRepository->find((int) $id);
        if (null === $product) {
            return $this->json([
                'success' => false,
                'code' => 404,
                'message' => 'Le produit à modifier n\'existe pas.',
                'data' => null,
            ], 404);
        }

        if (isset($payload['name'])) {
            $product->setName($payload['name']);
        }
        if (isset($payload['description'])) {
            $product->setDescription($payload['description']);
        }
        if (isset($payload['priceMonthly'])) {
            $product->setPriceMonthly((string) $payload['priceMonthly']);
        }
        if (isset($payload['isPublic'])) {
            $product->setIsPublic((bool) $payload['isPublic']);
        }
        if (isset($payload['isListed'])) {
            $product->setIsListed((bool) $payload['isListed']);
        }
        if (isset($payload['isActive'])) {
            $product->setIsActive((bool) $payload['isActive']);
        }
        if (array_key_exists('stock', $payload)) {
            $product->setStock(null !== $payload['stock'] ? (int) $payload['stock'] : null);
        }
        if (isset($payload['isOutOfStock'])) {
            $product->setIsOutOfStock((bool) $payload['isOutOfStock']);
        }
        if (isset($payload['specs']) && is_array($payload['specs'])) {
            $product->setSpecs($payload['specs']);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'code' => 200,
            'message' => sprintf('Le produit \'%s\' a été mis à jour avec succès.', $product->getName()),
            'data' => $product,
            'errors' => null,
        ], 200, [], ['groups' => ['product:read']]);
    }

    /**
     * DELETE /solidserv/product/delete-product
     * Supprimer un produit du catalogue (Réservé administrateurs)
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/solidserv/product/delete-product', name: 'solidserv_product_delete_product', methods: ['DELETE'])]
    public function deleteProduct(Request $request): JsonResponse
    {
        $id = $request->query->get('id');

        if (null === $id) {
            return $this->json([
                'success' => false,
                'code' => 400,
                'message' => 'L\'ID du produit à supprimer est obligatoire.',
                'data' => null,
            ], 400);
        }

        $product = $this->productRepository->find((int) $id);
        if (null === $product) {
            return $this->json([
                'success' => false,
                'code' => 404,
                'message' => 'Le produit à supprimer n\'existe pas.',
                'data' => null,
            ], 404);
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'code' => 200,
            'message' => sprintf('Le produit ID %d a été supprimé avec succès.', (int) $id),
            'data' => ['id' => (int) $id],
            'errors' => null,
        ]);
    }
}
