<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Content\ContentApprover;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Non-interactive approve/reject for CI and scripting; the admin review
 * screen is the interactive equivalent (docs/15 Phase 7).
 */
class ContentApprove extends Command
{
    protected $signature = 'content:approve
        {entity : lesson, concept, exercise, quiz_question, flashcard, diagram or code_example}
        {entity_id : Row id}
        {--user= : Admin user id (defaults to the first admin)}
        {--reject : Move the draft back to draft status instead of publishing}';

    protected $description = 'Approve (publish) or reject an in-review AI generation';

    public function handle(ContentApprover $approver): int
    {
        $entityType = (string) $this->argument('entity');
        $entityId = (int) $this->argument('entity_id');

        if (! isset(ContentApprover::ENTITIES[$entityType])) {
            $this->error('Unknown entity: '.$entityType.' (expected '.implode(', ', array_keys(ContentApprover::ENTITIES)).').');

            return self::FAILURE;
        }

        if ($this->option('reject')) {
            $entity = $approver->reject($entityType, $entityId);
            $this->info("Rejected {$entityType} #{$entity->getKey()} - back to draft.");

            return self::SUCCESS;
        }

        $adminId = $this->option('user');

        $admin = $adminId !== null
            ? User::query()->whereKey($adminId)->first()
            : User::query()->where('role', 'admin')->orderBy('id')->first();

        if ($admin === null || ! $admin->isAdmin()) {
            $this->error('No admin user found - pass --user=<id>.');

            return self::FAILURE;
        }

        try {
            $entity = $approver->approve($entityType, $entityId, $admin);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Approved {$entityType} #{$entity->getKey()} - published by {$admin->email}.");

        return self::SUCCESS;
    }
}
