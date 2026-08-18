<?php

declare(strict_types=1);

namespace AIEA\Audit;

final class DiffService
{
    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @return array{added:list<string>, modified:list<string>, deleted:list<string>}
     */
    public function compare(array $before, array $after): array
    {
        $beforeIndex = $this->index($before);
        $afterIndex = $this->index($after);

        $added = array_values(array_diff(array_keys($afterIndex), array_keys($beforeIndex)));
        $deleted = array_values(array_diff(array_keys($beforeIndex), array_keys($afterIndex)));
        $modified = [];

        foreach (array_intersect(array_keys($beforeIndex), array_keys($afterIndex)) as $id) {
            if (wp_json_encode($beforeIndex[$id]) !== wp_json_encode($afterIndex[$id])) {
                $modified[] = $id;
            }
        }

        return ['added' => $added, 'modified' => $modified, 'deleted' => $deleted];
    }

    /** @param array<string, mixed> $document
     *  @return array<string, array<string, mixed>>
     */
    private function index(array $document): array
    {
        $index = [];
        $visit = function (array $elements) use (&$visit, &$index): void {
            foreach ($elements as $element) {
                if (!is_array($element) || empty($element['id'])) {
                    continue;
                }
                $index[(string) $element['id']] = $element;
                if (isset($element['elements']) && is_array($element['elements'])) {
                    $visit($element['elements']);
                }
            }
        };

        $visit($document['content'] ?? []);
        return $index;
    }
}
