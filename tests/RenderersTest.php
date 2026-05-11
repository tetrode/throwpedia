<?php

declare(strict_types=1);

namespace Tetrode\Throwpedia\Tests;

use PHPUnit\Framework\TestCase;
use Tetrode\Throwpedia\DTO\ExceptionCatalog;
use Tetrode\Throwpedia\DTO\ExceptionModelEntry;
use Tetrode\Throwpedia\Renderers;

class RenderersTest extends TestCase
{
    /** @var array<string, ExceptionModelEntry> */
    private array $model;

    protected function setUp(): void
    {
        $this->model = [
            'ERR_001' => new ExceptionModelEntry(
                business: 'User not found',
                technical: 'Database query returned empty result',
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
        $this->assertArrayHasKey('ERR_001', $decoded);
        $this->assertEquals('User not found', $decoded['ERR_001']['business']);
    }

    public function testToYaml(): void
    {
        $yaml = Renderers::toYaml(new ExceptionCatalog($this->model));
        $this->assertStringContainsString('ERR_001:', $yaml);
        $this->assertStringContainsString('business: User not found', $yaml);
        $this->assertStringContainsString('technical: Database query returned empty result', $yaml);
        $this->assertStringContainsString('exception: UserNotFoundException', $yaml);
        $this->assertStringContainsString('- UserRepository::get', $yaml);
        $this->assertStringContainsString('- UserService::find', $yaml);
    }

    public function testToMarkdown(): void
    {
        $md = Renderers::toMarkdown(new ExceptionCatalog($this->model));
        $this->assertStringContainsString('# Business Exceptions Catalog', $md);
        $this->assertStringContainsString('ERR_001', $md);
        $this->assertStringContainsString('User not found', $md);
        $this->assertStringContainsString('`UserNotFoundException`', $md);
        $this->assertStringContainsString('UserRepository::get<br>UserService::find', $md);
    }

    public function testToMarkdownWithPipes(): void
    {
        $model = [
            'ERR_PIPE' => new ExceptionModelEntry(
                business: 'Business | with pipe',
                technical: 'Technical | with pipe',
                exception: 'Ex',
                thrown_from: ['Loc'],
            ),
        ];
        $md = Renderers::toMarkdown(new ExceptionCatalog($model));
        $this->assertStringContainsString('Business \| with pipe', $md);
        $this->assertStringContainsString('Technical \| with pipe', $md);
    }

    public function testToText(): void
    {
        $text = Renderers::toText(new ExceptionCatalog($this->model));
        $this->assertStringContainsString('Business Exceptions Catalog', $text);
        $this->assertStringContainsString('[ERR_001]', $text);
        $this->assertStringContainsString('Business:  User not found', $text);
        $this->assertStringContainsString('Technical: Database query returned empty result', $text);
        $this->assertStringContainsString('Exception: UserNotFoundException', $text);
        $this->assertStringContainsString('- UserRepository::get', $text);
        $this->assertStringContainsString('- UserService::find', $text);
    }
}
