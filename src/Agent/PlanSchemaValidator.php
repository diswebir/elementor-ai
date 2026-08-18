<?php

declare(strict_types=1);

namespace AIEA\Agent;

use AIEA\Elementor\CapabilityCatalog;
use InvalidArgumentException;

final class PlanSchemaValidator
{
    /** @var list<string> */
    private const TOOLS = [
        'create_container', 'create_widget', 'update_widget', 'move_element', 'delete_element',
        'set_style', 'set_responsive_style', 'set_spacing', 'set_alignment', 'set_background',
        'set_typography', 'save_page', 'validate_page',
    ];

    public function __construct(private readonly CapabilityCatalog $catalog)
    {
    }

    /** @param mixed $plan
     *  @return array<string, mixed>
     */
    public function validate(mixed $plan): array
    {
        if (!is_array($plan)) {
            throw new InvalidArgumentException('Plan must be a JSON object.');
        }
        foreach (['schema_version', 'goal', 'assumptions', 'acceptance_criteria', 'risk_level', 'actions'] as $key) {
            if (!array_key_exists($key, $plan)) {
                throw new InvalidArgumentException('Plan is missing required field: ' . $key);
            }
        }
        if ($plan['schema_version'] !== '1.0' || !is_string($plan['goal']) || !is_array($plan['actions']) || $plan['actions'] === []) {
            throw new InvalidArgumentException('Plan format is invalid or has no actions.');
        }
        if (!in_array($plan['risk_level'], ['low', 'medium', 'high'], true)) {
            throw new InvalidArgumentException('Plan risk level is invalid.');
        }

        $seen = [];
        foreach ($plan['actions'] as $index => $action) {
            if (!is_array($action)) {
                throw new InvalidArgumentException('Plan action is invalid at index ' . $index);
            }
            foreach (['id', 'tool', 'depends_on', 'args', 'description', 'risk_level', 'requires_approval'] as $key) {
                if (!array_key_exists($key, $action)) {
                    throw new InvalidArgumentException('Action missing field: ' . $key);
                }
            }
            if (!is_string($action['id']) || !preg_match('/^a-[a-zA-Z0-9_-]{1,50}$/', $action['id']) || isset($seen[$action['id']])) {
                throw new InvalidArgumentException('Action id is invalid or duplicated.');
            }
            $seen[$action['id']] = true;
            if (!is_string($action['tool']) || !in_array($action['tool'], self::TOOLS, true)) {
                throw new InvalidArgumentException('Tool is not allowlisted.');
            }
            if (!is_array($action['depends_on']) || !is_array($action['args']) || !is_string($action['description']) || !is_bool($action['requires_approval'])) {
                throw new InvalidArgumentException('Action fields have invalid types.');
            }
            if (!in_array($action['risk_level'], ['low', 'medium', 'high'], true)) {
                throw new InvalidArgumentException('Action risk level is invalid.');
            }
            if ($action['tool'] === 'create_widget') {
                $type = $action['args']['widget_type'] ?? null;
                if (!is_string($type) || !$this->catalog->isAllowedWidget($type)) {
                    throw new InvalidArgumentException('Plan requested a widget that is not supported.');
                }
            }
        }

        foreach ($plan['actions'] as $action) {
            foreach ($action['depends_on'] as $dependency) {
                if (!is_string($dependency) || !isset($seen[$dependency])) {
                    throw new InvalidArgumentException('Plan contains an unknown dependency.');
                }
            }
        }

        return $plan;
    }
}
