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
    public const TV_PREPARING_LIMIT = 12;

    public const TV_READY_LIMIT = 4;

    public const PHONE_LIST_LIMIT = 6;

    #[Locked]
    public string $variant = 'tv';

    #[Computed]
    public function board(): array
    {
        return app(BoardQuery::class)->get();
    }

    /** @return list<array{id: int, number: string, first_name: string}> */
    #[Computed]
    public function tvPreparing(): array
    {
        return array_slice($this->board['preparing'], 0, self::TV_PREPARING_LIMIT);
    }

    #[Computed]
    public function tvPreparingOverflow(): int
    {
        return max(0, count($this->board['preparing']) - self::TV_PREPARING_LIMIT);
    }

    /** @return list<array{id: int, number: string, first_name: string}> */
    #[Computed]
    public function tvReady(): array
    {
        return array_slice($this->board['ready'], 0, self::TV_READY_LIMIT);
    }

    /** @return list<array{id: int, number: string, first_name: string}> */
    #[Computed]
    public function phoneReady(): array
    {
        return array_slice($this->board['ready'], 0, self::PHONE_LIST_LIMIT);
    }

    #[Computed]
    public function phoneReadyOverflow(): int
    {
        return max(0, count($this->board['ready']) - self::PHONE_LIST_LIMIT);
    }

    /** @return list<array{id: int, number: string, first_name: string}> */
    #[Computed]
    public function phonePreparing(): array
    {
        return array_slice($this->board['preparing'], 0, self::PHONE_LIST_LIMIT);
    }

    #[Computed]
    public function phonePreparingOverflow(): int
    {
        return max(0, count($this->board['preparing']) - self::PHONE_LIST_LIMIT);
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
