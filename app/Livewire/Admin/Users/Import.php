<?php

namespace App\Livewire\Admin\Users;

use App\Domain\Users\Actions\ImportUsersFromCsv;
use App\Enums\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Import użytkowników')]
class Import extends Component
{
    use WithFileUploads;

    public $file;

    public string $defaultRole = 'teacher';

    public bool $updateExisting = false;

    public bool $sendSetupLinks = true;

    /** @var array<string, mixed>|null */
    public ?array $preview = null;

    public function mount(): void
    {
        $this->authorize('import', User::class);
    }

    protected function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'defaultRole' => ['required', Rule::enum(Role::class)],
        ];
    }

    public function updatedFile(): void
    {
        $this->preview = null;
    }

    public function check(ImportUsersFromCsv $import): void
    {
        $this->authorize('import', User::class);
        $this->validate();

        $this->preview = $import->handle($this->file->getRealPath(), dryRun: true,
            updateExisting: $this->updateExisting, defaultRole: $this->defaultRole);
    }

    public function import(ImportUsersFromCsv $import)
    {
        $this->authorize('import', User::class);
        $this->validate();

        $result = $import->handle($this->file->getRealPath(), dryRun: false,
            updateExisting: $this->updateExisting, sendSetupLinks: $this->sendSetupLinks, defaultRole: $this->defaultRole);

        Flux::toast(variant: 'success', text: __('Zaimportowano: :created nowych, :updated zaktualizowanych.', ['created' => $result['created'], 'updated' => $result['updated']]));

        if ($result['failed_links']) {
            Flux::toast(variant: 'warning', duration: 0, text: __('Nie udało się wysłać linku do: :emails. Wyślij go ponownie z listy użytkowników.', ['emails' => implode(', ', $result['failed_links'])]));
        }

        return $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF";
            echo "imie;nazwisko;email;rola;grupy;telefon\n";
            echo "Anna;Kowalska;a.kowalska@szkola.edu.pl;teacher;Nauczyciele|Matematyka:lider|Wychowawcy;\n";
            echo "Jan;Nowak;j.nowak@szkola.edu.pl;coordinator;Nauczyciele|Wolontariat;600100200\n";
        }, 'mci-import-szablon.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render()
    {
        $this->authorize('import', User::class);

        return view('livewire.admin.users.import', ['roles' => Role::cases()]);
    }
}
