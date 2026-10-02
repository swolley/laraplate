<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Modules\AI\Services\ModerationSystemUser;
use Modules\CMS\Models\Comment;
use Modules\Core\Approvals\Operation;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;
use Nwidart\Modules\Facades\Module;

/**
 * After the real application seeding, the system user AI moderation votes as may approve and
 * disapprove a CMS comment request: an application-level check, because it joins AI's seeded
 * actor to CMS's permissions and neither module depends on the other. Skipped when either
 * module is not installed and enabled.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (! class_exists(ModerationSystemUser::class)
        || ! class_exists(Comment::class)
        || ! Module::isEnabled('AI')
        || ! Module::isEnabled('CMS')) {
        $this->markTestSkipped('Needs the AI and CMS modules installed and enabled.');
    }

    Artisan::call('db:seed', ['--no-interaction' => true]);

    $this->system_user = app(ModerationSystemUser::class)->resolve();

    $this->modification = Modification::query()->create([
        'modifiable_type' => Comment::class,
        'modifiable_id' => null,
        'modifier_id' => User::factory()->create()->id,
        'modifier_type' => User::class,
        'active' => true,
        'operation' => Operation::Create,
        'approvers_required' => 1,
        'disapprovers_required' => 1,
        'md5' => md5('system-user-authorization'),
        'modifications' => ['body' => ['original' => null, 'modified' => 'Hi']],
    ]);
});

it('lets the seeded AI system user approve a comment modification', function (): void {
    expect($this->system_user->isAuthorizedToCastApprovalVote($this->modification, true))->toBeTrue();
});

it('lets the seeded AI system user disapprove a comment modification', function (): void {
    expect($this->system_user->isAuthorizedToCastApprovalVote($this->modification, false))->toBeTrue();
});
