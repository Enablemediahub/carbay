<div class="mx-auto max-w-2xl space-y-5">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">SMS promo blast</h1>
        <p class="mt-1 text-sm text-gray-500">Send a message to clients who opted in to marketing texts. Every recipient uses package SMS credits.</p>
    </div>
    <form wire:submit="send" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
        <label class="block text-sm font-medium">Message
            <textarea wire:model="message" rows="5" maxlength="1000" required class="mt-1 w-full rounded-xl border-gray-300" placeholder="Share an offer or update"></textarea>
        </label>
        @error('message')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
        <p class="text-xs text-gray-500">Only opted-in clients with a phone number receive promotions. Sending is queued; failed provider deliveries return reserved credits.</p>
        <button type="submit" wire:loading.attr="disabled" class="w-full rounded-xl bg-primary-600 px-4 py-3 font-semibold text-white">Queue promo messages</button>
    </form>
</div>
