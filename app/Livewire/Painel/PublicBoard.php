<?php

namespace App\Livewire\Painel;

use App\Queries\BoardQuery;
use App\Support\QrCode;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class PublicBoard extends Component
{
    #[Locked]
    public string $variant = 'tv';

    #[Computed]
    public function board(): array
    {
        return app(BoardQuery::class)->get();
    }

    #[On('echo:painel,BoardChanged')]
    public function onBoardChanged(array $payload = []): void
    {
        $kind = $payload['kind'] ?? null;

        if ($kind === 'ready') {
            $this->dispatch('announce', orderId: (int) $payload['orderId'], number: (string) $payload['number'], name: (string) $payload['firstName']);
        }

        if ($kind === 'unready') {
            $this->dispatch('announce-cancel', orderId: (int) $payload['orderId']);
        }
    }

    #[On('realtime-reconnected')]
    public function onReconnected(): void {}

    public function render(): View
    {
        if ($this->variant === 'mobile') {
            return view('livewire.painel.mobile');
        }

        return view('livewire.painel.tv', ['qr' => QrCode::dataUri(route('acompanhar'))]);
    }
}
