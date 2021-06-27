<?php

namespace Sabberworm\CSS\Tests;

use PHPUnit\Framework\TestCase;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\CSSList\KeyFrame;
use Sabberworm\CSS\Parser;
use Sabberworm\CSS\Parsing\UnexpectedTokenException;
use Sabberworm\CSS\Property\AtRule;
use Sabberworm\CSS\Property\Charset;
use Sabberworm\CSS\Property\CSSNamespace;
use Sabberworm\CSS\Property\Import;
use Sabberworm\CSS\Property\Selector;
use Sabberworm\CSS\RuleSet\AtRuleSet;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Settings;
use Sabberworm\CSS\Value\Color;
use Sabberworm\CSS\Value\Size;
use Sabberworm\CSS\Value\URL;

/**
 * @covers \Sabberworm\CSS\Parser
 * @covers \Sabberworm\CSS\CSSList\Document::parse
 * @covers \Sabberworm\CSS\Rule\Rule::parse
 * @covers \Sabberworm\CSS\RuleSet\DeclarationBlock::parse
 * @covers \Sabberworm\CSS\Value\CalcFunction::parse
 * @covers \Sabberworm\CSS\Value\Color::parse
 * @covers \Sabberworm\CSS\Value\CSSString::parse
 * @covers \Sabberworm\CSS\Value\LineName::parse
 * @covers \Sabberworm\CSS\Value\Size::parse
 * @covers \Sabberworm\CSS\Value\URL::parse
 */
class ParserTest extends TestCase
{
    public function testFiles()
    {
        $sDirectory = __DIR__ . '/fixtures';
        if ($rHandle = opendir($sDirectory)) {
            /* This is the correct way to loop over the directory. */
            while (false !== ($sFileName = readdir($rHandle))) {
                if (strpos($sFileName, '.') === 0) {
                    continue;
                }
                if (strrpos($sFileName, '.css') !== strlen($sFileName) - strlen('.css')) {
                    continue;
                }
                if (strpos($sFileName, '-') === 0) {
                    // Either a file which SHOULD fail (at least in strict mode)
                    // or a future test of a as-of-now missing feature
                    continue;
                }
                $oParser = new Parser(file_get_contents($sDirectory . '/' . $sFileName));
                try {
                    $this->assertNotEquals('', $oParser->parse()->render());
                } catch (\Exception $e) {
                    $this->fail($e);
                }
            }
            closedir($rHandle);
        }
    }

    /**
     * @depends testFiles
     */
    public function testColorParsing()
    {
        $oDoc = $this->parsedStructureForFile('colortest');
        foreach ($oDoc->getAllRuleSets() as $oRuleSet) {
            if (!$oRuleSet instanceof DeclarationBlock) {
                continue;
            }
            $sSelector = $oRuleSet->getSelectors();
            $sSelector = $sSelector[0]->getSelector();
            if ($sSelector === '#mine') {
                $aColorRule = $oRuleSet->getRules('color');
                $oColor = $aColorRule[0]->getValue();
                $this->assertSame('red', $oColor);
                $aColorRule = $oRuleSet->getRules('background-');
                $oColor = $aColorRule[0]->getValue();
                $this->assertEquals([
                    'r' => new Size(35.0, null, true, $oColor->getLineNo()),
                    'g' => new Size(35.0, null, true, $oColor->getLineNo()),
                    'b' => new Size(35.0, null, true, $oColor->getLineNo()),
                ], $oColor->getColor());
                $aColorRule = $oRuleSet->getRules('border-color');
                $oColor = $aColorRule[0]->getValue();
                $this->assertEquals([
                    'r' => new Size(10.0, null, true, $oColor->getLineNo()),
                    'g' => new Size(100.0, null, true, $oColor->getLineNo()),
                    'b' => new Size(230.0, null, true, $oColor->getLineNo()),
                ], $oColor->getColor());
                $oColor = $aColorRule[1]->getValue();
                $this->assertEquals([
                    'r' => new Size(10.0, null, true, $oColor->getLineNo()),
                    'g' => new Size(100.0, null, true, $oColor->getLineNo()),
                    'b' => new Size(231.0, null, true, $oColor->getLineNo()),
                    'a' => new Size("0000.3", null, true, $oColor->getLineNo()),
                ], $oColor->getColor());
                $aColorRule = $oRuleSet->getRules('outline-color');
                $oColor = $aColorRule[0]->getValue();
                $this->assertEquals([
                    'r' => new Size(34.0, null, true, $oColor->getLineNo()),
                    'g' => new Size(34.0, null, true, $oColor->getLineNo()),
                    'b' => new Size(34.0, null, true, $oColor->getLineNo()),
                ], $oColor->getColor());
            } elseif ($sSelector === '#yours') {
                $aColorRule = $oRuleSet->getRules('background-color');
                $oColor = $aColorRule[0]->getValue();
                $this->assertEquals([
                    'h' => new Size(220.0, null, true, $oColor->getLineNo()),
                    's' => new Size(10.0, '%', true, $oColor->getLineNo()),
                    'l' => new Size(220.0, '%', true, $oColor->getLineNo()),
                ], $oColor->getColor());
                $oColor = $aColorRule[1]->getValue();
                $this->assertEquals([
                    'h' => new Size(220.0, null, true, $oColor->getLineNo()),
                    's' => new Size(10.0, '%', true, $oColor->getLineNo()),
                    'l' => new Size(220.0, '%', true, $oColor->getLineNo()),
                    'a' => new Size(0000.3, null, true, $oColor->getLineNo()),
                ], $oColor->getColor());
            }
        }
        foreach ($oDoc->getAllValues('color') as $sColor) {
            $this->assertSame('red', $sColor);
        }
        $this->assertSame(
            '#mine {color: red;border-color: #0a64e6;border-color: rgba(10,100,231,.3);outline-color: #222;'
            . 'background-color: #232323;}'
            . "\n"
            . '#yours {background-color: hsl(220,10%,220%);background-color: hsla(220,10%,220%,.3);}'
            . "\n"
            . '#variables {background-color: rgb(var(--some-rgb));background-color: rgb(var(--r),var(--g),var(--b));'
            . 'background-color: rgb(255,var(--g),var(--b));background-color: rgb(255,255,var(--b));'
            . 'background-color: rgb(255,var(--rg));background-color: hsl(var(--some-hsl));}'
            . "\n"
            . '#variables-alpha {background-color: rgba(var(--some-rgb),.1);'
            . 'background-color: rgba(var(--some-rg),255,.1);background-color: hsla(var(--some-hsl),.1);}',
            $oDoc->render()
        );
    }

