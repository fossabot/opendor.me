<?php

namespace App\View\Components\Web;

use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\Component;

class GithubSponsors extends Component
{
    public function render(): View
    {
        return view('components.web.github-sponsors');
    }

    public function sponsors(): Collection
    {
        $query = <<<'GRAPHQL'
        {
            viewer {
                sponsorshipsAsMaintainer(first: 100) {
                    nodes {
                        sponsorEntity {
                            __typename
                            ... on User {
                                databaseId
                                login
                            }
                            ... on Organization {
                                databaseId
                                login
                            }
                        }
                    }
                }
            }
        }
        GRAPHQL;

        return Cache::remember(
            'github_sponsors',
            CarbonInterval::day(),
            fn () => Http::baseUrl('https://api.github.com')
                ->accept('application/vnd.github.v3+json')
                ->withToken(config('services.github.sponsors_access_token'))
                ->withUserAgent(config('app.name').' '.config('app.url'))
                ->withOptions(['http_errors' => true])
                ->post('/graphql', ['query' => $query])
                ->collect('data.viewer.sponsorshipsAsMaintainer.nodes.*.sponsorEntity')
                ->map(function (array $sponsor) {
                    $sponsor['id'] = $sponsor['databaseId'];

                    if ($sponsor['__typename'] === 'User') {
                        return User::fromGithub($sponsor);
                    }

                    if ($sponsor['__typename'] === 'Organization') {
                        return Organization::fromGithub($sponsor);
                    }
                })
                ->filter()
                ->sortBy(fn (User | Organization $sponsor) => Str::lower($sponsor->name))
                ->values()
        );
    }
}
