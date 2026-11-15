<?php

declare(strict_types=1);

namespace App\Service;

final class RobotsTxtPolicy
{
    private const USER_AGENT = 'ProspectOS-Research';

    public function allows(string $contents, string $url): bool
    {
        $groups = [];
        $group = ['agents' => [], 'rules' => []];

        foreach (preg_split('/\r\n|\r|\n/', $contents) ?: [] as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if ($group['rules'] !== []) {
                    $groups[] = $group;
                    $group = ['agents' => [], 'rules' => []];
                }
                $group['agents'][] = strtolower($value);
                continue;
            }

            if ($group['agents'] !== [] && in_array($field, ['allow', 'disallow'], true) && $value !== '') {
                $group['rules'][] = ['allow' => $field === 'allow', 'pattern' => $value];
            }
        }
        if ($group['agents'] !== []) {
            $groups[] = $group;
        }

        $agent = strtolower(self::USER_AGENT);
        $specificity = -1;
        $rules = [];
        $wildcardRules = [];
        foreach ($groups as $candidate) {
            $matchLength = -1;
            foreach ($candidate['agents'] as $candidateAgent) {
                if ($candidateAgent === '*') {
                    $wildcardRules = [...$wildcardRules, ...$candidate['rules']];
                } elseif ($candidateAgent !== '' && str_contains($agent, $candidateAgent)) {
                    $matchLength = max($matchLength, strlen($candidateAgent));
                }
            }

            if ($matchLength > $specificity) {
                $specificity = $matchLength;
                $rules = $candidate['rules'];
            } elseif ($matchLength === $specificity && $matchLength >= 0) {
                $rules = [...$rules, ...$candidate['rules']];
            }
        }
        if ($specificity < 0) {
            $rules = $wildcardRules;
        }

        $parts = parse_url($url);
        $path = is_array($parts) ? ($parts['path'] ?? '/') : '/';
        if (isset($parts['query'])) {
            $path .= '?'.$parts['query'];
        }

        $bestLength = -1;
        $allowed = true;
        foreach ($rules as $rule) {
            $pattern = $rule['pattern'];
            $endAnchored = str_ends_with($pattern, '$');
            if ($endAnchored) {
                $pattern = substr($pattern, 0, -1);
            }
            $regex = '/^'.str_replace('\\*', '.*', preg_quote($pattern, '/')).($endAnchored ? '$' : '').'/u';
            if (preg_match($regex, $path) !== 1) {
                continue;
            }

            $length = strlen(str_replace('*', '', $pattern));
            if ($length > $bestLength || ($length === $bestLength && $rule['allow'])) {
                $bestLength = $length;
                $allowed = $rule['allow'];
            }
        }

        return $allowed;
    }
}
