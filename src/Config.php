<?php
namespace BitbucketReviews;

use BitbucketReviews\Exception\LogicException;
use BitbucketReviews\Stock\Error;

/**
 * Конфигурация
 */
class Config
{
    /**
     * Только измененное
     */
    const INSPECT_CHANGED = 1;

    /**
     * Использовать контекст
     */
    const INSPECT_CONTEXT = 2;

    /**
     * Нет лимита
     */
    const NO_LIMIT   = PHP_INT_MAX;

    /**
     * @var array Конфигурация по умолчанию
     */
    const DEFAULT = [
        // Settings from stash/bitbucket
        // https://<hostname>/projects/<project>/repos/<repository>/browse
        'stash'    => [
            'url'         => 'https://127.0.0.1',
            // Access token for read
            // @see https://confluence.atlassian.com/bitbucketserver/personal-access-tokens-939515499.html
            'accessToken' => '',
            'project'     => '',
            'repository'  => '',
            // Write to output http requests to api
            'debug'       => false,
        ],
        'analyzer' => [
            // Inspection type changed or context
            'inspect'       => Config::INSPECT_CHANGED,
            // Error with ignored text will be ignored
            'ignoredText'   => [],
            // Files in mask-style to be ignored
            'ignoredFiles'  => [],
            // Main limit for comments
            'limit'         => Config::NO_LIMIT,
            // Limit comments per file
            'limitPerFile'  => Config::NO_LIMIT,
            // Limit comments per group
            'limitPerGroup' => Config::NO_LIMIT,
            // Group errors in one comment per line
            'group'         => true,
            // Minimum severity
            'minSeverity'   => Error::SEVERITY_WARNING,
        ],
        // Optional
        'statsd'   => [
            'host'      => '',
            'port'      => null,
            'namespace' => '',
        ],
    ];

    /**
     * @var array Конфигурация
     */
    protected $data = [];

    /**
     * Загружает конфигурацию из файла
     *
     * @param string $path
     */
    public function loadConfig(string $path)
    {
        if (!file_exists($path)) {
            throw new LogicException("Config file not found: {$path}");
        }

        $config = require $path;

        if (!$config || !\is_array($config)) {
            throw new LogicException("Config is empty or not array: {$path}");
        }

        $this->data = self::DEFAULT;

        foreach ($config as $k => $v) {
            if (\is_array($v)) {
                foreach ($v as $kk => $vv) {
                    $this->data[$k][$kk] = $vv;
                }
            } else {
                $this->data[$k] = $v;
            }
        }
    }

    /**
     * Получает настройку по ключу
     *
     * @param string $name
     * @return mixed
     */
    public function get(string $name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        throw new LogicException("Key {$name} not found");
    }
}
