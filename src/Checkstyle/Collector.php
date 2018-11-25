<?php
namespace BitbucketReviews\Checkstyle;

use BitbucketReviews\Exception\CheckStyleFormatException;
use BitbucketReviews\Stock\Error;
use BitbucketReviews\Stock\Group;

/**
 * Работа с форматом checkstyle
 */
class Collector
{
    /**
     * @var \BitbucketReviews\Stock\Group[]
     */
    protected $groups = [];

    /**
     * Получает информацию из файла
     *
     * @param string $xmlFilename
     * @param string $name
     * @param string $rootPath
     * @return int
     * @throws \BitbucketReviews\Exception\CheckStyleFormatException
     */
    public function parseFile(string $xmlFilename, string $name, string $rootPath = ''): int
    {
        $xml   = simplexml_load_string(file_get_contents($xmlFilename));
        $count = 0;

        foreach ($xml->children() as $fileNode) {
            $filename = (string) $fileNode->attributes()->name;

            if ($rootPath && strpos($filename, $rootPath) === 0) {
                $filename = ltrim(mb_substr($filename, mb_strlen($rootPath)), DIRECTORY_SEPARATOR);
            }

            if (!$filename) {
                throw new CheckStyleFormatException(
                    "Имя файла не задано в {$xmlFilename} для <{$fileNode->getName()}>"
                );
            }

            $file = new Group($filename, $name, []);

            if (!$fileNode->error) {
                continue;
            }

            foreach ($fileNode->error as $error) {
                $attr  = $error->attributes();
                $error = new Error(
                    (int) $attr->line,
                    (int) $attr->column,
                    (string) $attr->severity,
                    (string) $attr->message,
                    (string) $attr->source
                );

                $file->addError($error);
                $count ++;
            }

            $this->groups[] = $file;
        }

        return $count;
    }

    /**
     * @see Collector::$groups
     * @return \BitbucketReviews\Stock\Group[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Получает файл с ошибками
     *
     * @param string $filename
     * @return \BitbucketReviews\Stock\Group|null
     */
    public function getGroup(string $filename)
    {
        $files = array_filter($this->groups, function (Group $file) use ($filename) {
            return $file->getName() === $filename && $file->getCount() > 0;
        });

        return array_pop($files);
    }
}
