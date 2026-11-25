<?php

namespace App\Livewire\Concerns;

use Livewire\WithFileUploads;

/**
 * Upload rule shared by every screen that uses <x-ui.image-editor>. The
 * real checks (type from the bytes, pixel limit, re-encoding) happen in
 * App\Domain\Media\Actions\StoreImage.
 */
trait WithImageUploads
{
    use WithFileUploads;

    /**
     * @return list<string>
     */
    protected function imageUploadRule(): array
    {
        return ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:'.config('mci.media.max_upload_kb')];
    }
}
