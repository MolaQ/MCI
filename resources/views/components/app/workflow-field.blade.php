@props(['field', 'model'])

{{-- One input of a workflow template field (types: text, textarea, date, number, select, checkbox). --}}
@php($label = $field['label'].($field['required'] ? ' *' : ''))
@switch($field['type'])
    @case('textarea')
        <flux:textarea wire:model="{{ $model }}" :label="$label" rows="3" maxlength="5000" />
        @break
    @case('date')
        <flux:input type="date" wire:model="{{ $model }}" :label="$label" />
        @break
    @case('number')
        <flux:input type="text" inputmode="decimal" wire:model="{{ $model }}" :label="$label" maxlength="20" />
        @break
    @case('select')
        <flux:select wire:model="{{ $model }}" :label="$label">
            <flux:select.option value="">{{ __('— wybierz —') }}</flux:select.option>
            @foreach ($field['options'] ?? [] as $option)
                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>
        @break
    @case('checkbox')
        <flux:checkbox wire:model="{{ $model }}" :label="$label" />
        @break
    @default
        <flux:input wire:model="{{ $model }}" :label="$label" maxlength="255" />
@endswitch
<flux:error :name="'data.'.$field['key']" />
