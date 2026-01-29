<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO\Contents;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\StringContainerHelper;
use League\CommonMark\Parser\MarkdownParser;

/**
 * markdown 格式数据
 * 使用 league/commonmark 提取纯文本与媒体附件
 */
class MarkdownContent extends Content
{

    /**
     * 内容文本（markdown 原文）
     * @var string
     */
    public string $text = '';

    /**
     * 提取纯文本（基于 league/commonmark AST，去除 markdown 语法）
     */
    public function getPlainText() : string
    {
        if ($this->text === '') {
            return '';
        }

        $document = $this->parseMarkdown();
        $parts    = [];

        foreach ($document->iterator() as $node) {
            if ($node instanceof Text) {
                $parts[] = $node->getLiteral();
            }
            if ($node instanceof Image) {
                $alt = StringContainerHelper::getChildText($node);
                if ($alt !== '') {
                    $parts[] = $alt;
                }
            }
            if ($node instanceof Link) {
                $title = StringContainerHelper::getChildText($node);
                $url   = $node->getUrl();
                $parts[] = $title !== '' ? $title . ' (' . $url . ')' : $url;
            }
        }

        $s = implode('', $parts);
        $s = preg_replace('/\n{3,}/', "\n\n", $s);
        $s = trim(preg_replace('/[ \t]+/', ' ', $s));

        return $s;
    }

    /**
     * 视频链接扩展名（用于识别 [title](url) 中的视频）
     */
    private const VIDEO_EXT_PATTERN = '/\.(mp4|webm|ogg|mov|m4v|avi|wmv|flv)(\?.*)?$/i';

    /**
     * 音频链接扩展名（用于识别 [title](url) 中的音频）
     */
    private const AUDIO_EXT_PATTERN = '/\.(mp3|wav|m4a|ogg|aac|flac|wma)(\?.*)?$/i';

    /**
     * 提取媒体附件（仅图片、视频、音频）
     *
     * @return list<array{type: string, url: string, alt?: string, title?: string}>
     */
    public function getMediaAttachments() : array
    {
        $out = [];
        if ($this->text === '') {
            return $out;
        }

        $document = $this->parseMarkdown();

        foreach ($document->iterator() as $node) {
            if ($node instanceof Image) {
                $out[] = [
                    'type' => 'image',
                    'url'  => $node->getUrl(),
                    'alt'  => StringContainerHelper::getChildText($node),
                ];
            }
            if ($node instanceof Link) {
                $url   = $node->getUrl();
                $title = StringContainerHelper::getChildText($node);
                if (preg_match(self::VIDEO_EXT_PATTERN, $url)) {
                    $out[] = [
                        'type'  => 'video',
                        'url'   => $url,
                        'title' => $title,
                    ];
                } elseif (preg_match(self::AUDIO_EXT_PATTERN, $url)) {
                    $out[] = [
                        'type'  => 'audio',
                        'url'   => $url,
                        'title' => $title,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * 使用 league/commonmark 解析为 AST Document
     */
    private function parseMarkdown() : Document
    {
        $config      = [];
        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $parser = new MarkdownParser($environment);

        return $parser->parse($this->text);
    }
}
