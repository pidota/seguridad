<?php

declare(strict_types=1);

namespace Core;

interface MiddlewareInterface
{
    public function handle(Request $request, ?string $argument = null): void;
}
