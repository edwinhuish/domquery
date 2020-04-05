<?php

namespace DQTests\Dom;

use DQ\DomQuery;
use DQTests\TestCaseBase;

class MethodsTest extends TestCaseBase
{
    public function testText()
    {
        $dom = new DomQuery('<p> <a>1</a> <a>2,3</a> <a>4</a> <span></span> </p>');

        $links = $dom->find('a');

        $text = $links->text();

        $this->assertEquals('1', $text);
    }

    public function testTexts()
    {
        $dom = new DomQuery('<p> <a>1</a> <a>2,3</a> <a>4</a> <span></span> </p>');

        $links = $dom->find('a');

        $texts = $links->texts();

        $this->assertEquals(['1', '2,3', '4'], $texts);
    }

    public function testHtml()
    {
        $dom = new DomQuery('<p> <a>1</a> <a>2,3</a> <a>4</a> <span></span> </p>');

        $links = $dom->find('a');

        $html = $links->html();

        $this->assertEquals('1', $html);
    }

    public function testHtmls()
    {
        $dom = new DomQuery('<p> <a>1</a> <a>2,3</a> <a>4</a> <span></span> </p>');

        $links = $dom->find('a');

        $htmls = $links->htmls();

        $this->assertEquals(['1', '2,3', '4'], $htmls);
    }

    public function testOuterHtml()
    {
        $dom = new DomQuery('<p> <a id="test">1</a> <a>2,3</a> <a>4</a> <span></span> </p>');

        $link = $dom->find('#test');

        $outer1 = $link->outerHTML;
        $outer2 = $link->getOuterHtml();

        $this->assertEquals('<a id="test">1</a>', $outer1);
        $this->assertEquals('<a id="test">1</a>', $outer2);
    }

    public function testAppendTo()
    {
        $dom = new DomQuery('<div class="container">');
        $div = DomQuery::create('<div id="el1">')->appendTo($dom);
        DomQuery::create('<div id="el2">')->appendTo($div);

        $html = $dom->getOuterHtml();

        $this->assertEquals('<div class="container"><div id="el1"><div id="el2"></div></div></div>', $html);
    }

    public function testPrependTo()
    {
        $dom = new DomQuery('<div class="container"><a/><a/></div>');
        $div = DomQuery::create('<div id="el1"><a/><a/></div>')->prependTo($dom);
        DomQuery::create('<div id="el2">')->prependTo($div);

        $html = $dom->getOuterHtml();

        $this->assertEquals('<div class="container"><div id="el1"><div id="el2"></div><a></a><a></a></div><a></a><a></a></div>', $html);
    }
}
