<?php

namespace App\Controller\Tyrolium;

use App\Entity\SupportTicket;
use App\Entity\TicketMessage;
use App\Entity\User;
use App\Repository\SupportTicketRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_USER')]
final class TyroliumSupportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SupportTicketRepository $ticketRepository,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * GET /tyrolium/support/get-all-ticket
     * Lister les tickets (ses propres tickets pour un client, tous les tickets pour le staff/admin)
     */
    #[Route('/tyrolium/support/get-all-ticket', name: 'tyrolium_support_get_all_ticket', methods: ['GET'])]
    public function getAllTicket(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $isStaff = in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_STAFF', $user->getRoles(), true);

        if (!$isStaff) {
            $tickets = $this->ticketRepository->findByUser($user);
        } else {
            $status = $request->query->get('status');
            $criteria = [];
            if (null !== $status && '' !== $status) {
                $criteria['status'] = $status;
            }
            $tickets = $this->ticketRepository->findBy($criteria, ['updatedAt' => 'DESC']);
        }

        return $this->json([
            'success' => true,
            'code' => 200,
            'message' => 'Liste des tickets de support récupérée avec succès.',
            'data' => $tickets,
            'errors' => null,
            'meta' => [
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'total' => count($tickets),
            ],
        ], 200, [], ['groups' => ['ticket:read', 'user:read']]);
    }

    /**
     * GET /tyrolium/support/get-one-ticket
     * Consulter le détail d'un ticket et son fil de discussion
     */
    #[Route('/tyrolium/support/get-one-ticket', name: 'tyrolium_support_get_one_ticket', methods: ['GET'])]
    public function getOneTicket(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $id = $request->query->get('id');
        if (null === $id) {
            return $this->json([
                'success' => false,
                'code' => 400,
                'message' => 'L\'ID du ticket est obligatoire.',
                'data' => null,
            ], 400);
        }

        $ticket = $this->ticketRepository->find((int) $id);
        if (null === $ticket) {
            return $this->json([
                'success' => false,
                'code' => 404,
                'message' => 'Le ticket demandé n\'existe pas.',
                'data' => null,
            ], 404);
        }

        $isStaff = in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_STAFF', $user->getRoles(), true);
        if (!$isStaff && $ticket->getUser()?->getId() !== $user->getId()) {
            return $this->json([
                'success' => false,
                'code' => 403,
                'message' => 'Vous n\'avez pas la permission d\'accéder à ce ticket.',
                'data' => null,
            ], 403);
        }

        return $this->json([
            'success' => true,
            'code' => 200,
            'message' => 'Détails du ticket récupérés avec succès.',
            'data' => $ticket,
            'errors' => null,
        ], 200, [], ['groups' => ['ticket:read', 'ticket:details', 'ticket_message:read', 'user:read']]);
    }

    /**
     * POST /tyrolium/support/post-create-ticket
     * Ouvrir un nouveau ticket de support
     */
    #[Route('/tyrolium/support/post-create-ticket', name: 'tyrolium_support_post_create_ticket', methods: ['POST'])]
    public function postCreateTicket(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $subject = trim((string) ($payload['subject'] ?? ''));
        $content = trim((string) ($payload['message'] ?? $payload['content'] ?? ''));
        $category = (string) ($payload['category'] ?? 'GENERAL');
        $priority = (string) ($payload['priority'] ?? SupportTicket::PRIORITY_NORMAL);

        if ('' === $subject || '' === $content) {
            return $this->json([
                'success' => false,
                'code' => 422,
                'message' => 'Le sujet et le message initial sont obligatoires.',
                'data' => null,
                'errors' => [
                    ['field' => 'subject', 'message' => 'Sujet obligatoire'],
                    ['field' => 'message', 'message' => 'Message initial obligatoire'],
                ],
            ], 422);
        }

        $ticket = new SupportTicket();
        $ticket->setSubject($subject);
        $ticket->setCategory($category);
        $ticket->setPriority($priority);
        $ticket->setStatus(SupportTicket::STATUS_OPEN);
        $ticket->setUser($user);

        $initialMessage = new TicketMessage();
        $initialMessage->setAuthor($user);
        $initialMessage->setContent($content);
        $initialMessage->setIsInternalNote(false);

        $ticket->addMessage($initialMessage);

        $errors = $this->validator->validate($ticket);
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
                'message' => 'Erreur de validation lors de la création du ticket.',
                'data' => null,
                'errors' => $formattedErrors,
            ], 422);
        }

        $this->entityManager->persist($ticket);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'code' => 201,
            'message' => 'Votre ticket de support a été ouvert avec succès.',
            'data' => $ticket,
            'errors' => null,
        ], 201, [], ['groups' => ['ticket:read', 'ticket:details', 'ticket_message:read', 'user:read']]);
    }

    /**
     * POST /tyrolium/support/post-add-message
     * Ajouter une réponse dans un fil de discussion
     */
    #[Route('/tyrolium/support/post-add-message', name: 'tyrolium_support_post_add_message', methods: ['POST'])]
    public function postAddMessage(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $ticketId = $payload['ticketId'] ?? null;
        $content = trim((string) ($payload['content'] ?? ''));
        $isInternalNote = (bool) ($payload['isInternalNote'] ?? false);

        if (null === $ticketId || '' === $content) {
            return $this->json([
                'success' => false,
                'code' => 400,
                'message' => 'L\'ID du ticket (ticketId) et le contenu du message sont obligatoires.',
                'data' => null,
            ], 400);
        }

        $ticket = $this->ticketRepository->find((int) $ticketId);
        if (null === $ticket) {
            return $this->json([
                'success' => false,
                'code' => 404,
                'message' => 'Le ticket demandé n\'existe pas.',
                'data' => null,
            ], 404);
        }

        $isStaff = in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_STAFF', $user->getRoles(), true);
        if (!$isStaff && $ticket->getUser()?->getId() !== $user->getId()) {
            return $this->json([
                'success' => false,
                'code' => 403,
                'message' => 'Vous n\'avez pas la permission de répondre à ce ticket.',
                'data' => null,
            ], 403);
        }

        $message = new TicketMessage();
        $message->setAuthor($user);
        $message->setContent($content);
        $message->setIsInternalNote($isStaff ? $isInternalNote : false);

        $ticket->addMessage($message);

        // Mise à jour automatique du statut selon l'auteur
        if ($isStaff && !$isInternalNote) {
            $ticket->setStatus(SupportTicket::STATUS_WAITING_CUSTOMER);
        } elseif (!$isStaff) {
            $ticket->setStatus(SupportTicket::STATUS_IN_PROGRESS);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'code' => 201,
            'message' => 'Message ajouté au ticket avec succès.',
            'data' => $message,
            'errors' => null,
        ], 201, [], ['groups' => ['ticket_message:read', 'user:read']]);
    }

    /**
     * PUT /tyrolium/support/put-update-status
     * Changer le statut d'un ticket (Ouvert, En cours, Résolu...)
     */
    #[Route('/tyrolium/support/put-update-status', name: 'tyrolium_support_put_update_status', methods: ['PUT'])]
    public function putUpdateStatus(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $ticketId = $payload['ticketId'] ?? null;
        $newStatus = $payload['status'] ?? null;

        if (null === $ticketId || null === $newStatus) {
            return $this->json([
                'success' => false,
                'code' => 400,
                'message' => 'L\'ID du ticket (ticketId) et le statut (status) sont obligatoires.',
                'data' => null,
            ], 400);
        }

        $ticket = $this->ticketRepository->find((int) $ticketId);
        if (null === $ticket) {
            return $this->json([
                'success' => false,
                'code' => 404,
                'message' => 'Le ticket n\'existe pas.',
                'data' => null,
            ], 404);
        }

        $isStaff = in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_STAFF', $user->getRoles(), true);
        if (!$isStaff && $ticket->getUser()?->getId() !== $user->getId()) {
            return $this->json([
                'success' => false,
                'code' => 403,
                'message' => 'Vous n\'avez pas la permission de modifier ce ticket.',
                'data' => null,
            ], 403);
        }

        $ticket->setStatus((string) $newStatus);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'code' => 200,
            'message' => sprintf('Le statut du ticket #%d a été mis à jour vers \'%s\'.', $ticket->getId(), $ticket->getStatus()),
            'data' => $ticket,
            'errors' => null,
        ], 200, [], ['groups' => ['ticket:read', 'user:read']]);
    }

    /**
     * PUT /tyrolium/support/put-assign-ticket
     * Assigner un ticket à un collaborateur (Réservé Staff/Admin)
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/tyrolium/support/put-assign-ticket', name: 'tyrolium_support_put_assign_ticket', methods: ['PUT'])]
    public function putAssignTicket(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $ticketId = $payload['ticketId'] ?? null;
        $staffId = $payload['staffId'] ?? null;

        if (null === $ticketId) {
            return $this->json([
                'success' => false,
                'code' => 400,
                'message' => 'L\'ID du ticket (ticketId) est obligatoire.',
                'data' => null,
            ], 400);
        }

        $ticket = $this->ticketRepository->find((int) $ticketId);
        if (null === $ticket) {
            return $this->json([
                'success' => false,
                'code' => 404,
                'message' => 'Le ticket n\'existe pas.',
                'data' => null,
            ], 404);
        }

        $staff = null;
        if (null !== $staffId) {
            $staff = $this->userRepository->find((int) $staffId);
            if (null === $staff) {
                return $this->json([
                    'success' => false,
                    'code' => 404,
                    'message' => 'Le collaborateur spécifié est introuvable.',
                    'data' => null,
                ], 404);
            }
        }

        $ticket->setAssignedTo($staff);
        if (null !== $staff && $ticket->getStatus() === SupportTicket::STATUS_OPEN) {
            $ticket->setStatus(SupportTicket::STATUS_IN_PROGRESS);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'code' => 200,
            'message' => null !== $staff 
                ? sprintf('Le ticket #%d a été assigné à \'%s\'.', $ticket->getId(), $staff->getUsername())
                : sprintf('Le ticket #%d a été désassigné.', $ticket->getId()),
            'data' => $ticket,
            'errors' => null,
        ], 200, [], ['groups' => ['ticket:read', 'user:read']]);
    }
}
