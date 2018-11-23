<?php
namespace BitbucketReviews;

use BitbucketReviews\Checkstyle\Error;
use BitbucketReviews\Exception\LogicException;

/**
 * Конфигурация
 */
class Config
{
    /**
     * Только измененное
     */
    public const INSPECT_CHANGED = 1;

    /**
     * Использовать контекст
     */
    public const INSPECT_CONTEXT = 2;

    /**
     * Нет лимита
     */
    public const NO_LIMIT = PHP_INT_MAX;

    /**
     * @var array Конфигурация по умолчанию
     */
    protected const DEFAULT = [
        'stash'    => [
            'url'         => 'https://127.0.0.1',
            'accessToken' => '',
            'project'     => '',
            'repository'  => '',
            'debug'       => false,
        ],
        'analyzer' => [
            'inspect'       => Config::INSPECT_CHANGED,
            'ignoredText'   => [],
            'ignoredFiles'  => [],
            'limit'         => Config::NO_LIMIT,
            'limitPerFile'  => Config::NO_LIMIT,
            'limitPerGroup' => Config::NO_LIMIT,
            'group'         => true,
            'minSeverity'   => Error::SEVERITY_WARNING,
        ],
        'statsd'   => [
            'host'   => 'alahd-vm-graphite1.kolesa.dev',
            'port'   => 8125,
            'prefix' => 'krisha',
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