    public function testUnicodeParsing()
    {
        $oDoc = $this->parsedStructureForFile('unicode');
        foreach ($oDoc->getAllDeclarationBlocks() as $oRuleSet) {
            $sSelector = $oRuleSet->getSelectors();
            $sSelector = $sSelector[0]->getSelector();
            if (substr($sSelector, 0, strlen('.test-')) !== '.test-') {
                continue;
            }
            $aContentRules = $oRuleSet->getRules('content');
            $aContents = $aContentRules[0]->getValues();
            $sString = $aContents[0][0]->__toString();
            if ($sSelector == '.test-1') {
                $this->assertSame('" "', $sString);
            }
            if ($sSelector == '.test-2') {
                $this->assertSame('"é"', $sString);
            }
            if ($sSelector == '.test-3') {
                $this->assertSame('" "', $sString);
            }
            if ($sSelector == '.test-4') {
                $this->assertSame('"𝄞"', $sString);
            }
            if ($sSelector == '.test-5') {
                $this->assertSame('"水"', $sString);
            }
            if ($sSelector == '.test-6') {
                $this->assertSame('"¥"', $sString);
            }
            if ($sSelector == '.test-7') {
                $this->assertSame('"\A"', $sString);
            }
            if ($sSelector == '.test-8') {
                $this->assertSame('"\"\""', $sString);
            }
            if ($sSelector == '.test-9') {
                $this->assertSame('"\"\\\'"', $sString);
            }
            if ($sSelector == '.test-10') {
                $this->assertSame('"\\\'\\\\"', $sString);
            }
            if ($sSelector == '.test-11') {
                $this->assertSame('"test"', $sString);
            }
        }
    }

