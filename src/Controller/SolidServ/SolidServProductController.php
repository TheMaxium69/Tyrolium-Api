<?php

namespace App\Controller\SolidServ;

use App\Entity\SolidServProduct;
use App\Entity\User;
use App\Enum\OffreVisibility;
use App\Helper\Pagination;
use App\Repository\OffreRepository;
use App\Repository\SolidServProductRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Catalogue des produits SolidServ — voir SolidServProduct.php.
 * Permissions : solidserv.product.{view,create,update,delete} + solidserv.product.manage (parapluie).
 */
class SolidServProductController extends AbstractController
{
    private const INT_FIELDS = [
        'priceMonthly' => 'setPriceMonthly',
        'priceYearly' => 'setPriceYearly',
        'cpuCores' => 'setCpuCores',
        'ramMb' => 'setRamMb',
        'storageGb' => 'setStorageGb',
        'stock' => 'setStock',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SolidServProductRepository $productRepository,
        private readonly OffreRepository $offreRepository,
        private readonly ValidatorInterface $validator,
        private readonly NormalizerInterface $serializer,
    ) {
    }

    #[IsGranted('PERMS_SOLIDSERV_PRODUCT_VIEW')]
    #[Route('/solidserv/product/get-all-product', name: 'solidserv_product_get_all_product', methods: ['GET'])]
    public function getAllProduct(Request $request): JsonResponse
    {
        $filters = [];

        if (null !== $request->query->get('visibility')) {
            $visibility = OffreVisibility::tryFrom((string) $request->query->get('visibility'));
            if (null === $visibility) {
                return apiError("visibility doit être 'listed' ou 'custom'.", 400);
            }
            $filters['visibility'] = $visibility;
        }
        foreach (['isActive', 'inStock'] as $key) {
            if (null !== $request->query->get($key)) {
                $value = filter_var($request->query->get($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if (null === $value) {
                    return apiError($key.' doit être true ou false.', 400);
                }
                $filters[$key] = $value;
            }
        }
        $search = $request->query->get('search');
        if (is_string($search) && '' !== trim($search)) {
            $filters['search'] = mb_substr(trim($search), 0, 100);
        }

        $result = Pagination::fromQueryBuilder($this->productRepository->filteredQueryBuilder($filters), $request);

        return apiSuccess(
            data: array_map(fn (SolidServProduct $product): array => $this->normalizeProduct($product), $result['items']),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('PERMS_SOLIDSERV_PRODUCT_VIEW')]
    #[Route('/solidserv/product/get-one-product/{id}', name: 'solidserv_product_get_one_product', methods: ['GET'])]
    public function getOneProduct(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (null === $product) {
            return apiError('Produit introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeProduct($product));
    }

    #[IsGranted('PERMS_SOLIDSERV_PRODUCT_CREATE')]
    #[Route('/solidserv/product/post-create-product', name: 'solidserv_product_post_create_product', methods: ['POST'])]
    public function postCreateProduct(Request $request, #[CurrentUser] User $createdBy): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $product = new SolidServProduct();
        $product->setCreatedBy($createdBy);

        if ($error = $this->applyPayload($product, $payload)) {
            return $error;
        }

        return $this->saveProduct($product, 'Produit créé.', 201);
    }

    #[IsGranted('PERMS_SOLIDSERV_PRODUCT_UPDATE')]
    #[Route('/solidserv/product/put-update-product/{id}', name: 'solidserv_product_put_update_product', methods: ['PUT'])]
    public function putUpdateProduct(int $id, Request $request): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (null === $product) {
            return apiError('Produit introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if ($error = $this->applyPayload($product, $payload)) {
            return $error;
        }

        return $this->saveProduct($product, 'Produit mis à jour.', 200);
    }

    #[IsGranted('PERMS_SOLIDSERV_PRODUCT_DELETE')]
    #[Route('/solidserv/product/delete-product/{id}', name: 'solidserv_product_delete_product', methods: ['DELETE'])]
    public function deleteProduct(int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);
        if (null === $product) {
            return apiError('Produit introuvable.', 404);
        }

        $this->entityManager->remove($product);

        try {
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException) {
            return apiError('Impossible de supprimer ce produit : des serveurs l\'utilisent encore. Désactive-le (isActive: false) à la place.', 409);
        }

        return apiSuccess(message: 'Produit supprimé.');
    }

    /**
     * Applique uniquement les clés présentes dans le payload (création et modification partagent ce code).
     *
     * @param array<string, mixed> $payload
     */
    private function applyPayload(SolidServProduct $product, array $payload): ?JsonResponse
    {
        foreach (['tagName' => 'setTagName', 'displayName' => 'setDisplayName'] as $key => $setter) {
            if (array_key_exists($key, $payload)) {
                if (!is_string($payload[$key])) {
                    return apiError($key.' doit être une chaîne.', 400);
                }
                $product->$setter($payload[$key]);
            }
        }

        if (array_key_exists('description', $payload)) {
            if (null !== $payload['description'] && !is_string($payload['description'])) {
                return apiError('description doit être une chaîne ou null.', 400);
            }
            $product->setDescription($payload['description']);
        }

        if (isset($payload['visibility'])) {
            $visibility = OffreVisibility::tryFrom((string) $payload['visibility']);
            if (null === $visibility) {
                return apiError("visibility doit être 'listed' ou 'custom'.", 400);
            }
            $product->setVisibility($visibility);
        }

        foreach (self::INT_FIELDS as $key => $setter) {
            if (array_key_exists($key, $payload)) {
                if (null !== $payload[$key] && !is_int($payload[$key])) {
                    return apiError($key.' doit être un entier ou null.', 400);
                }
                $product->$setter($payload[$key]);
            }
        }

        if (isset($payload['position'])) {
            if (!is_int($payload['position'])) {
                return apiError('position doit être un entier.', 400);
            }
            $product->setPosition($payload['position']);
        }
        if (isset($payload['isActive'])) {
            if (!is_bool($payload['isActive'])) {
                return apiError('isActive doit être un booléen.', 400);
            }
            $product->setIsActive($payload['isActive']);
        }

        if (array_key_exists('offreId', $payload)) {
            if (null === $payload['offreId']) {
                $product->setOffre(null);
            } else {
                if (!is_int($payload['offreId'])) {
                    return apiError('offreId doit être un entier ou null.', 400);
                }
                $offre = $this->offreRepository->find($payload['offreId']);
                if (null === $offre) {
                    return apiError('Offre introuvable.', 404);
                }
                $product->setOffre($offre);
            }
        }

        return null;
    }

    private function saveProduct(SolidServProduct $product, string $message, int $code): JsonResponse
    {
        $violations = $this->validator->validate($product);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Produit invalide.');
        }

        try {
            $this->entityManager->persist($product);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Un produit avec ce tagName existe déjà.', 409);
        }

        return apiSuccess(data: $this->normalizeProduct($product), message: $message, code: $code);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeProduct(SolidServProduct $product): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($product, context: [
            'groups' => ['solidserv_product:read', 'offre:identifier', 'user:identifier'],
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        ]);

        return $data;
    }
}
