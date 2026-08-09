<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;

if (!function_exists('apiResponse')) {
    /**
     * Enveloppe universelle de réponse API Tyrolium.
     *
     * @param list<array<string, string|Stringable>>|null $errors
     * @param array<string, mixed>|null $meta
     */
    function apiResponse(
        bool $success,
        int $code,
        string $message,
        mixed $data = null,
        ?array $errors = null,
        ?array $meta = null,
    ): JsonResponse {

        // Création d'une requestId pour les logs
        static $requestId = null;
        if (null === $requestId) {
            $requestId = $_SERVER['HTTP_X_REQUEST_ID']
                ?? $_SERVER['UNIQUE_ID']
                ?? 'req_'.bin2hex(random_bytes(6));
        }

        // Affiché un ms
        $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $executionTimeMs = $startTime ? round((microtime(true) - $startTime) * 1000, 2) : 0.0;

        // gestion de la version
        $environment = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'unknownEnv';
        $apiVersion = $_ENV['API_VERSION'] ?? $_SERVER['API_VERSION'] ?? 'unknownVersion';

        // Les meta qui son toujours là
        $defaultMeta = [
            'apiVersion' => $apiVersion ."-". $environment,
            'requestId' => $requestId,
            // getClientIp() ignores X-Forwarded-For unless framework.trusted_proxies is
            // configured for this exact connecting IP — safe by default, no spoofing risk.
            'clientIp' => Request::createFromGlobals()->getClientIp() ?? '127.0.0.1',
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'executionTimeMs' => $executionTimeMs,
        ];

        // Ajouter les meta ajouter manuellement au meta toujours présent
        $finalMeta = null !== $meta ? array_merge($defaultMeta, $meta) : $defaultMeta;

        return new JsonResponse([
            'success' => $success,
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
            'meta' => $finalMeta,
        ], $code);
    }
}

if (!function_exists('apiSuccess')) {
    /**
     * Raccourci pour les réponses d'API en succès (200, 201...).
     *
     * @param array<string, mixed>|null $meta
     */
    function apiSuccess(
        mixed $data = null,
        string $message = 'Succès',
        int $code = 200,
        ?array $meta = null,
    ): JsonResponse {
        return apiResponse(true, $code, $message, $data, null, $meta);
    }
}

if (!function_exists('apiError')) {
    /**
     * Raccourci pour les réponses d'API en erreur (400, 404, 422, 500...).
     *
     * @param list<array<string, string|Stringable>>|null $errors
     * @param array<string, mixed>|null $meta
     */
    function apiError(
        string $message = 'Erreur',
        int $code = 400,
        ?array $errors = null,
        ?array $meta = null,
    ): JsonResponse {
        return apiResponse(false, $code, $message, null, $errors, $meta);
    }
}

if (!function_exists('apiValidationError')) {
    /**
     * Transforme le résultat d'un ValidatorInterface::validate() en réponse d'erreur API
     * (via api_error() ci-dessus) — ne valide rien elle-même, juste met en forme un
     * ConstraintViolationList déjà produit par le Validator.
     */
    function apiValidationError(
        ConstraintViolationListInterface $violations,
        string $message = 'Données invalides.',
        int $code = 422,
    ): JsonResponse {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return apiError($message, $code, $errors);
    }
}
