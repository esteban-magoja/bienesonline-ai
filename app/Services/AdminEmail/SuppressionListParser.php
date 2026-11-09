<?php

namespace App\Services\AdminEmail;

use Illuminate\Support\Str;

/**
 * Saca las direcciones a dar de baja de un texto pegado en el admin: una lista de emails
 * (separados por líneas, comas, espacios o ";") o el texto de uno o varios rebotes.
 *
 * Si el texto trae bloques de rebote ("Final-Recipient:"), solo toma esas direcciones y
 * descarta los avisos temporales ("Action: delayed"), para no dar de baja casillas que
 * todavía pueden recibir. Nunca devuelve direcciones del dominio propio (remitente, Message-ID)
 * ni las del servidor de correo (mailer-daemon, postmaster).
 */
class SuppressionListParser
{
    private const EMAIL_PATTERN = '/[a-z0-9._%+\-]+@[a-z0-9\-]+(?:\.[a-z0-9\-]+)*\.[a-z]{2,}/i';

    private const SYSTEM_LOCAL_PARTS = ['mailer-daemon', 'postmaster'];

    /**
     * @return array{emails: list<string>, temporary: list<string>, invalid: list<string>}
     */
    public function parse(string $text): array
    {
        if (preg_match('/^\s*Final-Recipient:/mi', $text) === 1) {
            return $this->parseBounces($text);
        }

        $emails = [];
        $invalid = [];

        foreach (preg_split('/[\s,;<>"\'()]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $token = Str::lower(trim($token, ".:[]"));

            if (! str_contains($token, '@')) {
                continue;
            }

            if (preg_match(self::EMAIL_PATTERN, $token, $match) === 1 && $match[0] === $token && $this->isValid($token)) {
                $emails[] = $token;
            } elseif (! str_starts_with($token, 'mailto:')) {
                $invalid[] = $token;
            }
        }

        return [
            'emails' => $this->withoutOwnAddresses($emails),
            'temporary' => [],
            'invalid' => array_values(array_unique($invalid)),
        ];
    }

    /**
     * @return array{emails: list<string>, temporary: list<string>, invalid: list<string>}
     */
    private function parseBounces(string $text): array
    {
        // Cada destinatario del rebote es un párrafo (Action, Final-Recipient, Status...) separado por una línea en blanco
        $blocks = preg_split('/\R[ \t]*\R/', $text) ?: [];
        $failed = [];
        $temporary = [];

        foreach ($blocks as $block) {
            if (preg_match('/^\s*Final-Recipient:\s*(?:rfc822\s*;)?\s*<?([^\s>]+)>?/mi', $block, $match) !== 1) {
                continue;
            }

            $email = Str::lower(trim($match[1]));

            if (! $this->isValid($email)) {
                continue;
            }

            if (preg_match('/^\s*Action:\s*delayed/mi', $block) === 1 || preg_match('/^\s*Status:\s*4\./mi', $block) === 1) {
                $temporary[] = $email;
            } else {
                $failed[] = $email;
            }
        }

        $failed = $this->withoutOwnAddresses($failed);

        return [
            'emails' => $failed,
            'temporary' => array_values(array_diff(array_unique($temporary), $failed)),
            'invalid' => [],
        ];
    }

    private function isValid(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    private function withoutOwnAddresses(array $emails): array
    {
        $ownDomains = $this->ownDomains();

        return array_values(array_unique(array_filter($emails, function (string $email) use ($ownDomains): bool {
            [$localPart, $domain] = explode('@', $email, 2);

            if (in_array($localPart, self::SYSTEM_LOCAL_PARTS, true)) {
                return false;
            }

            foreach ($ownDomains as $ownDomain) {
                if ($domain === $ownDomain || str_ends_with($domain, '.' . $ownDomain)) {
                    return false;
                }
            }

            return true;
        })));
    }

    /**
     * @return list<string>
     */
    private function ownDomains(): array
    {
        $domains = [];

        $senderAddress = config('mail.mailers.admin.from.address');
        if (is_string($senderAddress) && str_contains($senderAddress, '@')) {
            $domains[] = Str::lower(Str::after($senderAddress, '@'));
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '' && ! in_array($appHost, ['localhost', '127.0.0.1'], true)) {
            $domains[] = Str::lower(Str::after($appHost, 'www.'));
        }

        return array_values(array_unique(array_filter($domains)));
    }
}
