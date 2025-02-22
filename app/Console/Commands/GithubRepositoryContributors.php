<?php

namespace App\Console\Commands;

use App\Jobs\LoadRepositoryContributors;
use App\Models\Organization;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class GithubRepositoryContributors extends Command
{
    protected $signature = 'github:repository:contributors {name?}';
    protected $description = 'Load all contributors for repositories.';

    public function handle(): void
    {
        Repository::query()
            ->when(
                $this->argument('name'),
                fn (Builder $query, string $name) => $query->where('name', $name)
            )
            ->with('owner')
            ->get()
            ->reject(function (Repository $repository): bool {
                return $repository->owner instanceof User && $repository->owner->github_access_token === null;
            })
            ->reject(function (Repository $repository): bool {
                return $repository->owner instanceof Organization && $repository->owner->members()->whereIsRegistered()->doesntExist();
            })
            ->each(static function (Repository $repository): void {
                LoadRepositoryContributors::dispatchBatch($repository);
            });
    }
}