    public function testUnicodeRangeParsing()
    {
        $oDoc = $this->parsedStructureForFile('unicode-range');
        $sExpected = "@font-face {unicode-range: U+0100-024F,U+0259,U+1E??-2EFF,U+202F;}";
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testSpecificity()
    {
        $oDoc = $this->parsedStructureForFile('specificity');
        $oDeclarationBlock = $oDoc->getAllDeclarationBlocks();
        $oDeclarationBlock = $oDeclarationBlock[0];
        $aSelectors = $oDeclarationBlock->getSelectors();
        foreach ($aSelectors as $oSelector) {
            switch ($oSelector->getSelector()) {
                case "#test .help":
                    $this->assertSame(110, $oSelector->getSpecificity());
                    break;
                case "#file":
                    $this->assertSame(100, $oSelector->getSpecificity());
                    break;
                case ".help:hover":
                    $this->assertSame(20, $oSelector->getSpecificity());
                    break;
                case "ol li::before":
                    $this->assertSame(3, $oSelector->getSpecificity());
                    break;
                case "li.green":
                    $this->assertSame(11, $oSelector->getSpecificity());
                    break;
                default:
                    $this->fail("specificity: untested selector " . $oSelector->getSelector());
            }
        }
        $this->assertEquals([new Selector('#test .help', true)], $oDoc->getSelectorsBySpecificity('> 100'));
        $this->assertEquals(
            [new Selector('#test .help', true), new Selector('#file', true)],
            $oDoc->getSelectorsBySpecificity('>= 100')
        );
        $this->assertEquals([new Selector('#file', true)], $oDoc->getSelectorsBySpecificity('=== 100'));
        $this->assertEquals([new Selector('#file', true)], $oDoc->getSelectorsBySpecificity('== 100'));
        $this->assertEquals([
            new Selector('#file', true),
            new Selector('.help:hover', true),
            new Selector('li.green', true),
            new Selector('ol li::before', true),
        ], $oDoc->getSelectorsBySpecificity('<= 100'));
        $this->assertEquals([
            new Selector('.help:hover', true),
            new Selector('li.green', true),
            new Selector('ol li::before', true),
        ], $oDoc->getSelectorsBySpecificity('< 100'));
        $this->assertEquals([new Selector('li.green', true)], $oDoc->getSelectorsBySpecificity('11'));
        $this->assertEquals([new Selector('ol li::before', true)], $oDoc->getSelectorsBySpecificity(3));
    }

    public function testManipulation()
    {
        $oDoc = $this->parsedStructureForFile('atrules');
        $this->assertSame(
            '@charset "utf-8";'
            . "\n"
            . '@font-face {font-family: "CrassRoots";src: url("../media/cr.ttf");}'
            . "\n"
            . 'html, body {font-size: -.6em;}'
            . "\n"
            . '@keyframes mymove {from {top: 0px;}'
            . "\n\t"
            . 'to {top: 200px;}}'
            . "\n"
            . '@-moz-keyframes some-move {from {top: 0px;}'
            . "\n\t"
            . 'to {top: 200px;}}'
            . "\n"
            . '@supports ( (perspective: 10px) or (-moz-perspective: 10px) or (-webkit-perspective: 10px) or '
            . '(-ms-perspective: 10px) or (-o-perspective: 10px) ) {body {font-family: "Helvetica";}}'
            . "\n"
            . '@page :pseudo-class {margin: 2in;}'
            . "\n"
            . '@-moz-document url(https://www.w3.org/),'
            . "\n"
            . '               url-prefix(https://www.w3.org/Style/),'
            . "\n"
            . '               domain(mozilla.org),'
            . "\n"
            . '               regexp("https:.*") {body {color: purple;background: yellow;}}'
            . "\n"
            . '@media screen and (orientation: landscape) {@-ms-viewport {width: 1024px;height: 768px;}}'
            . "\n"
            . '@region-style #intro {p {color: blue;}}',
            $oDoc->render()
        );
        foreach ($oDoc->getAllDeclarationBlocks() as $oBlock) {
            foreach ($oBlock->getSelectors() as $oSelector) {
                //Loop over all selector parts (the comma-separated strings in a selector) and prepend the id
                $oSelector->setSelector('#my_id ' . $oSelector->getSelector());
            }
        }
        $this->assertSame(
            '@charset "utf-8";'
            . "\n"
            . '@font-face {font-family: "CrassRoots";src: url("../media/cr.ttf");}'
            . "\n"
            . '#my_id html, #my_id body {font-size: -.6em;}'
            . "\n"
            . '@keyframes mymove {from {top: 0px;}'
            . "\n\t"
            . 'to {top: 200px;}}'
            . "\n"
            . '@-moz-keyframes some-move {from {top: 0px;}'
            . "\n\t"
            . 'to {top: 200px;}}'
            . "\n"
            . '@supports ( (perspective: 10px) or (-moz-perspective: 10px) or (-webkit-perspective: 10px) '
            . 'or (-ms-perspective: 10px) or (-o-perspective: 10px) ) {#my_id body {font-family: "Helvetica";}}'
            . "\n"
            . '@page :pseudo-class {margin: 2in;}'
            . "\n"
            . '@-moz-document url(https://www.w3.org/),'
            . "\n"
            . '               url-prefix(https://www.w3.org/Style/),'
            . "\n"
            . '               domain(mozilla.org),'
            . "\n"
            . '               regexp("https:.*") {#my_id body {color: purple;background: yellow;}}'
            . "\n"
            . '@media screen and (orientation: landscape) {@-ms-viewport {width: 1024px;height: 768px;}}'
            . "\n"
            . '@region-style #intro {#my_id p {color: blue;}}',
            $oDoc->render()
        );

        $oDoc = $this->parsedStructureForFile('values');
        $this->assertSame(
            '#header {margin: 10px 2em 1cm 2%;font-family: Verdana,Helvetica,"Gill Sans",sans-serif;'
            . 'font-size: 10px;color: red !important;background-color: green;'
            . 'background-color: rgba(0,128,0,.7);frequency: 30Hz;}
body {color: green;font: 75% "Lucida Grande","Trebuchet MS",Verdana,sans-serif;}',
            $oDoc->render()
        );
        foreach ($oDoc->getAllRuleSets() as $oRuleSet) {
            $oRuleSet->removeRule('font-');
        }
        $this->assertSame(
            '#header {margin: 10px 2em 1cm 2%;color: red !important;background-color: green;'
            . 'background-color: rgba(0,128,0,.7);frequency: 30Hz;}
body {color: green;}',
            $oDoc->render()
        );
        foreach ($oDoc->getAllRuleSets() as $oRuleSet) {
            $oRuleSet->removeRule('background-');
        }
        $this->assertSame(
            '#header {margin: 10px 2em 1cm 2%;color: red !important;frequency: 30Hz;}
body {color: green;}',
            $oDoc->render()
        );
    }

    public function testRuleGetters()
    {
        $oDoc = $this->parsedStructureForFile('values');
        $aBlocks = $oDoc->getAllDeclarationBlocks();
        $oHeaderBlock = $aBlocks[0];
        $oBodyBlock = $aBlocks[1];
        $aHeaderRules = $oHeaderBlock->getRules('background-');
        $this->assertCount(2, $aHeaderRules);
        $this->assertSame('background-color', $aHeaderRules[0]->getRule());
        $this->assertSame('background-color', $aHeaderRules[1]->getRule());
        $aHeaderRules = $oHeaderBlock->getRulesAssoc('background-');
        $this->assertCount(1, $aHeaderRules);
        $this->assertTrue($aHeaderRules['background-color']->getValue() instanceof Color);
        $this->assertSame('rgba', $aHeaderRules['background-color']->getValue()->getColorDescription());
        $oHeaderBlock->removeRule($aHeaderRules['background-color']);
        $aHeaderRules = $oHeaderBlock->getRules('background-');
        $this->assertCount(1, $aHeaderRules);
        $this->assertSame('green', $aHeaderRules[0]->getValue());
    }

    public function testSlashedValues()
    {
        $oDoc = $this->parsedStructureForFile('slashed');
        $this->assertSame(
            '.test {font: 12px/1.5 Verdana,Arial,sans-serif;border-radius: 5px 10px 5px 10px/10px 5px 10px 5px;}',
            $oDoc->render()
        );
        foreach ($oDoc->getAllValues(null) as $mValue) {
            if ($mValue instanceof Size && $mValue->isSize() && !$mValue->isRelative()) {
                $mValue->setSize($mValue->getSize() * 3);
            }
        }
        foreach ($oDoc->getAllDeclarationBlocks() as $oBlock) {
            $oRule = $oBlock->getRules('font');
            $oRule = $oRule[0];
            $oSpaceList = $oRule->getValue();
            $this->assertEquals(' ', $oSpaceList->getListSeparator());
            $oSlashList = $oSpaceList->getListComponents();
            $oCommaList = $oSlashList[1];
            $oSlashList = $oSlashList[0];
            $this->assertEquals(',', $oCommaList->getListSeparator());
            $this->assertEquals('/', $oSlashList->getListSeparator());
            $oRule = $oBlock->getRules('border-radius');
            $oRule = $oRule[0];
            $oSlashList = $oRule->getValue();
            $this->assertEquals('/', $oSlashList->getListSeparator());
            $oSpaceList1 = $oSlashList->getListComponents();
            $oSpaceList2 = $oSpaceList1[1];
            $oSpaceList1 = $oSpaceList1[0];
            $this->assertEquals(' ', $oSpaceList1->getListSeparator());
            $this->assertEquals(' ', $oSpaceList2->getListSeparator());
        }
        $this->assertSame(
            '.test {font: 36px/1.5 Verdana,Arial,sans-serif;border-radius: 15px 30px 15px 30px/30px 15px 30px 15px;}',
            $oDoc->render()
        );
    }

    public function testFunctionSyntax()
    {
        $oDoc = $this->parsedStructureForFile('functions');
        $sExpected = 'div.main {background-image: linear-gradient(#000,#fff);}'
            . "\n"
            . '.collapser::before, .collapser::-moz-before, .collapser::-webkit-before {content: "»";font-size: 1.2em;'
            . 'margin-right: .2em;-moz-transition-property: -moz-transform;-moz-transition-duration: .2s;'
            . '-moz-transform-origin: center 60%;}'
            . "\n"
            . '.collapser.expanded::before, .collapser.expanded::-moz-before,'
            . ' .collapser.expanded::-webkit-before {-moz-transform: rotate(90deg);}'
            . "\n"
            . '.collapser + * {height: 0;overflow: hidden;-moz-transition-property: height;'
            . '-moz-transition-duration: .3s;}'
            . "\n"
            . '.collapser.expanded + * {height: auto;}';
        $this->assertSame($sExpected, $oDoc->render());

        foreach ($oDoc->getAllValues(null, true) as $mValue) {
            if ($mValue instanceof Size && $mValue->isSize()) {
                $mValue->setSize($mValue->getSize() * 3);
            }
        }
        $sExpected = str_replace(['1.2em', '.2em', '60%'], ['3.6em', '.6em', '180%'], $sExpected);
        $this->assertSame($sExpected, $oDoc->render());

        foreach ($oDoc->getAllValues(null, true) as $mValue) {
            if ($mValue instanceof Size && !$mValue->isRelative() && !$mValue->isColorComponent()) {
                $mValue->setSize($mValue->getSize() * 2);
            }
        }
        $sExpected = str_replace(['.2s', '.3s', '90deg'], ['.4s', '.6s', '180deg'], $sExpected);
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testExpandShorthands()
    {
        $oDoc = $this->parsedStructureForFile('expand-shorthands');
        $sExpected = 'body {font: italic 500 14px/1.618 "Trebuchet MS",Georgia,serif;border: 2px solid #f0f;'
            . 'background: #ccc url("/images/foo.png") no-repeat left top;margin: 1em !important;'
            . 'padding: 2px 6px 3px;}';
        $this->assertSame($sExpected, $oDoc->render());
        $oDoc->expandShorthands();
        $sExpected = 'body {margin-top: 1em !important;margin-right: 1em !important;margin-bottom: 1em !important;'
            . 'margin-left: 1em !important;padding-top: 2px;padding-right: 6px;padding-bottom: 3px;'
            . 'padding-left: 6px;border-top-color: #f0f;border-right-color: #f0f;border-bottom-color: #f0f;'
            . 'border-left-color: #f0f;border-top-style: solid;border-right-style: solid;'
            . 'border-bottom-style: solid;border-left-style: solid;border-top-width: 2px;'
            . 'border-right-width: 2px;border-bottom-width: 2px;border-left-width: 2px;font-style: italic;'
            . 'font-variant: normal;font-weight: 500;font-size: 14px;line-height: 1.618;'
            . 'font-family: "Trebuchet MS",Georgia,serif;background-color: #ccc;'
            . 'background-image: url("/images/foo.png");background-repeat: no-repeat;background-attachment: scroll;'
            . 'background-position: left top;}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testCreateShorthands()
    {
        $oDoc = $this->parsedStructureForFile('create-shorthands');
        $sExpected = 'body {font-size: 2em;font-family: Helvetica,Arial,sans-serif;font-weight: bold;'
            . 'border-width: 2px;border-color: #999;border-style: dotted;background-color: #fff;'
            . 'background-image: url("foobar.png");background-repeat: repeat-y;margin-top: 2px;margin-right: 3px;'
            . 'margin-bottom: 4px;margin-left: 5px;}';
        $this->assertSame($sExpected, $oDoc->render());
        $oDoc->createShorthands();
        $sExpected = 'body {background: #fff url("foobar.png") repeat-y;margin: 2px 5px 4px 3px;'
            . 'border: 2px dotted #999;font: bold 2em Helvetica,Arial,sans-serif;}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testNamespaces()
    {
        $oDoc = $this->parsedStructureForFile('namespaces');
        $sExpected = '@namespace toto "http://toto.example.org";
@namespace "http://example.com/foo";
@namespace foo url("http://www.example.com/");
@namespace foo url("http://www.example.com/");
foo|test {gaga: 1;}
|test {gaga: 2;}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testInnerColors()
    {
        $oDoc = $this->parsedStructureForFile('inner-color');
        $sExpected = 'test {background: -webkit-gradient(linear,0 0,0 bottom,from(#006cad),to(hsl(202,100%,49%)));}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testPrefixedGradient()
    {
        $oDoc = $this->parsedStructureForFile('webkit');
        $sExpected = '.test {background: -webkit-linear-gradient(top right,white,black);}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testListValueRemoval()
    {
        $oDoc = $this->parsedStructureForFile('atrules');
        foreach ($oDoc->getContents() as $oItem) {
            if ($oItem instanceof AtRule) {
                $oDoc->remove($oItem);
                continue;
            }
        }
        $this->assertSame('html, body {font-size: -.6em;}', $oDoc->render());

        $oDoc = $this->parsedStructureForFile('nested');
        foreach ($oDoc->getAllDeclarationBlocks() as $oBlock) {
            $oDoc->removeDeclarationBlockBySelector($oBlock, false);
            break;
        }
        $this->assertSame(
            'html {some-other: -test(val1);}
@media screen {html {some: -test(val2);}}
#unrelated {other: yes;}',
            $oDoc->render()
        );

        $oDoc = $this->parsedStructureForFile('nested');
        foreach ($oDoc->getAllDeclarationBlocks() as $oBlock) {
            $oDoc->removeDeclarationBlockBySelector($oBlock, true);
            break;
        }
        $this->assertSame(
            '@media screen {html {some: -test(val2);}}
#unrelated {other: yes;}',
            $oDoc->render()
        );
    }

    /**
     * @expectedException \Sabberworm\CSS\Parsing\OutputException
     */
    public function testSelectorRemoval()
    {
        $oDoc = $this->parsedStructureForFile('1readme');
        $aBlocks = $oDoc->getAllDeclarationBlocks();
        $oBlock1 = $aBlocks[0];
        $this->assertTrue($oBlock1->removeSelector('html'));
        $sExpected = '@charset "utf-8";
@font-face {font-family: "CrassRoots";src: url("../media/cr.ttf");}
body {font-size: 1.6em;}';
        $this->assertSame($sExpected, $oDoc->render());
        $this->assertFalse($oBlock1->removeSelector('html'));
        $this->assertTrue($oBlock1->removeSelector('body'));
        // This tries to output a declaration block without a selector and throws.
        $oDoc->render();
    }

    public function testComments()
    {
        $oDoc = $this->parsedStructureForFile('comments');
        $sExpected = '@import url("some/url.css") screen;
.foo, #bar {background-color: #000;}
@media screen {#foo.bar {position: absolute;}}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testUrlInFile()
    {
        $oDoc = $this->parsedStructureForFile('url', Settings::create()->withMultibyteSupport(true));
        $sExpected = 'body {background: #fff url("https://somesite.com/images/someimage.gif") repeat top center;}
body {background-url: url("https://somesite.com/images/someimage.gif");}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testHexAlphaInFile()
    {
        $oDoc = $this->parsedStructureForFile('hex-alpha', Settings::create()->withMultibyteSupport(true));
        $sExpected = 'div {background: rgba(17,34,51,.27);}
div {background: rgba(17,34,51,.27);}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testCalcInFile()
    {
        $oDoc = $this->parsedStructureForFile('calc', Settings::create()->withMultibyteSupport(true));
        $sExpected = 'div {width: calc(100% / 4);}
div {margin-top: calc(-120% - 4px);}
div {height: -webkit-calc(9 / 16 * 100%) !important;width: -moz-calc(( 50px - 50% ) * 2);}
div {width: calc(50% - ( ( 4% ) * .5 ));}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testCalcNestedInFile()
    {
        $oDoc = $this->parsedStructureForFile('calc-nested', Settings::create()->withMultibyteSupport(true));
        $sExpected = '.test {font-size: calc(( 3 * 4px ) + -2px);top: calc(200px - calc(20 * 3px));}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testGridLineNameInFile()
    {
        $oDoc = $this->parsedStructureForFile('grid-linename', Settings::create()->withMultibyteSupport(true));
        $sExpected = "div {grid-template-columns: [linename] 100px;}\n"
            . "span {grid-template-columns: [linename1 linename2] 100px;}";
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testEmptyGridLineNameLenientInFile()
    {
        $oDoc = $this->parsedStructureForFile('empty-grid-linename');
        $sExpected = '.test {grid-template-columns: [] 100px;}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testInvalidGridLineNameInFile()
    {
        $oDoc = $this->parsedStructureForFile('invalid-grid-linename', Settings::create()->withMultibyteSupport(true));
        $sExpected = "div {}";
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testUnmatchedBracesInFile()
    {
        $oDoc = $this->parsedStructureForFile('unmatched_braces', Settings::create()->withMultibyteSupport(true));
        $sExpected = 'button, input, checkbox, textarea {outline: 0;margin: 0;}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testInvalidSelectorsInFile()
    {
        $oDoc = $this->parsedStructureForFile('invalid-selectors', Settings::create()->withMultibyteSupport(true));
        $sExpected = '@keyframes mymove {from {top: 0px;}}
#test {color: white;background: green;}
#test {display: block;background: white;color: black;}';
        $this->assertSame($sExpected, $oDoc->render());

        $oDoc = $this->parsedStructureForFile('invalid-selectors-2', Settings::create()->withMultibyteSupport(true));
        $sExpected = '@media only screen and (max-width: 1215px) {.breadcrumb {padding-left: 10px;}
	.super-menu > li:first-of-type {border-left-width: 0;}
	.super-menu > li:last-of-type {border-right-width: 0;}
	html[dir="rtl"] .super-menu > li:first-of-type {border-left-width: 1px;border-right-width: 0;}
	html[dir="rtl"] .super-menu > li:last-of-type {border-left-width: 0;}}
body {background-color: red;}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testSelectorEscapesInFile()
    {
        $oDoc = $this->parsedStructureForFile('selector-escapes', Settings::create()->withMultibyteSupport(true));
        $sExpected = '#\# {color: red;}
.col-sm-1\/5 {width: 20%;}';
        $this->assertSame($sExpected, $oDoc->render());

        $oDoc = $this->parsedStructureForFile('invalid-selectors-2', Settings::create()->withMultibyteSupport(true));
        $sExpected = '@media only screen and (max-width: 1215px) {.breadcrumb {padding-left: 10px;}
	.super-menu > li:first-of-type {border-left-width: 0;}
	.super-menu > li:last-of-type {border-right-width: 0;}
	html[dir="rtl"] .super-menu > li:first-of-type {border-left-width: 1px;border-right-width: 0;}
	html[dir="rtl"] .super-menu > li:last-of-type {border-left-width: 0;}}
body {background-color: red;}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testIdentifierEscapesInFile()
    {
        $oDoc = $this->parsedStructureForFile('identifier-escapes', Settings::create()->withMultibyteSupport(true));
        $sExpected = 'div {font: 14px Font Awesome\ 5 Pro;font: 14px Font Awesome\} 5 Pro;'
            . 'font: 14px Font Awesome\; 5 Pro;f\;ont: 14px Font Awesome\; 5 Pro;}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testSelectorIgnoresInFile()
    {
        $oDoc = $this->parsedStructureForFile('selector-ignores', Settings::create()->withMultibyteSupport(true));
        $sExpected = '.some[selectors-may=\'contain-a-{\'] {}'
            . "\n"
            . '.this-selector  .valid {width: 100px;}'
            . "\n"
            . '@media only screen and (min-width: 200px) {.test {prop: val;}}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testKeyframeSelectors()
    {
        $oDoc = $this->parsedStructureForFile(
            'keyframe-selector-validation',
            Settings::create()->withMultibyteSupport(true)
        );
        $sExpected = '@-webkit-keyframes zoom {0% {-webkit-transform: scale(1,1);}'
            . "\n\t"
            . '50% {-webkit-transform: scale(1.2,1.2);}'
            . "\n\t"
            . '100% {-webkit-transform: scale(1,1);}}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    /**
     * @expectedException \Sabberworm\CSS\Parsing\UnexpectedTokenException
     */
    public function testLineNameFailure()
    {
        $this->parsedStructureForFile('-empty-grid-linename', Settings::create()->withLenientParsing(false));
    }

    /**
     * @expectedException \Sabberworm\CSS\Parsing\UnexpectedTokenException
     */
    public function testCalcFailure()
    {
        $this->parsedStructureForFile('-calc-no-space-around-minus', Settings::create()->withLenientParsing(false));
    }

    public function testUrlInFileMbOff()
    {
        $oDoc = $this->parsedStructureForFile('url', Settings::create()->withMultibyteSupport(false));
        $sExpected = 'body {background: #fff url("https://somesite.com/images/someimage.gif") repeat top center;}'
            . "\n"
            . 'body {background-url: url("https://somesite.com/images/someimage.gif");}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testEmptyFile()
    {
        $oDoc = $this->parsedStructureForFile('-empty', Settings::create()->withMultibyteSupport(true));
        $sExpected = '';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testEmptyFileMbOff()
    {
        $oDoc = $this->parsedStructureForFile('-empty', Settings::create()->withMultibyteSupport(false));
        $sExpected = '';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testCharsetLenient1()
    {
        $oDoc = $this->parsedStructureForFile('-charset-after-rule', Settings::create()->withLenientParsing(true));
        $sExpected = '#id {prop: var(--val);}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testCharsetLenient2()
    {
        $oDoc = $this->parsedStructureForFile('-charset-in-block', Settings::create()->withLenientParsing(true));
        $sExpected = '@media print {}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testTrailingWhitespace()
    {
        $oDoc = $this->parsedStructureForFile('trailing-whitespace', Settings::create()->withLenientParsing(false));
        $sExpected = 'div {width: 200px;}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    /**
     * @expectedException \Sabberworm\CSS\Parsing\UnexpectedTokenException
     */
    public function testCharsetFailure1()
    {
        $this->parsedStructureForFile('-charset-after-rule', Settings::create()->withLenientParsing(false));
    }

    /**
     * @expectedException \Sabberworm\CSS\Parsing\UnexpectedTokenException
     */
    public function testCharsetFailure2()
    {
        $this->parsedStructureForFile('-charset-in-block', Settings::create()->withLenientParsing(false));
    }

    /**
     * @expectedException \Sabberworm\CSS\Parsing\SourceException
     */
    public function testUnopenedClosingBracketFailure()
    {
        $this->parsedStructureForFile('-unopened-close-brackets', Settings::create()->withLenientParsing(false));
    }

    /**
     * Ensure that a missing property value raises an exception.
     *
     * @expectedException \Sabberworm\CSS\Parsing\UnexpectedTokenException
     * @covers \Sabberworm\CSS\Value\Value::parseValue()
     */
    public function testMissingPropertyValueStrict()
    {
        $this->parsedStructureForFile('missing-property-value', Settings::create()->withLenientParsing(false));
    }

    /**
     * Ensure that a missing property value is ignored when in lenient parsing mode.
     *
     * @covers \Sabberworm\CSS\Value\Value::parseValue()
     */
    public function testMissingPropertyValueLenient()
    {
        $parsed = $this->parsedStructureForFile('missing-property-value', Settings::create()->withLenientParsing(true));
        $rulesets = $parsed->getAllRuleSets();
        $this->assertCount(1, $rulesets);
        $block = $rulesets[0];
        $this->assertTrue($block instanceof DeclarationBlock);
        $this->assertEquals(['div'], $block->getSelectors());
        $rules = $block->getRules();
        $this->assertCount(1, $rules);
        $rule = $rules[0];
        $this->assertEquals('display', $rule->getRule());
        $this->assertEquals('inline-block', $rule->getValue());
    }

    /**
     * Parse structure for file.
     *
     * @param string $sFileName Filename.
     * @param Settings|null $oSettings Settings.
     *
     * @return Document Parsed document.
     */
    private function parsedStructureForFile($sFileName, $oSettings = null)
    {
        $sFile = __DIR__ . "/fixtures/$sFileName.css";
        $oParser = new Parser(file_get_contents($sFile), $oSettings);
        return $oParser->parse();
    }

    /**
     * @depends testFiles
     */
    public function testLineNumbersParsing()
    {
        $oDoc = $this->parsedStructureForFile('line-numbers');
        // array key is the expected line number
        $aExpected = [
            1 => [Charset::class],
            3 => [CSSNamespace::class],
            5 => [AtRuleSet::class],
            11 => [DeclarationBlock::class],
            // Line Numbers of the inner declaration blocks
            17 => [KeyFrame::class, 18, 20],
            23 => [Import::class],
            25 => [DeclarationBlock::class],
        ];

        $aActual = [];
        foreach ($oDoc->getContents() as $oContent) {
            $aActual[$oContent->getLineNo()] = [get_class($oContent)];
            if ($oContent instanceof KeyFrame) {
                foreach ($oContent->getContents() as $block) {
                    $aActual[$oContent->getLineNo()][] = $block->getLineNo();
                }
            }
        }

        $aUrlExpected = [7, 26]; // expected line numbers
        $aUrlActual = [];
        foreach ($oDoc->getAllValues() as $oValue) {
            if ($oValue instanceof URL) {
                $aUrlActual[] = $oValue->getLineNo();
            }
        }

        // Checking for the multiline color rule lines 27-31
        $aExpectedColorLines = [28, 29, 30];
        $aDeclBlocks = $oDoc->getAllDeclarationBlocks();
        // Choose the 2nd one
        $oDeclBlock = $aDeclBlocks[1];
        $aRules = $oDeclBlock->getRules();
        // Choose the 2nd one
        $oColor = $aRules[1]->getValue();
        $this->assertEquals(27, $aRules[1]->getLineNo());

        $aActualColorLines = [];
        foreach ($oColor->getColor() as $oSize) {
            $aActualColorLines[] = $oSize->getLineNo();
        }

        $this->assertEquals($aExpectedColorLines, $aActualColorLines);
        $this->assertEquals($aUrlExpected, $aUrlActual);
        $this->assertEquals($aExpected, $aActual);
    }

    /**
     * @expectedException \Sabberworm\CSS\Parsing\UnexpectedTokenException
     * Credit: This test by @sabberworm (from
     *     https://github.com/sabberworm/PHP-CSS-Parser/pull/105#issuecomment-229643910 )
     */
    public function testUnexpectedTokenExceptionLineNo()
    {
        $oParser = new Parser("\ntest: 1;", Settings::create()->beStrict());
        try {
            $oParser->parse();
        } catch (UnexpectedTokenException $e) {
            $this->assertSame(2, $e->getLineNo());
            throw $e;
        }
    }

    /**
     * @expectedException \Sabberworm\CSS\Parsing\UnexpectedTokenException
     */
    public function testIeHacksStrictParsing()
    {
        // We can't strictly parse IE hacks.
        $this->parsedStructureForFile('ie-hacks', Settings::create()->beStrict());
    }

    public function testIeHacksParsing()
    {
        $oDoc = $this->parsedStructureForFile('ie-hacks', Settings::create()->withLenientParsing(true));
        $sExpected = 'p {padding-right: .75rem \9;background-image: none \9;color: red \9\0;'
            . 'background-color: red \9\0;background-color: red \9\0 !important;content: "red 	\0";content: "red઼";}';
        $this->assertEquals($sExpected, $oDoc->render());
    }

    /**
     * @depends testFiles
     */
    public function testCommentExtracting()
    {
        $oDoc = $this->parsedStructureForFile('comments');
        $aNodes = $oDoc->getContents();

        // Import property.
        $importComments = $aNodes[0]->getComments();
        $this->assertCount(1, $importComments);
        $this->assertEquals("*\n * Comments Hell.\n ", $importComments[0]->getComment());

        // Declaration block.
        $fooBarBlock = $aNodes[1];
        $fooBarBlockComments = $fooBarBlock->getComments();
        // TODO Support comments in selectors.
        // $this->assertCount(2, $fooBarBlockComments);
        // $this->assertEquals("* Number 4 *", $fooBarBlockComments[0]->getComment());
        // $this->assertEquals("* Number 5 *", $fooBarBlockComments[1]->getComment());

        // Declaration rules.
        $fooBarRules = $fooBarBlock->getRules();
        $fooBarRule = $fooBarRules[0];
        $fooBarRuleComments = $fooBarRule->getComments();
        $this->assertCount(1, $fooBarRuleComments);
        $this->assertEquals(" Number 6 ", $fooBarRuleComments[0]->getComment());

        // Media property.
        $mediaComments = $aNodes[2]->getComments();
        $this->assertCount(0, $mediaComments);

        // Media children.
        $mediaRules = $aNodes[2]->getContents();
        $fooBarComments = $mediaRules[0]->getComments();
        $this->assertCount(1, $fooBarComments);
        $this->assertEquals("* Number 10 *", $fooBarComments[0]->getComment());

        // Media -> declaration -> rule.
        $fooBarRules = $mediaRules[0]->getRules();
        $fooBarChildComments = $fooBarRules[0]->getComments();
        $this->assertCount(1, $fooBarChildComments);
        $this->assertEquals("* Number 10b *", $fooBarChildComments[0]->getComment());
    }

    public function testFlatCommentExtracting()
    {
        $parser = new Parser('div {/*Find Me!*/left:10px; text-align:left;}');
        $doc = $parser->parse();
        $contents = $doc->getContents();
        $divRules = $contents[0]->getRules();
        $comments = $divRules[0]->getComments();
        $this->assertCount(1, $comments);
        $this->assertEquals("Find Me!", $comments[0]->getComment());
    }

    public function testTopLevelCommentExtracting()
    {
        $parser = new Parser('/*Find Me!*/div {left:10px; text-align:left;}');
        $doc = $parser->parse();
        $contents = $doc->getContents();
        $comments = $contents[0]->getComments();
        $this->assertCount(1, $comments);
        $this->assertEquals("Find Me!", $comments[0]->getComment());
    }

    /**
     * @expectedException \Sabberworm\CSS\Parsing\UnexpectedTokenException
     */
    public function testMicrosoftFilterStrictParsing()
    {
        $oDoc = $this->parsedStructureForFile('ms-filter', Settings::create()->beStrict());
    }

    public function testMicrosoftFilterParsing()
    {
        $oDoc = $this->parsedStructureForFile('ms-filter');
        $sExpected = '.test {filter: progid:DXImageTransform.Microsoft.gradient(startColorstr="#80000000",'
            . 'endColorstr="#00000000",GradientType=1);}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testLargeSizeValuesInFile()
    {
        $oDoc = $this->parsedStructureForFile('large-z-index', Settings::create()->withMultibyteSupport(false));
        $sExpected = '.overlay {z-index: 10000000000000000000000;}';
        $this->assertSame($sExpected, $oDoc->render());
    }

    public function testLonelyImport()
    {
        $oDoc = $this->parsedStructureForFile('lonely-import');
        $sExpected = "@import url(\"example.css\") only screen and (max-width: 600px);";
        $this->assertSame($sExpected, $oDoc->render());
    }
}