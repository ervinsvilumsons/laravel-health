<?php

declare(strict_types=1);

namespace ErvinsVilumsons\LaravelHealth\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Event;

class ServiceFailed extends Event
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $message,
        public array $context,
        public string $level,
    ) {}
}
