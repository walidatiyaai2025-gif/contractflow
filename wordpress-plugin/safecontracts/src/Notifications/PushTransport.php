<?php

declare(strict_types=1);

namespace SafeContracts\Notifications;

interface PushTransport
{
    /**
     * @param array{title:string,body:string,data?:array<string, scalar|null>} $payload
     * @return array{success:bool,status_code?:int,error_code?:string|null}
     */
    public function send(string $token, array $payload): array;
}
