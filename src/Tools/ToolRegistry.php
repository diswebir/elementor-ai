<?php

declare(strict_types=1);

namespace AIEA\Tools;

final class ToolRegistry
{
    /** @var array<string, ToolDefinition> */
    private array $definitions;

    public function __construct()
    {
        $this->definitions = [
            'create_container' => new ToolDefinition('create_container', 'low', ['parent']),
            'create_widget' => new ToolDefinition('create_widget', 'low', ['parent', 'widget_type', 'settings']),
            'update_widget' => new ToolDefinition('update_widget', 'low', ['target_id', 'settings']),
            'move_element' => new ToolDefinition('move_element', 'medium', ['target_id', 'parent_id']),
            'delete_element' => new ToolDefinition('delete_element', 'high', ['target_id'], true),
            'set_style' => new ToolDefinition('set_style', 'low', ['target_id', 'settings']),
            'set_responsive_style' => new ToolDefinition('set_responsive_style', 'medium', ['target_id', 'settings']),
            'set_spacing' => new ToolDefinition('set_spacing', 'low', ['target_id', 'settings']),
            'set_alignment' => new ToolDefinition('set_alignment', 'low', ['target_id', 'settings']),
            'set_background' => new ToolDefinition('set_background', 'medium', ['target_id', 'settings']),
            'set_typography' => new ToolDefinition('set_typography', 'medium', ['target_id', 'settings']),
            'save_page' => new ToolDefinition('save_page', 'low', []),
            'validate_page' => new ToolDefinition('validate_page', 'low', []),
        ];
    }

    /** @param array<string, mixed> $arguments */
    public function assertInvocation(string $tool, array $arguments): ToolDefinition
    {
        $definition = $this->definitions[$tool] ?? null;
        if ($definition === null) {
            throw new ToolException('Tool is not allowlisted.', 'tool_not_allowed');
        }
        foreach ($definition->requiredArguments as $argument) {
            if (!array_key_exists($argument, $arguments)) {
                throw new ToolException('Tool is missing a required argument.', 'invalid_tool_arguments');
            }
        }
        return $definition;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->definitions);
    }
}
