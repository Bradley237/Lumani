<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->schema }}

        <div class="mt-6 flex items-center gap-x-3">
            <x-filament::button type="submit">
                Save Settings
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
