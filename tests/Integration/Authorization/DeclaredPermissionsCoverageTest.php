<?php

declare(strict_types=1);

use Modules\Core\Authorization\PermissionManifest;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\SAO\Models\Ticket;

/**
 * The action registry and the permission manifest are not wired to each other:
 * the registry is built at boot because the dispatcher needs it in a request,
 * the manifest is built only by `permission:refresh`. Coupling them at runtime
 * to keep them aligned would make every request pay for the console's data, so
 * the alignment is a test instead.
 *
 * An application-level check: it covers the domain actions of every installed module.
 */
it('declares a permission for every registered domain action', function (): void {
    /**
     * An action that deliberately reuses another action's permission. Reading the
     * moves a ticket may make is gated by the `transition` that performs them.
     * The alias is inert when SAO is not installed.
     *
     * @var array<class-string, array<string, string>>
     */
    $aliases = [Ticket::class => ['transitions' => 'transition']];

    $handlers = (new ReflectionProperty(DomainActionRegistry::class, 'handlers'))
        ->getValue(app(DomainActionRegistry::class));

    $declared = app(PermissionManifest::class)->operations();
    $undeclared = [];

    foreach ($handlers as $model_class => $actions) {
        foreach (array_keys($actions) as $action) {
            $operation = $aliases[$model_class][$action] ?? $action;

            if (! in_array($operation, $declared[$model_class] ?? [], true)) {
                $undeclared[] = sprintf('%s::%s', $model_class, $operation);
            }
        }
    }

    expect($undeclared)->toBe([]);
});
