<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\Tests;

use PHPUnit\Framework\TestCase;
use Tetrode\Throwpedia\DTO\AttributeField;
use Tetrode\Throwpedia\DTO\ExceptionCatalog;
use Tetrode\Throwpedia\DTO\ExceptionModelEntry;
use Tetrode\Throwpedia\Renderers;

class RenderersTest extends TestCase
{
    /** @var array<string, ExceptionModelEntry> */
    private array $model;

    /** @var array<string, AttributeField[]> */
    private array $fields;

    protected function setUp(): void
    {
        $this->fields = [
            'ExceptionReason' => [
                new AttributeField('code', 'Code', true),
                new AttributeField('technical', 'Technical'),
                new AttributeField('business', 'Business'),
            ],
        ];
        $this->model = [
            'ExceptionReason:ERR_001' => new ExceptionModelEntry(
                attributeName: 'ExceptionReason',
                values: [
                    'code'      => 'ERR_001',
                    'business'  => 'User not found',
                    'technical' => 'Database query returned empty result',
                ],
                exception: 'UserNotFoundException',
                thrown_from: ['UserRepository::get', 'UserService::find'],
            ),
        ];
    }

    public function testToJson(): void
    {
        $json = Renderers::toJson(new ExceptionCatalog($this->model));
        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertArrayHasKey('entries', $decoded);
        $this->assertArrayHasKey('ExceptionReason:ERR_001', $decoded['entries']);
        $this->assertEquals('User not found', $decoded['entries']['ExceptionReason:ERR_001']['values']['business']);
    }

    public function testToYaml(): void
    {
        $yaml = Renderers::toYaml(new ExceptionCatalog($this->model));
        $this->assertStringContainsString('entries:', $yaml);
        $this->assertStringContainsString('ERR_001:', $yaml);
        $this->assertStringContainsString('business: User not found', $yaml);
        $this->assertStringContainsString('technical: Database query returned empty result', $yaml);
        $this->assertStringContainsString('exception: UserNotFoundException', $yaml);
        $this->assertStringContainsString('- UserRepository::get', $yaml);
        $this->assertStringContainsString('- UserService::find', $yaml);
    }

    public function testToMarkdown(): void
    {
        $md = Renderers::toMarkdown(new ExceptionCatalog($this->model), $this->fields);
        $this->assertStringContainsString('# Business Exceptions Catalog', $md);
        $this->assertStringContainsString('ERR_001', $md);
        $this->assertStringContainsString('User not found', $md);
        $this->assertStringContainsString('`UserNotFoundException`', $md);
        $this->assertStringContainsString('UserRepository::get<br>UserService::find', $md);
    }

    public function testToMarkdownWithPipes(): void
    {
        $model = [
            'ExceptionReason:ERR_PIPE' => new ExceptionModelEntry(
                attributeName: 'ExceptionReason',
                values: [
                    'code'      => 'ERR_PIPE',
                    'business'  => 'Business | with pipe',
                    'technical' => 'Technical | with pipe',
                ],
                exception: 'Ex',
                thrown_from: ['Loc'],
            ),
        ];
        $md = Renderers::toMarkdown(new ExceptionCatalog($model), $this->fields);
        $this->assertStringContainsString('Business \| with pipe', $md);
        $this->assertStringContainsString('Technical \| with pipe', $md);
    }

    public function testToText(): void
    {
        $text = Renderers::toText(new ExceptionCatalog($this->model), $this->fields);
        $this->assertStringContainsString('Business Exceptions Catalog', $text);
        $this->assertStringContainsString('[ExceptionReason]', $text);
        $this->assertStringContainsString('(ERR_001)', $text);
        $this->assertStringContainsString('Business: User not found', $text);
        $this->assertStringContainsString('Technical: Database query returned empty result', $text);
        $this->assertStringContainsString('Exception: UserNotFoundException', $text);
        $this->assertStringContainsString('- UserRepository::get', $text);
        $this->assertStringContainsString('- UserService::find', $text);
    }
}
