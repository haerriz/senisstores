<?php
namespace Haerriz\GoogleShoppingFeed\Test\Unit\Model\Writer;

use Haerriz\GoogleShoppingFeed\Model\FeedProfile;
use Haerriz\GoogleShoppingFeed\Model\Writer\JsonLines;
use Haerriz\GoogleShoppingFeed\Model\Writer\Delimited;
use Haerriz\GoogleShoppingFeed\Model\ProfileConfigReader;
use PHPUnit\Framework\TestCase;

class WriterTest extends TestCase
{
    public function testJsonLinesProducesOneDecodableObjectPerRow()
    {
        $stream = new MemoryStream();
        $writer = new JsonLines();
        $profile = $this->getMockBuilder(FeedProfile::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $writer->start($stream, $profile, ['g:id', 'g:title']);
        $writer->writeRow($stream, $profile, ['g:id' => '1', 'g:title' => 'Café']);
        $writer->writeRow($stream, $profile, ['g:id' => '2', 'g:title' => "Line\nTwo"]);
        $writer->finish($stream, $profile);

        $lines = array_values(array_filter(explode("\n", $stream->contents)));
        $this->assertCount(2, $lines);
        $this->assertSame('Café', json_decode($lines[0], true)['g:title']);
        $this->assertSame("Line\nTwo", json_decode($lines[1], true)['g:title']);
    }

    public function testDelimitedWriterRoundTripsEscapedValuesAndCrLf()
    {
        $stream = new MemoryStream();
        $profile = $this->getMockBuilder(FeedProfile::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $profile->setData('delimiter', ',');
        $profile->setData('enclosure', '"');
        $profile->setData('line_ending', 'CRLF');
        $profile->setData('encoding', 'UTF-8');
        $writer = new Delimited(new ProfileConfigReader());

        $writer->start($stream, $profile, ['id', 'title']);
        $writer->writeRow($stream, $profile, ['id' => '1', 'title' => 'A, "quoted" title']);

        $this->assertStringEndsWith("\r\n", $stream->contents);
        $lines = preg_split('/\r\n/', trim($stream->contents));
        $this->assertSame(['1', 'A, "quoted" title'], str_getcsv($lines[1]));
    }
}

class MemoryStream
{
    public $contents = '';

    public function write($value)
    {
        $this->contents .= $value;
    }
}
