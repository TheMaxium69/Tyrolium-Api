<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    private const RATE_LIMIT_ATTRIBUTE = '_api_rate_limit';

    public function __construct(
        #[Target('api_global')]
        private readonly RateLimiterFactoryInterface $apiGlobalLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Priority 40 runs before the router (32): throttled requests never reach routing.
            KernelEvents::REQUEST => ['onKernelRequest', 40],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Keyed by IP for now. Switch to the authenticated client/token identifier once an
        // authenticator is wired up, so users behind a shared IP (NAT, corporate proxy)
        // don't share one quota.
        $limiter = $this->apiGlobalLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $limiter->consume();

        $request->attributes->set(self::RATE_LIMIT_ATTRIBUTE, $limit);

        if (!$limit->isAccepted()) {
            $response = new JsonResponse(['error' => 'Too Many Requests'], Response::HTTP_TOO_MANY_REQUESTS);
            $this->addRateLimitHeaders($response, $limit);
            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $limit = $event->getRequest()->attributes->get(self::RATE_LIMIT_ATTRIBUTE);
        if ($limit instanceof RateLimit) {
            $this->addRateLimitHeaders($event->getResponse(), $limit);
        }
    }

    private function addRateLimitHeaders(Response $response, RateLimit $limit): void
    {
        $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
        $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());

        if (!$limit->isAccepted()) {
            $response->headers->set('Retry-After', (string) $limit->getRetryAfter()->getTimestamp());
        }
    }
}
