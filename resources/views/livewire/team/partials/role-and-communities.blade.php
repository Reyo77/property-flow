{{-- Role picker plus community checkboxes (hidden when the role already covers every community). --}}
<flux:select wire:model.live="{{ $roleField }}" :label="__('Role')" required>
    <flux:select.option value="">{{ __('Choose a role') }}</flux:select.option>
    @foreach ($this->roles as $role)
        <flux:select.option :value="$role->name">{{ $this->roleLabel($role->name) }}</flux:select.option>
    @endforeach
</flux:select>

@if ($this->{$roleField} !== '' && ! $this->roleGivesAllCommunities($this->{$roleField}))
    <flux:checkbox.group wire:model="{{ $communitiesField }}" :label="__('Communities')" :description="__('They will only see these communities.')">
        @foreach ($this->communities as $community)
            <flux:checkbox :value="$community->id" :label="$community->name" />
        @endforeach
    </flux:checkbox.group>
@elseif ($this->{$roleField} !== '')
    <flux:text>{{ __('This role can access every community.') }}</flux:text>
@endif
