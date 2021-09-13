<?php

namespace Sabberworm\CSS\Tests\CSSList;

use PHPUnit\Framework\TestCase;
use Sabberworm\CSS\Comment\Commentable;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\Renderable;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Parser;

/**
 * @covers \Sabberworm\CSS\CSSList\Document
 */
final class DocumentTest extends TestCase
{
    /**
     * @var Document
     */
    private $subject;

    protected function setUp()
    {
        $this->subject = new Document();
    }

    /**
     * @test
     */
    public function implementsRenderable()
    {
        self::assertInstanceOf(Renderable::class, $this->subject);
    }

    /**
     * @test
     */
    public function implementsCommentable()
    {
        self::assertInstanceOf(Commentable::class, $this->subject);
    }

    /**
     * @test
     */
    public function getContentsInitiallyReturnsEmptyArray()
    {
        self::assertSame([], $this->subject->getContents());
    }

    /**
     * @return array<string, array<int, array<int, DeclarationBlock>>>
     */
    public static function contentsDataProvider()
    {
        return [
            'empty array' => [[]],
            '1 item' => [[new DeclarationBlock()]],
            '2 items' => [[new DeclarationBlock(), new DeclarationBlock()]],
        ];
    }

    /**
     * @test
     *
     * @param array<int, DeclarationBlock> $contents
     *
     * @dataProvider contentsDataProvider
     */
    public function setContentsSetsContents(array $contents)
    {
        $this->subject->setContents($contents);

        self::assertSame($contents, $this->subject->getContents());
    }

    /**
     * @test
     */
    public function setContentsReplacesContentsSetInPreviousCall()
    {
        $contents2 = [new DeclarationBlock()];

        $this->subject->setContents([new DeclarationBlock()]);
        $this->subject->setContents($contents2);

        self::assertSame($contents2, $this->subject->getContents());
    }

    public function testInsertContent() {
        $sCss = '.thing { left: 10px; } .stuff { margin: 1px; } ';
        $oParser = new Parser($sCss);
        $oDoc = $oParser->parse();
        $aContents = $oDoc->getContents();
        $this->assertCount(2, $aContents);

        $oThing = $aContents[0];
        $oStuff = $aContents[1];

        $oFirst = new DeclarationBlock();
        $oFirst->setSelectors('.first');
        $oBetween = new DeclarationBlock();
        $oBetween->setSelectors('.between');
        $oOrphan = new DeclarationBlock();
        $oOrphan->setSelectors('.forever-alone');
        $oNotFound = new DeclarationBlock();
        $oNotFound->setSelectors('.not-found');

        $oDoc->insert($oFirst, $oThing);
        $oDoc->insert($oBetween, $oStuff);
        $oDoc->insert($oOrphan, $oNotFound);

        $aContents = $oDoc->getContents();
        $this->assertCount(5, $aContents);
        $this->assertSame($oFirst, $aContents[0]);
        $this->assertSame($oThing, $aContents[1]);
        $this->assertSame($oBetween, $aContents[2]);
        $this->assertSame($oStuff, $aContents[3]);
        $this->assertSame($oOrphan, $aContents[4]);
    }
}
