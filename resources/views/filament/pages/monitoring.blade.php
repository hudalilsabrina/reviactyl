<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Select Node
        </x-slot>
        
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="nodeId">
                @foreach($nodes as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </x-filament::section>

    @foreach ($this->getHeaderWidgetsData() as $widget)
        @livewire(\Livewire\Livewire::getAlias($widget['class']), $widget['data'], key($widget['class']))
    @endforeach
</x-filament-panels::page>
