<?php
declare(strict_types=1);
namespace Viserio\Component\Translation\Tests;

use Narrowspark\TestingHelper\Phpunit\MockeryTestCase;
use org\bovigo\vfs\vfsStream;
use Viserio\Component\Contract\Parser\Loader as LoaderContract;
use Viserio\Component\Contract\Translation\Exception\InvalidArgumentException;
use Viserio\Component\Contract\Translation\MessageCatalogue as MessageCatalogueContract;
use Viserio\Component\Parser\FileLoader;
use Viserio\Component\Translation\Formatter\IntlMessageFormatter;
use Viserio\Component\Translation\TranslationManager;

/**
 * @internal
 */
final class TranslationManagerTest extends MockeryTestCase
{
    /**
     * @var \Viserio\Component\Translation\TranslationManager
     */
    private $manager;

    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    private $root;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->root    = vfsStream::setup();
        $this->manager = new TranslationManager(new IntlMessageFormatter());
    }

    public function testSetAndGetDirectories(): void
    {
        $this->manager->setDirectories([
            __DIR__ . \DIRECTORY_SEPARATOR . 'stubs',
        ]);

        $this->assertSame(
            __DIR__ . \DIRECTORY_SEPARATOR . 'stubs',
            $this->manager->getDirectories()[0]
        );
    }

    public function testImportToThrowException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File [invalid.php] cant be imported. Key for language is missing.');

        vfsStream::newFile('invalid.php')->withContent("<?php
declare(strict_types=1); return [
    'domain1' => [
        'foo' => 'bar',
    ],
    'domain2' => [
        'bar' => 'foo',
    ],
];")->at($this->root);

        $this->manager->setLoader($this->getFileLoader());

        $this->manager->import('invalid.php');
    }

    public function testImport(): void
    {
        vfsStream::newFile('en.php')->withContent("<?php
declare(strict_types=1); return [
    'lang' => 'en',
    'domain1' => [
        'foo' => 'bar',
    ],
    'domain2' => [
        'bar' => 'foo',
    ],
];")->at($this->root);

        $this->manager->setLoader($this->getFileLoader());

        $this->manager->import('en.php');

        $this->assertSame('en', $this->manager->getTranslator('en')->getLocale());
        $this->assertSame('en', $this->manager->getTranslator()->getLocale());
    }

    public function testImportWithDefaultFallback(): void
    {
        vfsStream::newFile('fr.php')->withContent("<?php
declare(strict_types=1); return [
    'lang' => 'fr',
    'domain1' => [
        'foo' => 'bar',
    ],
    'domain2' => [
        'bar' => 'foo',
    ],
];")->at($this->root);

        $message = $this->mock(MessageCatalogueContract::class);
        $message
            ->shouldReceive('getLocale')
            ->once()
            ->andReturn('de');
        $message
            ->shouldReceive('setParent')
            ->once();

        $this->manager->setDefaultFallback($message);
        $this->manager->setLoader($this->getFileLoader());

        $this->manager->import('fr.php');
    }

    public function testImportWithLanguageFallback(): void
    {
        vfsStream::newFile('de.php')->withContent("<?php
declare(strict_types=1); return [
    'lang' => 'de',
    'domain1' => [
        'foo' => 'bar',
    ],
    'domain2' => [
        'bar' => 'foo',
    ],
];")->at($this->root);

        $message = $this->mock(MessageCatalogueContract::class);
        $message
            ->shouldReceive('getLocale')
            ->once()
            ->andReturn('en');
        $message
            ->shouldReceive('setParent')
            ->once();

        $this->manager->setLanguageFallback('de', $message);
        $this->manager->setLoader($this->getFileLoader());

        $this->manager->import('de.php');
    }

    public function testGetTranslator(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->manager->getTranslator('jp');
    }

    public function testSetAndGetDefaultFallback(): void
    {
        $this->manager->setDefaultFallback($this->mock(MessageCatalogueContract::class));

        $this->assertInstanceOf(MessageCatalogueContract::class, $this->manager->getDefaultFallback());
    }

    public function testSetAndLanguageFallback(): void
    {
        $this->manager->setLanguageFallback('de', $this->mock(MessageCatalogueContract::class));

        $this->assertInstanceOf(MessageCatalogueContract::class, $this->manager->getLanguageFallback('de'));
    }

    public function testSetAndGetLocale(): void
    {
        $this->manager->setLocale('de');

        $this->assertSame('de', $this->manager->getLocale());
    }

    public function testAddMessageCatalogue(): void
    {
        $message = $this->mock(MessageCatalogueContract::class);
        $message
            ->shouldReceive('getLocale')
            ->times(3)
            ->andReturn('ab');

        $this->manager->addMessageCatalogue($message);
        $this->manager->getTranslator('ab');
    }

    /**
     * @return \Viserio\Component\Contract\Parser\Loader
     */
    protected function getFileLoader(): LoaderContract
    {
        return (new FileLoader())->addDirectory($this->root->url());
    }
}
