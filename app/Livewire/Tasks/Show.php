<?php

namespace App\Livewire\Tasks;

use App\Domain\Tasks\Actions\ChangeTaskStatus;
use App\Domain\Tasks\Actions\DeleteTask;
use App\Domain\Tasks\Actions\ToggleChecklistItem;
use App\Enums\TaskStatus;
use App\Models\Task;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public int $taskId;

    public function mount(Task $task): void
    {
        $this->authorize('view', $task);
        $this->taskId = $task->id;

        // Opening the task clears its bell notifications.
        Auth::user()->unreadNotifications()->where('data->task_id', $task->id)->update(['read_at' => now()]);
    }

    #[Computed]
    public function task(): Task
    {
        return Task::query()->with(['school', 'creator', 'completer', 'assignees.user', 'assignees.group', 'checklist', 'images'])->findOrFail($this->taskId);
    }

    public function setStatus(string $status, ChangeTaskStatus $change): void
    {
        $change->handle(Auth::user(), $this->task, TaskStatus::from($status));
        unset($this->task);
        Flux::toast(text: __('Status: :status', ['status' => TaskStatus::from($status)->label()]));
    }

    public function toggleItem(int $itemId, ToggleChecklistItem $toggle): void
    {
        $item = $this->task->checklist->firstWhere('id', $itemId);
        abort_unless($item, 404);
        $toggle->handle(Auth::user(), $item);
        unset($this->task);
    }

    public function delete(DeleteTask $delete)
    {
        $delete->handle(Auth::user(), $this->task);
        Flux::toast(text: __('Usunięto zadanie.'));

        return $this->redirectRoute('tasks.index', navigate: true);
    }

    public function render()
    {
        $task = $this->task;

        return view('livewire.tasks.show', [
            'statuses' => TaskStatus::cases(),
            'canWork' => Auth::user()->can('work', $task),
            'canEdit' => Auth::user()->can('update', $task),
            'description' => $task->description,
        ])->title($task->title);
    }
}
