<?php
namespace BitbucketReviews\Stash;

use BitbucketReviews\Exception\StashException;
use GuzzleHttp;
use Psr\Http\Message\ResponseInterface;

/**
 * Интеграция с stash-api
 *
 * @see https://developer.atlassian.com/server/bitbucket/reference/rest-api/
 */
class API
{
    /**
     * Все комментарии
     */
    public const ANCHOR_STATE_ALL = 'ALL';

    /**
     * Устаревшие
     */
    public const ANCHOR_STATE_ORPHANED = 'ORPHANED';

    /**
     * Активные
     */
    public const ANCHOR_STATE_ACTIVE = 'ACTIVE';

    /**
     * @var string Ключ для авторизации
     */
    protected $secretKey;

    /**
     * @var string Идентификатор проекта
     */
    protected $project;

    /**
     * @var string Имя репозитория
     */
    protected $repository;

    /**
     * @var string Версия stash API
     */
    protected $version = '1.0';

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var int Время ожидания ответа от API
     */
    protected $clientTimeout = 3.0;

    /**
     * @var int Идентификатор пользователя
     */
    protected $userId;

    /**
     * Конструктор
     *
     * @param string $url
     * @param string $secretKey
     * @param string $project
     * @param string $repository
     * @param bool   $debug
     * @throws \BitbucketReviews\Exception\StashException
     */
    public function __construct(
        string $url,
        string $secretKey,
        string $project,
        string $repository,
        bool $debug = false
    ) {
        $this->secretKey  = $secretKey;
        $this->project    = $project;
        $this->repository = $repository;
        $this->client     = new GuzzleHttp\Client([
            'base_uri'                                 => sprintf('%s/rest/api/%s/', trim($url, '/'), $this->version),
            GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => $this->clientTimeout,
            GuzzleHttp\RequestOptions::DEBUG           => $debug,
            GuzzleHttp\RequestOptions::HEADERS         => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->secretKey,
            ],
        ]);

        $this->ping();
    }

    /**
     * @see API::$userId
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Проверяет готовность API
     *
     * @throws \BitbucketReviews\Exception\StashException
     */
    public function ping(): void
    {
        $response = $this->client->get(sprintf('projects/%s/repos/%s', $this->project, $this->repository));

        if (!$response->hasHeader('X-AUSERID')) {
            throw new StashException(
                'Ошибка инициализации, не верный ответ: ' . GuzzleHttp\json_encode($response->getBody())
            );
        }

        $this->userId = (int) ($response->getHeader('X-AUSERID')[0] ?? 0);
    }

    /**
     * Получает идентификатор открытого пулл-реквеста
     *
     * @param string $branch
     * @return int
     * @throws \BitbucketReviews\Exception\StashException
     */
    public function getOpenPullRequestId(string $branch): int
    {
        $response = $this->client->get(
            sprintf('projects/%s/repos/%s/pull-requests', $this->project, $this->repository),
            ['query' => ['at' => $branch, 'status' => 'OPEN', 'direction' => 'OUTGOING', 'order' => 'NEWEST']]
        );

        $data = $this->getResponseAsArray($response, 200);

        if (!isset($data['values'][0]['id'])) {
            throw new StashException('Не удалось получить pull-request для ветки ' . $branch);
        }

        return (int) $data['values'][0]['id'];
    }

    /**
     * Получает комментарии для файла
     *
     * @param int    $pullRequestId
     * @param string $path
     * @param string $anchorState
     * @return \BitbucketReviews\Stash\Comment[]
     * @throws \BitbucketReviews\Exception\StashException
     */
    public function getComments(int $pullRequestId, string $path, string $anchorState = self::ANCHOR_STATE_ALL): array
    {
        $response = $this->client->get(
            sprintf(
                'projects/%s/repos/%s/pull-requests/%d/comments',
                $this->project,
                $this->repository,
                $pullRequestId
            ),
            ['query' => ['path' => $path, 'anchorState' => $anchorState]]
        );

        $data = $this->getResponseAsArray($response, 200);

        if (!isset($data['values'])) {
            throw new StashException(
                sprintf('Не удалось получить комментарии для %d по пути %s', $pullRequestId, $path)
            );
        }

        return array_map([Comment::class, 'fromArray'], (array) $data['values']);
    }

    /**
     * Публикует комментарий
     *
     * @param int                             $pullRequestId
     * @param \BitbucketReviews\Stash\Comment $comment
     * @return \BitbucketReviews\Stash\Comment
     * @throws \BitbucketReviews\Exception\StashException
     */
    public function postComment(int $pullRequestId, Comment $comment): Comment
    {
        $response = $this->client->post(
            sprintf(
                'projects/%s/repos/%s/pull-requests/%d/comments',
                $this->project,
                $this->repository,
                $pullRequestId
            ),
            ['json' => $comment->asArrayCreate()]
        );

        $data = $this->getResponseAsArray($response, 201);

        if (!isset($data['id'])) {
            throw new StashException('Не удалось получить id комментария: ' . GuzzleHttp\json_encode($data));
        }

        return $comment->setId($data['id']);
    }

    /**
     * Обновляет комментарий
     *
     * @param int                             $pullRequestId
     * @param \BitbucketReviews\Stash\Comment $comment
     * @return \BitbucketReviews\Stash\Comment
     * @throws \BitbucketReviews\Exception\StashException
     */
    public function updateComment(int $pullRequestId, Comment $comment): Comment
    {
        $response = $this->client->put(
            sprintf(
                'projects/%s/repos/%s/pull-requests/%d/comments/%d',
                $this->project,
                $this->repository,
                $pullRequestId,
                $comment->getId()
            ),
            ['json' => $comment->asArrayUpdate()]
        );

        $this->getResponseAsArray($response, 200);

        return $comment;
    }

    /**
     * Удаляет комментарий
     *
     * @param int                             $pullRequestId
     * @param \BitbucketReviews\Stash\Comment $comment
     * @return \BitbucketReviews\Stash\Comment
     * @throws \BitbucketReviews\Exception\StashException
     */
    public function deleteComment(int $pullRequestId, Comment $comment): Comment
    {
        $response = $this->client->delete(
            sprintf(
                'projects/%s/repos/%s/pull-requests/%d/comments/%d',
                $this->project,
                $this->repository,
                $pullRequestId,
                $comment->getId()
            )
        );

        $this->getResponseAsArray($response, 200);

        return $comment;
    }

    /**
     * Возвращает тело ответа в виде массива
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param int                                 $expectCode
     * @return array
     * @throws \BitbucketReviews\Exception\StashException
     */
    protected function getResponseAsArray(ResponseInterface $response, $expectCode): array
    {
        if ($response->getStatusCode() !== $expectCode) {
            throw new StashException('Получен не верный код ответа: ' . $response->getStatusCode());
        }

        try {
            return GuzzleHttp\json_decode($response->getBody()->getContents(), true);
        } catch (\InvalidArgumentException $e) {
            throw new StashException('Получен не верное тело ответа: ' . $e->getMessage());
        }
    }
}
