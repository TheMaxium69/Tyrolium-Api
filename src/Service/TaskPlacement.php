<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskColumn;
use App\Entity\User;
use App\Repository\TaskRepository;

/**
 * Ordonne les tâches dans leur conteneur (sous-tâches d'un parent, cartes d'une colonne
 * ou liste de base) : les positions sont renumérotées 0, 1, 2… à chaque déplacement, ce
 * qui garde un « glisser-déposer » simple à exprimer (un index cible). Un trou peut
 * exister après une suppression ; il disparaît au prochain déplacement dans le conteneur. Ne flush pas :
 * l'appelant le fait dans sa transaction.
 */
final class TaskPlacement
{
    public function __construct(private readonly TaskRepository $taskRepository)
    {
    }

    /**
     * Insère $task à l'index $position (null ou trop grand = à la fin) parmi les autres
     * tâches de son conteneur, tel que décrit par son parent / sa colonne déjà posés sur
     * l'entité, puis renumérote tout le conteneur.
     */
    public function place(Task $task, ?int $position = null): void
    {
        $user = $task->getUser();
        if (null === $user) {
            throw new \LogicException('La tâche doit avoir un propriétaire avant d\'être placée.');
        }

        $siblings = $this->taskRepository->findSiblings($user, $task->getParent(), $task->getColumn(), $task);
        $index = null === $position ? count($siblings) : max(0, min($position, count($siblings)));
        array_splice($siblings, $index, 0, [$task]);

        $this->renumber($siblings);
    }

    /**
     * Referme le trou laissé par une tâche partie d'un conteneur (après un déplacement).
     */
    public function compact(User $user, ?Task $parent, ?TaskColumn $column, ?Task $exclude = null): void
    {
        $this->renumber($this->taskRepository->findSiblings($user, $parent, $column, $exclude));
    }

    /**
     * Range une carte dans une colonne (et donc dans son projet) : elle prend l'état de la
     * colonne (terminée si la colonne l'est, rouverte sinon), puis est insérée à l'index
     * voulu ; la colonne quittée est refermée. Valide pour une tâche racine uniquement.
     */
    public function moveToColumn(Task $task, TaskColumn $target, ?int $position = null): void
    {
        $user = $task->getUser();
        $oldColumn = $task->getColumn();

        $task->setProject($target->getProject())->setColumn($target)->setDone($target->isDone());
        $this->place($task, $position);

        if (null !== $user && null !== $oldColumn && $oldColumn !== $target) {
            $this->compact($user, null, $oldColumn, $task);
        }
    }

    /**
     * Remet une carte dans la liste de base (hors de tout projet) à l'index voulu ; son
     * état terminé / ouvert ne change pas.
     */
    public function moveToBase(Task $task, ?int $position = null): void
    {
        $user = $task->getUser();
        $oldColumn = $task->getColumn();

        $task->setProject(null)->setColumn(null);
        $this->place($task, $position);

        if (null !== $user && null !== $oldColumn) {
            $this->compact($user, null, $oldColumn, $task);
        }
    }

    /**
     * Rattache une sous-tâche à un parent (le même ou un autre) à l'index voulu.
     */
    public function moveToParent(Task $subtask, Task $newParent, ?int $position = null): void
    {
        $user = $subtask->getUser();
        $oldParent = $subtask->getParent();

        $subtask->setParent($newParent);
        $this->place($subtask, $position);

        if (null !== $user && null !== $oldParent && $oldParent !== $newParent) {
            $this->compact($user, $oldParent, null, $subtask);
        }
    }

    /**
     * Ajoute plusieurs cartes à la fin d'une colonne (ou de la liste de base si $target est
     * null), dans l'ordre donné — suppression d'une colonne ou d'un projet. Les cartes
     * sont toutes placées en une fois : des insertions successives liraient des
     * positions pas encore enregistrées.
     *
     * @param list<Task> $tasks
     */
    public function appendMany(User $user, array $tasks, ?TaskColumn $target): void
    {
        $siblings = $this->taskRepository->findSiblings($user, null, $target);
        $movedIds = array_map(static fn (Task $task): ?int => $task->getId(), $tasks);
        $siblings = array_values(array_filter($siblings, static fn (Task $task): bool => !in_array($task->getId(), $movedIds, true)));

        foreach ($tasks as $task) {
            if (null === $target) {
                $task->setProject(null)->setColumn(null);
            } else {
                $task->setProject($target->getProject())->setColumn($target)->setDone($target->isDone());
            }
            $siblings[] = $task;
        }

        $this->renumber($siblings);
    }

    /**
     * @param list<Task> $tasks
     */
    private function renumber(array $tasks): void
    {
        foreach ($tasks as $index => $task) {
            if ($task->getPosition() !== $index) {
                $task->setPosition($index);
            }
        }
    }
}
