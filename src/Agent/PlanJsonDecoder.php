<?php

declare(strict_types=1);

namespace AIEA\Agent;

use InvalidArgumentException;

final class PlanJsonDecoder
{
    /** @return array<string, mixed> */
    public function decode(string $content): array
    {
        $content = trim($content);
        $candidates = [$content];

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/si', $content, $matches) === 1) {
            $candidates[] = trim($matches[1]);
        }

        $embeddedObject = $this->extractFirstObject($content);
        if ($embeddedObject !== null) {
            $candidates[] = $embeddedObject;
        }

        foreach (array_unique($candidates) as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new InvalidArgumentException('Provider response did not contain a valid JSON plan.');
    }

    private function extractFirstObject(string $content): ?string
    {
        $start = strpos($content, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaping = false;
        $length = strlen($content);

        for ($index = $start; $index < $length; $index++) {
            $character = $content[$index];

            if ($inString) {
                if ($escaping) {
                    $escaping = false;
                    continue;
                }
                if ($character === '\\') {
                    $escaping = true;
                    continue;
                }
                if ($character === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($character === '"') {
                $inString = true;
                continue;
            }
            if ($character === '{') {
                $depth++;
                continue;
            }
            if ($character === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($content, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }
}
