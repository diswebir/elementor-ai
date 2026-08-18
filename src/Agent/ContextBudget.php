<?php

declare(strict_types=1);

namespace AIEA\Agent;

final readonly class ContextBudget
{
    public function __construct(public int $maxCharacters = 40000, public int $planMaxCharacters = 14000)
    {
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    public function trim(array $context): array
    {
        $json = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || mb_strlen($json) <= $this->maxCharacters) {
            return $context;
        }

        $context['truncated'] = true;
        $context['document']['content'] = array_slice((array) ($context['document']['content'] ?? []), 0, 30);
        return $context;
    }

    /** @param array<string, mixed> $context
     *  @return array<string, mixed>
     */
    public function forPlan(array $context): array
    {
        $planContext = $context;
        $document = (array) ($planContext['document'] ?? []);
        $document['content'] = $this->compactElements((array) ($document['content'] ?? []), 14, 2);
        $document['page_settings'] = $this->compactSettings((array) ($document['page_settings'] ?? []));
        $planContext['document'] = $document;
        $planContext['plan_context_reduced'] = true;

        if ($this->length($planContext) > $this->planMaxCharacters) {
            unset($planContext['globals']);
            $planContext['document']['content'] = $this->compactElements((array) $document['content'], 8, 1);
        }
        if ($this->length($planContext) > $this->planMaxCharacters) {
            $planContext['catalog'] = array_map(
                static fn (mixed $definition): mixed => is_array($definition) ? array_intersect_key($definition, array_flip(['name', 'title', 'controls'])) : $definition,
                (array) ($planContext['catalog'] ?? []),
            );
        }

        return $planContext;
    }

    /** @param list<mixed> $elements
     *  @return list<array<string, mixed>>
     */
    private function compactElements(array $elements, int $limit, int $depth): array
    {
        $result = [];
        foreach (array_slice($elements, 0, $limit) as $element) {
            if (!is_array($element)) {
                continue;
            }
            $compact = array_intersect_key($element, array_flip(['id', 'type', 'widgetType', 'elType', 'parent', 'settings', 'children']));
            $compact['settings'] = $this->compactSettings((array) ($compact['settings'] ?? []));
            if ($depth > 0) {
                $compact['children'] = $this->compactElements((array) ($compact['children'] ?? []), 8, $depth - 1);
            } else {
                unset($compact['children']);
            }
            $result[] = $compact;
        }

        return $result;
    }

    /** @param array<string, mixed> $settings
     *  @return array<string, mixed>
     */
    private function compactSettings(array $settings): array
    {
        $result = [];
        foreach ($settings as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (is_string($value)) {
                $result[$key] = mb_substr($value, 0, 500);
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $result[$key] = $value;
                continue;
            }
            if (is_array($value)) {
                $result[$key] = array_slice($value, 0, 10, true);
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $context */
    private function length(array $context): int
    {
        $json = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? mb_strlen($json) : 0;
    }
}
