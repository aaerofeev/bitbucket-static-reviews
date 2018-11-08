<?php
namespace CheckstyleStash\Stash;

use CheckstyleStash\Exception\StashException;
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
     * @var string Хост API
     */
    protected $url;

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
     * Конструктор
     *
     * @param string $url
     * @param string $secretKey
     * @param string $project
     * @param string $repository
     */
    public function __construct(string $url, string $secretKey, string $project, string $repository)
    {
        $this->secretKey  = $secretKey;
        $this->project    = $project;
        $this->repository = $repository;
        $this->url        = trim($url, '/');
        $this->client     = new GuzzleHttp\Client([
            'base_uri'                                 => sprintf('%s/rest/api/%s/', $url, $this->version),
            GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => $this->clientTimeout,
            GuzzleHttp\RequestOptions::HEADERS         => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->secretKey,
            ],
        ]);
    }

    /**
     * Получает идентификатор открытого пулл-реквеста
     *
     * @param string $branch
     * @return int
     * @throws \CheckstyleStash\Exception\StashException
     */
    public function getOpenPRId(string $branch): int
    {
        $response = $this->client->get(
            sprintf('/projects/%s/repos/%s/pull-requests', $this->project, $this->repository),
            ['query' => ['at' => $branch, 'status' => 'OPEN', 'direction' => 'OUTGOING', 'order' => 'NEWEST']]
        );

        $data = $this->getResponseAsArray($response, 200);

        if (!isset($data['values'][0]['id'])) {
            throw new StashException('Не удалось получить pull-request для ветки ' . $branch);
        }

        return (int) $data['values'][0]['id'];
    }

    /**
     * Публикует комментарий
     *
     * @param \CheckstyleStash\Stash\Comment $comment
     * @return int
     * @throws \CheckstyleStash\Exception\StashException
     */
    public function postComment(Comment $comment): Comment
    {
        $response = $this->client->post(
            sprintf('/projects/%s/repos/%s/pull-requests/comments', $this->project, $this->repository),
            ['json' => $comment]
        );

        $data = $this->getResponseAsArray($response, 201);

        if (!isset($data['id'])) {
            throw new StashException('Не удалось получить id комментария: ' . GuzzleHttp\json_encode($data));
        }

        return $comment->setId($data['id']);
    }

    /**
     * Возвращает тело ответа в виде массива
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param int                                 $expectCode
     * @return array
     * @throws \CheckstyleStash\Exception\StashException
     */
    protected function getResponseAsArray(ResponseInterface $response, $expectCode): array
    {
        if ($response->getStatusCode() !== $expectCode) {
            throw new StashException('Получен не верный код ответа: ' . $response->getStatusCode());
        }

        try {
            return GuzzleHttp\json_decode($response->getBody()->getContents(), true);
        } catch (\InvalidArgumentException $e) {
            throw new StashException('Получен не верное тело ответа. ' . $e->getMessage());
        }
    }
}
