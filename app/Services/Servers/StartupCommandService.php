<?php

namespace App\Services\Servers;

use App\Models\Server;
use Illuminate\Support\Str;

class StartupCommandService
{
    /**
     * Generates a startup command for a given server instance.
     */
    public function handle(Server $server, bool $hideAllValues = false): string
    {
        $find = ['{{SERVER_MEMORY}}', '{{SERVER_IP}}', '{{SERVER_PORT}}'];
        $replace = [$server->memory, $server->allocation->ip, $server->allocation->port];

        // Build modular startup parts string
        $partsString = $this->buildPartsString($server);
        $find[] = '{{STARTUP_PARTS}}';
        $replace[] = $partsString;

        foreach ($server->variables as $variable) {
            $find[] = '{{'.$variable->env_variable.'}}';
            $replace[] = ($variable->user_viewable && ! $hideAllValues) ? ($variable->server_value ?? $variable->default_value) : '[hidden]';
        }

        $command = Str::replace($find, $replace, $server->startup);

        // If no {{STARTUP_PARTS}} placeholder existed but parts are defined, append them
        if ($partsString && ! str_contains($server->startup, '{{STARTUP_PARTS}}')) {
            $command = rtrim($command).' '.$partsString;
        }

        return $command;
    }

    /**
     * Build the startup parts string from server's saved choices and egg's defined parts.
     */
    private function buildPartsString(Server $server): string
    {
        $parts = $server->egg->startupParts;
        if ($parts->isEmpty()) {
            return '';
        }

        $userChoices = $server->startup_parts ?? [];
        $enabledValues = [];

        foreach ($parts as $part) {
            $choice = collect($userChoices)->firstWhere('part_id', $part->id);
            $enabled = $choice['enabled'] ?? $part->default_enabled;

            if ($enabled) {
                $enabledValues[] = $part->value;
            }
        }

        return implode(' ', $enabledValues);
    }
}
