<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskColumn;
use App\Entity\TaskLabel;
use App\Entity\TaskProject;
use App\Repository\TaskRepository;

/**
 * Sortie JSON des tâches, colonnes et projets (liste blanche de champs écrite à la
 * main, comme le reste du dashboard public). Partagée par UseritiumTaskController et
 * UseritiumTaskProjectController pour que les deux parties du produit parlent la même langue.
 */
final class TaskPresenter
{
    public function __construct(private readonly TaskRepository $taskRepository)
    {
    }

    /**
     * Une tâche (avec ses sous-tâches si elle est racine et $withSubtasks).
     *
     * @return array<string, mixed>
     */
    public function task(Task $task, bool $withSubtasks = true): array
    {
        $subtasks = $task->isSubtask() ? [] : ($this->taskRepository->loadRelations([$task])[$task->getId()] ?? []);

        return $this->normalizeTask($task, $subtasks, $withSubtasks);
    }

    /**
     * Plusieurs tâches racines, avec précharge des étiquettes et sous-tâches en deux
     * requêtes au total (pas une par tâche).
     *
     * @param list<Task> $roots
     *
     * @return list<array<string, mixed>>
     */
    public function tasks(array $roots, bool $withSubtasks = true): array
    {
        $subtasksByParent = $this->taskRepository->loadRelations($roots);

        return array_map(
            fn (Task $task): array => $this->normalizeTask($task, $subtasksByParent[$task->getId()] ?? [], $withSubtasks),
            $roots,
        );
    }

    /**
     * @param array<int, int> $cardCounts nombre de cartes par id de colonne (facultatif)
     *
     * @return array<string, mixed>
     */
    public function column(TaskColumn $column, array $cardCounts = []): array
    {
        return [
            'id' => $column->getId(),
            'projectId' => $column->getProject()?->getId(),
            'name' => $column->getName(),
            'color' => $column->getColor(),
            'position' => $column->getPosition(),
            'wipLimit' => $column->getWipLimit(),
            'isDone' => $column->isDone(),
            'taskCount' => $cardCounts[$column->getId()] ?? 0,
        ];
    }

    /**
     * @param array{open: int, done: int} $counts
     *
     * @return array<string, mixed>
     */
    public function project(TaskProject $project, array $counts = ['open' => 0, 'done' => 0]): array
    {
        return [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'color' => $project->getColor(),
            'isArchived' => $project->isArchived(),
            'position' => $project->getPosition(),
            'openCount' => $counts['open'],
            'doneCount' => $counts['done'],
            'createdAt' => $project->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $project->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @param list<Task> $subtasks
     *
     * @return array<string, mixed>
     */
    private function normalizeTask(Task $task, array $subtasks, bool $withSubtasks): array
    {
        $labels = $task->getLabels()->toArray();
        usort($labels, static fn (TaskLabel $a, TaskLabel $b): int => strcasecmp($a->getName(), $b->getName()));

        $data = [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'isDone' => $task->isDone(),
            'completedAt' => $task->getCompletedAt()?->format(\DATE_ATOM),
            'priority' => $task->getPriority(),
            'dueDate' => $task->getDueDate()?->format('Y-m-d'),
            'isOverdue' => $task->isOverdue(),
            'repeat' => $task->getRepeat(),
            'position' => $task->getPosition(),
            'parentId' => $task->getParent()?->getId(),
            'projectId' => $task->getProject()?->getId(),
            'columnId' => $task->getColumn()?->getId(),
            'labels' => array_map(
                static fn (TaskLabel $label): array => ['id' => $label->getId(), 'name' => $label->getName(), 'color' => $label->getColor()],
                $labels,
            ),
            'subtaskCount' => count($subtasks),
            'subtaskDoneCount' => count(array_filter($subtasks, static fn (Task $subtask): bool => $subtask->isDone())),
            'createdAt' => $task->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $task->getUpdatedAt()->format(\DATE_ATOM),
        ];

        if ($withSubtasks && !$task->isSubtask()) {
            $data['subtasks'] = array_map(fn (Task $subtask): array => $this->normalizeTask($subtask, [], false), $subtasks);
        }

        return $data;
    }
}
