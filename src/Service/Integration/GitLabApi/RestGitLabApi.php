<?php

declare(strict_types=1);

namespace Buddy\Repman\Service\Integration\GitLabApi;

use Buddy\Repman\Service\Integration\GitLabApi;
use Gitlab\Client;
use Gitlab\ResultPager;

final class RestGitLabApi implements GitLabApi
{
    public function __construct(private readonly Client $client, private readonly ResultPager $pager, string $url)
    {
        $this->client->setUrl($url);
    }

    public function projects(string $accessToken): Projects
    {
        $this->client->authenticate($accessToken, Client::AUTH_OAUTH_TOKEN);

        return new Projects(array_merge(
            $this->fetchAllProjects(['owned' => true]),
            $this->fetchAllProjects(['membership' => true])
        ));
    }

    public function addHook(string $accessToken, int $projectId, string $hookUrl): void
    {
        $this->client->authenticate($accessToken, Client::AUTH_OAUTH_TOKEN);

        foreach ($this->pager->fetchAll($this->client->projects(), 'hooks', [$projectId]) as $hook) {
            if ($hook['url'] === $hookUrl) {
                return;
            }
        }

        $this->client->projects()->addHook($projectId, $hookUrl, [
            'push_events' => true,
            'tag_push_events' => true,
        ]);
    }

    public function removeHook(string $accessToken, int $projectId, string $hookUrl): void
    {
        $this->client->authenticate($accessToken, Client::AUTH_OAUTH_TOKEN);

        foreach ($this->pager->fetchAll($this->client->projects(), 'hooks', [$projectId]) as $hook) {
            if ($hook['url'] === $hookUrl) {
                $this->client->projects()->removeHook($projectId, $hook['id']);
            }
        }
    }

    /**
     * @param array<string,bool> $options
     *
     * @return Project[]
     */
    private function fetchAllProjects(array $options = []): array
    {
        $fetchOptions = array_merge([
            'simple' => true,
            'order_by' => 'last_activity_at',
        ], $options);

        return array_map(fn (array $project): Project => new Project(
            $project['id'],
            $project['path_with_namespace'],
            $project['web_url']
        ), $this->pager->fetchAll($this->client->projects(), 'all', [$fetchOptions]));
    }
}
