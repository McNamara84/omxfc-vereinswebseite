<div>
    <!-- Generate API Token -->
    <x-card title="{{ __('Create API Token') }}" subtitle="{{ __('API tokens allow third-party services to authenticate with our application on your behalf.') }}" shadow separator>
        <form wire:submit="createApiToken">
            <div class="space-y-4">
                <x-input label="{{ __('Token Name') }}" wire:model="createApiTokenForm.name" autofocus />

                @if (Laravel\Jetstream\Jetstream::hasPermissions())
                    <div>
                        <div class="font-medium text-sm text-base-content mb-2">{{ __('Permissions') }}</div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach (Laravel\Jetstream\Jetstream::$permissions as $permission)
                                <x-checkbox wire:model="createApiTokenForm.permissions" :value="$permission" :label="$permission" />
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <x-slot:actions>
                <x-action-message class="me-3" on="created">
                    {{ __('Created.') }}
                </x-action-message>
                <x-button label="{{ __('Create') }}" type="submit" class="btn-primary" />
            </x-slot:actions>
        </form>
    </x-card>

    @if ($this->user->tokens->isNotEmpty())
        <x-hr class="my-8" />

        <!-- Manage API Tokens -->
        <x-card title="{{ __('Manage API Tokens') }}" subtitle="{{ __('You may delete any of your existing tokens if they are no longer needed.') }}" shadow separator>
            <div class="space-y-6">
                @foreach ($this->user->tokens->sortBy('name') as $token)
                    <div class="flex items-center justify-between">
                        <div class="break-all text-base-content">
                            {{ $token->name }}
                        </div>

                        <div class="flex items-center ms-2">
                            @if ($token->last_used_at)
                                <div class="text-sm text-base-content/60">
                                    {{ __('Last used') }} {{ $token->last_used_at->diffForHumans() }}
                                </div>
                            @endif

                            @if (Laravel\Jetstream\Jetstream::hasPermissions())
                                <button class="cursor-pointer ms-6 text-sm text-base-content/60 underline" wire:click="manageApiTokenPermissions({{ $token->id }})">
                                    {{ __('Permissions') }}
                                </button>
                            @endif

                            <button class="cursor-pointer ms-6 text-sm text-error" wire:click="confirmApiTokenDeletion({{ $token->id }})">
                                {{ __('Delete') }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    <!-- Token Value Modal -->
    <x-modal wire:model="displayingToken" title="{{ __('API Token') }}" separator>
        <div>
            {{ __('Please copy your new API token. For your security, it won\'t be shown again.') }}
        </div>

        <x-input x-ref="plaintextToken" type="text" readonly :value="$plainTextToken"
            class="mt-4 font-mono text-sm"
            autofocus autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
            @showing-token-modal.window="setTimeout(() => $refs.plaintextToken.select(), 250)"
        />

        <x-slot:actions>
            <x-button label="{{ __('Close') }}" class="btn-ghost" wire:click="$set('displayingToken', false)" wire:loading.attr="disabled" />
        </x-slot:actions>
    </x-modal>

    <!-- API Token Permissions Modal -->
    <x-modal wire:model="managingApiTokenPermissions" title="{{ __('API Token Permissions') }}" separator>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach (Laravel\Jetstream\Jetstream::$permissions as $permission)
                <x-checkbox wire:model="updateApiTokenForm.permissions" :value="$permission" :label="$permission" />
            @endforeach
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" class="btn-ghost" wire:click="$set('managingApiTokenPermissions', false)" wire:loading.attr="disabled" />
            <x-button label="{{ __('Save') }}" class="btn-primary ms-3" wire:click="updateApiToken" wire:loading.attr="disabled" />
        </x-slot:actions>
    </x-modal>

    <!-- Delete Token Confirmation Modal -->
    <x-modal wire:model="confirmingApiTokenDeletion" title="{{ __('Delete API Token') }}" separator>
        <div class="flex items-start gap-4">
            <div class="flex items-center justify-center size-10 rounded-full bg-error/10 shrink-0">
                <x-icon name="o-exclamation-triangle" class="size-6 text-error" />
            </div>
            <div class="text-sm text-base-content">
                {{ __('Are you sure you would like to delete this API token?') }}
            </div>
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" class="btn-ghost" wire:click="$toggle('confirmingApiTokenDeletion')" wire:loading.attr="disabled" />
            <x-button label="{{ __('Delete') }}" class="btn-error ms-3" wire:click="deleteApiToken" wire:loading.attr="disabled" />
        </x-slot:actions>
    </x-modal>
</div>
