<?php
namespace Haerriz\GoogleShoppingFeed\Model\Writer;

use Haerriz\GoogleShoppingFeed\Api\Data\FeedProfileInterface;
use Haerriz\GoogleShoppingFeed\Api\WriterInterface;
use Magento\Store\Model\StoreManagerInterface;

class GoogleXml implements WriterInterface
{
    private $storeManager;

    public function __construct(StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
    }

    public function start($stream, FeedProfileInterface $profile, array $fields)
    {
        foreach ($fields as $field) {
            $this->validateFieldName($field);
        }
        $title = $this->cdata((string)$profile->getName());
        $link = $this->cdata($this->storeManager->getStore($profile->getStoreId())->getBaseUrl());
        $stream->write('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
        $stream->write('<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>' . "\n");
        $stream->write("<title><![CDATA[{$title}]]></title>\n<link><![CDATA[{$link}]]></link>\n");
    }

    public function writeRow($stream, FeedProfileInterface $profile, array $row)
    {
        $stream->write("<item>\n");
        foreach ($row as $field => $value) {
            $this->validateFieldName($field);
            if ($value !== null && $value !== '') {
                $value = $this->cdata((string)$value);
                $stream->write("<{$field}><![CDATA[{$value}]]></{$field}>\n");
            }
        }
        $stream->write("</item>\n");
    }

    public function finish($stream, FeedProfileInterface $profile)
    {
        $stream->write("</channel></rss>\n");
    }

    private function validateFieldName($field)
    {
        if (!preg_match('/^(?:g:)?[A-Za-z_][A-Za-z0-9_.-]*$/', (string)$field)) {
            throw new \InvalidArgumentException('Invalid XML output field name: ' . (string)$field);
        }
    }

    private function cdata($value)
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $value);
    }
}
