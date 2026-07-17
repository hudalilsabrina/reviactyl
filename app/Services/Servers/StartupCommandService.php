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

        $template = $this->resolveStartupTemplate($server);
        $command = Str::replace($find, $replace, $template);

        // Fallback: append parts at the end if the template doesn't use {{STARTUP_PARTS}}.
        if ($partsString && ! str_contains($template, '{{STARTUP_PARTS}}')) {
            $command = rtrim($command).' '.$partsString;
        }

        return $command;
    }

    /**
     * Build the daemon invocation command with startup parts injected but
     * all other {{VAR}} placeholders left unresolved for the daemon.
     */
    public function getDaemonInvocation(Server $server): string
    {
        $partsString = $this->buildPartsString($server);
        if (empty($partsString)) {
            return $server->startup;
        }

        $template = $this->resolveStartupTemplate($server);

        if (str_contains($template, '{{STARTUP_PARTS}}')) {
            return str_replace('{{STARTUP_PARTS}}', $partsString, $template);
        }

        return rtrim($server->startup).' '.$partsString;
    }

    /**
     * Resolve the startup template to use for building the command.
     * If the server's startup has {{STARTUP_PARTS}}, use it. Otherwise
     * fall back to the egg's startup template which may have it.
     */
    private function resolveStartupTemplate(Server $server): string
    {
        if (str_contains($server->startup, '{{STARTUP_PARTS}}')) {
            return $server->startup;
        }

        $eggStartup = $server->egg->startup ?? '';
        if (str_contains($eggStartup, '{{STARTUP_PARTS}}')) {
            return $eggStartup;
        }

        return $server->startup;
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

            if ($enabled && ! empty(trim($part->value))) {
                $enabledValues[] = trim($part->value);
            }
        }

        return implode(' ', $enabledValues);
    }
}
